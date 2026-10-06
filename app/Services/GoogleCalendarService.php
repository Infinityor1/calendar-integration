<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Class GoogleCalendarService
 *
 * 1) General Naming Conventions:
 * - Class Name: PascalCase (`GoogleCalendarService`) adhering to Laravel's service-layer architecture.
 * - API Constants: SCREAMING_SNAKE_CASE (`BASE_URL`, `OAUTH_TOKEN_URL`, `SCOPE_*`) representing external Google OAuth 2.0 and REST specifications.
 * - Methods: Expressive camelCase action verbs (`getAuthUrl`, `exchangeCode`, `refreshToken`, `executeLive`, `getCatalog`).
 * - Catalog Keys: Structured in snake_case as `{resource}_{action}` (e.g. `calendarList_list`, `events_insert_meet`, `freebusy_query`), matching Google Calendar API v3 resource hierarchies.
 *
 * 2) Function of the Code:
 * Central service responsible for interacting with the Google Calendar API v3 (REST).
 * Handles the OAuth 2.0 Authorization Code Flow (generating consent URLs, exchanging authorization codes for tokens,
 * refreshing expired access tokens), executing live authenticated HTTP requests using Laravel's Http client,
 * and providing a pre-configured catalog of mock responses and developer implementation insights.
 */
class GoogleCalendarService
{
    /** Base endpoint for the Google Calendar API v3 REST service. */
    public const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    /** Google OAuth 2.0 authorization endpoint for user consent. */
    public const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** Google OAuth 2.0 token endpoint for exchanging codes and refreshing tokens. */
    public const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Read-only access to user calendars and events. */
    public const SCOPE_READONLY = 'https://www.googleapis.com/auth/calendar.readonly';

    /** Read and write access to calendar events (appointments, meetings). */
    public const SCOPE_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

    /** Read-only access to calendar events specifically. */
    public const SCOPE_EVENTS_READONLY = 'https://www.googleapis.com/auth/calendar.events.readonly';

    /** Full administrative access to calendars, events, sharing (ACL), and settings. */
    public const SCOPE_FULL = 'https://www.googleapis.com/auth/calendar';

    /** Free/busy availability query scope (privacy-preserving availability checks). */
    public const SCOPE_FREEBUSY = 'https://www.googleapis.com/auth/calendar.freebusy';

    /** Read-only access to user account calendar preferences (timezone, time format). */
    public const SCOPE_SETTINGS_READONLY = 'https://www.googleapis.com/auth/calendar.settings.readonly';

    /**
     * Get the list of common scopes available for selection.
     *
     * @return array<string, array{name: string, description: string}>
     */
    public function getAvailableScopes(): array
    {
        return [
            self::SCOPE_READONLY => [
                'name' => 'Calendar Read-Only',
                'description' => 'View events and calendars without modification permission.',
            ],
            self::SCOPE_EVENTS => [
                'name' => 'Calendar Events (Read/Write)',
                'description' => 'View, create, edit, and delete events on accessible calendars.',
            ],
            self::SCOPE_FREEBUSY => [
                'name' => 'Free/Busy Availability',
                'description' => 'Query availability blocks without seeing event titles or details.',
            ],
            self::SCOPE_SETTINGS_READONLY => [
                'name' => 'Settings Read-Only',
                'description' => 'View user calendar preferences such as primary timezone and time format.',
            ],
            self::SCOPE_FULL => [
                'name' => 'Calendar Full Access',
                'description' => 'Complete control over calendars, events, access control lists, and settings.',
            ],
        ];
    }

    /**
     * Generate the Google OAuth authorization URL.
     *
     * @param  array<int, string>  $scopes
     */
    public function getAuthUrl(array $scopes, string $redirectUri, string $clientId, ?string $state = null): string
    {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', array_unique(array_merge($scopes, ['openid', 'email', 'profile']))),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state ?? Str::random(32),
        ];

