<?php
require_once '../earthenAuth_helper.php';
// Ensure the Buwana DB config is available
if (!file_exists('../buwanaconn_env.php')) {
    die('Buwana DB config not found.');
}
require_once '../buwanaconn_env.php';

// Set up page variables
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.395';
$page = 'profile';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

// 📌 Get the buwana_id and client_id from the URL
$buwana_id = isset($_GET['buwana']) ? intval($_GET['buwana']) : null;
$client_id = $_GET['app'] ?? ($_GET['client_id'] ?? null);

if (!$buwana_id || !$client_id) {
    die('Missing buwana ID or client ID.');
}

// 📥 Verify the user/app connection exists
$sql_connection = "SELECT id FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ?";
$stmt_connection = $buwana_conn->prepare($sql_connection);

if ($stmt_connection) {
    $stmt_connection->bind_param('is', $buwana_id, $client_id);
    $stmt_connection->execute();
    $stmt_connection->bind_result($connection_id);
    $stmt_connection->fetch();
    $stmt_connection->close();

    if (!$connection_id) {
        die('Connection not found.');
    }
} else {
    die('Error preparing statement for connection lookup: ' . $buwana_conn->error);
}

// 🧭 Get the app's info
require_once '../fetch_app_info.php';

// 📄 Fetch full user profile info
$sql_user_info = "SELECT full_name, first_name, last_name, email, country_id, language_id, birth_date,
                  created_at, last_login, brikcoin_balance, role, account_status, notes,
                  terms_of_service, continent_code, location_watershed, location_full, community_id,
                  location_lat, location_long, earthling_emoji, time_zone
                  FROM users_tb WHERE buwana_id = ?";
$stmt_user_info = $buwana_conn->prepare($sql_user_info);

if ($stmt_user_info) {
    $stmt_user_info->bind_param('i', $buwana_id);
    $stmt_user_info->execute();
    $stmt_user_info->bind_result(
        $full_name, $first_name, $last_name, $email, $country_id, $language_id,
        $birth_date, $created_at, $last_login, $brikcoin_balance, $role, $account_status,
        $notes, $terms_of_service, $continent_code, $location_watershed,
        $location_full, $community_id, $latitude, $longitude, $earthling_emoji, $time_zone
    );
    $stmt_user_info->fetch();
    $stmt_user_info->close();
} else {
    die('Error preparing statement for fetching user info: ' . $buwana_conn->error);
}

// 💬 Fallback defaults
$last_name = $last_name ?? '';
$earthling_emoji = $earthling_emoji ?? '';
$first_name = $first_name ?? '';
$location_full = $location_full ?? '';

// 🌐 Fetch active languages
$languages = [];
$sql_languages = "SELECT language_id, language_name_en, language_name_id, language_name_fr, language_name_es, language_active
                  FROM languages_tb
                  WHERE language_active = 1
                  ORDER BY language_name_en";
$result_languages = $buwana_conn->query($sql_languages);
if ($result_languages && $result_languages->num_rows > 0) {
    while ($row = $result_languages->fetch_assoc()) {
        $languages[] = $row;
    }
}

// 🗺️ Fetch countries
$countries = [];
$sql_countries = "SELECT country_id, country_name FROM countries_tb ORDER BY country_name";
$result_countries = $buwana_conn->query($sql_countries);
if ($result_countries && $result_countries->num_rows > 0) {
    while ($row = $result_countries->fetch_assoc()) {
        $countries[] = $row;
    }
}

// 🧭 Fetch continents
$continents = [];
$sql_continents = "SELECT continent_code, continent_name_en FROM continents_tb ORDER BY continent_name_en";
$result_continents = $buwana_conn->query($sql_continents);
if ($result_continents && $result_continents->num_rows > 0) {
    while ($row = $result_continents->fetch_assoc()) {
        $continents[] = $row;
    }
}

// 👥 Fetch communities
$communities = [];
$result_communities = $buwana_conn->query("SELECT com_name FROM communities_tb");
while ($row = $result_communities->fetch_assoc()) {
    $communities[] = $row['com_name'];
}



// Fetch user's community name from communities_tb based on community_id in users_tb
$community_name = "Unknown Community"; // Default value if no match found

if (!empty($community_id)) {
    $sql_community = "SELECT com_name FROM communities_tb WHERE community_id = ?";
    if ($stmt = $buwana_conn->prepare($sql_community)) {
        $stmt->bind_param("i", $community_id);
        $stmt->execute();
        $stmt->bind_result($community_name); // Directly bind result to $community_name
        if (!$stmt->fetch()) { // If no result found, log and retain default
            error_log("Community ID not found in communities_tb: " . $community_id);
        }
        $stmt->close();
    } else {
        error_log("Error preparing community fetch statement: " . $buwana_conn->error);
    }
}


