<?php
require_once __DIR__ . '/bootstrap.php';

function cs_fetch_connected_apps(mysqli $conn, int $userId): array
{
    $apps = [];
    $sql = "SELECT a.app_id, a.app_name, a.app_display_name, a.client_id, a.app_square_icon_url, a.app_slogan
            FROM apps_tb a
            INNER JOIN user_app_connections_tb c ON a.client_id = c.client_id
            WHERE c.buwana_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $apps[intval($row['app_id'])] = $row;
                }
            }
        }
        $stmt->close();
    }

    return $apps;
}

function cs_fetch_support_staff(mysqli $conn): array
{
    $staff = [];
    $sql = "SELECT buwana_id, first_name, full_name, earthling_emoji
            FROM users_tb
            WHERE LOWER(role) = 'admin'
            ORDER BY first_name";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $staff[] = [
                'id' => intval($row['buwana_id']),
                'first_name' => $row['first_name'] ?: $row['full_name'],
                'earthling_emoji' => $row['earthling_emoji'],
            ];
        }
        $result->free();
    }

    return $staff;
}

function cs_fetch_categories(mysqli $conn): array
{
    $categories = [];
    $result = $conn->query("SELECT DISTINCT category FROM cs_chats_tb WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
        $result->free();
    }

    return $categories;
}

function cs_group_chats_by_app(array $chats, array $connectedApps, ?array $currentApp): array
{
    $grouped = [];
    foreach ($chats as $chat) {
        $appId = intval($chat['app']['app_id']);
        if (!isset($grouped[$appId])) {
            $grouped[$appId] = [
                'app' => $chat['app'],
                'chats' => [],
            ];
        }
        $grouped[$appId]['chats'][] = $chat;
    }

    foreach ($connectedApps as $appId => $appInfo) {
        if (!isset($grouped[$appId])) {
            $grouped[$appId] = [
                'app' => [
                    'app_id' => intval($appInfo['app_id']),
                    'app_name' => $appInfo['app_name'],
                    'app_display_name' => $appInfo['app_display_name'],
                    'client_id' => $appInfo['client_id'],
                    'app_square_icon_url' => $appInfo['app_square_icon_url'] ?? null,
                    'is_current' => false,
                ],
                'chats' => [],
            ];
        }
    }

    if ($currentApp && isset($currentApp['app_id'])) {
        $currentId = intval($currentApp['app_id']);
        if (!isset($grouped[$currentId])) {
            $grouped[$currentId] = [
                'app' => [
                    'app_id' => $currentId,
                    'app_name' => $currentApp['app_name'] ?? ($currentApp['client_id'] ?? 'app'),
                    'app_display_name' => $currentApp['app_display_name'] ?? ($currentApp['app_name'] ?? 'App'),
                    'client_id' => $currentApp['client_id'] ?? null,
                    'app_square_icon_url' => $currentApp['app_square_icon_url'] ?? null,
                    'is_current' => true,
                ],
                'chats' => [],
            ];
        }
    }

    foreach ($grouped as &$group) {
        $group['app']['is_current'] = isset($currentApp['app_id']) && intval($group['app']['app_id']) === intval($currentApp['app_id']);
    }
    unset($group);

    usort($grouped, static function ($a, $b) {
        $aCurrent = $a['app']['is_current'] ?? false;
        $bCurrent = $b['app']['is_current'] ?? false;
        if ($aCurrent === $bCurrent) {
            return strcasecmp($a['app']['app_display_name'] ?? '', $b['app']['app_display_name'] ?? '');
        }
        return $aCurrent ? -1 : 1;
    });

    return $grouped;
}

