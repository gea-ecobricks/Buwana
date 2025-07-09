<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

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

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'edit-app-core.php';
$version = '0.12';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

if (empty($_SESSION['buwana_id'])) {
    $query = [
        'status'   => 'loggedout',
        'redirect' => $page,
    ];
    if (!empty($client_id)) {
        $query['app'] = $client_id;
    } elseif (!empty($_GET['client_id'])) {
        $query['app'] = $_GET['client_id'];
    } elseif (!empty($_GET['app'])) {
        $query['app'] = $_GET['app'];
    }
    if (!empty($buwana_id)) {
        $query['id'] = $buwana_id;
    } elseif (!empty($_GET['id'])) {
        $query['id'] = $_GET['id'];
    }

    header('Location: login.php?' . http_build_query($query));
    exit();
}

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
$buwana_id = intval($_SESSION['buwana_id']);

$first_name = '';
$earthling_emoji = '';
$stmt = $buwana_conn->prepare("SELECT first_name, earthling_emoji FROM users_tb WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $earthling_emoji);
    $stmt->fetch();
    $stmt->close();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_app'])) {
    $redirect_uris     = $_POST['redirect_uris'] ?? '';
    $app_login_url     = $_POST['app_login_url'] ?? '';
    $scopes_input      = $_POST['scopes'] ?? [];
    $scopes_input      = is_array($scopes_input) ? $scopes_input : [];
    $scopes_input      = array_intersect($scopes_input, $scope_options);
    $scopes            = implode(',', $scopes_input);
    $app_domain        = $_POST['app_domain'] ?? '';
    $app_url           = $_POST['app_url'] ?? '';
    $app_dashboard_url = $_POST['app_dashboard_url'] ?? '';
    $app_description   = $_POST['app_description'] ?? '';
    $app_version       = $_POST['app_version'] ?? '';
    $app_display_name  = $_POST['app_display_name'] ?? '';
    $contact_email     = $_POST['contact_email'] ?? '';

    $success = false;
    $error_message = '';

    $sql = "UPDATE apps_tb a
            JOIN app_owners_tb ao ON ao.app_id = a.app_id
            SET a.redirect_uris=?, a.app_login_url=?, a.scopes=?, a.app_domain=?, a.app_url=?, a.app_dashboard_url=?, a.app_description=?, a.app_version=?, a.app_display_name=?, a.contact_email=?
            WHERE a.app_id=? AND ao.buwana_id=?";
    $stmt = $buwana_conn->prepare($sql);
    if ($stmt) {
        if ($stmt->bind_param('ssssssssssii', $redirect_uris, $app_login_url, $scopes, $app_domain, $app_url, $app_dashboard_url, $app_description, $app_version, $app_display_name, $contact_email, $app_id, $buwana_id)) {
            $success = $stmt->execute();
            if (!$success) {
                $error_message = $stmt->error;
            }
        } else {
            $error_message = $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = $buwana_conn->error;
    }

    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'error' => $error_message]);
        exit();
    }
}


$stmt = $buwana_conn->prepare("SELECT a.* FROM apps_tb a JOIN app_owners_tb ao ON ao.app_id = a.app_id WHERE a.app_id = ? AND ao.buwana_id = ?");
$stmt->bind_param('ii', $app_id, $buwana_id);
$stmt->execute();
$result = $stmt->get_result();
$app = $result ? $result->fetch_assoc() : [];
$stmt->close();

$selected_scopes = array_filter(array_map('trim', explode(',', $app['scopes'] ?? '')));
$jwt_public_key  = $app['jwt_public_key'] ?? '';
$jwt_private_key = $app['jwt_private_key'] ?? '';
$client_secret   = $app['client_secret'] ?? '';

