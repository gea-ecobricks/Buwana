<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once 'signup-1_process.php';
    exit();
}

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
$version = '0.77779';
$page = 'signup-1';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$is_logged_in = false; // Ensure not logged in for this page
$buwana_id = null;

// ✅ Direct session check instead of calling a function
if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? 'https://gobrik.com';
    echo "<script>
        alert('Looks like you already have an account and are logged in! Let\'s take you to your dashboard.');
        window.location.href = '$redirect_url';
    </script>";
    header("Location: $redirect_url"); // Fallback in case JS doesn't run
    exit();
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



<?php require_once ("../includes/signup-1-inc.php");?>




<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>

<div class="login-panel-group">
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">
            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

            <div style="text-align:center;width:100%;margin:auto;">
                <div id="status-message" data-lang-id="001-signup-heading" style="font-family: 'Arvo';margin-top:15px;"><!--Create Your Account--></div>

                <div id="sub-status-message" style="margin-bottom:15px;"><?= htmlspecialchars($app_info['app_display_name']) ?><span data-lang-id="002-signup-subtext"> <!--uses the Buwana Authentication protocol— a secure, open-source and for-Earth protocol that powers regenerative apps.--></div>
            </div>

            <!--SIGNUP-1 FORM-->
            <form id="user-signup-form" method="post" action="signup-1_process.php" novalidate>

   <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
     <input type="text" id="first_name" name="first_name"
            aria-label="Your first name"
            maxlength="255"
            required
            placeholder=" " />
     <label for="first_name" data-lang-id="003-firstname">What's your first name?</label>
     <!-- ERRORS -->
     <div id="maker-error-required" class="form-field-error" data-lang-id="000-field-required-error">This field is required.</div>
     <div id="maker-error-long" class="form-field-error" data-lang-id="000-name-field-too-long-error">The name is too long. Max 255 characters.</div>
     <div id="maker-error-invalid" class="form-field-error" data-lang-id="005b-name-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
   </div>


<!-- Credential selection simplified: default to email without showing the field -->
<input type="hidden" id="credential" name="credential" value="email" />



  <div class="submit-button-wrapper">

  <button type="submit" id="submit-button" class="kick-ass-submit">
    <span id="submit-button-text" data-lang-id="000-next"><!--Next ➡--></span>
    <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
  </button>


  </div>


  </form>

            <div style="font-size: medium; text-align: center; margin: auto; align-self: center;padding-top:40px;padding-bottom:50px;margin-top: 0px;">
                <?php $login_url = build_login_url($app_info['app_login_url'], ['app' => $app_info['client_id']]); ?>
                <p style="font-size:medium;line-height:2em;"><span data-lang-id="000-already-have-account">Already have an account?</span> <br> <a href="<?= htmlspecialchars($login_url) ?>"><span data-lang-id="000-login-to"> Login to </span> <?= htmlspecialchars($app_info['app_display_name']) ?> ↗</a>.</p>
            </div>
        </div>
    </div>
</div>


      </div><!--closes Landing content-->






 </div>

</div><!--closes main and starry background-->

<!--FOOTER STARTS HERE-->

<?php require_once ("../footer-2025.php");?>

</div><!--close page content-->


<script>

    document.addEventListener('DOMContentLoaded', function () {
        const credentialSelect = document.getElementById('credential');

        function updateCredentialColor() {
            if (credentialSelect.value === "") {
                credentialSelect.style.color = "var(--subdued-text)";
            } else {
                credentialSelect.style.color = "var(--h1)";
            }
        }

        // Run it initially (in case a value is pre-selected)
        updateCredentialColor();

        // Run it whenever user changes selection
        credentialSelect.addEventListener('change', updateCredentialColor);
    });


document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('user-signup-form');
  const submitButton = document.getElementById('submit-button');
  const firstNameInput = document.getElementById('first_name');
  const credentialSelect = document.getElementById('credential');
  const btnText = document.getElementById('submit-button-text');

  const errorRequired = document.getElementById('maker-error-required');
  const errorLong = document.getElementById('maker-error-long');
  const errorInvalid = document.getElementById('maker-error-invalid');
  const credentialError = document.getElementById('credential-error-required');

  function hasInvalidChars(value) {
    const invalidChars = /[\'\"><]/;
    return invalidChars.test(value);
  }

  function displayError(element, show) {
    if (element) element.style.display = show ? 'block' : 'none';
  }

  function validateFieldsLive() {
    const firstName = firstNameInput.value.trim();
    const isFirstNameValid = firstName.length > 0 && firstName.length <= 255;
    const isCredentialValid = credentialSelect.value !== "";

    return isFirstNameValid && isCredentialValid;
  }

  function validateOnSubmit() {
    let isValid = true;
    const firstName = firstNameInput.value.trim();
    const credential = credentialSelect.value;

    displayError(errorRequired, firstName === '');
    displayError(errorLong, firstName.length > 255);
    displayError(errorInvalid, hasInvalidChars(firstName));
    displayError(credentialError, credential === '');

    if (firstName === '' || firstName.length > 255 || hasInvalidChars(firstName)) {
      isValid = false;
    }

    if (credential === '') {
      isValid = false;
    }

    return isValid;
  }

  // Real-time validation
  firstNameInput.addEventListener('input', validateFieldsLive);
  credentialSelect.addEventListener('change', validateFieldsLive);

  // Custom submit logic
  form.addEventListener('submit', function (event) {
    event.preventDefault(); // Always block the native form submission

    const isValid = validateOnSubmit();

    if (!isValid) {
      console.warn("🚫 Form blocked due to validation errors.");
      shakeElement(submitButton);
      return;
    }

    // ✅ Validation passed — manually dispatch a custom event that your global script expects
    const submitEvent = new Event('kickAssSubmit', { bubbles: true });
    form.dispatchEvent(submitEvent);
  });

  // 🔥 Trigger submit when user hits Enter
  form.addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      submitButton.click();
    }
  });



});
</script>




<?php require_once ("../scripts/app_modals.php");?>






</body>

</html>
