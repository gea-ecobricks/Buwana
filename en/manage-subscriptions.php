<?php
require_once '../earthenAuth_helper.php'; // Include the authentication helper functions
require_once '../auth/session_start.php';

// PART 1: Set up page variables
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.544';
$page = 'manage-subscriptions';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));


// PART 2: Check if user is logged in and session active
if ($is_logged_in) {
    $buwana_id = $_GET['id'] ?? null;
    $buwana_id = $_SESSION['buwana_id'] ?? ''; // Retrieve buwana_id from session

    // Include database connection
    require_once '../gobrikconn_env.php';
    require_once '../buwanaconn_env.php';

    // Fetch the user's location data
    $user_continent_icon = getUserContinent($buwana_conn, $buwana_id);
$earthling_emoji = getUserEarthlingEmoji($buwana_conn, $buwana_id);
    $user_location_watershed = getWatershedName($buwana_conn, $buwana_id);
    $user_location_full = getUserFullLocation($buwana_conn, $buwana_id);
    $gea_status = getGEA_status($buwana_id);
    $user_community_name = getCommunityName($buwana_conn, $buwana_id);
    $ecobrick_unique_id = '0';
    $first_name = getFirstName($buwana_conn, $buwana_id);

    $response = ['success' => false];

    $ghost_member_id = '';

    // Initialize user variables
    $credential_type = '';
    $credential_key = '';
    $account_status = '';

    // Global variable to store the user's subscribed newsletters
    $subscribed_newsletters = [];


    // Include database connection
    include '../buwanaconn_env.php';
    include '../gobrikconn_env.php';
    require_once ("../scripts/earthen_subscribe_functions.php");

    // Look up user information if buwana_id is provided
    if ($buwana_id) {
        $gea_status = getGEA_status($buwana_id);  //added here
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

} else {
    header('Location: login.php?redirect=' . urlencode($page));
    exit();
}

?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require_once ("../includes/manage-subscriptions-inc.php");?>
<div class="splash-title-block"></div>
<div id="splash-bar"></div>

<!-- PAGE CONTENT -->
<div id="top-page-image" class="credentials-banner top-page-image"></div>

<div id="form-submission-box" class="landing-page-form">
    <div class="form-container">
        <div style="text-align:center;width:100%;margin:auto;">
            <h2 data-lang-id="001-manage-subs">Manage Earthen Subscriptions</h2>
            <p><span  data-lang-id="003-get-your">Earthen newsletter and GoBrik notifications sent to </span><?php echo $credential_key; ?>.<span data-lang-id="004-later-upgrade">  To manage your paid subscriptions please login with your email at <a href="https://earthen.io" target="_blank">earthen.io</a></span>
            </p>
           <!--<div id="subscribed" style="color:green;display:<?php echo $is_subscribed ? 'block' : 'none'; ?>;">
                <?php if ($is_subscribed && !empty($earthen_subscriptions)): ?>
                    <p style="color:green;font-size:1em;">👍 <span data-lang-id="005-nice">Nice! You're already subscribed to:</span> <?php echo htmlspecialchars($earthen_subscriptions); ?>.  <span data-lang-id="006-choose"> Choose to add or remove subscriptions below:</span></p>
                <?php endif; ?>
            </div>-->
            <div id="not-subscribed" style="color:grey;display:<?php echo !$is_subscribed ? 'block' : 'none'; ?>;" data-lang-id="007-not-subscribed">You're not yet subscribed to any Earthen newsletters yet.  All are free with upgrade options later.  Please select:</div>
            <div id="earthen-server-error" class="form-field-error"></div>


            <!-- MANAGE SUBSCRIPTIONS FORM
                  -->
        <form id="select-earthen-subs" method="post" action="process_sub_selections.php" style="margin-top:30px;">
             <input type="hidden" name="buwana_id" value="<?php echo htmlspecialchars($buwana_id); ?>">
            <input type="hidden" name="credential_key" value="<?php echo htmlspecialchars($credential_key); ?>">
            <input type="hidden" name="subscribed_newsletters" value="<?php echo htmlspecialchars(json_encode($subscribed_newsletters)); ?>">
            <input type="hidden" name="ghost_member_id" value="<?php echo htmlspecialchars($ghost_member_id); ?>">
            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>"> <!-- Added input for first_name -->

            <div class="subscription-boxes">
                <!-- Subscription boxes will be populated here by the PHP function -->
                <?php grabActiveEarthenSubs(); ?>
            </div>
        <p class="form-caption" style="text-align:center; margin-top: 10px;font-size:0.9em;" data-lang-id="009-terms">Earthen newsletters and GoBrik are sent according to our non-profit, privacy <a href="#" onclick="showModalInfo('terms', '<?php echo $lang; ?>')" class="underline-link"> Terms of Service</a>.</p>

           </div>

            <div id="submit-section" style="text-align:center;margin-top:25px;" data-lang-id="016x-complete-button">
                <input type="submit" id="submit-button" value="💾 Save Selection" class="submit-button enabled">
            </div>
        <hr>
        Or conversely you can unsubscribe completely:
        <button id="unsubscribe-button" class="submit-button delete">Full Unsubscribe</button>
        </form>



        </div>
    </did>


    </div>
</div>


</div>

<!-- FOOTER STARTS HERE -->
<?php require_once ("../footer-2025.php"); ?>

<script>

    // subBoxHighlighter.js

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




</body>
</html>
