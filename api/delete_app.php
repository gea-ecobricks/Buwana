<?php
session_start();
require_once '../buwanaconn_env.php';

header('Content-Type: application/json');

function jsonError(string $msg): void {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit();
}

$buwana_id = intval($_SESSION['buwana_id'] ?? 0);
if (!$buwana_id) {
    jsonError('Not authenticated.');
}

// Validate CSRF token
$submitted_csrf = $_POST['csrf_token'] ?? '';
if (empty($submitted_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
    jsonError('Invalid CSRF token.');
}

$app_id = intval($_POST['app_id'] ?? 0);
if (!$app_id) {
    jsonError('Invalid app ID.');
}

// Verify the requesting user has admin role
$stmt = $buwana_conn->prepare("SELECT role FROM users_tb WHERE buwana_id = ? LIMIT 1");
if (!$stmt) jsonError('Database error.');
$stmt->bind_param('i', $buwana_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

if (stripos($role ?? '', 'admin') === false) {
    jsonError('Admin access required.');
}

// Verify the user is an owner of this app
$stmt = $buwana_conn->prepare("SELECT 1 FROM app_owners_tb WHERE app_id = ? AND buwana_id = ? LIMIT 1");
if (!$stmt) jsonError('Database error.');
$stmt->bind_param('ii', $app_id, $buwana_id);
$stmt->execute();
$stmt->store_result();
$is_owner = $stmt->num_rows > 0;
$stmt->close();

if (!$is_owner) {
    jsonError('You do not own this app.');
}

// Fetch client_id for cascading deletes on client_id-keyed tables
$stmt = $buwana_conn->prepare("SELECT client_id FROM apps_tb WHERE app_id = ? LIMIT 1");
if (!$stmt) jsonError('Database error.');
$stmt->bind_param('i', $app_id);
$stmt->execute();
$stmt->bind_result($client_id);
$stmt->fetch();
$stmt->close();

if (!$client_id) {
    jsonError('App not found.');
}

// Cascade delete within a transaction
$buwana_conn->begin_transaction();

try {
    $deletes = [
        ['DELETE FROM authorization_codes_tb WHERE client_id = ?',  's', $client_id],
        ['DELETE FROM user_app_connections_tb WHERE client_id = ?',  's', $client_id],
        ['DELETE FROM app_owners_tb WHERE app_id = ?',              'i', $app_id],
        ['DELETE FROM apps_tb WHERE app_id = ?',                    'i', $app_id],
    ];

    foreach ($deletes as [$sql, $type, $val]) {
        $st = $buwana_conn->prepare($sql);
        if (!$st) throw new Exception('Prepare failed: ' . $buwana_conn->error);
        $st->bind_param($type, $val);
        if (!$st->execute()) throw new Exception('Execute failed: ' . $st->error);
        $st->close();
    }

    $buwana_conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $buwana_conn->rollback();
    jsonError('Delete failed: ' . $e->getMessage());
}