if (!$app) {
    echo "<p>App not found or access denied.</p>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/app-view-en.php"); ?>
    <?php require_once("../includes/dashboard-inc.php"); ?>
    <style>
      .top-wrapper {
        background: var(--darker-lighter);
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
      .button-info {
        display: flex;
        flex-direction: column;
      }
      .button-column {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
      }
      .scope-info {
        display: flex;
        flex-direction: column;
        color: var(--text-color)
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
      #copy-public-key,
      #copy-private-key,
      #copy-client-secret,
      #generate-keys {
        background: grey;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 8px 12px;
        margin-left: 15px;
        margin-right: 5px;
        cursor: pointer;
      }
      #copy-public-key:hover,
      #copy-private-key:hover,
      #copy-client-secret:hover,
      #generate-keys:hover {
        background: darkgrey;
      }
    </style>
<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div class="top-wrapper">
      <div>
        <div class="login-status"><?= htmlspecialchars($earthling_emoji) ?> Logged in as <?= htmlspecialchars($first_name) ?></div>
        <div style="font-size:0.9em;color:grey;margin-bottom: auto;">
          <?php if($app['is_active']): ?>
            🟢 <?= htmlspecialchars($app['app_display_name']) ?> is active
          <?php else: ?>
            ⚪ <?= htmlspecialchars($app['app_display_name']) ?> is not active
          <?php endif; ?>
        </div>
        <div style="font-size:0.9em;color:grey;">
          <?php if($app['allow_signup']): ?>
            🟢 <?= htmlspecialchars($app['app_display_name']) ?> signups enabled
          <?php else: ?>
            ⚪ <?= htmlspecialchars($app['app_display_name']) ?> signups off
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;flex-flow:column;margin-left:auto;">
          <div style="display:flex;align-items:center;margin-left:auto;">

                <div style="text-align:right;margin-right:10px;">
                  <div class="page-name" title="Page name"><?= htmlspecialchars($app['app_display_name']) ?></div>
                  <div class="client-id" title="App Client ID"><?= htmlspecialchars($app['client_id']) ?></div>
                </div>
                <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon" title="<?= htmlspecialchars($app['app_display_name']) ?>" width="60" height="60">
          </div>
      </div>

    </div>
    <div class="breadcrumb" style="text-align:right;margin-left:auto;margin-right: 15px;">
                          <a href="dashboard.php">Dashboard</a> &gt;
                          <a href="app-view.php?app_id=<?= intval($app_id) ?>">Manage <?= htmlspecialchars($app['app_display_name']) ?></a> &gt;
                          Edit Core
                        </div>
            <div id="update-status" style="font-size:1.3em; color:green;padding:10px;margin-top:10px;"></div>
            <div id="update-error" style="font-size:1.3em; color:red;padding:10px;margin-top:10px;"></div>
    <h2 data-lang-id="000-edit-core-date" style="martoin">Edit Core Data</h2>
    <p>Set the core parameters for your <?= htmlspecialchars($app['app_display_name']) ?> app.  These will set the base display and functionality for signing up, logins, redirects and log outs.</p>
    <form id="edit-core-form" method="post" style="margin-top:20px;">
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <textarea id="redirect_uris" name="redirect_uris" aria-label="Redirect URIs" maxlength="255" required placeholder=" " rows="2"><?= htmlspecialchars($app['redirect_uris']) ?></textarea>
        <label for="redirect_uris">Redirect URIs</label>
        <p class="form-caption" data-lang-id="011c-redirect">Allowed OAuth redirect URIs, comma separated</p>
        <div id="redirect_uris-error-required" class="form-field-error">This field is required.</div>
        <div id="redirect_uris-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="redirect_uris-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_login_url" name="app_login_url" aria-label="App Login URL" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_login_url']) ?>">
        <label for="app_login_url">App Login URL</label>
        <p class="form-caption" data-lang-id="001-app-login-field-description">This is where we'll direct users to login to your app (i.e. after signup)</p>

        <div id="app_login_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_login_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_login_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">

          <div class="scope-info">
              <label for="scopes" style="padding:7px;"><h5>JWT Key Pair</h5></label>
            <span>The JWT key pair consists of a private key, used by  Buwana  to sign each user login with a secret signature, and a corresponding public key, which any app in the ecosystem can use to verify the JWT’s authenticity by checking the signature against the public registry. Like a magic diamond ring that proves a user's identity, this mystical pairing ensures that only the true Buwana gem master can craft valid tokens, while all village gates can recognize and honor them. 💫🔐💍</span>
          </div>

          <?php if(empty($jwt_public_key) && empty($jwt_private_key)): ?>
            <div style="display:flex;justify-content:flex-end;width:100%;">
              <button type="button" id="generate-keys">Generate Keys</button>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;width:100%;">
              <p>JWT Public Key</p>
              <div style="display:flex;align-items:center;">
                <div class="password-wrapper" style="position:relative;flex-grow:1;">
                  <input type="password" id="public_key" readonly value="<?= htmlspecialchars($jwt_public_key) ?>" style="width:100%;">
                  <span toggle="#public_key" class="toggle-password" style="cursor:pointer;position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;z-index:1;">🙈</span>
                </div>
                <button type="button" id="copy-public-key">Copy</button>
              </div>
              <p class="form-caption">  A key listed on the openly shared registry of legit gem cuts (via JWKS) that all Buwana apps consult to verify the mystical integrity of any incoming JWT ring.</p>
              <p>JWT Private Key</p>
              <div style="display:flex;align-items:center;">
                <div class="password-wrapper" style="position:relative;flex-grow:1;">
                  <input type="password" id="private_key" readonly value="<?= htmlspecialchars($jwt_private_key) ?>" style="width:100%;">
                  <span toggle="#private_key" class="toggle-password" style="cursor:pointer;position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;z-index:1;">🙈</span>
                </div>
                <button type="button" id="copy-private-key">Copy</button>
              </div>
              <p class="form-caption"> The secret-cut gem geometry used by the Buwana Auth Server to sign JWTs, proving their authenticity and origin — held sacred and unique for each app.</p>
            </div>
          <?php endif; ?>



        <?php if(!empty($jwt_public_key) && !empty($jwt_private_key)): ?>
        <p class="form-caption"><a href="#" id="regenerate-keys" style="color:red;">Regenerate Keys</a></p>
        <?php endif; ?>
      </div>
      <?php if(!empty($client_secret)): ?>
      <div class="form-item" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <div class="scope-info">
          <label for="client_secret" style="padding:7px;"><h5>Client Secret</h5></label>
        </div>
        <p>This is the private key for your app:</p>
        <div style="display:flex;align-items:center;">
          <div class="password-wrapper" style="position:relative;flex-grow:1;">
            <input type="password" id="client_secret" readonly value="<?= htmlspecialchars($client_secret) ?>" style="width:100%;">
            <span toggle="#client_secret" class="toggle-password" style="cursor:pointer;position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:18px;z-index:1;">🙈</span>
          </div>
          <button type="button" id="copy-client-secret">Copy</button>
        </div>
        <p class="form-caption"> A confidential phrase shared only between the Buwana Authorization Server and trusted backend apps, allowing them to request tokens and prove their identity in the sacred dance of OAuth2.</p>
      </div>
      <?php endif; ?>
      <div class="form-item" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <label for="scopes" style="padding:7px;"><h5>Scopes</h5></label>
        <div id="scopes" class="scopes-list">
