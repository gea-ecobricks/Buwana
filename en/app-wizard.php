<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../vendor/autoload.php';
require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$scope_options = [
    'openid',
    'email',
    'profile',
    'address',
    'phone',
    'buwana:bioregion',
    'buwana:earthlingEmoji',
    'buwana:community',
    'buwana:location.continent'
];

$scope_descriptions = [
    'openid'                  => 'Unique identifier for user login',
    'email'                   => 'Access to user email address',
    'profile'                 => 'Basic profile information',
    'address'                 => 'User postal address details',
    'phone'                   => 'Telephone number information',
    'buwana:bioregion'        => 'User watershed & bioregion',
    'buwana:earthlingEmoji'   => 'Preferred emoji avatar',
    'buwana:community'        => 'Community membership',
    'buwana:location.continent' => 'Continent of residence'
];

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'app-wizard.php';
$version = '0.2';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

// Grab JWT and client_id from session
$jwt = $_SESSION['jwt'] ?? null;
$client_id = $_SESSION['client_id'] ?? ($_GET['app'] ?? ($_GET['client_id'] ?? null));

if (!$jwt || !$client_id) {
    $query = ['status' => 'loggedout', 'redirect' => $page];
    if ($client_id) $query['app'] = $client_id;
    header('Location: login.php?' . http_build_query($query));
    exit();
}

// Get the app's public key
$stmt = $buwana_conn->prepare("SELECT jwt_public_key FROM apps_tb WHERE client_id = ?");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->bind_result($public_key);
$stmt->fetch();
$stmt->close();

try {
    $decoded = JWT::decode($jwt, new Key($public_key, 'RS256'));
    $sub = $decoded->sub ?? '';
    $buwana_id = 0;
    if (preg_match('/^buwana_(\d+)$/', $sub, $m)) {
        $buwana_id = (int)$m[1];
    } else {
        $stmt = $buwana_conn->prepare("SELECT buwana_id FROM users_tb WHERE open_id = ? LIMIT 1");
        $stmt->bind_param('s', $sub);
        $stmt->execute();
        $stmt->bind_result($buwana_id);
        $stmt->fetch();
        $stmt->close();
    }
    $_SESSION['buwana_id'] = $buwana_id;
    $first_name = $decoded->given_name ?? '';
    $earthling_emoji = $decoded->{'buwana:earthlingEmoji'} ?? '';
} catch (Exception $e) {
    error_log("JWT Decode Error: " . $e->getMessage());
    $query = ['status' => 'loggedout', 'redirect' => $page];
    if ($client_id) $query['app'] = $client_id;
    header('Location: login.php?' . http_build_query($query));
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/app-wizard-en.php"); ?>
    <?php require_once("../includes/app-wizard-en.php"); ?>
    <style>
      .top-wrapper {
        background: var(--darker-lighter);
      }
      .form-item.float-label-group {
        border-radius: 10px 10px 5px 5px;
        padding-bottom: 10px;
      }
      .scopes-list {
        display: flex;
        flex-direction: column;
      }
      .scope-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        border-bottom: 1px solid var(--subdued-text);
      }
      .scope-info {
        display: flex;
        flex-direction: column;
        color: var(--text-color);
      }
      .scope-caption {
        font-size: 0.9em;
        color: grey;
      }
      .scope-subscopes {
        font-size: 0.85em;
        color: var(--subdued-text);
      }
      .hidden-scope {
        display: none;
      }
    </style>
