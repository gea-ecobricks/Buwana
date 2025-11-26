<?php
require_once '../earthenAuth_helper.php';
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php'; // Include EarthCal database connection

header('Content-Type: application/json');

// CORS configuration
$allowed_origins = [
    'https://ecobricks.org',
    'https://earthcal.app',
    'http://localhost',
    'file://'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim($_SERVER['HTTP_ORIGIN'], '/') : '';

if (empty($origin)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'CORS error: Invalid origin']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
    exit();
}

try {
    $inputData = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $requiredFields = ['name', 'color', 'public', 'created_at'];
    foreach ($requiredFields as $field) {
        if (!isset($inputData[$field]) || ($field === 'public' && !in_array($inputData[$field], [0, 1], true))) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => "Invalid or missing field: $field"]);
            exit();
        }
    }

    // Extract input data
    $name = htmlspecialchars($inputData['name']);
    $color = htmlspecialchars($inputData['color']);
    $public = intval($inputData['public']); // Map "public" from request to "calendar_public" in the database
    $createdAt = intval($inputData['created_at']); // Convert milliseconds timestamp to INT
    $buwanaId = isset($inputData['buwana_id']) && is_numeric($inputData['buwana_id']) ? intval($inputData['buwana_id']) : null;

    if (!$buwanaId) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Missing or invalid buwana_id.']);
        exit();
    }

    // Prepare SQL to insert the calendar
    $sql = "INSERT INTO calendars_tb (
                buwana_id, calendar_name, calendar_color, calendar_public, created_at
            ) VALUES (?, ?, ?, ?, ?)";

    $stmt = $cal_conn->prepare($sql);
    $stmt->bind_param("issii", $buwanaId, $name, $color, $public, $createdAt);

    // Execute the query
    if ($stmt->execute()) {
        // Retrieve the newly inserted calendar ID
        $calendarId = $stmt->insert_id;

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Calendar successfully created.',
            'calendar_id' => $calendarId,
            'created_at' => $createdAt // ✅ Return created_at for debugging
        ]);
    } else {
        throw new Exception("Failed to insert calendar into the database.");
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $stmt->close();
    $cal_conn->close();
}
?>
