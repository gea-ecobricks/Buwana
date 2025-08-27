<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'signup-5';
$version = '0.7779';
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

// 🧩 Validate buwana_id
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

// 🧠 Fetch user info
$first_name = 'User';
$sql = "SELECT first_name FROM users_tb WHERE buwana_id = ?";
$stmt = $buwana_conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name);
    $stmt->fetch();
    $stmt->close();
}


$response = ['success' => false];
$buwana_id = $_GET['id'] ?? null;
$ghost_member_id = '';

// Initialize user variables
$credential_type = '';
$credential_key = '';
$first_name = '';
$account_status = '';
$country_icon = '';
// Global variable to store the user's subscribed newsletters
$subscribed_newsletters = [];


// Include database connection
//include '../buwanaconn_env.php';
//include '../gobrikconn_env.php';
require_once ("../scripts/earthen_subscribe_functions.php");

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

    $sql_lookup_user = "SELECT first_name, account_status FROM users_tb WHERE buwana_id = ?";
    $stmt_lookup_user = $buwana_conn->prepare($sql_lookup_user);
    if ($stmt_lookup_user) {
        $stmt_lookup_user->bind_param("i", $buwana_id);
        $stmt_lookup_user->execute();
        $stmt_lookup_user->bind_result($first_name, $account_status);
        $stmt_lookup_user->fetch();
        $stmt_lookup_user->close();
    } else {
        $response['error'] = 'db_error';
    }

    $credential_type = htmlspecialchars($credential_type);
    $first_name = htmlspecialchars($first_name);

    if ($account_status !== 'name set only') {
        $response['error'] = 'account_status';
    }



// Check subscription status
$is_subscribed = false;
$earthen_subscriptions = ''; // To store newsletter names if subscribed
if (!empty($credential_key)) {
    // Call the function and capture the JSON response
    $api_response = checkEarthenEmailStatus($credential_key);

    // Parse the API response
    $response_data = json_decode($api_response, true);

    // Check if the response is valid JSON and handle accordingly
    if (json_last_error() === JSON_ERROR_NONE && isset($response_data['status']) && $response_data['status'] === 'success') {
        if ($response_data['registered'] === 1) {
            $is_subscribed = true;
            // Join newsletter names with commas for display
            $earthen_subscriptions = implode(', ', $subscribed_newsletters);
        }
    } else {
        // Handle invalid JSON or other errors
        echo '<script>console.error("Invalid JSON response or error: ' . htmlspecialchars($response_data['message'] ?? 'Unknown error') . '");</script>';
    }
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

<?php require_once ("../includes/signup-5-inc.php");?>


<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>
<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form" style="min-height:calc( 100vh - 54px)">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;margin:auto;">

            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

        <div style="text-align:center;width:100%;margin:auto;">
            <h2 data-lang-id="001-select-subs"></h2>
            <h4 style="color:#748931;" data-lang-id="002-sub-subtitle"></h4>
            <p><span  data-lang-id="003-get-your"></p>
           <div id="subscribed" style="color:green;display:<?php echo $is_subscribed ? 'block' : 'none'; ?>;">
                <?php if ($is_subscribed && !empty($earthen_subscriptions)): ?>
                    <p style="color:green;font-size:1em;">👍 <span data-lang-id="005-nice">Nice! You're already subscribed to:</span> <?php echo htmlspecialchars($earthen_subscriptions); ?>.  <span data-lang-id="006-choose"> Choose to add or remove subscriptions below:</span></p>
                <?php endif; ?>
            </div>
            <div id="not-subscribed" style="color:grey;display:<?php echo !$is_subscribed ? 'block' : 'none'; ?>;"><?php echo $credential_key; ?><span data-lang-id="007-later-upgrade"> is not yet subscribed to any Earthen newsletters.</div>
            <div id="earthen-server-error" class="form-field-error"></div>

            <!-- SIGNUP FORM -->
            <form id="user-signup-form" method="post" action="signup-5_process.php" style="margin-top:30px;">
                 <input type="hidden" name="buwana_id" value="<?php echo htmlspecialchars($buwana_id); ?>">
                <input type="hidden" name="credential_key" value="<?php echo htmlspecialchars($credential_key); ?>">
                <input type="hidden" name="subscribed_newsletters" value="<?php echo htmlspecialchars(json_encode($subscribed_newsletters)); ?>">
                <input type="hidden" name="ghost_member_id" value="<?php echo htmlspecialchars($ghost_member_id); ?>">
                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>"> <!-- Added input for first_name -->

                <div class="subscription-boxes">
                    <!-- Subscription boxes will be populated here by the PHP function -->
                    <?php grabActiveEarthenSubs(); ?>
                </div>

                     <!-- Kick-Ass Submit Button -->
                <div id="submit-section" class="submit-button-wrapper">
                   <p data-lang-id="008-almost-done" style="text-align:center;margin-top:35px;margin-bottom:15px">Your Buwana account activation is almost complete!</p>

                    <button type="submit" id="submit-button" class="kick-ass-submit">
                        <span id="submit-button-text" data-lang-id="009-finalize-button">Finalize ➡</span>
                        <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
                    </button>
                </div>

            <p class="form-caption" style="text-align:center; margin-top: 10px;font-size:0.9em;"><span  data-lang-id="010-terms"></span><a href="#" onclick="openBuwanaPrivacy()" class="underline-link" data-lang-id="1000-privacy-policy"></a>.</p>

            </form>
        </div>
    </div>

    <div id="browser-back-link" style="font-size: medium; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: 0px;">
        <p style="font-size: 1em"><a href="#" onclick="browserBack(event)" data-lang-id="000-go-back">↩ Go back one</a></p>
    </div>
    </div>
</div>

<!-- FOOTER STARTS HERE -->
<?php require_once ("../footer-2025.php"); ?>



<script>

document.addEventListener('DOMContentLoaded', function () {
    const subBoxes = document.querySelectorAll('.sub-box');

    subBoxes.forEach(box => {
        const checkbox = box.querySelector('.sub-checkbox');

        // Toggle checkbox when box is clicked
        box.addEventListener('click', function (event) {
            if (event.target !== checkbox && event.target.className !== 'checkbox-label') {
                checkbox.checked = !checkbox.checked;
            }
            updateBoxStyle(box, checkbox.checked);
        });

        // Update style on checkbox change
        checkbox.addEventListener('change', function () {
            updateBoxStyle(box, checkbox.checked);
        });
    });

    function updateBoxStyle(box, isSelected) {
        if (isSelected) {
            box.style.border = '2px solid green';
            box.style.backgroundColor = 'var(--darker)';
        } else {
            box.style.border = '1px solid rgba(128, 128, 128, 0.5)';
            box.style.backgroundColor = 'transparent';
        }
    }
});



function enhanceNewsletterInfo() {
    // Define the newsletters and their corresponding updates
    const updates = {
        'gea-trainers': 'English | monthly',
        'gea-trainer-newsletter-indonesian': 'Bahasa Indonesia | setiap bulan',
        'updates-by-russell': 'English | monthly',
        'gobrik-news-updates': 'English | monthly',
        'default-newsletter': 'English | monthly'
    };

    // Loop through each update and modify the inner HTML of the matching newsletter divs
    Object.keys(updates).forEach(newsletter => {
        const element = document.querySelector(`#${newsletter} .sub-lang`);
        if (element) {
            element.innerHTML = updates[newsletter];
        }
    });
}

// Call the function to apply the updates
enhanceNewsletterInfo();

</script>

<?php require_once ("../scripts/app_modals.php"); ?>


</body>
</html>
