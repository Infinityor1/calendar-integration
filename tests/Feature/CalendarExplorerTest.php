<?php

use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Http;

test('it renders the calendar explorer home page', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Google Calendar API Explorer');
    $response->assertSee('API Explorer');
});

test('it renders the settings tab', function () {
    $response = $this->get('/?tab=settings');

    $response->assertStatus(200);
    $response->assertSee('API Access Configuration');
    $response->assertSee('Google OAuth 2.0 Web Client Credentials');
    $response->assertSee('Direct Token Bypass');
});

test('it saves client credentials in session', function () {
    $response = $this->post('/settings', [
        'client_id' => 'test-client-id-12345.apps.googleusercontent.com',
        'client_secret' => 'test-secret-value-abc',
        'redirect_uri' => 'http://localhost:8000/oauth/google/callback',
        'selected_scopes' => [
            GoogleCalendarService::SCOPE_READONLY,
            GoogleCalendarService::SCOPE_EVENTS,
        ],
    ]);

    $response->assertRedirect('/?tab=settings');
    $response->assertSessionHas('google_client_id', 'test-client-id-12345.apps.googleusercontent.com');
    $response->assertSessionHas('google_client_secret', 'test-secret-value-abc');
});

test('it saves direct manual access token and switches to live mode', function () {
    $response = $this->post('/settings', [
        'manual_access_token' => 'ya29.sample_test_token',
    ]);

    $response->assertRedirect('/?tab=settings');
    $response->assertSessionHas('google_access_token', 'ya29.sample_test_token');
    $response->assertSessionHas('explorer_mode', 'live');
});

test('it switches execution mode via api', function () {
    $response = $this->postJson('/api/mode', [
        'mode' => 'live',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'mode' => 'live',
    ]);
});

test('it executes mock request and returns status and insights', function () {
    $response = $this->postJson('/api/execute', [
        'request_id' => 'events_insert_meet',
        'mode' => 'mock',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'status' => 200,
        'mode_used' => 'mock',
    ]);
    $response->assertJsonStructure([
        'status',
        'latency_ms',
        'headers',
        'body' => [
            'id',
            'summary',
            'hangoutLink',
        ],
        'insights' => [
            'key_fields',
            'app_actions',
            'error_handling',
        ],
    ]);
});

test('it disconnects google account and purges tokens', function () {
    session([
        'google_access_token' => 'token_to_purge',
        'google_refresh_token' => 'refresh_to_purge',
    ]);

    $response = $this->post('/oauth/google/disconnect');

    $response->assertRedirect('/?tab=settings');
    $response->assertSessionMissing('google_access_token');
    $response->assertSessionMissing('google_refresh_token');
    $response->assertSessionHas('explorer_mode', 'mock');
});

test('it saves and redirects with fwd.host proxy redirect uri', function () {
    $proxyUri = 'https://fwd.host/http://calendar-integration.test/oauth/google/callback';

    $response = $this->post('/settings', [
        'client_id' => '123456789.apps.googleusercontent.com',
        'client_secret' => 'test-secret',
        'redirect_uri' => $proxyUri,
    ]);

    $response->assertRedirect('/?tab=settings');
    $response->assertSessionHas('google_redirect_uri', $proxyUri);

    $redirectResponse = $this->get('/oauth/google/redirect');
    $redirectResponse->assertRedirect();
    $targetUrl = $redirectResponse->headers->get('Location');
    expect($targetUrl)->toContain(urlencode($proxyUri));
});

test('it saves user input parameter in session via api', function () {
    $response = $this->postJson('/api/params/save', [
        'request_id' => 'events_list',
        'param_type' => 'query',
        'param_key' => 'maxResults',
        'param_value' => '50',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'saved_params' => [
            'query' => [
                'maxResults' => '50',
            ],
        ],
    ]);

    $saved = session('gcal_saved_params');
    expect($saved['events_list']['query']['maxResults'])->toBe('50');
});

test('it restores saved user parameters on page load', function () {
    session([
        'gcal_saved_params' => [
            'events_list' => [
                'path' => [
                    'calendarId' => 'custom-user-calendar@group.calendar.google.com',
                ],
                'query' => [
                    'maxResults' => '75',
                ],
            ],
        ],
    ]);

    $response = $this->get('/?request=events_list');

    $response->assertStatus(200);
    $response->assertSee('custom-user-calendar@group.calendar.google.com');
    $response->assertSee('value="75"', false);
    $response->assertSee('reset-btn-path-calendarId');
    $response->assertSee('reset-btn-query-maxResults');
});