<?php
  $profile_scopes = ['openid','email','profile','phone','buwana:earthlingEmoji','buwana:location.continent'];
  $all_profile = count(array_intersect($profile_scopes, $selected_scopes)) === count($profile_scopes);
?>

          <div class="scope-row">
            <div class="scope-info">
              <span>🌐 <b>Buwana Profile</b></span>

              <span class="scope-caption">Essential user data for logging in and using the app</span>
              <span class="scope-subscopes">openId, Name, email, profile, phone, buwana:earthlingEmoji, buwana:location_continent</span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" class="scope-checkbox scope-group" data-scopes="<?= implode(',', $profile_scopes) ?>" <?= $all_profile ? 'checked' : '' ?> />
              <span class="slider"></span>
            </label>
<?php foreach ($profile_scopes as $sc): ?>
            <input type="checkbox" class="scope-checkbox hidden-scope" name="scopes[]" value="<?= htmlspecialchars($sc) ?>" <?= in_array($sc, $selected_scopes) ? 'checked' : '' ?> style="display:none;" />
<?php endforeach; ?>
          </div>
<?php foreach ([ 'buwana:community', 'buwana:bioregion' ] as $scope): ?>
          <div class="scope-row">
            <div class="scope-info">
              <span>ℹ️ <b><?= htmlspecialchars($scope) ?></b></span>

              <span class="scope-caption">
                <?= htmlspecialchars($scope_descriptions[$scope] ?? '') ?>
              </span>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" class="scope-checkbox" name="scopes[]" value="<?= htmlspecialchars($scope) ?>" <?= in_array($scope, $selected_scopes) ? 'checked' : '' ?> />
              <span class="slider"></span>
            </label>
          </div>
