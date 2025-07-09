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

$signup_1_top_img_light  = $_POST['signup_1_top_img_light'] ?? '';
$signup_1_top_img_dark   = $_POST['signup_1_top_img_dark'] ?? '';
$signup_2_top_img_light  = $_POST['signup_2_top_img_light'] ?? '';
$signup_2_top_img_dark   = $_POST['signup_2_top_img_dark'] ?? '';
$signup_3_top_img_light  = $_POST['signup_3_top_img_light'] ?? '';
$signup_3_top_img_dark   = $_POST['signup_3_top_img_dark'] ?? '';
$signup_4_top_img_light  = $_POST['signup_4_top_img_light'] ?? '';
$signup_4_top_img_dark   = $_POST['signup_4_top_img_dark'] ?? '';
$signup_5_top_img_light  = $_POST['signup_5_top_img_light'] ?? '';
$signup_5_top_img_dark   = $_POST['signup_5_top_img_dark'] ?? '';
$signup_6_top_img_light  = $_POST['signup_6_top_img_light'] ?? '';
$signup_6_top_img_dark   = $_POST['signup_6_top_img_dark'] ?? '';
$signup_7_top_img_light  = $_POST['signup_7_top_img_light'] ?? '';
$signup_7_top_img_dark   = $_POST['signup_7_top_img_dark'] ?? '';
$login_top_img_light     = $_POST['login_top_img_light'] ?? '';
$login_top_img_dark      = $_POST['login_top_img_dark'] ?? '';

$success = false;
$error_message = '';

$sql = "UPDATE apps_tb a
        JOIN app_owners_tb ao ON ao.app_id = a.app_id
        SET a.signup_1_top_img_light=?, a.signup_1_top_img_dark=?,
            a.signup_2_top_img_light=?, a.signup_2_top_img_dark=?, a.signup_3_top_img_light=?, a.signup_3_top_img_dark=?,
            a.signup_4_top_img_light=?, a.signup_4_top_img_dark=?, a.signup_5_top_img_light=?, a.signup_5_top_img_dark=?,
            a.signup_6_top_img_light=?, a.signup_6_top_img_dark=?, a.signup_7_top_img_light=?, a.signup_7_top_img_dark=?,
            a.login_top_img_light=?, a.login_top_img_dark=?
        WHERE a.app_id=? AND ao.buwana_id=?";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    if ($stmt->bind_param('ssssssssssssssssii', $signup_1_top_img_light, $signup_1_top_img_dark, $signup_2_top_img_light, $signup_2_top_img_dark, $signup_3_top_img_light, $signup_3_top_img_dark, $signup_4_top_img_light, $signup_4_top_img_dark, $signup_5_top_img_light, $signup_5_top_img_dark, $signup_6_top_img_light, $signup_6_top_img_dark, $signup_7_top_img_light, $signup_7_top_img_dark, $login_top_img_light, $login_top_img_dark, $app_id, $buwana_id)) {
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
