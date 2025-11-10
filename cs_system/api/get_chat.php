<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$input = array_merge($_GET ?? [], $_POST ?? [], cs_get_json_input());
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

if (!$isAdmin && intval($chat['owner']['id']) !== intval($user['buwana_id'])) {
    cs_json_response([
        'success' => false,
        'error' => 'You are not authorized to view this chat.',
    ], 403);
}

$messages = cs_fetch_chat_messages($buwana_conn, $chatId);
cs_touch_chat_reader($buwana_conn, $chatId, intval($user['buwana_id']));

cs_json_response([
    'success' => true,
    'data' => [
        'chat' => $chat,
        'messages' => $messages,
        'is_admin' => $isAdmin,
    ],
]);