<?php endforeach; ?>
        </div>
        <p class="form-caption" data-lang-id="011c-scopes">OAuth scopes requested by your app</p>
        <div id="scopes-error-required" class="form-field-error">This field is required.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_domain" name="app_domain" aria-label="App Domain" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_domain']) ?>">
        <label for="app_domain">App Domain</label>
        <p class="form-caption" data-lang-id="011c-domain">Your primary domain name</p>
        <div id="app_domain-error-required" class="form-field-error">This field is required.</div>
        <div id="app_domain-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_domain-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_url" name="app_url" aria-label="App URL" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_url']) ?>">
        <label for="app_url">App URL</label>
        <p class="form-caption" data-lang-id="011c-app-url">Public homepage of your app</p>
        <div id="app_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_dashboard_url" name="app_dashboard_url" aria-label="App Dashboard URL" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_dashboard_url']) ?>">
        <label for="app_dashboard_url">App Dashboard URL</label>
        <p class="form-caption" data-lang-id="011c-dashboard">Where users manage their account</p>
        <div id="app_dashboard_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_dashboard_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_dashboard_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <textarea id="app_description" name="app_description" aria-label="Description" maxlength="255" required placeholder=" " rows="3"><?= htmlspecialchars($app['app_description']) ?></textarea>
        <label for="app_description">Description</label>
        <p class="form-caption" data-lang-id="011c-description">Short summary of your app</p>
        <div id="app_description-error-required" class="form-field-error">This field is required.</div>
        <div id="app_description-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_description-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_version" name="app_version" aria-label="Version" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_version']) ?>">
        <label for="app_version">Version</label>
        <p class="form-caption" data-lang-id="011c-version">Current version of the app</p>
        <div id="app_version-error-required" class="form-field-error">This field is required.</div>
        <div id="app_version-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_version-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_display_name" name="app_display_name" aria-label="Display Name" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_display_name']) ?>">
        <label for="app_display_name">Display Name</label>
        <p class="form-caption" data-lang-id="011c-display">Name shown to users</p>
        <div id="app_display_name-error-required" class="form-field-error">This field is required.</div>
        <div id="app_display_name-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_display_name-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="email" id="contact_email" name="contact_email" aria-label="Contact Email" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['contact_email']) ?>">
        <label for="contact_email">Contact Email</label>
        <p class="form-caption" data-lang-id="011c-contact">Where we can reach you</p>
        <div id="contact_email-error-required" class="form-field-error">This field is required.</div>
        <div id="contact_email-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="contact_email-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="submit-button-wrapper">
        <button type="submit" id="submit-button" name="update_app" class="kick-ass-submit">
          <span id="submit-button-text">💾 Save Changes</span>
          <span id="submit-emoji" class="submit-emoji" style="display:none;"></span>
        </button>
      </div>
    </form>
  </div>
