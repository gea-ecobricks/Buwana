<?php
// Admin-only: delete another user's Buwana account (and its client-app data)
// from the App Manager dashboard. Session + CSRF + POST + admin role required.
// Runs the same cascade as a self-delete (includes/user_deletion.php).
ob_start(); // Start output buffering to prevent unexpected output
session_start();

require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';
require_once '../config/earthcal_env.php'; // Include EarthCal database connection
require_once '../includes/user_deletion.php';

header('Content-Type: application/json');

$response_sent = false;

function jsonError(string $msg): void {
    global $response_sent;
    ob_end_clean();
    $response_sent = true;
    echo json_encode(['success' => false, 'error' => $msg]);
    exit();
}

// The cascade calls into legacy Earthen helpers that may echo HTML or exit()
// mid-request (e.g. createGhostJWT() when EARTHEN_KEY is missing), and a slow
// Ghost API call can hit max_execution_time. Either way the user is usually
// already deleted, so always answer with JSON instead of a stray body.
register_shutdown_function(function () use (&$response_sent) {
    if ($response_sent) {
        return;
    }
    $stray = '';
    while (ob_get_level() > 0) {
        $stray .= ob_get_clean();
    }
    $fatal = error_get_last();
    error_log('admin_delete_user.php ended early. Last error: ' . json_encode($fatal) . ' Output: ' . substr($stray, 0, 500));
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'error'   => 'The server stopped before finishing. The account may already be deleted; refresh to check.',
    ]);
});

$earthcal_conn = $client_conn ?? null; // Normalize EarthCal connection handle

$admin_id = intval($_SESSION['buwana_id'] ?? 0);
if (!$admin_id) {
    jsonError('Not authenticated.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.');
}

// Validate CSRF token
$submitted_csrf = $_POST['csrf_token'] ?? '';
if (empty($submitted_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
    jsonError('Invalid CSRF token.');
}

// Verify the requesting user has admin role
$stmt = $buwana_conn->prepare("SELECT role FROM users_tb WHERE buwana_id = ? LIMIT 1");
if (!$stmt) jsonError('Database error.');
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if (stripos($role ?? '', 'admin') === false) {
    jsonError('Admin access required.');
}

$target_id = intval($_POST['buwana_id'] ?? 0);
if ($target_id <= 0) {
    jsonError('Invalid Buwana ID.');
}
if ($target_id === $admin_id) {
    jsonError('You cannot delete your own account from the dashboard. Use your profile page instead.');
}

// Load the target and refuse protected accounts
$target_role = null;
$target_deleteable = null;
$stmt = $buwana_conn->prepare("SELECT role, deleteable FROM users_tb WHERE buwana_id = ? LIMIT 1");
if (!$stmt) jsonError('Database error.');
$stmt->bind_param('i', $target_id);
$stmt->execute();
$stmt->bind_result($target_role, $target_deleteable);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    jsonError('User not found.');
}
if ((int) $target_deleteable === 0) {
    jsonError('This account is marked as non-deleteable.');
}
if (stripos($target_role ?? '', 'admin') !== false) {
    jsonError('Admin accounts cannot be deleted from the dashboard.');
}

if (!$earthcal_conn instanceof mysqli || $earthcal_conn->connect_errno) {
    jsonError('EarthCal DB connection is not available.');
}

try {
    $cascade = buwana_delete_user_cascade($buwana_conn, $gobrik_conn ?? null, $earthcal_conn, $target_id, "the user's");
    error_log("Admin {$admin_id} deleted Buwana user {$target_id}: " . json_encode($cascade));

    $response = [
        'success'   => $cascade['success'],
        'message'   => $cascade['success'] ? 'User deleted successfully.' : 'User deletion completed with some errors.',
        'successes' => $cascade['successes'],
        'failures'  => $cascade['failures'],
    ];
} catch (Throwable $e) {
    // mysqli has no in_transaction flag; rolling back with no open transaction is a no-op.
    $buwana_conn->rollback();
    if (isset($gobrik_conn) && $gobrik_conn instanceof mysqli) {
        $gobrik_conn->rollback();
    }
    $earthcal_conn->rollback();
    error_log("Admin {$admin_id} delete of Buwana user {$target_id} threw: " . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

ob_end_clean();
$response_sent = true;
echo json_encode($response);
exit();
