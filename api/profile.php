<?php
/**
 * GET /api/profile.php  —  read the authenticated user's Buwana profile.
 *
 * Auth: Bearer access token + scope buwana:profile.read + registered connection.
 * Returns the user's editable profile (country/continent are read-only, derived
 * from location) plus the reference data a client form needs (languages, time
 * zones). Buwana is the source of truth — clients keep no local copy.
 */

// Load the JSON + CORS helpers and start output buffering + send CORS headers
// BEFORE any heavier include can emit output. Otherwise a stray notice or trailing
// whitespace (display_errors is on) would flush headers early and the CORS header
// would be lost (browser sees "Access-Control-Allow-Origin missing").
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/cors.php';

init_json_api();
send_api_cors_headers('GET, OPTIONS');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../buwanaconn_env.php';            // $buwana_conn
require_once __DIR__ . '/../includes/security-headers.php';
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/profile_service.php';
require_once __DIR__ . '/../includes/profile_reference.php';

send_security_headers(true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('method_not_allowed', 405);
}

$auth = authenticate_api_request($buwana_conn, 'buwana:profile.read');

$profile = get_user_profile($buwana_conn, $auth['buwana_id']);
if ($profile === null) {
    api_error('user_not_found', 404);
}

api_json([
    'status'    => 'succeeded',
    'profile'   => $profile,
    'reference' => [
        'languages' => fetch_active_languages($buwana_conn),
        'timezones' => profile_timezones(),
    ],
]);
