<?php
// ----------------------------------------
// 🌐 signup-6_process.php
// Final step of Buwana account creation
// ----------------------------------------

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php';

// 🌿 Capture active client id from session
$session_client_id = $_SESSION['client_id'] ?? null;

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

// --- STEP 1: Validate and extract inputs ---
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

$buwana_id = (int) $buwana_id;

$selected_country_id  = $_POST['country_name'] ?? null; // this is the country_id
$selected_language_id = $_POST['language_id'] ?? '';
$earthling_emoji      = $_POST['earthling_emoji'] ?? '🌍';

// --- STEP 2: Load app info ---
$app_name      = $app_info['app_name'] ?? null;
$app_login_url = $app_info['app_login_url'] ?? '/';
$client_id     = $app_info['client_id'] ?? null;

if (!$app_name || !$client_id) {
    die("❌ Missing app configuration details.");
}

// --- STEP 3: Resolve continent_code using country_id ---
$set_continent_code = null;

$sql = "SELECT continent_code FROM countries_tb WHERE country_id = ? LIMIT 1";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $selected_country_id);
    $stmt->execute();
    $stmt->bind_result($set_continent_code);
    $stmt->fetch();
    $stmt->close();
}

// --- STEP 4: Update Buwana User Record ---
$update_sql = "
    UPDATE users_tb
    SET continent_code = ?,
        country_id = ?,
        language_id = ?,
        earthling_emoji = ?,
        open_id = CONCAT('buwana_', ?)
    WHERE buwana_id = ?
";
$stmt = $buwana_conn->prepare($update_sql);
$stmt->bind_param(
    'sissii',
    $set_continent_code,
    $selected_country_id,
    $selected_language_id,
    $earthling_emoji,
    $buwana_id,  // for CONCAT('buwana_', ?)
    $buwana_id   // for WHERE clause
);
$stmt->execute();
$stmt->close();

// --- STEP 5: Bypass full client provisioning if Learning Portal app ---
if ($session_client_id === 'lear_a30d677a7b08') {
    error_log("🌱 Skipping client provisioning for Learning Portal app ID: $session_client_id");
    $connected_at = date('Y-m-d H:i:s');
    updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'registered', $connected_at);
    updateBuwanaUserNotes($buwana_conn, $buwana_id, $app_name, $connected_at);

    header("Location: signup-7.php?id=" . urlencode($buwana_id));
    exit();
}

// --- STEP 6: Fetch fresh Buwana user fields for provisioning ---
$userData = [];
$stmt = $buwana_conn->prepare("
    SELECT
        open_id,
        username,
        first_name,
        last_name,
        full_name,
        email,
        terms_of_service,
        profile_pic,
        country_id,
        language_id,
        continent_code,
        location_full,
        location_watershed,
        location_lat,
        location_long,
        community_id,
        earthling_emoji
    FROM users_tb
    WHERE buwana_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc() ?: [];
$stmt->close();

if (empty($userData)) {
    die("❌ Unable to load Buwana user record for provisioning.");
}

// ---------------------------------------------------------
// STEP 7: API-first provisioning via sync_url if configured
//         Otherwise fall back to legacy DB provisioning
// ---------------------------------------------------------
$sync_url = null;
$sync_secret = null;
$response = null;

try {
    if (tableExists($buwana_conn, 'apps_tb') && columnExists($buwana_conn, 'apps_tb', 'sync_url')) {
        if (columnExists($buwana_conn, 'apps_tb', 'sync_secret')) {
            $stmt_sync = $buwana_conn->prepare("
                SELECT sync_url, sync_secret
                FROM apps_tb
                WHERE client_id = ?
                LIMIT 1
            ");
        } else {
            $stmt_sync = $buwana_conn->prepare("
                SELECT sync_url, NULL as sync_secret
                FROM apps_tb
                WHERE client_id = ?
                LIMIT 1
            ");
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

// --- STEP 7A: Modern webhook/API sync path ---
if (!empty($sync_url)) {
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
    // STEP 7B: Legacy direct DB provisioning fallback
    // ---------------------------------------------------------
    $client_env_path = "../config/{$app_name}_env.php";

    if (!file_exists($client_env_path)) {
        error_log("❌ Missing DB config: $client_env_path");
        $response = ['success' => false, 'error' => 'missing_client_db_config'];
    } else {
        require_once $client_env_path;

        if (!isset($client_conn) || !($client_conn instanceof mysqli) || $client_conn->connect_error) {
            error_log("❌ Client DB connection could not be initialized for {$app_name}");
            $response = ['success' => false, 'error' => 'client_db_unreachable'];
        } else {
            error_log("✅ Using legacy DB provisioning for {$app_name}");
            $response = createUserInClientApp($buwana_id, $userData, $app_name, $client_conn, $buwana_conn, $client_id);
        }
    }
}

// --- STEP 8: Handle provisioning result ---
if ($response && !empty($response['success'])) {

    // ---------------------------------------------------------
    // Record the app connection in Buwana for all apps/users
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

        $insert_sql = "
            INSERT INTO user_app_connections_tb (buwana_id, client_id, status, connected_at)
            VALUES (?, ?, ?, ?)
        ";
        $insert_stmt = $buwana_conn->prepare($insert_sql);
        $insert_stmt->bind_param('isss', $buwana_id, $client_id, $status, $connected_at);
        $insert_stmt->execute();
        $insert_stmt->close();

        error_log("✅ App connection recorded for buwana_id={$buwana_id}, client_id={$client_id}");
    } else {
        $check_stmt->close();
        error_log("ℹ️ App connection already exists for buwana_id={$buwana_id}, client_id={$client_id}");
    }

    header("Location: signup-7.php?id=" . urlencode($buwana_id));
    exit;
} else {
    $err = $response['error'] ?? 'unknown_error';
    die("❌ Failed to create user in client app. Error: " . htmlspecialchars($err));
}
?>