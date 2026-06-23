<?php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../buwanaconn_env.php';           // Buwana database credentials
require_once '../includes/profile_service.php'; // Shared validate + update logic

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['buwana_id'])) {
    echo json_encode(['status' => 'failed', 'message' => 'User is not logged in.']);
    exit();
}

// CSRF: the update must be a POST carrying the session CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'failed', 'message' => 'Invalid request method.']);
    exit();
}
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['status' => 'failed', 'message' => 'Invalid or missing CSRF token.']);
    exit();
}

// Identity comes from the session; field values from the POST body. Validation
// and the write itself live in the shared service so the (future) client-app
// API applies exactly the same rules.
$result = update_user_profile($buwana_conn, $_SESSION['buwana_id'], $_POST);

echo json_encode($result);

$buwana_conn->close();
exit();
