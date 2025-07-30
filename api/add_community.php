<?php
require_once '../buwanaconn_env.php';

header('Content-Type: application/json');

$allowed_origins = [
    'https://earthcal.app',
    'https://ecobricks.org',
    'https://gobrik.com',
    'https://learning.ecobricks.org',
    'https://openbooks.ecobricks.org',
    'https://earthen.io'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $com_name = trim($_POST['newCommunityName']);
    $com_type = trim($_POST['newCommunityType']);
    $country_id = intval($_POST['communityCountry']);
    $com_lang = trim($_POST['communityLanguage']); // This is the actual language_id (e.g. 'en', 'es')

    // Get the country name from countries_tb
    $sql_get_country = "SELECT country_name FROM countries_tb WHERE country_id = ?";
    $stmt_country = $buwana_conn->prepare($sql_get_country);
    $stmt_country->bind_param("i", $country_id);
    $stmt_country->execute();
    $stmt_country->bind_result($com_country);
    $stmt_country->fetch();
    $stmt_country->close();

    // Debug log
    error_log("🌍 Community Creation: name=$com_name, type=$com_type, country_id=$country_id, country=$com_country, language_id=$com_lang");

    // Validate inputs
    if (empty($com_name) || empty($com_type) || empty($com_country) || empty($com_lang)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }

    // Insert into communities_tb
    $sql_insert = "INSERT INTO communities_tb (com_name, com_country, com_type, com_lang, country_id, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt_insert = $buwana_conn->prepare($sql_insert);
    $stmt_insert->bind_param("sssii", $com_name, $com_country, $com_type, $com_lang, $country_id);

    if ($stmt_insert->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "✅ Community added successfully!",
            "community_name" => $com_name
        ]);
    } else {
        error_log("❌ MySQL insert error: " . $stmt_insert->error);
        echo json_encode(["success" => false, "message" => "Error adding community."]);
    }

    $stmt_insert->close();
    $buwana_conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
?>
