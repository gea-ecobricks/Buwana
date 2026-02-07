<?php
/**
 * ../processes/emailer.php
 *
 * Centralized email sending for Buwana pages.
 * - Mailgun primary (optional via USE_PRIMARY_EMAIL_SENDER)
 * - 1s hard timeout for Mailgun
 * - SMTP fallback via PHPMailer
 * - Minimal logging: success/failure + chosen method
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;

// ------------------------------------------------------------------
// Feature flag (default false if not defined by caller)
// Caller can define('USE_PRIMARY_EMAIL_SENDER', true/false) before include.
// ------------------------------------------------------------------
if (!defined('USE_PRIMARY_EMAIL_SENDER')) {
    define('USE_PRIMARY_EMAIL_SENDER', false);
}

/**
 * High-level helper specifically for verification-code emails.
 *
 * @param array $opts
 *   - first_name (string)
 *   - to_email (string)
 *   - code (string)
 *   - lang (string)
 *   - timeout (int|float)  Mailgun timeout seconds (default 1)
 * @return array
 *   - sent (bool)
 *   - method (string)  mailgun|smtp|none
 *   - error (string|null)
 */
function buwana_send_verification_email(array $opts): array {

    $first_name = (string)($opts['first_name'] ?? '');
    $to_email   = (string)($opts['to_email'] ?? '');
    $code       = (string)($opts['code'] ?? '');
    $lang       = (string)($opts['lang'] ?? 'en');
    $timeout    = (float)($opts['timeout'] ?? 1);

    if ($to_email === '' || $code === '') {
        return ['sent' => false, 'method' => 'none', 'error' => 'Missing to_email or code'];
    }

    // Attempt Mailgun (if enabled)
    if (USE_PRIMARY_EMAIL_SENDER === true) {
        $mg = buwana_mailgun_send_verification($first_name, $to_email, $code, $lang, $timeout);

        if (!empty($mg['sent'])) {
            return ['sent' => true, 'method' => 'mailgun', 'error' => null];
        }
        // Log minimal mailgun failure and fall through to SMTP
        error_log("emailer | mailgun failed | to={$to_email} | err=" . ($mg['error'] ?? 'unknown'));
    }

    // SMTP fallback
    $smtp = buwana_smtp_send_verification($first_name, $to_email, $code);

    if (!empty($smtp['sent'])) {
        return ['sent' => true, 'method' => 'smtp', 'error' => null];
    }

    error_log("emailer | smtp failed | to={$to_email} | err=" . ($smtp['error'] ?? 'unknown'));

    return [
        'sent'   => false,
        'method' => ($smtp['method'] ?? 'smtp'),
        'error'  => $smtp['error'] ?? 'Email send failed',
    ];
}

/**
 * Mailgun sender (verification code) with hard timeouts.
 */
function buwana_mailgun_send_verification(string $first_name, string $to_email, string $code, string $lang, float $timeout = 1): array {

    $mailgunApiKey = getenv('MAILGUN_API_KEY');
    $mailgunDomain = getenv('MAILGUN_DOMAIN') ?: 'mail.gobrik.com'; // allow override via env

    if (!$mailgunApiKey) {
        return ['sent' => false, 'method' => 'mailgun', 'error' => 'MAILGUN_API_KEY missing'];
    }

    $client = new Client([
        'base_uri'        => 'https://api.eu.mailgun.net/v3/',
        'timeout'         => $timeout,
        'connect_timeout' => $timeout,
        'http_errors'     => true
    ]);

    $subject   = "Your Verification Code";
    $html_body = "Hi {$first_name},<br><br>Your verification code is: <b>{$code}</b><br><br>Enter this code to continue your registration.<br><br>— The Buwana Team";
    $text_body = "Hi {$first_name}, your verification code is: {$code}. Enter this code to continue your registration. — The Buwana Team";

    try {
        $response = $client->post("{$mailgunDomain}/messages", [
            'auth' => ['api', $mailgunApiKey],
            'form_params' => [
                'from'    => 'Buwana Team <no-reply@' . $mailgunDomain . '>',
                'to'      => $to_email,
                'subject' => $subject,
                'html'    => $html_body,
                'text'    => $text_body,
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status === 200) {
            error_log("emailer | mailgun success | to={$to_email}");
            return ['sent' => true, 'method' => 'mailgun', 'error' => null];
        }

        return ['sent' => false, 'method' => 'mailgun', 'error' => "Unexpected status {$status}"];

    } catch (RequestException $e) {
        // Keep it short; don't dump full body unless you want it
        return ['sent' => false, 'method' => 'mailgun', 'error' => $e->getMessage()];
    } catch (\Throwable $e) {
        return ['sent' => false, 'method' => 'mailgun', 'error' => $e->getMessage()];
    }
}

/**
 * SMTP sender (verification code) via PHPMailer.
 */
function buwana_smtp_send_verification(string $first_name, string $to_email, string $code): array {

    $host   = (string)getenv('SMTP_HOST');
    $port   = (int)getenv('SMTP_PORT');
    $user   = (string)getenv('SMTP_USERNAME');
    $pass   = (string)getenv('SMTP_PASSWORD');
    $secure = strtolower((string)getenv('SMTP_SECURE')); // ssl|tls

    if ($host === '' || $port === 0 || $user === '' || $pass === '') {
        return ['sent' => false, 'method' => 'smtp', 'error' => 'Missing SMTP env vars (host/port/user/pass)'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->Port = $port;

        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // 465
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // 587
        } else {
            $mail->SMTPSecure = false;
        }

        $mail->SMTPAutoTLS = true;

        $mail->setFrom($user, 'Buwana Backup Mailer');
        $mail->addAddress($to_email, $first_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Buwana Verification Code';
        $mail->Body    = "Hello {$first_name}!<br><br>Your activation code is: <b>{$code}</b><br><br>Enter this code on the verification page.<br><br>The Buwana Team";
        $mail->AltBody = "Hello {$first_name}! Your activation code is: {$code}. Enter this code on the verification page.";

        $mail->send();

        error_log("emailer | smtp success | to={$to_email}");
        return ['sent' => true, 'method' => 'smtp', 'error' => null];

    } catch (\Throwable $e) {
        return ['sent' => false, 'method' => 'smtp', 'error' => $e->getMessage()];
    }
}