        return self::OAUTH_AUTH_URL.'?'.http_build_query($params);
    }

    /**
     * Exchange the authorization code for access and refresh tokens.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function exchangeCode(string $code, string $clientId, string $clientSecret, string $redirectUri): array
    {
        try {
            $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error_description') ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh an expired access token using the refresh token.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function refreshToken(string $refreshToken, string $clientId, string $clientSecret): array
    {
        try {
            $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error_description') ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch user profile info (email and name) using the access token.
     *
     * @return array<string, mixed>|null
     */
    public function getUserProfile(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Execute a live API request to Google Calendar.
     *
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, latency_ms: float, headers: array<string, mixed>, body: mixed, success: bool}
     */
    public function executeLive(string $httpMethod, string $fullUrl, array $queryParams, ?array $body, string $accessToken): array
    {
        $startTime = microtime(true);

        try {
            $client = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15);

            $method = strtoupper($httpMethod);
            $hasBody = $body !== null && (! is_array($body) || count($body) > 0);

            $response = match ($method) {
                'GET' => $client->get($fullUrl, $queryParams),
                'POST' => $hasBody
                    ? $client->withQueryParameters($queryParams)->post($fullUrl, $body)
                    : $client->withQueryParameters($queryParams)->withHeaders(['Content-Length' => '0'])->send('POST', $fullUrl),
                'PATCH' => $hasBody
                    ? $client->withQueryParameters($queryParams)->patch($fullUrl, $body)
                    : $client->withQueryParameters($queryParams)->withHeaders(['Content-Length' => '0'])->send('PATCH', $fullUrl),
                'PUT' => $hasBody
                    ? $client->withQueryParameters($queryParams)->put($fullUrl, $body)
                    : $client->withQueryParameters($queryParams)->withHeaders(['Content-Length' => '0'])->send('PUT', $fullUrl),
                'DELETE' => $client->withQueryParameters($queryParams)->delete($fullUrl),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => $response->status(),
                'latency_ms' => $latency,
                'headers' => $response->headers(),
                'body' => $response->json() ?? $response->body(),
                'success' => $response->successful(),
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 500,
                'latency_ms' => $latency,
                'headers' => [],
                'body' => [
                    'error' => [
                        'code' => 500,
                        'message' => 'Connection failed: '.$e->getMessage(),
                    ],
                ],
                'success' => false,
            ];
        }
    }

    /**
     * Returns the comprehensive catalog of official Google Calendar API v3 requests.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        $now = now();
        $startIso = $now->copy()->addDay()->setHour(14)->setMinute(0)->setSecond(0)->toRfc3339String();
        $endIso = $now->copy()->addDay()->setHour(15)->setMinute(0)->setSecond(0)->toRfc3339String();
        $timeMin = $now->copy()->startOfDay()->toRfc3339String();
        $timeMax = $now->copy()->addDays(7)->endOfDay()->toRfc3339String();

        return [
            // ---------------------------------------------------------
            // 1. CalendarList: List
            // ---------------------------------------------------------
            'calendarList_list' => [
                'id' => 'calendarList_list',
                'resource' => 'CalendarList',
                'method_name' => 'calendarList.list',
                'http_method' => 'GET',
                'endpoint' => '/users/me/calendarList',
                'full_url' => self::BASE_URL.'/users/me/calendarList',
                'summary' => 'List User Calendars',
                'description' => 'Retrieves the list of calendars on the authenticated user\'s calendar list. Use this to discover accessible calendars and locate the primary calendar ID.',
                'scopes' => [self::SCOPE_READONLY, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Enable Google Calendar API in Google Cloud Console > APIs & Services > Library.',
                    'Consent screen must include either calendar.readonly or calendar scope.',
                ],
                'path_params' => [],
                'query_params' => [
                    'minAccessRole' => 'reader',
                    'showHidden' => 'false',
                ],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#calendarList',
                    'etag' => '"p32of89123891000"',
                    'nextSyncToken' => 'CPDP2_jU34kCEAE=',
                    'items' => [
                        [
                            'kind' => 'calendar#calendarListEntry',
                            'id' => 'alex.developer@example.com',
                            'summary' => 'alex.developer@example.com',
                            'description' => 'Primary user calendar',
                            'timeZone' => 'Asia/Singapore',
                            'colorId' => '14',
                            'backgroundColor' => '#9fe1e7',
                            'foregroundColor' => '#000000',
                            'selected' => true,
                            'accessRole' => 'owner',
                            'primary' => true,
                            'defaultReminders' => [
                                ['method' => 'popup', 'minutes' => 10],
                            ],
                        ],
                        [
                            'kind' => 'calendar#calendarListEntry',
                            'id' => 'c_team_releases_123@group.calendar.google.com',
                            'summary' => 'Product Releases & Sprints',
                            'timeZone' => 'UTC',
                            'colorId' => '7',
                            'backgroundColor' => '#039be5',
                            'foregroundColor' => '#ffffff',
                            'selected' => true,
                            'accessRole' => 'writer',
                        ],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'primary: true' => 'Identifies the user\'s main calendar. You can also use the literal alias "primary" in place of the email address.',
                        'accessRole' => 'Indicates user permissions: "owner", "writer", "reader", or "freeBusyReader". Verify write permissions before offering event creation in your app.',
                        'nextSyncToken' => 'Store this token to perform incremental synchronization later without reloading all calendars.',
                    ],
                    'app_actions' => [
                        'Display a calendar selector dropdown in your app allowing users to pick which calendar events will sync to.',
                        'Store the selected `id` in your database user settings table.',
                    ],
                    'error_handling' => [
                        '401 Unauthorized: Access token expired. Call Google OAuth token endpoint with your refresh_token.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 2. Events: List
            // ---------------------------------------------------------
            'events_list' => [
                'id' => 'events_list',
                'resource' => 'Events',
                'method_name' => 'events.list',
                'http_method' => 'GET',
                'endpoint' => '/calendars/{calendarId}/events',
                'full_url' => self::BASE_URL.'/calendars/primary/events',
                'summary' => 'List Events (Agenda Query)',
                'description' => 'Retrieves events on the specified calendar within a time range. Setting singleEvents=true expands recurring events into individual instances and enables ordering by start time.',
                'scopes' => [self::SCOPE_READONLY, self::SCOPE_EVENTS_READONLY, self::SCOPE_EVENTS],
                'cloud_actions' => [
                    'Requires calendar.readonly or calendar.events scope.',
                    'Always provide singleEvents=true and orderBy=startTime when building agenda or calendar views.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                ],
                'query_params' => [
                    'timeMin' => $timeMin,
                    'timeMax' => $timeMax,
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'timeZone' => 'UTC',
                    'maxResults' => '10',
                ],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#events',
                    'etag' => '"p33m19901420000"',
                    'summary' => 'alex.developer@example.com',
                    'timeZone' => 'UTC',
                    'nextSyncToken' => 'CLD7xOmU34kCEAE=',
                    'items' => [
                        [
                            'kind' => 'calendar#event',
                            'id' => 'evt_product_demo_001',
                            'status' => 'confirmed',
                            'htmlLink' => 'https://www.google.com/calendar/event?eid=ZXZ0X3Byb2R1Y3RfZGVtb18wMDEgYWxleA',
                            'created' => '2026-10-05T08:00:00Z',
                            'updated' => '2026-10-05T08:15:00Z',
                            'summary' => 'Q4 Roadmap & Feature Planning',
                            'description' => 'Review upcoming product sprint deliverables.',
                            'location' => 'Meeting Room 4B / Remote',
                            'start' => ['dateTime' => $startIso, 'timeZone' => 'UTC'],
                            'end' => ['dateTime' => $endIso, 'timeZone' => 'UTC'],
                            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                            'attendees' => [
                                ['email' => 'alex.developer@example.com', 'responseStatus' => 'accepted', 'self' => true],
                                ['email' => 'sarah.lead@example.com', 'responseStatus' => 'needsAction'],
                            ],
                        ],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'singleEvents: true' => 'Crucial! If false, recurring events return as master recurrence rules without expanded dates. If true, instances are expanded with exact start/end times.',
                        'hangoutLink' => 'Google Meet link automatically provided if conferencing was attached.',
                        'attendees[].responseStatus' => 'Shows participant RSVP: "accepted", "declined", "tentative", or "needsAction".',
                    ],
                    'app_actions' => [
                        'Store `id` to sync event state in local database.',
                        'Use `nextPageToken` for pagination if there are more than `maxResults` events.',
                    ],
                    'error_handling' => [
                        '404 Not Found: The specified calendarId does not exist or user lacks permission to access it.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 3. Events: Get
            // ---------------------------------------------------------
            'events_get' => [
                'id' => 'events_get',
                'resource' => 'Events',
                'method_name' => 'events.get',
                'http_method' => 'GET',
                'endpoint' => '/calendars/{calendarId}/events/{eventId}',
                'full_url' => self::BASE_URL.'/calendars/primary/events/evt_product_demo_001',
                'summary' => 'Get Single Event',
                'description' => 'Retrieves full metadata for a specific event by its ID.',
                'scopes' => [self::SCOPE_READONLY, self::SCOPE_EVENTS],
                'cloud_actions' => [
                    'Requires the target eventId returned by events.insert or events.list.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                    'eventId' => 'evt_product_demo_001',
                ],
                'query_params' => [
                    'timeZone' => 'UTC',
                ],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#event',
                    'id' => 'evt_product_demo_001',
                    'status' => 'confirmed',
                    'htmlLink' => 'https://www.google.com/calendar/event?eid=ZXZ0X3Byb2R1Y3RfZGVtb18wMDEgYWxleA',
                    'summary' => 'Q4 Roadmap & Feature Planning',
                    'description' => 'Review upcoming product sprint deliverables.',
                    'start' => ['dateTime' => $startIso, 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => $endIso, 'timeZone' => 'UTC'],
                    'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                    'conferenceData' => [
                        'entryPoints' => [
                            ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij', 'label' => 'meet.google.com/abc-defg-hij'],
                        ],
                        'conferenceSolution' => ['name' => 'Google Meet'],
                    ],
                    'organizer' => ['email' => 'alex.developer@example.com', 'self' => true],
                ],
                'insights' => [
                    'key_fields' => [
                        'status: "cancelled"' => 'Google Calendar soft-deletes events. If an event was deleted, get returns status "cancelled" with minimal metadata.',
                        'conferenceData.entryPoints' => 'Contains video call URIs, phone dial-in numbers, and PIN codes for remote meetings.',
                    ],
                    'app_actions' => [
                        'Display detailed appointment modal in your frontend.',
                    ],
                    'error_handling' => [
                        '404 Not Found: Event ID does not exist or has been permanently purged.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 4. Events: Insert (With Google Meet Video Conference)
            // ---------------------------------------------------------
            'events_insert_meet' => [
                'id' => 'events_insert_meet',
                'resource' => 'Events',
                'method_name' => 'events.insert',
                'http_method' => 'POST',
                'endpoint' => '/calendars/{calendarId}/events',
                'full_url' => self::BASE_URL.'/calendars/primary/events?conferenceDataVersion=1',
                'summary' => 'Create Event with Google Meet',
                'description' => 'Creates a new calendar event with an auto-generated Google Meet video conference link. Passing conferenceDataVersion=1 query parameter is mandatory.',
                'scopes' => [self::SCOPE_EVENTS, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Scope must have write access: https://www.googleapis.com/auth/calendar.events',
                    'Query param conferenceDataVersion=1 MUST be present in URL for conferenceData createRequest to be processed.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                ],
                'query_params' => [
                    'conferenceDataVersion' => '1',
                    'sendUpdates' => 'all',
                ],
                'body' => [
                    'summary' => 'Client Demo & Architecture Walkthrough',
                    'description' => 'Demonstrating the new calendar integration workflow.',
                    'start' => [
                        'dateTime' => $startIso,
                        'timeZone' => 'UTC',
                    ],
                    'end' => [
                        'dateTime' => $endIso,
                        'timeZone' => 'UTC',
                    ],
                    'attendees' => [
                        ['email' => 'client.partner@example.com'],
                    ],
                    'conferenceData' => [
                        'createRequest' => [
                            'requestId' => 'req_demo_'.time(),
                            'conferenceSolutionKey' => [
                                'type' => 'hangoutsMeet',
                            ],
                        ],
                    ],
                    'reminders' => [
                        'useDefault' => false,
                        'overrides' => [
                            ['method' => 'popup', 'minutes' => 15],
                            ['method' => 'email', 'minutes' => 60],
                        ],
                    ],
                ],
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#event',
                    'id' => 'meet_evt_998124719',
                    'status' => 'confirmed',
                    'htmlLink' => 'https://www.google.com/calendar/event?eid=bWVldF9ldnRfeHl6',
                    'summary' => 'Client Demo & Architecture Walkthrough',
                    'hangoutLink' => 'https://meet.google.com/xyz-ghjk-vbn',
                    'start' => ['dateTime' => $startIso, 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => $endIso, 'timeZone' => 'UTC'],
                    'conferenceData' => [
                        'entryPoints' => [
                            ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/xyz-ghjk-vbn'],
                        ],
                        'conferenceSolution' => [
                            'name' => 'Google Meet',
                            'iconUri' => 'https://fonts.gstatic.com/s/i/productlogos/meet_2020q4/v6/web-512dp/logo_meet_2020q4_color_2x_web_512dp.png',
                        ],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'hangoutLink' => 'The generated Google Meet URL. Save this in your database bookings table to show a "Join Google Meet" button to both host and client.',
                        'id' => 'Save this primary event ID for rescheduling or cancellation.',
                        'sendUpdates: all' => 'Notifies attendees via official Google Calendar email invites with accept/decline links.',
                    ],
                    'app_actions' => [
                        'Save `google_event_id` and `hangoutLink` into your local appointment record.',
                    ],
                    'error_handling' => [
                        '400 Bad Request: Missing conferenceDataVersion=1 or invalid RFC3339 timestamp format.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 5. Events: QuickAdd
            // ---------------------------------------------------------
            'events_quickAdd' => [
                'id' => 'events_quickAdd',
                'resource' => 'Events',
                'method_name' => 'events.quickAdd',
                'http_method' => 'POST',
                'endpoint' => '/calendars/{calendarId}/events/quickAdd',
                'full_url' => self::BASE_URL.'/calendars/primary/events/quickAdd?text=Dinner+with+Alex+tomorrow+at+7pm',
                'summary' => 'QuickAdd Event (Natural Language)',
                'description' => 'Creates an event based on a simple plain text string parsed automatically by Google (e.g., "Dinner with Alex tomorrow at 7pm").',
                'scopes' => [self::SCOPE_EVENTS, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Requires calendar.events scope.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                ],
                'query_params' => [
                    'text' => 'Strategy Meeting with Sarah next Monday at 10am to 11am',
                    'sendUpdates' => 'none',
                ],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#event',
                    'id' => 'quickadd_evt_381920',
                    'status' => 'confirmed',
                    'summary' => 'Strategy Meeting with Sarah',
                    'start' => ['dateTime' => $now->copy()->next('Monday')->setHour(10)->toRfc3339String()],
                    'end' => ['dateTime' => $now->copy()->next('Monday')->setHour(11)->toRfc3339String()],
                    'htmlLink' => 'https://www.google.com/calendar/event?eid=cXVpY2thZGRfZXZ0',
                ],
                'insights' => [
                    'key_fields' => [
                        'text parameter' => 'Google\'s natural language processor extracts the summary, date, and start/end time directly from conversational strings.',
                    ],
                    'app_actions' => [
                        'Allows you to build command bars or chatbot scheduling inputs where users type freeform schedule commands.',
                    ],
                    'error_handling' => [
                        'If text cannot be parsed with a time, Google defaults to an all-day event for today.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 6. Events: Patch
            // ---------------------------------------------------------
            'events_patch' => [
                'id' => 'events_patch',
                'resource' => 'Events',
                'method_name' => 'events.patch',
                'http_method' => 'PATCH',
                'endpoint' => '/calendars/{calendarId}/events/{eventId}',
                'full_url' => self::BASE_URL.'/calendars/primary/events/evt_product_demo_001',
                'summary' => 'Patch / Reschedule Event',
                'description' => 'Updates specific fields of an event using patch semantics without overwriting unspecified attributes.',
                'scopes' => [self::SCOPE_EVENTS, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Requires calendar.events scope.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                    'eventId' => 'evt_product_demo_001',
                ],
                'query_params' => [
                    'sendUpdates' => 'all',
                ],
                'body' => [
                    'summary' => 'Q4 Roadmap & Feature Planning (Rescheduled to 3 PM)',
                    'start' => [
                        'dateTime' => $now->copy()->addDay()->setHour(15)->setMinute(0)->toRfc3339String(),
                        'timeZone' => 'UTC',
                    ],
                    'end' => [
                        'dateTime' => $now->copy()->addDay()->setHour(16)->setMinute(0)->toRfc3339String(),
                        'timeZone' => 'UTC',
                    ],
                ],
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#event',
                    'id' => 'evt_product_demo_001',
                    'status' => 'confirmed',
                    'summary' => 'Q4 Roadmap & Feature Planning (Rescheduled to 3 PM)',
                    'start' => ['dateTime' => $now->copy()->addDay()->setHour(15)->toRfc3339String()],
                    'end' => ['dateTime' => $now->copy()->addDay()->setHour(16)->toRfc3339String()],
                    'updated' => now()->toRfc3339String(),
                ],
                'insights' => [
                    'key_fields' => [
                        'updated' => 'Timestamp of modification. Use this to maintain sync with other systems.',
                    ],
                    'app_actions' => [
                        'Ideal for drag-and-drop rescheduling in calendar views.',
                    ],
                    'error_handling' => [
                        'Use PATCH instead of PUT when updating partial fields so attendees and existing conference links are preserved.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 7. Events: Delete
            // ---------------------------------------------------------
            'events_delete' => [
                'id' => 'events_delete',
                'resource' => 'Events',
                'method_name' => 'events.delete',
                'http_method' => 'DELETE',
                'endpoint' => '/calendars/{calendarId}/events/{eventId}',
                'full_url' => self::BASE_URL.'/calendars/primary/events/evt_product_demo_001',
                'summary' => 'Delete / Cancel Event',
                'description' => 'Deletes an event from the calendar and notifies attendees of cancellation if sendUpdates=all is set.',
                'scopes' => [self::SCOPE_EVENTS, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Requires calendar.events scope.',
                ],
                'path_params' => [
                    'calendarId' => 'primary',
                    'eventId' => 'evt_product_demo_001',
                ],
                'query_params' => [
                    'sendUpdates' => 'all',
                ],
                'body' => null,
                'mock_status' => 204,
                'mock_response' => [
                    'message' => 'HTTP 204 No Content: Event successfully deleted and attendees notified.',
                ],
                'insights' => [
                    'key_fields' => [
                        '204 No Content' => 'A successful deletion returns empty body with HTTP 204 status code.',
                    ],
                    'app_actions' => [
                        'Mark the appointment as cancelled or delete it in your application database.',
                    ],
                    'error_handling' => [
                        'Subsequent GET requests on this eventId will return status="cancelled" (or 404/410 Gone once purged).',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 8. Freebusy: Query
            // ---------------------------------------------------------
            'freebusy_query' => [
                'id' => 'freebusy_query',
                'resource' => 'Freebusy',
                'method_name' => 'freebusy.query',
                'http_method' => 'POST',
                'endpoint' => '/freeBusy',
                'full_url' => self::BASE_URL.'/freeBusy',
                'summary' => 'Query Free/Busy Availability',
                'description' => 'Returns busy time windows for a set of calendars over a specified time interval. Perfect for scheduling and booking apps like Calendly to detect clashes without reading private event titles.',
                'scopes' => [self::SCOPE_FREEBUSY, self::SCOPE_READONLY, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Only requires https://www.googleapis.com/auth/calendar.freebusy scope (minimal privacy scope).',
                ],
                'path_params' => [],
                'query_params' => [],
                'body' => [
                    'timeMin' => $now->copy()->startOfDay()->toRfc3339String(),
                    'timeMax' => $now->copy()->addDays(2)->endOfDay()->toRfc3339String(),
                    'timeZone' => 'UTC',
                    'items' => [
                        ['id' => 'primary'],
                    ],
                ],
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#freeBusy',
                    'timeMin' => $now->copy()->startOfDay()->toRfc3339String(),
                    'timeMax' => $now->copy()->addDays(2)->endOfDay()->toRfc3339String(),
                    'calendars' => [
                        'primary' => [
                            'busy' => [
                                [
                                    'start' => $now->copy()->addDay()->setHour(9)->setMinute(0)->toRfc3339String(),
                                    'end' => $now->copy()->addDay()->setHour(10)->setMinute(30)->toRfc3339String(),
                                ],
                                [
                                    'start' => $now->copy()->addDay()->setHour(14)->setMinute(0)->toRfc3339String(),
                                    'end' => $now->copy()->addDay()->setHour(15)->setMinute(0)->toRfc3339String(),
                                ],
                            ],
                        ],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'calendars[].busy' => 'Array of time ranges when the user is already booked. Subtract these intervals from working hours to compute open booking slots.',
                        'body.timeZone' => 'Controls the timezone used for output busy intervals. If omitted or set to UTC, intervals match UTC (Z). If set to a named timezone (e.g. Asia/Singapore), Google automatically converts the UTC event timestamps to that timezone (+08:00).',
                    ],
                    'app_actions' => [
                        'Power availability slot pickers for customers without requiring access to read sensitive event subjects.',
                    ],
                    'error_handling' => [
                        'If querying secondary external calendars, ensure those users have shared free/busy visibility with the authorized account.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 9. Colors: Get
            // ---------------------------------------------------------
            'colors_get' => [
                'id' => 'colors_get',
                'resource' => 'Colors',
                'method_name' => 'colors.get',
                'http_method' => 'GET',
                'endpoint' => '/colors',
                'full_url' => self::BASE_URL.'/colors',
                'summary' => 'Get Color Palette Definitions',
                'description' => 'Returns the official Google color definitions for calendar colors and event colors (mapping colorId 1 through 11 to hex background/foreground colors).',
                'scopes' => [self::SCOPE_READONLY, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Requires calendar.readonly or calendar scope.',
                ],
                'path_params' => [],
                'query_params' => [],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#colors',
                    'updated' => '2026-08-01T00:00:00Z',
                    'event' => [
                        '1' => ['background' => '#a4bdfc', 'foreground' => '#1d1d1d'],
                        '2' => ['background' => '#7ae7bf', 'foreground' => '#1d1d1d'],
                        '7' => ['background' => '#039be5', 'foreground' => '#ffffff'],
                        '11' => ['background' => '#d60000', 'foreground' => '#ffffff'],
                    ],
                    'calendar' => [
                        '1' => ['background' => '#ac725e', 'foreground' => '#ffffff'],
                        '14' => ['background' => '#9fe1e7', 'foreground' => '#000000'],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'event[colorId]' => 'Google does not let you pass custom arbitrary hex colors when creating an event; you must pass a valid colorId string (e.g. "1" to "11").',
                    ],
                    'app_actions' => [
                        'Cache these colors in your application UI so your color pickers match Google Calendar exactly.',
                    ],
                    'error_handling' => [
                        'Palette rarely changes; cache this response for up to 30 days.',
                    ],
                ],
            ],

            // ---------------------------------------------------------
            // 10. Settings: List
            // ---------------------------------------------------------
            'settings_list' => [
                'id' => 'settings_list',
                'resource' => 'Settings',
                'method_name' => 'settings.list',
                'http_method' => 'GET',
                'endpoint' => '/users/me/settings',
                'full_url' => self::BASE_URL.'/users/me/settings',
                'summary' => 'Get User Calendar Settings',
                'description' => 'Returns user-specific settings such as default timezone, format (12h/24h), and week start day.',
                'scopes' => [self::SCOPE_SETTINGS_READONLY, self::SCOPE_READONLY, self::SCOPE_FULL],
                'cloud_actions' => [
                    'Requires calendar.settings.readonly scope.',
                ],
                'path_params' => [],
                'query_params' => [],
                'body' => null,
                'mock_status' => 200,
                'mock_response' => [
                    'kind' => 'calendar#settings',
                    'etag' => '"p32of89123891000"',
                    'items' => [
                        ['id' => 'timezone', 'value' => 'Asia/Singapore'],
                        ['id' => 'format24HourTime', 'value' => 'true'],
                        ['id' => 'weekStart', 'value' => '1'],
                        ['id' => 'defaultEventLength', 'value' => '60'],
                    ],
                ],
                'insights' => [
                    'key_fields' => [
                        'timezone' => 'Essential for accurately parsing dates in your app without causing 1-day offset bugs.',
                        'weekStart' => '0 is Sunday, 1 is Monday. Use this to format date-pickers to match user habits.',
                    ],
                    'app_actions' => [
                        'Auto-configure your frontend timezone and calendar layout based on user preferences.',
                    ],
                    'error_handling' => [
                        'Settings are read-only via standard API endpoints.',
                    ],
                ],
            ],
        ];
    }
}
