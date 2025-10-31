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

$app_slogan      = $_POST['app_slogan'] ?? '';
$app_terms_txt   = $_POST['app_terms_txt'] ?? '';
$app_privacy_txt = $_POST['app_privacy_txt'] ?? '';
$app_emojis_array = trim($_POST['app_emojis_array'] ?? '');

$success = false;
$error_message = '';

$decoded_emojis = json_decode($app_emojis_array, true);
if ($app_emojis_array === '') {
    $error_message = 'Emojis Array is required.';
} elseif (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_emojis)) {
    $error_message = 'Emojis Array must be valid JSON (for example ["🧱","🌍","🌱"]).';
} else {
    $normalized_emojis = [];
    foreach ($decoded_emojis as $emoji) {
        if (!is_string($emoji) || $emoji === '') {
            $error_message = 'Emojis Array must contain only emoji strings.';
            break;
        }
        $normalized_emojis[] = $emoji;
    }

    if ($error_message === '') {
        $app_emojis_array = json_encode($normalized_emojis, JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE apps_tb a
                JOIN app_owners_tb ao ON ao.app_id = a.app_id
                SET a.app_slogan=?, a.app_terms_txt=?, a.app_privacy_txt=?, a.app_emojis_array=?
                WHERE a.app_id=? AND ao.buwana_id=?";
        $stmt = $buwana_conn->prepare($sql);
        if ($stmt) {
            if ($stmt->bind_param('ssssii', $app_slogan, $app_terms_txt, $app_privacy_txt, $app_emojis_array, $app_id, $buwana_id)) {
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
    }
}

echo json_encode(['success' => $success, 'error' => $error_message]);
exit();
?>
