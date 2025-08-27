<?php
//<!-- For legacy gobrik email confirmation-->

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../earthenAuth_helper.php'; // Include the authentication helper functions

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


$is_logged_in = false; // Ensure not logged in for this page

$response = ['success' => false];
$ecobricker_id = $_GET['id'] ?? null;

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.4';
$page = 'activate';
$version = '0.776';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

// Already logged in?
if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>
        alert('Looks like you’re already logged in! Redirecting to your dashboard...');
        window.location.href = '$redirect_url';
    </script>";
    exit();
}


// PART 2: Check if ecobricker_id is passed in the URL
if (is_null($ecobricker_id)) {
    echo '<script>
        alert("Hmm... something went wrong. No ecobricker ID was passed along. Please try logging in again. If this problem persists, you\'ll need to create a new account.");
        window.location.href = "login.php";
    </script>';
    exit();
}

// Initialize user variables
$ecobricker_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$first_name = '';
$email_addr = '';
$code_sent = false;
$static_code = 'AYYEW';
$generated_code = '';
$country_icon = '';
$buwana_id = '';

// PART 2: FUNCTIONS

// Function to generate a random 5-character alphanumeric code
function generateCode() {
    return strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

// Function to send the verification code email using Mailgun API
function sendVerificationCode($first_name, $email_addr, $verification_code, $lang) {
    // Set up the Mailgun API client
    $client = new Client(['base_uri' => 'https://api.eu.mailgun.net/v3/']); // EU endpoint for Mailgun
    $mailgunApiKey = getenv('MAILGUN_API_KEY'); // Get Mailgun API key from environment
    $mailgunDomain = 'mail.gobrik.com'; // Set Mailgun domain

    // Determine the email content based on the language
    switch ($lang) {
        case 'fr':
            $subject = 'Code de vérification GoBrik';
            $html_body = "Bonjour $first_name!<br><br>Si vous lisez ceci, un code d'activation pour votre compte GoBrik et Buwana a été demandé ! Le code pour activer votre compte est :<br><br><b>$verification_code</b><br><br>Retournez à votre navigateur et entrez le code.<br><br>L'équipe GoBrik";
            $text_body = "Bonjour $first_name! Si vous lisez ceci, un code d'activation pour votre compte GoBrik et Buwana a été demandé ! Le code pour activer votre compte est : $verification_code. Retournez à votre navigateur et entrez le code. L'équipe GoBrik";
            break;
        case 'es':
            $subject = 'Código de verificación de GoBrik';
            $html_body = "Hola $first_name!<br><br>¡Si estás leyendo esto, se ha solicitado un código de activación para tu cuenta de GoBrik y Buwana! El código para activar tu cuenta es:<br><br><b>$verification_code</b><br><br>Vuelve a tu navegador e ingresa el código.<br><br>El equipo de GoBrik";
            $text_body = "Hola $first_name! Si estás leyendo esto, se ha solicitado un código de activación para tu cuenta de GoBrik y Buwana! El código para activar tu cuenta es: $verification_code. Vuelve a tu navegador e ingresa el código. El equipo de GoBrik";
            break;
        case 'id':
            $subject = 'Kode Verifikasi GoBrik';
            $html_body = "Halo $first_name!<br><br>Jika Anda membaca ini, kode aktivasi untuk akun GoBrik dan Buwana Anda telah diminta! Kode untuk mengaktifkan akun Anda adalah:<br><br><b>$verification_code</b><br><br>Kembali ke browser Anda dan masukkan kodenya.<br><br>Tim GoBrik";
            $text_body = "Halo $first_name! Jika Anda membaca ini, kode aktivasi untuk akun GoBrik dan Buwana Anda telah diminta! Kode untuk mengaktifkan akun Anda adalah: $verification_code. Kembali ke browser Anda dan masukkan kodenya. Tim GoBrik";
            break;
        case 'en':
        default:
            $subject = 'GoBrik Verification Code';
            $html_body = "Hello $first_name!<br><br>If you are reading this, an activation code for your GoBrik and Buwana account has been requested! The code to activate your account is:<br><br><b>$verification_code</b><br><br>Return back to your browser and enter the code.<br><br>The GoBrik team";
            $text_body = "Hello $first_name! If you're reading this, an activation code for your GoBrik and Buwana account has been requested! The code to activate your account is: $verification_code. Return back to your browser and enter the code. The GoBrik team";
            break;
    }

    try {
        // Send the email using Mailgun's API
        $response = $client->post("{$mailgunDomain}/messages", [
            'auth' => ['api', $mailgunApiKey],
            'form_params' => [
                'from' => 'GoBrik Team <no-reply@mail.gobrik.com>', // Verified domain email
                'to' => $email_addr,
                'subject' => $subject,
                'html' => $html_body,
                'text' => $text_body, // Plain text fallback
            ]
        ]);

        // Check response status
        if ($response->getStatusCode() == 200) {
            error_log("Mailgun: Verification email sent successfully to $email_addr");
            return true;
        } else {
            error_log("Mailgun: Failed to send verification email. Status: " . $response->getStatusCode());
            return false;
        }

    } catch (RequestException $e) {
        error_log("Mailgun API Exception: " . $e->getMessage());
        return false;
    }
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function backUpSMTPsender($first_name, $email_addr, $verification_code, $lang) {
    $mail = new PHPMailer(true);

    try {
        // Enable SMTP debug output to logs (for development)
        $mail->SMTPDebug = 2; // 0 = off, 2 = verbose
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };

        // Server settings
        $mail->isSMTP();
$mail->Host = getenv('SMTP_HOST');
$mail->SMTPAuth = true;
$mail->Username = getenv('SMTP_USERNAME');
$mail->Password = getenv('SMTP_PASSWORD');
$mail->Port = getenv('SMTP_PORT');

// Disable encryption
$mail->SMTPSecure = false;
$mail->SMTPAutoTLS = false;

        // Log basic SMTP config
        error_log("SMTP fallback: Trying to send email using:");
        error_log("Host: " . $mail->Host);
        error_log("Port: " . $mail->Port);
        error_log("Username: " . $mail->Username);
        error_log("SMTPAuth: " . ($mail->SMTPAuth ? "true" : "false"));

        // Sender & recipient
        $mail->setFrom('gobrik@ecobricks.org', 'GoBrik Backup Mailer');
        $mail->addAddress($email_addr, $first_name);

        // Language-specific content
        switch ($lang) {
            case 'fr':
                $subject = 'Code de vérification GoBrik';
                $html_body = "Bonjour $first_name!<br><br>Votre code d'activation est : <b>$verification_code</b><br><br>Retournez à votre navigateur pour le saisir.<br><br>L'équipe GoBrik";
                $text_body = "Bonjour $first_name! Votre code d'activation est : $verification_code. Retournez à votre navigateur pour le saisir. L'équipe GoBrik";
                break;
            case 'es':
                $subject = 'Código de verificación de GoBrik';
                $html_body = "Hola $first_name!<br><br>Tu código de activación es: <b>$verification_code</b><br><br>Vuelve a tu navegador para ingresarlo.<br><br>El equipo de GoBrik";
                $text_body = "Hola $first_name! Tu código de activación es: $verification_code. Vuelve a tu navegador para ingresarlo. El equipo de GoBrik";
                break;
            case 'id':
                $subject = 'Kode Verifikasi GoBrik';
                $html_body = "Halo $first_name!<br><br>Kode aktivasi Anda adalah: <b>$verification_code</b><br><br>Kembali ke browser Anda untuk memasukkan kode.<br><br>Tim GoBrik";
                $text_body = "Halo $first_name! Kode aktivasi Anda adalah: $verification_code. Kembali ke browser Anda untuk memasukkan kode. Tim GoBrik";
                break;
            case 'en':
            default:
                $subject = 'GoBrik Verification Code';
                $html_body = "Hello $first_name!<br><br>Your activation code is: <b>$verification_code</b><br><br>Return to your browser and enter the code.<br><br>The GoBrik team";
                $text_body = "Hello $first_name! Your activation code is: $verification_code. Return to your browser and enter the code. The GoBrik team";
                break;
        }

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body;

        // Add timeouts and safety
        $mail->Timeout = 10;
        $mail->SMTPConnectTimeout = 10;
        $mail->SMTPKeepAlive = false;

        $mail->send();
        error_log("✅ SMTP: Fallback verification email sent successfully to $email_addr");
        return true;

    } catch (\Throwable $e) {
        error_log("🚨 PHPMailer Throwable Exception: " . $e->getMessage());
        error_log("❌ PHPMailer ErrorInfo: " . $mail->ErrorInfo);
        return false;
    }
}






// PART 4: Look up user information using ecobricker_id provided in URL
require_once("../gobrikconn_env.php");

$sql_user_info = "SELECT first_name, email_addr, gobrik_migrated, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
$stmt_user_info = $gobrik_conn->prepare($sql_user_info);
if ($stmt_user_info) {
    $stmt_user_info->bind_param('i', $ecobricker_id);
    $stmt_user_info->execute();
    $stmt_user_info->bind_result($first_name, $email_addr, $gobrik_migrated, $buwana_id);
    $stmt_user_info->fetch();
    $stmt_user_info->close();
} else {
    die('Error preparing statement for fetching user info: ' . $gobrik_conn->error);
}

// Check if buwana_id is empty and handle accordingly (if needed)
if (empty($buwana_id)) {
    // Handle the case where buwana_id is null or empty
    $buwana_id = null; // You can choose to set it to null or any default value if needed
}


// PART 5: Generate the code and update the activation_code field in the database
$generated_code = generateCode();

$sql_update_code = "UPDATE tb_ecobrickers SET activation_code = ? WHERE ecobricker_id = ?";
$stmt_update_code = $gobrik_conn->prepare($sql_update_code);
if ($stmt_update_code) {
    $stmt_update_code->bind_param('si', $generated_code, $ecobricker_id);
    $stmt_update_code->execute();
    $stmt_update_code->close();
} else {
    die('Error preparing statement for updating activation code: ' . $gobrik_conn->error);
}


// PART 6: Handle form submission to send the confirmation code by email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_email']) || isset($_POST['resend_email']))) {
    $code_sent = sendVerificationCode($first_name, $email_addr, $generated_code, $lang);

    if (!$code_sent) {
        // Try backup SMTP method
        $code_sent = backUpSMTPsender($first_name, $email_addr, $generated_code, $lang);
    }

if ($code_sent) {
    $code_sent_flag = true;
} else {
    echo '<script>alert("We tried both our main and backup servers, but your verification email could not be sent. Please try again later or contact support.");</script>';
    error_log("❌ Final email attempt failed.");
}
}