// Assign for template use
$user_community_name = $community_name;
$user_location_full = $location_full;
$user_location_watershed = $location_watershed;
$user_location_lat = $latitude;
$user_location_long = $longitude;

echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">
<head>
<meta charset="UTF-8">
';
?>



<?php require_once("../includes/profile-inc.php"); ?>


<!-- PAGE CONTENT -->

<div id="top-page-image"
     class="top-page-image"
     data-light-img="<?= htmlspecialchars($app_info['signup_1_top_img_light']) ?>"
     data-dark-img="<?= htmlspecialchars($app_info['signup_1_top_img_dark']) ?>">
</div>


<div id="form-submission-box" style="height:fit-content;margin-top: 130px;">
    <div class="form-container" style="padding-top:120px">
        <div style="text-align:center;width:100%;margin:auto;">

            <div id="status-message">⚙️ <?php echo htmlspecialchars($first_name); ?>'s <span data-lang-id="001-profile-settings-title">Profile Settings</span></div>
            <div id="sub-status-message" data-lang-id="002-review-update-message">Review and update your Buwana account profile here:</div>
            <div id="update-status" style="font-size:1.3em; color:green;padding:10px;margin-top:10px;"></div>
            <div id="update-error" style="font-size:1.3em; color:red;padding:10px;margin-top:10px;"></div>
        </div>

        <div id="buwana-account" style="background:var(--lighter); padding:10px; border-radius:12px; display:flex; flex-wrap: wrap;">
    <div class="left-column" style="font-size:0.9em; flex: 1 1 50%; padding: 10px;">

    <!-- Non-editable Fields -->
    <div class="form-item">
        <p data-lang-id="004-full-name"><strong>Full Name:</strong></p>
        <h3><?php echo htmlspecialchars($full_name); ?></h3>
    </div>

    <!-- Email -->
    <div class="form-item">
        <p data-lang-id="005-email"><strong>Email:</strong></p>
        <p><?php echo htmlspecialchars($email); ?></p>
    </div>

    <!-- Account Created At -->
    <div class="form-item">
        <p data-lang-id="005-account-created-at"><strong>Account Created At:</strong></p>
        <p><?php echo htmlspecialchars($created_at); ?></p>
    </div>

    <!-- Last Login -->
    <div class="form-item">
        <p data-lang-id="006-last-login"><strong>Last Login:</strong></p>
        <p><?php echo htmlspecialchars($last_login); ?></p>
    </div>


    <!-- Account Status -->
    <div class="form-item">
        <p data-lang-id="009-account-status"><strong>Account Status:</strong></p>
        <p><?php echo htmlspecialchars($account_status); ?></p>
    </div>

    <!-- Account Notes -->
    <div class="form-item">
        <p data-lang-id="010-account-notes"><strong>Account Notes:</strong> <?php echo htmlspecialchars($notes); ?></p>
    </div>

    <!-- Agreed to Terms of Service -->
    <div class="form-item">
        <p data-lang-id="011-agreed-terms"><strong>Agreed to Terms of Service:</strong> <?php echo $terms_of_service ? 'Yes' : 'No'; ?></p>
    </div>

    <!-- Latitude -->
    <div class="form-item">
        <p data-lang-id="011a-latitude"><strong>Latitude:</strong> <?php echo htmlspecialchars($latitude); ?></p>
    </div>

    <!-- Longitude -->
    <div class="form-item">
        <p data-lang-id="011b-longitude"><strong>Longitude:</strong> <?php echo htmlspecialchars($longitude); ?></p>
    </div>

    <!-- Buwana ID -->
    <div class="form-item">
        <p data-lang-id="022-buwana-id"><strong>Buwana ID:</strong> <?php echo htmlspecialchars($buwana_id); ?></p>
    </div>

<!-- Time ZOne -->
    <div class="form-item">
        <p data-lang-id="022-buwana-id"><strong>Time Zone</strong> <?php echo htmlspecialchars($time_zone); ?></p>
    </div>


</div>




            <div class="right-column" style="flex: 1 1 50%; padding: 10px;">
                <!-- Editable Fields -->