<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div class="top-wrapper">
      <div>
        <div class="login-status"><?= htmlspecialchars($earthling_emoji) ?> Logged in as <?= htmlspecialchars($first_name) ?></div>
      </div>
      <div style="display:flex;flex-flow:column;margin-left:auto;">
          <div style="display:flex;align-items:center;margin-left:auto;">
                <div style="text-align:right;margin-right:10px;">
                  <div class="page-name">New App Setup</div>
                  <div class="client-id">Create New App</div>
                </div>
                <span style="font-size:60px;">✨</span>
          </div>
      </div>
    </div>
    <div class="breadcrumb" style="text-align:right;margin-left:auto;margin-right: 15px;">
                          <a href="dashboard.php">Dashboard</a> &gt;
                          New App
                        </div>
    <div id="update-status" style="font-size:1.3em; color:green;padding:10px;margin-top:10px;"></div>
    <div id="update-error" style="font-size:1.3em; color:red;padding:10px;margin-top:10px;"></div>
    <h2>New App Setup</h2>
    <p>Follow the steps to register your new application.</p>
    <form id="appWizardForm" method="post" action="../scripts/create_app.php">
      <div id="step1" class="wizard-step active">
        <h3>Step 1: Basic Info</h3>
        <div class="form-item float-label-group">
          <input type="text" id="app_name" name="app_name" aria-label="App Name" required placeholder=" ">
          <label for="app_name">App Name</label>
          <p class="form-caption">Internal name for your app</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="redirect_uris" name="redirect_uris" aria-label="Redirect URIs" required placeholder=" ">
          <label for="redirect_uris">Redirect URIs</label>
          <p class="form-caption">Comma separated OAuth redirect URLs</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_login_url" name="app_login_url" aria-label="App Login URL" placeholder=" ">
          <label for="app_login_url">App Login URL</label>
          <p class="form-caption">Where users login to your app</p>
        </div>
        <div class="form-item" style="border-radius:10px 10px 5px 5px;padding-bottom:10px;">
          <label for="scopes" style="padding:7px;"><h5>Scopes</h5></label>
          <div id="scopes" class="scopes-list">
<?php
  $profile_scopes = ['openid','email','profile','phone','buwana:earthlingEmoji','buwana:location.continent'];
?>
            <div class="scope-row">
              <div class="scope-info">
                <span>🌐 <b>Buwana Profile</b></span>
                <span class="scope-caption">Essential user data for logging in and using the app</span>
                <span class="scope-subscopes">openId, Name, email, profile, phone, buwana:earthlingEmoji, buwana:location_continent</span>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" class="scope-checkbox scope-group" data-scopes="<?= implode(',', $profile_scopes) ?>" />
                <span class="slider"></span>
              </label>
<?php foreach ($profile_scopes as $sc): ?>
              <input type="checkbox" class="scope-checkbox hidden-scope" name="scopes[]" value="<?= htmlspecialchars($sc) ?>" style="display:none;" />
<?php endforeach; ?>
            </div>
<?php foreach (['buwana:community','buwana:bioregion'] as $scope): ?>
            <div class="scope-row">
              <div class="scope-info">
                <span>ℹ️ <b><?= htmlspecialchars($scope) ?></b></span>
                <span class="scope-caption">
                  <?= htmlspecialchars($scope_descriptions[$scope] ?? '') ?>
                </span>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" class="scope-checkbox" name="scopes[]" value="<?= htmlspecialchars($scope) ?>" />
                <span class="slider"></span>
              </label>
            </div>
