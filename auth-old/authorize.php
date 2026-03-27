<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'buwanaconn_env.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- Logging helper ---
$authLogFile = dirname(__DIR__) . 'logs/auth.log';
function auth_log($message) {
    global $authLogFile;
    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . "] TOKEN: " . $message . PHP_EOL, 3, $authLogFile);
}

// Normalize redirect URIs to avoid mismatches from parameter order
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

// --- CORS Headers for frontend PKCE clients (Earthcal) ---
$allowedOrigins = [
    "https://earthcal.app",
    "https://hopeturtles.org"

];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST");
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "method_not_allowed"]);
    exit;
}

// --- Read POST params ---
$grant_type     = $_POST['grant_type'] ?? '';
$code           = $_POST['code'] ?? '';
$redirect_uri   = $_POST['redirect_uri'] ?? '';
$client_id      = $_POST['client_id'] ?? '';
$client_secret  = $_POST['client_secret'] ?? '';
$code_verifier  = $_POST['code_verifier'] ?? '';

$redirect_uri = normalize_redirect_uri($redirect_uri);

if ($grant_type !== 'authorization_code' || !$code || !$redirect_uri || !$client_id) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_request"]);
    exit;
}

// --- Lookup app ---
$stmt = $buwana_conn->prepare("SELECT client_secret, jwt_private_key FROM apps_tb WHERE client_id = ?");
$stmt->bind_param('s', $client_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    http_response_code(401);
    echo json_encode(["error" => "invalid_client"]);
    exit;
}
$stmt->bind_result($expected_secret, $jwt_private_key);
$stmt->fetch();
$stmt->close();

// --- Lookup authorization code data from DB ---
$stmt = $buwana_conn->prepare("
    SELECT user_id, redirect_uri, scope, nonce, code_challenge, code_challenge_method
    FROM authorization_codes_tb
    WHERE code = ? AND client_id = ?
");
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

// --- Validate redirect_uri match (optional but best practice) ---
$stored_redirect_uri = normalize_redirect_uri($stored_redirect_uri);
if ($redirect_uri !== $stored_redirect_uri) {
    http_response_code(400);
    echo json_encode(["error" => "redirect_uri_mismatch"]);
    exit;
}

// --- Hybrid flow: Determine whether PKCE or confidential client ---
if (!empty($client_secret)) {
    // --- Confidential client flow ---
    auth_log("Confidential client flow for $client_id");

    if (empty($expected_secret)) {
        http_response_code(401);
        echo json_encode(["error" => "client_secret_not_allowed"]);
        exit;
    }

    if ($client_secret !== $expected_secret) {
        http_response_code(401);
        echo json_encode(["error" => "invalid_client_secret"]);
        exit;
    }

    // No PKCE verification needed

} else {
    // --- Public PKCE flow ---
    auth_log("PKCE flow for $client_id");

    if (empty($code_challenge)) {
        http_response_code(400);
        echo json_encode(["error" => "missing_pkce_challenge"]);
        exit;
    }

    if (empty($code_verifier)) {
        http_response_code(400);
        echo json_encode(["error" => "missing_code_verifier"]);
        exit;
    }

    // Recompute challenge
    $calculated_challenge = ($code_challenge_method === 'S256')
        ? rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=')
        : $code_verifier;

    if ($calculated_challenge !== $code_challenge) {
        http_response_code(401);
        echo json_encode(["error" => "invalid_code_verifier"]);
        exit;
    }
}

// --- Authorization code one-time use: Delete after successful use ---
$stmt = $buwana_conn->prepare("DELETE FROM authorization_codes_tb WHERE code = ?");
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->close();

// --- Fetch user info ---
$stmt_user = $buwana_conn->prepare("SELECT email, first_name, open_id FROM users_tb WHERE buwana_id = ?");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$stmt_user->bind_result($email, $first_name, $open_id);
$stmt_user->fetch();
$stmt_user->close();

// --- Issue tokens ---
$now = time();
$expire = $now + 5400;

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

// Access token (simplified)
$access_token_payload = [
    "iss" => "https://buwana.ecobricks.org",
    "sub" => $open_id ?? ("buwana_$user_id"),
    "scope" => $scope,
    "aud" => $client_id,
    "exp" => $expire,
    "iat" => $now
];

$access_token = JWT::encode($access_token_payload, $jwt_private_key, 'RS256', $client_id);

header('Content-Type: application/json');
echo json_encode([
    "access_token" => $access_token,
    "id_token" => $id_token,
    "token_type" => "Bearer",
    "expires_in" => 5400
]);
exit;
?>
