using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Threading.Tasks;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace CalendarExplorer
{
    public class MainForm : Form
    {
        private readonly ServerManager _serverManager;
        private WebView2? _webView;
        private Panel? _splashPanel;
        private Label? _statusLabel;
        private ProgressBar? _progressBar;

        public MainForm()
        {
            _serverManager = new ServerManager();
            InitializeComponent();
        }

        private void InitializeComponent()
        {
            Text = "Google Calendar API Explorer";
            StartPosition = FormStartPosition.CenterScreen;
            Size = new Size(1380, 880);
            MinimumSize = new Size(1024, 700);
            BackColor = Color.FromArgb(11, 15, 25);
            KeyPreview = true;

            // Load app icon if available
            try
            {
                var iconPath = Path.Combine(AppContext.BaseDirectory, "app.ico");
                if (File.Exists(iconPath))
                {
                    Icon = new Icon(iconPath);
                }
            }
            catch { }

            // Splash / Loading Panel
            _splashPanel = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.FromArgb(11, 15, 25)
            };

            var titleLabel = new Label
            {
                Text = "Google Calendar API Explorer",
                Font = new Font("Segoe UI", 20, FontStyle.Bold),
                ForeColor = Color.White,
                AutoSize = true,
                BackColor = Color.Transparent
            };

            _statusLabel = new Label
            {
                Text = "Initializing desktop environment...",
                Font = new Font("Segoe UI", 10, FontStyle.Regular),
                ForeColor = Color.FromArgb(148, 163, 184),
                AutoSize = true,
                BackColor = Color.Transparent
            };

            _progressBar = new ProgressBar
            {
                Style = ProgressBarStyle.Marquee,
                MarqueeAnimationSpeed = 30,
                Width = 340,
                Height = 4,
                BackColor = Color.FromArgb(30, 41, 59),
                ForeColor = Color.FromArgb(37, 99, 235)
            };

            _splashPanel.Controls.Add(titleLabel);
            _splashPanel.Controls.Add(_statusLabel);
            _splashPanel.Controls.Add(_progressBar);

            _splashPanel.Resize += (s, e) =>
            {
                int centerX = _splashPanel.ClientSize.Width / 2;
                int centerY = _splashPanel.ClientSize.Height / 2;

                titleLabel.Location = new Point(centerX - titleLabel.Width / 2, centerY - 60);
                _statusLabel.Location = new Point(centerX - _statusLabel.Width / 2, centerY - 10);
                _progressBar.Location = new Point(centerX - _progressBar.Width / 2, centerY + 25);
            };

            // WebView2 Control
            _webView = new WebView2
            {
                Dock = DockStyle.Fill,
                Visible = false
            };

            Controls.Add(_webView);
            Controls.Add(_splashPanel);

            KeyDown += OnFormKeyDown;
            FormClosing += OnFormClosing;
        }

        protected override async void OnShown(EventArgs e)
        {
            base.OnShown(e);
            await StartAndLoadAppAsync();
        }

        private async Task StartAndLoadAppAsync()
        {
            try
            {
                UpdateStatus("Connecting to application server...");
                var targetUrl = await _serverManager.StartOrConnectAsync(msg =>
                {
                    Invoke(() => UpdateStatus(msg));
                });

                UpdateStatus("Preparing web view...");
                var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                var dataFolder = Path.Combine(localAppData, "GoogleCalendarExplorer", "WebView2Profile");
                Directory.CreateDirectory(dataFolder);

                var env = await CoreWebView2Environment.CreateAsync(null, dataFolder);
                await _webView!.EnsureCoreWebView2Async(env);

                _webView.CoreWebView2.Settings.IsStatusBarEnabled = false;
                _webView.CoreWebView2.Settings.AreDevToolsEnabled = true;
                _webView.CoreWebView2.Settings.IsZoomControlEnabled = true;

                // Handle external links (open in system default browser)
                _webView.CoreWebView2.NewWindowRequested += (s, args) =>
                {
                    var uri = args.Uri;
                    if (IsExternalUrl(uri))
                    {
                        args.Handled = true;
                        try
                        {
                            Process.Start(new ProcessStartInfo(uri) { UseShellExecute = true });
                        }
                        catch { }
                    }
                };

                _webView.CoreWebView2.NavigationCompleted += (s, args) =>
                {
                    if (args.IsSuccess)
                    {
                        _splashPanel!.Visible = false;
                        _webView.Visible = true;
                        _webView.Focus();
                    }
                    else
                    {
                        UpdateStatus($"Failed to load interface: {args.WebErrorStatus}");
                    }
                };

                UpdateStatus($"Loading workbench at {targetUrl}...");
                _webView.CoreWebView2.Navigate(targetUrl);
            }
            catch (Exception ex)
            {
                UpdateStatus($"Error: {ex.Message}");
                MessageBox.Show(
                    $"Unable to start the Calendar Explorer desktop app:\n\n{ex.Message}\n\nPlease verify that PHP 8.3+ is installed and accessible.",
                    "Startup Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
            }
        }

        private void UpdateStatus(string message)
        {
            if (_statusLabel != null)
            {
                _statusLabel.Text = message;
                if (_splashPanel != null)
                {
                    _statusLabel.Location = new Point(
                        _splashPanel.ClientSize.Width / 2 - _statusLabel.Width / 2,
                        _splashPanel.ClientSize.Height / 2 - 10
                    );
                }
            }
        }

        private static bool IsExternalUrl(string url)
        {
            if (string.IsNullOrWhiteSpace(url)) return false;

            // Allow OAuth and local callbacks inside the WebView
            if (url.Contains("accounts.google.com", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("fwd.host", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("127.0.0.1", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("localhost", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("calendar-integration.test", StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }

            // External documentation, Google Meet, Google Cloud Console open in default browser
            if (url.Contains("meet.google.com", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("console.cloud.google.com", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("developers.google.com", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("github.com", StringComparison.OrdinalIgnoreCase) ||
                url.Contains("cloud.laravel.com", StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }

            return false;
        }

        private void OnFormKeyDown(object? sender, KeyEventArgs e)
        {
            // F5 or Ctrl+R to reload
            if (e.KeyCode == Keys.F5 || (e.Control && e.KeyCode == Keys.R))
            {
                _webView?.CoreWebView2?.Reload();
                e.Handled = true;
            }
            // F12 or Ctrl+Shift+I to open DevTools
            else if (e.KeyCode == Keys.F12 || (e.Control && e.Shift && e.KeyCode == Keys.I))
            {
                _webView?.CoreWebView2?.OpenDevToolsWindow();
                e.Handled = true;
            }
        }

        private void OnFormClosing(object? sender, FormClosingEventArgs e)
        {
            _serverManager.Stop();
        }
    }
}

