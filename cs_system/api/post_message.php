<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$chatId = intval($_POST['chat_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$parentId = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
$languageId = intval($_POST['language_id'] ?? ($user['language_id'] ?? 0));

if ($chatId <= 0 || $body === '') {
    cs_json_response([
        'success' => false,
        'error' => 'Chat and message body are required.',
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

if (!$isAdmin && intval($user['buwana_id']) !== $ownerId && intval($user['buwana_id']) !== $assignedId) {
    cs_json_response([
        'success' => false,
        'error' => 'You are not authorized to reply to this chat.',
    ], 403);
}

$messageStmt = $buwana_conn->prepare('INSERT INTO cs_messages_tb (chat_id, user_id, language_id, body, parent_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
if (!$messageStmt) {
    cs_json_response([
        'success' => false,
        'error' => 'Unable to create message.',
    ], 500);
}

if ($parentId && $parentId <= 0) {
    $parentId = null;
}

$messageStmt->bind_param('iiisi', $chatId, $user['buwana_id'], $languageId, $body, $parentId);
if (!$messageStmt->execute()) {
    $error = $messageStmt->error;
    $messageStmt->close();
    cs_json_response([
        'success' => false,
        'error' => 'Failed to save message: ' . $error,
    ], 500);
}

$messageId = $messageStmt->insert_id;
$messageStmt->close();

$attachments = [];
if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $count = count($_FILES['attachments']['name']);
    for ($i = 0; $i < $count; $i++) {
        if (!isset($_FILES['attachments']['tmp_name'][$i]) || $_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $fileInfo = [
            'name' => $_FILES['attachments']['name'][$i],
            'type' => $_FILES['attachments']['type'][$i] ?? mime_content_type($_FILES['attachments']['tmp_name'][$i]),
            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
            'error' => $_FILES['attachments']['error'][$i],
            'size' => $_FILES['attachments']['size'][$i],
        ];

        try {
            $processed = cs_process_image_upload($fileInfo, $chatId, $messageId);
            $insertAttachment = $buwana_conn->prepare('INSERT INTO cs_attachments_tb (chat_id, message_id, file_url, file_type, created_at) VALUES (?, ?, ?, ?, NOW())');
            if ($insertAttachment) {
                $insertAttachment->bind_param('iiss', $chatId, $messageId, $processed['file_url'], $processed['file_type']);
                $insertAttachment->execute();
                $insertAttachment->close();
            }
            $attachments[] = $processed;
        } catch (Throwable $e) {
            error_log('Attachment upload failed: ' . $e->getMessage());
        }
    }
}

$updateChat = $buwana_conn->prepare('UPDATE cs_chats_tb SET updated_at = NOW() WHERE id = ?');
if ($updateChat) {
    $updateChat->bind_param('i', $chatId);
    $updateChat->execute();
    $updateChat->close();
}

cs_touch_chat_reader($buwana_conn, $chatId, intval($user['buwana_id']));

$messages = cs_fetch_chat_messages($buwana_conn, $chatId);
$newMessage = null;
foreach ($messages as $message) {
    if (intval($message['id']) === $messageId) {
        $newMessage = $message;
        break;
    }
}

$updatedChat = cs_fetch_chats($buwana_conn, [
    'current_user_id' => intval($user['buwana_id']),
    'chat_id' => $chatId,
]);

cs_json_response([
    'success' => true,
    'data' => [
        'message' => $newMessage,
        'attachments' => $attachments,
        'chat' => $updatedChat ? $updatedChat[0] : null,
    ],
]);
