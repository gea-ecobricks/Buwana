<?php
/**
 * POST /api/profile_update.php  —  update the authenticated user's profile.
 *
 * Auth: Bearer access token + scope buwana:profile.write + registered connection.
 * Body: JSON or form-encoded with the editable fields (first_name, last_name,
 * language_id, birth_date, community_id, location_full, latitude, longitude,
 * location_watershed, earthling_emoji, time_zone). country_id + continent_code
 * are NOT accepted — they're derived server-side from location_full.
 *
 * Validation + write + geo-derivation are the SAME shared logic the Buwana-hosted
 * page uses (includes/profile_service.php). On success the fresh profile is
 * returned so the client can update its display without a second request.
 */

// Load the JSON + CORS helpers and start output buffering + send CORS headers
// BEFORE any heavier include can emit output. Otherwise a stray notice or trailing
// whitespace (display_errors is on) would flush headers early and the CORS header
// would be lost (browser sees "Access-Control-Allow-Origin missing").
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/cors.php';

init_json_api();
send_api_cors_headers('POST, OPTIONS');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../buwanaconn_env.php';            // $buwana_conn
require_once __DIR__ . '/../includes/security-headers.php';
require_once __DIR__ . '/../includes/api_auth.php';
require_once __DIR__ . '/../includes/profile_service.php';

send_security_headers(true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('method_not_allowed', 405);
}

$auth = authenticate_api_request($buwana_conn, 'buwana:profile.write');

$input = parse_request_body();

$result = update_user_profile($buwana_conn, $auth['buwana_id'], $input);

if (($result['status'] ?? '') !== 'succeeded') {
    // Validation / derivation failure (e.g. unresolved location, bad community).
    api_json($result, 422);
}

// Echo the fresh profile (incl. the just-derived country/continent) so the
// client can refresh its view in one round-trip.
$result['profile'] = get_user_profile($buwana_conn, $auth['buwana_id']);
api_json($result);
