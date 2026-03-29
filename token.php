<?php
ob_start();

/**
 * ================================
 * SECTION 1: INITIALIZATION & LOGGING SETUP
 * This section starts the session, loads dependencies, and defines a helper
 * for writing all authentication-related logs to /auth_log.
 * ================================
 */

session_start();
require_once 'vendor/autoload.php';
require_once 'buwanaconn_env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$authLogFile = __DIR__ . '/auth_log';

function auth_log($message) {
    global $authLogFile;
    $log_message = '[' . date('Y-m-d H:i:s') . "] TOKEN: " . $message;

    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }

    // Write ONLY to the dedicated auth log file
    error_log($log_message . PHP_EOL, 3, $authLogFile);
}

/**
 * Normalize redirect URIs so that query parameter order does not affect matching.
 */
function normalize_redirect_uri($uri) {
    $parts = parse_url($uri);
    if (!$parts) {
        return $uri;
    }
    $scheme = $parts['scheme'] ?? '';
    $host   = $parts['host'] ?? '';
    $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path   = $parts['path'] ?? '';
    $normalized = $scheme . '://' . $host . $port . $path;
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
        ksort($query);
        $normalized .= '?' . http_build_query($query);
    }
    return $normalized;
}

auth_log("Token request received");

/**
 * ================================
 * SECTION 2: CORS & HTTP METHOD VALIDATION
 * This section allows trusted origins and ensures only POST requests are accepted.
 * We also log the incoming Origin so we can debug Snap / localhost flows.
 * ================================
 */

// Allowed front-end origins that may call /token
$allowedOrigins = [
    "https://earthcal.app",
    "https://gobrik.com",
    "https://ecobricks.org",
    "https://learning.ecobricks.org",
    "https://openbooks.ecobricks.org",
    "https://air2.earthen.io",
    "https://air.earthen.io",
    "https://hopeturtles.org",
    "https://files.mandala.team",
    // EarthCal desktop / local dev:
    "http://127.0.0.1:3000",
    "http://localhost:3000",
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
auth_log("Incoming Origin header: " . ($origin ?: 'NONE'));

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    auth_log("CORS: Origin allowed: {$origin}");
} else {
    // Not fatal for now, but useful to know if we’re missing a value
    auth_log("CORS: Origin NOT allowed: " . ($origin ?: 'NONE'));
}

// Always send these CORS-related headers
header("Vary: Origin");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Handle preflight OPTIONS cleanly and exit early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    auth_log("CORS preflight (OPTIONS) handled");
    http_response_code(204);
    exit;
}

// After CORS handling, enforce POST for actual token requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_log("Rejected non-POST method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(["error" => "method_not_allowed"]);
    exit;
}

/**
 * ================================
 * SECTION 3: INPUT GATHERING & NORMALIZATION
 * This section collects POST parameters and normalizes the redirect URI.
 * ================================
 */

$grant_type    = $_POST['grant_type']    ?? '';
$code          = $_POST['code']          ?? '';
$redirect_uri  = $_POST['redirect_uri']  ?? '';
$client_id     = $_POST['client_id']     ?? '';
$client_secret = $_POST['client_secret'] ?? '';
$code_verifier = $_POST['code_verifier'] ?? '';

$redirect_uri = normalize_redirect_uri($redirect_uri);

if ($grant_type !== 'authorization_code' || !$code || !$redirect_uri || !$client_id) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_request"]);
    exit;
}

/**
 * ================================
 * SECTION 4: CLIENT LOOKUP
 * This section validates the client_id and loads its secret, key, and name.
 * ================================
 */

$stmt = $buwana_conn->prepare(
    "SELECT client_secret, jwt_private_key, app_name, redirect_uris
     FROM apps_tb
     WHERE client_id = ?"
);
$stmt->bind_param('s', $client_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(["error" => "invalid_client"]);
    exit;
}
$stmt->bind_result($expected_secret, $jwt_private_key, $app_name, $registered_redirect_uris_str);
$stmt->fetch();
$stmt->close();

$app_name = $app_name ?: $client_id; // fallback if app_name is null/empty

// --- Validate redirect_uri against registered whitelist (defense-in-depth)
if (!empty(trim($registered_redirect_uris_str))) {
    $registered_uris = array_map('trim', explode(',', $registered_redirect_uris_str));
    $normalized_registered = array_map('normalize_redirect_uri', $registered_uris);
    if (!in_array($redirect_uri, $normalized_registered, true)) {
        auth_log("Rejecting token request: redirect_uri not in registered list for client_id=$client_id. Supplied: $redirect_uri");
        http_response_code(400);
        echo json_encode(["error" => "invalid_redirect_uri"]);
        exit;
    }
} else {
    auth_log("WARNING: client_id=$client_id has no registered redirect_uris — whitelist check skipped (backward compat)");
}

/**
 * ================================
 * SECTION 5: AUTHORIZATION CODE VALIDATION
 * This section validates the authorization code and ensures redirect URIs match.
 * ================================
 */

