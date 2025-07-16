<?php
session_start();
require_once __DIR__ . '/../buwanaconn_env.php';

header('Content-Type: application/json');

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

$buwana_id = $_SESSION['buwana_id'] ?? null;

if (!$buwana_id) {
    echo json_encode(['logged_in' => false, 'apps' => []]);
    exit();
}

$sql = "SELECT a.app_display_name, a.app_login_url, a.app_logo_url, a.app_logo_dark_url, a.app_version, a.app_slogan
        FROM apps_tb a
        JOIN user_app_connections_tb c ON a.client_id = c.client_id
        WHERE c.buwana_id = ?";

$apps = [];
if ($stmt = $buwana_conn->prepare($sql)) {
    $stmt->bind_param('i', $buwana_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            $apps = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

echo json_encode(['logged_in' => true, 'apps' => $apps]);
exit();
?>
