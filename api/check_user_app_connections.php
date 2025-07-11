<?php
session_start();
header('Content-Type: application/json');
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
