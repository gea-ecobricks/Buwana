<?php
require_once '../earthenAuth_helper.php';
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php';

header('Content-Type: application/json');

// CORS
$allowed_origins = ['https://ecobricks.org', 'https://earthcal.app', 'http://localhost', 'file://'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array(rtrim($origin, '/'), $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . rtrim($origin, '/'));
} elseif (empty($origin)) {
    header('Access-Control-Allow-Origin: *');
} else {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'CORS error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}
$data = json_decode(file_get_contents('php://input'), true);

// Required fields (except raw_json and cal_emoji)
$required_fields = [
    'buwana_id', 'cal_id', 'cal_name', 'cal_color', 'title', 'date', 'time', 'time_zone',
    'day', 'month', 'year', 'frequency', 'created_at', 'last_edited', 'synced',
    'unique_key', 'datecycle_color', 'date_emoji', 'pinned', 'comment', 'comments',
    'completed', 'public', 'delete_it', 'conflict'
];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

// Sanitize
$buwana_id       = (int) $data['buwana_id'];
$cal_id          = (int) $data['cal_id'];
$cal_name        = $cal_conn->real_escape_string($data['cal_name']);
$cal_color       = $cal_conn->real_escape_string($data['cal_color']);
$title           = $cal_conn->real_escape_string($data['title']);
$date            = $cal_conn->real_escape_string($data['date']);
$time            = $cal_conn->real_escape_string($data['time']);
$time_zone       = $cal_conn->real_escape_string($data['time_zone']);
$day             = (int) $data['day'];
$month           = (int) $data['month'];
$year            = (int) $data['year'];
$frequency       = $cal_conn->real_escape_string($data['frequency']);
$created_at      = $cal_conn->real_escape_string($data['created_at']);
$last_edited     = date('Y-m-d H:i:s', strtotime($data['last_edited'] ?? 'now'));
$synced          = (int) $data['synced'];
$unique_key      = $cal_conn->real_escape_string($data['unique_key']);
$datecycle_color = $cal_conn->real_escape_string($data['datecycle_color']);
$date_emoji      = $cal_conn->real_escape_string($data['date_emoji']);
$pinned          = (int) $data['pinned'];
$comment         = (int) $data['comment'];
$comments        = $cal_conn->real_escape_string($data['comments']);
$completed       = (int) $data['completed'];
$public          = (int) $data['public'];
$delete_it       = (int) $data['delete_it'];
$conflict        = (int) $data['conflict'];
$raw_json        = isset($data['raw_json']) ? $cal_conn->real_escape_string($data['raw_json']) : '';
$cal_emoji       = isset($data['cal_emoji']) ? $cal_conn->real_escape_string($data['cal_emoji']) : '';

try {
    $query = "
    INSERT INTO datecycles_tb (
        buwana_id, cal_id, cal_name, cal_color, title, date, time, time_zone,
        day, month, year, frequency, created_at, last_edited, synced, unique_key,
        datecycle_color, date_emoji, pinned, comment, comments, completed, public,
        delete_it, conflict, raw_json, cal_emoji
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        cal_id = VALUES(cal_id),
        cal_name = VALUES(cal_name),
        cal_color = VALUES(cal_color),
        title = VALUES(title),
        date = VALUES(date),
        time = VALUES(time),
        time_zone = VALUES(time_zone),
        day = VALUES(day),
        month = VALUES(month),
        year = VALUES(year),
        frequency = VALUES(frequency),
        last_edited = VALUES(last_edited),
        synced = VALUES(synced),
        datecycle_color = VALUES(datecycle_color),
        date_emoji = VALUES(date_emoji),
        pinned = VALUES(pinned),
        comment = VALUES(comment),
        comments = VALUES(comments),
        completed = VALUES(completed),
        public = VALUES(public),
        delete_it = VALUES(delete_it),
        conflict = VALUES(conflict),
        raw_json = VALUES(raw_json),
        cal_emoji = VALUES(cal_emoji)
    ";

    $stmt = $cal_conn->prepare($query);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . $cal_conn->error);

    $stmt->bind_param(
        'iissssssiiisssisssisisiiiss',
        $buwana_id, $cal_id, $cal_name, $cal_color, $title, $date, $time, $time_zone,
        $day, $month, $year, $frequency, $created_at, $last_edited, $synced, $unique_key,
        $datecycle_color, $date_emoji, $pinned, $comment, $comments, $completed, $public,
        $delete_it, $conflict, $raw_json, $cal_emoji
    );

    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'DateCycle upserted successfully.'
    ]);
} catch (Exception $e) {
    error_log("Upsert Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