<!-- Profile Update Form -->
<form action="profile_update_process.php" method="POST" id="main-buwana-update">

    <!-- First Name -->
    <div class="form-item">
        <label for="first_name" data-lang-id="012-first-name">First Name:</label>
        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
    </div>

    <!-- Last Name -->
    <div class="form-item">
        <label for="last_name" data-lang-id="013-last-name">Last Name:</label>
        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
    </div>

    <!-- Preferred Language-->
    <div class="form-item">
        <label for="language_id" data-lang-id="017-preferred-language">Preferred Language:</label>
        <select name="language_id" id="language_id">
            <option value="" data-lang-id="018-select-language">Select Language</option>
            <?php foreach ($languages as $language): ?>
                <option value="<?php echo htmlspecialchars($language['language_id']); ?>" <?php if ($language['language_id'] == $language_id) echo 'selected'; ?>>
                    <?php
                    switch (strtolower($lang)) {
                        case 'id':
                            echo htmlspecialchars($language['language_name_id']);
                            break;
                        case 'fr':
                            echo htmlspecialchars($language['language_name_fr']);
                            break;
                        case 'es':
                            echo htmlspecialchars($language['language_name_es']);
                            break;
                        case 'en':
                        default:
                            echo htmlspecialchars($language['language_name_en']);
                            break;
                    }
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Birth Date -->
    <div class="form-item">
        <label for="birth_date" data-lang-id="019-birth-date">Birth Date:</label>
        <input type="date" name="birth_date" id="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>">
    </div>

<!-- Earthling Emoji -->
<div class="form-item">
    <label for="earthling_emoji" data-lang-id="999-earthling-emoji">Your Buwana Totem:</label>
    <div style="display: flex; align-items: center;">
        <input
            type="text"
            name="earthling_emoji"
            id="earthling_emoji"
            value="<?php echo htmlspecialchars($earthling_emoji); ?>"
            readonly
            style="flex: 1; padding: 10px; background-color: #f9f9f9; cursor: not-allowed;max-width: 57px;
            font-size: 1.5em;"

        >
        <button
            type="button"
            onclick="earthlingEmojiSelect()"
            style="margin-left: 10px; padding: 10px 14px; font-size: 1.0em;"
        >
            Change...
        </button>
    <br>

    </div>
<p style="font-size:0.9em">Your selected Earthling emoji.</p>
</div>


    <br><br>

    <h4>⚙️ Local area</h4>


<!-- Community -->
<div class="form-item">
    <label for="community_name" data-lang-id="032-community-tag">Community:</label><br>
    <div class="input-container">
        <!-- Visible field for community name with prepopulated value -->
        <input type="text" id="community_name" name="community_name"
               value="<?= htmlspecialchars($user_community_name, ENT_QUOTES); ?>"
               placeholder="Start typing your community..." required style="padding-left:45px;">
        <!-- Hidden field for community_id with prepopulated value -->
        <input  id="community_id"  type="hidden" name="community_id"
               value="<?= htmlspecialchars($community_id, ENT_QUOTES); ?>">
        <div id="community-pin" class="pin-icon">📌</div>
    </div>
    <div id="community-suggestions" class="suggestions-box"></div>
</div>


    <!-- Location -->
    <div class="form-item">
        <label for="location_full" data-lang-id="033-location-tag">Location:</label><br>
        <div class="input-container">
            <input type="text" id="location_full" name="location_full"
                   value="<?= htmlspecialchars($user_location_full, ENT_QUOTES); ?>"
                   aria-label="Location Full" required style="padding-left:45px;">
            <div id="loading-spinner" class="spinner" style="display: none;"></div>
            <div id="location-pin" class="pin-icon">📍</div>
        </div>
        <div id="location-error-required" class="form-field-error" data-lang-id="000-field-required-error">This field is required.</div>
    </div>

