<?php
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php'; // EarthCal database connection

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0'); // Suppress in production

// ======= DEV MODE TOGGLE =======
define('DEVMODE', true); // Set to false on production servers
// ===============================

$allowed_origins = [
    'https://cal.earthen.io',
    'https://cycles.earthen.io',
    'https://ecobricks.org',
    'https://gobrik.com',
    'https://earthcal.app',
    'https://www.earthcal.app',
    'http://localhost:8080'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$origin = rtrim($origin, '/'); // Normalize

if (DEVMODE && empty($origin)) {
    // Local file:// fallback (e.g. file:// or dev server with no origin header)
    header('Access-Control-Allow-Origin: http://localhost:8080');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
} elseif (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
} else {
    error_log('CORS error: Invalid or missing HTTP_ORIGIN - ' . $origin);
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'CORS error: Invalid origin']);
    exit();
}

// ===== API Logic Below =====

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Invalid request method. Use POST.';
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$buwana_id = $input['buwana_id'] ?? null;

if (empty($buwana_id) || !is_numeric($buwana_id)) {
    $response['message'] = 'Invalid or missing Buwana ID.';
    echo json_encode($response);
    exit();
}

try {
    // 1. Fetch personal calendars (owned by the user)
    $sqlPersonal = "
        SELECT calendar_id, calendar_name, calendar_color, calendar_public, last_updated, created_at
        FROM calendars_tb
        WHERE buwana_id = ? AND deleted = 0
    ";
    $stmtPersonal = $cal_conn->prepare($sqlPersonal);
    if (!$stmtPersonal) {
        throw new Exception("Personal calendars SQL preparation failed: " . $cal_conn->error);
    }
    $stmtPersonal->bind_param("i", $buwana_id);
    $stmtPersonal->execute();
    $resultPersonal = $stmtPersonal->get_result();
    $personalCalendars = [];
    while ($row = $resultPersonal->fetch_assoc()) {
        $personalCalendars[] = $row;
    }
    $stmtPersonal->close();

    // 2. Fetch subscribed public calendars.
    // This returns calendars that are public and to which the user has subscribed.
    $sqlSubscribedPublic = "
        SELECT c.calendar_id, c.calendar_name, c.calendar_color, c.calendar_public, c.last_updated, c.created_at
        FROM cal_subscriptions_tb s
        JOIN calendars_tb c ON s.calendar_id = c.calendar_id
        WHERE s.buwana_id = ? AND c.deleted = 0 AND c.calendar_public = 1
    ";
    $stmtSubscribed = $cal_conn->prepare($sqlSubscribedPublic);
    if (!$stmtSubscribed) {
        throw new Exception("Subscribed calendars SQL preparation failed: " . $cal_conn->error);
    }
    $stmtSubscribed->bind_param("i", $buwana_id);
    $stmtSubscribed->execute();
    $resultSubscribed = $stmtSubscribed->get_result();
    $subscribedPublicCalendars = [];
    while ($row = $resultSubscribed->fetch_assoc()) {
        $subscribedPublicCalendars[] = $row;
    }
    $stmtSubscribed->close();

    // Merge personal calendars and subscribed public calendars.
    // If there are duplicates (i.e. a calendar that is both owned and subscribed),
    // you can deduplicate here if needed.
    $calendars = array_merge($personalCalendars, $subscribedPublicCalendars);

    $response['success'] = true;
    $response['buwana_id'] = $buwana_id;
    $response['calendars'] = $calendars;

} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
} finally {
    $cal_conn->close();
}

echo json_encode($response);
exit();
?>
