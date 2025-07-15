<?php
require_once __DIR__ . '/../earthenAuth_helper.php';
require_once __DIR__ . '/../buwanaconn_env.php';

// Allow cross-origin requests from trusted apps
$allowed_origins = [
    'https://earthcal.app',
    'https://gobrik.com',
    'https://ecobricks.org',
    'https://learning.ecobricks.org',
    'https://openbooks.ecobricks.org'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🌿 Retrieve query params
$buwana_id = intval($_GET['buwana_id'] ?? 0);
$client_id = $_GET['client_id'] ?? '';
$lang = $_GET['lang'] ?? 'en';

$response = [
    'connected' => false,
    'app_login_url' => "/$lang/app-connect.php?id=$buwana_id&client_id=$client_id"
];

if ($buwana_id && $client_id) {
    $check_sql = "SELECT id FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? AND status = 'registered' LIMIT 1";
    $check_stmt = $buwana_conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param('is', $buwana_id, $client_id);
        $check_stmt->execute();
        $check_stmt->bind_result($connection_id);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($connection_id) {
            $_SESSION['connection_id'] = $connection_id;
            $response['connected'] = true;
            unset($response['app_login_url']); // not needed if connected
        }
    }
}

// 🍃 Echo the truth
header('Content-Type: application/json');
echo json_encode($response);