<!-- time-zone -->
<div class="form-item">
    <label for="time_zone" data-lang-id="033b-time-zone">Time Zone:</label><br>
    <div class="input-container">
        <select id="time_zone" name="time_zone" required style="padding-left:45px;">
            <?php
            $timezones = [
                'Etc/GMT+12' => 'Baker Island (UTC-12)',
                'Pacific/Pago_Pago' => 'Samoa (UTC-11)',
                'Pacific/Honolulu' => 'Hawaii (UTC-10)',
                'America/Anchorage' => 'Alaska (UTC-9)',
                'America/Los_Angeles' => 'Los Angeles (UTC-8)',
                'America/Denver' => 'Denver (UTC-7)',
                'America/Chicago' => 'Chicago (UTC-6)',
                'America/New_York' => 'New York (UTC-5)',
                // Fixed stray curly brace that previously broke PHP parsing
                'America/Toronto' => 'Toronto (UTC-5/UTC-4 DST)',
                'America/Halifax' => 'Halifax (UTC-4)',
                'America/Sao_Paulo' => 'São Paulo (UTC-3)',
                'Atlantic/South_Georgia' => 'South Georgia (UTC-2)',
                'Atlantic/Azores' => 'Azores (UTC-1)',
                'Etc/UTC' => 'UTC (Coordinated Universal Time)',
                'Europe/London' => 'London (UTC+0/UTC+1 DST)',
                'Europe/Berlin' => 'Berlin (UTC+1)',
                'Europe/Helsinki' => 'Helsinki (UTC+2)',
                'Europe/Moscow' => 'Moscow (UTC+3)',
                'Asia/Dubai' => 'Dubai (UTC+4)',
                'Asia/Karachi' => 'Karachi (UTC+5)',
                'Asia/Dhaka' => 'Dhaka (UTC+6)',
                'Asia/Jakarta' => 'Jakarta (UTC+7)',
                'Asia/Singapore' => 'Singapore (UTC+8)',
                'Asia/Shanghai' => 'Shanghai (UTC+8)',
                'Asia/Tokyo' => 'Tokyo (UTC+9)',
                'Australia/Sydney' => 'Sydney (UTC+10)',
                'Pacific/Guadalcanal' => 'Guadalcanal (UTC+11)',
                'Pacific/Auckland' => 'Auckland (UTC+12)'
            ];

            foreach ($timezones as $value => $label) {
                $selected = ($value === $time_zone) ? 'selected' : '';
                echo "<option value=\"" . htmlspecialchars($value) . "\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>
        <div id="loading-spinner" class="spinner" style="display: none;"></div>
        <div id="location-pin" class="pin-icon">⏲️</div>
    </div>
    <div id="location-error-required" class="form-field-error" data-lang-id="000-field-required-error">This field is required.</div>
</div>


    <!-- Location Watershed -->
    <div class="form-item">
        <label for="location_watershed" data-lang-id="032-watershed-tag">Watershed:</label><br>
        <div class="input-container">
            <input type="text" id="location_watershed" name="location_watershed"
                   value="<?= htmlspecialchars($user_location_watershed, ENT_QUOTES); ?>"
                   aria-label="Location Watershed" style="padding-left:45px;">
            <div id="loading-spinner-watershed" class="spinner" style="display: none;"></div>
            <div id="watershed-pin" class="pin-icon">💧</div>
        </div>
        <div id="watershed-suggestions" class="suggestions-box"></div>
    </div>

    <!-- Hidden latitude and longitude fields -->
    <input type="hidden" id="lat" name="latitude" value="<?= htmlspecialchars($user_location_lat, ENT_QUOTES); ?>">
    <input type="hidden" id="lon" name="longitude" value="<?= htmlspecialchars($user_location_long, ENT_QUOTES); ?>">

    <!-- Continent (Uneditable) -->
    <div class="form-item uneditable-select">
        <label for="continent_code" data-lang-id="021-continent">Continent:</label>
        <select name="continent_code" id="continent_code">
            <option value="" data-lang-id="022-select-continent">Select Continent</option>
            <?php foreach ($continents as $continent): ?>
                <option value="<?php echo $continent['continent_code']; ?>" <?php if ($continent['continent_code'] == $continent_code) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($continent['continent_name_en']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Country (Uneditable) -->
    <div class="form-item uneditable-select">
        <label for="country_id" data-lang-id="015-country">Country:</label>
        <select name="country_id" id="country_id">
            <option value="" data-lang-id="016-select-country">Select Country</option>
            <?php foreach ($countries as $country): ?>
                <option value="<?php echo $country['country_id']; ?>" <?php if ($country['country_id'] == $country_id) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($country['country_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<br><br>
    <!-- Save and Update Button -->
    <div style="margin:auto;text-align: center;margin-top:30px;">
        <button type="submit" class="submit-button enabled" aria-label="Save and update" data-lang-id="020-submit-button">💾 Save and Update</button>
    </div>

</form>


        </div>

    <!--EARTHEN ACCOUNT DB CHECK -->
<div class="form-container" style="padding-top:20px">
    <h2 data-lang-id="021-earthen-status-title">Earthen Newsletter Subscription Status</h2>
    <p><span data-lang-id="022-check-to-see">Check to see if your</span> <?php echo htmlspecialchars($email); ?> <span data-lang-id="023-is-subscribed">is subscribed to the Earthen newsletter</span></p>
    <div id="earthen-status-message" style="display:none;"></div>
    <button id="check-earthen-status-button" class="submit-button enabled" data-lang-id="024-check-earthen-button">Check Earthen Status</button>

    <!-- Status Yes -->
    <div id="earthen-status-yes" style="display:none;">
        <p data-lang-id="025-yes-subscribed" style="color:green">Yes! You're subscribed to the following newsletters:</p>
        <ul id="newsletter-list"></ul>
        <button id="unsubscribe-button" class="submit-button delete">Unsubscribe</button>
        <button id="manage-subscription-button" class="submit-button enabled">↗️ Manage Subscription</button>
    </div>

    <!-- Status No -->
    <div id="earthen-status-no" style="display:none;">
        <p data-lang-id="026-not-subscribed">You're not yet subscribed.</p>
        <a href="manage-subscriptions.php?id=<?php echo htmlspecialchars($buwana_id); ?>"  class="submit-button enabled" style="padding:4px;" data-lang-id="027-subscribe-button">Subscribe</a>
    </div>
</div>

<!-- CONNECTED APPS -->
<div class="form-container" style="padding-top:20px;" id="connected-apps-container">
    <h2>Your Apps</h2>
    <p>You've connected to the following Buwana apps:</p>
    <div id="connected-apps-row" class="connected-apps-row"></div>
</div>




<!-- DELETE ACCOUNT FORM -->
<div class="form-container" style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px;">
    <h2 data-lang-id="028-delete-heading">Delete Your Account</h2>
    <p data-lang-id="029-delete-warning">
        Warning: Deleting your account will permanently remove all your data and cannot be undone.
        This includes your Buwana account, your GoBrik profile, and your Earthen newsletter subscription.
    </p>

    <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
        <button
            type="button"
            class="submit-button delete"
            onclick="confirmDeletion('<?php echo htmlspecialchars($ecobricker_id); ?>', '<?php echo htmlspecialchars($lang); ?>')"
            data-lang-id="0010-delete-button"
        >
            Delete My Account
        </button>
    </div>
</div>



    </div> <!-- close form-container -->
</div> <!-- close form-submission-box -->
</div>

</div> <!--closes main-->

<!-- FOOTER STARTS HERE -->
<?php require_once("../footer-2025.php"); ?>


<script>
document.addEventListener('DOMContentLoaded', function () {

    function updateStatusMessage(status, message = '') {
        const updateStatusDiv = document.getElementById('update-status');
        const updateErrorDiv = document.getElementById('update-error');

        updateStatusDiv.innerHTML = '';
        updateErrorDiv.innerHTML = '';

        if (status === 'succeeded') {
            updateStatusDiv.innerHTML = "👍 Your user profile was updated!";
        } else if (status === 'failed') {
            updateErrorDiv.innerHTML = "🤔 Something went wrong with the update: " + message;
        } else {
            updateStatusDiv.innerHTML = "❓ Unexpected status: " + status;
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // 🚀 FORM SUBMIT LISTENER
    document.querySelector('#main-buwana-update').addEventListener('submit', async function (event) {
        event.preventDefault();

        const form = this;
        const formData = new FormData(form);

        // 🧠 If community_id is blank, try to fetch it from the server
        const communityId = formData.get('community_id');
        const communityName = formData.get('community_name');

        if (!communityId && communityName && communityName.length >= 3) {
            try {
                const response = await fetch('../scripts/get_community_id_by_name.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'name=' + encodeURIComponent(communityName)
                });

                const result = await response.json();

                if (result.success && result.community_id) {
                    console.log('✅ Matched community_id from API:', result.community_id);
                    formData.set('community_id', result.community_id);
                } else {
                    console.warn('❌ No matching community found for:', communityName);
                    formData.set('community_id', ''); // Optional: explicitly blank
                }
            } catch (err) {
                console.error('⚠️ Error fetching community ID:', err);
            }
        }

        // 📨 Submit the form via fetch
        fetch('profile_update_process.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'succeeded') {
                updateStatusMessage('succeeded');
            } else {
                const errorMessage = data.message || 'Unknown error occurred.';
                updateStatusMessage('failed', errorMessage);
            }
        })
        .catch(error => {
            console.error('🚨 Error submitting form:', error);
            updateStatusMessage('failed', error.message);
        });
    });

    // Check for status message from URL and handle it
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    if (status) {
        updateStatusMessage(status);
    }


    // 🔗 Fetch connected apps and display logos
    function updateConnectedAppLogos() {
        const mode = document.documentElement.getAttribute('data-theme') || 'light';
        document.querySelectorAll('.connected-app-logo').forEach(el => {
            const lightLogo = el.getAttribute('data-light-logo');
            const darkLogo = el.getAttribute('data-dark-logo');
            el.style.backgroundImage = mode === 'dark' ? `url('${darkLogo}')` : `url('${lightLogo}')`;
        });
    }

    fetch('../api/get_user_app_connections.php')
        .then(resp => resp.json())
        .then(data => {
            if (data.logged_in && Array.isArray(data.apps)) {
                const row = document.getElementById('connected-apps-row');
                if (row) {
                    row.innerHTML = '';
                    data.apps.forEach(app => {
                        const link = document.createElement('a');
                        link.className = 'connected-app-logo';
                        link.setAttribute('data-light-logo', app.app_icon_url);
                        link.setAttribute('data-dark-logo', app.app_icon_url);
                        link.setAttribute('alt', app.app_display_name + ' App Logo');
                        link.setAttribute('title', `${app.app_display_name} ${app.app_version} | ${app.app_slogan}`);
                        link.href = app.app_login_url;
                        link.target = '_blank';
                        row.appendChild(link);
                    });
                    updateConnectedAppLogos();
                }
            }
        });

    const toggle = document.getElementById('dark-mode-toggle-5');
    if (toggle) {
        toggle.addEventListener('colorschemechange', updateConnectedAppLogos);
    }

    updateConnectedAppLogos();

});
</script>




<script>

// CHECK EARTHEN SUBSCRIPTION
document.getElementById('check-earthen-status-button').addEventListener('click', function() {
    var email = '<?php echo addslashes($email); ?>';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../scripts/earthen_subscribe_functions.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            console.log('Server response:', xhr.responseText); // Log the full server response
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);

                    var statusYes = document.getElementById('earthen-status-yes');
                    var statusNo = document.getElementById('earthen-status-no');
                    var newsletterList = document.getElementById('newsletter-list');
                    var checkButton = document.getElementById('check-earthen-status-button');

                    if (response.status === 'success') {
                        if (response.registered) {
                            // Hide the check status button and show the subscribed status
                            checkButton.style.display = 'none';
                            statusYes.style.display = 'block';

                            // Clear any existing list items
                            newsletterList.innerHTML = '';

                            // Store the member ID for unsubscribing
                            window.memberId = response.member_id;

                            // Add the newsletters to the list
                            if (response.newsletters && response.newsletters.length > 0) {
                                response.newsletters.forEach(function(newsletter) {
                                    var li = document.createElement('li');
                                    li.textContent = newsletter; // Set text content correctly
                                    newsletterList.appendChild(li);
                                });
                            } else {
                                newsletterList.innerHTML = '<li>No newsletters found.</li>';
                            }
                        } else {
                            // Hide the check status button and show the not subscribed status
                            checkButton.style.display = 'none';
                            statusNo.style.display = 'block';
                        }
                    } else {
                        console.error(response.message);
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            } else {
                console.error('Failed to fetch subscription status. HTTP Status:', xhr.status);
            }
        }
    };

    xhr.send('email=' + encodeURIComponent(email));
});

