<?php
require_once '../earthenAuth_helper.php';
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php';

header('Content-Type: application/json');

$allowed_origins = ['https://ecobricks.org', 'https://earthcal.app', 'http://localhost', 'file://'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array(rtrim($origin, '/'), $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . rtrim($origin, '/'));
} elseif (empty($origin)) {
    header('Access-Control-Allow-Origin: *');
} else {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'CORS error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['unique_key'])) {
    echo json_encode(['success' => false, 'message' => 'Missing unique_key.']);
    exit();
}

$unique_key = $cal_conn->real_escape_string($data['unique_key']);

// Optional fields (with fallbacks or nulls)
$completed = isset($data['completed']) ? (int)$data['completed'] : 0;
$last_edited = isset($data['last_edited']) ? date('Y-m-d H:i:s', strtotime($data['last_edited'])) : date('Y-m-d H:i:s');

try {
    $query = "
        UPDATE datecycles_tb
        SET completed = ?, last_edited = ?, synced = 0
        WHERE unique_key = ?
    ";
    $stmt = $cal_conn->prepare($query);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . $cal_conn->error);

    $stmt->bind_param('iss', $completed, $last_edited, $unique_key);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No matching dateCycle found or no changes made.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'DateCycle updated successfully.']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
