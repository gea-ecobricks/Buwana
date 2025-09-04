<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: application/json');

require_once '../buwanaconn_env.php';
require_once '../gobrikconn_env.php';
require_once '../fetch_app_info.php';
require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$ecobricker_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ecobricker_id) {
    echo json_encode(['success' => false, 'message' => 'missing_id']);
    exit();
}

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));

// Fetch legacy gobrik user info
$sql = "SELECT first_name, email_addr, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
$stmt = $gobrik_conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'db_error']);
    exit();
}
$stmt->bind_param('i', $ecobricker_id);
$stmt->execute();
$stmt->bind_result($first_name, $email_addr, $buwana_id);
$stmt->fetch();
$stmt->close();

if (empty($email_addr)) {
    echo json_encode(['success' => false, 'message' => 'missing_email']);
    exit();
}

$gobrik_conn->close();

$created = false;
$current_time = date('Y-m-d H:i:s');

if (empty($buwana_id)) {
    // Create basic buwana account
    $full_name = $first_name;
    $created_at = $current_time;
    $last_login = $current_time;
    $account_status = 'legacy account activated';
    $role = 'ecobricker';
    $notes = 'Legacy activation complete but password not yet reset';

    $sql_user = "INSERT INTO users_tb (first_name, full_name, created_at, last_login, account_status, role, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_user = $buwana_conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param('sssssss', $first_name, $full_name, $created_at, $last_login, $account_status, $role, $notes);
        if ($stmt_user->execute()) {
            $buwana_id = $buwana_conn->insert_id;
            $created = true;

            // Register app connection
            $client_id = $_SESSION['client_id'] ?? $default_client_id;
            $sql_connect = "INSERT INTO user_app_connections_tb (buwana_id, client_id) VALUES (?, ?)";
            $stmt_connect = $buwana_conn->prepare($sql_connect);
            if ($stmt_connect) {
                $stmt_connect->bind_param('is', $buwana_id, $client_id);
                $stmt_connect->execute();
                $stmt_connect->close();
            }

            // Insert credential record
            $sql_cred = "INSERT INTO credentials_tb (buwana_id, credential_type, credential_key, times_used, failed_password_count, last_login) VALUES (?, 'email', ?, 0, 0, ?)";
            $stmt_cred = $buwana_conn->prepare($sql_cred);
            if ($stmt_cred) {
                $stmt_cred->bind_param('iss', $buwana_id, $email_addr, $last_login);
                $stmt_cred->execute();
                $stmt_cred->close();
            }

            // Update gobrik account with buwana_id using existing credential variables
            $gobrik_conn2 = new mysqli($gobrik_servername, $gobrik_username, $gobrik_password, $gobrik_dbname);
            if (!$gobrik_conn2->connect_error) {
                $sql_update = "UPDATE tb_ecobrickers SET buwana_id = ? WHERE ecobricker_id = ?";
                $stmt_update = $gobrik_conn2->prepare($sql_update);
                if ($stmt_update) {
                    $stmt_update->bind_param('ii', $buwana_id, $ecobricker_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
                $gobrik_conn2->close();
            }
        }
        $stmt_user->close();
    }
}

// Generate activation code and update credentials
function generateCode() {
    return strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}
$generated_code = generateCode();
$update_sql = "UPDATE credentials_tb SET activation_code = ? WHERE buwana_id = ?";
$update_stmt = $buwana_conn->prepare($update_sql);
if ($update_stmt) {
    $update_stmt->bind_param('si', $generated_code, $buwana_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Send verification email via Mailgun
function sendVerificationCode($first_name, $email_addr, $code, $lang) {
    $client = new Client(['base_uri' => 'https://api.eu.mailgun.net/v3/']);
    $mailgunApiKey = getenv('MAILGUN_API_KEY');
    $mailgunDomain = 'mail.gobrik.com';
    $subject = 'Your Verification Code';
    $html_body = "Hi $first_name,<br><br>Your verification code is: <b>$code</b><br><br>Enter this code to continue your registration.<br><br>— The Buwana Team";
    $text_body = "Hi $first_name, your verification code is: $code. Enter this code to continue your registration. — The Buwana Team";
    try {
        $response = $client->post("{$mailgunDomain}/messages", [
            'auth' => ['api', $mailgunApiKey],
            'form_params' => [
                'from' => 'Buwana Team <no-reply@mail.gobrik.com>',
                'to' => $email_addr,
                'subject' => $subject,
                'html' => $html_body,
                'text' => $text_body
            ]
        ]);
        return $response->getStatusCode() === 200;
    } catch (RequestException $e) {
        error_log('Mailgun error: ' . $e->getMessage());
        return false;
    }
}

function backUpSMTPsender($first_name, $email_addr, $code) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');
        $mail->Port = getenv('SMTP_PORT');
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;

        $mail->setFrom('buwana@ecobricks.org', 'Buwana Backup Mailer');
        $mail->addAddress($email_addr, $first_name);
        $mail->isHTML(true);
        $mail->Subject = 'Your Buwana Verification Code';
        $mail->Body = "Hello $first_name!<br><br>Your activation code is: <b>$code</b><br><br>Enter this code on the verification page.<br><br>The Buwana Team";
        $mail->AltBody = "Hello $first_name! Your activation code is: $code. Enter this code on the verification page.";
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

$sent = sendVerificationCode($first_name, $email_addr, $generated_code, $lang);
if (!$sent) {
    $sent = backUpSMTPsender($first_name, $email_addr, $generated_code);
}

if (!$sent) {
    echo json_encode(['success' => false, 'message' => 'send_fail']);
    exit();
}

echo json_encode([
    'success' => true,
    'code' => $generated_code,
    'buwana_id' => $buwana_id,
    'email' => $email_addr
]);
exit();
?>
