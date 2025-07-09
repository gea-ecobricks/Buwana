<?php
session_start();
require_once '../buwanaconn_env.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$app_id = intval($data['app_id'] ?? 0);
$owners = $data['owners'] ?? [];
$buwana_id = intval($_SESSION['buwana_id'] ?? 0);

if(!$app_id || !$buwana_id){
    echo json_encode(['success'=>false]);
    exit();
}

// Verify current user is an owner
$stmt = $buwana_conn->prepare("SELECT 1 FROM app_owners_tb WHERE app_id=? AND buwana_id=? LIMIT 1");
if(!$stmt){
    echo json_encode(['success'=>false]);
    exit();
}
$stmt->bind_param('ii', $app_id, $buwana_id);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows === 0){
    $stmt->close();
    echo json_encode(['success'=>false]);
    exit();
}
$stmt->close();

$buwana_conn->begin_transaction();

$del = $buwana_conn->prepare("DELETE FROM app_owners_tb WHERE app_id=?");
if($del){
    $del->bind_param('i', $app_id);
    $del->execute();
    $del->close();
}

$ins = $buwana_conn->prepare("INSERT INTO app_owners_tb (app_id, buwana_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE buwana_id=VALUES(buwana_id)");
if($ins){
    foreach($owners as $oid){
        $oid = intval($oid);
        $ins->bind_param('ii', $app_id, $oid);
        $ins->execute();
    }
    $ins->close();
}

$buwana_conn->commit();

echo json_encode(['success'=>true]);
