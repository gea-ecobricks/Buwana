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
 * ================================
 */

$allowedOrigins = [
    "https://earthcal.app",
    "https://gobrik.com",
    "https://ecobricks.org",
    "https://learning.ecobricks.org",
    "https://openbooks.ecobricks.org",
    "https://hopeturtles.org",
    "https://files.mandala.team",
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    "SELECT client_secret, jwt_private_key, app_name
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
$stmt->bind_result($expected_secret, $jwt_private_key, $app_name);
$stmt->fetch();
$stmt->close();

$app_name = $app_name ?: $client_id; // fallback if app_name is null/empty

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
            u.location_full, u.time_zone, c.country_name
     FROM users_tb u
     LEFT JOIN countries_tb c ON u.country_id = c.country_id
     WHERE u.buwana_id = ?"
);
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$stmt_user->bind_result(
    $email,
    $first_name,
    $last_name,
    $open_id,
    $earthling_emoji,
    $continent_code,
    $community_id,
    $location_full,
    $time_zone,
    $country_name
);
$stmt_user->fetch();
$stmt_user->close();

$is_learning_app = ($client_id === 'lear_a30d677a7b08');

$resolved_given_name   = trim($first_name ?? '');
$resolved_family_name  = trim($last_name ?? '');
$resolved_location     = trim($location_full ?? '');
$resolved_country      = trim($country_name ?? '');
$resolved_timezone     = trim($time_zone ?? '');
$resolved_emoji        = trim($earthling_emoji ?? '');

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
    ])
);

// Fallback values when profile data is missing
$default_location = 'Bantul, Jogja';
$default_country  = 'Indonesia';
$default_timezone = 'Jakarta UTC+7';
$default_emoji    = '🌏';

if ($resolved_location === '') {
    $resolved_location = $default_location;
}
if ($resolved_country === '') {
    $resolved_country = $default_country;
}
if ($resolved_timezone === '') {
    $resolved_timezone = $default_timezone;
}
if ($resolved_emoji === '') {
    $resolved_emoji = $default_emoji;
}

// Learning-app-specific exception: if no family_name, use the earthling emoji.
if ($is_learning_app && $resolved_family_name === '') {
    $resolved_family_name = $resolved_emoji;
}

/**
 * ================================
 * SECTION 9: TOKEN CLAIM PREPARATION
 * This section constructs the standard and custom claims for the ID and access tokens.
 * ================================
 */

$now = time();
$exp = $now + 5400;
$sub = $open_id ?? ("buwana_$user_id");

$id_token_payload = [
    "iss"  => "https://buwana.ecobricks.org",
    "sub"  => $sub,
    "aud"  => $client_id,
    "exp"  => $exp,
    "iat"  => $now,

    "email"       => $email,
    // Standard OIDC name claims
    "given_name"  => $resolved_given_name,
    "family_name" => $resolved_family_name,
    // Extra non-standard alias for compatibility
    "last_name"   => $resolved_family_name,

    "nonce"       => $nonce,
    "scope"       => $scope,

    "buwana_id"              => $user_id,
    "buwana:earthlingEmoji"  => $resolved_emoji,
    "buwana:community"       => "Planet Earth",
    "buwana:location.continent" => $continent_code,
];

// Pass location and country to ALL apps via both address object and simple claims
$id_token_payload["address"] = array_filter([
    "locality" => $resolved_location,
    "country"  => $resolved_country,
]);

$id_token_payload["zoneinfo"] = $resolved_timezone;

// Optional flat claims for convenience (e.g. mapping in clients)
$id_token_payload["city"]    = $resolved_location;
$id_token_payload["country"] = $resolved_country;

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
