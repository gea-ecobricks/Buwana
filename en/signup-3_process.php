<?php
// ✅ Verifies the signup activation code server-side (POSTed by signup-3.php),
// then marks the email confirmed and checks for Earthen registration.
// Responds with JSON: {success, redirect} or {success: false, error}.

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // keep warnings out of the JSON response
session_start();

require_once("../buwanaconn_env.php");

header('Content-Type: application/json');

const SIGNUP_CODE_MAX_ATTEMPTS = 5;

function signup3_respond(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    signup3_respond(['success' => false, 'error' => 'invalid_method']);
}

$buwana_id = (int) ($_POST['buwana_id'] ?? 0);
$entered_code = strtoupper(trim((string) ($_POST['code'] ?? '')));

// Only the browser session that requested the code (signup-3.php send/resend)
// may try it, and only a few times per code.
$pending = $_SESSION['signup_code'] ?? null;
if (!$buwana_id || !is_array($pending) || (int) ($pending['buwana_id'] ?? 0) !== $buwana_id) {
    signup3_respond(['success' => false, 'error' => 'no_code_requested']);
}
if (($pending['attempts'] ?? 0) >= SIGNUP_CODE_MAX_ATTEMPTS) {
    signup3_respond(['success' => false, 'error' => 'too_many_attempts']);
}

$stored_code = null;
$stmt = $buwana_conn->prepare("SELECT activation_code FROM credentials_tb WHERE buwana_id = ? LIMIT 1");
if (!$stmt) {
    signup3_respond(['success' => false, 'error' => 'db_error']);
}
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result($stored_code);
$stmt->fetch();
$stmt->close();

// Optional tester code, set only in the server environment (e.g. SetEnv in .htaccess)
$test_code = strtoupper(trim((string) getenv('SIGNUP_TEST_CODE')));

$code_ok = $entered_code !== '' && (
    (!empty($stored_code) && hash_equals(strtoupper($stored_code), $entered_code)) ||
    ($test_code !== '' && hash_equals($test_code, $entered_code))
);

if (!$code_ok) {
    $_SESSION['signup_code']['attempts'] = ($pending['attempts'] ?? 0) + 1;
    $attempts_left = SIGNUP_CODE_MAX_ATTEMPTS - $_SESSION['signup_code']['attempts'];

    if ($attempts_left <= 0) {
        // Burn the code; the user has to request a new one.
        $stmt = $buwana_conn->prepare("UPDATE credentials_tb SET activation_code = NULL WHERE buwana_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $buwana_id);
            $stmt->execute();
            $stmt->close();
        }
        signup3_respond(['success' => false, 'error' => 'too_many_attempts']);
    }
    signup3_respond(['success' => false, 'error' => 'incorrect', 'attempts_left' => $attempts_left]);
}

// ✅ Code verified: make it single-use and remember the verification for the
// later signup steps (signup-5_process.php requires it).
$stmt = $buwana_conn->prepare("UPDATE credentials_tb SET activation_code = NULL WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $buwana_id);
    $stmt->execute();
    $stmt->close();
}
unset($_SESSION['signup_code']);
session_regenerate_id(true);
$_SESSION['signup_verified_buwana_id'] = $buwana_id;

$current_date_time = date('Y-m-d H:i:s');

// PART 1: Fetch user's email
$sql_fetch_email = "SELECT email FROM users_tb WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql_fetch_email);
if (!$stmt) {
    error_log("DB error fetching email: " . $buwana_conn->error);
    signup3_respond(['success' => false, 'error' => 'db_error']);
}
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

if (empty($email)) {
    signup3_respond(['success' => false, 'error' => 'email_not_found']);
}

// PART 2: Update credentials_tb (email confirmed)
$sql_update_credentials = "UPDATE credentials_tb SET email_confirm_dt = ? WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql_update_credentials);
if ($stmt) {
    $stmt->bind_param("si", $current_date_time, $buwana_id);
    $stmt->execute();
    $stmt->close();
} else {
    error_log("❌ DB error updating credentials_tb: " . $buwana_conn->error);
}

// ✅ PART 3: Update users_tb 'notes' with email confirmation
$note = "Step 3: User's email confirmed.";
$sql_update_notes = "UPDATE users_tb SET notes = ? WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql_update_notes);
if ($stmt) {
    $stmt->bind_param("si", $note, $buwana_id);
    $stmt->execute();
    $stmt->close();
} else {
    error_log("❌ DB error updating users_tb.notes: " . $buwana_conn->error);
}

// PART 4: Check if user is registered on Ghost (Earthen.io)
$email_encoded = urlencode($email);
$ghost_api_url = "https://earthen.io/ghost/api/v3/admin/members/?filter=email:$email_encoded";

// Same Ghost Admin key as the rest of Buwana (scripts/earthen_subscribe_functions.php)
$apiKey = (string) getenv('EARTHEN_KEY');
list($id, $secret) = array_pad(explode(':', $apiKey, 2), 2, '');

// Create JWT for Ghost API
$header = json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => $id]);
$now = time();
$payload = json_encode(['iat' => $now, 'exp' => $now + 300, 'aud' => '/v3/admin/']);

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$jwt = base64UrlEncode($header) . '.' . base64UrlEncode($payload);
$signature = hash_hmac('sha256', $jwt, hex2bin($secret), true);
$jwt .= '.' . base64UrlEncode($signature);

// cURL to Ghost API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ghost_api_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Ghost $jwt",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// A hung Ghost call used to outlive the MySQL connection ("server has gone away")
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// PART 5: Check if Ghost response was successful
$registered = 0;
if ($http_code >= 200 && $http_code < 300) {
    $data = json_decode($response, true);
    if (!empty($data['members'])) {
        $registered = 1;
    }
} else {
    error_log("❌ Ghost API failed: $http_code - $response");
}

// PART 6: Update Buwana 'credentials_tb' with Earthen status
$sql_earthen_update = "UPDATE credentials_tb SET earthen_registered = ? WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql_earthen_update);
if ($stmt) {
    $stmt->bind_param("ii", $registered, $buwana_id);
    $stmt->execute();
    $stmt->close();
} else {
    error_log("❌ DB error updating earthen_registered: " . $buwana_conn->error);
}

// ✅ All done! On to signup-4
signup3_respond(['success' => true, 'redirect' => 'signup-4.php?id=' . $buwana_id]);
