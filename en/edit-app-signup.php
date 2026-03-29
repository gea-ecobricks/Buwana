<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'edit-app-signup';
$version = '0.1';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

if (empty($_SESSION['buwana_id'])) {
    header('Location: login.php');
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

$success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_app'])) {
    $signup_1_top_img_light   = $_POST['signup_1_top_img_light'] ?? '';
    $signup_1_top_img_dark    = $_POST['signup_1_top_img_dark'] ?? '';
    $signup_2_top_img_light   = $_POST['signup_2_top_img_light'] ?? '';
    $signup_2_top_img_dark    = $_POST['signup_2_top_img_dark'] ?? '';
    $signup_3_top_img_light   = $_POST['signup_3_top_img_light'] ?? '';
    $signup_3_top_img_dark    = $_POST['signup_3_top_img_dark'] ?? '';
    $signup_4_top_img_light   = $_POST['signup_4_top_img_light'] ?? '';
    $signup_4_top_img_dark    = $_POST['signup_4_top_img_dark'] ?? '';
    $signup_5_top_img_light   = $_POST['signup_5_top_img_light'] ?? '';
    $signup_5_top_img_dark    = $_POST['signup_5_top_img_dark'] ?? '';
    $signup_6_top_img_light   = $_POST['signup_6_top_img_light'] ?? '';
    $signup_6_top_img_dark    = $_POST['signup_6_top_img_dark'] ?? '';
    $signup_7_top_img_light   = $_POST['signup_7_top_img_light'] ?? '';
    $signup_7_top_img_dark    = $_POST['signup_7_top_img_dark'] ?? '';
    $login_top_img_light      = $_POST['login_top_img_light'] ?? '';
    $login_top_img_dark       = $_POST['login_top_img_dark'] ?? '';

    $sql = "UPDATE apps_tb a
            JOIN app_owners_tb ao ON ao.app_id = a.app_id
            SET a.signup_1_top_img_light=?, a.signup_1_top_img_dark=?, a.signup_2_top_img_light=?, a.signup_2_top_img_dark=?,
                a.signup_3_top_img_light=?, a.signup_3_top_img_dark=?, a.signup_4_top_img_light=?, a.signup_4_top_img_dark=?,
                a.signup_5_top_img_light=?, a.signup_5_top_img_dark=?, a.signup_6_top_img_light=?, a.signup_6_top_img_dark=?,
                a.signup_7_top_img_light=?, a.signup_7_top_img_dark=?, a.login_top_img_light=?, a.login_top_img_dark=?
            WHERE a.app_id=? AND ao.buwana_id=?";
    $stmt = $buwana_conn->prepare($sql);
    if ($stmt) {
        if ($stmt->bind_param('ssssssssssssssssii', $signup_1_top_img_light, $signup_1_top_img_dark, $signup_2_top_img_light, $signup_2_top_img_dark, $signup_3_top_img_light, $signup_3_top_img_dark, $signup_4_top_img_light, $signup_4_top_img_dark, $signup_5_top_img_light, $signup_5_top_img_dark, $signup_6_top_img_light, $signup_6_top_img_dark, $signup_7_top_img_light, $signup_7_top_img_dark, $login_top_img_light, $login_top_img_dark, $app_id, $buwana_id)) {
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
    </style>
<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div class="top-wrapper">
      <a class="back-arrow" href="app-view.php?app_id=<?= intval($app_id) ?>" title="Back to <?= htmlspecialchars($app['app_display_name']) ?>"></a>
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
                  <div class="page-name">Edit Signup Graphics: <?= htmlspecialchars($app['app_display_name']) ?></div>
                  <div class="client-id">Client ID: <?= htmlspecialchars($app['client_id']) ?></div>
                </div>
                <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon" title="<?= htmlspecialchars($app['app_display_name']) ?>" width="60" height="60">
          </div>
      </div>

    </div>
    <div class="breadcrumb" style="text-align:right;margin-left:auto;margin-right: 15px;">
                          <a href="dashboard.php">Dashboard</a> &gt;
                          <a href="app-view.php?app_id=<?= intval($app_id) ?>">Manage <?= htmlspecialchars($app['app_display_name']) ?></a> &gt;
                          Edit Signup Graphics
                        </div>
            <div id="update-status" style="font-size:1.3em; color:green;padding:10px;margin-top:10px;"></div>
            <div id="update-error" style="font-size:1.3em; color:red;padding:10px;margin-top:10px;"></div>
    <h1>Edit Signup Graphics</h1>
    <p>Update the signup and login images for your <?= htmlspecialchars($app['app_display_name']) ?> app.</p>
    <form id="edit-signup-form" method="post" style="margin-top:20px;">
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_1_top_img_light" name="signup_1_top_img_light" aria-label="Signup 1 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_1_top_img_light']) ?>">
        <label for="signup_1_top_img_light">Signup 1 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_1_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_1_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_1_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_1_top_img_dark" name="signup_1_top_img_dark" aria-label="Signup 1 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_1_top_img_dark']) ?>">
        <label for="signup_1_top_img_dark">Signup 1 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_1_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_1_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_1_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_2_top_img_light" name="signup_2_top_img_light" aria-label="Signup 2 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_2_top_img_light']) ?>">
        <label for="signup_2_top_img_light">Signup 2 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_2_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_2_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_2_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_2_top_img_dark" name="signup_2_top_img_dark" aria-label="Signup 2 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_2_top_img_dark']) ?>">
        <label for="signup_2_top_img_dark">Signup 2 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_2_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_2_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_2_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_3_top_img_light" name="signup_3_top_img_light" aria-label="Signup 3 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_3_top_img_light']) ?>">
        <label for="signup_3_top_img_light">Signup 3 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_3_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_3_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_3_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_3_top_img_dark" name="signup_3_top_img_dark" aria-label="Signup 3 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_3_top_img_dark']) ?>">
        <label for="signup_3_top_img_dark">Signup 3 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_3_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_3_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_3_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_4_top_img_light" name="signup_4_top_img_light" aria-label="Signup 4 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_4_top_img_light']) ?>">
        <label for="signup_4_top_img_light">Signup 4 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_4_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_4_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_4_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_4_top_img_dark" name="signup_4_top_img_dark" aria-label="Signup 4 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_4_top_img_dark']) ?>">
        <label for="signup_4_top_img_dark">Signup 4 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_4_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_4_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_4_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_5_top_img_light" name="signup_5_top_img_light" aria-label="Signup 5 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_5_top_img_light']) ?>">
        <label for="signup_5_top_img_light">Signup 5 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_5_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_5_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_5_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_5_top_img_dark" name="signup_5_top_img_dark" aria-label="Signup 5 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_5_top_img_dark']) ?>">
        <label for="signup_5_top_img_dark">Signup 5 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_5_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_5_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_5_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_6_top_img_light" name="signup_6_top_img_light" aria-label="Signup 6 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_6_top_img_light']) ?>">
        <label for="signup_6_top_img_light">Signup 6 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_6_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_6_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_6_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_6_top_img_dark" name="signup_6_top_img_dark" aria-label="Signup 6 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_6_top_img_dark']) ?>">
        <label for="signup_6_top_img_dark">Signup 6 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_6_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_6_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_6_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_7_top_img_light" name="signup_7_top_img_light" aria-label="Signup 7 Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_7_top_img_light']) ?>">
        <label for="signup_7_top_img_light">Signup 7 Light</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_7_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_7_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_7_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="signup_7_top_img_dark" name="signup_7_top_img_dark" aria-label="Signup 7 Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['signup_7_top_img_dark']) ?>">
        <label for="signup_7_top_img_dark">Signup 7 Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="signup_7_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="signup_7_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="signup_7_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="login_top_img_light" name="login_top_img_light" aria-label="Login Banner Light" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['login_top_img_light']) ?>">
        <label for="login_top_img_light">Login Banner Light</label>
        <p class="form-caption">Image URL</p>
        <div id="login_top_img_light-error-required" class="form-field-error">This field is required.</div>
        <div id="login_top_img_light-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="login_top_img_light-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="login_top_img_dark" name="login_top_img_dark" aria-label="Login Banner Dark" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['login_top_img_dark']) ?>">
        <label for="login_top_img_dark">Login Banner Dark</label>
        <p class="form-caption">Image URL</p>
        <div id="login_top_img_dark-error-required" class="form-field-error">This field is required.</div>
        <div id="login_top_img_dark-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="login_top_img_dark-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="submit-button-wrapper">
        <button type="submit" id="submit-button" name="update_app" class="kick-ass-submit">
          <span id="submit-button-text">Save Changes</span>
          <span id="submit-emoji" class="submit-emoji" style="display:none;"></span>
        </button>
      </div>
    </form>
  </div>
</div>
</div> <!-- closes main -->
<?php require_once("../footer-2025.php"); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('edit-signup-form');
  const fields = ['signup_1_top_img_light','signup_1_top_img_dark','signup_2_top_img_light','signup_2_top_img_dark','signup_3_top_img_light','signup_3_top_img_dark','signup_4_top_img_light','signup_4_top_img_dark','signup_5_top_img_light','signup_5_top_img_dark','signup_6_top_img_light','signup_6_top_img_dark','signup_7_top_img_light','signup_7_top_img_dark','login_top_img_light','login_top_img_dark'];

  fields.forEach(id => {
    const input = document.getElementById(id);
    if (input) {
      const img = document.createElement('img');
      img.id = 'preview-' + id;
      img.src = input.value;
      img.style.maxWidth = '250px';
      img.style.maxHeight = '100px';
      img.style.display = input.value ? 'block' : 'none';
      img.style.margin = '0 0 5px 0';
      img.onerror = () => { img.style.display = 'none'; };
      input.parentNode.insertBefore(img, input);
      input.addEventListener('input', () => {
        img.src = input.value;
        img.style.display = input.value ? 'block' : 'none';
      });
    }
  });

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

  fields.forEach(f => {
    const el = document.getElementById(f);
    if (el) {
      el.addEventListener('input', () => validateField(f));
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    let allValid = true;
    fields.forEach(f => { if (!validateField(f)) allValid = false; });
    if (!allValid) {
      return;
    }

    const formData = new FormData(form);
    formData.append('update_app', '1');
    fetch('edit-app-signup.php?app_id=<?= intval($app_id) ?>&ajax=1', {
      method: 'POST',
      body: formData
    }).then(r => r.text()).then(text => {
      try {
        const d = JSON.parse(text);
        if (d.success) {
          updateStatusMessage(true);
        } else {
          updateStatusMessage(false, d.error || 'Unknown error');
        }
      } catch (e) {
        updateStatusMessage(false, text.trim() || e.message);
      }
    }).catch(err => {
      updateStatusMessage(false, err.message);
    });
  });
});
</script>
</body>
</html>
