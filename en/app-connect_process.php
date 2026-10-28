<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// fetch_app_info.php resolves the app from $_GET / the session, so point it at
// the posted client_id; otherwise a stale session app (e.g. BAM) gets provisioned.
if (!empty($_POST['client_id'])) {
    $_GET['client_id'] = $_POST['client_id'];
}

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php'; // createUserInClientApp()
require_once '../vendor/autoload.php';

use Firebase\JWT\JWT;

// ---------------------------------------------------------
// Inputs
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Invalid request method.");
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    die("❌ Your session expired. Please go back and try again.");
}

// 🔐 Identity comes ONLY from the authenticated session — never the form.
$buwana_id = isset($_SESSION['buwana_id']) ? (int) $_SESSION['buwana_id'] : null;
$client_id = $_POST['client_id'] ?? null;
$redirect = isset($_POST['redirect']) ? filter_var($_POST['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
$is_first_time_connection = false;

if (!$buwana_id || !$client_id) {
    die("❌ Missing Buwana ID or Client ID.");
}

// fetch_app_info.php falls back to GoBrik for an unknown client_id
if (($app_info['client_id'] ?? null) !== $client_id) {
    die("❌ Unknown app.");
}

// App info (from fetch_app_info.php)
$app_name = $app_info['app_name'] ?? 'default_app';
$app_dashboard_url = $app_info['app_dashboard_url'] ?? '/';

// ---------------------------------------------------------
// SPECIAL: Moodle handling
// ---------------------------------------------------------
if ($client_id === 'lear_a30d677a7b08') {
    $check_sql = "SELECT 1 FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1";
    $check_stmt = $buwana_conn->prepare($check_sql);
    $check_stmt->bind_param('is', $buwana_id, $client_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    $connected_at = date('Y-m-d H:i:s');

    if ($check_stmt->num_rows === 0) {
        $check_stmt->close();

        $status = 'registered';
        $insert_sql = "INSERT INTO user_app_connections_tb (buwana_id, client_id, status, connected_at) VALUES (?, ?, ?, ?)";
        $insert_stmt = $buwana_conn->prepare($insert_sql);
        $insert_stmt->bind_param('isss', $buwana_id, $client_id, $status, $connected_at);
        $insert_stmt->execute();
        $insert_stmt->close();

        $is_first_time_connection = true;
    } else {
        $check_stmt->close();
    }

    if (function_exists('updateAppConnectionStatus')) {
        updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'registered', $connected_at);
    }

    if (function_exists('updateBuwanaUserNotes')) {
        updateBuwanaUserNotes($buwana_conn, $buwana_id, $app_name, $connected_at);
    }

    $moodle_login_url = "https://learning.ecobricks.org/login/index.php";
    $redirect_url = "{$moodle_login_url}?auth=oidc";
    if (!empty($redirect)) {
        $redirect_url .= '&redirect=' . urlencode($redirect);
    }
    if ($is_first_time_connection) {
        $redirect_url .= '&status=firsttime';
    }

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

// Accounts that never reached signup-6 have no open_id; give them the same
// 'buwana_<id>' subject token.php already falls back to.
if (empty($userData['open_id'])) {
    $userData['open_id'] = 'buwana_' . $buwana_id;
    $stmt_oid = $buwana_conn->prepare("UPDATE users_tb SET open_id = ? WHERE buwana_id = ? AND (open_id IS NULL OR open_id = '')");
    if ($stmt_oid) {
        $stmt_oid->bind_param('si', $userData['open_id'], $buwana_id);
        $stmt_oid->execute();
        $stmt_oid->close();
    }
    error_log('OpenID was missing for buwana_id ' . $buwana_id . '; set to ' . $userData['open_id']);
}

// ---------------------------------------------------------
// 1) API-first: Generic Client Sync (webhook) if configured
//    - NO client DB connection needed
// ---------------------------------------------------------
$sync_url = null;
$sync_secret = null;

try {
    if (tableExists($buwana_conn, 'apps_tb') && columnExists($buwana_conn, 'apps_tb', 'sync_url')) {
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
            $response = ['success' => false, 'error' => 'sync_http_' . $httpCode];
        } else {
            error_log("✅ Client sync success via API for client_id={$client_id}");
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
    echo "<p><a href='/en/app-connect.php?id=" . (int) $buwana_id . "&amp;client_id=" . htmlspecialchars(urlencode($client_id), ENT_QUOTES, 'UTF-8') . "'>Try again</a></p>";
    exit;
}

// ---------------------------------------------------------
// Record the app connection in Buwana
// ---------------------------------------------------------
$connected_at = date('Y-m-d H:i:s');

$check_sql = "SELECT 1 FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1";
$check_stmt = $buwana_conn->prepare($check_sql);
$check_stmt->bind_param('is', $buwana_id, $client_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    $check_stmt->close();

    $status = 'registered';
    $insert_sql = "INSERT INTO user_app_connections_tb (buwana_id, client_id, status, connected_at) VALUES (?, ?, ?, ?)";
    $insert_stmt = $buwana_conn->prepare($insert_sql);
    $insert_stmt->bind_param('isss', $buwana_id, $client_id, $status, $connected_at);
    $insert_stmt->execute();
    $insert_stmt->close();

    $is_first_time_connection = true;
    error_log("✅ App connection recorded for buwana_id={$buwana_id}, client_id={$client_id}");
} else {
    $check_stmt->close();
    error_log("ℹ️ App connection already exists for buwana_id={$buwana_id}, client_id={$client_id}");
}

// ---------------------------------------------------------
// Optional parity updates for Buwana-side notes/status
// ---------------------------------------------------------
if (function_exists('updateAppConnectionStatus')) {
    updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'registered', $connected_at);
}

if (function_exists('updateBuwanaUserNotes')) {
    updateBuwanaUserNotes($buwana_conn, $buwana_id, $app_name, $connected_at);
}

// ---------------------------------------------------------
// Resume an OAuth/OIDC login that was waiting on this connection
//   check_user_app_connection() sends a not-yet-connected user here before
//   the login process gets to pending_oauth_request, so it's still in the
//   session. Hand it back to authorize.php, as login_process_jwt.php does,
//   instead of dropping the user on the dashboard. Without this, PKCE clients
//   (native apps like Wrangler) never get their code on a first connection.
// ---------------------------------------------------------
$pending = $_SESSION['pending_oauth_request'] ?? null;
if (is_array($pending)
    && ($pending['client_id'] ?? null) === $client_id
    && (int) ($_SESSION['user_id'] ?? 0) === $buwana_id) {
    unset($_SESSION['pending_oauth_request']);
    error_log("↪️ Resuming OAuth flow after app connection for buwana_id={$buwana_id}, client_id={$client_id}");
    header('Location: /authorize.php?' . http_build_query($pending));
    exit;
}

// ---------------------------------------------------------
// Generate JWT
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

if (!empty($jwt_token)) {
    $params['jwt'] = $jwt_token;
}
if (!empty($redirect)) {
    $params['redirect'] = $redirect;
}

if (!empty($params)) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . http_build_query($params);
}

if ($is_first_time_connection) {
    $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'status=firsttime';
}

header("Location: $redirect_url");
exit;
?>