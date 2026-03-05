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
$is_first_time_connection = false;

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
        $is_first_time_connection = true;
    } else {
        $check_stmt->close();
    }

    // Redirect to Moodle's login page to initiate the proper OIDC flow
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


// Helper utilities ---------------------------------------------------------
if (!function_exists('tableExists')) {
    /**
     * Check if a given table exists in the current database.
     */
    function tableExists(mysqli $connection, string $tableName): bool
    {
        $tableNameEscaped = $connection->real_escape_string($tableName);
        $result = $connection->query("SHOW TABLES LIKE '{$tableNameEscaped}'");

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('columnExists')) {
    /**
     * Check if a column exists on the provided table.
     */
    function columnExists(mysqli $connection, string $tableName, string $columnName): bool
    {
        $tableNameEscaped = $connection->real_escape_string($tableName);
        $columnNameEscaped = $connection->real_escape_string($columnName);
        $sql = "SHOW COLUMNS FROM `{$tableNameEscaped}` LIKE '{$columnNameEscaped}'";
        $result = $connection->query($sql);

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
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





// ---------------------------------------------------------
// SPECIAL AIRBUDDY HANDLING (use API instead of direct DB)
// ---------------------------------------------------------
if ($client_id === 'airb_ca090536efc8') {

    $api_url = "https://air2.earthen.io/api/buwana/sync-user";

    $payload = [
        'buwana_sub' => $userData['open_id'] ?? null,
        'email' => $userData['email'] ?? '',
        'username' => $userData['username'] ?? '',
        'first_name' => $userData['first_name'] ?? '',
        'last_name' => $userData['last_name'] ?? '',
        'full_name' => $userData['full_name'] ?? ''
    ];

    $ch = curl_init($api_url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-Buwana-Secret: " . BUWANA_SYNC_SECRET
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10
    ]);

    $api_response = curl_exec($ch);
    $api_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("AirBuddy sync curl error: " . curl_error($ch));
    }

    curl_close($ch);

    if ($api_http_code !== 200) {
        error_log("AirBuddy sync failed: HTTP {$api_http_code} response={$api_response}");
    }

    // simulate success so the rest of the flow continues
    $response = ['success' => true];
}
else {

    // Normal legacy client DB creation
    $response = createUserInClientApp(
        $buwana_id,
        $userData,
        $app_name,
        $client_conn,
        $buwana_conn,
        $client_id
    );

}


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
    $is_first_time_connection = true;
} else {
    $check_stmt->close();
}

// ✅ Step 3: Generate JWT and redirect to the app dashboard
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