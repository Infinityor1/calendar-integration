# Google Calendar API Explorer & Workbench

An interactive developer workbench and learning laboratory built in Laravel 13 to explore, test, and understand the **Google Calendar API (v3)**.

This application provides a side-by-side **Request & Response Workbench** that demonstrates what each API call does, how Google structures its responses, and the exact actions required to enable calendar integration in production applications.

---

## Industry Documentation & Specifications Referenced

This application is built in strict accordance with official industry specifications and Google Workspace engineering guides:

* **Google Calendar API v3 Reference**: [Google for Developers Calendar Reference](https://developers.google.com/workspace/calendar/api/v3/reference) — Official resource hierarchy (`CalendarList`, `Calendars`, `Events`, `Freebusy`, `Colors`, `Settings`, `Channels`).
* **OAuth 2.0 for Web Server Applications**: [Google Identity Documentation](https://developers.google.com/identity/protocols/oauth2/web-server) — Authorization Code Grant with offline access and token rotation.
* **RFC 6749**: [The OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749) — Standard authorization flows, scope negotiation, and bearer tokens.
* **RFC 3339**: [Date and Time on the Internet: Timestamps](https://datatracker.ietf.org/doc/html/rfc3339) — ISO-8601 profile utilized by Google Calendar for `timeMin`, `timeMax`, and `start.dateTime`.
* **Google Meet Conferencing Guide**: [Creating Video Conferences](https://developers.google.com/workspace/calendar/api/v3/reference/events/insert#conferenceDataVersion) — Requirements for `conferenceDataVersion=1` and `conferenceSolutionKey`.

---

## Key Features

1. **Dual Execution Modes**:
   * **Simulated Mock Mode**: Inspect request structures, test parameters, and receive realistic simulated Google Calendar responses instantly without needing Google Cloud credentials.
   * **Live Google API Mode**: Execute live HTTP calls against real Google accounts using OAuth 2.0 bearer access tokens.
2. **Interactive Two-Pane Workbench**:
   * **Request Inspector (Left)**: Live target endpoint, required OAuth scopes, Google Cloud prerequisites, editable path & query parameters, and a formatted JSON request payload editor.
   * **Response Inspector (Right)**: Real HTTP status codes, latency telemetry in milliseconds, formatted raw JSON with 1-click copy, interactive visual preview cards (including Google Meet launch buttons), and HTTP response headers.
   * **"What This Means for Your App" Guide**: Explains returned properties (`id`, `hangoutLink`, `syncToken`, `htmlLink`), local database storage recommendations, and error-handling routines (e.g. `401 Unauthorized`, `403 Rate Limit`).
3. **Dedicated Settings & Credentials Tab**:
   * One-click OAuth 2.0 connection with customizable scope selection.
   * Auto-detected, copyable Authorized Redirect URI.
   * Direct token bypass to test bearer tokens from Google OAuth 2.0 Playground immediately.
   * Live connection status monitor with token expiration tracking and auto-refresh.

---

## Codebase Architecture & Naming Conventions

### 1. General Naming Conventions
* **Service Classes (`app/Services/`)**:
  * Named using PascalCase with descriptive domain suffixes (e.g., `GoogleCalendarService`).
  * API Constants are in `SCREAMING_SNAKE_CASE` (`BASE_URL`, `OAUTH_AUTH_URL`, `SCOPE_*`) directly mirroring Google Cloud and RFC specifications.
  * Methods use expressive camelCase verbs (`getAuthUrl`, `exchangeCode`, `refreshToken`, `executeLive`, `getCatalog`).
  * Catalog request identifiers use snake_case formatted as `{resource}_{action}` (e.g., `calendarList_list`, `events_insert_meet`, `freebusy_query`).
* **Controllers (`app/Http/Controllers/`)**:
  * Named using PascalCase (e.g., `CalendarExplorerController`).
  * Methods follow standard Laravel HTTP action conventions (`index`, `saveSettings`, `oauthRedirect`, `oauthCallback`, `oauthDisconnect`, `setMode`, `executeRequest`).
  * Session credentials are namespaced under the `google_*` prefix (e.g. `google_client_id`, `google_access_token`, `google_token_expires_at`).
* **Routes (`routes/web.php`)**:
  * Named routes use dot notation under the `explorer.*` namespace (`explorer.index`, `explorer.settings.save`, `explorer.oauth.*`, `explorer.execute`).
* **Frontend Components (`resources/views/explorer.blade.php`)**:
  * DOM element IDs use kebab-case with role prefixes (`tab-btn-*`, `view-*`, `current-*`, `resp-*`, `insights-*`).
  * JavaScript functions use camelCase action verbs (`switchMainTab`, `selectRequest`, `executeCurrentRequest`, `setExecutionMode`, `renderResponse`).

### 2. Core Code Components & Functions

| File | Type | Function Description |
| :--- | :--- | :--- |
| [`app/Services/GoogleCalendarService.php`](app/Services/GoogleCalendarService.php) | Service Class | Handles Google Calendar v3 API communications, OAuth consent URL generation, code/token exchanges, token auto-refresh, and supplies the pre-configured catalog of endpoints and mock datasets. |
| [`app/Http/Controllers/CalendarExplorerController.php`](app/Http/Controllers/CalendarExplorerController.php) | Controller | Coordinates web requests, manages session credentials and token states, processes OAuth callbacks, and dispatches API execution requests. |
| [`routes/web.php`](routes/web.php) | Route Registry | Registers application routes for the explorer workbench, settings persistence, OAuth redirection/callback, and API execution. |
| [`resources/views/explorer.blade.php`](resources/views/explorer.blade.php) | Blade View | Single-page developer workbench with Tailwind CSS containing the API Explorer tabs, JSON editors, visual card previewers, and settings forms. |
| [`tests/Feature/CalendarExplorerTest.php`](tests/Feature/CalendarExplorerTest.php) | Pest Test Suite | Automated feature tests verifying interface rendering, credential persistence, mode toggling, mock execution, and token purging. |

---

## Supported Google Calendar API Endpoints

The catalog covers essential methods across Google's 8 core resources:

| Resource | Method | Verb | Endpoint | Purpose & Features |
| :--- | :--- | :--- | :--- | :--- |
| **`CalendarList`** | `calendarList.list` | `GET` | `/users/me/calendarList` | Discovers user calendars and locates the `primary` calendar. |
| **`Events`** | `events.list` | `GET` | `/calendars/{calendarId}/events` | Agenda query supporting `timeMin`, `timeMax`, and `singleEvents=true`. |
| **`Events`** | `events.get` | `GET` | `/calendars/{calendarId}/events/{eventId}` | Fetches full event metadata, attendees, and RSVP statuses. |
| **`Events`** | `events.insert` | `POST` | `/calendars/{calendarId}/events` | Creates events with Google Meet links (`conferenceDataVersion=1`). |
| **`Events`** | `events.quickAdd` | `POST` | `/calendars/{calendarId}/events/quickAdd` | Natural language event parser (e.g., *"Team sync tomorrow 3pm"*). |
| **`Events`** | `events.patch` | `PATCH` | `/calendars/{calendarId}/events/{eventId}` | Updates and reschedules specific fields without overwriting. |
| **`Events`** | `events.delete` | `DELETE` | `/calendars/{calendarId}/events/{eventId}` | Cancels/deletes an appointment and alerts invitees. |
| **`Freebusy`** | `freebusy.query` | `POST` | `/freeBusy` | Privacy-safe busy intervals check for booking workflows. |
| **`Colors`** | `colors.get` | `GET` | `/colors` | Color palette maps for calendar styling and event labels. |
| **`Settings`** | `settings.list` | `GET` | `/users/me/settings` | User account preferences (primary timezone, 24h format). |

---

## Google Cloud Console Setup Guide

To execute requests in **Live Google API Mode**, follow these steps:

1. **Create or Select a Cloud Project**:
   * Navigate to the [Google Cloud Console](https://console.cloud.google.com).
2. **Enable the Google Calendar API**:
   * Go to **APIs & Services > Library**, search for **Google Calendar API**, and click **Enable**.
3. **Configure OAuth Consent Screen**:
   * Go to **APIs & Services > OAuth Consent Screen**.
   * Choose **External** User Type (in Testing status).
   * Fill in the mandatory app details.
   * Add your Google email address to **Test Users**.
4. **Create Web Application Credentials**:
   * Go to **APIs & Services > Credentials > Create Credentials > OAuth Client ID**.
   * Select **Web application**.
   * Add the Redirect URI under **Authorized redirect URIs**:
     * **Herd with Public Proxy (Recommended)**: `https://fwd.host/http://calendar-integration.test/oauth/google/callback`
       > **Note on Non-TLD Domains**: Google rejects non-top-level domains such as `.test` or `.local`. Using `https://fwd.host/<target_url>` proxies Google's callback redirect directly back to your local Herd site while satisfying Google's public TLD validation.
     * **Local Artisan Server**: `http://localhost:8000/oauth/google/callback`
5. **Connect in the App**:
   * Open the app, navigate to the **Settings & Credentials** tab, verify or paste your **Client ID**, **Client Secret**, and the Redirect URI above, then click **Connect with Google Calendar**.

---

## Running the Application Locally

### Requirements
* PHP 8.3 or higher (PHP 8.5 recommended) with `pdo_sqlite` and `curl` extensions enabled.
* Composer
* Node.js & NPM

### Setup & Migrations
```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Run database migrations
php artisan migrate
```

### Starting the Server
* **Via Laravel Herd**: Automatically accessible at `http://calendar-integration.test`.
* **Via Artisan Serve**:
```bash
php artisan serve
```
Open [http://localhost:8000](http://localhost:8000) in your web browser.

---

## Running Automated Tests

The application utilizes [Pest PHP](https://pestphp.com) for test coverage:

```bash
# Run the test suite
php artisan test --compact

# Run with direct Pest binary
vendor/bin/pest
```

---

## Code Formatting

The codebase follows Laravel Pint conventions:

```bash
vendor/bin/pint --format agent
```

---

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