test('it resets user input parameter back to default via api', function () {
    session([
        'gcal_saved_params' => [
            'events_list' => [
                'path' => [
                    'calendarId' => 'custom-calendar-to-reset',
                ],
            ],
        ],
    ]);

    $response = $this->postJson('/api/params/reset', [
        'request_id' => 'events_list',
        'param_type' => 'path',
        'param_key' => 'calendarId',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'default_value' => 'primary',
    ]);

    $saved = session('gcal_saved_params');
    expect($saved['events_list']['path'])->not->toHaveKey('calendarId');
});

test('it saves executed parameters as last input in session during execution', function () {
    $response = $this->postJson('/api/execute', [
        'request_id' => 'events_get',
        'mode' => 'mock',
        'path_params' => [
            'calendarId' => 'my-work-calendar',
            'eventId' => 'custom_evt_123',
        ],
    ]);

    $response->assertStatus(200);

    $saved = session('gcal_saved_params');
    expect($saved['events_get']['path']['calendarId'])->toBe('my-work-calendar');
    expect($saved['events_get']['path']['eventId'])->toBe('custom_evt_123');
});

test('it renders reset symbols beside parameter names', function () {
    $response = $this->get('/?request=events_get');

    $response->assertStatus(200);
    $response->assertSee('reset-btn-path-calendarId');
    $response->assertSee('reset-btn-path-eventId');
    $response->assertSee('Click ↺ to reset');
});

test('it renders timezone dropdown and reset symbol for timezone-affected queries', function () {
    $response = $this->get('/?request=events_list');

    $response->assertStatus(200);
    $response->assertSee('id="timezone-param-section"', false);
    $response->assertSee('id="timezone-select"', false);
    $response->assertSee('id="reset-btn-timezone"', false);
    $response->assertSee('value="Asia/Singapore"', false);
    $response->assertSee('value="UTC"', false);
});

test('it hides timezone section for queries not affected by timezone', function () {
    $response = $this->get('/?request=colors_get');

    $response->assertStatus(200);
    $response->assertSee('id="timezone-param-section" class="hidden"', false);
});

test('it saves user chosen timezone in session via api', function () {
    $response = $this->postJson('/api/params/save', [
        'request_id' => 'events_list',
        'param_type' => 'timezone',
        'param_value' => 'Asia/Singapore',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'saved_params' => [
            'timezone' => 'Asia/Singapore',
        ],
    ]);

    $saved = session('gcal_saved_params');
    expect($saved['events_list']['timezone'])->toBe('Asia/Singapore');
    expect($saved['events_list']['query']['timeZone'])->toBe('Asia/Singapore');
});

test('it resets user timezone parameter back to UTC default via api', function () {
    session([
        'gcal_saved_params' => [
            'events_list' => [
                'timezone' => 'Asia/Tokyo',
                'query' => [
                    'timeZone' => 'Asia/Tokyo',
                ],
            ],
        ],
    ]);

    $response = $this->postJson('/api/params/reset', [
        'request_id' => 'events_list',
        'param_type' => 'timezone',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'default_value' => 'UTC',
    ]);

    $saved = session('gcal_saved_params');
    expect($saved['events_list'])->not->toHaveKey('timezone');
    expect($saved['events_list']['query'])->not->toHaveKey('timeZone');
});

test('it restores saved timezone parameter on page load', function () {
    session([
        'gcal_saved_params' => [
            'freebusy_query' => [
                'timezone' => 'Asia/Singapore',
            ],
        ],
    ]);

    $response = $this->get('/?request=freebusy_query');

    $response->assertStatus(200);
    $response->assertSee('value="Asia/Singapore" selected', false);
    $response->assertSee('reset-btn-timezone');
});

test('it executes live quickAdd without sending empty json body array', function () {
    Http::fake([
        'https://www.googleapis.com/*' => Http::response([
            'kind' => 'calendar#event',
            'id' => 'quick_evt_test_123',
            'status' => 'confirmed',
            'summary' => 'Strategy Meeting with Sarah next Monday at 10am to 11am',
        ], 200),
    ]);

    session([
        'google_access_token' => 'mock_token',
        'explorer_mode' => 'live',
    ]);

    $response = $this->postJson('/api/execute', [
        'request_id' => 'events_quickAdd',
        'mode' => 'live',
        'query_params' => [
            'text' => 'Strategy Meeting with Sarah next Monday at 10am to 11am',
            'sendUpdates' => 'none',
        ],
        'body' => null,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'status' => 200,
        'mode_used' => 'live',
    ]);

    Http::assertSent(function ($request) {
        // Assert that the body is NOT '[]' and Content-Length is 0
        return $request->body() === '' || $request->body() === null || $request->body() === false;
    });
});
