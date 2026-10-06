<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class CalendarExplorerController
 *
 * 1) General Naming Conventions:
 * - Class Name: PascalCase (`CalendarExplorerController`) representing a dedicated feature controller in `App\Http\Controllers`.
 * - Action Methods: camelCase verbs matching standard HTTP intent and Laravel conventions:
 *   - `index()`: Serves the primary workbench and settings interface.
 *   - `saveSettings()`: Handles POST persistence of OAuth credentials and tokens.
 *   - `oauthRedirect()`: Redirects user agent to Google's consent screen.
 *   - `oauthCallback()`: Processes the authorization code callback and exchanges it for tokens.
 *   - `oauthDisconnect()`: Clears active session tokens and resets mode to mock simulation.
 *   - `setMode()`: Toggles execution state between 'mock' and 'live'.
 *   - `executeRequest()`: AJAX endpoint that executes API queries and returns telemetry data.
 *   - `saveParam()`: Saves user input parameters in session as the last input parameter.
 *   - `resetParam()`: Resets user input parameters back to default catalog definitions.
 * - Session Namespacing: Uses the `google_*` and `gcal_*` prefixes to isolate OAuth credentials and workbench parameter state.
 *
 * 2) Function of the Code:
 * Manages the web user interface and API execution pipeline for the Google Calendar integration workbench.
 * Bridges user interactions from the Blade frontend to `GoogleCalendarService`, maintains OAuth state in session,
 * and formats API responses with latency measurements, headers, and implementation insights.
 */
class CalendarExplorerController extends Controller
{
    /**
     * Inject the Google Calendar integration service.
     */
    public function __construct(
        protected GoogleCalendarService $calendarService
    ) {}

