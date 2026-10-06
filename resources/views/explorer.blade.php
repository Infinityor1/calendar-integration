<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
{{--
    Google Calendar API Explorer & Workbench View
    
    1) General Naming Conventions:
    - DOM IDs: kebab-case prefixed by role (e.g. `tab-btn-*` for navigation, `view-*` for panels, `current-*` for request details, `resp-*` for response outputs).
    - JavaScript Functions: camelCase verbs (`switchMainTab`, `switchResponseTab`, `selectRequest`, `executeCurrentRequest`, `setExecutionMode`, `renderResponse`).
    - CSS Classes: Standard Tailwind CSS utility classes with slate-950 dark developer theme.

    2) Function of the Code:
    - Serves as the interactive single-page developer workbench.
    - Houses the API Explorer Tab (catalog sidebar, request parameter editor, response viewer, actionable guide)
      and the Settings & Credentials Tab (Google OAuth client config, scope selection, manual token bypass, setup checklist).
--}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Google Calendar API Explorer & Workbench</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Instrument Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        pre code {
            font-family: 'JetBrains Mono', monospace;
        }
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #0f172a;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 3px;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans selection:bg-blue-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="h-16 border-b border-slate-800 bg-slate-900/90 backdrop-blur px-6 flex items-center justify-between shrink-0 z-20">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/20 text-white font-bold text-lg">
                    📅
                </div>
                <div>
                    <h1 class="text-sm font-bold tracking-tight text-white flex items-center gap-2">
                        Google Calendar API Explorer
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-mono bg-blue-500/10 text-blue-400 border border-blue-500/20">v3 REST</span>
                    </h1>
                    <p class="text-xs text-slate-400">Interactive Request & Response Workbench</p>
                </div>
            </div>

            <!-- View Switcher Tabs -->
            <div class="flex items-center bg-slate-950 p-1 rounded-lg border border-slate-800 text-xs font-medium">
                <button 
                    onclick="switchMainTab('explorer')" 
                    id="tab-btn-explorer"
                    class="px-3.5 py-1.5 rounded-md transition flex items-center gap-2 {{ $activeTab === 'explorer' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    API Explorer
                </button>
                <button 
                    onclick="switchMainTab('settings')" 
                    id="tab-btn-settings"
                    class="px-3.5 py-1.5 rounded-md transition flex items-center gap-2 {{ $activeTab === 'settings' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200' }}">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Settings & Credentials
                    @if(!$isConnected)
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    @endif
                </button>
            </div>
        </div>

        <!-- Right Header Status Controls -->
        <div class="flex items-center gap-4">
            <!-- Mode Toggle (Live vs Mock) -->
            <div class="flex items-center gap-2 bg-slate-950 px-3 py-1.5 rounded-lg border border-slate-800 text-xs">
                <span class="text-slate-400">Mode:</span>
                <button 
                    id="mode-mock-btn"
                    onclick="setExecutionMode('mock')" 
                    class="px-2 py-0.5 rounded font-mono font-medium transition {{ $mode === 'mock' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'text-slate-500 hover:text-slate-300' }}">
                    Mock Preview
                </button>
                <button 
                    id="mode-live-btn"
                    onclick="setExecutionMode('live')" 
                    class="px-2 py-0.5 rounded font-mono font-medium transition {{ $mode === 'live' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'text-slate-500 hover:text-slate-300' }}">
                    Live Google API
                </button>
            </div>

            <!-- Google OAuth Badge -->
            @if($isConnected && $userProfile)
                <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-950/40 border border-emerald-800/40 rounded-lg text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-slate-300">{{ $userProfile['email'] ?? 'Google Account' }}</span>
                </div>
            @elseif($isConnected)
                <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-950/40 border border-emerald-800/40 rounded-lg text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="text-emerald-300 font-medium">OAuth Active</span>
                </div>
            @else
                <button 
                    onclick="switchMainTab('settings')" 
                    class="flex items-center gap-2 px-3 py-1.5 bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 rounded-lg text-xs text-slate-300 transition">
                    <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                    <span>Not Connected</span>
                    <span class="text-[10px] text-blue-400 underline">Setup</span>
                </button>
            @endif
        </div>
    </header>

    <!-- Global Flash Notifications -->
    @if(session('success'))
        <div class="bg-emerald-900/60 border-b border-emerald-700/50 px-6 py-2.5 text-xs text-emerald-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-rose-900/60 border-b border-rose-700/50 px-6 py-2.5 text-xs text-rose-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>⚠</span>
                <span>{{ session('error') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white">&times;</button>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden relative">

        <!-- ========================================================================= -->
        <!-- TAB 1: API EXPLORER WORKBENCH                                            -->
        <!-- ========================================================================= -->
        <div id="view-explorer" class="h-full flex {{ $activeTab === 'explorer' ? '' : 'hidden' }}">
            
            <!-- Left Sidebar: Request Catalog -->
            <aside class="w-72 border-r border-slate-800 bg-slate-900/50 flex flex-col shrink-0 custom-scroll overflow-y-auto">
                <div class="p-3 border-b border-slate-800/80 bg-slate-900/80 sticky top-0 z-10">
                    <div class="relative">
                        <input 
                            type="text" 
                            id="catalog-search" 
                            placeholder="Filter requests..." 
                            oninput="filterCatalog(this.value)"
                            class="w-full bg-slate-950 border border-slate-800 rounded-md px-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition">
                    </div>
                </div>

                <div class="p-2 space-y-4">
                    @php
                        $groupedCatalog = collect($catalog)->groupBy('resource');
                    @endphp

                    @foreach($groupedCatalog as $resource => $requests)
                        <div class="catalog-group">
                            <h3 class="px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                {{ $resource }}
                            </h3>
                            <div class="mt-1 space-y-0.5">
                                @foreach($requests as $req)
                                    @php
                                        $methodColor = match($req['http_method']) {
                                            'GET' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
                                            'POST' => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
                                            'PATCH' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                                            'DELETE' => 'text-rose-400 bg-rose-500/10 border-rose-500/20',
                                            default => 'text-slate-400 bg-slate-500/10 border-slate-500/20',
                                        };
                                        $isSelected = $activeRequestId === $req['id'];
                                    @endphp
                                    <button 
                                        onclick="selectRequest('{{ $req['id'] }}')"
                                        data-req-id="{{ $req['id'] }}"
                                        data-req-title="{{ strtolower($req['summary'] . ' ' . $req['method_name']) }}"
                                        class="catalog-item w-full text-left px-2.5 py-2 rounded-md text-xs transition flex items-center justify-between group {{ $isSelected ? 'bg-blue-600/20 border border-blue-500/30 text-white' : 'hover:bg-slate-800/60 text-slate-300' }}">
                                        <div class="truncate mr-2">
                                            <div class="font-medium truncate">{{ $req['summary'] }}</div>
                                            <div class="text-[10px] text-slate-500 font-mono truncate">{{ $req['method_name'] }}</div>
                                        </div>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase shrink-0 font-bold {{ $methodColor }}">
                                            {{ $req['http_method'] }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </aside>

            <!-- Center & Right: Two-Pane Workbench -->
            <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
                
                <!-- Center Pane: Request Inspector -->
                <section class="flex-1 flex flex-col border-r border-slate-800 bg-slate-950 overflow-y-auto custom-scroll">
                    
                    <!-- Request Header & Method Display -->
                    <div class="p-5 border-b border-slate-800/80 bg-slate-900/40">
                        <div class="flex items-center gap-2 mb-2">
                            @php
                                $methodBadge = match($activeRequest['http_method']) {
                                    'GET' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                    'POST' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                    'PATCH' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                    'DELETE' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                    default => 'bg-slate-500/20 text-slate-300 border-slate-500/30',
                                };
                            @endphp
                            <span id="current-method-badge" class="px-2 py-0.5 rounded text-xs font-mono font-bold border {{ $methodBadge }}">
                                {{ $activeRequest['http_method'] }}
                            </span>
                            <span id="current-method-name" class="text-xs font-mono text-slate-400">
                                {{ $activeRequest['method_name'] }}
                            </span>
                        </div>
                        <h2 id="current-summary" class="text-lg font-bold text-white mb-1">
                            {{ $activeRequest['summary'] }}
                        </h2>
                        <p id="current-description" class="text-xs text-slate-400 mb-3 leading-relaxed">
                            {{ $activeRequest['description'] }}
                        </p>

                        <!-- Live Endpoint URL Box -->
                        <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 rounded-lg p-2 font-mono text-xs text-slate-300">
                            <span class="text-slate-500 select-none">ENDPOINT:</span>
                            <span id="current-full-url" class="text-blue-400 flex-1 break-all">
                                {{ $activeRequest['full_url'] }}
                            </span>
                            <button onclick="copyToClipboard(document.getElementById('current-full-url').innerText, this)" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 rounded text-[11px] text-slate-300 transition">
                                Copy
                            </button>
                        </div>
                    </div>

                    <!-- App Actions & Permissions Banner -->
                    <div class="p-5 border-b border-slate-800/80 bg-gradient-to-r from-blue-950/20 via-slate-900/20 to-slate-950">
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 flex items-center justify-center shrink-0 mt-0.5 text-xs font-bold">
                                ℹ
                            </div>
                            <div class="flex-1 space-y-2">
                                <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Actions & Prerequisites for Your App</h4>
                                <div class="space-y-1.5 text-xs text-slate-300">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Required OAuth Scopes:</span>
                                        <div id="current-scopes-list" class="flex flex-wrap gap-1.5">
                                            @foreach($activeRequest['scopes'] as $scope)
                                                <span class="px-2 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[11px] text-blue-300">
                                                    {{ Str::afterLast($scope, '/') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <ul id="current-cloud-actions" class="list-disc list-inside text-slate-400 space-y-0.5 pt-1">
                                        @foreach($activeRequest['cloud_actions'] as $action)
                                            <li>{{ $action }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Request Parameters Form -->
                    <div class="p-5 flex-1 space-y-5">
                        
                        <!-- Timezone Parameter (for queries affected by it) -->
                        @php
                            $tzAffected = ['events_list', 'events_get', 'freebusy_query', 'events_insert_meet', 'events_patch'];
                            $isTzAffected = in_array($activeRequestId, $tzAffected, true);
                            $selectedTz = $activeRequest['timezone'] ?? ($activeRequest['query_params']['timeZone'] ?? ($activeRequest['body']['timeZone'] ?? ($activeRequest['body']['start']['timeZone'] ?? 'UTC')));
                            $isTzModified = $selectedTz !== 'UTC';
                        @endphp
                        <div id="timezone-param-section" class="{{ $isTzAffected ? '' : 'hidden' }}">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>Timezone</span>
                                    <span class="text-[10px] text-blue-400 font-mono font-normal lowercase">(timeZone)</span>
                                </label>
                                <span class="text-[10px] text-slate-500 font-mono">Click ↺ to reset</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-48 flex items-center gap-1.5 shrink-0">
                                    <span class="font-mono text-xs text-slate-400 truncate" title="timeZone">timeZone</span>
                                    <button 
                                        type="button" 
                                        onclick="resetTimezoneParam()" 
                                        id="reset-btn-timezone"
                                        title="Reset timeZone to default (UTC)" 
                                        aria-label="Reset timeZone to default"
                                        class="reset-param-btn p-1 rounded transition shrink-0 {{ $isTzModified ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20' : 'text-slate-500 hover:text-blue-400 hover:bg-slate-800' }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex-1 relative">
                                    <select 
                                        id="timezone-select"
                                        onchange="handleTimezoneChange(this.value)"
                                        class="w-full bg-slate-900 border {{ $isTzModified ? 'border-amber-500/40 bg-amber-950/10' : 'border-slate-800' }} rounded px-3 py-1.5 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none transition cursor-pointer appearance-none pr-8">
                                        <optgroup label="Standard / Universal">
                                            <option value="UTC" {{ $selectedTz === 'UTC' ? 'selected' : '' }}>UTC (Coordinated Universal Time)</option>
                                        </optgroup>
                                        <optgroup label="Asia & Pacific">
                                            <option value="Asia/Singapore" {{ $selectedTz === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT +08:00)</option>
                                            <option value="Asia/Tokyo" {{ $selectedTz === 'Asia/Tokyo' ? 'selected' : '' }}>Asia/Tokyo (JST +09:00)</option>
                                            <option value="Asia/Shanghai" {{ $selectedTz === 'Asia/Shanghai' ? 'selected' : '' }}>Asia/Shanghai (CST +08:00)</option>
                                            <option value="Asia/Hong_Kong" {{ $selectedTz === 'Asia/Hong_Kong' ? 'selected' : '' }}>Asia/Hong_Kong (HKT +08:00)</option>
                                            <option value="Asia/Kolkata" {{ $selectedTz === 'Asia/Kolkata' ? 'selected' : '' }}>Asia/Kolkata (IST +05:30)</option>
                                            <option value="Asia/Dubai" {{ $selectedTz === 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai (GST +04:00)</option>
                                            <option value="Asia/Bangkok" {{ $selectedTz === 'Asia/Bangkok' ? 'selected' : '' }}>Asia/Bangkok (ICT +07:00)</option>
                                            <option value="Asia/Seoul" {{ $selectedTz === 'Asia/Seoul' ? 'selected' : '' }}>Asia/Seoul (KST +09:00)</option>
                                            <option value="Asia/Jakarta" {{ $selectedTz === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB +07:00)</option>
                                            <option value="Australia/Sydney" {{ $selectedTz === 'Australia/Sydney' ? 'selected' : '' }}>Australia/Sydney (AEST +10:00)</option>
                                            <option value="Australia/Melbourne" {{ $selectedTz === 'Australia/Melbourne' ? 'selected' : '' }}>Australia/Melbourne (AEST +10:00)</option>
                                            <option value="Pacific/Auckland" {{ $selectedTz === 'Pacific/Auckland' ? 'selected' : '' }}>Pacific/Auckland (NZST +12:00)</option>
                                        </optgroup>
                                        <optgroup label="Europe">
                                            <option value="Europe/London" {{ $selectedTz === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT/BST +00:00)</option>
                                            <option value="Europe/Paris" {{ $selectedTz === 'Europe/Paris' ? 'selected' : '' }}>Europe/Paris (CET +01:00)</option>
                                            <option value="Europe/Berlin" {{ $selectedTz === 'Europe/Berlin' ? 'selected' : '' }}>Europe/Berlin (CET +01:00)</option>
                                            <option value="Europe/Amsterdam" {{ $selectedTz === 'Europe/Amsterdam' ? 'selected' : '' }}>Europe/Amsterdam (CET +01:00)</option>
                                            <option value="Europe/Zurich" {{ $selectedTz === 'Europe/Zurich' ? 'selected' : '' }}>Europe/Zurich (CET +01:00)</option>
                                            <option value="Europe/Rome" {{ $selectedTz === 'Europe/Rome' ? 'selected' : '' }}>Europe/Rome (CET +01:00)</option>
                                            <option value="Europe/Madrid" {{ $selectedTz === 'Europe/Madrid' ? 'selected' : '' }}>Europe/Madrid (CET +01:00)</option>
                                            <option value="Europe/Athens" {{ $selectedTz === 'Europe/Athens' ? 'selected' : '' }}>Europe/Athens (EET +02:00)</option>
                                        </optgroup>
                                        <optgroup label="Americas">
                                            <option value="America/New_York" {{ $selectedTz === 'America/New_York' ? 'selected' : '' }}>America/New_York (Eastern ET -05:00)</option>
                                            <option value="America/Chicago" {{ $selectedTz === 'America/Chicago' ? 'selected' : '' }}>America/Chicago (Central CT -06:00)</option>
                                            <option value="America/Denver" {{ $selectedTz === 'America/Denver' ? 'selected' : '' }}>America/Denver (Mountain MT -07:00)</option>
                                            <option value="America/Los_Angeles" {{ $selectedTz === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los_Angeles (Pacific PT -08:00)</option>
                                            <option value="America/Toronto" {{ $selectedTz === 'America/Toronto' ? 'selected' : '' }}>America/Toronto (Eastern ET -05:00)</option>
                                            <option value="America/Vancouver" {{ $selectedTz === 'America/Vancouver' ? 'selected' : '' }}>America/Vancouver (Pacific PT -08:00)</option>
                                            <option value="America/Sao_Paulo" {{ $selectedTz === 'America/Sao_Paulo' ? 'selected' : '' }}>America/Sao_Paulo (BRT -03:00)</option>
                                            <option value="America/Mexico_City" {{ $selectedTz === 'America/Mexico_City' ? 'selected' : '' }}>America/Mexico_City (CST -06:00)</option>
                                            <option value="America/Phoenix" {{ $selectedTz === 'America/Phoenix' ? 'selected' : '' }}>America/Phoenix (MST -07:00)</option>
                                            <option value="Pacific/Honolulu" {{ $selectedTz === 'Pacific/Honolulu' ? 'selected' : '' }}>Pacific/Honolulu (HST -10:00)</option>
                                        </optgroup>
                                        <optgroup label="Africa & Middle East">
                                            <option value="Africa/Cairo" {{ $selectedTz === 'Africa/Cairo' ? 'selected' : '' }}>Africa/Cairo (EET +02:00)</option>
                                            <option value="Africa/Johannesburg" {{ $selectedTz === 'Africa/Johannesburg' ? 'selected' : '' }}>Africa/Johannesburg (SAST +02:00)</option>
                                            <option value="Asia/Jerusalem" {{ $selectedTz === 'Asia/Jerusalem' ? 'selected' : '' }}>Asia/Jerusalem (IST +02:00)</option>
                                            <option value="Africa/Nairobi" {{ $selectedTz === 'Africa/Nairobi' ? 'selected' : '' }}>Africa/Nairobi (EAT +03:00)</option>
                                        </optgroup>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400 text-xs">
                                        ▼
                                    </div>
                                </div>
                            </div>
                            <p id="timezone-hint" class="text-[11px] text-slate-500 mt-1.5 leading-tight">
                                {{ in_array($activeRequestId, ['events_list', 'events_get'], true) ? 'Sent via timeZone query parameter to format timestamps returned by Google Calendar.' : ($activeRequestId === 'freebusy_query' ? 'Sent in request body timeZone to specify the reference timezone for busy slots.' : 'Applied to start.timeZone and end.timeZone in the event payload.') }}
                            </p>
                        </div>
                        
                        <!-- Path Parameters -->
                        <div id="path-params-section" class="{{ empty($activeRequest['path_params']) ? 'hidden' : '' }}">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Path Variables</label>
                                <span class="text-[10px] text-slate-500 font-mono">Click ↺ to reset</span>
                            </div>
                            <div id="path-params-container" class="space-y-2">
                                @foreach($activeRequest['path_params'] ?? [] as $key => $val)
                                    @php
                                        $defaultVal = $catalog[$activeRequestId]['path_params'][$key] ?? $val;
                                        $isModified = (string)$val !== (string)$defaultVal;
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        <div class="w-48 flex items-center gap-1.5 shrink-0">
                                            <span class="font-mono text-xs text-slate-400 truncate" title="{{ '{' . $key . '}' }}">{{ '{' . $key . '}' }}</span>
                                            <button 
                                                type="button" 
                                                onclick="resetParam('path', '{{ $key }}')" 
                                                id="reset-btn-path-{{ $key }}"
                                                title="Reset {{ '{' . $key . '}' }} to default ({{ $defaultVal }})" 
                                                aria-label="Reset {{ '{' . $key . '}' }} to default"
                                                class="reset-param-btn p-1 rounded transition shrink-0 {{ $isModified ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20' : 'text-slate-500 hover:text-blue-400 hover:bg-slate-800' }}">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                        <input 
                                            type="text" 
                                            data-path-key="{{ $key }}"
                                            value="{{ $val }}"
                                            oninput="handleParamChange('path', '{{ $key }}', this.value)"
                                            class="path-param-input flex-1 bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none transition {{ $isModified ? 'border-amber-500/40 bg-amber-950/10' : '' }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Query Parameters -->
                        <div id="query-params-section" class="{{ empty($activeRequest['query_params']) ? 'hidden' : '' }}">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Query Parameters</label>
                                <span class="text-[10px] text-slate-500 font-mono">Click ↺ to reset</span>
                            </div>
                            <div id="query-params-container" class="space-y-2">
                                @foreach($activeRequest['query_params'] ?? [] as $key => $val)
                                    @if($key === 'timeZone')
                                        <input type="hidden" class="query-param-input" data-query-key="timeZone" id="query-param-timezone-hidden" value="{{ $val }}">
                                        @continue
                                    @endif
                                    @php
                                        $defaultVal = $catalog[$activeRequestId]['query_params'][$key] ?? $val;
                                        $isModified = (string)$val !== (string)$defaultVal;
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        <div class="w-48 flex items-center gap-1.5 shrink-0">
                                            <span class="font-mono text-xs text-slate-400 truncate" title="{{ $key }}">{{ $key }}</span>
                                            <button 
                                                type="button" 
                                                onclick="resetParam('query', '{{ $key }}')" 
                                                id="reset-btn-query-{{ $key }}"
                                                title="Reset {{ $key }} to default ({{ $defaultVal }})" 
                                                aria-label="Reset {{ $key }} to default"
                                                class="reset-param-btn p-1 rounded transition shrink-0 {{ $isModified ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20' : 'text-slate-500 hover:text-blue-400 hover:bg-slate-800' }}">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </div>
                                        <input 
                                            type="text" 
                                            data-query-key="{{ $key }}"
                                            value="{{ $val }}"
                                            oninput="handleParamChange('query', '{{ $key }}', this.value)"
                                            class="query-param-input flex-1 bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none transition {{ $isModified ? 'border-amber-500/40 bg-amber-950/10' : '' }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- JSON Request Body Editor -->
                        <div id="request-body-section" class="{{ empty($activeRequest['body']) ? 'hidden' : '' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider">JSON Request Payload</label>
                                    <button 
                                        type="button" 
                                        onclick="resetRequestBodyJson()" 
                                        id="reset-btn-body"
                                        title="Reset payload to default" 
                                        aria-label="Reset payload to default"
                                        class="reset-param-btn p-1 rounded transition shrink-0 text-slate-500 hover:text-blue-400 hover:bg-slate-800">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="formatRequestBodyJson()" class="text-[11px] text-slate-400 hover:text-slate-200 transition">
                                        Prettify JSON
                                    </button>
                                    <button onclick="resetRequestBodyJson()" class="text-[11px] text-blue-400 hover:text-blue-300 transition">
                                        Reset Default
                                    </button>
                                </div>
                            </div>
                            <textarea 
                                id="request-body-editor"
                                rows="9"
                                spellcheck="false"
                                oninput="handleBodyChange(this.value)"
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg p-3 text-xs font-mono text-slate-200 focus:border-blue-500 focus:outline-none custom-scroll leading-relaxed">{{ !empty($activeRequest['body']) ? (is_string($activeRequest['body']) ? $activeRequest['body'] : json_encode($activeRequest['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) : '' }}</textarea>
                        </div>
                    </div>

                    <!-- Bottom Sticky Action Bar -->
                    <div class="p-4 border-t border-slate-800 bg-slate-900/90 backdrop-blur sticky bottom-0 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-xs text-slate-400">
                            <span>Executing as:</span>
                            <span id="active-mode-label" class="font-mono px-2 py-0.5 rounded font-bold {{ $mode === 'live' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                {{ $mode === 'live' ? 'Live Google API' : 'Simulated Mock' }}
                            </span>
                        </div>
                        <button 
                            id="btn-send-request"
                            onclick="executeCurrentRequest()" 
                            class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium text-xs flex items-center gap-2 shadow-lg shadow-blue-600/30 transition transform active:scale-95">
                            <span id="btn-spinner" class="hidden animate-spin">⟳</span>
                            <span id="btn-icon">⚡</span>
                            <span id="btn-text">Send Request</span>
                        </button>
                    </div>
                </section>

                <!-- Right Pane: Response & Output Inspector -->
                <section class="flex-1 flex flex-col bg-slate-950 overflow-y-auto custom-scroll">
                    
                    <!-- Response Status & Latency Bar -->
                    <div class="p-5 border-b border-slate-800/80 bg-slate-900/40 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span id="response-status-badge" class="px-2.5 py-1 rounded text-xs font-mono font-bold bg-slate-800 text-slate-400 border border-slate-700">
                                Awaiting Execution
                            </span>
                            <span id="response-latency-badge" class="text-xs font-mono text-slate-500">
                                -- ms
                            </span>
                            <span id="response-mode-used" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hidden">
                                MOCK
                            </span>
                        </div>

                        <!-- Response View Tabs -->
                        <div class="flex items-center bg-slate-900 p-1 rounded-md border border-slate-800 text-xs">
                            <button onclick="switchResponseTab('json')" id="resp-tab-json" class="px-3 py-1 rounded bg-slate-800 text-white transition">
                                Formatted JSON
                            </button>
                            <button onclick="switchResponseTab('preview')" id="resp-tab-preview" class="px-3 py-1 rounded text-slate-400 hover:text-slate-200 transition">
                                Visual Preview
                            </button>
                            <button onclick="switchResponseTab('headers')" id="resp-tab-headers" class="px-3 py-1 rounded text-slate-400 hover:text-slate-200 transition">
                                Headers
                            </button>
                        </div>
                    </div>

                    <!-- Response Payload Body Area -->
                    <div class="flex-1 p-5 space-y-5">
                        
                        <!-- JSON View -->
                        <div id="resp-view-json" class="relative group">
                            <button 
                                onclick="copyToClipboard(document.getElementById('response-raw-json').innerText, this)"
                                class="absolute top-3 right-3 px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300 transition opacity-0 group-hover:opacity-100">
                                Copy JSON
                            </button>
                            <pre class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 text-xs font-mono text-slate-200 custom-scroll overflow-x-auto max-h-[460px] leading-relaxed"><code id="response-raw-json">// Click "Send Request" to execute this API call and inspect the output.
// In Mock Mode, realistic Google responses are returned instantly.
// In Live Mode, requests hit the live Google Calendar API using your OAuth token.</code></pre>
                        </div>

                        <!-- Visual Card Preview -->
                        <div id="resp-view-preview" class="hidden">
                            <div id="visual-preview-container" class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
                                <p class="text-xs text-slate-400 italic">Run a request to generate the interactive card preview.</p>
                            </div>
                        </div>

                        <!-- Headers View -->
                        <div id="resp-view-headers" class="hidden">
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 font-mono text-xs text-slate-300">
                                <table class="w-full text-left">
                                    <tbody id="response-headers-tbody" class="divide-y divide-slate-800">
                                        <tr><td class="py-1 text-slate-500">content-type</td><td class="py-1">application/json; charset=UTF-8</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Developer Actionable Insights Card -->
                        <div class="border border-slate-800 bg-slate-900/50 rounded-xl p-5 space-y-4">
                            <div class="flex items-center gap-2 border-b border-slate-800/80 pb-3">
                                <span class="text-base">💡</span>
                                <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider">What This Means for Your App</h4>
                            </div>

                            <!-- Key Fields Breakdown -->
                            <div>
                                <h5 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Key Return Properties</h5>
                                <div id="insights-key-fields" class="space-y-2 text-xs">
                                    @foreach($activeRequest['insights']['key_fields'] ?? [] as $field => $exp)
                                        <div class="bg-slate-950 p-2.5 rounded border border-slate-800/80">
                                            <span class="font-mono text-blue-400 font-semibold">{{ $field }}</span>
                                            <p class="text-slate-300 mt-0.5 leading-relaxed">{{ $exp }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Application Action Items -->
                            <div>
                                <h5 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Application Implementation Steps</h5>
                                <ul id="insights-app-actions" class="list-disc list-inside text-xs text-slate-300 space-y-1">
                                    @foreach($activeRequest['insights']['app_actions'] ?? [] as $action)
                                        <li>{{ $action }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Error & Edge Case Handling -->
                            <div>
                                <h5 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Error & Edge Cases to Handle</h5>
                                <ul id="insights-error-handling" class="list-disc list-inside text-xs text-amber-300/80 space-y-1">
                                    @foreach($activeRequest['insights']['error_handling'] ?? [] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: SETTINGS & CREDENTIALS                                            -->
        <!-- ========================================================================= -->
        <div id="view-settings" class="h-full overflow-y-auto custom-scroll p-6 lg:p-10 {{ $activeTab === 'settings' ? '' : 'hidden' }}">
            <div class="max-w-4xl mx-auto space-y-8">
                
                <!-- Settings Header -->
                <div>
                    <h2 class="text-xl font-bold text-white tracking-tight">Credentials & API Access Configuration</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Configure your Google Cloud OAuth 2.0 Client credentials to test against live Google accounts, or paste direct tokens.
                    </p>
                </div>

                <!-- Section 1: Active Connection Status Card -->
                <div class="border border-slate-800 bg-slate-900/60 rounded-xl p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full {{ $isConnected ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-bold text-sm">
                                {{ $isConnected ? '✓' : '○' }}
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">
                                    {{ $isConnected ? 'Connected to Google Calendar API' : 'Not Connected (Currently in Simulated Mock Mode)' }}
                                </h3>
                                <p class="text-xs text-slate-400">
                                    @if($isConnected && $userProfile)
                                        Authorized as <span class="text-emerald-400 font-medium">{{ $userProfile['email'] }}</span>
                                    @elseif($isConnected)
                                        Access token active in session
                                    @else
                                        Running in Mock Preview Mode. Connect below to enable Live Google API calls.
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($isConnected)
                            <form action="{{ route('explorer.oauth.disconnect') }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 text-xs font-medium transition">
                                    Disconnect & Purge Tokens
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($isConnected)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 text-xs font-mono">
                            <div class="bg-slate-950 p-3 rounded-lg border border-slate-800">
                                <span class="text-slate-500 block text-[10px] uppercase">Token Expiration</span>
                                <span class="text-slate-200 mt-1 block">
                                    @if($tokenExpiresAt)
                                        {{ now()->timestamp < $tokenExpiresAt ? 'Valid (~' . round(($tokenExpiresAt - now()->timestamp)/60) . ' mins remaining)' : 'Expired (Auto-refresh on next call)' }}
                                    @else
                                        Active
                                    @endif
                                </span>
                            </div>
                            <div class="bg-slate-950 p-3 rounded-lg border border-slate-800">
                                <span class="text-slate-500 block text-[10px] uppercase">Refresh Token</span>
                                <span class="text-slate-200 mt-1 block">
                                    {{ !empty($refreshToken) ? 'Available (Offline Access Granted)' : 'None (One-time Access Token)' }}
                                </span>
                            </div>
                            <div class="bg-slate-950 p-3 rounded-lg border border-slate-800">
                                <span class="text-slate-500 block text-[10px] uppercase">Execution Mode</span>
                                <span class="text-emerald-400 font-bold mt-1 block">
                                    Live Google API
                                </span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Section 2: OAuth Credentials & Connect Form -->
                <div class="border border-slate-800 bg-slate-900/60 rounded-xl p-6 space-y-6">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">1. Google OAuth 2.0 Web Client Credentials</h3>
                    
                    <form action="{{ route('explorer.settings.save') }}" method="POST" class="space-y-4">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Client ID</label>
                                <input 
                                    type="text" 
                                    name="client_id" 
                                    value="{{ $clientId }}" 
                                    placeholder="e.g. 123456789-xyz.apps.googleusercontent.com"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">Client Secret</label>
                                <input 
                                    type="password" 
                                    name="client_secret" 
                                    value="{{ $clientSecret }}" 
                                    placeholder="e.g. GOCSPX-xxxxxxxxxxxxxxxx"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                Authorized Redirect URI 
                                <span class="text-slate-500 font-normal">(Paste this into Google Cloud Console > Authorized redirect URIs)</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input 
                                    type="text" 
                                    id="input-redirect-uri"
                                    name="redirect_uri" 
                                    value="{{ $redirectUri }}" 
                                    class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none">
                                <button 
                                    type="button" 
                                    onclick="copyToClipboard(document.getElementById('input-redirect-uri').value, this)"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-xs rounded-lg text-slate-200 transition">
                                    Copy URI
                                </button>
                            </div>
                        </div>

                        <!-- Scopes Selection Checkboxes -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-2">OAuth Scopes to Request During Login</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($availableScopes as $scopeUrl => $scopeData)
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg bg-slate-950 border border-slate-800/80 cursor-pointer hover:border-slate-700 transition">
                                        <input 
                                            type="checkbox" 
                                            name="selected_scopes[]" 
                                            value="{{ $scopeUrl }}"
                                            {{ in_array($scopeUrl, $selectedScopes, true) ? 'checked' : '' }}
                                            class="mt-0.5 rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-0">
                                        <div>
                                            <span class="text-xs font-medium text-slate-200 block">{{ $scopeData['name'] }}</span>
                                            <span class="text-[11px] text-slate-500 font-mono block">{{ Str::afterLast($scopeUrl, '/') }}</span>
                                            <span class="text-[11px] text-slate-400 block mt-0.5 leading-snug">{{ $scopeData['description'] }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-2 flex items-center gap-3">
                            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-medium transition">
                                Save Credentials
                            </button>
                            
                            @if(!empty($clientId) && !empty($clientSecret))
                                <a 
                                    href="{{ route('explorer.oauth.redirect') }}" 
                                    class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-medium flex items-center gap-2 shadow-lg shadow-blue-600/30 transition">
                                    <span>🌐</span>
                                    <span>Connect with Google Calendar</span>
                                </a>
                            @else
                                <span class="text-xs text-slate-500 italic">Enter Client ID and Secret to enable Google OAuth login.</span>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Section 3: Direct Manual Token Bypass -->
                <div class="border border-slate-800 bg-slate-900/60 rounded-xl p-6 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">2. Direct Token Bypass (Instant Testing)</h3>
                        <p class="text-xs text-slate-400 mt-1">
                            Already have an access token from Google OAuth 2.0 Playground or another backend? Paste it directly to bypass the OAuth redirect flow.
                        </p>
                    </div>

                    <form action="{{ route('explorer.settings.save') }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Access Token (Bearer)</label>
                            <input 
                                type="text" 
                                name="manual_access_token" 
                                placeholder="ya29.a0AfH6SMB..."
                                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Refresh Token (Optional)</label>
                            <input 
                                type="text" 
                                name="manual_refresh_token" 
                                placeholder="1//04..."
                                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-medium transition">
                            Apply Active Token
                        </button>
                    </form>
                </div>

                <!-- Section 4: Google Cloud Console Setup Guide -->
                <div class="border border-slate-800 bg-slate-900/40 rounded-xl p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Google Cloud Setup Checklist</h3>
                    <ol class="list-decimal list-inside text-xs text-slate-300 space-y-2 leading-relaxed">
                        <li>
                            Go to the <a href="https://console.cloud.google.com" target="_blank" class="text-blue-400 underline">Google Cloud Console</a> and create or select a project.
                        </li>
                        <li>
                            Navigate to <strong>APIs & Services > Library</strong> and search for <strong>Google Calendar API</strong>, then click <strong>Enable</strong>.
                        </li>
                        <li>
                            Go to <strong>OAuth Consent Screen</strong>:
                            <ul class="list-disc list-inside ml-5 mt-1 text-slate-400 space-y-1">
                                <li>Choose <strong>External</strong> User Type.</li>
                                <li>Fill in the App Name (e.g. <em>Calendar Integration Testbench</em>).</li>
                                <li>Add your test email in <strong>Test Users</strong> (required while in Testing status).</li>
                            </ul>
                        </li>
                        <li>
                            Go to <strong>Credentials > Create Credentials > OAuth Client ID</strong>:
                            <ul class="list-disc list-inside ml-5 mt-1 text-slate-400 space-y-1">
                                <li>Application Type: <strong>Web application</strong>.</li>
                                <li>Add the exact Redirect URI shown above into <strong>Authorized redirect URIs</strong>.</li>
                            </ul>
                        </li>
                        <li>Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> into the form above and click <strong>Connect</strong>!</li>
                    </ol>
                </div>
            </div>
        </div>
    </main>

    <!-- Client-Side Javascript Logic -->
    <script>
        const catalogData = @json($catalog);
        const serverSavedParams = @json($savedParams);
        let activeRequestId = '{{ $activeRequestId }}';
        let currentMode = '{{ $mode }}';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const STORAGE_KEY = 'gcal_explorer_saved_params';

        function loadStoredParams() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                let parsed = raw ? JSON.parse(raw) : null;
                if (!parsed && serverSavedParams && Object.keys(serverSavedParams).length > 0) {
                    parsed = serverSavedParams;
                    persistStoredParams(parsed);
                }
                return parsed || {};
            } catch(e) {
                return {};
            }
        }

        function persistStoredParams(data) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch(e) {}
        }

        function getDefaultParamValue(reqId, type, key) {
            const req = catalogData[reqId];
            if (!req) return '';
            const defSource = type === 'path' ? (req.path_params || {}) : (req.query_params || {});
            return defSource[key] !== undefined ? defSource[key] : '';
        }

        function getParamValue(reqId, type, key) {
            const params = loadStoredParams();
            if (params[reqId] && params[reqId][type] && params[reqId][type][key] !== undefined) {
                return params[reqId][type][key];
            }
            return getDefaultParamValue(reqId, type, key);
        }

        let syncParamTimeout = null;
        function syncParamWithServer(reqId, type, key, value) {
            clearTimeout(syncParamTimeout);
            syncParamTimeout = setTimeout(() => {
                fetch('{{ route('explorer.params.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: reqId,
                        param_type: type,
                        param_key: key,
                        param_value: value
                    })
                }).catch(() => {});
            }, 300);
        }

        function handleParamChange(type, key, value) {
            const params = loadStoredParams();
            if (!params[activeRequestId]) {
                params[activeRequestId] = { path: {}, query: {}, body: null };
            }
            if (!params[activeRequestId][type]) {
                params[activeRequestId][type] = {};
            }

            const defaultVal = getDefaultParamValue(activeRequestId, type, key);
            const isModified = String(value) !== String(defaultVal);

            params[activeRequestId][type][key] = value;
            persistStoredParams(params);

            updateResetButtonState(type, key, isModified, defaultVal);
            updatePreviewUrl();
            syncParamWithServer(activeRequestId, type, key, value);
        }

        function handleBodyChange(value) {
            const params = loadStoredParams();
            if (!params[activeRequestId]) {
                params[activeRequestId] = { path: {}, query: {}, body: null };
            }
            params[activeRequestId].body = value;
            persistStoredParams(params);

            const req = catalogData[activeRequestId];
            const defaultBodyStr = req && req.body ? JSON.stringify(req.body, null, 2) : '';
            const isModified = value.trim() !== defaultBodyStr.trim();
            updateBodyResetButtonState(isModified);
            syncParamWithServer(activeRequestId, 'body', null, value);
        }

        function updateResetButtonState(type, key, isModified, defaultVal) {
            const btn = document.getElementById(`reset-btn-${type}-${key}`);
            const input = document.querySelector(`.${type}-param-input[data-${type}-key="${key}"]`);
            if (btn) {
                if (isModified) {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20';
                    btn.title = `Reset ${type === 'path' ? '{' + key + '}' : key} to default (${defaultVal})`;
                } else {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-slate-500 hover:text-blue-400 hover:bg-slate-800';
                    btn.title = `Reset ${type === 'path' ? '{' + key + '}' : key} to default (${defaultVal})`;
                }
            }
            if (input) {
                if (isModified) {
                    input.classList.add('border-amber-500/40', 'bg-amber-950/10');
                } else {
                    input.classList.remove('border-amber-500/40', 'bg-amber-950/10');
                }
            }
        }

        function updateBodyResetButtonState(isModified) {
            const btn = document.getElementById('reset-btn-body');
            if (btn) {
                if (isModified) {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20';
                } else {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-slate-500 hover:text-blue-400 hover:bg-slate-800';
                }
            }
        }

        function resetParam(type, key) {
            const defaultVal = getDefaultParamValue(activeRequestId, type, key);
            const input = document.querySelector(`.${type}-param-input[data-${type}-key="${key}"]`);
            if (input) {
                input.value = defaultVal;
                input.classList.add('ring-2', 'ring-blue-500/50');
                setTimeout(() => input.classList.remove('ring-2', 'ring-blue-500/50'), 400);
            }

            const params = loadStoredParams();
            if (params[activeRequestId] && params[activeRequestId][type]) {
                delete params[activeRequestId][type][key];
                persistStoredParams(params);
            }

            updateResetButtonState(type, key, false, defaultVal);
            updatePreviewUrl();

            fetch('{{ route('explorer.params.reset') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: activeRequestId,
                    param_type: type,
                    param_key: key
                })
            }).catch(() => {});
        }

        const TIMEZONE_AFFECTED_REQUESTS = ['events_list', 'events_get', 'freebusy_query', 'events_insert_meet', 'events_patch'];

        function updateTimezoneResetButton(isModified) {
            const btn = document.getElementById('reset-btn-timezone');
            const select = document.getElementById('timezone-select');
            if (btn) {
                if (isModified) {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20';
                    btn.title = 'Reset timeZone to default (UTC)';
                } else {
                    btn.className = 'reset-param-btn p-1 rounded transition shrink-0 text-slate-500 hover:text-blue-400 hover:bg-slate-800';
                    btn.title = 'Reset timeZone to default (UTC)';
                }
            }
            if (select) {
                if (isModified) {
                    select.classList.add('border-amber-500/40', 'bg-amber-950/10');
                    select.classList.remove('border-slate-800');
                } else {
                    select.classList.remove('border-amber-500/40', 'bg-amber-950/10');
                    select.classList.add('border-slate-800');
                }
            }
        }

        function handleTimezoneChange(val) {
            const isModified = val !== 'UTC';
            updateTimezoneResetButton(isModified);

            const params = loadStoredParams();
            if (!params[activeRequestId]) {
                params[activeRequestId] = { path: {}, query: {}, body: null };
            }
            params[activeRequestId].timezone = val;

            // 1. Query param sync for events_list and events_get
            if (['events_list', 'events_get'].includes(activeRequestId)) {
                if (!params[activeRequestId].query) params[activeRequestId].query = {};
                params[activeRequestId].query.timeZone = val;

                const hiddenInput = document.getElementById('query-param-timezone-hidden');
                if (hiddenInput) {
                    hiddenInput.value = val;
                }

                updatePreviewUrl();
                syncParamWithServer(activeRequestId, 'query', 'timeZone', val);
            }

            // 2. Request body sync for freebusy_query
            if (activeRequestId === 'freebusy_query') {
                const editor = document.getElementById('request-body-editor');
                if (editor && editor.value.trim() !== '') {
                    try {
                        const bodyObj = JSON.parse(editor.value);
                        bodyObj.timeZone = val;
                        editor.value = JSON.stringify(bodyObj, null, 2);
                        handleBodyChange(editor.value);
                    } catch(e) {}
                }
            }

            // 3. Request body sync for events_insert_meet and events_patch
            if (['events_insert_meet', 'events_patch'].includes(activeRequestId)) {
                const editor = document.getElementById('request-body-editor');
                if (editor && editor.value.trim() !== '') {
                    try {
                        const bodyObj = JSON.parse(editor.value);
                        if (bodyObj.start) bodyObj.start.timeZone = val;
                        if (bodyObj.end) bodyObj.end.timeZone = val;
                        editor.value = JSON.stringify(bodyObj, null, 2);
                        handleBodyChange(editor.value);
                    } catch(e) {}
                }
            }

            persistStoredParams(params);
            syncParamWithServer(activeRequestId, 'timezone', null, val);
        }

        function resetTimezoneParam() {
            const select = document.getElementById('timezone-select');
            if (select) {
                select.value = 'UTC';
                select.classList.add('ring-2', 'ring-blue-500/50');
                setTimeout(() => select.classList.remove('ring-2', 'ring-blue-500/50'), 400);
            }

            updateTimezoneResetButton(false);

            const params = loadStoredParams();
            if (params[activeRequestId]) {
                delete params[activeRequestId].timezone;
            }

            if (['events_list', 'events_get'].includes(activeRequestId)) {
                if (params[activeRequestId] && params[activeRequestId].query) {
                    delete params[activeRequestId].query.timeZone;
                }
                const hiddenInput = document.getElementById('query-param-timezone-hidden');
                if (hiddenInput) {
                    hiddenInput.value = 'UTC';
                }
                updatePreviewUrl();

                fetch('{{ route('explorer.params.reset') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: activeRequestId,
                        param_type: 'query',
                        param_key: 'timeZone'
                    })
                }).catch(() => {});
            }

            if (activeRequestId === 'freebusy_query') {
                const editor = document.getElementById('request-body-editor');
                if (editor && editor.value.trim() !== '') {
                    try {
                        const bodyObj = JSON.parse(editor.value);
                        bodyObj.timeZone = 'UTC';
                        editor.value = JSON.stringify(bodyObj, null, 2);
                        handleBodyChange(editor.value);
                    } catch(e) {}
                }
            }

            if (['events_insert_meet', 'events_patch'].includes(activeRequestId)) {
                const editor = document.getElementById('request-body-editor');
                if (editor && editor.value.trim() !== '') {
                    try {
                        const bodyObj = JSON.parse(editor.value);
                        if (bodyObj.start) bodyObj.start.timeZone = 'UTC';
                        if (bodyObj.end) bodyObj.end.timeZone = 'UTC';
                        editor.value = JSON.stringify(bodyObj, null, 2);
                        handleBodyChange(editor.value);
                    } catch(e) {}
                }
            }

            persistStoredParams(params);

            fetch('{{ route('explorer.params.reset') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: activeRequestId,
                    param_type: 'timezone'
                })
            }).catch(() => {});
        }

        function resetRequestBodyJson() {
            const req = catalogData[activeRequestId];
            const editor = document.getElementById('request-body-editor');
            if (req && req.body && editor) {
                editor.value = JSON.stringify(req.body, null, 2);
                editor.classList.add('ring-2', 'ring-blue-500/50');
                setTimeout(() => editor.classList.remove('ring-2', 'ring-blue-500/50'), 400);
            }

            const params = loadStoredParams();
            if (params[activeRequestId]) {
                delete params[activeRequestId].body;
                delete params[activeRequestId].timezone;
                persistStoredParams(params);
            }

            if (TIMEZONE_AFFECTED_REQUESTS.includes(activeRequestId)) {
                const select = document.getElementById('timezone-select');
                if (select) select.value = 'UTC';
                updateTimezoneResetButton(false);
            }

            updateBodyResetButtonState(false);

            fetch('{{ route('explorer.params.reset') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: activeRequestId,
                    param_type: 'body'
                })
            }).catch(() => {});
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // Switch Top Tabs (Explorer vs Settings)
        function switchMainTab(tab) {
            const explorerView = document.getElementById('view-explorer');
            const settingsView = document.getElementById('view-settings');
            const tabBtnExp = document.getElementById('tab-btn-explorer');
            const tabBtnSet = document.getElementById('tab-btn-settings');

            if (tab === 'settings') {
                explorerView.classList.add('hidden');
                settingsView.classList.remove('hidden');
                tabBtnSet.className = 'px-3.5 py-1.5 rounded-md transition flex items-center gap-2 bg-blue-600 text-white shadow-sm';
                tabBtnExp.className = 'px-3.5 py-1.5 rounded-md transition flex items-center gap-2 text-slate-400 hover:text-slate-200';
            } else {
                settingsView.classList.add('hidden');
                explorerView.classList.remove('hidden');
                tabBtnExp.className = 'px-3.5 py-1.5 rounded-md transition flex items-center gap-2 bg-blue-600 text-white shadow-sm';
                tabBtnSet.className = 'px-3.5 py-1.5 rounded-md transition flex items-center gap-2 text-slate-400 hover:text-slate-200';
            }

            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }

        // Switch Response Inspector Tabs
        function switchResponseTab(tab) {
            const jsonView = document.getElementById('resp-view-json');
            const previewView = document.getElementById('resp-view-preview');
            const headersView = document.getElementById('resp-view-headers');

            const btnJson = document.getElementById('resp-tab-json');
            const btnPreview = document.getElementById('resp-tab-preview');
            const btnHeaders = document.getElementById('resp-tab-headers');

            jsonView.classList.add('hidden');
            previewView.classList.add('hidden');
            headersView.classList.add('hidden');

            btnJson.className = 'px-3 py-1 rounded text-slate-400 hover:text-slate-200 transition';
            btnPreview.className = 'px-3 py-1 rounded text-slate-400 hover:text-slate-200 transition';
            btnHeaders.className = 'px-3 py-1 rounded text-slate-400 hover:text-slate-200 transition';

            if (tab === 'json') {
                jsonView.classList.remove('hidden');
                btnJson.className = 'px-3 py-1 rounded bg-slate-800 text-white transition';
            } else if (tab === 'preview') {
                previewView.classList.remove('hidden');
                btnPreview.className = 'px-3 py-1 rounded bg-slate-800 text-white transition';
            } else if (tab === 'headers') {
                headersView.classList.remove('hidden');
                btnHeaders.className = 'px-3 py-1 rounded bg-slate-800 text-white transition';
            }
        }

        // Select a Request from the Catalog
        function selectRequest(reqId) {
            if (!catalogData[reqId]) return;
            activeRequestId = reqId;
            const req = catalogData[reqId];

            // Highlight sidebar
            document.querySelectorAll('.catalog-item').forEach(el => {
                if (el.getAttribute('data-req-id') === reqId) {
                    el.className = 'catalog-item w-full text-left px-2.5 py-2 rounded-md text-xs transition flex items-center justify-between group bg-blue-600/20 border border-blue-500/30 text-white';
                } else {
                    el.className = 'catalog-item w-full text-left px-2.5 py-2 rounded-md text-xs transition flex items-center justify-between group hover:bg-slate-800/60 text-slate-300';
                }
            });

            // Update Method Badge
            const methodBadge = document.getElementById('current-method-badge');
            methodBadge.innerText = req.http_method;
            const badgeClasses = {
                'GET': 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                'POST': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                'PATCH': 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                'DELETE': 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            };
            methodBadge.className = 'px-2 py-0.5 rounded text-xs font-mono font-bold border ' + (badgeClasses[req.http_method] || 'bg-slate-500/20 text-slate-300');

            document.getElementById('current-method-name').innerText = req.method_name;
            document.getElementById('current-summary').innerText = req.summary;
            document.getElementById('current-description').innerText = req.description;
            document.getElementById('current-full-url').innerText = req.full_url;

            // Render Scopes
            const scopesList = document.getElementById('current-scopes-list');
            scopesList.innerHTML = '';
            req.scopes.forEach(s => {
                const span = document.createElement('span');
                span.className = 'px-2 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[11px] text-blue-300';
                span.innerText = s.split('/').pop();
                scopesList.appendChild(span);
            });

            // Render Cloud Actions
            const actionsList = document.getElementById('current-cloud-actions');
            actionsList.innerHTML = '';
            req.cloud_actions.forEach(a => {
                const li = document.createElement('li');
                li.innerText = a;
                actionsList.appendChild(li);
            });

            // Render Timezone Parameter
            const tzSec = document.getElementById('timezone-param-section');
            const tzSelect = document.getElementById('timezone-select');
            const tzHint = document.getElementById('timezone-hint');
            if (TIMEZONE_AFFECTED_REQUESTS.includes(reqId)) {
                tzSec.classList.remove('hidden');
                const stored = loadStoredParams();
                let currentTz = 'UTC';
                if (stored[reqId] && stored[reqId].timezone) {
                    currentTz = stored[reqId].timezone;
                } else if (['events_list', 'events_get'].includes(reqId)) {
                    currentTz = getParamValue(reqId, 'query', 'timeZone') || 'UTC';
                } else if (reqId === 'freebusy_query') {
                    let b = null;
                    if (stored[reqId] && stored[reqId].body) {
                        try {
                            b = typeof stored[reqId].body === 'string' ? JSON.parse(stored[reqId].body) : stored[reqId].body;
                        } catch(e) {}
                    }
                    if (!b && req.body) b = req.body;
                    currentTz = b && b.timeZone ? b.timeZone : 'UTC';
                } else if (['events_insert_meet', 'events_patch'].includes(reqId)) {
                    let b = null;
                    if (stored[reqId] && stored[reqId].body) {
                        try {
                            b = typeof stored[reqId].body === 'string' ? JSON.parse(stored[reqId].body) : stored[reqId].body;
                        } catch(e) {}
                    }
                    if (!b && req.body) b = req.body;
                    currentTz = b && b.start && b.start.timeZone ? b.start.timeZone : 'UTC';
                }
                if (tzSelect) {
                    tzSelect.value = currentTz;
                }
                updateTimezoneResetButton(currentTz !== 'UTC');

                if (tzHint) {
                    if (reqId === 'events_list' || reqId === 'events_get') {
                        tzHint.innerHTML = 'Sent via <code class="text-blue-300 font-mono">timeZone</code> query parameter to format timestamps returned by Google Calendar.';
                    } else if (reqId === 'freebusy_query') {
                        tzHint.innerHTML = 'Sent in request body <code class="text-blue-300 font-mono">timeZone</code> to specify the reference timezone for busy slots.';
                    } else {
                        tzHint.innerHTML = 'Applied to <code class="text-blue-300 font-mono">start.timeZone</code> and <code class="text-blue-300 font-mono">end.timeZone</code> in the event payload.';
                    }
                }
            } else {
                tzSec.classList.add('hidden');
            }

            // Render Path Params
            const pathSec = document.getElementById('path-params-section');
            const pathCont = document.getElementById('path-params-container');
            pathCont.innerHTML = '';
            if (req.path_params && Object.keys(req.path_params).length > 0) {
                pathSec.classList.remove('hidden');
                for (const [k, defaultVal] of Object.entries(req.path_params)) {
                    const currentVal = getParamValue(reqId, 'path', k);
                    const isModified = String(currentVal) !== String(defaultVal);
                    const resetClass = isModified ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20' : 'text-slate-500 hover:text-blue-400 hover:bg-slate-800';
                    const inputModClass = isModified ? 'border-amber-500/40 bg-amber-950/10' : '';
                    pathCont.innerHTML += `
                        <div class="flex items-center gap-3">
                            <div class="w-48 flex items-center gap-1.5 shrink-0">
                                <span class="font-mono text-xs text-slate-400 truncate" title="{${k}}">{${k}}</span>
                                <button 
                                    type="button" 
                                    onclick="resetParam('path', '${k}')" 
                                    id="reset-btn-path-${k}"
                                    title="Reset {${k}} to default (${defaultVal})" 
                                    aria-label="Reset {${k}} to default"
                                    class="reset-param-btn p-1 rounded transition shrink-0 ${resetClass}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                            <input 
                                type="text" 
                                data-path-key="${k}" 
                                value="${escapeHtml(currentVal)}" 
                                oninput="handleParamChange('path', '${k}', this.value)" 
                                class="path-param-input flex-1 bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none transition ${inputModClass}">
                        </div>`;
                }
            } else {
                pathSec.classList.add('hidden');
            }

            // Render Query Params
            const querySec = document.getElementById('query-params-section');
            const queryCont = document.getElementById('query-params-container');
            queryCont.innerHTML = '';
            if (req.query_params && Object.keys(req.query_params).length > 0) {
                querySec.classList.remove('hidden');
                for (const [k, defaultVal] of Object.entries(req.query_params)) {
                    if (k === 'timeZone') {
                        const currentVal = getParamValue(reqId, 'query', 'timeZone') || 'UTC';
                        queryCont.innerHTML += `<input type="hidden" class="query-param-input" data-query-key="timeZone" id="query-param-timezone-hidden" value="${escapeHtml(currentVal)}">`;
                        continue;
                    }
                    const currentVal = getParamValue(reqId, 'query', k);
                    const isModified = String(currentVal) !== String(defaultVal);
                    const resetClass = isModified ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20' : 'text-slate-500 hover:text-blue-400 hover:bg-slate-800';
                    const inputModClass = isModified ? 'border-amber-500/40 bg-amber-950/10' : '';
                    queryCont.innerHTML += `
                        <div class="flex items-center gap-3">
                            <div class="w-48 flex items-center gap-1.5 shrink-0">
                                <span class="font-mono text-xs text-slate-400 truncate" title="${k}">${k}</span>
                                <button 
                                    type="button" 
                                    onclick="resetParam('query', '${k}')" 
                                    id="reset-btn-query-${k}"
                                    title="Reset ${k} to default (${defaultVal})" 
                                    aria-label="Reset ${k} to default"
                                    class="reset-param-btn p-1 rounded transition shrink-0 ${resetClass}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                            <input 
                                type="text" 
                                data-query-key="${k}" 
                                value="${escapeHtml(currentVal)}" 
                                oninput="handleParamChange('query', '${k}', this.value)" 
                                class="query-param-input flex-1 bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-xs text-slate-200 font-mono focus:border-blue-500 focus:outline-none transition ${inputModClass}">
                        </div>`;
                }
            } else {
                querySec.classList.add('hidden');
            }

            // Render Body Editor
            const bodySec = document.getElementById('request-body-section');
            const bodyEditor = document.getElementById('request-body-editor');
            if (req.body) {
                bodySec.classList.remove('hidden');
                const stored = loadStoredParams();
                const savedBody = stored[reqId] && stored[reqId].body !== undefined ? stored[reqId].body : null;
                const bodyToDisplay = savedBody !== null ? (typeof savedBody === 'string' ? savedBody : JSON.stringify(savedBody, null, 2)) : JSON.stringify(req.body, null, 2);
                bodyEditor.value = bodyToDisplay;
                const defaultBodyStr = JSON.stringify(req.body, null, 2);
                updateBodyResetButtonState(savedBody !== null && bodyToDisplay.trim() !== defaultBodyStr.trim());
            } else {
                bodySec.classList.add('hidden');
                bodyEditor.value = '';
            }

            // Render Insights
            renderInsights(req.insights);

            // Reset Response Pane
            document.getElementById('response-status-badge').innerText = 'Awaiting Execution';
            document.getElementById('response-status-badge').className = 'px-2.5 py-1 rounded text-xs font-mono font-bold bg-slate-800 text-slate-400 border border-slate-700';
            document.getElementById('response-latency-badge').innerText = '-- ms';
            document.getElementById('response-raw-json').innerText = '// Ready. Click "Send Request" to test this endpoint.';
            document.getElementById('visual-preview-container').innerHTML = '<p class="text-xs text-slate-400 italic">Run this request to view formatted preview.</p>';

            // Update URL search query
            const url = new URL(window.location);
            url.searchParams.set('request', reqId);
            window.history.replaceState({}, '', url);

            updatePreviewUrl();
        }

        // Render Developer Insights
        function renderInsights(insights) {
            const fieldsCont = document.getElementById('insights-key-fields');
            fieldsCont.innerHTML = '';
            if (insights && insights.key_fields) {
                for (const [field, desc] of Object.entries(insights.key_fields)) {
                    fieldsCont.innerHTML += `
                        <div class="bg-slate-950 p-2.5 rounded border border-slate-800/80">
                            <span class="font-mono text-blue-400 font-semibold">${field}</span>
                            <p class="text-slate-300 mt-0.5 leading-relaxed">${desc}</p>
                        </div>`;
                }
            }

            const actionsCont = document.getElementById('insights-app-actions');
            actionsCont.innerHTML = '';
            if (insights && insights.app_actions) {
                insights.app_actions.forEach(act => {
                    actionsCont.innerHTML += `<li>${act}</li>`;
                });
            }

            const errorCont = document.getElementById('insights-error-handling');
            errorCont.innerHTML = '';
            if (insights && insights.error_handling) {
                insights.error_handling.forEach(err => {
                    errorCont.innerHTML += `<li>${err}</li>`;
                });
            }
        }

        // Update Full URL in Header based on parameters
        function updatePreviewUrl() {
            const req = catalogData[activeRequestId];
            if (!req) return;

            let endpoint = req.endpoint;
            document.querySelectorAll('.path-param-input').forEach(input => {
                const key = input.getAttribute('data-path-key');
                endpoint = endpoint.replace(`{${key}}`, encodeURIComponent(input.value));
            });

            const queryParams = [];
            document.querySelectorAll('.query-param-input').forEach(input => {
                const key = input.getAttribute('data-query-key');
                if (input.value.trim() !== '') {
                    queryParams.push(`${encodeURIComponent(key)}=${encodeURIComponent(input.value.trim())}`);
                }
            });

            let full = `https://www.googleapis.com/calendar/v3${endpoint}`;
            if (queryParams.length > 0) {
                full += `?${queryParams.join('&')}`;
            }

            document.getElementById('current-full-url').innerText = full;
        }

        // Set Execution Mode (Live vs Mock)
        function setExecutionMode(mode) {
            currentMode = mode;
            const btnMock = document.getElementById('mode-mock-btn');
            const btnLive = document.getElementById('mode-live-btn');
            const label = document.getElementById('active-mode-label');

            if (mode === 'live') {
                btnLive.className = 'px-2 py-0.5 rounded font-mono font-medium transition bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                btnMock.className = 'px-2 py-0.5 rounded font-mono font-medium transition text-slate-500 hover:text-slate-300';
                label.innerText = 'Live Google API';
                label.className = 'font-mono px-2 py-0.5 rounded font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
            } else {
                btnMock.className = 'px-2 py-0.5 rounded font-mono font-medium transition bg-amber-500/20 text-amber-300 border border-amber-500/30';
                btnLive.className = 'px-2 py-0.5 rounded font-mono font-medium transition text-slate-500 hover:text-slate-300';
                label.innerText = 'Simulated Mock';
                label.className = 'font-mono px-2 py-0.5 rounded font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20';
            }

            fetch('{{ route('explorer.mode') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ mode: mode })
            });
        }

        // Execute Request
        async function executeCurrentRequest() {
            const btn = document.getElementById('btn-send-request');
            const btnText = document.getElementById('btn-text');
            const btnIcon = document.getElementById('btn-icon');
            const btnSpinner = document.getElementById('btn-spinner');

            btn.disabled = true;
            btnText.innerText = 'Sending...';
            btnIcon.classList.add('hidden');
            btnSpinner.classList.remove('hidden');

            const pathParams = {};
            document.querySelectorAll('.path-param-input').forEach(i => {
                pathParams[i.getAttribute('data-path-key')] = i.value;
            });

            const queryParams = {};
            document.querySelectorAll('.query-param-input').forEach(i => {
                queryParams[i.getAttribute('data-query-key')] = i.value;
            });

            let bodyContent = null;
            const editor = document.getElementById('request-body-editor');
            if (editor && editor.value.trim() !== '') {
                try {
                    bodyContent = JSON.parse(editor.value);
                } catch(e) {
                    alert('Invalid JSON in Request Body: ' + e.message);
                    resetButton();
                    return;
                }
            }

            try {
                const response = await fetch('{{ route('explorer.execute') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: activeRequestId,
                        mode: currentMode,
                        path_params: pathParams,
                        query_params: queryParams,
                        body: bodyContent
                    })
                });

                const data = await response.json();
                renderResponse(data);
            } catch (err) {
                alert('Request failed: ' + err.message);
            } finally {
                resetButton();
            }

            function resetButton() {
                btn.disabled = false;
                btnText.innerText = 'Send Request';
                btnIcon.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
            }
        }

        // Render Received Response
        function renderResponse(res) {
            const statusBadge = document.getElementById('response-status-badge');
            const latencyBadge = document.getElementById('response-latency-badge');
            const modeUsed = document.getElementById('response-mode-used');
            const rawJsonEl = document.getElementById('response-raw-json');

            // Status Badge
            const status = res.status || 200;
            statusBadge.innerText = `${status} ${getStatusText(status)}`;
            if (status >= 200 && status < 300) {
                statusBadge.className = 'px-2.5 py-1 rounded text-xs font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            } else if (status >= 400 && status < 500) {
                statusBadge.className = 'px-2.5 py-1 rounded text-xs font-mono font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30';
            } else {
                statusBadge.className = 'px-2.5 py-1 rounded text-xs font-mono font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30';
            }

            latencyBadge.innerText = `${res.latency_ms || 0} ms`;
            modeUsed.innerText = (res.mode_used || 'mock').toUpperCase();
            modeUsed.classList.remove('hidden');

            // Raw JSON
            rawJsonEl.innerText = JSON.stringify(res.body, null, 2);

            // Render Visual Preview Card
            renderVisualPreview(res.body);

            // Render Headers
            const headersTbody = document.getElementById('response-headers-tbody');
            headersTbody.innerHTML = '';
            if (res.headers) {
                for (const [k, v] of Object.entries(res.headers)) {
                    headersTbody.innerHTML += `
                        <tr>
                            <td class="py-1 text-slate-400 font-semibold pr-4">${k}</td>
                            <td class="py-1 text-slate-300">${Array.isArray(v) ? v.join(', ') : v}</td>
                        </tr>`;
                }
            }
        }

        // Format Visual Preview
        function renderVisualPreview(body) {
            const container = document.getElementById('visual-preview-container');
            if (!body) {
                container.innerHTML = '<p class="text-xs text-slate-400">Empty response body.</p>';
                return;
            }

            // Event Single Object
            if (body.kind === 'calendar#event' || (body.summary && body.start)) {
                const meetLink = body.hangoutLink ? `
                    <div class="mt-3 p-3 bg-blue-950/60 border border-blue-800/60 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-2 text-xs text-blue-300">
                            <span class="text-base">📹</span>
                            <span class="font-medium font-mono">${body.hangoutLink}</span>
                        </div>
                        <a href="${body.hangoutLink}" target="_blank" class="px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white text-xs font-medium transition">
                            Join Meeting
                        </a>
                    </div>` : '';

                const formatEventTime = (timeObj) => {
                    if (!timeObj) return 'N/A';
                    const raw = timeObj.dateTime || timeObj.date || '';
                    if (!raw || !raw.includes('T')) return raw || 'N/A';
                    const d = new Date(raw);
                    if (isNaN(d)) return raw;
                    const dateStr = d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
                    const localTime = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const tzPart = raw.includes('Z') ? 'UTC' : (raw.includes('+') ? '+' + raw.split('+')[1] : '');
                    const rawTime = raw.split('T')[1].substring(0, 5) + (tzPart ? ` (${tzPart})` : '');
                    return `${dateStr} ${rawTime} (${localTime} Local)`;
                };

                container.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex items-start justify-between">
                            <h4 class="text-sm font-bold text-white">${body.summary || 'Untitled Event'}</h4>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                ${body.status || 'confirmed'}
                            </span>
                        </div>
                        ${body.description ? `<p class="text-xs text-slate-400">${body.description}</p>` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs text-slate-300 pt-2 font-mono">
                            <div class="bg-slate-950 p-2 rounded border border-slate-800">
                                <span class="text-slate-500 text-[10px] block uppercase">Starts</span>
                                <span>${formatEventTime(body.start)}</span>
                            </div>
                            <div class="bg-slate-950 p-2 rounded border border-slate-800">
                                <span class="text-slate-500 text-[10px] block uppercase">Ends</span>
                                <span>${formatEventTime(body.end)}</span>
                            </div>
                        </div>
                        ${meetLink}
                    </div>`;
                return;
            }

            // Events List
            if (body.kind === 'calendar#events' && Array.isArray(body.items)) {
                let itemsHtml = body.items.map(it => {
                    const startRaw = it.start ? (it.start.dateTime || it.start.date) : '';
                    let timeDisplay = startRaw;
                    if (startRaw && startRaw.includes('T')) {
                        const d = new Date(startRaw);
                        if (!isNaN(d)) {
                            const dateStr = d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
                            const localTime = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            const tzPart = startRaw.includes('Z') ? 'UTC' : (startRaw.includes('+') ? '+' + startRaw.split('+')[1] : '');
                            const rawTime = startRaw.split('T')[1].substring(0, 5) + (tzPart ? ` (${tzPart})` : '');
                            timeDisplay = `${dateStr} • ${rawTime} → ${localTime} Local`;
                        }
                    }
                    return `
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800/80 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-medium text-white block">${it.summary || 'Untitled'}</span>
                            <span class="text-slate-400 font-mono text-[11px]">${timeDisplay}</span>
                        </div>
                        ${it.hangoutLink ? `<a href="${it.hangoutLink}" target="_blank" class="text-blue-400 hover:underline text-[11px]">Join Meet</a>` : ''}
                    </div>`;
                }).join('');

                container.innerHTML = `
                    <div>
                        <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 text-xs">
                            <span class="font-bold text-white">Events (${body.items.length})</span>
                            <span class="text-slate-400 font-mono text-[11px]">${body.timeZone ? 'Calendar TZ: ' + body.timeZone : ''}</span>
                        </div>
                        <div class="space-y-1.5">${itemsHtml || '<p class="text-xs text-slate-500">No events found in this range.</p>'}</div>
                    </div>`;
                return;
            }

            // CalendarList
            if (body.kind === 'calendar#calendarList' && Array.isArray(body.items)) {
                let calsHtml = body.items.map(c => `
                    <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800/80 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background-color: ${c.backgroundColor || '#3b82f6'}"></span>
                            <div>
                                <span class="font-medium text-white block">${c.summary}</span>
                                <span class="text-slate-400 font-mono text-[11px]">${c.id}</span>
                            </div>
                        </div>
                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 uppercase">${c.accessRole}</span>
                    </div>
                `).join('');

                container.innerHTML = `
                    <div>
                        <h4 class="text-xs font-bold text-white mb-2">Available Calendars (${body.items.length})</h4>
                        <div class="space-y-1.5">${calsHtml}</div>
                    </div>`;
                return;
            }

            // FreeBusy
            if (body.kind === 'calendar#freeBusy' && body.calendars) {
                let busyHtml = '';
                for (const [calId, calData] of Object.entries(body.calendars)) {
                    const busySlots = (calData.busy || []).map(b => {
                        const sDate = new Date(b.start);
                        const eDate = new Date(b.end);
                        
                        const dateLabel = !isNaN(sDate) ? sDate.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) : (b.start.split('T')[0] || '');
                        
                        const formatRawTime = (iso) => {
                            if (!iso || !iso.includes('T')) return iso;
                            const timePart = iso.split('T')[1];
                            const timeStr = timePart.substring(0, 5);
                            let tz = '';
                            if (timePart.includes('+')) tz = '+' + timePart.split('+')[1];
                            else if (timePart.includes('-')) tz = '-' + timePart.split('-')[1];
                            else if (timePart.includes('Z')) tz = 'UTC';
                            return tz ? `${timeStr} (${tz})` : timeStr;
                        };
                        
                        const localStart = !isNaN(sDate) ? sDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                        const localEnd = !isNaN(eDate) ? eDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

                        return `
                            <div class="p-2.5 rounded-lg bg-rose-950/40 border border-rose-900/50 text-xs font-mono text-rose-300 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-white font-semibold">📅 ${dateLabel}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-900/60 text-rose-200 uppercase font-bold">Busy Slot</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-rose-300">
                                    <span>${formatRawTime(b.start)} → ${formatRawTime(b.end)}</span>
                                    ${localStart ? `<span class="text-[11px] text-slate-400 font-sans">(${localStart} - ${localEnd} Local)</span>` : ''}
                                </div>
                            </div>`;
                    }).join('');
                    busyHtml += `
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-mono text-slate-400">
                                <span>Calendar: <strong class="text-white">${calId}</strong></span>
                                <span>Slots: ${(calData.busy || []).length}</span>
                            </div>
                            <div class="space-y-1.5">${busySlots || '<p class="text-xs text-emerald-400">All free! No conflicts in this window.</p>'}</div>
                        </div>`;
                }
                container.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800 text-xs">
                            <h4 class="font-bold text-white">Free/Busy Analysis</h4>
                            <span class="text-slate-400 text-[11px] font-mono">Range: ${body.timeMin ? body.timeMin.split('T')[0] : ''} to ${body.timeMax ? body.timeMax.split('T')[0] : ''}</span>
                        </div>
                        ${busyHtml}
                    </div>`;
                return;
            }

            container.innerHTML = `<pre class="text-xs font-mono text-slate-300 overflow-x-auto">${JSON.stringify(body, null, 2)}</pre>`;
        }

        // Helpers
        function getStatusText(code) {
            const map = {
                200: 'OK', 201: 'Created', 204: 'No Content',
                400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden',
                404: 'Not Found', 409: 'Conflict', 500: 'Server Error'
            };
            return map[code] || '';
        }

        function formatRequestBodyJson() {
            const el = document.getElementById('request-body-editor');
            try {
                const parsed = JSON.parse(el.value);
                el.value = JSON.stringify(parsed, null, 2);
            } catch(e) {
                alert('Invalid JSON: ' + e.message);
            }
        }

        function applyInitialStoredParams() {
            const stored = loadStoredParams();
            const savedReq = stored[activeRequestId];
            if (!savedReq) return;

            if (savedReq.path) {
                for (const [k, v] of Object.entries(savedReq.path)) {
                    const input = document.querySelector(`.path-param-input[data-path-key="${k}"]`);
                    if (input) {
                        input.value = v;
                        const defaultVal = getDefaultParamValue(activeRequestId, 'path', k);
                        updateResetButtonState('path', k, String(v) !== String(defaultVal), defaultVal);
                    }
                }
            }

            if (savedReq.query) {
                for (const [k, v] of Object.entries(savedReq.query)) {
                    const input = document.querySelector(`.query-param-input[data-query-key="${k}"]`);
                    if (input) {
                        input.value = v;
                        const defaultVal = getDefaultParamValue(activeRequestId, 'query', k);
                        updateResetButtonState('query', k, String(v) !== String(defaultVal), defaultVal);
                    }
                }
            }

            if (savedReq.body !== undefined && savedReq.body !== null) {
                const editor = document.getElementById('request-body-editor');
                if (editor) {
                    editor.value = typeof savedReq.body === 'string' ? savedReq.body : JSON.stringify(savedReq.body, null, 2);
                    const req = catalogData[activeRequestId];
                    const defaultBodyStr = req && req.body ? JSON.stringify(req.body, null, 2) : '';
                    updateBodyResetButtonState(editor.value.trim() !== defaultBodyStr.trim());
                }
            }

            if (TIMEZONE_AFFECTED_REQUESTS.includes(activeRequestId)) {
                let savedTz = null;
                if (savedReq.timezone) {
                    savedTz = savedReq.timezone;
                } else if (savedReq.query && savedReq.query.timeZone) {
                    savedTz = savedReq.query.timeZone;
                } else if (savedReq.body) {
                    try {
                        const b = typeof savedReq.body === 'string' ? JSON.parse(savedReq.body) : savedReq.body;
                        if (b && b.timeZone) savedTz = b.timeZone;
                        else if (b && b.start && b.start.timeZone) savedTz = b.start.timeZone;
                    } catch(e) {}
                }
                if (savedTz) {
                    const tzSelect = document.getElementById('timezone-select');
                    if (tzSelect) {
                        tzSelect.value = savedTz;
                        updateTimezoneResetButton(savedTz !== 'UTC');
                    }
                    const hiddenTz = document.getElementById('query-param-timezone-hidden');
                    if (hiddenTz) {
                        hiddenTz.value = savedTz;
                    }
                }
            }

            updatePreviewUrl();
        }

        document.addEventListener('DOMContentLoaded', applyInitialStoredParams);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            applyInitialStoredParams();
        }

        function filterCatalog(term) {
            const t = term.toLowerCase().trim();
            document.querySelectorAll('.catalog-item').forEach(el => {
                const text = el.getAttribute('data-req-title') || '';
                if (text.includes(t)) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });
        }

        // Toast Notification for Copy
        function showCopyToast(msg = 'Copied to clipboard!') {
            const toast = document.getElementById('copy-toast');
            const msgEl = document.getElementById('copy-toast-msg');
            if (!toast) return;
            if (msgEl) msgEl.innerText = msg;
            toast.classList.remove('opacity-0', 'translate-y-2', 'pointer-events-none');
            toast.classList.add('opacity-100', 'translate-y-0');
            clearTimeout(toast.dismissTimer);
            toast.dismissTimer = setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-2', 'pointer-events-none');
            }, 2000);
        }

        // Safe Copy to Clipboard supporting both HTTPS and HTTP (.test domains)
        function copyToClipboard(text, btnElement = null) {
            if (!text) return;

            function onCopySuccess() {
                if (btnElement && btnElement instanceof HTMLElement) {
                    const originalText = btnElement.innerText;
                    btnElement.innerText = 'Copied!';
                    btnElement.classList.add('text-emerald-400');
                    setTimeout(() => {
                        btnElement.innerText = originalText;
                        btnElement.classList.remove('text-emerald-400');
                    }, 1800);
                }
                showCopyToast();
            }

            // 1. Try modern Clipboard API if supported and in secure context (HTTPS / localhost)
            if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text).then(() => {
                    onCopySuccess();
                }).catch(() => {
                    fallbackCopy(text, onCopySuccess);
                });
                return;
            }

            // 2. Fallback to execCommand('copy') for non-secure HTTP contexts (e.g. .test domains)
            fallbackCopy(text, onCopySuccess);
        }

        function fallbackCopy(text, onSuccess) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                textarea.style.top = '-9999px';
                textarea.style.opacity = '0';
                textarea.setAttribute('readonly', '');
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();

                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (successful) {
                    onSuccess();
                } else {
                    window.prompt('Copy to clipboard (Ctrl+C, Enter):', text);
                }
            } catch (err) {
                window.prompt('Copy to clipboard (Ctrl+C, Enter):', text);
            }
        }
    </script>

    <!-- Floating Copy Toast Notification -->
    <div id="copy-toast" class="fixed bottom-6 right-6 z-50 bg-slate-900 border border-slate-700 text-slate-100 text-xs px-4 py-2.5 rounded-lg shadow-2xl flex items-center gap-2 transform transition-all duration-300 opacity-0 pointer-events-none translate-y-2">
        <span class="text-emerald-400 font-bold">✓</span>
        <span id="copy-toast-msg">Copied to clipboard!</span>
    </div>
</body>
</html>

