<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
ob_start();

require_once '../buwanaconn_env.php';

$response = ['success' => false];

// 🛂 Validate inputs
$buwana_id = $_GET['id'] ?? null;
if (empty($buwana_id) || !is_numeric($buwana_id)) {
    echo json_encode(['success' => false, 'error' => 'invalid_buwana_id']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location_full = trim($_POST['location_full'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $watershed_select = trim($_POST['watershed_select'] ?? '');

    if (empty($location_full) || empty($latitude) || empty($longitude)) {
        echo json_encode(['success' => false, 'error' => 'missing_location_data']);
        exit();
    }

    // 🌎 Extract country from location_full
    $location_parts = explode(',', $location_full);
    $country_name = trim(end($location_parts));

    $sql_country = "SELECT country_id, continent_code FROM countries_tb WHERE country_name = ?";
    $stmt_country = $buwana_conn->prepare($sql_country);

    if ($stmt_country) {
        $stmt_country->bind_param('s', $country_name);
        $stmt_country->execute();
        $stmt_country->bind_result($country_id, $continent_code);
        $stmt_country->fetch();
        $stmt_country->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'db_error_country']);
        exit();
    }



//PART 2:  Get the user's timezone using their lattitude and longitude

// 🌍 Step 4.1: Determine time zone using GeoNames API
$geonames_username = 'ecobricks25';
$timezone_url = "https://secure.geonames.org/timezoneJSON?lat={$latitude}&lng={$longitude}&username={$geonames_username}";

$timezone_response = @file_get_contents($timezone_url);
$timezone_data = $timezone_response ? json_decode($timezone_response, true) : null;

$user_timezone = (isset($timezone_data['timezoneId']) && !empty($timezone_data['timezoneId']))
    ? $timezone_data['timezoneId']
    : 'Etc/GMT'; // fallback



$sql_update = "UPDATE users_tb SET
    continent_code = ?,
    country_id = ?,
    location_full = ?,
    location_lat = ?,
    location_long = ?,
    location_watershed = ?,
    time_zone = ?
    WHERE buwana_id = ?";

$stmt_update = $buwana_conn->prepare($sql_update);

error_log("Timezone result: $user_timezone");
error_log("Updating user ID: $buwana_id with tz: $user_timezone");

if ($stmt_update) {
    $stmt_update->bind_param(
        'sisddssi',
        $continent_code,
        $country_id,
        $location_full,
        $latitude,
        $longitude,
        $watershed_select,
        $user_timezone,
        $buwana_id
    );



        if ($stmt_update->execute()) {
            $stmt_update->close();
            header("Location: signup-5.php?id=" . urlencode($buwana_id));
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'db_update_failed']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'db_prepare_failed']);
        exit();
    }
}

ob_end_clean();
echo json_encode($response);
exit();
?>