function cs_fetch_chats(mysqli $conn, array $options = []): array
{
    $currentUserId = intval($options['current_user_id'] ?? 0);
    $types = 'i';
    $params = [$currentUserId];
    $joins = [];
    $where = ['1 = 1'];

    if (!empty($options['chat_id'])) {
        $where[] = 'c.id = ?';
        $types .= 'i';
        $params[] = intval($options['chat_id']);
    }

    if (!empty($options['owner_id'])) {
        $where[] = 'c.user_id = ?';
        $types .= 'i';
        $params[] = intval($options['owner_id']);
    }

    if (!empty($options['app_id'])) {
        $where[] = 'c.app_id = ?';
        $types .= 'i';
        $params[] = intval($options['app_id']);
    }

    if (!empty($options['participant_id'])) {
        $participantId = intval($options['participant_id']);
        $where[] = '(c.assigned_to = ? OR c.user_id = ? OR EXISTS (SELECT 1 FROM cs_messages_tb pm WHERE pm.chat_id = c.id AND pm.user_id = ?))';
        $types .= 'iii';
        $params[] = $participantId;
        $params[] = $participantId;
        $params[] = $participantId;
    }

    if (!empty($options['status'])) {
        $statuses = is_array($options['status']) ? $options['status'] : [$options['status']];
        $statuses = array_filter(array_map('trim', $statuses));
        if ($statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $where[] = "c.status IN ($placeholders)";
            $types .= str_repeat('s', count($statuses));
            foreach ($statuses as $status) {
                $params[] = strtolower($status);
            }
        }
    }

    if (!empty($options['priority'])) {
        $priorities = is_array($options['priority']) ? $options['priority'] : [$options['priority']];
        $priorities = array_filter(array_map('trim', $priorities));
        if ($priorities) {
            $placeholders = implode(',', array_fill(0, count($priorities), '?'));
            $where[] = "c.priority IN ($placeholders)";
            $types .= str_repeat('s', count($priorities));
            foreach ($priorities as $priority) {
                $params[] = strtolower($priority);
            }
        }
    }

    if (!empty($options['tag_ids'])) {
        $tagIds = array_map('intval', (array) $options['tag_ids']);
        $tagIds = array_filter($tagIds, static fn($id) => $id > 0);
        if ($tagIds) {
            $joins[] = 'INNER JOIN cs_chat_tag_map_tb map_filter ON map_filter.chat_id = c.id';
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $where[] = "map_filter.tag_id IN ($placeholders)";
            $types .= str_repeat('i', count($tagIds));
            foreach ($tagIds as $tagId) {
                $params[] = $tagId;
            }
        }
    }

    $sql = "SELECT c.id, c.user_id, c.app_id, c.language_id, c.title, c.description, c.status, c.priority, c.category,
                   c.assigned_to, c.created_at, c.updated_at, c.resolved_at, c.closed_at,
                   a.app_name, a.app_display_name, a.client_id, a.app_square_icon_url,
                   au.first_name AS assigned_first_name, au.earthling_emoji AS assigned_emoji,
                   owner.first_name AS owner_first_name, owner.earthling_emoji AS owner_emoji,
                   (SELECT COUNT(*) FROM cs_chat_upvotes_tb cu WHERE cu.chat_id = c.id) AS upvote_count,
                   (SELECT COUNT(*) FROM cs_messages_tb m WHERE m.chat_id = c.id) AS message_count,
                   IF(cu_user.user_id IS NULL, 0, 1) AS has_upvoted
            FROM cs_chats_tb c
            JOIN apps_tb a ON c.app_id = a.app_id
            LEFT JOIN users_tb au ON c.assigned_to = au.buwana_id
            LEFT JOIN users_tb owner ON c.user_id = owner.buwana_id
            LEFT JOIN cs_chat_upvotes_tb cu_user ON cu_user.chat_id = c.id AND cu_user.user_id = ?
            " . implode(' ', $joins) . "
            WHERE " . implode(' AND ', $where) . '
            GROUP BY c.id
            ORDER BY c.updated_at DESC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    if (!$rows) {
        return [];
    }

    $chatIds = array_map(static fn($row) => intval($row['id']), $rows);
    $readers = cs_fetch_chat_readers($conn, $chatIds);
    $tags = cs_fetch_chat_tags($conn, $chatIds);

    $payload = [];
    foreach ($rows as $row) {
        $chatId = intval($row['id']);
        $payload[] = [
            'id' => $chatId,
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'category' => $row['category'],
            'assigned_to' => $row['assigned_to'] ? [
                'id' => intval($row['assigned_to']),
                'first_name' => $row['assigned_first_name'],
                'earthling_emoji' => $row['assigned_emoji'],
            ] : null,
            'owner' => [
                'id' => intval($row['user_id']),
                'first_name' => $row['owner_first_name'],
                'earthling_emoji' => $row['owner_emoji'],
            ],
            'app' => [
                'app_id' => intval($row['app_id']),
                'app_name' => $row['app_name'],
                'app_display_name' => $row['app_display_name'] ?: $row['app_name'],
                'client_id' => $row['client_id'],
                'app_square_icon_url' => $row['app_square_icon_url'],
                'is_current' => false,
            ],
            'language_id' => intval($row['language_id'] ?? 0),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'resolved_at' => $row['resolved_at'],
            'closed_at' => $row['closed_at'],
            'message_count' => intval($row['message_count'] ?? 0),
            'upvote_count' => intval($row['upvote_count'] ?? 0),
            'has_upvoted' => intval($row['has_upvoted'] ?? 0) === 1,
            'readers' => $readers[$chatId] ?? [],
            'tags' => $tags[$chatId] ?? [],
        ];
    }

    return $payload;
}

