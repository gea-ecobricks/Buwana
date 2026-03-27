<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

header('Content-Type: application/json');

if (empty($_SESSION['buwana_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_app'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$buwana_id = intval($_SESSION['buwana_id']);
$app_id    = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;

$scope_options = [
    'openid',
    'buwana:basic',
    'buwana:profile',
    'buwana:community',
    'buwana:bioregion',
];

$redirect_uris     = $_POST['redirect_uris'] ?? '';
$app_login_url     = $_POST['app_login_url'] ?? '';
$scopes_input      = $_POST['scopes'] ?? [];
$scopes_input      = is_array($scopes_input) ? $scopes_input : [];
$scopes_input      = array_intersect($scopes_input, $scope_options);
$scopes            = implode(',', $scopes_input);
$app_domain        = $_POST['app_domain'] ?? '';
$app_url           = $_POST['app_url'] ?? '';
$app_dashboard_url = $_POST['app_dashboard_url'] ?? '';
$app_description   = $_POST['app_description'] ?? '';
$app_version       = $_POST['app_version'] ?? '';
$app_display_name  = $_POST['app_display_name'] ?? '';
$contact_email     = $_POST['contact_email'] ?? '';

$success = false;
$error_message = '';

$sql = "UPDATE apps_tb a
        JOIN app_owners_tb ao ON ao.app_id = a.app_id
        SET a.redirect_uris=?, a.app_login_url=?, a.scopes=?, a.app_domain=?, a.app_url=?, a.app_dashboard_url=?, a.app_description=?, a.app_version=?, a.app_display_name=?, a.contact_email=?
        WHERE a.app_id=? AND ao.buwana_id=?";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    if ($stmt->bind_param('ssssssssssii', $redirect_uris, $app_login_url, $scopes, $app_domain, $app_url, $app_dashboard_url, $app_description, $app_version, $app_display_name, $contact_email, $app_id, $buwana_id)) {
        $success = $stmt->execute();
        if (!$success) {
            $error_message = $stmt->error;
        }
    } else {
        $error_message = $stmt->error;
    }
    $stmt->close();
} else {
    $error_message = $buwana_conn->error;
}

echo json_encode(['success' => $success, 'error' => $error_message]);
exit();
?>
