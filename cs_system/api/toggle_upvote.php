<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$input = array_merge($_POST ?? [], cs_get_json_input());
$chatId = intval($input['chat_id'] ?? 0);

if ($chatId <= 0) {
    cs_json_response([
        'success' => false,
        'error' => 'Chat ID is required.',
    ], 422);
}

$chatRecords = cs_fetch_chats($buwana_conn, [
    'current_user_id' => intval($user['buwana_id']),
    'chat_id' => $chatId,
]);

if (!$chatRecords) {
    cs_json_response([
        'success' => false,
        'error' => 'Chat not found.',
    ], 404);
}

$chat = $chatRecords[0];
$ownerId = intval($chat['owner']['id']);
$assignedId = intval($chat['assigned_to']['id'] ?? 0);
$userId = intval($user['buwana_id']);

if (!$isAdmin && $userId !== $ownerId && $userId !== $assignedId) {
    cs_json_response([
        'success' => false,
        'error' => 'You are not permitted to interact with this chat.',
    ], 403);
}

$checkStmt = $buwana_conn->prepare('SELECT id FROM cs_chat_upvotes_tb WHERE chat_id = ? AND user_id = ?');
if (!$checkStmt) {
    cs_json_response([
        'success' => false,
        'error' => 'Unable to process request.',
    ], 500);
}

$checkStmt->bind_param('ii', $chatId, $userId);
$hasUpvoted = false;
if ($checkStmt->execute()) {
    $checkStmt->bind_result($upvoteId);
    if ($checkStmt->fetch()) {
        $hasUpvoted = true;
    }
}
$checkStmt->close();

if ($hasUpvoted) {
    $deleteStmt = $buwana_conn->prepare('DELETE FROM cs_chat_upvotes_tb WHERE chat_id = ? AND user_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('ii', $chatId, $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    $hasUpvoted = false;
} else {
    $insertStmt = $buwana_conn->prepare('INSERT INTO cs_chat_upvotes_tb (chat_id, user_id, created_at) VALUES (?, ?, NOW())');
    if ($insertStmt) {
        $insertStmt->bind_param('ii', $chatId, $userId);
        $insertStmt->execute();
        $insertStmt->close();
    }
    $hasUpvoted = true;
}

$countStmt = $buwana_conn->prepare('SELECT COUNT(*) FROM cs_chat_upvotes_tb WHERE chat_id = ?');
$totalUpvotes = 0;
if ($countStmt) {
    $countStmt->bind_param('i', $chatId);
    if ($countStmt->execute()) {
        $countStmt->bind_result($totalUpvotes);
        $countStmt->fetch();
    }
    $countStmt->close();
}

cs_json_response([
    'success' => true,
    'data' => [
        'chat_id' => $chatId,
        'has_upvoted' => $hasUpvoted,
        'upvote_count' => intval($totalUpvotes),
    ],
]);
