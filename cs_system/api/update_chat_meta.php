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
$currentUserId = intval($user['buwana_id']);

if (!$isAdmin && $currentUserId !== $ownerId && $currentUserId !== $assignedId) {
    cs_json_response([
        'success' => false,
        'error' => 'You are not permitted to update this chat.',
    ], 403);
}

$priority = isset($input['priority']) ? strtolower(trim((string) $input['priority'])) : null;
$status = isset($input['status']) ? strtolower(trim((string) $input['status'])) : null;
$category = array_key_exists('category', $input) ? trim((string) $input['category']) : null;
$assignedTo = $input['assigned_to'] ?? null;
$tagsInput = $input['tags'] ?? null;
$resolvedAt = $input['resolved_at'] ?? null;
$closedAt = $input['closed_at'] ?? null;

$allowedPriorities = ['low', 'medium', 'high', 'urgent'];
$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

$fields = [];
$params = [];
$types = '';

if ($priority && in_array($priority, $allowedPriorities, true)) {
    $fields[] = 'priority = ?';
    $params[] = $priority;
    $types .= 's';
}

if ($status && in_array($status, $allowedStatuses, true) && ($isAdmin || $status === 'open' || $status === 'in_progress')) {
    $fields[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($category !== null) {
    if ($category === '') {
        $fields[] = 'category = NULL';
    } else {
        $fields[] = 'category = ?';
        $params[] = $category;
        $types .= 's';
    }
}

if ($isAdmin && $assignedTo !== null) {
    $assignedValue = intval($assignedTo);
    $fields[] = 'assigned_to = NULLIF(?, 0)';
    $params[] = $assignedValue;
    $types .= 'i';
}

if ($isAdmin && $resolvedAt !== null) {
    if ($resolvedAt === '') {
        $fields[] = 'resolved_at = NULL';
    } else {
        $fields[] = 'resolved_at = ?';
        $params[] = $resolvedAt;
        $types .= 's';
    }
}

if ($isAdmin && $closedAt !== null) {
    if ($closedAt === '') {
        $fields[] = 'closed_at = NULL';
    } else {
        $fields[] = 'closed_at = ?';
        $params[] = $closedAt;
        $types .= 's';
    }
}

if ($fields) {
    $fields[] = 'updated_at = NOW()';
    $sql = 'UPDATE cs_chats_tb SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $buwana_conn->prepare($sql);
    if (!$stmt) {
        cs_json_response([
            'success' => false,
            'error' => 'Failed to prepare update.',
        ], 500);
    }

    $types .= 'i';
    $params[] = $chatId;
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        cs_json_response([
            'success' => false,
            'error' => 'Failed to update chat: ' . $error,
        ], 500);
    }
    $stmt->close();
}

if ($tagsInput !== null) {
    $tags = [];
    if (is_string($tagsInput)) {
        $decoded = json_decode($tagsInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $tags = $decoded;
        } else {
            $tags = array_map('trim', explode(',', $tagsInput));
        }
    } elseif (is_array($tagsInput)) {
        $tags = $tagsInput;
    }
    $tagIds = cs_resolve_tag_ids($buwana_conn, $tags);
    cs_sync_chat_tags($buwana_conn, $chatId, $tagIds);
}

$updatedChat = cs_fetch_chats($buwana_conn, [
    'current_user_id' => intval($user['buwana_id']),
    'chat_id' => $chatId,
]);

cs_json_response([
    'success' => true,
    'data' => [
        'chat' => $updatedChat ? $updatedChat[0] : null,
    ],
]);
