<?php
session_start();
header('Content-Type: application/json');

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

require_once '../buwanaconn_env.php';

$buwana_id = $_SESSION['buwana_id'] ?? null;
if (!$buwana_id) {
    echo json_encode(['logged_in' => false]);
    exit;
}

$sql = "SELECT a.app_display_name, a.app_login_url
        FROM apps_tb a
        JOIN user_app_connections_tb c ON a.client_id = c.client_id
        WHERE c.buwana_id = ?";
$stmt = $buwana_conn->prepare($sql);
$apps = [];
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $apps[] = $row;
            }
        }
    }
    $stmt->close();
}

echo json_encode(['logged_in' => true, 'apps' => $apps]);
exit;
?>
