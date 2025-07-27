<?php
require_once '../buwanaconn_env.php';

header('Content-Type: application/json');

$allowed_origins = [
    'https://earthcal.app',
    'https://ecobricks.org',
    'https://gobrik.com',
    'https://learning.ecobricks.org',
    'https://openbooks.ecobricks.org',
    'https://earthen.io'
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

$community_id = isset($_GET['community_id']) ? intval($_GET['community_id']) : intval($_GET['id'] ?? 0);
if ($community_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid community ID']);
    exit();
}

$sql = "SELECT com_name FROM communities_tb WHERE community_id = ?";
$stmt = $buwana_conn->prepare($sql);
$community_name = null;
if ($stmt) {
    $stmt->bind_param('i', $community_id);
    $stmt->execute();
    $stmt->bind_result($community_name);
    $stmt->fetch();
    $stmt->close();
}

if ($community_name !== null) {
    echo json_encode(['success' => true, 'com_name' => $community_name]);
} else {
    echo json_encode(['success' => false, 'message' => 'Community not found']);
}
?>
