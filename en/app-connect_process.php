<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php'; // Includes createUserInClientApp()
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

// Get POSTed form data
$buwana_id = isset($_POST['buwana_id']) ? (int) $_POST['buwana_id'] : null;
$client_id = $_POST['client_id'] ?? null;
$redirect = isset($_POST['redirect']) ? filter_var($_POST['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';

// Validate inputs
if (!$buwana_id || !$client_id) {
    die("❌ Missing Buwana ID or Client ID.");
}

// Get app info
$app_name = $app_info['app_name'] ?? 'default_app';
$app_dashboard_url = $app_info['app_dashboard_url'] ?? '/';

// 🌟 SPECIAL MOODLE HANDLING PLEASE
if ($client_id === 'lear_a30d677a7b08') {
    // ✅ Ensure the connection is recorded in Buwana before redirecting
    $check_sql = "SELECT 1 FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1";
    $check_stmt = $buwana_conn->prepare($check_sql);
    $check_stmt->bind_param('is', $buwana_id, $client_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows === 0) {
        $check_stmt->close();
        $status = 'registered';
        $connected_at = date('Y-m-d H:i:s');
        $insert_sql = "INSERT INTO user_app_connections_tb (buwana_id, client_id, status, connected_at) VALUES (?, ?, ?, ?)";
        $insert_stmt = $buwana_conn->prepare($insert_sql);
        $insert_stmt->bind_param('isss', $buwana_id, $client_id, $status, $connected_at);
        $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        $check_stmt->close();
    }

    // Redirect to Moodle's login page to initiate the proper OIDC flow
    $moodle_login_url = "https://learning.ecobricks.org/login/index.php";
    $redirect_param = !empty($redirect) ? '&redirect=' . urlencode($redirect) : '';
    header("Location: {$moodle_login_url}?auth=oidc{$redirect_param}");
    exit;
}


// --- STEP 5: Load client connection file ---
$client_env_path = "../config/{$app_name}_env.php";

if (!file_exists($client_env_path)) {
    error_log("❌ Client config file not found at: $client_env_path");
    die("❌ Missing DB config: $client_env_path");
}

require_once $client_env_path;
error_log("✅ Loaded client config: $client_env_path");

// --- Validate $client_conn existence and connection ---
if (!isset($client_conn) || !($client_conn instanceof mysqli) || $client_conn->connect_error) {
    error_log("❌ Client DB connection is not set or is invalid.");
    die("❌ Client DB connection could not be initialized.");
}

error_log("✅ Client DB connection ($app_name) established successfully.");

// 🧠 Fetch full user data from Buwana
$stmt = $buwana_conn->prepare("SELECT * FROM users_tb WHERE buwana_id = ?");
$stmt->bind_param('i', $buwana_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$userData = $result->fetch_assoc()) {
    die("❌ Buwana user not found.");
}
$stmt->close();

// ✅ Step 1: Try to create the user in the client app
$response = createUserInClientApp($buwana_id, $userData, $app_name, $client_conn, $buwana_conn, $client_id);

// ⚠️ Even if creation fails (e.g. duplicate), continue to connection logic
if (!$response['success'] && $response['error'] !== 'duplicate_user') {
    echo "<h2>⚠️ Failed to connect your account</h2>";
    echo "<p>Error: " . htmlspecialchars($response['error']) . "</p>";
    echo "<p><a href='javascript:history.back()'>Try again</a></p>";
    exit;
}

// ✅ Step 2: Check if the connection already exists
$check_sql = "SELECT 1 FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1";
$check_stmt = $buwana_conn->prepare($check_sql);
$check_stmt->bind_param('is', $buwana_id, $client_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    $check_stmt->close();

    // 🔗 Insert new app connection
    $status = 'registered';
    $connected_at = date('Y-m-d H:i:s');
    $insert_sql = "INSERT INTO user_app_connections_tb (buwana_id, client_id, status, connected_at) VALUES (?, ?, ?, ?)";
    $insert_stmt = $buwana_conn->prepare($insert_sql);
    $insert_stmt->bind_param('isss', $buwana_id, $client_id, $status, $connected_at);
    $insert_stmt->execute();
    $insert_stmt->close();
} else {
    $check_stmt->close();
}

// ✅ Step 3: Generate JWT and redirect to the app dashboard
$private_key = '';
$stmt_key = $buwana_conn->prepare("SELECT jwt_private_key FROM apps_tb WHERE client_id = ?");
if ($stmt_key) {
    $stmt_key->bind_param('s', $client_id);
    $stmt_key->execute();
    $stmt_key->bind_result($private_key);
    $stmt_key->fetch();
    $stmt_key->close();
} else {
    error_log('Unable to prepare private key lookup for client_id ' . $client_id);
}

$jwt_token = '';
if (!empty($private_key)) {
    $open_id = $userData['open_id'] ?? null;
    if ($open_id) {
        $now = time();
        $exp = $now + 3600; // 1 hour expiry
        $payload = [
            'iss' => 'https://buwana.ecobricks.org',
            'sub' => $open_id,
            'buwana_id' => $buwana_id,
            'aud' => $client_id,
            'exp' => $exp,
            'iat' => $now,
            'email' => $userData['email'] ?? '',
            'given_name' => $userData['first_name'] ?? ''
        ];
        try {
            $jwt_token = JWT::encode($payload, $private_key, 'RS256', $client_id);
        } catch (Exception $e) {
            error_log('JWT generation failed: ' . $e->getMessage());
        }
    } else {
        error_log('OpenID missing for buwana_id ' . $buwana_id);
    }
} else {
    error_log('Private key not found for client_id ' . $client_id);
}

$redirect_url = $app_dashboard_url;
$params = [];
if (!empty($jwt_token)) {
    $params['jwt'] = $jwt_token;
}
if (!empty($redirect)) {
    $params['redirect'] = $redirect;
}
if (!empty($params)) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . http_build_query($params);
}

header("Location: $redirect_url");
exit;
?>