$gobrik_conn->close();

?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<title>Confirm Your Email</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!--
GoBrik.com site version 3.0
Developed and made open source by the Global Ecobrick Alliance
See our git hub repository for the full code and to help out:
https://github.com/gea-ecobricks/gobrik-3.0/tree/main/en-->


<?php require_once ("../includes/activate-inc.php");?>


<!-- PAGE CONTENT -->
   <?php
   $page_key = 'signup_3';
   ?>

<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">

            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

       <!-- Email confirmation form -->
<div id="first-send-form" style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;"
    class="<?php echo $code_sent ? 'hidden' : ''; ?>"> <!-- Fix the inline PHP inside attributes -->

    <h2><span data-lang-id="001-alright">Alright</span> <?php echo htmlspecialchars($first_name); ?>, <span data-lang-id="002-lets-confirm"> let's confirm your email.</span></h2>
    <p data-lang-id="003-to-create">To create your Buwana GoBrik account we need to confirm your chosen credential. This is how we'll keep in touch and keep your account secure.  Click the send button and we'll send an account activation code to:</p>

    <h3><?php echo htmlspecialchars($email_addr); ?></h3>
    <form id="send-email-code" method="post" action="">
        <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
            <div id="submit-section" style="text-align:center;margin-top:20px;padding-right:15px;padding-left:15px" title="Start Activation process" data-lang-id="004-send-email-button">
                <input type="submit" name="send_email" id="send_email" value="📨 Send Code" class="submit-button activate">
            </div>
        </div>
    </form>
