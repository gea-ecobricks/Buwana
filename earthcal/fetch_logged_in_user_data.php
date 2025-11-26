<?php
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
define('DEVMODE', true);

// Detect origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$origin = rtrim($origin, '/');

$allowed_origins = [
    'https://earthcal.app',
    'https://ecobricks.org',
    'https://earthcal.app',
    'https://www.earthcal.app',
    'https://gobrik.com',
    'http://localhost:8080'
];

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS policy with credentials
if (DEVMODE && empty($origin)) {
    header('Access-Control-Allow-Origin: http://localhost:8080');
    header('Access-Control-Allow-Credentials: true');
} elseif (empty($origin)) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: false'); // Note: cookies will not work
} elseif (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CORS error: Invalid origin']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 🔐 Set session cookie parameters for cross-origin
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // or your domain if applicable
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();


// Fetch session values
$buwana_id = $_SESSION['buwana_id'] ?? null;
$connection_id = $_SESSION['connection_id'] ?? null;

if (!$buwana_id || !is_numeric($buwana_id)) {
    echo json_encode(['logged_in' => false]);
    exit;
}

// Fetch user data
$stmt = $buwana_conn->prepare("SELECT first_name, earthling_emoji, continent_code, language_id, time_zone, email, last_login, location_full, location_lat, location_long FROM users_tb WHERE buwana_id = ?");
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result($first_name, $earthling_emoji, $continent_code, $language_id, $time_zone, $email, $last_login, $location_full, $location_lat, $location_long);
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
    'email' => $email,
    'last_login' => $last_login,
    'location_full' => $location_full,
    'location_lat' => $location_lat,
    'location_long' => $location_long,
    'connection_id' => $connection_id
]);
exit;
?>
