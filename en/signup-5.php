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

// 📋 Fetch communities
$communities = [];
$result_communities = $buwana_conn->query("SELECT com_name FROM communities_tb");
while ($row = $result_communities->fetch_assoc()) {
    $communities[] = $row['com_name'];
}

// 📋 Fetch countries
$countries = [];
$result_countries = $buwana_conn->query("SELECT country_id, country_name FROM countries_tb ORDER BY country_name ASC");
while ($row = $result_countries->fetch_assoc()) {
    $countries[] = $row;
}

// 📋 Fetch languages
$languages = [];
$result_languages = $buwana_conn->query("SELECT language_id, languages_native_name FROM languages_tb ORDER BY languages_native_name ASC");
while ($row = $result_languages->fetch_assoc()) {
    $languages[] = $row;
}

// 📋 Fetch user's current country id
$user_country_id = null;
$stmt = $buwana_conn->prepare("SELECT country_id FROM users_tb WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($user_country_id);
    $stmt->fetch();
    $stmt->close();
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
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

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

                <!-- COMMUNITY FIELD -->
                <div class="form-item float-label-group" id="community-section" style="padding-bottom:10px;">
                    <input type="text" id="community_name" name="community_name" aria-label="Community Name" style="padding-left:45px;" placeholder=" ">
                    <label for="community_name" data-lang-id="011-community-connect" style="border-radius:10px 10px 0px 0px;padding-bottom:10px;">Your community...</label>
                    <div id="community-loading-spinner" class="spinner" style="display:none;"></div>
                    <div id="community-pin" class="pin-icon">👥</div>
                    <p class="form-caption"><span data-lang-id="012-start-typing-community">Start typing to see and select a community. There's a good chance someone local to you has already set one up!</span><br>➕ <a href="#" onclick="openAddCommunityModal(); return false;" style="color:#007BFF; text-decoration: underline;" data-lang-id="013-add-community"></a></p>
                </div>

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

    // Community autocomplete logic
    const communityNames = <?php echo json_encode($communities); ?>;
    $("#community_name").autocomplete({
        source: communityNames,
        minLength: 2,
        search: function() {
            $("#community-loading-spinner").show();
            $("#community-pin").hide();
        },
        response: function() {
            $("#community-loading-spinner").hide();
            $("#community-pin").show();
        }
    });

    $("#community_name").on("input", function() {
        if (this.value.trim() === '') {
            $("#community-pin").show();
            $("#community-loading-spinner").hide();
        }
    });

    const userLanguageId = "<?php echo $lang; ?>";
    const userCountryId = "<?php echo htmlspecialchars($user_country_id ?? '', ENT_QUOTES, 'UTF-8'); ?>";

    window.openAddCommunityModal = function () {
        const modal = document.getElementById('form-modal-message');
        const modalBox = document.getElementById('modal-content-box');

        modal.style.display = 'flex';
        modalBox.style.flexFlow = 'column';
        document.getElementById('page-content')?.classList.add('blurred');
        document.getElementById('footer-full')?.classList.add('blurred');
        document.body.classList.add('modal-open');

        modalBox.style.maxHeight = '100vh';
        modalBox.style.overflowY = 'auto';

        modalBox.innerHTML = `
            <h4 style="text-align:center;" data-lang-id="014-add-community-title">Add Your Community</h4>
            <p data-lang-id="015-add-community-desc">Add your community to Buwana so that others can connect across regenerative apps.</p>
            <form id="addCommunityForm" onsubmit="addCommunity2Buwana(event)">
                <label for="newCommunityName" data-lang-id="016-community-name-label">Name of Community:</label>
                <input type="text" id="newCommunityName" name="newCommunityName" required>
                <label for="newCommunityType" data-lang-id="017-community-type-label">Type of Community:</label>
                <select id="newCommunityType" name="newCommunityType" required>
                    <option value="" data-lang-id="018-select-type-option">Select Type</option>
                    <option value="neighborhood" data-lang-id="019-type-neighborhood">Neighborhood</option>
                    <option value="city" data-lang-id="020-type-city">City</option>
                    <option value="school" data-lang-id="021-type-school">School</option>
                    <option value="organization" data-lang-id="022-type-organization">Organization</option>
                </select>
                <label for="communityCountry" data-lang-id="023-country-label">Country:</label>
                <select id="communityCountry" name="communityCountry" required>
                    <option value="" data-lang-id="024-select-country-option">Select Country...</option>
                    <?php foreach ($countries as $country) : ?>
                        <option value="<?php echo $country['country_id']; ?>"><?php echo htmlspecialchars($country['country_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="communityLanguage" data-lang-id="025-language-label">Preferred Language:</label>
                <select id="communityLanguage" name="communityLanguage" required>
                    <option value="" data-lang-id="026-select-language-option">Select Language...</option>
                    <?php foreach ($languages as $language) : ?>
                        <option value="<?php echo $language['language_id']; ?>"><?php echo htmlspecialchars($language['languages_native_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="margin-top:10px;" class="confirm-button enabled" data-lang-id="027-submit-button">Create Community</button>
            </form>
        `;

        applyTranslations();

        setTimeout(() => {
            document.getElementById('communityCountry').value = userCountryId;
            document.getElementById('communityLanguage').value = userLanguageId;
        }, 100);
    };

    window.addCommunity2Buwana = function (event) {
        event.preventDefault();
        const form = document.getElementById('addCommunityForm');
        const formData = new FormData(form);

        fetch('../api/add_community.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                closeInfoModal();
                communityNames.push(data.community_name);
                $('#community_name').val(data.community_name);
            }
        })
        .catch(error => {
            alert('Error adding community. Please try again.');
            console.error('Error:', error);
        });
    };
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