<?php endforeach; ?>
          </div>
          <p class="form-caption">OAuth scopes requested by your app</p>
          <div id="scopes-error-required" class="form-field-error" style="display:none;">This field is required.</div>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_domain" name="app_domain" aria-label="App Domain" placeholder=" ">
          <label for="app_domain">App Domain</label>
          <p class="form-caption">Primary domain name</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_url" name="app_url" aria-label="App URL" placeholder=" ">
          <label for="app_url">App URL</label>
          <p class="form-caption">Public homepage of your app</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_favicon" name="app_favicon" aria-label="App Favicon URL" placeholder=" ">
          <label for="app_favicon">Set App Favicon</label>
          <p class="form-caption">URL for your app's favicon</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_dashboard_url" name="app_dashboard_url" aria-label="App Dashboard URL" placeholder=" ">
          <label for="app_dashboard_url">App Dashboard URL</label>
          <p class="form-caption">Where users manage their account</p>
        </div>
        <div class="form-item float-label-group">
          <textarea id="app_description" name="app_description" aria-label="Description" rows="3" placeholder=" "></textarea>
          <label for="app_description">Description</label>
          <p class="form-caption">Short summary of your app</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_version" name="app_version" aria-label="Version" placeholder=" ">
          <label for="app_version">Version</label>
          <p class="form-caption">Current version</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_display_name" name="app_display_name" aria-label="Display Name" placeholder=" ">
          <label for="app_display_name">Display Name</label>
          <p class="form-caption">Name shown to users</p>
        </div>
        <div class="form-item float-label-group">
          <input type="email" id="contact_email" name="contact_email" aria-label="Contact Email" placeholder=" ">
          <label for="contact_email">Contact Email</label>
          <p class="form-caption">Where we can reach you</p>
        </div>
      </div>
      <div id="step2" class="wizard-step">
        <h3>Step 2: Text Strings</h3>
        <div class="form-item float-label-group">
          <input type="text" id="app_slogan" name="app_slogan" aria-label="Slogan" placeholder=" ">
          <label for="app_slogan">Slogan</label>
          <p class="form-caption">Tagline for your app</p>
        </div>
        <div class="form-item float-label-group">
          <textarea id="app_terms_txt" name="app_terms_txt" aria-label="Terms of Use" rows="6" placeholder=" "></textarea>
          <label for="app_terms_txt">Terms of Use</label>
          <p class="form-caption">Short version of your terms</p>
        </div>
        <div class="form-item float-label-group">
          <textarea id="app_privacy_txt" name="app_privacy_txt" aria-label="Privacy Text" rows="6" placeholder=" "></textarea>
          <label for="app_privacy_txt">Privacy Text</label>
          <p class="form-caption">Short privacy notice</p>
        </div>
        <div class="form-item float-label-group">
          <textarea id="app_emojis_array" name="app_emojis_array" aria-label="Emoji List" rows="4" placeholder=" "></textarea>
          <label for="app_emojis_array">Emoji List</label>
          <p class="form-caption">Please format your list of emoji like this "🌒", "🌓", "🌔", "🌕","🌖", "🌗", "🌘", "🌑"</p>
        </div>
      </div>
      <div id="step3" class="wizard-step">
        <h3>Step 3: Basic Graphics</h3>
        <div class="form-item float-label-group">
          <input type="text" id="app_logo_url" name="app_logo_url" aria-label="Logo URL" placeholder=" ">
          <label for="app_logo_url">Logo URL</label>
          <p class="form-caption">Light mode logo</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_logo_dark_url" name="app_logo_dark_url" aria-label="Dark Logo URL" placeholder=" ">
          <label for="app_logo_dark_url">Dark Logo URL</label>
          <p class="form-caption">Dark mode logo</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_square_icon_url" name="app_square_icon_url" aria-label="Square Icon URL" placeholder=" ">
          <label for="app_square_icon_url">Square Icon URL</label>
          <p class="form-caption">App icon</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_wordmark_url" name="app_wordmark_url" aria-label="Wordmark URL" placeholder=" ">
          <label for="app_wordmark_url">Wordmark URL</label>
          <p class="form-caption">Light mode wordmark</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="app_wordmark_dark_url" name="app_wordmark_dark_url" aria-label="Dark Wordmark URL" placeholder=" ">
          <label for="app_wordmark_dark_url">Dark Wordmark URL</label>
          <p class="form-caption">Dark mode wordmark</p>
        </div>
      </div>
      <div id="step4" class="wizard-step">
        <h3>Step 4: Signup Graphics</h3>
        <div class="form-item float-label-group">
          <input type="text" id="signup_1_top_img_light" name="signup_1_top_img_light" aria-label="Signup 1 Light" placeholder=" ">
          <label for="signup_1_top_img_light">Signup 1 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_1_top_img_dark" name="signup_1_top_img_dark" aria-label="Signup 1 Dark" placeholder=" ">
          <label for="signup_1_top_img_dark">Signup 1 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_2_top_img_light" name="signup_2_top_img_light" aria-label="Signup 2 Light" placeholder=" ">
          <label for="signup_2_top_img_light">Signup 2 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_2_top_img_dark" name="signup_2_top_img_dark" aria-label="Signup 2 Dark" placeholder=" ">
          <label for="signup_2_top_img_dark">Signup 2 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_3_top_img_light" name="signup_3_top_img_light" aria-label="Signup 3 Light" placeholder=" ">
          <label for="signup_3_top_img_light">Signup 3 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_3_top_img_dark" name="signup_3_top_img_dark" aria-label="Signup 3 Dark" placeholder=" ">
          <label for="signup_3_top_img_dark">Signup 3 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_4_top_img_light" name="signup_4_top_img_light" aria-label="Signup 4 Light" placeholder=" ">
          <label for="signup_4_top_img_light">Signup 4 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_4_top_img_dark" name="signup_4_top_img_dark" aria-label="Signup 4 Dark" placeholder=" ">
          <label for="signup_4_top_img_dark">Signup 4 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_5_top_img_light" name="signup_5_top_img_light" aria-label="Signup 5 Light" placeholder=" ">
          <label for="signup_5_top_img_light">Signup 5 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_5_top_img_dark" name="signup_5_top_img_dark" aria-label="Signup 5 Dark" placeholder=" ">
          <label for="signup_5_top_img_dark">Signup 5 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_6_top_img_light" name="signup_6_top_img_light" aria-label="Signup 6 Light" placeholder=" ">
          <label for="signup_6_top_img_light">Signup 6 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_6_top_img_dark" name="signup_6_top_img_dark" aria-label="Signup 6 Dark" placeholder=" ">
          <label for="signup_6_top_img_dark">Signup 6 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_7_top_img_light" name="signup_7_top_img_light" aria-label="Signup 7 Light" placeholder=" ">
          <label for="signup_7_top_img_light">Signup 7 Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="signup_7_top_img_dark" name="signup_7_top_img_dark" aria-label="Signup 7 Dark" placeholder=" ">
          <label for="signup_7_top_img_dark">Signup 7 Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="login_top_img_light" name="login_top_img_light" aria-label="Login Image Light" placeholder=" ">
          <label for="login_top_img_light">Login Image Light</label>
          <p class="form-caption">Image URL</p>
        </div>
        <div class="form-item float-label-group">
          <input type="text" id="login_top_img_dark" name="login_top_img_dark" aria-label="Login Image Dark" placeholder=" ">
          <label for="login_top_img_dark">Login Image Dark</label>
          <p class="form-caption">Image URL</p>
        </div>
      </div>
      <div id="step5" class="wizard-step">
        <h3>Step 5: Finish</h3>
        <p>Review your details and submit to create the app.</p>
      </div>
      <div class="wizard-buttons">
        <button type="button" id="prevBtn" class="simple-button">Previous</button>
        <button type="button" id="nextBtn" class="simple-button">Next</button>
        <button type="submit" id="submitBtn" class="kick-ass-submit" style="display:none;">Submit</button>
      </div>
    </form>
  </div>