</div>

<!-- Code entry form -->
<div id="second-code-confirm" style="text-align:center;"
    class="<?php echo !$code_sent ? 'hidden' : ''; ?>"> <!-- Fix the inline PHP inside attributes -->

    <h2 data-lang-id="006-enter-code">Please enter your code:</h2>
    <p><span data-lang-id="007-check-email">Check your email</span> <?php echo htmlspecialchars($email_addr); ?> <span data-lang-id="008-for-your-code">for your account confirmation code. Enter it here:</span></p>

    <div class="form-item" id="code-form" style="text-align:center;">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
    </form>

    <p id="code-feedback"></p>

    <p id="resend-code" style="font-size:1em"><span data-lang-id="009-no-code">Didn't get your code? You can request a resend of the code in</span> <span id="timer">1:00</span></p>
</div>




            <?php if (!empty($buwana_id)) : ?>
            <div id="new-account-another-email-please" style="text-align:center;width:90%;margin:auto;margin-top:30px;margin-bottom:30px;">
                <p style="font-size:1em;"><span data-lang-id="011-change-email">Want to change your email? </span>  <a href="signup-2.php?id=<?php echo htmlspecialchars($buwana_id); ?>"><span data-lang-id="012-go-back-new-email"> Go back to enter a different email address.</span></a>
                </p>
            </div>
            <?php else : ?>
            <div id="legacy-account-email-not-used" style="text-align:center;width:90%;margin:auto;margin-top:30px;margin-bottom:50px;">
                <p style="font-size:1em;" data-lang-id="010-email-no-longer">Do you no longer use this email address?<br>If not, you'll need to <a href="signup.php">create a new account</a> or contact our team at support@gobrik.com.</p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</div> <!--Closes main-->