$stmt = $buwana_conn->prepare(
    "SELECT user_id, redirect_uri, scope, nonce, code_challenge, code_challenge_method
     FROM authorization_codes_tb
     WHERE code = ? AND client_id = ?"
);
$stmt->bind_param('ss', $code, $client_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows !== 1) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_code"]);
    exit;
}
$stmt->bind_result($user_id, $stored_redirect_uri, $scope, $nonce, $code_challenge, $code_challenge_method);
$stmt->fetch();
$stmt->close();

$stored_redirect_uri = normalize_redirect_uri($stored_redirect_uri);

if ($redirect_uri !== $stored_redirect_uri) {
    http_response_code(400);
    echo json_encode(["error" => "redirect_uri_mismatch"]);
    exit;
}

// Log which app is asking for which scopes
auth_log("$app_name ($client_id) is requesting the scopes: $scope");

/**
 * ================================
 * SECTION 6: AUTH FLOW & PKCE HANDLING
 * This section distinguishes between confidential clients and PKCE public clients.
 * ================================
 */

if (!empty($client_secret)) {
    auth_log("Confidential client flow for $client_id");
    if (empty($expected_secret) || $client_secret !== $expected_secret) {
        http_response_code(401);
        echo json_encode(["error" => "invalid_client_secret"]);
        exit;
    }
} else {
    auth_log("PKCE flow for $client_id");
    if (empty($code_challenge) || empty($code_verifier)) {
        http_response_code(400);
        echo json_encode(["error" => "missing_code_verifier_or_challenge"]);
        exit;
    }
    $calculated_challenge = ($code_challenge_method === 'S256')
        ? rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=')
        : $code_verifier;

    if ($calculated_challenge !== $code_challenge) {
        http_response_code(401);
        echo json_encode(["error" => "invalid_code_verifier"]);
        exit;
    }
}

/**
 * ================================
 * SECTION 7: AUTH CODE CLEANUP
 * This section invalidates the used authorization code so it cannot be replayed.
 * ================================
 */

$stmt = $buwana_conn->prepare("DELETE FROM authorization_codes_tb WHERE code = ?");
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->close();

/**
 * ================================
 * SECTION 8: USER PROFILE LOOKUP & FALLBACKS
 * This section loads the user profile, applies sensible defaults, and
 * prepares values used later in the token claims.
 * ================================
 */

$stmt_user = $buwana_conn->prepare(
    "SELECT u.email, u.first_name, u.last_name, u.open_id,
            u.earthling_emoji, u.continent_code, u.community_id,
            u.location_full, u.time_zone,
            u.created_at, u.role, u.gea_status, u.profile_pic,
            u.language_id, u.birth_date, u.brikcoin_balance,
            u.connected_app_ids, u.watershed_id,
            u.location_watershed, u.location_lat, u.location_long,
            c.country_name,
            l.language_name_en   AS language_name,
            ct.continent_name_en AS continent_name,
            cm.com_name          AS community_name,
            w.watershed_name_en  AS watershed_name
     FROM users_tb u
     LEFT JOIN countries_tb   c  ON u.country_id     = c.country_id
     LEFT JOIN languages_tb   l  ON u.language_id    = l.language_id
     LEFT JOIN continents_tb  ct ON u.continent_code = ct.continent_code
     LEFT JOIN communities_tb cm ON u.community_id   = cm.community_id
     LEFT JOIN watersheds_tb  w  ON u.watershed_id   = w.watershed_id
     WHERE u.buwana_id = ?"
);
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$stmt_user->bind_result(
    $email, $first_name, $last_name, $open_id,
    $earthling_emoji, $continent_code, $community_id,
    $location_full, $time_zone,
    $created_at, $role, $gea_status, $profile_pic,
    $language_id, $birth_date, $brikcoin_balance,
    $connected_app_ids, $watershed_id,
    $location_watershed, $location_lat, $location_long,
    $country_name, $language_name, $continent_name,
    $community_name, $watershed_name
);
$stmt_user->fetch();
$stmt_user->close();

$is_learning_app = ($client_id === 'lear_a30d677a7b08');

$resolved_given_name  = trim($first_name ?? '');
$resolved_family_name = trim($last_name ?? '');
$resolved_timezone    = trim($time_zone ?? '');
$resolved_emoji       = trim($earthling_emoji ?? '') ?: '🌏';

auth_log(
    "User lookup results for user_id=$user_id: " . json_encode([
        'email'          => $email,
        'first_name'     => $first_name,
        'last_name'      => $last_name,
        'earthling_emoji'=> $earthling_emoji,
        'continent_code' => $continent_code,
        'community_id'   => $community_id,
        'location_full'  => $location_full,
        'time_zone'      => $time_zone,
        'country_name'   => $country_name,
        'language_name'  => $language_name,
        'continent_name' => $continent_name,
        'community_name' => $community_name,
        'watershed_name' => $watershed_name,
    ])
);

/**
 * ================================
 * SECTION 9: TOKEN CLAIM PREPARATION
 * Claims are gated by the scopes requested. Only fields that are non-empty
 * are included — empty/null values are omitted from the token entirely.
 *
 * Scope tiers:
 *   openid          — always: iss, sub, aud, exp, iat, nonce, scope
 *   buwana:basic    — identity fingerprint: buwana_id, email, given_name, emoji
 *   buwana:profile  — extended personal data
 *   buwana:community — resolved community name
 *   buwana:bioregion — geographic/watershed data
 * ================================
 */

