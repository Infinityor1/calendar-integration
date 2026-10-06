<?php

/**
 * Web Routes Configuration
 *
 * 1) General Naming Conventions:
 * - Route Names: Grouped under the `explorer.*` dot-notation namespace (e.g. `explorer.index`, `explorer.oauth.callback`),
 *   adhering to Laravel's recommended named-route conventions for maintainable URL generation.
 * - URI Endpoints: Semantic lowercase paths categorizing functionality:
 *   - `/` : Root dashboard interface.
 *   - `/settings` : Credential configuration and token management.
 *   - `/oauth/google/*` : OAuth 2.0 redirect and callback endpoints conforming to RFC 6749.
 *   - `/api/*` : Asynchronous workbench execution endpoints.
 *
 * 2) Function of the Code:
 * Defines the HTTP endpoints for the application and maps each URI to its corresponding action
 * on `CalendarExplorerController`.
 */

use App\Http\Controllers\CalendarExplorerController;
use Illuminate\Support\Facades\Route;

// Main explorer interface & settings dashboard view
Route::get('/', [CalendarExplorerController::class, 'index'])->name('explorer.index');

// Credential persistence & direct token configuration
Route::post('/settings', [CalendarExplorerController::class, 'saveSettings'])->name('explorer.settings.save');

// Google OAuth 2.0 authorization redirect (initiates consent screen)
Route::get('/oauth/google/redirect', [CalendarExplorerController::class, 'oauthRedirect'])->name('explorer.oauth.redirect');

// Google OAuth 2.0 authorization code callback (handles exchange for access/refresh tokens)
Route::get('/oauth/google/callback', [CalendarExplorerController::class, 'oauthCallback'])->name('explorer.oauth.callback');

// Session logout and token purge
Route::post('/oauth/google/disconnect', [CalendarExplorerController::class, 'oauthDisconnect'])->name('explorer.oauth.disconnect');

// Mode toggle endpoint (switches between simulated mock and live Google API execution)
Route::post('/api/mode', [CalendarExplorerController::class, 'setMode'])->name('explorer.mode');

// Asynchronous execution endpoint for dispatching Google Calendar API requests
Route::post('/api/execute', [CalendarExplorerController::class, 'executeRequest'])->name('explorer.execute');

// User parameter persistence & reset endpoints
Route::post('/api/params/save', [CalendarExplorerController::class, 'saveParam'])->name('explorer.params.save');
Route::post('/api/params/reset', [CalendarExplorerController::class, 'resetParam'])->name('explorer.params.reset');