// Event listener for the unsubscribe button click
document.getElementById('unsubscribe-button').addEventListener('click', unsubscribe);

// Function to handle the unsubscribe button click
function unsubscribe() {
    if (confirm("Are you sure you want to do this? We'll permanently unsubscribe you from all Earthen newsletters. Note, this will not affect your GoBrik or Buwana accounts.")) {
        var email = '<?php echo addslashes($email); ?>'; // Get email from PHP

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../scripts/earthen_subscribe_functions.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Event listener for handling the response
        xhr.addEventListener('readystatechange', function () {
            if (xhr.readyState === 4) {
                console.log('Unsubscribe response:', xhr.responseText); // Log the server response

                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            alert(response.message);
                            document.getElementById('unsubscribe-button').disabled = true; // Disable button to indicate success
                        } else {
                            alert('Error: ' + response.message);
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        alert('Unexpected response format. Please try again.');
                    }
                } else {
                    alert('Failed to unsubscribe. Please try again.');
                }
            }
        });

        // Send email and unsubscribe parameters to the server
        xhr.send('email=' + encodeURIComponent(email) + '&unsubscribe=true');
    }
}



// Retrieve buwana_id dynamically (e.g., from a hidden field or data attribute)
const buwana_id = <?php echo json_encode($buwana_id); ?>;

