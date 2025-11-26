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

session_start();
require_once '../buwanaconn_env.php';

header('Content-Type: application/json');

$buwana_id = $_SESSION['buwana_id'] ?? $_POST['buwana_id'] ?? $_GET['buwana_id'] ?? null;

if (!$buwana_id || !is_numeric($buwana_id)) {
    echo json_encode(['logged_in' => false]);
    exit;
}

$stmt = $buwana_conn->prepare("SELECT first_name, earthling_emoji, continent_code, language_id, time_zone, email FROM users_tb WHERE buwana_id = ?");
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result($first_name, $earthling_emoji, $continent_code, $language_id, $time_zone, $email);
$stmt->fetch();
$stmt->close();

echo json_encode([
    'logged_in' => true,
    'buwana_id' => $buwana_id,
    'first_name' => $first_name,
    'earthling_emoji' => $earthling_emoji,
    'continent_code' => $continent_code,
    'language_id' => $language_id,
    'time_zone' => $time_zone,
    'email' => $email
]);
exit;
?>
