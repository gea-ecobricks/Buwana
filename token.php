<?php
ob_start(); // 🚿 Buffer any accidental output early

session_start();
require_once 'vendor/autoload.php';
require_once 'buwanaconn_env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$authLogFile = dirname(__DIR__) . '/logs/auth.log';

function auth_log($message) {
    global $authLogFile;
    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . "] TOKEN: " . $message . PHP_EOL, 3, $authLogFile);
}

auth_log("Token request received");

// 🌐 CORS: Allow trusted origins
$allowedOrigins = [
    "https://earthcal.app",
    "https://gobrik.com",
    "https://ecobricks.org",
    "https://learning.ecobricks.org",
    "https://openbooks.ecobricks.org"
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

// 📥 Gather POST input
$grant_type = $_POST['grant_type'] ?? '';
$code = $_POST['code'] ?? '';
$redirect_uri = $_POST['redirect_uri'] ?? '';
$client_id = $_POST['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? '';
$code_verifier = $_POST['code_verifier'] ?? '';

if ($grant_type !== 'authorization_code' || !$code || !$redirect_uri || !$client_id) {
    http_response_code(400);
    echo json_encode(["error" => "invalid_request"]);
    exit;
}

// 🔍 Lookup client
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

// 🔑 Validate authorization code
$stmt = $buwana_conn->prepare("SELECT user_id, redirect_uri, scope, nonce, code_challenge, code_challenge_method FROM authorization_codes_tb WHERE code = ? AND client_id = ?");
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

if ($redirect_uri !== $stored_redirect_uri) {
    http_response_code(400);
    echo json_encode(["error" => "redirect_uri_mismatch"]);
    exit;
}

// 🔄 Auth flow logic
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

// 🧹 Clean up used code
$stmt = $buwana_conn->prepare("DELETE FROM authorization_codes_tb WHERE code = ?");
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->close();

// 👤 Fetch user info
$stmt_user = $buwana_conn->prepare("SELECT email, first_name, open_id, earthling_emoji, continent_code, community_id FROM users_tb WHERE buwana_id = ?");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$stmt_user->bind_result($email, $first_name, $open_id, $earthling_emoji, $continent_code, $community_id);
$stmt_user->fetch();
$stmt_user->close();

// 📅 Prepare token claims
$now = time();
$exp = $now + 3600;
$sub = $open_id ?? ("buwana_$user_id");

$id_token_payload = [
    "iss" => "https://buwana.ecobricks.org",
    "sub" => $sub,
    "aud" => $client_id,
    "exp" => $exp,
    "iat" => $now,
    "email" => $email,
    "given_name" => $first_name,
    "nonce" => $nonce,
    "scope" => $scope,
    "buwana_id" => $user_id,
    "buwana:earthlingEmoji" => $earthling_emoji,
    "buwana:community" => "Planet Earth",
    "buwana:location.continent" => $continent_code,
];

try {
    $id_token = JWT::encode($id_token_payload, $jwt_private_key, 'RS256', $client_id);
} catch (Exception $e) {
    auth_log("ID token generation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "jwt_generation_failed"]);
    exit;
}

$access_token_payload = [
    "iss" => "https://buwana.ecobricks.org",
    "sub" => $sub,
    "scope" => $scope,
    "aud" => $client_id,
    "exp" => $exp,
    "iat" => $now,
    "buwana_id" => $user_id
];

try {
    $access_token = JWT::encode($access_token_payload, $jwt_private_key, 'RS256', $client_id);
} catch (Exception $e) {
    auth_log("Access token generation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "access_token_generation_failed"]);
    exit;
}

// 🧽 Clean output & respond
ob_clean();
header('Content-Type: application/json');

$response = [
    "access_token" => $access_token,
    "id_token" => $id_token,
    "token_type" => "Bearer",
    "expires_in" => 3600
];

auth_log("Returning tokens for user_id: $user_id");
auth_log(json_encode($response, JSON_PRETTY_PRINT));

echo json_encode($response);
exit;