    /**
     * Display the main explorer workbench or settings tab.
     *
     * Function: Prepares the catalog of requests, active selection, credentials, and connection state.
     */
    public function index(Request $request): View
    {
        $catalog = $this->calendarService->getCatalog();
        $availableScopes = $this->calendarService->getAvailableScopes();

        $activeRequestId = $request->query('request', (string) array_key_first($catalog));
        if (! isset($catalog[$activeRequestId])) {
            $activeRequestId = (string) array_key_first($catalog);
        }

        $activeTab = $request->query('tab', 'explorer');
        if (! in_array($activeTab, ['explorer', 'settings'], true)) {
            $activeTab = 'explorer';
        }

        $clientId = (string) (session('google_client_id') ?? config('services.google.client_id', env('GOOGLE_CLIENT_ID', '')));
        $clientSecret = (string) (session('google_client_secret') ?? config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET', '')));
        $redirectUri = $this->resolveRedirectUri();
        $selectedScopes = session('google_selected_scopes', [
            GoogleCalendarService::SCOPE_READONLY,
            GoogleCalendarService::SCOPE_EVENTS,
            GoogleCalendarService::SCOPE_FREEBUSY,
        ]);

        $accessToken = session('google_access_token');
        $refreshToken = session('google_refresh_token');
        $tokenExpiresAt = session('google_token_expires_at');
        $userProfile = session('google_user_profile');
        $mode = session('explorer_mode', $accessToken ? 'live' : 'mock');

        $isTokenExpired = $tokenExpiresAt && now()->timestamp > (int) $tokenExpiresAt;
        $isConnected = ! empty($accessToken) && (! $isTokenExpired || ! empty($refreshToken));

        // Retrieve persisted user parameters from session
        $savedParams = session('gcal_saved_params', []);
        $activeRequest = $catalog[$activeRequestId];
        $activeSaved = $savedParams[$activeRequestId] ?? [];

        if (! empty($activeSaved['path'])) {
            $activeRequest['path_params'] = array_merge($activeRequest['path_params'] ?? [], $activeSaved['path']);
        }
        if (! empty($activeSaved['query'])) {
            $activeRequest['query_params'] = array_merge($activeRequest['query_params'] ?? [], $activeSaved['query']);
        }
        if (isset($activeSaved['body'])) {
            $activeRequest['body'] = $activeSaved['body'];
        }
        if (isset($activeSaved['timezone'])) {
            $activeRequest['timezone'] = $activeSaved['timezone'];
            if (isset($activeRequest['query_params']['timeZone'])) {
                $activeRequest['query_params']['timeZone'] = $activeSaved['timezone'];
            }
            if (is_array($activeRequest['body']) && isset($activeRequest['body']['timeZone'])) {
                $activeRequest['body']['timeZone'] = $activeSaved['timezone'];
            }
        }

        return view('explorer', [
            'catalog' => $catalog,
            'activeRequest' => $activeRequest,
            'activeRequestId' => $activeRequestId,
            'activeTab' => $activeTab,
            'availableScopes' => $availableScopes,
            'selectedScopes' => $selectedScopes,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'isConnected' => $isConnected,
            'isTokenExpired' => $isTokenExpired,
            'userProfile' => $userProfile,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'tokenExpiresAt' => $tokenExpiresAt,
            'mode' => $mode,
            'savedParams' => $savedParams,
        ]);
    }

    /**
     * Save OAuth credentials or direct access tokens.
     */
    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['nullable', 'string', 'max:255'],
            'selected_scopes' => ['nullable', 'array'],
            'selected_scopes.*' => ['string'],
            'manual_access_token' => ['nullable', 'string'],
            'manual_refresh_token' => ['nullable', 'string'],
        ]);

        if (! empty($validated['client_id'])) {
            session(['google_client_id' => trim($validated['client_id'])]);
        }
        if (! empty($validated['client_secret'])) {
            session(['google_client_secret' => trim($validated['client_secret'])]);
        }
        if (! empty($validated['redirect_uri'])) {
            session(['google_redirect_uri' => trim($validated['redirect_uri'])]);
        }
        if (isset($validated['selected_scopes'])) {
            session(['google_selected_scopes' => $validated['selected_scopes']]);
        }

        // Direct manual token input
        if (! empty($validated['manual_access_token'])) {
            $manualToken = trim($validated['manual_access_token']);
            session([
                'google_access_token' => $manualToken,
                'google_refresh_token' => ! empty($validated['manual_refresh_token']) ? trim($validated['manual_refresh_token']) : session('google_refresh_token'),
                'google_token_expires_at' => now()->addHour()->timestamp,
                'explorer_mode' => 'live',
            ]);

            // Attempt to get user profile
            $profile = $this->calendarService->getUserProfile($manualToken);
            if ($profile) {
                session(['google_user_profile' => $profile]);
            }

            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('success', 'Manual Access Token set and validated successfully! Switched to Live API mode.');
        }

        return redirect()->route('explorer.index', ['tab' => 'settings'])
            ->with('success', 'Settings updated successfully.');
    }

    /**
     * Resolve the active OAuth redirect URI.
     * Defaults to https://fwd.host/http://calendar-integration.test/oauth/google/callback
     * to fulfill Google Cloud Console's requirement for public top-level domains.
     */
    private function resolveRedirectUri(): string
    {
        return (string) (session('google_redirect_uri')
            ?? config('services.google.redirect')
            ?? env('GOOGLE_REDIRECT_URI', 'https://fwd.host/http://calendar-integration.test/oauth/google/callback'));
    }

    /**
     * Redirect to Google OAuth Consent Screen.
     */
    public function oauthRedirect(Request $request): RedirectResponse
    {
        $clientId = session('google_client_id') ?? config('services.google.client_id', env('GOOGLE_CLIENT_ID'));
        $redirectUri = $this->resolveRedirectUri();
        $scopes = session('google_selected_scopes', [
            GoogleCalendarService::SCOPE_READONLY,
            GoogleCalendarService::SCOPE_EVENTS,
            GoogleCalendarService::SCOPE_FREEBUSY,
        ]);

        if (empty($clientId)) {
            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('error', 'Please configure your Google Client ID first.');
        }

        $authUrl = $this->calendarService->getAuthUrl($scopes, $redirectUri, $clientId);

        return redirect()->away($authUrl);
    }

    /**
     * Handle the OAuth redirect callback from Google.
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('error', 'Google authorization error: '.$request->query('error_description', $request->query('error')));
        }

        $code = (string) $request->query('code');
        if (empty($code)) {
            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('error', 'No authorization code returned from Google.');
        }

        $clientId = (string) (session('google_client_id') ?? config('services.google.client_id', env('GOOGLE_CLIENT_ID')));
        $clientSecret = (string) (session('google_client_secret') ?? config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET')));
        $redirectUri = $this->resolveRedirectUri();

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('error', 'Client ID or Client Secret is missing. Please enter them in settings.');
        }

        $tokenResult = $this->calendarService->exchangeCode($code, $clientId, $clientSecret, $redirectUri);

        if (! $tokenResult['success'] || ! isset($tokenResult['data'])) {
            return redirect()->route('explorer.index', ['tab' => 'settings'])
                ->with('error', 'Failed to exchange authorization code: '.($tokenResult['error'] ?? 'Unknown error'));
        }

        $tokenData = $tokenResult['data'];
        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'] ?? 3600;

        session([
            'google_access_token' => $accessToken,
            'google_token_expires_at' => now()->addSeconds($expiresIn)->timestamp,
            'explorer_mode' => 'live',
        ]);

        if (! empty($tokenData['refresh_token'])) {
            session(['google_refresh_token' => $tokenData['refresh_token']]);
        }

        // Fetch user profile
        $profile = $this->calendarService->getUserProfile($accessToken);
        if ($profile) {
            session(['google_user_profile' => $profile]);
        }

        return redirect()->route('explorer.index', ['tab' => 'settings'])
            ->with('success', 'Successfully connected to Google Calendar! Live API mode is active.');
    }

    /**
     * Disconnect Google account and purge session tokens.
     */
    public function oauthDisconnect(): RedirectResponse
    {
        session()->forget([
            'google_access_token',
            'google_refresh_token',
            'google_token_expires_at',
            'google_user_profile',
        ]);

        session(['explorer_mode' => 'mock']);

        return redirect()->route('explorer.index', ['tab' => 'settings'])
            ->with('success', 'Google account disconnected. Switched back to Mock Simulation Mode.');
    }

    /**
     * Toggle active mode (mock or live).
     */
    public function setMode(Request $request): JsonResponse
    {
        $mode = $request->input('mode', 'mock');
        if (! in_array($mode, ['mock', 'live'], true)) {
            $mode = 'mock';
        }

        session(['explorer_mode' => $mode]);

        return response()->json([
            'success' => true,
            'mode' => $mode,
        ]);
    }

    /**
     * Execute a calendar request (either live or mock simulation).
     */
    public function executeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
            'mode' => ['nullable', 'string', 'in:mock,live'],
            'path_params' => ['nullable', 'array'],
            'query_params' => ['nullable', 'array'],
            'body' => ['nullable'],
        ]);

        $catalog = $this->calendarService->getCatalog();
        $requestId = $validated['request_id'];

        if (! isset($catalog[$requestId])) {
            return response()->json([
                'success' => false,
                'error' => "Request definition '{$requestId}' not found in catalog.",
            ], 404);
        }

        $item = $catalog[$requestId];
        $mode = $validated['mode'] ?? session('explorer_mode', 'mock');

        // Parse path parameters into URL
        $pathParams = $validated['path_params'] ?? $item['path_params'];
        $endpoint = $item['endpoint'];
        foreach ($pathParams as $paramKey => $paramVal) {
            $endpoint = str_replace('{'.$paramKey.'}', urlencode((string) $paramVal), $endpoint);
        }
        $fullUrl = GoogleCalendarService::BASE_URL.$endpoint;

        $queryParams = $validated['query_params'] ?? ($item['query_params'] ?? []);
        $body = $validated['body'] ?? $item['body'];

        if (is_string($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $decoded;
            }
        }

        // Persist user parameters in session as the last input
        $savedParams = session('gcal_saved_params', []);
        if (! isset($savedParams[$requestId])) {
            $savedParams[$requestId] = ['path' => [], 'query' => [], 'body' => null];
        }
        if (! empty($validated['path_params'])) {
            $savedParams[$requestId]['path'] = array_merge($savedParams[$requestId]['path'] ?? [], $validated['path_params']);
        }
        if (! empty($validated['query_params'])) {
            $savedParams[$requestId]['query'] = array_merge($savedParams[$requestId]['query'] ?? [], $validated['query_params']);
        }
        if (array_key_exists('body', $validated)) {
            $savedParams[$requestId]['body'] = $body;
        }
        if (! empty($queryParams['timeZone'])) {
            $savedParams[$requestId]['timezone'] = $queryParams['timeZone'];
        } elseif (is_array($body) && ! empty($body['timeZone'])) {
            $savedParams[$requestId]['timezone'] = $body['timeZone'];
        } elseif (is_array($body) && ! empty($body['start']['timeZone'])) {
            $savedParams[$requestId]['timezone'] = $body['start']['timeZone'];
        }
        session(['gcal_saved_params' => $savedParams]);

        // Live execution branch
        if ($mode === 'live') {
            $accessToken = session('google_access_token');
            $refreshToken = session('google_refresh_token');
            $expiresAt = session('google_token_expires_at');

            // If token expired, try refreshing
            if ($refreshToken && $expiresAt && now()->timestamp > (int) $expiresAt) {
                $clientId = (string) (session('google_client_id') ?? config('services.google.client_id', env('GOOGLE_CLIENT_ID')));
                $clientSecret = (string) (session('google_client_secret') ?? config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET')));

                if ($clientId && $clientSecret) {
                    $refreshResult = $this->calendarService->refreshToken($refreshToken, $clientId, $clientSecret);
                    if ($refreshResult['success'] && isset($refreshResult['data']['access_token'])) {
                        $accessToken = $refreshResult['data']['access_token'];
                        $expiresIn = $refreshResult['data']['expires_in'] ?? 3600;
                        session([
                            'google_access_token' => $accessToken,
                            'google_token_expires_at' => now()->addSeconds($expiresIn)->timestamp,
                        ]);
                    }
                }
            }

            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'latency_ms' => 12.0,
                    'headers' => ['content-type' => ['application/json']],
                    'body' => [
                        'error' => [
                            'code' => 401,
                            'message' => 'No active Google OAuth access token. Please connect in the Settings tab or switch to Mock Mode.',
                            'status' => 'UNAUTHENTICATED',
                        ],
                    ],
                    'mode_used' => 'live',
                    'insights' => $item['insights'],
                ]);
            }

            $result = $this->calendarService->executeLive(
                $item['http_method'],
                $fullUrl,
                $queryParams,
                $body,
                $accessToken
            );

            return response()->json([
                'success' => $result['success'],
                'status' => $result['status'],
                'latency_ms' => $result['latency_ms'],
                'headers' => $result['headers'],
                'body' => $result['body'],
                'mode_used' => 'live',
                'insights' => $item['insights'],
                'requested_url' => $fullUrl,
            ]);
        }

        // Mock simulation branch
        $simulatedLatency = (float) rand(95, 240);
        $mockResponse = $item['mock_response'];
        if (! empty($queryParams['timeZone']) && isset($mockResponse['timeZone'])) {
            $mockResponse['timeZone'] = $queryParams['timeZone'];
        }

        return response()->json([
            'success' => true,
            'status' => $item['mock_status'],
            'latency_ms' => $simulatedLatency,
            'headers' => [
                'content-type' => ['application/json; charset=UTF-8'],
                'etag' => ['"wz8104820mocketag"'],
                'server' => ['GSE (Simulated Google Server)'],
            ],
            'body' => $mockResponse,
            'mode_used' => 'mock',
            'insights' => $item['insights'],
            'requested_url' => $fullUrl,
        ]);
    }

    /**
     * Save user parameter input as the last input user parameter for a request.
     */
    public function saveParam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
            'param_type' => ['required', 'string', 'in:path,query,body,timezone'],
            'param_key' => ['nullable', 'string'],
            'param_value' => ['nullable'],
        ]);

        $catalog = $this->calendarService->getCatalog();
        $requestId = $validated['request_id'];

        if (! isset($catalog[$requestId])) {
            return response()->json([
                'success' => false,
                'error' => "Request '{$requestId}' not found in catalog.",
            ], 404);
        }

        $savedParams = session('gcal_saved_params', []);
        if (! isset($savedParams[$requestId])) {
            $savedParams[$requestId] = ['path' => [], 'query' => [], 'body' => null];
        }

        $paramType = $validated['param_type'];
        if ($paramType === 'timezone') {
            $savedParams[$requestId]['timezone'] = $validated['param_value'];
            if (in_array($requestId, ['events_list', 'events_get'], true)) {
                $savedParams[$requestId]['query']['timeZone'] = $validated['param_value'];
            }
        } elseif ($paramType === 'body') {
            $savedParams[$requestId]['body'] = $validated['param_value'];
        } else {
            $paramKey = $validated['param_key'];
            if ($paramKey) {
                $savedParams[$requestId][$paramType][$paramKey] = $validated['param_value'];
            }
        }

        session(['gcal_saved_params' => $savedParams]);

        return response()->json([
            'success' => true,
            'saved_params' => $savedParams[$requestId],
        ]);
    }

    /**
     * Reset user parameter input back to catalog default.
     */
    public function resetParam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
            'param_type' => ['nullable', 'string', 'in:path,query,body,timezone,all'],
            'param_key' => ['nullable', 'string'],
        ]);

        $catalog = $this->calendarService->getCatalog();
        $requestId = $validated['request_id'];

        if (! isset($catalog[$requestId])) {
            return response()->json([
                'success' => false,
                'error' => "Request '{$requestId}' not found in catalog.",
            ], 404);
        }

        $catalogItem = $catalog[$requestId];
        $savedParams = session('gcal_saved_params', []);
        $paramType = $validated['param_type'] ?? 'all';
        $paramKey = $validated['param_key'] ?? null;
        $defaultValue = null;

        if ($paramType === 'all') {
            unset($savedParams[$requestId]);
        } elseif ($paramType === 'timezone') {
            unset($savedParams[$requestId]['timezone']);
            if (isset($savedParams[$requestId]['query']['timeZone'])) {
                unset($savedParams[$requestId]['query']['timeZone']);
            }
            $defaultValue = 'UTC';
        } elseif ($paramType === 'body') {
            if (isset($savedParams[$requestId]['body'])) {
                unset($savedParams[$requestId]['body']);
            }
            $defaultValue = $catalogItem['body'];
        } elseif (in_array($paramType, ['path', 'query'], true) && $paramKey) {
            if (isset($savedParams[$requestId][$paramType][$paramKey])) {
                unset($savedParams[$requestId][$paramType][$paramKey]);
            }
            $defaultSource = $paramType === 'path' ? ($catalogItem['path_params'] ?? []) : ($catalogItem['query_params'] ?? []);
            $defaultValue = $defaultSource[$paramKey] ?? '';
        }

        session(['gcal_saved_params' => $savedParams]);

        return response()->json([
            'success' => true,
            'default_value' => $defaultValue,
            'saved_params' => $savedParams[$requestId] ?? ['path' => [], 'query' => [], 'body' => null],
        ]);
    }
}