<!--FOOTER STARTS HERE-->
<?php require_once ("../footer-2025.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const staticCode = "AYYEW";
    const generatedCode = <?php echo json_encode($generated_code); ?>;
    const ecobricker_id = <?php echo json_encode($ecobricker_id); ?>;
    const lang = '<?php echo $lang; ?>';
    let timeLeft = 60;
    const sendEmailForm = document.getElementById('send-email-code');
    const buwana_id = <?php echo json_encode($buwana_id); ?>;

    const messages = {
        en: { confirmed: "👍 Code confirmed!", incorrect: "😕 Code incorrect. Try again." },
        fr: { confirmed: "👍 Code confirmé!", incorrect: "😕 Code incorrect. Réessayez." },
        es: { confirmed: "👍 Código confirmado!", incorrect: "Código incorrecto. Inténtalo de nuevo." },
        id: { confirmed: "👍 Kode dikonfirmasi!", incorrect: "😕 Kode salah. Coba lagi." }
    };

    const feedbackMessages = messages[lang] || messages.en;
    const codeFeedback = document.querySelector('#code-feedback');
    const codeBoxes = document.querySelectorAll('.code-box');

    function checkCode() {
        let enteredCode = '';
        codeBoxes.forEach(box => enteredCode += box.value.toUpperCase());

        if (enteredCode.length === 5) {
            if (enteredCode === staticCode || enteredCode === generatedCode) {
                codeFeedback.textContent = feedbackMessages.confirmed;
                codeFeedback.classList.add('success');
                codeFeedback.classList.remove('error');
                document.getElementById('resend-code').style.display = 'none';

                setTimeout(function() {
                    window.location.href = "activate_process.php?id=" + ecobricker_id + "&buwana_id=" + buwana_id;
                }, 300);
            } else {
                codeFeedback.textContent = feedbackMessages.incorrect;
                codeFeedback.classList.add('error');
                codeFeedback.classList.remove('success');
                shakeElement(document.getElementById('code-form'));

            }
        }
    }


    codeBoxes.forEach((box, index) => {
        box.addEventListener('keyup', function(e) {
            if (box.value.length === 1 && index < codeBoxes.length - 1) {
                codeBoxes[index + 1].focus();
            }
            checkCode();
        });

        if (index === 0) {
            box.addEventListener('paste', function(e) {
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');

                if (pastedText.length === 5) {
                    e.preventDefault();
                    codeBoxes.forEach((box, i) => box.value = pastedText[i] || '');
                    codeBoxes[codeBoxes.length - 1].focus();
                    checkCode();
                }
            });
        }

        // Add keydown event to handle backspacing
        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && box.value === '' && index > 0) {
                codeBoxes[index - 1].focus(); // Move to the previous box
            }
        });
    });






    // Handle the resend code timer
    let countdownTimer = setInterval(function() {
        timeLeft--;
        if (timeLeft <= 0) {
            clearInterval(countdownTimer);
            document.getElementById('resend-code').innerHTML = '<a href="#" id="resend-link">Resend the code now.</a>';

            // Add click event to trigger form submission
            document.getElementById('resend-link').addEventListener('click', function(event) {
                event.preventDefault(); // Prevent default anchor behavior
                sendEmailForm.submit(); // Submit the form programmatically
            });
        } else {
            document.getElementById('timer').textContent = '0:' + (timeLeft < 10 ? '0' : '') + timeLeft;
        }
    }, 1000);



    // Show/Hide Divs after email is sent
    var codeSent = <?php echo json_encode($code_sent_flag ?? false); ?>;  // Only set once
    if (codeSent) {
        document.getElementById('first-send-form').style.display = 'none';
        document.getElementById('second-code-confirm').style.display = 'block';
    }


});
</script>


</body>
</html>