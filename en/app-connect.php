<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'app-connect';
$version = '0.7772';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

error_reporting(E_ALL);
ini_set('display_errors', 1);


// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'app-connect';
$version = '0.7772';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

// --- Validate inputs
$buwana_id = $_GET['id'] ?? null;
$client_id = $app_info['client_id'] ?? null;
$redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';

// Determine if this is the user's first connection attempt for this app
$is_first_time_connection = true;
if ($buwana_id && $client_id) {
    $connection_check_stmt = $buwana_conn->prepare("SELECT 1 FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1");
    if ($connection_check_stmt) {
        $connection_check_stmt->bind_param('is', $buwana_id, $client_id);
        $connection_check_stmt->execute();
        $connection_check_stmt->store_result();
        if ($connection_check_stmt->num_rows > 0) {
            $is_first_time_connection = false;
        }
        $connection_check_stmt->close();
    }
}

// --- Conditional validation and JavaScript fallback handling
if (!$buwana_id && !$client_id) {
    echo "<script>alert('Sorry! Something went wrong. Please select your Buwana app and login again.'); window.location.href = 'index.php';</script>";
    exit();
}

if (!$buwana_id || !is_numeric($buwana_id)) {
    $safe_client_id = urlencode($client_id);
    echo "<script>alert(\"Sorry! We couldn't discern who is logging in. Please try again.\"); window.location.href = 'login.php?app=$safe_client_id';</script>";
    exit();
}

if (!$client_id) {
    $safe_buwana_id = urlencode($buwana_id);
    echo "<script>alert(\"Sorry! Seems like the target app wasn't set! Please select the app you want to use and try logging in again.\"); window.location.href = 'index.php?id=$safe_buwana_id';</script>";
    exit();
}

// 🔗 Get app info
$app_display_name = $app_info['app_display_name'] ?? 'Your App';
$redirect_url = $app_info['app_dashboard_url'] ?? '/';


// 🔍 Fetch user info
$first_name = 'User';
// Default earthling emoji if none is stored
$earthling_emoji = '🌏';
$email = 'email';
$stmt = $buwana_conn->prepare("SELECT email, first_name, earthling_emoji FROM users_tb WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($email, $first_name, $earthling_emoji_db);
    $stmt->fetch();
    $stmt->close();
    // Use stored emoji if available
    if (!empty($earthling_emoji_db)) {
        $earthling_emoji = $earthling_emoji_db;
    }
}

// Determine requested OAuth scopes
$requested_scopes = array_filter(array_map('trim', explode(',', $app_info['scopes'] ?? '')));
$scope_descriptions = [
    'openid'                    => 'Unique identifier for user login',
    'email'                     => 'Access to user email address',
    'profile'                   => 'Basic profile information',
    'address'                   => 'User postal address details',
    'phone'                     => 'Telephone number information',
    'buwana:bioregion'          => 'User watershed & bioregion',
    'buwana:earthlingEmoji'     => 'Preferred emoji avatar',
    'buwana:community'          => 'Community membership',
    'buwana:location.continent' => 'Continent of residence',
];

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">

<?php require_once ("../meta/$page-$lang.php");?>

<STYLE>

.form-container {
padding-top: 10px !important;
}

.app-connect-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin: 30px auto;
  width: 90%;
  max-width: 500px;
}

