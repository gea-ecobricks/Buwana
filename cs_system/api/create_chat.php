<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$appId = intval($_POST['app_id'] ?? 0);
$priority = strtolower(trim($_POST['priority'] ?? 'medium'));
$status = strtolower(trim($_POST['status'] ?? 'open'));
$category = trim($_POST['category'] ?? '');
$languageId = intval($_POST['language_id'] ?? ($user['language_id'] ?? 0));
$assignedTo = intval($_POST['assigned_to'] ?? 0);
$tagInput = $_POST['tags'] ?? '';

if ($title === '' || $description === '' || $appId <= 0) {
    cs_json_response([
        'success' => false,
        'error' => 'Title, description, and app are required.',
    ], 422);
}

$priorityOptions = ['low', 'medium', 'high', 'urgent'];
if (!in_array($priority, $priorityOptions, true)) {
    $priority = 'medium';
}

$statusOptions = ['open', 'in_progress', 'resolved', 'closed'];
if (!in_array($status, $statusOptions, true)) {
    $status = 'open';
}

if (!$isAdmin) {
    $status = 'open';
    $assignedTo = 0;
}

$assignedNormalized = ($isAdmin && $assignedTo > 0) ? $assignedTo : 0;

$stmt = $buwana_conn->prepare('INSERT INTO cs_chats_tb (user_id, app_id, language_id, title, description, status, priority, category, assigned_to, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NOW(), NOW())');
if (!$stmt) {
    cs_json_response([
        'success' => false,
        'error' => 'Unable to create chat.',
    ], 500);
}

$stmt->bind_param(
    'iiisssssi',
    $user['buwana_id'],
    $appId,
    $languageId,
    $title,
    $description,
    $status,
    $priority,
    $category,
    $assignedNormalized
);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    cs_json_response([
        'success' => false,
        'error' => 'Failed to save chat: ' . $error,
    ], 500);
}

$chatId = $stmt->insert_id;
$stmt->close();

$messageStmt = $buwana_conn->prepare('INSERT INTO cs_messages_tb (chat_id, user_id, language_id, body, parent_id, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, NOW(), NOW())');
if (!$messageStmt) {
    cs_json_response([
        'success' => false,
        'error' => 'Unable to save initial message.',
    ], 500);
}

$messageStmt->bind_param('iiis', $chatId, $user['buwana_id'], $languageId, $description);
if (!$messageStmt->execute()) {
    $error = $messageStmt->error;
    $messageStmt->close();
    cs_json_response([
        'success' => false,
        'error' => 'Failed to save initial message: ' . $error,
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
            // Continue processing remaining files but log error
            error_log('Attachment upload failed: ' . $e->getMessage());
        }
    }
}

$tags = [];
if (is_string($tagInput) && $tagInput !== '') {
    $decoded = json_decode($tagInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $tags = $decoded;
    } else {
        $tags = array_map('trim', explode(',', $tagInput));
    }
} elseif (is_array($tagInput)) {
    $tags = $tagInput;
}

$tagIds = cs_resolve_tag_ids($buwana_conn, $tags);
cs_sync_chat_tags($buwana_conn, $chatId, $tagIds);

cs_touch_chat_reader($buwana_conn, $chatId, intval($user['buwana_id']));

$chatDetails = cs_fetch_chats($buwana_conn, [
    'current_user_id' => intval($user['buwana_id']),
    'chat_id' => $chatId,
]);

cs_json_response([
    'success' => true,
    'data' => [
        'chat' => $chatDetails ? $chatDetails[0] : null,
        'message_id' => $messageId,
        'attachments' => $attachments,
    ],
]);