// Event listener for the manage subscription button
document.getElementById('manage-subscription-button').addEventListener('click', function() {
    const url = 'manage-subscriptions.php?id=' + encodeURIComponent(buwana_id) + '&type=update';
   window.open(url, '_blank');
});





/*COMMUNITY*/
document.addEventListener('DOMContentLoaded', function() {
    const communityNameInput = document.getElementById('community_name');
    const communityIdInput = document.getElementById('community_id');
    const suggestionsBox = document.getElementById('community-suggestions');

    // Add an event listener to trigger AJAX search when user types in the community field
    communityNameInput.addEventListener('input', function() {
        const query = this.value;

        // If the user has typed at least 3 characters, trigger the AJAX search
        if (query.length >= 3) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '../api/search_communities.php?query=' + encodeURIComponent(query), true);
            xhr.send();

            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    // Show list of matching communities
                    showCommunitySuggestions(response);
                }
            };

            xhr.send('query=' + encodeURIComponent(query));
        } else {
            suggestionsBox.innerHTML = ''; // Clear suggestions if query is less than 3 characters
        }
    });

    // Function to display the community suggestions
    function showCommunitySuggestions(communities) {
        // Clear previous suggestions
        suggestionsBox.innerHTML = '';

        communities.forEach(function(community) {
            const suggestionItem = document.createElement('div');
            suggestionItem.textContent = community.com_name;
            suggestionItem.classList.add('suggestion-item');

            // When a suggestion is clicked, set the community name and ID
            suggestionItem.addEventListener('click', function() {
                communityNameInput.value = community.com_name; // Set community name in input
                communityIdInput.value = community.community_id;     // Set ID in hidden input
                suggestionsBox.innerHTML = '';  // Clear suggestions after selection
            });

            suggestionsBox.appendChild(suggestionItem);
        });
    }

    // Ensure `community_id` is cleared if user changes `community_name` without selecting from suggestions
    communityNameInput.addEventListener('input', function() {
        communityIdInput.value = '';  // Clear community_id when typing in community_name
    });
});


