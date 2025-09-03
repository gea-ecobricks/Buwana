<?php
// For legacy gobrik email confirmation
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../gobrikconn_env.php';
require_once '../fetch_app_info.php';

$is_logged_in = false;

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'confirm-email';
$version = '0.8';
$lastModified = date('c');

if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>\n        alert('Looks like you’re already logged in! Redirecting to your dashboard...');\n        window.location.href = '$redirect_url';\n    </script>";
    exit();
}

$ecobricker_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ecobricker_id) {
    echo '<script>\n        alert("Hmm... something went wrong. No ecobricker ID was passed along. Please try logging in again. If this problem persists, you\'ll need to create a new account.");\n        window.location.href = "login.php";\n    </script>';
    exit();
}

$first_name = '';
$email_addr = '';
$buwana_id = null;
$generated_code = '';
$code_sent_flag = false;
$static_code = 'AYYEW';

$sql_user = "SELECT first_name, email_addr, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
$stmt_user = $gobrik_conn->prepare($sql_user);
if ($stmt_user) {
    $stmt_user->bind_param('i', $ecobricker_id);
    $stmt_user->execute();
    $stmt_user->bind_result($first_name, $email_addr, $buwana_id);
    $stmt_user->fetch();
    $stmt_user->close();
}

if (!empty($buwana_id)) {
    $sql_code = "SELECT activation_code FROM credentials_tb WHERE buwana_id = ? AND credential_type IN ('email','e-mail')";
    $stmt_code = $buwana_conn->prepare($sql_code);
    if ($stmt_code) {
        $stmt_code->bind_param('i', $buwana_id);
        $stmt_code->execute();
        $stmt_code->bind_result($generated_code);
        $stmt_code->fetch();
        $stmt_code->close();
        if (!empty($generated_code)) {
            $code_sent_flag = true;
        }
    }
}

$gobrik_conn->close();
$buwana_conn->close();
?>
<!DOCTYPE html>
<html lang="<?= $lang; ?>">
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

</head>
<body>
<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">

            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info['signup_3_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info['signup_3_top_img_dark']) ?>">
            </div>

       <!-- Email confirmation form -->
<div id="first-send-form" style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;"
    class="<?php echo $code_sent_flag ? 'hidden' : ''; ?>">

    <h2><span data-lang-id="001-alright">Alright</span> <?php echo htmlspecialchars($first_name); ?>, <span data-lang-id="002-lets-confirm"> let's confirm your email.</span></h2>
    <p data-lang-id="003-to-create">To create your Buwana GoBrik account we need to confirm your chosen credential. This is how we'll keep in touch and keep your account secure.  Click the send button and we'll send an account activation code to:</p>

    <h3><?php echo htmlspecialchars($email_addr); ?></h3>
    <form id="send-email-code" method="post" action="confirm-email_process.php?id=<?= htmlspecialchars($ecobricker_id); ?>">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($lang); ?>">
        <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
            <div id="submit-section" style="text-align:center;margin-top:20px;padding-right:15px;padding-left:15px" title="Start Activation process" data-lang-id="004-send-email-button">
                <input type="submit" name="send_email" id="send_email" value="📨 Send Code" class="submit-button activate">
            </div>
        </div>
    </form>
</div>

<!-- Code entry form -->
<div id="second-code-confirm" style="text-align:center;" class="<?php echo !$code_sent_flag ? 'hidden' : ''; ?>">

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
    const lang = '<?php echo $lang; ?>';
    let timeLeft = 60;
    const sendEmailForm = document.getElementById('send-email-code');
    const buwana_id = <?php echo json_encode($buwana_id); ?>;
    const emailAddr = <?php echo json_encode($email_addr); ?>;

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
                    window.location.href = "signup-2.php?id=" + buwana_id + "&email=" + encodeURIComponent(emailAddr);
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

    let countdownTimer = setInterval(function() {
        timeLeft--;
        if (timeLeft <= 0) {
            clearInterval(countdownTimer);
            document.getElementById('resend-code').innerHTML = '<a href="#" id="resend-link">Resend the code now.</a>';
            document.getElementById('resend-link').addEventListener('click', function(event) {
                event.preventDefault();
                sendEmailForm.submit();
            });
        } else {
            document.getElementById('timer').textContent = '0:' + (timeLeft < 10 ? '0' : '') + timeLeft;
        }
    }, 1000);

    var codeSent = <?php echo json_encode($code_sent_flag); ?>;
    if (codeSent) {
        document.getElementById('first-send-form').style.display = 'none';
        document.getElementById('second-code-confirm').style.display = 'block';
    }
});
</script>
</body>
</html>
