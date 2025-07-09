<?php
session_start();
require_once '../buwanaconn_env.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit();
}

$sql = "SELECT u.buwana_id, u.full_name
        FROM users_tb u
        JOIN user_app_connections_tb c ON u.buwana_id = c.buwana_id
        JOIN apps_tb a ON c.client_id = a.client_id
        WHERE a.app_id = 5 AND u.full_name LIKE CONCAT('%', ?, '%')
        GROUP BY u.buwana_id
        ORDER BY u.full_name
        LIMIT 10";
$stmt = $buwana_conn->prepare($sql);
$users = [];
if ($stmt) {
    $stmt->bind_param('s', $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

echo json_encode($users);
