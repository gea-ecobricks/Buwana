<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

function build_login_url($base, array $params) {
    $delimiter = (strpos($base, '?') !== false) ? '&' : '?';
    return $base . $delimiter . http_build_query($params);
}
require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'signup-3';
$version = '0.732';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$earthling_emoji = '';

if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>
        alert('Looks like you’re already logged in! Redirecting to your dashboard...');
        window.location.href = '$redirect_url';
    </script>";
    exit();
}

// 🧩 Pull Buwana ID
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

// ✅ Check if signup is already completed
if (!is_null($earthling_emoji) && trim($earthling_emoji) !== '') {
    // Redirect because signup is already done
    $login_url = build_login_url($app_info['app_login_url'], [
        'lang' => $lang,
        'id'   => $buwana_id
    ]);
    echo "<script>
        alert('Whoops! Looks like you’ve already completed your signup. No need to return to this page! Please login to your " . htmlspecialchars($app_info['app_display_name']) . " account.');
        window.location.href = '$login_url';
    </script>";
    exit();
}

// Initialize
$first_name = '';
$credential_key = '';
$credential_type = '';
$generated_code = '';
$code_sent_flag = false;
$code_sent = false; // Track whether an email has been sent in this request

// 🔐 Generate activation code
function generateCode() {
    return strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

// 📬 Mailgun Sender
function sendVerificationCode($first_name, $credential_key, $code, $lang) {
    $client = new Client(['base_uri' => 'https://api.eu.mailgun.net/v3/']);
    $mailgunApiKey = getenv('MAILGUN_API_KEY');
    $mailgunDomain = 'mail.gobrik.com';

    $subject = "Your Verification Code";
    $html_body = "Hi $first_name,<br><br>Your verification code is: <b>$code</b><br><br>Enter this code to continue your registration.<br><br>— The Buwana Team";
    $text_body = "Hi $first_name, your verification code is: $code. Enter this code to continue your registration. — The Buwana Team";

    try {
        $response = $client->post("{$mailgunDomain}/messages", [
            'auth' => ['api', $mailgunApiKey],
            'form_params' => [
                'from' => 'Buwana Team <no-reply@mail.gobrik.com>',
                'to' => $credential_key,
                'subject' => $subject,
                'html' => $html_body,
                'text' => $text_body
            ]
        ]);
        return $response->getStatusCode() === 200;
    } catch (RequestException $e) {
        error_log("Mailgun error: " . $e->getMessage());
        return false;
    }
}

// 📭 SMTP Fallback
function backUpSMTPsender($first_name, $credential_key, $code) {
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
        $mail->addAddress($credential_key, $first_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Buwana Verification Code';
        $mail->Body = "Hello $first_name!<br><br>Your activation code is: <b>$code</b><br><br>Enter this code on the verification page.<br><br>The Buwana Team";
        $mail->AltBody = "Hello $first_name! Your activation code is: $code. Enter this code on the verification page.";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

// 🧠 PART 4: Get user info from Buwana DB
$sql = "SELECT u.first_name, c.credential_key, c.credential_type
        FROM users_tb u
        JOIN credentials_tb c ON u.buwana_id = c.buwana_id
        WHERE u.buwana_id = ?";
$stmt = $buwana_conn->prepare($sql);
$stmt->bind_param("i", $buwana_id);
$stmt->execute();
$stmt->bind_result($first_name, $credential_key, $credential_type);
$stmt->fetch();
$stmt->close();

if (!$credential_key || !$credential_type) {
    die("⚠️ Missing or invalid credential information.");
}

// PART 5: Generate and update activation code in credentials_tb
$generated_code = generateCode();

$update_sql = "UPDATE credentials_tb SET activation_code = ? WHERE buwana_id = ?";
$update_stmt = $buwana_conn->prepare($update_sql);
$update_stmt->bind_param("si", $generated_code, $buwana_id);
$update_stmt->execute();
$update_stmt->close();

// 📩 PART 6: Send verification code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['send_email']) || isset($_POST['resend_email']))) {

    if ($credential_type === 'e-mail' || $credential_type === 'email') {
        $code_sent = sendVerificationCode($first_name, $credential_key, $generated_code, $lang);

        if (!$code_sent) {
            $code_sent = backUpSMTPsender($first_name, $credential_key, $generated_code);
        }

        if ($code_sent) {
            $code_sent_flag = true;
        } else {
            echo '<script>alert("Verification email failed to send using both methods. Please try again later or contact support.");</script>';
        }
    } elseif ($credential_type === 'phone') {
        echo '<script>alert("📱 SMS verification is under construction. Please use an email address for now.");</script>';
    } else {
        echo '<script>alert("Unsupported credential type.");</script>';
    }
}


// Echo the HTML structure
echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">
<head>
<meta charset="UTF-8">
';


?>


<!--
Buwana EarthenAuth
Developed and made open source by the Global Ecobrick Alliance
See our git hub repository for the full code and to help out:
https://github.com/gea-ecobricks/buwana/-->


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<?php require_once ("../includes/signup-3-inc.php");?>


<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>

   <div id="top-page-image"
        class="top-page-image"
        data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
        data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
   </div>

<div id="form-submission-box" class="landing-page-form">
    <div class="form-container">

       <!-- Email confirmation form -->
<div id="first-send-form" style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;"
    class="<?php echo $code_sent ? 'hidden' : ''; ?>"> <!-- Fix the inline PHP inside attributes -->

    <h2><span data-lang-id="001-alright">Alright</span> <?php echo htmlspecialchars($first_name); ?>, <span data-lang-id="002-lets-confirm"> let's confirm your email.</span></h2>
    <p data-lang-id="003-to-create">To create your Buwana GoBrik account we need to confirm your <?php echo htmlspecialchars($credential_type); ?>. This is how we'll keep in touch and keep your account secure.  Click the send button and we'll send an account activation code to:</p>

    <h3><?php echo htmlspecialchars($credential_key); ?></h3>
    <form id="send-email-code" method="post" action="">


            <!-- Kick-Ass Submit Button -->
                     <div id="submit-section" class="submit-button-wrapper" >
                       <button type="submit" name="send_email" id="send_email" class="kick-ass-submit">
                         <span id="submit-button-text" data-lang-id="004-send-email-button">📨 Send Code ➡</span>
                         <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
                       </button>
                     </div>


        <!--
        <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
            <div id="submit-section" style="text-align:center;margin-top:20px;padding-right:15px;padding-left:15px" title="Start Activation process" data-lang-id="004-send-email-button">
                <input type="submit" name="send_email" id="send_email" value="📨 Send Code" class="submit-button activate">
            </div>
        </div>-->
    </form>
</div>

<!-- Code entry form -->
<div id="second-code-confirm" style="text-align:center;"
    class="<?php echo !$code_sent ? 'hidden' : ''; ?>"> <!-- Fix the inline PHP inside attributes -->

    <h2 data-lang-id="006-enter-code">Please enter your code:</h2>
    <p><span data-lang-id="007-check-email">Check your email</span> <?php echo htmlspecialchars($credential_key); ?> <span data-lang-id="008-for-your-code">for your account confirmation code. Enter it here:</span></p>

    <div class="form-item" id="code-form" style="text-align:center;">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">
        <input type="text" maxlength="1" class="code-box" required placeholder="-">

    <p id="code-feedback"></p>

    <p id="resend-code" style="font-size:1em"><span data-lang-id="009-no-code">Didn't get your code? You can request a resend of the code in</span> <span id="timer">1:00</span></p>
</div>


</div>


</div>

<?php if (!empty($buwana_id)) : ?>
<div id="browser-back-link" style="font-size: medium; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: 0px; ">
    <p style="font-size:1em;line-height: 1.9em;"><span data-lang-id="011-change-email">Need to change your email? </span><br><a href="#" onclick="browserBack(event)" data-lang-id="000-go-back">↩ Go back</a>
    </p>
<?php else : ?>
<div id="legacy-account-email-not-used" style="text-align:center;width:90%;margin:auto;margin-top:30px;margin-bottom:50px;">
    <p style="font-size:1em;line-height: 1.9em;" data-lang-id="010-email-no-longer">Do you no longer use this email address?<br>If not, you'll need to <a href="signup-1.php">create a new account</a> or contact our team at support@gobrik.com.</p>
</div>
<?php endif; ?>
</div>


</div>



</div> <!--Closes main-->


<!--FOOTER STARTS HERE-->
<?php require_once ("../footer-2025.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const staticCode = "AYYEW";
    const generatedCode = <?php echo json_encode($generated_code); ?>;

    const lang = '<?php echo $lang; ?>';
    let timeLeft = 60;
    const sendEmailForm = document.getElementById('send-email-code');
    const buwana_id = <?php echo json_encode($buwana_id); ?>;
    let codeSent = <?php echo json_encode($code_sent_flag ?? false); ?>;

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !codeSent) {
            e.preventDefault();
            sendEmailForm.submit();
        }
    });

    const messages = {
        en: { confirmed: "👍 Code confirmed!", incorrect: "😕 Code incorrect. Try again." },
        fr: { confirmed: "👍 Code confirmé!", incorrect: "😕 Code incorrect. Réessayez." },
        es: { confirmed: "👍 Código confirmado!", incorrect: "😕 Código incorrecto. Inténtalo de nuevo." },
        id: { confirmed: "👍 Kode dikonfirmasi!", incorrect: "😕 Kode salah. Coba lagi." },
        de: { confirmed: "👍 Code bestätigt!", incorrect: "😕 Code falsch. Bitte erneut versuchen." },
        zh: { confirmed: "👍 验证码确认成功！", incorrect: "😕 验证码错误，请重试。" },
        ar: { confirmed: "👍 تم تأكيد الرمز!", incorrect: "😕 الرمز غير صحيح. حاول مرة أخرى." }
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
                    window.location.href = "signup-3_process.php?id=" + buwana_id;
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
    if (codeSent) {
        document.getElementById('first-send-form').style.display = 'none';
        document.getElementById('second-code-confirm').style.display = 'block';
    }


});
</script>


<?php require_once ("../scripts/app_modals.php");?>


</body>
</html>