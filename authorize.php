<?php
session_start();
require_once 'buwanaconn_env.php';

// 🌐 CORS: Allow trusted origins for browser clients (for future readiness or diagnostics)
$allowedOrigins = [
    "https://earthcal.app",
    "https://gobrik.com",
    "https://ecobricks.org",
    "https://learning.ecobricks.org",
    "https://openbooks.ecobricks.org"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
}


// 🔒 Log helper
function auth_log($msg) {
    error_log("[AUTHORIZE] $msg");
}

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// --- Capture OAuth query params
$client_id             = $_GET['client_id'] ?? null;
$response_type         = $_GET['response_type'] ?? null;
$redirect_uri          = $_GET['redirect_uri'] ?? null;
$scope                 = $_GET['scope'] ?? '';
$state                 = $_GET['state'] ?? null;
$nonce                 = $_GET['nonce'] ?? null;
$lang                  = $_GET['lang'] ?? 'en';
$code_challenge        = $_GET['code_challenge'] ?? null;
$code_challenge_method = $_GET['code_challenge_method'] ?? null;
$prompt                = $_GET['prompt'] ?? '';

// --- Basic parameter validation
if (!$client_id || !$response_type || !$redirect_uri || !$state || !$nonce) {
    auth_log("Rejecting request: missing required parameters");
    http_response_code(400);
    echo json_encode(['error' => 'missing_required_parameters']);
    exit;
}

if ($response_type !== 'code') {
    auth_log("Rejecting request: unsupported response_type = $response_type");
    http_response_code(400);
    echo json_encode(['error' => 'unsupported_response_type']);
    exit;
}

if (strpos($scope, 'openid') === false) {
    auth_log("Rejecting request: missing 'openid' scope");
    http_response_code(400);
    echo json_encode(['error' => 'missing_openid_scope']);
    exit;
}

// --- Validate client_id exists in DB
$stmt = $buwana_conn->prepare("SELECT client_id FROM apps_tb WHERE client_id = ?");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows !== 1) {
    auth_log("Rejecting request: invalid client_id = $client_id");
    http_response_code(400);
    echo json_encode(['error' => 'invalid_client_id']);
    exit;
}
$stmt->close();

// --- Handle prompt=none (silent check) ---
if ($prompt === 'none' && !isset($_SESSION['user_id'])) {
    auth_log("Silent login failed: user not logged in");
    http_response_code(403);
    echo json_encode(['error' => 'login_required']);
    exit;
}

// --- If user NOT logged in, store request and redirect to login.php
if (!isset($_SESSION['user_id'])) {
    auth_log("User not authenticated, storing pending request and redirecting to login");
    $_SESSION['pending_oauth_request'] = [
        'client_id' => $client_id,
        'response_type' => $response_type,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
        'state' => $state,
        'nonce' => $nonce,
        'lang' => $lang,
        'code_challenge' => $code_challenge,
        'code_challenge_method' => $code_challenge_method
    ];
    header("Location: /$lang/login.php");
    exit;
}

// --- User is logged in: issue authorization code
$user_id = $_SESSION['user_id'];
$auth_code = bin2hex(random_bytes(32));

$stmt = $buwana_conn->prepare("INSERT INTO authorization_codes_tb
    (code, user_id, client_id, redirect_uri, scope, nonce, code_challenge, code_challenge_method, issued_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param(
    "sissssss",
    $auth_code,
    $user_id,
    $client_id,
    $redirect_uri,
    $scope,
    $nonce,
    $code_challenge,
    $code_challenge_method
);
$stmt->execute();
$stmt->close();

auth_log("Issued auth_code for user_id=$user_id to client_id=$client_id");

// --- Redirect back to client with code
$redirect = $redirect_uri . '?' . http_build_query([
    'code' => $auth_code,
    'state' => $state
]);

header("Location: $redirect");
exit;
?>