</div>
</div>
</div> <!-- closes main -->
<?php require_once("../footer-2025.php"); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('edit-core-form');
  const fields = ['redirect_uris','app_login_url','app_domain','app_url','app_dashboard_url','app_description','app_version','app_display_name','contact_email'];
  const scopeBoxes = document.querySelectorAll('.scope-checkbox');
  const groupToggles = document.querySelectorAll('.scope-group');
  const generateBtn = document.getElementById('generate-keys');
  const copyPublicBtn  = document.getElementById('copy-public-key');
  const copyPrivateBtn = document.getElementById('copy-private-key');
  const copySecretBtn  = document.getElementById('copy-client-secret');
  const regenLink = document.getElementById('regenerate-keys');


  function updateStatusMessage(success, message = '') {
    const statusEl = document.getElementById('update-status');
    const errorEl = document.getElementById('update-error');
    statusEl.textContent = '';
    errorEl.textContent = '';
    if (success) {
      statusEl.textContent = '✅ App updated!';
    } else {
      errorEl.textContent = '😭 There was a problem: ' + message;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function hasInvalidChars(value) {
    return /[\'"<>]/.test(value);
  }

  function toggleError(id, show) {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? 'block' : 'none';
  }

  function validateField(name) {
    const value = document.getElementById(name).value.trim();
    let valid = true;
    toggleError(name + '-error-required', value === '');
    toggleError(name + '-error-long', value.length > 255);
    toggleError(name + '-error-invalid', hasInvalidChars(value));
    if (value === '' || value.length > 255 || hasInvalidChars(value)) {
      valid = false;
    }
    return valid;
  }

  function validateScopes() {
    const anyChecked = Array.from(scopeBoxes).some(cb => cb.checked);
    toggleError('scopes-error-required', !anyChecked);
    return anyChecked;
  }

  fields.forEach(f => {
    const el = document.getElementById(f);
    if (el) {
      el.addEventListener('input', () => validateField(f));
    }
  });

  scopeBoxes.forEach(cb => cb.addEventListener('change', validateScopes));
  groupToggles.forEach(tg => {
    tg.addEventListener('change', () => {
      const scopes = tg.dataset.scopes.split(',');
      scopes.forEach(sc => {
        const cb = document.querySelector('.hidden-scope[value="' + sc + '"]');
        if (cb) cb.checked = tg.checked;
      });
      validateScopes();
    });
  });

  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      fetch('../processes/key_generator.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'app_id=<?= intval($app_id) ?>'
      }).then(r => r.json()).then(d => {
        if (d.success) {
          location.reload();
        } else {
          alert('😭 ' + (d.error || 'Error generating keys'));
        }
      });
    });
  }

  function setupCopy(btn, inputId) {
    if (btn) {
      btn.addEventListener('click', () => {
        const input = document.getElementById(inputId);
        if (input) {
          navigator.clipboard.writeText(input.value).then(() => {
            const originalText = btn.textContent;
            const originalBg = btn.style.background;
            btn.textContent = 'Copied!';
            btn.style.background = 'green';
            setTimeout(() => {
              btn.textContent = originalText;
              btn.style.background = originalBg;
            }, 10000);
          });
        }
      });
    }
  }

  setupCopy(copyPublicBtn, 'public_key');
  setupCopy(copyPrivateBtn, 'private_key');
  setupCopy(copySecretBtn, 'client_secret');

  if (regenLink) {
    regenLink.addEventListener('click', (e) => {
      e.preventDefault();
      if (confirm("Are you sure you want to do this? You will need to update your App's code with the new public key.")) {
        fetch('../processes/key_generator.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'app_id=<?= intval($app_id) ?>'
        }).then(r => r.json()).then(d => {
          if (d.success) {
            location.reload();
          } else {
            alert('😭 ' + (d.error || 'Error generating keys'));
          }
        });
      }
    });
  }


  form.addEventListener('submit', function (e) {
    e.preventDefault();
    let allValid = true;
    fields.forEach(f => { if (!validateField(f)) allValid = false; });
    if (!validateScopes()) allValid = false;
    if (!allValid) {
      return;
    }

    const formData = new FormData(form);
    formData.append('update_app', '1');
    fetch('edit_appcore_process.php?app_id=<?= intval($app_id) ?>', {

      method: 'POST',
      body: formData
    }).then(r => r.json()).then(d => {
      if (d.success) {
        updateStatusMessage(true);
      } else {
        updateStatusMessage(false, d.error || 'Unknown error');
      }
    }).catch(err => {
      updateStatusMessage(false, err.message);
    });
  });
});

</script>
</body>
</html>
