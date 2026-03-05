<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php'; // createUserInClientApp()
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

// ---------------------------------------------------------
// Inputs
// ---------------------------------------------------------
$buwana_id = isset($_POST['buwana_id']) ? (int) $_POST['buwana_id'] : null;
$client_id = $_POST['client_id'] ?? null;
$redirect = isset($_POST['redirect']) ? filter_var($_POST['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
$is_first_time_connection = false;

if (!$buwana_id || !$client_id) {
    die("❌ Missing Buwana ID or Client ID.");
}

// App info (from fetch_app_info.php)
$app_name = $app_info['app_name'] ?? 'default_app';
$app_dashboard_url = $app_info['app_dashboard_url'] ?? '/';

// ---------------------------------------------------------
// SPECIAL: Moodle handling (unchanged)
// ---------------------------------------------------------
if ($client_id === 'lear_a30d677a7b08') {
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
        $is_first_time_connection = true;
    } else {
        $check_stmt->close();
    }

    $moodle_login_url = "https://learning.ecobricks.org/login/index.php";
    $redirect_url = "{$moodle_login_url}?auth=oidc";
    if (!empty($redirect)) $redirect_url .= '&redirect=' . urlencode($redirect);
    if ($is_first_time_connection) $redirect_url .= '&status=firsttime';
    header("Location: {$redirect_url}");
    exit;
}

// ---------------------------------------------------------
// Helper utilities
// ---------------------------------------------------------
if (!function_exists('tableExists')) {
    function tableExists(mysqli $connection, string $tableName): bool {
        $tableNameEscaped = $connection->real_escape_string($tableName);
        $result = $connection->query("SHOW TABLES LIKE '{$tableNameEscaped}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('columnExists')) {
    function columnExists(mysqli $connection, string $tableName, string $columnName): bool {
        $tableNameEscaped = $connection->real_escape_string($tableName);
        $columnNameEscaped = $connection->real_escape_string($columnName);
        $result = $connection->query("SHOW COLUMNS FROM `{$tableNameEscaped}` LIKE '{$columnNameEscaped}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

// ---------------------------------------------------------
// Load Buwana user record (source of truth)
// ---------------------------------------------------------
$stmt = $buwana_conn->prepare("SELECT * FROM users_tb WHERE buwana_id = ?");
$stmt->bind_param('i', $buwana_id);
$stmt->execute();
$result = $stmt->get_result();

$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    die("❌ Buwana user not found.");
}

// ---------------------------------------------------------
// 1) NEW: Generic Client Sync (API webhook) if configured
//    - NO client DB connection needed
// ---------------------------------------------------------
$sync_url = null;
$sync_secret = null;

try {
    if (tableExists($buwana_conn, 'apps_tb') && columnExists($buwana_conn, 'apps_tb', 'sync_url')) {
        // sync_secret column optional; if missing, header is omitted
        if (columnExists($buwana_conn, 'apps_tb', 'sync_secret')) {
            $stmt_sync = $buwana_conn->prepare("SELECT sync_url, sync_secret FROM apps_tb WHERE client_id = ? LIMIT 1");
        } else {
            $stmt_sync = $buwana_conn->prepare("SELECT sync_url, NULL as sync_secret FROM apps_tb WHERE client_id = ? LIMIT 1");
        }

        $stmt_sync->bind_param('s', $client_id);
        $stmt_sync->execute();
        $stmt_sync->bind_result($sync_url, $sync_secret);
        $stmt_sync->fetch();
        $stmt_sync->close();
    }
} catch (Exception $e) {
    error_log("sync_url lookup error client_id={$client_id}: " . $e->getMessage());
}

$response = null;

if (!empty($sync_url)) {
    // IMPORTANT: buwana_sub should be OIDC subject (your open_id field)
    $payload = [
        'buwana_sub' => $userData['open_id'] ?? null,
        'buwana_id'  => $buwana_id,
        'email'      => $userData['email'] ?? '',
        'username'   => $userData['username'] ?? '',
        'first_name' => $userData['first_name'] ?? '',
        'last_name'  => $userData['last_name'] ?? '',
        'full_name'  => $userData['full_name'] ?? '',
        'role'       => 'user'
    ];

    if (empty($payload['buwana_sub'])) {
        error_log("client sync blocked: missing open_id for buwana_id={$buwana_id}");
        $response = ['success' => false, 'error' => 'missing_open_id'];
    } else {
        $ch = curl_init($sync_url);

        $headers = ["Content-Type: application/json"];
        if (!empty($sync_secret)) {
            $headers[] = "X-Buwana-Secret: " . $sync_secret;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("client sync curl error client_id={$client_id}: " . $curlErr);
            $response = ['success' => false, 'error' => 'sync_curl_error'];
        } elseif ($httpCode < 200 || $httpCode >= 300) {
            error_log("client sync failed client_id={$client_id} http={$httpCode} resp={$respBody}");
            // You choose: soft-fail or hard-fail.
            // For now: FAIL (so user sees it, and you know immediately).
            $response = ['success' => false, 'error' => 'sync_http_' . $httpCode];
        } else {
            $response = ['success' => true, 'error' => null];
        }
    }
} else {
    // ---------------------------------------------------------
    // 2) LEGACY: direct DB insert/update
    //    - Only do this when no sync_url configured
    // ---------------------------------------------------------
    $client_env_path = "../config/{$app_name}_env.php";

    if (!file_exists($client_env_path)) {
        error_log("❌ Client config file not found at: $client_env_path");
        $response = ['success' => false, 'error' => 'missing_client_db_config'];
    } else {
        require_once $client_env_path;
        error_log("✅ Loaded client config: $client_env_path");

        if (!isset($client_conn) || !($client_conn instanceof mysqli) || $client_conn->connect_error) {
            error_log("❌ Client DB connection is not set or is invalid.");
            $response = ['success' => false, 'error' => 'client_db_unreachable'];
        } else {
            error_log("✅ Client DB connection ($app_name) established successfully.");
            $response = createUserInClientApp($buwana_id, $userData, $app_name, $client_conn, $buwana_conn, $client_id);
        }
    }
}

// ---------------------------------------------------------
// Handle sync/create errors
// ---------------------------------------------------------
if (!$response || empty($response['success'])) {
    $err = $response['error'] ?? 'unknown_error';
    echo "<h2>⚠️ Failed to connect your account</h2>";
    echo "<p>Error: " . htmlspecialchars($err) . "</p>";
    echo "<p><a href='javascript:history.back()'>Try again</a></p>";
    exit;
}

// ---------------------------------------------------------
// Record the app connection in Buwana
// ---------------------------------------------------------
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
    $is_first_time_connection = true;
} else {
    $check_stmt->close();
}

// ---------------------------------------------------------
// Generate JWT (unchanged)
// ---------------------------------------------------------
$private_key = '';

try {
    if (tableExists($buwana_conn, 'apps_tb') && columnExists($buwana_conn, 'apps_tb', 'jwt_private_key')) {
        $stmt_key = $buwana_conn->prepare("SELECT jwt_private_key FROM apps_tb WHERE client_id = ?");
    } elseif (tableExists($buwana_conn, 'oauth_clients_keys_tb')) {
        $columnName = columnExists($buwana_conn, 'oauth_clients_keys_tb', 'jwt_private_key') ? 'jwt_private_key' : 'private_key';
        $stmt_key = $buwana_conn->prepare("SELECT {$columnName} FROM oauth_clients_keys_tb WHERE client_id = ?");
    } else {
        $stmt_key = false;
        error_log('No table available for private key lookup.');
    }

    if ($stmt_key) {
        $stmt_key->bind_param('s', $client_id);
        $stmt_key->execute();
        $stmt_key->bind_result($private_key);
        $stmt_key->fetch();
        $stmt_key->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Private key lookup failed for client_id ' . $client_id . ': ' . $e->getMessage());
}

$jwt_token = '';
if (!empty($private_key)) {
    $open_id = $userData['open_id'] ?? null;
    if ($open_id) {
        $now = time();
        $exp = $now + 5400; // 90 minute expiry
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
if (!empty($jwt_token)) $params['jwt'] = $jwt_token;
if (!empty($redirect)) $params['redirect'] = $redirect;

if (!empty($params)) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . http_build_query($params);
}
if ($is_first_time_connection) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'status=firsttime';
}

header("Location: $redirect_url");
exit;
?>