</div>
</div>
<script>
  const steps = document.querySelectorAll('.wizard-step');
  let currentStep = 0;
  const nextBtn = document.getElementById('nextBtn');
  const prevBtn = document.getElementById('prevBtn');
  const submitBtn = document.getElementById('submitBtn');
  const form = document.getElementById('appWizardForm');
  const groupToggles = document.querySelectorAll('.scope-group');

  function showStep(index) {
    steps.forEach((step,i)=>{
      step.classList.toggle('active', i === index);
    });
    prevBtn.style.display = index === 0 ? 'none':'inline-block';
    nextBtn.style.display = index === steps.length -1 ? 'none':'inline-block';
    submitBtn.style.display = index === steps.length -1 ? 'inline-block':'none';
  }

  nextBtn.addEventListener('click', () => {
    if(currentStep < steps.length -1){
      currentStep++;
      showStep(currentStep);
    }
  });

  prevBtn.addEventListener('click', () => {
    if(currentStep > 0){
      currentStep--;
      showStep(currentStep);
    }
  });

  groupToggles.forEach(tg => {
    tg.addEventListener('change', () => {
      const scopes = tg.dataset.scopes.split(',');
      scopes.forEach(sc => {
        const cb = document.querySelector('.hidden-scope[value="' + sc + '"]');
        if (cb) cb.checked = tg.checked;
      });
    });
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          form.reset();
          document.getElementById('form-submission-box').innerHTML = `
<div id="top-page-image" class="top-page-image" style="min-height:250px;" data-light-img="../svgs/confirmed-day.svg" data-dark-img="../svgs/confirmed-night.svg"></div>
<h1>Your App is Setup!</h1>
<p>To continue fine tuning the settings of your app, click through on your dashboard.  There you'll be able to tweak the full app configuation and add any missing details</p>
<a href="dashboard.php"><button class="kick-ass">Dashboard</button></a>`;
        } else {
          document.getElementById('update-error').textContent = d.error || 'Unknown error';
        }
      })
      .catch(err => {
        document.getElementById('update-error').textContent = err.message;
      });
  });

  showStep(currentStep);
</script>
<?php require_once("../footer-2025.php"); ?>
</body>
</html>