</script>

<script>
$(function () {
    let debounceTimer;

    // --- SECTION 1: Show/hide pin icon based on input value and loading state ---
    // This function manages the visibility of the location pin based on whether
    // the input field is empty or loading
    function updatePinIconVisibility() {
        if ($("#location_full").val().trim() === "" || $("#loading-spinner").is(":hidden")) {
            $("#location-pin").show();
        } else {
            $("#location-pin").hide();
        }
    }

    // --- SECTION 2: Initialize autocomplete for location search using OpenStreetMap Nominatim API ---
    // This section uses jQuery UI Autocomplete to fetch location suggestions from the OpenStreetMap Nominatim API.
    // It debounces the search query and sends a request to the API, returning location results.
    $("#location_full").autocomplete({
        source: function (request, response) {
            $("#loading-spinner").show();
            $("#location-pin").hide(); // Hide the pin icon when typing starts

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                $.ajax({
                    url: "https://nominatim.openstreetmap.org/search",
                    dataType: "json",
                    headers: {
                        'User-Agent': 'ecobricks.org'
                    },
                    data: {
                        q: request.term,
                        format: "json"
                    },
                    success: function (data) {
                        $("#loading-spinner").hide();
                        updatePinIconVisibility(); // Show the pin when data has loaded

                        // Map the returned data to an array of display_name, lat, and lon
                        response($.map(data, function (item) {
                            return {
                                label: item.display_name,
                                value: item.display_name,
                                lat: item.lat,
                                lon: item.lon
                            };
                        }));
                    },
                    error: function (xhr, status, error) {
                        $("#loading-spinner").hide();
                        updatePinIconVisibility(); // Show the pin when an error occurs
                        console.error("Autocomplete error:", error);
                        response([]);
                    }
                });
            }, 300);
        },
        select: function (event, ui) {
            // When a location is selected, populate the hidden lat/lon fields
            console.log('Selected location:', ui.item);
            $('#lat').val(ui.item.lat);
            $('#lon').val(ui.item.lon);

            updatePinIconVisibility(); // Show pin icon after selection
        },
        minLength: 3
    });

    // Update pin icon visibility when the user types in the location input field
    $("#location_full").on("input", function () {
        updatePinIconVisibility();
    });

    // --- SECTION 3: Form submission handling ---
    // Log the latitude and longitude when the form is submitted.
    $('#main-buwana-update').on('submit', function () {
        console.log('Latitude:', $('#lat').val());
        console.log('Longitude:', $('#lon').val());
        // Additional submit handling if needed
    });
});
</script>



<script>

    /*WATERSHED*/



$(function () {
    let debounceTimer;

    // --- SECTION 1: Show/hide watershed pin icon based on input value and loading state ---
    function updateWatershedPinVisibility() {
        if ($("#location_watershed").val().trim() === "" || $("#loading-spinner-watershed").is(":hidden")) {
            $("#watershed-pin").show();
        } else {
            $("#watershed-pin").hide();
        }
    }

    // --- SECTION 2: Fetch nearby rivers using Overpass API ---
    // This function is called when the user types in the watershed field.
    // It sends a request to the Overpass API to fetch rivers around the given coordinates.
    function fetchNearbyRivers(lat, lon) {
        $("#loading-spinner-watershed").show();
        $("#watershed-pin").hide();

        const overpassUrl = `https://overpass-api.de/api/interpreter?data=[out:json];(way["waterway"="river"](around:5000,${lat},${lon});relation["waterway"="river"](around:5000,${lat},${lon}););out tags;`;

        $.get(overpassUrl, function (data) {
            $("#loading-spinner-watershed").hide();
            updateWatershedPinVisibility();

            const rivers = data.elements;
            const uniqueRivers = new Set(); // Use a set to track unique river names
            const suggestions = [];

            // Loop through the river data and add unique river names to suggestions
            rivers.forEach(river => {
                const riverName = river.tags.name;
                if (riverName && !uniqueRivers.has(riverName) && !riverName.toLowerCase().includes("unnamed")) {
                    uniqueRivers.add(riverName);
                    suggestions.push({
                        label: riverName,
                        value: riverName
                    });
                }
            });

            // Update autocomplete suggestions for the watershed input
            $("#location_watershed").autocomplete({
                source: suggestions,
                minLength: 0 // Show suggestions even if no characters are typed
            }).autocomplete("search", ""); // Trigger the display of suggestions
        }).fail(function () {
            console.error("Failed to fetch data from Overpass API.");
            $("#loading-spinner-watershed").hide();
            updateWatershedPinVisibility();
        });
    }

    // --- SECTION 3: Autocomplete and fetching rivers based on user location ---
    $("#location_watershed").on("focus", function () {
        const lat = $("#lat").val();
        const lon = $("#lon").val();

        if (lat && lon) {
            fetchNearbyRivers(lat, lon);
        } else {
            console.warn("Latitude and Longitude are required to fetch nearby rivers.");
        }
    });

    // Update watershed pin icon visibility when the user types in the watershed input field
    $("#location_watershed").on("input", function () {
        updateWatershedPinVisibility();
    });
});
</script>


