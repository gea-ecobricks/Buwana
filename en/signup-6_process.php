<?php
// ----------------------------------------
// 🌐 signup-6_process.php
// Final step of Buwana account creation
// ----------------------------------------

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../scripts/create_user.php';

// 🌿 Special handling for Learning Portal (Moodle SSO)
$app_id = $_SESSION['app_id'] ?? null;

if ($app_id === 'lear_a30d677a7b08') {
    // 🌻 Skip DB logic & redirect to Moodle Learning Portal
    header("Location: https://learning.ecobricks.org");
    exit();
}


// --- STEP 1: Validate and extract inputs ---
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

$selected_country_id  = $_POST['country_name'] ?? null; // this is the country_id
$selected_language_id = $_POST['language_id'] ?? '';
$earthling_emoji      = $_POST['earthling_emoji'] ?? '🌍';

// --- STEP 2: Load app info ---
$app_name     = $app_info['app_name'] ?? null;
$app_login_url = $app_info['app_login_url'] ?? '/';
$client_id    = $app_info['client_id'] ?? null;

if (!$app_name || !$client_id) {
    die("❌ Missing app configuration details.");
}

// --- STEP 3: Resolve continent_code using country_id ---
$set_continent_code = null;

$sql = "SELECT continent_code FROM countries_tb WHERE country_id = ? LIMIT 1";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $selected_country_id);
    $stmt->execute();
    $stmt->bind_result($set_continent_code);
    $stmt->fetch();
    $stmt->close();
}

// --- STEP 4: Update Buwana User Record ---
$update_sql = "
    UPDATE users_tb
    SET continent_code = ?,
        country_id = ?,
        language_id = ?,
        earthling_emoji = ?,
        open_id = CONCAT('buwana_', ?)
    WHERE buwana_id = ?
";
$stmt = $buwana_conn->prepare($update_sql);
$stmt->bind_param(
    'sissii',
    $set_continent_code,
    $selected_country_id,
    $selected_language_id,
    $earthling_emoji,
    $buwana_id,  // for CONCAT('buwana_', ?)
    $buwana_id   // for WHERE clause
);
$stmt->execute();
$stmt->close();

// --- STEP 5: Bypass full client provisioning if Learning Portal app ---
if ($app_name === 'lear_a30d677a7b08') {
    error_log("🌱 Skipping client provisioning for Learning Portal app ID: $app_name");

    // Optional: Maybe even notify user via session or toast here

    header("Location: signup-7.php?id=" . urlencode($buwana_id));
    exit();
}

// --- STEP 6: Load client connection ---
$client_env_path = "../config/{$app_name}_env.php";
if (!file_exists($client_env_path)) {
    die("❌ Missing DB config: $client_env_path");
}
require_once $client_env_path;

if (!isset($client_conn) || !($client_conn instanceof mysqli) || $client_conn->connect_error) {
    die("❌ Client DB connection could not be initialized.");
}

// --- STEP 8: Fetch user fields for provisioning ---
$userData = [];
$stmt = $buwana_conn->prepare("
    SELECT first_name, last_name, full_name, email, terms_of_service, profile_pic,
           country_id, language_id, continent_code, location_full, location_watershed,
           location_lat, location_long, community_id, earthling_emoji
    FROM users_tb
    WHERE buwana_id = ?
");
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result(
    $userData['first_name'], $userData['last_name'], $userData['full_name'], $userData['email'],
    $userData['terms_of_service'], $userData['profile_pic'], $userData['country_id'],
    $userData['language_id'], $userData['continent_code'], $userData['location_full'],
    $userData['location_watershed'], $userData['location_lat'], $userData['location_long'],
    $userData['community_id'], $userData['earthling_emoji']
);
$stmt->fetch();
$stmt->close();

// --- STEP 9: Create user in client app ---
$response = createUserInClientApp($buwana_id, $userData, $app_name, $client_conn, $buwana_conn, $client_id);

if ($response['success']) {
    header("Location: signup-7.php?id=" . urlencode($buwana_id));
    exit;
} else {
    die("❌ Failed to create user in client app.");
}
?>
