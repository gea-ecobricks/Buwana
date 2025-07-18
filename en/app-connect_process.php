<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php'; // Includes createUserInClientApp()

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

// 🌟 SPECIAL MOODLE HANDLING
if ($client_id === 'lear_a30d677a7b08') {
    // Redirect to Moodle's OpenID Connect plugin login path
    $moodle_oidc_url = "https://learning.ecobricks.org/auth/oidc/";
    $redirect_param = !empty($redirect) ? '&redirect=' . urlencode($redirect) : '';
    header("Location: {$moodle_oidc_url}?from_buwana=1{$redirect_param}");
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

// ✅ Step 3: Redirect to the app login page with upgraded status
$app_login_url = $app_info['app_login_url'] ?? '/';
$redirect_url = $app_login_url . '?id=' . urlencode($buwana_id) . '&status=upgraded';
if (!empty($redirect)) {
    $redirect_url .= '&redirect=' . urlencode($redirect);
}

header("Location: $redirect_url");
exit;
?>
