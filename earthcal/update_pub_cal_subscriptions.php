<?php
require_once '../earthenAuth_helper.php';
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php';

header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

// CORS Handling
$allowed_origins = [
    'https://cycles.earthen.io',
    'https://ecobricks.org',
    'https://gobrik.com',
    'http://localhost',
    'file://'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim($_SERVER['HTTP_ORIGIN'], '/') : '';
if (empty($origin)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    error_log('CORS error: Invalid origin - ' . $origin);
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'CORS error']);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit();
}

// Initialize response
$response = ['success' => false];

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$buwana_id = $input['buwana_id'] ?? null;
$calendar_id = $input['calendar_id'] ?? null;
$subscribe = $input['subscribe'] ?? null;

// Log input data
error_log("Received subscription request: " . print_r($input, true));

// Validate input
if (empty($buwana_id) || !is_numeric($buwana_id)) {
    $response['message'] = 'Invalid or missing Buwana ID.';
    echo json_encode($response);
    exit();
}

if (empty($calendar_id) || !is_numeric($calendar_id)) {
    $response['message'] = 'Invalid or missing Calendar ID.';
    echo json_encode($response);
    exit();
}

if (!isset($subscribe) || ($subscribe !== "1" && $subscribe !== "0")) {
    $response['message'] = 'Invalid subscribe value. Must be "1" or "0".';
    echo json_encode($response);
    exit();
}

try {
    $cal_conn->begin_transaction();

    if ($subscribe === "1") {
        // Subscribe to the calendar (INSERT IGNORE prevents duplicate entries)
        $stmt = $cal_conn->prepare("INSERT IGNORE INTO cal_subscriptions_tb (buwana_id, calendar_id, subscribed_on) VALUES (?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("SQL Preparation Failed: " . $cal_conn->error);
        }
        $stmt->bind_param("ii", $buwana_id, $calendar_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Unsubscribe from the calendar
        $stmt = $cal_conn->prepare("DELETE FROM cal_subscriptions_tb WHERE buwana_id = ? AND calendar_id = ?");
        if (!$stmt) {
            throw new Exception("SQL Preparation Failed: " . $cal_conn->error);
        }
        $stmt->bind_param("ii", $buwana_id, $calendar_id);
        $stmt->execute();
        $stmt->close();
    }

    // Commit transaction
    $cal_conn->commit();

    // Success response
    $response['success'] = true;
    $response['message'] = $subscribe === "1" ? "Subscription added successfully." : "Subscription removed successfully.";
    error_log("Subscription update successful for buwana_id: $buwana_id, calendar_id: $calendar_id");

} catch (Exception $e) {
    $cal_conn->rollback();
    $response['message'] = "Database error: " . $e->getMessage();
    error_log("Subscription update failed: " . $e->getMessage());
} finally {
    $cal_conn->close();
}

// Return response as JSON
echo json_encode($response);
exit();
?>