.icon-box {
  width: 80px;
  height: 80px;
  border: 1px solid var(--subdued-text);
  background-color: var(--lighter);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.icon-box {
    width: 100px;
    height: 100px;
    border: 1px solid var(--subdued-text);
    background-color: var(--lighter);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.icon-box img {
    width: 80%;
    height: 80%;
    object-fit: contain;
}

.connect-arrow {
  position: relative;
  width: 50px;
  height: 50px;
  animation: chevronPulse 1.5s ease-in-out infinite;
  transform-origin: center;
  margin-left: -10px;
}

/* Chevron arms */
.connect-arrow::before,
.connect-arrow::after {
  content: '';
  position: absolute;
  width: 3px;
  height: 20px;
  background-color: green;
  transform-origin: bottom right;
}

.connect-arrow::before {
  top: 5px;
    left: 32px;
    transform: rotate(-55deg);
}

.connect-arrow::after {
  top: 5px;
    left: 32px;
    transform: rotate(-125deg);
}

/* Chevron pulse animation */
@keyframes chevronPulse {
  0% {
    transform: translateX(0) scaleY(1.1); /* Left + stretch */
  }
  30% {
    transform: translateX(10px) scaleY(0.9); /* Right + compress */
  }
  100% {
    transform: translateX(0) scaleY(1.1); /* Back to left + stretch */
  }
}

/* Scope list styling */
.scope-list {
  list-style: none;
  margin: 20px auto;
  padding: 0;
  max-width: 500px;
  border: 1px solid var(--subdued-text);

  border-radius: 6px;
  background: var(--form-background, #f6f8fa);
  text-align: left;
}
.scope-list li {
  display: flex;
  align-items: flex-start;
  padding: 8px 12px;
  border-bottom: 1px solid var(--subdued-text);

  font-size: 0.95em;
}
.scope-list li:last-child {
  border-bottom: none;
}
.scope-icon {
  margin-right: 8px;

  color: green;

  font-size: 1.1em;
}
.scope-info {
  display: flex;
  flex-direction: column;
}
.scope-name {
  font-weight: 600;
  color: var(--text-color);
}
.scope-desc {
  font-size: 0.85em;
  color: var(--subdued-text);
}
.scope-sub {
  font-size: 0.8em;
  color: var(--subdued-text);
}






</STYLE>

<?php require_once ("../header-2025.php");?>  <!-- this file closes HEAD-->

<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div style="text-align:center;width:100%;margin:auto;">
        <div class="app-connect-container">
          <div class="icon-box earthling-icon">
            <?= htmlspecialchars($earthling_emoji) ?>
          </div>
          <div class="connect-arrow"></div>
          <div class="icon-box app-icon"
               title="<?= htmlspecialchars($app_info['app_display_name']) ?> <?= htmlspecialchars($app_info['app_version']) ?> | <?= htmlspecialchars($app_info['app_slogan']) ?>">
              <img src="<?= htmlspecialchars($app_info['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app_info['app_display_name']) ?> App Icon" />
          </div>

        </div>

      <h1>
        <span data-lang-id="001-first-time-to-connect">Connect to</span> <?= htmlspecialchars($app_info['app_display_name']) ?>
      </h1>
  <p style="text-align:center; margin-top: 10px;color:green;font-size:1em;">✅ <?= htmlspecialchars($app_info['app_display_name']) ?> <span data-lang-id="001-authorized-app">is an authorized Buwana app</span></p>



        <p style="margin-top:15px;margin-bottom:20px;">
             <?= htmlspecialchars($first_name) ?>, <span data-lang-id="002-looks-like"> it looks like you are trying to login to </span> <?= htmlspecialchars($app_info['app_display_name']) ?><?php if ($is_first_time_connection): ?><span data-lang-id="002b-first-time"> for the first time!  Nice. 👍</span><?php endif; ?>
             <span data-lang-id="003-to-do-so">To do so, we will connect your Buwana account to </span> <?= htmlspecialchars($app_info['app_display_name']) ?> <span data-lang-id="003b-and">and allow it to access the following scopes:</span>
        </p>

        <?php
            $profile_group = ['openid','email','profile','address','phone','buwana:earthlingEmoji','buwana:location.continent'];
            $display_scopes = ['openId','Name','email','profile','phone','buwana:earthlingEmoji','buwana:location_continent'];
            $used_profile_scopes = array_intersect($profile_group, $requested_scopes);
            $other_scopes = array_intersect($requested_scopes, ['buwana:community','buwana:bioregion']);
        ?>
        <?php if ($requested_scopes): ?>
        <ul class="scope-list">
            <?php if ($used_profile_scopes): ?>
            <li>
                <span class="scope-icon">🌐</span>
                <span class="scope-info">
                    <span class="scope-name" data-lang-id="004-buwana-profile">Buwana Profile</span>
                    <span class="scope-desc" data-lang-id="005-data-essentials">Essential user data for logging in and using the app...</span>
                    <span class="scope-sub">
                        <?php
                            $display_used = [];
                            foreach ($display_scopes as $sc) {
                                $key = str_replace(['openId','Name','location_continent'], ['openid','name','location.continent'], $sc);
                                if (in_array($key, $used_profile_scopes)) $display_used[] = $sc;
                            }
                            echo htmlspecialchars(implode(', ', $display_used));
                        ?>
                    </span>
                </span>
            </li>
            <?php endif; ?>
            <?php foreach ($other_scopes as $scope): ?>
            <li>
                <span class="scope-icon">ℹ️</span>

                <span class="scope-info">
                    <span class="scope-name"><?= htmlspecialchars($scope) ?></span>
                    <span class="scope-desc"><?= htmlspecialchars($scope_descriptions[$scope] ?? '') ?></span>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <p><span data-lang-id="004-will-be-granted">Connect to authorize and get rocking on </span> <?= htmlspecialchars($app_info['app_display_name']) ?></span>.</p>



        <form id="user-signup-form" method="post" action="app-connect_process.php" novalidate>
            <input type="hidden" name="buwana_id" value="<?= htmlspecialchars($buwana_id) ?>">
            <input type="hidden" name="client_id" value="<?= htmlspecialchars($app_info['client_id']) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
 <!-- Kick-Ass Submit Button -->
            <div id="submit-section" class="submit-button-wrapper">


                <button type="submit" id="submit-button" class="kick-ass-submit">
                    <span id="submit-button-text" data-lang-id="009-connect-button">Connect</span>
                    <span id="submit-emoji" class="submit-emoji" style="display: none;"></span>
                </button>
            </div>

            <div class="form-caption" >


            <p style="text-align:center; margin-top: 10px;font-size:0.9em;">By connecting you agree to the <a href="#" onclick="openTermsModal(); return false;"><span><?= htmlspecialchars($app_info['app_display_name']) ?></span> <span data-lang-id="1000-terms-of-use">Terms of Use</span></a></p>
            </div>
        </form>
    </div>
  </div>
  <div id="browser-back-link" style="font-size: small; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: -20px;">
      <p style="line-height:1.3em;"><span data-lang-id="010-no-connect"> ↩ Or return to the </span> <a href="<?= htmlspecialchars($app_info['app_url']) ?>"><?= htmlspecialchars($app_info['app_display_name']) ?> <span data-lang-id="000-home">home</span></a>
      </p>
  </div>
</div>


</div>


<?php require_once ("../scripts/app_modals.php");?>

<?php require_once ("../footer-2025.php"); ?>


</body>
</html>
