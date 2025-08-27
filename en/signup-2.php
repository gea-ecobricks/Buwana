<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); // Needed for app context persistence

require_once '../buwanaconn_env.php';         // Sets up $buwana_conn
require_once '../fetch_app_info.php';         // Retrieves designated app's core data

function build_login_url($base, array $params) {
    $delimiter = (strpos($base, '?') !== false) ? '&' : '?';
    return $base . $delimiter . http_build_query($params);
}


// Set up page variables
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.7772';
$page = 'signup-2';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$is_logged_in = false; // Ensure not logged in for this page


// ✅ Direct session check instead of calling a function
if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? 'https://gobrik.com';
    echo "<script>
        alert('Looks like you already have an account and are logged in! Let\'s take you to your dashboard.');
        window.location.href = '$redirect_url';
    </script>";
    exit();
}

$response = ['success' => false];
$buwana_id = $_GET['id'] ?? null;

// Initialize user variables
$credential_type = '';
$credential_key = '';
$first_name = '';
$account_status = '';
$country_icon = '';
$earthling_emoji = '';


// Look up user information if buwana_id is provided
if ($buwana_id) {
    $sql_lookup_credential = "SELECT credential_type, credential_key FROM credentials_tb WHERE buwana_id = ?";
    $stmt_lookup_credential = $buwana_conn->prepare($sql_lookup_credential);
    if ($stmt_lookup_credential) {
        $stmt_lookup_credential->bind_param("i", $buwana_id);
        $stmt_lookup_credential->execute();
        $stmt_lookup_credential->bind_result($credential_type, $credential_key);
        $stmt_lookup_credential->fetch();
        $stmt_lookup_credential->close();
    } else {
        $response['error'] = 'db_error';
    }

$sql_lookup_user = "SELECT first_name, account_status, earthling_emoji FROM users_tb WHERE buwana_id = ?";
$stmt_lookup_user = $buwana_conn->prepare($sql_lookup_user);
if ($stmt_lookup_user) {
    $stmt_lookup_user->bind_param("i", $buwana_id);
    $stmt_lookup_user->execute();
    $stmt_lookup_user->bind_result($first_name, $account_status, $earthling_emoji);
    $stmt_lookup_user->fetch();
    $stmt_lookup_user->close();
} else {
    $response['error'] = 'db_error';
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



    $credential_type = htmlspecialchars($credential_type);
    $first_name = htmlspecialchars($first_name);

    if ($account_status !== 'name set only') {
        $response['error'] = 'account_status';
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

<?php require_once ("../includes/signup-2-inc.php");?>


<!--<div class="splash-title-block"></div>
    <div id="splash-bar"></div>-->

<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>

<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form" style="min-height:calc( 100vh - 54px)">
        <div class="form-container" style="margin:auto;">
            <div id="top-page-image"
                class="top-page-image"
                data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

            <div style="text-align:center;width:100%;margin:auto;">
                <h2><span data-lang-id="001-register-by"></span> <?php echo $credential_type; ?></h2>
                <p>Ok <?php echo $first_name; ?>! <span data-lang-id="002-now-lets-use"></span> <?= $app_info['app_display_name']; ?>...</p>
            </div>

           <form id="user-signup-form" method="post" action="signup-2_process.php?id=<?php echo htmlspecialchars($buwana_id); ?>">


                <div class="form-item" id="last-name-field">
                    <label for="last_name" data-lang-id="003b-last-name">Now what is your last name?</label><br>
                    <input type="text" id="last_name" name="last_name" placeholder="Your last name...">
                    <p class="form-caption" data-lang-id="011b-required" style="color:red">*This field is required.</p>
                </div>

                 <!-- Email / Credential Field -->
                 <div class="form-item float-label-group" id="credential-section">
                   <input type="text" id="credential_value" name="credential_value" required aria-label="Your email"
                   <?php if (!empty($credential_key)) : ?>
                       value="<?php echo htmlspecialchars($credential_key); ?>"
                   <?php endif; ?>
                   placeholder=" "  />  <!--style="padding-left:35px;"-->
                   <label for="credential_value">
                     <span data-lang-id="004-your-email-please">Your e-mail please...</span>
                   </label>

                   <div id="duplicate-email-error" class="form-field-error" data-lang-id="005-duplicate-email">
                     🚧 Whoops! Looks like that e-mail address is already being used by a Buwana Account. Please choose another.
                   </div>
                   <div id="duplicate-gobrik-email" class="form-warning">
                     <?php $dup_login = build_login_url('login.php', ['app' => $app_info['client_id']]); ?>
                     🌏 <span data-lang-id="006-gobrik-duplicate">It looks like this email is already being used with a legacy GoBrik account. Please <a href="<?= htmlspecialchars($dup_login) ?>" class="underline-link">login with this email to upgrade your account.</a></span>
                  </div>

                   <div id="loading-spinner" class="spinner" style="display: none;margin-left: 10px;margin-top: 7px;"></div>

                   <p class="form-caption" data-lang-id="007-email-sub-caption" style="margin-bottom: -10px;">💌 We'll use this email to confirm your account.</p>
                 </div>


               <!-- Set Password -->
               <div class="form-item float-label-group bullet-container" id="set-password" style="display: none; margin-top: 14px; margin-bottom: 15px; padding-bottom: 1px;">
                 <div class="bullet-indicator" id="bullet-password"></div>
                 <input type="password" id="password_hash" name="password_hash" required minlength="6" placeholder=" " style="font-size: 22px !important; padding-left: 30px;" />
                 <label for="password_hash" data-lang-id="008-set-your-pass">Set your password...</label>
                 <span toggle="#password_hash" class="toggle-password" style="cursor: pointer; top:36%; margin-right:15px; font-size:20px;">🙈</span>
                 <p class="form-caption" data-lang-id="008-password-advice">🔑 Your password must be at least 6 characters.</p>
               </div>

               <!-- Confirm Password -->
               <div class="form-item float-label-group bullet-container" id="confirm-password-section" style="display: none; margin-top: 10px;">
                 <div class="bullet-indicator" id="bullet-confirm"></div>
                 <input type="password" id="confirm_password" name="confirm_password" required placeholder=" " style="font-size: 22px !important; padding-left: 30px;" />
                 <label for="confirm_password" data-lang-id="009-confirm-pass">Confirm your password...</label>
                 <span toggle="#confirm_password" class="toggle-password" style="cursor: pointer; margin-bottom:13px; margin-right:15px; font-size:20px; margin-top: 45px;">🙈</span>
                 <div id="maker-error-invalid" class="form-field-error" data-lang-id="010-pass-error-no-match">👉 Passwords do not match.</div>
               </div>

               <!-- Human Check -->
               <div class="form-item float-label-group bullet-container" id="human-check-section" style="display: none; margin-top:15px; margin-bottom:15px; padding-bottom:5px;">
                 <div class="bullet-indicator" id="bullet-human"></div>
                 <input type="text" id="human_check" name="human_check" style="padding-left: 30px;" required placeholder=" " />
                 <label for="human_check" data-lang-id="011-human-check">Type the word "ecobrick"...</label>
                 <p class="form-caption">
                   <span data-lang-id="012-prove-human">This is a little test to see if you're human!</span>
                   <span data-lang-id="013-fun-fact">🤓 Fun fact: </span>
                   <a href="#" onclick="openAboutKeyword()" class="underline-link" data-lang-id="000-ecobrick-low">ecobrick</a>
                   <span data-lang-id="014-is-spelled"> is spelled without a space, capital or hyphen!</span>
                 </p>
               </div>



               <div style="display:flex;" class="form-item">
                 <input type="checkbox" id="terms" name="terms" required checked>
                 <div class="form-caption"><span data-lang-id="015-by-registering">By registering today, I agree to the </span><a href="#" onclick="openTermsModal(); return false;" class="underline-link"><?= $app_info['app_display_name']; ?> <span data-lang-id="1000-terms-of-use">Terms of Use</a>.
                 </div>
               </div>

           <input type="hidden" id="fillout_duration" name="fillout_duration" value="">



             <!-- Kick-Ass Submit Button -->
             <div id="submit-section" style="display:none;" class="submit-button-wrapper">
               <button type="submit" id="submit-button" class="kick-ass-submit disabled" title="Be sure you wrote ecobrick correctly!">
                 <span id="submit-button-text" data-lang-id="016-register-button">Register ➡</span>
                 <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
               </button>
             </div>
            </form>
        </div>
    <div id="browser-back-link" style="font-size: medium; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: 0px;" >
            <p style="font-size: medium;">
            <a href="#" onclick="browserBack(event)" data-lang-id="000-go-back">↩ Go back a step</a>
        </p>
    </div>

    </div>
</div>
</div><!--main closes-->

    <?php require_once ("../footer-2025.php"); ?>


<script>
$(document).ready(function () {
  // === Elements ===
  const credentialField = document.getElementById('credential_value');
  const passwordField = document.getElementById('password_hash');
  const confirmPasswordField = document.getElementById('confirm_password');
  const humanCheckField = document.getElementById('human_check');
  const termsCheckbox = document.getElementById('terms');
  const submitButton = document.getElementById('submit-button');
  const setPasswordSection = document.getElementById('set-password');
  const confirmPasswordSection = document.getElementById('confirm-password-section');
  const humanCheckSection = document.getElementById('human-check-section');
  const submitSection = document.getElementById('submit-section');
  const makerErrorInvalid = document.getElementById('maker-error-invalid');
  const duplicateEmailError = $('#duplicate-email-error');
  const duplicateGobrikEmail = $('#duplicate-gobrik-email');
  const loadingSpinner = $('#loading-spinner');

  // === Initial UI State ===
  setPasswordSection.style.display = 'none';
  confirmPasswordSection.style.display = 'none';
  humanCheckSection.style.display = 'none';
  submitSection.style.display = 'none';

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  // === Email Live Check ===
  $('#credential_value').on('input blur', function () {
      const email = $(this).val();
      if (isValidEmail(email)) {
        loadingSpinner.removeClass('green red').show();
        $('#credential_value').css('padding-left', '35px');  // <--- ADD padding immediately when checking

        $.ajax({
          url: '../scripts/check_email.php',
          type: 'POST',
          data: { credential_value: email },
          success: function (response) {
            loadingSpinner.hide();
            try {
              var res = JSON.parse(response);
            } catch (e) {
              console.error("Invalid JSON response", response);
              alert("An error occurred while checking the email.");
              $('#credential_value').css('padding-left', ''); // <--- RESET padding if error
              return;
            }

            if (res.success) {
              duplicateEmailError.hide();
              duplicateGobrikEmail.hide();
              loadingSpinner.addClass('green').show();
              setPasswordSection.style.display = 'block';
              $('#credential_value').css('padding-left', '35px');  // <--- Keep padding when success
            } else if (res.error === 'duplicate_email') {
              duplicateEmailError.show();
              duplicateGobrikEmail.hide();
              loadingSpinner.addClass('red').show();
              setPasswordSection.style.display = 'none';
              $('#credential_value').css('padding-left', '35px');  // <--- Keep padding when duplicate
            } else if (res.error === 'duplicate_gobrik_email') {
              duplicateGobrikEmail.show();
              duplicateEmailError.hide();
              loadingSpinner.addClass('red').show();
              setPasswordSection.style.display = 'none';
              $('#credential_value').css('padding-left', '35px');  // <--- Keep padding when gobrik duplicate
            } else {
              alert("An error occurred: " + res.error);
              $('#credential_value').css('padding-left', '');  // <--- Reset padding if unknown error
            }
          },
          error: function () {
            loadingSpinner.hide();
            alert("An error occurred while checking the email. Please try again.");
            $('#credential_value').css('padding-left', ''); // <--- RESET padding on request failure
          }
        });

      } else {
        // If invalid email format, hide spinner and remove padding
        loadingSpinner.hide();
        $('#credential_value').css('padding-left', '');
        setPasswordSection.style.display = 'none';
      }
  });


  // === Password Length Check + Bullet Update ===
  passwordField.addEventListener('input', function () {
    if (passwordField.value.length >= 6) {
      confirmPasswordSection.style.display = 'block';
      document.getElementById('bullet-password').classList.add('green');
    } else {
      confirmPasswordSection.style.display = 'none';
      humanCheckSection.style.display = 'none';
      submitSection.style.display = 'none';
      document.getElementById('bullet-password').classList.remove('green');
    }
  });

  // === Confirm Password Match + Bullet Update ===
  confirmPasswordField.addEventListener('input', function () {
    if (passwordField.value === confirmPasswordField.value && passwordField.value.length >= 6) {
      makerErrorInvalid.style.display = 'none';
      humanCheckSection.style.display = 'block';
      submitSection.style.display = 'block';
      document.getElementById('bullet-confirm').classList.add('green');
    } else {
      makerErrorInvalid.style.display = 'block';
      humanCheckSection.style.display = 'none';
      submitSection.style.display = 'none';
      document.getElementById('bullet-confirm').classList.remove('green');
    }
  });

  // === Human Check Validation + Bullet Update ===
  humanCheckField.addEventListener('input', function () {
    const validWords = ['ecobrick', 'ecoladrillo', 'écobrique', 'ecobrique'];
    const enteredWord = humanCheckField.value.toLowerCase();

    if (validWords.includes(enteredWord)) {
      document.getElementById('bullet-human').classList.add('green');
    } else {
      document.getElementById('bullet-human').classList.remove('green');
    }

    // Still update the submit button regardless
    updateSubmitButtonState();
  });


  // === Enable/Disable Submit Button ===
  function updateSubmitButtonState() {
    const validWords = ['ecobrick', 'ecoladrillo', 'écobrique', 'ecobrique'];
    const enteredWord = humanCheckField.value.toLowerCase();
    if (validWords.includes(enteredWord) && termsCheckbox.checked) {
      submitButton.classList.remove('disabled');
      submitButton.classList.add('enabled');
      submitButton.disabled = false;
    } else {
      submitButton.classList.remove('enabled');
      submitButton.classList.add('disabled');
      submitButton.disabled = true;
    }
  }

  humanCheckField.addEventListener('input', updateSubmitButtonState);
  termsCheckbox.addEventListener('change', updateSubmitButtonState);

  // === Page-level Validation Function ===
  window.validateOnSubmit = function () {
      const email = credentialField.value.trim();
      const password = passwordField.value;
      const confirmPassword = confirmPasswordField.value;
      const humanCheck = humanCheckField.value.toLowerCase();
      const termsChecked = termsCheckbox.checked;
      const honeypotTriggered = checkHoneypot(); // 🧠 check if honeypot filled
      const validWords = ['ecobrick', 'ecoladrillo', 'écobrique', 'ecobrique'];

      // Optionally log it
      console.log("Honeypot Triggered:", honeypotTriggered);

      return (
        isValidEmail(email) &&
        password.length >= 6 &&
        password === confirmPassword &&
        validWords.includes(humanCheck) &&
        termsChecked
      );
  };

});

// === Track Form Fillout Time ===
let filloutStartTime = null;

function startFilloutChrono() {
  if (!filloutStartTime) {
    filloutStartTime = Date.now();
    console.log("🕰️ Form filling started...");
  }
}

function endFilloutChrono(event) {
  if (filloutStartTime) {
    const filloutEndTime = Date.now();
    const filloutDuration = Math.floor((filloutEndTime - filloutStartTime) / 1000);

    console.log(`✅ Form submitted after ${filloutDuration} seconds.`);

    const chronoInput = document.getElementById('fillout_duration');
    if (chronoInput) {
      chronoInput.value = filloutDuration;
    }

    // 🧠 Honeypot field setting (optional safety)
    const honeypotField = document.getElementById('last_name');
    if (honeypotField && honeypotField.value.trim() !== '') {
      honeypotField.value = honeypotField.value.trim();
    }

    // Optional block bots submitting too fast
    if (filloutDuration < 5) {
      alert("⚠️ Too fast! Possible bot.");
      event.preventDefault();
    }
  }
}


// Start chrono on first user input
document.querySelectorAll('#user-signup-form input').forEach(input => {
  input.addEventListener('input', startFilloutChrono, { once: true });
});

// Attach chrono ender to form submit
document.getElementById('user-signup-form').addEventListener('submit', endFilloutChrono);

// === Mark JS enabled ===
document.addEventListener('DOMContentLoaded', function () {
  const jsEnabledInput = document.createElement('input');
  jsEnabledInput.type = 'hidden';
  jsEnabledInput.name = 'js_enabled';
  jsEnabledInput.value = 'true';
  document.getElementById('user-signup-form').appendChild(jsEnabledInput);
});

function checkHoneypot() {
  const honeypotField = document.getElementById('last_name');
  if (honeypotField && honeypotField.value.trim() !== '') {
    console.log("🚨 Honeypot triggered! Bot likely.");
    honeypotField.value = honeypotField.value.trim(); // just clean it
    return 1;
  }
  return 0;
}

</script>


<script>
document.getElementById('user-signup-form').addEventListener('submit', function(event) {
    event.preventDefault();

    const form = event.target;
    const action = form.action;
    const formData = new FormData(form);

    fetch(action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            alert("Something went wrong. Please try again.");
            console.error(data.error || "Unknown error");
        }
    })
    .catch(error => {
        alert("A network error occurred.");
        console.error("Network error:", error);
    });
});







</script>



<?php require_once ("../scripts/app_modals.php");?>




</body>
</html>
