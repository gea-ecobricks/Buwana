<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../buwanaconn_env.php';
use Firebase\JWT\JWT;

// Logging helper
$authLogFile = dirname(__DIR__) . '/logs/auth.log';
function auth_log($message) {
    global $authLogFile;
    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . "] TOKEN: " . $message . PHP_EOL, 3, $authLogFile);
}

// Normalize redirect URIs so query parameter order doesn't cause mismatches
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

auth_log("Token endpoint called");

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "method_not_allowed"]);
    exit;
}

// Collect POST parameters
$grant_type = $_POST['grant_type'] ?? '';
$code = $_POST['code'] ?? '';
$redirect_uri = $_POST['redirect_uri'] ?? '';
$client_id = $_POST['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? '';

$redirect_uri = normalize_redirect_uri($redirect_uri);

// Basic parameter validation
if ($grant_type !== 'authorization_code' || !$code || !$redirect_uri || !$client_id || !$client_secret) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_request"]);
    exit;
}

auth_log("Received token request for client_id: $client_id");

// Validate client credentials and fetch registered data
$stmt = $buwana_conn->prepare("SELECT client_secret, jwt_private_key, redirect_uri, scopes FROM apps_tb WHERE client_id = ?");
$stmt->bind_param('s', $client_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(["error" => "invalid_client"]);
    exit;
}

$stmt->bind_result($expected_secret, $jwt_private_key, $registered_redirect_uri, $registered_scopes);
$stmt->fetch();
$stmt->close();

// Verify client_secret
if ($client_secret !== $expected_secret) {
    http_response_code(401);
    echo json_encode(["error" => "invalid_client_secret"]);
    exit;
}

// Verify redirect_uri matches what is registered
$registered_redirect_uri = normalize_redirect_uri($registered_redirect_uri);
if ($redirect_uri !== $registered_redirect_uri) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_redirect_uri"]);
    exit;
}

// Verify authorization code exists in session storage
if (!isset($_SESSION['auth_codes'][$code])) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_code"]);
    exit;
}

$auth_data = $_SESSION['auth_codes'][$code];
unset($_SESSION['auth_codes'][$code]); // Authorization code is one-time use

$user_id = $auth_data['user_id'];
$requested_scope = $auth_data['scope'];
$nonce = $auth_data['nonce'];

// Validate scopes: ensure requested scopes are within what this client is allowed to request
$allowed_scopes = array_map('trim', explode(',', $registered_scopes));
$requested_scopes = array_map('trim', explode(' ', $requested_scope));

foreach ($requested_scopes as $scope) {
    if (!in_array($scope, $allowed_scopes)) {
        http_response_code(400);
        echo json_encode(["error" => "invalid_scope", "details" => "$scope not allowed"]);
        exit;
    }
}

auth_log("Scopes validated for client_id: $client_id");

// Fetch user data to populate ID Token
$stmt_user = $buwana_conn->prepare("SELECT email, first_name, open_id FROM users_tb WHERE buwana_id = ?");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$stmt_user->bind_result($email, $first_name, $open_id);
$stmt_user->fetch();
$stmt_user->close();

$now = time();
$expire = $now + 5400; // 90 minute token lifetime

// Generate ID Token with standard claims
$id_token_payload = [
    "iss" => "https://buwana.ecobricks.org",
    "sub" => $open_id ?? ("buwana_$user_id"),
    "aud" => $client_id,
    "exp" => $expire,
    "iat" => $now,
    "email" => $email,
    "given_name" => $first_name,
    "nonce" => $nonce
];

$id_token = JWT::encode($id_token_payload, $jwt_private_key, 'RS256', $client_id);

// Generate Access Token with limited claims
$access_token_payload = [
    "iss" => "https://buwana.ecobricks.org",
    "sub" => $open_id ?? ("buwana_$user_id"),
    "scope" => $requested_scope,
    "aud" => $client_id,
    "exp" => $expire,
    "iat" => $now
];

$access_token = JWT::encode($access_token_payload, $jwt_private_key, 'RS256', $client_id);

// Return final token response to client
header('Content-Type: application/json');
echo json_encode([
    "access_token" => $access_token,
    "id_token" => $id_token,
    "token_type" => "Bearer",
    "expires_in" => 5400
]);
exit;
