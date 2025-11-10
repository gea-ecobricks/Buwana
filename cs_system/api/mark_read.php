<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$input = array_merge($_POST ?? [], cs_get_json_input());
$chatId = intval($input['chat_id'] ?? 0);
$messageIds = $input['message_ids'] ?? [];

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
$currentUserId = intval($user['buwana_id']);

if (!$isAdmin && $currentUserId !== $ownerId && $currentUserId !== $assignedId) {
    cs_json_response([
        'success' => false,
        'error' => 'You are not permitted to update read state for this chat.',
    ], 403);
}

cs_touch_chat_reader($buwana_conn, $chatId, $currentUserId);

if (is_string($messageIds)) {
    $decoded = json_decode($messageIds, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $messageIds = $decoded;
    } else {
        $messageIds = array_map('intval', explode(',', $messageIds));
    }
}

if (!is_array($messageIds)) {
    $messageIds = [];
}

foreach ($messageIds as $messageId) {
    $messageId = intval($messageId);
    if ($messageId > 0) {
        cs_touch_message_reader($buwana_conn, $messageId, $currentUserId);
    }
}

cs_json_response([
    'success' => true,
    'data' => [
        'chat_id' => $chatId,
        'message_ids' => array_values(array_filter(array_map('intval', (array) $messageIds))),
    ],
]);
