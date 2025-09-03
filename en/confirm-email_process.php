<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../gobrikconn_env.php';
require_once '../fetch_app_info.php';
require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateCode() {
    return strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function sendVerificationCode($first_name, $email_addr, $code, $lang) {
    $client = new Client(['base_uri' => 'https://api.eu.mailgun.net/v3/']);
    $apiKey = getenv('MAILGUN_API_KEY');
    $domain = 'mail.gobrik.com';

    switch ($lang) {
        case 'fr':
            $subject = 'Code de vérification GoBrik';
            $html = "Bonjour $first_name!<br><br>Votre code d'activation est : <b>$code</b><br><br>Retournez à votre navigateur pour le saisir.<br><br>L'équipe GoBrik";
            $text = "Bonjour $first_name! Votre code d'activation est : $code. Retournez à votre navigateur pour le saisir. L'équipe GoBrik";
            break;
        case 'es':
            $subject = 'Código de verificación de GoBrik';
            $html = "Hola $first_name!<br><br>Tu código de activación es: <b>$code</b><br><br>Vuelve a tu navegador para ingresarlo.<br><br>El equipo de GoBrik";
            $text = "Hola $first_name! Tu código de activación es: $code. Vuelve a tu navegador para ingresarlo. El equipo de GoBrik";
            break;
        case 'id':
            $subject = 'Kode Verifikasi GoBrik';
            $html = "Halo $first_name!<br><br>Kode aktivasi Anda adalah: <b>$code</b><br><br>Kembali ke browser Anda untuk memasukkan kode.<br><br>Tim GoBrik";
            $text = "Halo $first_name! Kode aktivasi Anda adalah: $code. Kembali ke browser Anda untuk memasukkan kode. Tim GoBrik";
            break;
        case 'en':
        default:
            $subject = 'GoBrik Verification Code';
            $html = "Hello $first_name!<br><br>Your activation code is: <b>$code</b><br><br>Return to your browser and enter the code.<br><br>The GoBrik team";
            $text = "Hello $first_name! Your activation code is: $code. Return to your browser and enter the code. The GoBrik team";
            break;
    }

    try {
        $response = $client->post("{$domain}/messages", [
            'auth' => ['api', $apiKey],
            'form_params' => [
                'from' => 'GoBrik Team <no-reply@mail.gobrik.com>',
                'to' => $email_addr,
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
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

        $mail->setFrom('gobrik@ecobricks.org', 'GoBrik Backup Mailer');
        $mail->addAddress($email_addr, $first_name);

        $mail->isHTML(true);
        $mail->Subject = 'GoBrik Verification Code';
        $mail->Body = "Hello $first_name!<br><br>Your activation code is: <b>$code</b><br><br>Return to your browser and enter the code.<br><br>The GoBrik team";
        $mail->AltBody = "Hello $first_name! Your activation code is: $code. Return to your browser and enter the code. The GoBrik team";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ecobricker_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    $lang = $_POST['lang'] ?? 'en';
    if (!$ecobricker_id) {
        die('Missing ecobricker ID');
    }

    $first_name = $email_addr = '';
    $buwana_id = null;

    $sql = "SELECT first_name, email_addr, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
    $stmt = $gobrik_conn->prepare($sql);
    $stmt->bind_param('i', $ecobricker_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $email_addr, $buwana_id);
    $stmt->fetch();
    $stmt->close();

    if (!$email_addr) {
        die('Email not found');
    }

    $generated_code = generateCode();
    $created_at = $last_login = date('Y-m-d H:i:s');

    if (!$buwana_id) {
        $account_status = 'legacy account activated';
        $role = 'ecobricker';
        $notes = 'Legacy activation complete but password not yet reset';

        $sql_user = "INSERT INTO users_tb (first_name, full_name, created_at, last_login, account_status, role, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_user = $buwana_conn->prepare($sql_user);
        $full_name = $first_name;
        $stmt_user->bind_param('sssssss', $first_name, $full_name, $created_at, $last_login, $account_status, $role, $notes);
        $stmt_user->execute();
        $buwana_id = $buwana_conn->insert_id;
        $stmt_user->close();

        $client_id = $_SESSION['client_id'] ?? $default_client_id;
        $sql_connect = "INSERT INTO user_app_connections_tb (buwana_id, client_id) VALUES (?, ?)";
        $stmt_connect = $buwana_conn->prepare($sql_connect);
        $stmt_connect->bind_param('is', $buwana_id, $client_id);
        $stmt_connect->execute();
        $stmt_connect->close();
    }

    $sql_check = "SELECT credential_id FROM credentials_tb WHERE buwana_id = ? AND credential_type IN ('email','e-mail')";
    $stmt_check = $buwana_conn->prepare($sql_check);
    $stmt_check->bind_param('i', $buwana_id);
    $stmt_check->execute();
    $stmt_check->bind_result($credential_id);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($credential_id) {
        $sql_update = "UPDATE credentials_tb SET credential_key = ?, activation_code = ?, last_login = ? WHERE credential_id = ?";
        $stmt_update = $buwana_conn->prepare($sql_update);
        $stmt_update->bind_param('sssi', $email_addr, $generated_code, $last_login, $credential_id);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $sql_cred = "INSERT INTO credentials_tb (buwana_id, credential_type, credential_key, activation_code, times_used, failed_password_count, last_login) VALUES (?, 'email', ?, ?, 0, 0, ?)";
        $stmt_cred = $buwana_conn->prepare($sql_cred);
        $stmt_cred->bind_param('isss', $buwana_id, $email_addr, $generated_code, $last_login);
        $stmt_cred->execute();
        $stmt_cred->close();
    }

    $sql_update_gobrik = "UPDATE tb_ecobrickers SET buwana_id = ? WHERE ecobricker_id = ?";
    $stmt_update_gobrik = $gobrik_conn->prepare($sql_update_gobrik);
    $stmt_update_gobrik->bind_param('ii', $buwana_id, $ecobricker_id);
    $stmt_update_gobrik->execute();
    $stmt_update_gobrik->close();

    $sent = sendVerificationCode($first_name, $email_addr, $generated_code, $lang);
    if (!$sent) {
        $sent = backUpSMTPsender($first_name, $email_addr, $generated_code);
    }

    header('Location: confirm-email.php?id=' . $ecobricker_id . '&sent=1');
    exit();
}
?>
