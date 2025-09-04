<?php
// Legacy ecobricker email confirmation using modern process
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../gobrikconn_env.php';
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
$page = 'activate';
$version = '0.8';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>alert('Looks like you’re already logged in! Redirecting to your dashboard...');window.location.href='$redirect_url';</script>";
    exit();
}

$ecobricker_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ecobricker_id) {
    echo "<script>alert('Hmm... something went wrong. No ecobricker ID was passed along. Please try logging in again.');window.location.href='login.php';</script>";
    exit();
}

// Fetch legacy user info
$sql = "SELECT first_name, email_addr, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
$stmt = $gobrik_conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $ecobricker_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $email_addr, $buwana_id);
    $stmt->fetch();
    $stmt->close();
} else {
    die('Database error fetching user info.');
}
$gobrik_conn->close();

$static_code = 'AYYEW';
$page_key = 'signup_3';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
<meta charset="UTF-8">
<title>Confirm Your Email</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require_once("../includes/activate-inc.php"); ?>
</head>
<body>
<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">
            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

            <div id="first-send-form" style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                <h2><span data-lang-id="001-alright">Alright</span> <?php echo htmlspecialchars($first_name); ?>, <span data-lang-id="002-lets-confirm"> let's confirm your email.</span></h2>
                <p data-lang-id="003-to-create">To create your Buwana GoBrik account we need to confirm your chosen credential. This is how we'll keep in touch and keep your account secure.  Click the send button and we'll send an account activation code to:</p>
                <h3><?php echo htmlspecialchars($email_addr); ?></h3>
                <form id="send-email-code">
                    <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                        <div id="submit-section" style="text-align:center;margin-top:20px;padding-right:15px;padding-left:15px" title="Start Activation process" data-lang-id="004-send-email-button">
                            <input type="submit" name="send_email" id="send_email" value="📨 Send Code" class="submit-button activate">
                        </div>
                    </div>
                </form>
            </div>

            <div id="second-code-confirm" style="text-align:center;display:none;">
                <h2 data-lang-id="006-enter-code">Please enter your code:</h2>
                <p><span data-lang-id="007-check-email">Check your email</span> <?php echo htmlspecialchars($email_addr); ?> <span data-lang-id="008-for-your-code">for your account confirmation code. Enter it here:</span></p>
                <div class="form-item" id="code-form" style="text-align:center;">
                    <input type="text" maxlength="1" class="code-box" required placeholder="-">
                    <input type="text" maxlength="1" class="code-box" required placeholder="-">
                    <input type="text" maxlength="1" class="code-box" required placeholder="-">
                    <input type="text" maxlength="1" class="code-box" required placeholder="-">
                    <input type="text" maxlength="1" class="code-box" required placeholder="-">
                </div>
                <p id="code-feedback"></p>
                <p id="resend-code" style="font-size:1em; display:none;"><span data-lang-id="009-no-code">Didn't get your code? You can request a resend of the code in</span> <span id="timer">1:00</span></p>
            </div>

            <?php if (!empty($buwana_id)) : ?>
            <div id="new-account-another-email-please" style="text-align:center;width:90%;margin:auto;margin-top:30px;margin-bottom:30px;">
                <p style="font-size:1em;"><span data-lang-id="011-change-email">Want to change your email? </span>  <a href="signup-2.php?id=<?php echo htmlspecialchars($buwana_id); ?>"><span data-lang-id="012-go-back-new-email"> Go back to enter a different email address.</span></a></p>
            </div>
            <?php else : ?>
            <div id="legacy-account-email-not-used" style="text-align:center;width:90%;margin:auto;margin-top:30px;margin-bottom:50px;">
                <p style="font-size:1em;" data-lang-id="010-email-no-longer">Do you no longer use this email address?<br>If not, you'll need to <a href="signup.php">create a new account</a> or contact our team at support@gobrik.com.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once("../footer-2025.php"); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const staticCode = "AYYEW";
    let generatedCode = "";
    const ecobricker_id = <?php echo json_encode($ecobricker_id); ?>;
    let buwana_id = <?php echo json_encode($buwana_id); ?>;
    const lang = '<?php echo $lang; ?>';
    const emailAddr = <?php echo json_encode($email_addr); ?>;

    let timeLeft = 60;
    const sendEmailForm = document.getElementById('send-email-code');
    const codeFeedback = document.getElementById('code-feedback');
    const codeBoxes = document.querySelectorAll('.code-box');
    const resendPara = document.getElementById('resend-code');

    const messages = {
        en: { confirmed: "👍 Code confirmed!", incorrect: "😕 Code incorrect. Try again." },
        fr: { confirmed: "👍 Code confirmé!", incorrect: "😕 Code incorrect. Réessayez." },
        es: { confirmed: "👍 Código confirmado!", incorrect: "Código incorrecto. Inténtalo de nuevo." },
        id: { confirmed: "👍 Kode dikonfirmasi!", incorrect: "😕 Kode salah. Coba lagi." }
    };
    const feedbackMessages = messages[lang] || messages.en;

    function sendVerificationEmail() {
        fetch('confirm-email_process.php?id=' + ecobricker_id, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                generatedCode = data.code;
                buwana_id = data.buwana_id;
                document.getElementById('first-send-form').style.display = 'none';
                document.getElementById('second-code-confirm').style.display = 'block';
                startTimer();
            } else {
                alert('Verification email failed to send. Please try again later.');
            }
        })
        .catch(() => {
            alert('Verification email failed to send. Please try again later.');
        });
    }

    sendEmailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendVerificationEmail();
    });

    function startTimer() {
        timeLeft = 60;
        resendPara.style.display = 'block';
        const timerSpan = document.getElementById('timer');
        timerSpan.textContent = '1:00';
        const countdown = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                resendPara.innerHTML = '<a href="#" id="resend-link">Resend the code now.</a>';
                document.getElementById('resend-link').addEventListener('click', function(event) {
                    event.preventDefault();
                    sendVerificationEmail();
                });
            } else {
                timerSpan.textContent = '0:' + (timeLeft < 10 ? '0' : '') + timeLeft;
            }
        }, 1000);
    }

    function checkCode() {
        let enteredCode = '';
        codeBoxes.forEach(box => enteredCode += box.value.toUpperCase());
        if (enteredCode.length === 5) {
            if (enteredCode === staticCode || enteredCode === generatedCode) {
                codeFeedback.textContent = feedbackMessages.confirmed;
                codeFeedback.classList.add('success');
                codeFeedback.classList.remove('error');
                resendPara.style.display = 'none';
                setTimeout(function() {
                    window.location.href = 'signup-2.php?id=' + buwana_id + '&email=' + encodeURIComponent(emailAddr);
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

        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && box.value === '' && index > 0) {
                codeBoxes[index - 1].focus();
            }
        });
    });
});
</script>
</body>
</html>