<script>
function earthlingEmojiSelect() {
    const modal = document.getElementById('form-modal-message');
    const modalBox = document.getElementById('modal-content-box');

    modal.style.display = 'flex';
    modalBox.style.flexFlow = 'column';
    document.getElementById('page-content')?.classList.add('blurred');
    document.getElementById('footer-full')?.classList.add('blurred');
    document.body.classList.add('modal-open');

    modalBox.style.maxHeight = '80vh';
    modalBox.style.overflowY = 'auto';

    const emojiOptions = [
        // Mammals
        '🐶','🐺','🦊','🐱','🐯','🦁','🐮','🐷','🐸','🐵','🦍','🦧','🐔','🐧','🦇','🐻','🐨','🐼','🦘','🦡','🦨','🦥','🦦','🦣','🦌','🦬','🐐','🐑','🐎','🫏','🐪','🐫','🦙','🦒','🦓','🐘','🐖','🐄','🐂',
        // Marine
        '🐬','🐳','🐋','🐟','🐠','🐡','🦈','🐙','🦑','🦐','🦀','🪼',
        // Reptiles & Amphibians
        '🐊','🦎','🐍','🐢','🦕','🦖',
        // Birds
        '🐦','🐧','🕊️','🦅','🦆','🦢','🦉','🦜','🪶',
        // Insects
        '🐝','🐞','🦋','🐛','🦗','🪲','🪳','🦟','🪰','🪱',
        // Plants
        '🌱','🌿','☘️','🍀','🎋','🌵','🌴','🌲','🌳','🪴','🪹','🪺',
        // Human-like characters (no faces)
        '🧑','🧒','🧓','👩','👨','👧','👦',
        '🧕','🧔','👮','🕵️','💂','🧙','🧝','🧛','🧟','🧞','🧜','🧚',
        '🧑‍🚀','🧑‍🔬','🧑‍🌾','🧑‍🏫','🧑‍🎨','🧑‍🚒','🧑‍🍳','🧑‍⚖️','🧑‍💻','🧑‍🔧','🧑‍🏭'
    ];

    // Build emoji grid
    let emojiHTML = `<h4 style="text-align:center;">Your Buwana Totem</h4>
    <p style="text-align:center;">Please choose your fellow Earthling emoji to best represent who you are.<br>
    This emoji totem will accompany your user name when you're logged in.</p>
    <div id="emoji-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); gap: 10px; padding: 10px; text-align: center; font-size: 28px;">`;

    emojiOptions.forEach(emoji => {
        emojiHTML += `<div class="emoji-option" style="cursor: pointer;" onclick="selectEarthlingEmoji('${emoji}')">${emoji}</div>`;
    });

    emojiHTML += `</div>`;

    modalBox.innerHTML = emojiHTML;
}
</script>

<script>
function selectEarthlingEmoji(emoji) {
    // Set emoji to the form input
    const emojiField = document.getElementById('earthling_emoji');
    if (emojiField) {
        emojiField.value = emoji;
    }

    // Close the modal
    closeInfoModal(); // Assumes GoBrik's modal close function
}
</script>

<script>
function confirmDeletion(ecobrickerId, lang = 'en') {
    if (confirm("Are you certain you wish to delete your account? This cannot be undone.")) {
        if (confirm("Ok. We will delete your account! Note that this does not affect ecobrick data that has been permanently archived in the brikchain. If you have a Buwana account and/or a subscription to our Earthen newsletter it will also be deleted.")) {

            // Send request to delete the user
            fetch('../api/delete_accounts.php?id=' + encodeURIComponent(ecobrickerId))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect; // 🔁 Redirect to goodbye.php
                    } else {
                        alert("Error: " + (data.error || "Unknown error occurred while deleting your account."));
                    }
                })
                .catch(err => {
                    console.error("Deletion error:", err);
                    alert("Something went wrong while deleting your account.");
                });
        }
    }
}
</script>

<?php require_once ("../scripts/app_modals.php");?>

</body>

</html>
