<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$response = ['success' => false];

require_once '../buwanaconn_env.php';

function sendJsonError($error) {
    error_log("Signup-2 Error: $error");
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $error]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $buwana_id = $_GET['id'] ?? null;

    if (empty($buwana_id) || !is_numeric($buwana_id)) {
        sendJsonError('invalid_buwana_id');
    }

    // 🧼 Sanitize inputs
    $credential_value = filter_var(trim($_POST['credential_value']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password_hash'] ?? '';

    if (!$credential_value) sendJsonError('invalid_email');
    if (strlen($password) < 6) sendJsonError('invalid_password');

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // 🧠 Anti-bot fields
    $signup_timetaken = isset($_POST['fillout_duration']) ? (int)$_POST['fillout_duration'] : 999;
    $honeypot_field = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $honeypotted = (!empty($honeypot_field)) ? 1 : 0;
    $js_enabled_catch = (isset($_POST['js_enabled']) && $_POST['js_enabled'] === 'true') ? 1 : 0;

    // 🤖 Simple Bot Scoring
    $bot_score = 0;
    if ($signup_timetaken !== null) {
        if ($signup_timetaken < 5) $bot_score += 50;
        elseif ($signup_timetaken < 10) $bot_score += 20;
    }
    if ($honeypotted) $bot_score += 40;
    if (!$js_enabled_catch) $bot_score += 20;
    $bot_score = min($bot_score, 100);

    if ($bot_score > 70) {
        error_log("🚨 High bot score signup detected: Buwana ID $buwana_id with score $bot_score");
    }

    // 🛠️ Fetch user's first name and account status
    $stmt = $buwana_conn->prepare("SELECT first_name, account_status FROM users_tb WHERE buwana_id = ?");
    if (!$stmt) sendJsonError('db_error_first_name');
    $stmt->bind_param("i", $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $account_status);
    $stmt->fetch();
    $stmt->close();

    if (empty($first_name)) sendJsonError('missing_first_name');

    // 📧 Check if email already used
    $stmt = $buwana_conn->prepare("SELECT COUNT(*), buwana_id FROM users_tb WHERE email = ?");
    if (!$stmt) sendJsonError('db_error_check_email');
    $stmt->bind_param("s", $credential_value);
    $stmt->execute();
    $stmt->bind_result($count, $existing_buwana_id);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0 && $existing_buwana_id != $buwana_id) {
        sendJsonError('duplicate_email');
    }

    // 🔥 Update users_tb
    $stmt = $buwana_conn->prepare("UPDATE users_tb
        SET email = ?,
            password_hash = ?,
            account_status = 'signup-2_process run. Email unverified',
            last_login = NOW(),
            signup_timetaken = ?,
            honeypotted = ?,
            js_enabled_catch = ?,
            bot_score = ?
        WHERE buwana_id = ?");
    if (!$stmt) sendJsonError('db_error_user_update');

    $stmt->bind_param("ssiiiii", $credential_value, $password_hash, $signup_timetaken, $honeypotted, $js_enabled_catch, $bot_score, $buwana_id);

    if (!$stmt->execute()) sendJsonError('user_update_failed');
    $stmt->close();


    // 🔑 Update credentials_tb
    $stmt = $buwana_conn->prepare("UPDATE credentials_tb SET credential_key = ?, credential_type = 'email' WHERE buwana_id = ?");
    if (!$stmt) sendJsonError('db_error_credentials');
    $stmt->bind_param("si", $credential_value, $buwana_id);
    $stmt->execute();
    $stmt->close();

    // 🎉 Success
    $response['success'] = true;
    if ($account_status === 'legacy account activated') {
        $response['redirect'] = "signup-4.php?id=" . urlencode($buwana_id);
    } else {
        $response['redirect'] = "signup-3.php?id=" . urlencode($buwana_id);
    }
}

ob_end_clean();
echo json_encode($response);
exit();
?>
