<?php
/**
 * Bootstrap utilities for the Buwana Chat Support (CS) system APIs.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../earthenAuth_helper.php';

if (!file_exists(__DIR__ . '/../../buwanaconn_env.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration missing.',
    ]);
    exit;
}

require_once __DIR__ . '/../../buwanaconn_env.php';

header('Content-Type: application/json');

/**
 * Sends a JSON response and halts execution.
 */
function cs_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * Fetch the currently authenticated user record.
 */
function cs_get_current_user(mysqli $conn): array
{
    $userId = intval($_SESSION['buwana_id'] ?? 0);
    if ($userId <= 0) {
        cs_json_response([
            'success' => false,
            'error' => 'Authentication required.',
        ], 401);
    }

    $sql = "SELECT buwana_id, full_name, first_name, email, language_id, earthling_emoji, country_id, role
            FROM users_tb
            WHERE buwana_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        cs_json_response([
            'success' => false,
            'error' => 'Failed to prepare user lookup.',
        ], 500);
    }

    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        cs_json_response([
            'success' => false,
            'error' => 'Failed to execute user lookup.',
        ], 500);
    }

    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        cs_json_response([
            'success' => false,
            'error' => 'User not found.',
        ], 404);
    }

    return $user;
}

/**
 * Determines if a user should be considered support admin.
 */
function cs_is_admin_user(array $user): bool
{
    $role = strtolower(trim($user['role'] ?? ''));
    return $role !== '' && strpos($role, 'admin') !== false;
}

/**
 * Generates a slug from a string.
 */
function cs_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug ?? '');
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'tag-' . uniqid();
}

/**
 * Builds a safe string for HTML output.
 */
function cs_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Ensures CS attachment directories exist.
 */
function cs_ensure_attachment_directories(): array
{
    $baseDir = __DIR__ . '/../attachments/2025';
    $thumbDir = $baseDir . '/thumbs';

    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }

    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0775, true);
    }

    return [
        'base' => realpath($baseDir) ?: $baseDir,
        'thumbs' => realpath($thumbDir) ?: $thumbDir,
    ];
}

/**
 * Converts an uploaded image to WebP and returns paths.
 *
 * @param array $file The $_FILES entry for the upload
 * @param int $chatId
 * @param int|null $messageId
 */
function cs_process_image_upload(array $file, int $chatId, ?int $messageId = null): array
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $directories = cs_ensure_attachment_directories();
    $unique = uniqid('cs_' . $chatId . '_');
    $baseName = $unique . '.webp';
    $destPath = $directories['base'] . '/' . $baseName;
    $thumbPath = $directories['thumbs'] . '/' . $baseName;

    $image = cs_create_image_from_upload($file['tmp_name'], $file['type']);
    if (!$image) {
        throw new RuntimeException('Failed to read uploaded image.');
    }

    if (!imagewebp($image, $destPath, 88)) {
        imagedestroy($image);
        throw new RuntimeException('Failed to save WebP image.');
    }

    $thumb = imagescale($image, 150, 150, IMG_BILINEAR_FIXED);
    if ($thumb) {
        if (!imagewebp($thumb, $thumbPath, 77)) {
            imagedestroy($thumb);
            imagedestroy($image);
            throw new RuntimeException('Failed to save thumbnail.');
        }
        imagedestroy($thumb);
    }

    imagedestroy($image);

    return [
        'file_url' => 'cs_system/attachments/2025/' . $baseName,
        'thumb_url' => 'cs_system/attachments/2025/thumbs/' . $baseName,
        'file_type' => 'image/webp',
        'original_name' => $file['name'] ?? $baseName,
    ];
}

/**
 * Creates an image resource from an upload.
 */
function cs_create_image_from_upload(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return imagecreatefromjpeg($path);
        case 'image/png':
            return imagecreatefrompng($path);
        case 'image/gif':
            return imagecreatefromgif($path);
        case 'image/webp':
            return imagecreatefromwebp($path);
        default:
            return null;
    }
}

/**
 * Safely decode JSON from input.
 */
function cs_get_json_input(): array
{
    $input = file_get_contents('php://input');
    if (!$input) {
        return [];
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    return is_array($data) ? $data : [];
}

/**
 * Fetch tags keyed by slug.
 */
function cs_get_existing_tags(mysqli $conn): array
{
    $tags = [];
    $result = $conn->query('SELECT id, name, slug FROM cs_chat_tags_tb ORDER BY name');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tags[strtolower($row['slug'])] = $row;
        }
        $result->free();
    }

    return $tags;
}

/**
 * Ensure tags exist and return their IDs.
 *
 * @param mysqli $conn
 * @param array $tagNames
 * @return int[]
 */
function cs_resolve_tag_ids(mysqli $conn, array $tagNames): array
{
    if (empty($tagNames)) {
        return [];
    }

    $tagIds = [];
    $existing = cs_get_existing_tags($conn);

    foreach ($tagNames as $tagName) {
        $name = trim($tagName);
        if ($name === '') {
            continue;
        }

        $slug = cs_slugify($name);
        $key = strtolower($slug);

        if (isset($existing[$key])) {
            $tagIds[] = intval($existing[$key]['id']);
            continue;
        }

        $insert = $conn->prepare('INSERT INTO cs_chat_tags_tb (name, slug, description, created_at, updated_at) VALUES (?, ?, NULL, NOW(), NOW())');
        if ($insert) {
            $insert->bind_param('ss', $name, $slug);
            if ($insert->execute()) {
                $tagIds[] = $insert->insert_id;
                $existing[$key] = [
                    'id' => $insert->insert_id,
                    'name' => $name,
                    'slug' => $slug,
                ];
            }
            $insert->close();
        }
    }

    return $tagIds;
}

/**
 * Attach tags to a chat (replaces existing map).
 */
function cs_sync_chat_tags(mysqli $conn, int $chatId, array $tagIds): void
{
    $delete = $conn->prepare('DELETE FROM cs_chat_tag_map_tb WHERE chat_id = ?');
    if ($delete) {
        $delete->bind_param('i', $chatId);
        $delete->execute();
        $delete->close();
    }

    if (empty($tagIds)) {
        return;
    }

    $insert = $conn->prepare('INSERT INTO cs_chat_tag_map_tb (chat_id, tag_id, created_at) VALUES (?, ?, NOW())');
    if (!$insert) {
        return;
    }

    foreach ($tagIds as $tagId) {
        $tagId = intval($tagId);
        if ($tagId <= 0) {
            continue;
        }
        $insert->bind_param('ii', $chatId, $tagId);
        $insert->execute();
    }

    $insert->close();
}

/**
 * Record chat read state.
 */
function cs_touch_chat_reader(mysqli $conn, int $chatId, int $userId): void
{
    $sql = 'INSERT INTO cs_chat_readers_tb (chat_id, user_id, last_read_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $chatId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Record message read state.
 */
function cs_touch_message_reader(mysqli $conn, int $messageId, int $userId): void
{
    $sql = 'INSERT INTO cs_message_reads_tb (message_id, user_id, read_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
