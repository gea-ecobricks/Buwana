<?php
session_start();
require_once '../buwanaconn_env.php';
header('Content-Type: application/json');

$submitted_csrf = $_POST['csrf_token'] ?? '';
if (empty($submitted_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'invalid_csrf_token']);
    exit();
}

$buwana_id = intval($_SESSION['buwana_id'] ?? 0);
$app_id = intval($_POST['app_id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = isset($_POST['value']) ? intval($_POST['value']) : null;

if(!$buwana_id || !$app_id || !in_array($field, ['is_active','allow_signup']) || !in_array($value,[0,1])){
    echo json_encode(['success'=>false]);
    exit();
}

$sql = $field === 'is_active'
    ? 'UPDATE apps_tb a JOIN app_owners_tb ao ON ao.app_id = a.app_id SET a.is_active=? WHERE a.app_id=? AND ao.buwana_id=?'
    : 'UPDATE apps_tb a JOIN app_owners_tb ao ON ao.app_id = a.app_id SET a.allow_signup=? WHERE a.app_id=? AND ao.buwana_id=?';
$stmt = $buwana_conn->prepare($sql);
$success = false;
if($stmt){
    $stmt->bind_param('iii',$value,$app_id,$buwana_id);
    $success = $stmt->execute();
    $stmt->close();
}

echo json_encode(['success'=>$success]);