function cs_fetch_chat_readers(mysqli $conn, array $chatIds): array
{
    if (!$chatIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($chatIds), '?'));
    $types = str_repeat('i', count($chatIds));
    $sql = "SELECT cr.chat_id, cr.user_id, cr.last_read_at, u.first_name, u.earthling_emoji
            FROM cs_chat_readers_tb cr
            JOIN users_tb u ON u.buwana_id = cr.user_id
            WHERE cr.chat_id IN ($placeholders)
            ORDER BY cr.last_read_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$chatIds);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $readers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chatId = intval($row['chat_id']);
            if (!isset($readers[$chatId])) {
                $readers[$chatId] = [];
            }
            $readers[$chatId][] = [
                'id' => intval($row['user_id']),
                'first_name' => $row['first_name'],
                'earthling_emoji' => $row['earthling_emoji'],
                'last_read_at' => $row['last_read_at'],
            ];
        }
        $result->free();
    }

    $stmt->close();

    return $readers;
}

function cs_fetch_chat_tags(mysqli $conn, array $chatIds): array
{
    if (!$chatIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($chatIds), '?'));
    $types = str_repeat('i', count($chatIds));
    $sql = "SELECT map.chat_id, t.id, t.name, t.slug
            FROM cs_chat_tag_map_tb map
            JOIN cs_chat_tags_tb t ON t.id = map.tag_id
            WHERE map.chat_id IN ($placeholders)
            ORDER BY t.name";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$chatIds);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $tags = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chatId = intval($row['chat_id']);
            if (!isset($tags[$chatId])) {
                $tags[$chatId] = [];
            }
            $tags[$chatId][] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'slug' => $row['slug'],
            ];
        }
        $result->free();
    }

    $stmt->close();

    return $tags;
}

function cs_fetch_chat_messages(mysqli $conn, int $chatId): array
{
    $stmt = $conn->prepare('SELECT m.id, m.chat_id, m.user_id, m.language_id, m.body, m.parent_id, m.created_at, m.updated_at, u.first_name, u.earthling_emoji
                             FROM cs_messages_tb m
                             LEFT JOIN users_tb u ON u.buwana_id = m.user_id
                             WHERE m.chat_id = ?
                             ORDER BY m.created_at ASC, m.id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $chatId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $messages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    if (!$messages) {
        return [];
    }

    $messageIds = array_map(static fn($row) => intval($row['id']), $messages);
    $attachments = cs_fetch_message_attachments($conn, $messageIds);
    $reads = cs_fetch_message_reads($conn, $messageIds);

    $payload = [];
    foreach ($messages as $message) {
        $messageId = intval($message['id']);
        $payload[] = [
            'id' => $messageId,
            'chat_id' => intval($message['chat_id']),
            'user_id' => intval($message['user_id']),
            'language_id' => intval($message['language_id'] ?? 0),
            'body' => $message['body'],
            'parent_id' => $message['parent_id'] ? intval($message['parent_id']) : null,
            'created_at' => $message['created_at'],
            'updated_at' => $message['updated_at'],
            'author' => [
                'id' => intval($message['user_id']),
                'first_name' => $message['first_name'],
                'earthling_emoji' => $message['earthling_emoji'],
            ],
            'attachments' => $attachments[$messageId] ?? [],
            'reads' => $reads[$messageId] ?? [],
        ];
    }

    return $payload;
}

function cs_fetch_message_attachments(mysqli $conn, array $messageIds): array
{
    if (!$messageIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $types = str_repeat('i', count($messageIds));
    $sql = "SELECT id, chat_id, message_id, file_url, file_type, created_at
            FROM cs_attachments_tb
            WHERE message_id IN ($placeholders)
            ORDER BY created_at";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$messageIds);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $attachments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messageId = intval($row['message_id']);
            if (!isset($attachments[$messageId])) {
                $attachments[$messageId] = [];
            }
            $fileUrl = $row['file_url'];
            $thumbUrl = preg_replace('#/attachments/2025/#', '/attachments/2025/thumbs/', $fileUrl);
            $attachments[$messageId][] = [
                'id' => intval($row['id']),
                'chat_id' => intval($row['chat_id']),
                'message_id' => $messageId,
                'file_url' => $fileUrl,
                'thumbnail_url' => $thumbUrl,
                'file_type' => $row['file_type'],
                'created_at' => $row['created_at'],
            ];
        }
        $result->free();
    }

    $stmt->close();

    return $attachments;
}

function cs_fetch_message_reads(mysqli $conn, array $messageIds): array
{
    if (!$messageIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $types = str_repeat('i', count($messageIds));
    $sql = "SELECT mr.message_id, mr.user_id, mr.read_at, u.first_name, u.earthling_emoji
            FROM cs_message_reads_tb mr
            JOIN users_tb u ON u.buwana_id = mr.user_id
            WHERE mr.message_id IN ($placeholders)
            ORDER BY mr.read_at DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$messageIds);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $reads = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messageId = intval($row['message_id']);
            if (!isset($reads[$messageId])) {
                $reads[$messageId] = [];
            }
            $reads[$messageId][] = [
                'user_id' => intval($row['user_id']),
                'first_name' => $row['first_name'],
                'earthling_emoji' => $row['earthling_emoji'],
                'read_at' => $row['read_at'],
            ];
        }
        $result->free();
    }

    $stmt->close();

    return $reads;
}
