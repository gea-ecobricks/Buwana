<?php
ob_start(); // Start output buffering to prevent unexpected output

require_once '../earthenAuth_helper.php';
require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';
require_once '../config/earthcal_env.php'; // Include EarthCal database connection
require_once '../includes/user_deletion.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$earthcal_conn = $client_conn ?? null; // Normalize EarthCal connection handle

$response = [];
$successes = [];
$failures = [];
$overall_success = true;

try {
    if (!$earthcal_conn instanceof mysqli || $earthcal_conn->connect_errno) {
        throw new Exception('EarthCal DB connection is not available.');
    }

    // 🔐 Identity comes ONLY from the authenticated session — never the URL.
    // Previously $_GET['buwana_id'] was trusted ahead of the session AND there was
    // no login check, so any unauthenticated caller could delete ANY account by id
    // (and enumerate the whole user base). A user may only ever delete themselves.
    if (!isLoggedIn()) {
        throw new Exception('You must be logged in to delete your account.');
    }

    // CSRF + method: deletion must be a POST carrying the session CSRF token, so
    // it can't be triggered cross-site (e.g. via an <img>/link GET).
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Invalid or missing CSRF token.');
    }

    $buwana_id = (int) $_SESSION['buwana_id'];
    if ($buwana_id <= 0) {
        throw new Exception('Invalid Buwana ID.');
    }

    $cascade = buwana_delete_user_cascade($buwana_conn, $gobrik_conn ?? null, $earthcal_conn, $buwana_id, 'your');
    $successes = $cascade['successes'];
    $failures = $cascade['failures'];
    $overall_success = $cascade['success'];

    // Clear user session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    $query = http_build_query([
        'successes' => $successes,
        'failures' => $failures
    ]);

    $response = [
        'success' => $overall_success,
        'message' => $overall_success ? 'User deleted successfully.' : 'User deletion completed with some errors.',
        'redirect' => 'goodbye.php?' . $query
    ];
} catch (Exception $e) {
    if ($buwana_conn->in_transaction) {
        $buwana_conn->rollback();
    }
    if (isset($gobrik_conn) && $gobrik_conn->in_transaction) {
        $gobrik_conn->rollback();
    }
    if ($earthcal_conn instanceof mysqli && $earthcal_conn->in_transaction) {
        $earthcal_conn->rollback();
    }

    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

ob_end_clean();
echo json_encode($response);
exit();
