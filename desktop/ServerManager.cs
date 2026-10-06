using System;
using System.Diagnostics;
using System.IO;
using System.Net;
using System.Net.Http;
using System.Net.Sockets;
using System.Threading.Tasks;

namespace CalendarExplorer
{
    public class ServerManager
    {
        private static readonly HttpClient HttpClient = new HttpClient(new HttpClientHandler
        {
            ServerCertificateCustomValidationCallback = (_, _, _, _) => true
        })
        {
            Timeout = TimeSpan.FromMilliseconds(1200)
        };

        public string ProjectRoot { get; private set; }
        public string ActiveUrl { get; private set; } = string.Empty;
        public Process? SpawnedServerProcess { get; private set; }

        public ServerManager()
        {
            ProjectRoot = ResolveProjectRoot();
        }

        public static string ResolveProjectRoot()
        {
            var current = AppContext.BaseDirectory;
            while (!string.IsNullOrEmpty(current))
            {
                if (File.Exists(Path.Combine(current, "artisan")))
                {
                    return current;
                }
                var parent = Directory.GetParent(current);
                if (parent == null) break;
                current = parent.FullName;
            }

            var workingDir = Directory.GetCurrentDirectory();
            if (File.Exists(Path.Combine(workingDir, "artisan")))
            {
                return workingDir;
            }

            return AppContext.BaseDirectory;
        }

        public async Task<string> StartOrConnectAsync(Action<string>? statusCallback = null)
        {
            // 1. Check if local Herd site is already running
            statusCallback?.Invoke("Checking local Herd site (calendar-integration.test)...");
            if (await IsUrlHealthyAsync("http://calendar-integration.test"))
            {
                ActiveUrl = "http://calendar-integration.test";
                return ActiveUrl;
            }

            // 2. Check if a local php artisan serve on port 8000 is already active
            statusCallback?.Invoke("Checking localhost:8000...");
            if (await IsUrlHealthyAsync("http://127.0.0.1:8000"))
            {
                ActiveUrl = "http://127.0.0.1:8000";
                return ActiveUrl;
            }

            // 3. Otherwise, spawn background php artisan serve
            statusCallback?.Invoke("Starting background PHP application server...");
            int port = GetAvailableTcpPort(8000);
            string phpBinary = LocatePhpBinary();

            var startInfo = new ProcessStartInfo
            {
                FileName = phpBinary,
                Arguments = $"artisan serve --host=127.0.0.1 --port={port}",
                WorkingDirectory = ProjectRoot,
                CreateNoWindow = true,
                UseShellExecute = false,
                WindowStyle = ProcessWindowStyle.Hidden,
                RedirectStandardOutput = true,
                RedirectStandardError = true
            };

            var process = new Process { StartInfo = startInfo, EnableRaisingEvents = true };

            process.OutputDataReceived += (_, e) =>
            {
                if (!string.IsNullOrEmpty(e.Data))
                {
                    Debug.WriteLine($"[PHP Server Out] {e.Data}");
                }
            };
            process.ErrorDataReceived += (_, e) =>
            {
                if (!string.IsNullOrEmpty(e.Data))
                {
                    Debug.WriteLine($"[PHP Server Err] {e.Data}");
                }
            };

            process.Start();
            process.BeginOutputReadLine();
            process.BeginErrorReadLine();

            SpawnedServerProcess = process;
            ActiveUrl = $"http://127.0.0.1:{port}";

            // Poll until server is ready
            var sw = Stopwatch.StartNew();
            bool isReady = false;
            while (sw.ElapsedMilliseconds < 15000)
            {
                if (process.HasExited)
                {
                    throw new Exception($"PHP server exited unexpectedly with code {process.ExitCode}. Ensure PHP 8.3+ is installed and dependencies are configured.");
                }

                if (await IsUrlHealthyAsync(ActiveUrl))
                {
                    isReady = true;
                    break;
                }

                await Task.Delay(200);
            }

            if (!isReady)
            {
                Stop();
                throw new TimeoutException($"Timed out waiting for PHP server at {ActiveUrl} to become responsive.");
            }

            return ActiveUrl;
        }

        public async Task<bool> IsUrlHealthyAsync(string url)
        {
            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromMilliseconds(1200));
                var response = await HttpClient.GetAsync(url, cts.Token);
                return ((int)response.StatusCode >= 200 && (int)response.StatusCode < 400);
            }
            catch
            {
                return false;
            }
        }

        public static string LocatePhpBinary()
        {
            // Check common specific paths
            string[] knownPaths =
            {
                @"C:\php\8.5.10\php.exe",
                @"C:\php\php.exe",
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), @".config\herd\bin\php.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), @"Herd\bin\php.exe"),
            };

            foreach (var path in knownPaths)
            {
                if (File.Exists(path))
                {
                    return path;
                }
            }

            // Search PATH
            var envPath = Environment.GetEnvironmentVariable("PATH") ?? string.Empty;
            foreach (var folder in envPath.Split(Path.PathSeparator, StringSplitOptions.RemoveEmptyEntries))
            {
                try
                {
                    var full = Path.Combine(folder.Trim(), "php.exe");
                    if (File.Exists(full))
                    {
                        return full;
                    }
                }
                catch { }
            }

            return "php";
        }

        public static int GetAvailableTcpPort(int defaultPort = 8000)
        {
            try
            {
                var listener = new TcpListener(IPAddress.Loopback, defaultPort);
                listener.Start();
                listener.Stop();
                return defaultPort;
            }
            catch
            {
                var listener = new TcpListener(IPAddress.Loopback, 0);
                listener.Start();
                int port = ((IPEndPoint)listener.LocalEndpoint).Port;
                listener.Stop();
                return port;
            }
        }

        public void Stop()
        {
            if (SpawnedServerProcess != null && !SpawnedServerProcess.HasExited)
            {
                try
                {
                    SpawnedServerProcess.Kill(entireProcessTree: true);
                    SpawnedServerProcess.Dispose();
                }
                catch { }
                finally
                {
                    SpawnedServerProcess = null;
                }
            }
        }
    }
}