$now = time();
$exp = $now + 5400;
$sub = $open_id ?? ("buwana_$user_id");

// Parse requested scopes (handle space- or comma-separated storage)
$requested_scopes = preg_split('/[\s,]+/', trim($scope ?? ''), -1, PREG_SPLIT_NO_EMPTY);
$has_basic     = in_array('buwana:basic',     $requested_scopes, true);
$has_profile   = in_array('buwana:profile',   $requested_scopes, true);
$has_community = in_array('buwana:community', $requested_scopes, true);
$has_bioregion = in_array('buwana:bioregion', $requested_scopes, true);

// Helper: only add a claim if the value is non-empty and not the string 'null'
function add_claim(array &$payload, string $key, $value): void {
    if ($value !== null && $value !== '' && $value !== 'null') {
        $payload[$key] = $value;
    }
}

// openid base claims — always present
$id_token_payload = [
    "iss"   => "https://buwana.ecobricks.org",
    "sub"   => $sub,
    "aud"   => $client_id,
    "exp"   => $exp,
    "iat"   => $now,
    "nonce" => $nonce,
    "scope" => $scope,
];

// buwana:basic — identity fingerprint
if ($has_basic) {
    $id_token_payload["buwana_id"] = $user_id;
    add_claim($id_token_payload, "email",               $email);
    add_claim($id_token_payload, "given_name",          $resolved_given_name);
    add_claim($id_token_payload, "buwana:earthlingEmoji", $resolved_emoji);
}

// buwana:profile — extended personal data
if ($has_profile) {
    // TODO: remove is_learning_app override once lear_ app updated to use buwana:profile
    $effective_family = $resolved_family_name;
    if ($is_learning_app && $effective_family === '') {
        $effective_family = $resolved_emoji;
    }
    add_claim($id_token_payload, "family_name",      $effective_family);
    add_claim($id_token_payload, "last_name",         $effective_family); // compat alias
    add_claim($id_token_payload, "created_at",        $created_at);
    add_claim($id_token_payload, "role",              $role);
    add_claim($id_token_payload, "gea_status",        $gea_status);
    add_claim($id_token_payload, "profile_pic",       $profile_pic);
    add_claim($id_token_payload, "language",          $language_name);
    add_claim($id_token_payload, "country",           $country_name);
    add_claim($id_token_payload, "birth_date",        $birth_date);
    add_claim($id_token_payload, "zoneinfo",          $resolved_timezone);
    add_claim($id_token_payload, "connected_app_ids", $connected_app_ids);
    if ($community_id)        $id_token_payload["community_id"]    = (int)$community_id;
    if ($brikcoin_balance !== null) $id_token_payload["brikcoin_balance"] = (float)$brikcoin_balance;
}

// buwana:community — resolved community name
if ($has_community) {
    add_claim($id_token_payload, "buwana:community", $community_name);
}

// buwana:bioregion — geographic and watershed data
if ($has_bioregion) {
    add_claim($id_token_payload, "continent",          $continent_name);
    add_claim($id_token_payload, "location_full",      $location_full);
    add_claim($id_token_payload, "location_watershed", $location_watershed);
    add_claim($id_token_payload, "watershed_name",     $watershed_name);
    if ($watershed_id)  $id_token_payload["watershed_id"]  = (int)$watershed_id;
    if ($location_lat)  $id_token_payload["location_lat"]  = (float)$location_lat;
    if ($location_long) $id_token_payload["location_long"] = (float)$location_long;
}

try {
    $id_token = JWT::encode($id_token_payload, $jwt_private_key, 'RS256', $client_id);
} catch (Exception $e) {
    auth_log("ID token generation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "jwt_generation_failed"]);
    exit;
}
auth_log("ID token payload: " . json_encode($id_token_payload));
auth_log("Generated ID token: $id_token");

$access_token_payload = [
    "iss"       => "https://buwana.ecobricks.org",
    "sub"       => $sub,
    "scope"     => $scope,
    "aud"       => $client_id,
    "exp"       => $exp,
    "iat"       => $now,
    "buwana_id" => $user_id,
];

try {
    $access_token = JWT::encode($access_token_payload, $jwt_private_key, 'RS256', $client_id);
} catch (Exception $e) {
    auth_log("Access token generation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "access_token_generation_failed"]);
    exit;
}
auth_log("Access token payload: " . json_encode($access_token_payload));
auth_log("Generated access token: $access_token");

/**
 * ================================
 * SECTION 10: RESPONSE CONSTRUCTION
 * This section clears any buffered output and returns the JSON token response.
 * ================================
 */

ob_clean();
header('Content-Type: application/json');

$response = [
    "access_token" => $access_token,
    "id_token"     => $id_token,
    "token_type"   => "Bearer",
    "expires_in"   => 5400,
];

auth_log("Returning tokens for user_id: $user_id");
auth_log(json_encode($response, JSON_PRETTY_PRINT));

echo json_encode($response);
exit;
