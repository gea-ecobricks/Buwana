<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'edit-app-graphics';
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
    $app_logo_url        = $_POST['app_logo_url'] ?? '';
    $app_logo_dark_url   = $_POST['app_logo_dark_url'] ?? '';
    $app_square_icon_url = $_POST['app_square_icon_url'] ?? '';
    $app_wordmark_url    = $_POST['app_wordmark_url'] ?? '';
    $app_wordmark_dark_url = $_POST['app_wordmark_dark_url'] ?? '';

    $sql = "UPDATE apps_tb a
            JOIN app_owners_tb ao ON ao.app_id = a.app_id
            SET a.app_logo_url=?, a.app_logo_dark_url=?, a.app_square_icon_url=?, a.app_wordmark_url=?, a.app_wordmark_dark_url=?
            WHERE a.app_id=? AND ao.buwana_id=?";
    $stmt = $buwana_conn->prepare($sql);
    if ($stmt) {
        if ($stmt->bind_param('ssssssi', $app_logo_url, $app_logo_dark_url, $app_square_icon_url, $app_wordmark_url, $app_wordmark_dark_url, $app_id, $buwana_id)) {
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
                  <div class="page-name">Edit App Graphics: <?= htmlspecialchars($app['app_display_name']) ?></div>
                  <div class="client-id">Client ID: <?= htmlspecialchars($app['client_id']) ?></div>
                </div>
                <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon" title="<?= htmlspecialchars($app['app_display_name']) ?>" width="60" height="60">
          </div>
      </div>

    </div>
    <div class="breadcrumb" style="text-align:right;margin-left:auto;margin-right: 15px;">
                          <a href="dashboard.php">Dashboard</a> &gt;
                          <a href="app-view.php?app_id=<?= intval($app_id) ?>">Manage <?= htmlspecialchars($app['app_display_name']) ?></a> &gt;
                          Edit Graphics
                        </div>
            <div id="update-status" style="font-size:1.3em; color:green;padding:10px;margin-top:10px;"></div>
            <div id="update-error" style="font-size:1.3em; color:red;padding:10px;margin-top:10px;"></div>
    <h1>Edit App Graphics</h1>
    <p>Manage the image URLs used for your <?= htmlspecialchars($app['app_display_name']) ?> app.</p>
      <form id="edit-graphics-form" method="post" style="margin-top:20px;">
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_logo_url" name="app_logo_url" aria-label="Logo URL (light)" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_logo_url']) ?>">
        <label for="app_logo_url">Logo URL (light)</label>
        <p class="form-caption">Light mode logo</p>
        <div id="app_logo_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_logo_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_logo_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_logo_dark_url" name="app_logo_dark_url" aria-label="Logo URL (dark)" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_logo_dark_url']) ?>">
        <label for="app_logo_dark_url">Logo URL (dark)</label>
        <p class="form-caption">Dark mode logo</p>
        <div id="app_logo_dark_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_logo_dark_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_logo_dark_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_square_icon_url" name="app_square_icon_url" aria-label="Square Icon URL" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_square_icon_url']) ?>">
        <label for="app_square_icon_url">Square Icon URL</label>
        <p class="form-caption">App icon</p>
        <div id="app_square_icon_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_square_icon_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_square_icon_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_wordmark_url" name="app_wordmark_url" aria-label="Wordmark URL (light)" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_wordmark_url']) ?>">
        <label for="app_wordmark_url">Wordmark URL (light)</label>
        <p class="form-caption">Light mode wordmark</p>
        <div id="app_wordmark_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_wordmark_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_wordmark_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
      </div>
      <div class="form-item float-label-group" style="border-radius:10px 10px 5px 5px;padding-bottom: 10px;">
        <input type="text" id="app_wordmark_dark_url" name="app_wordmark_dark_url" aria-label="Wordmark URL (dark)" maxlength="255" required placeholder=" " value="<?= htmlspecialchars($app['app_wordmark_dark_url']) ?>">
        <label for="app_wordmark_dark_url">Wordmark URL (dark)</label>
        <p class="form-caption">Dark mode wordmark</p>
        <div id="app_wordmark_dark_url-error-required" class="form-field-error">This field is required.</div>
        <div id="app_wordmark_dark_url-error-long" class="form-field-error">The entry is too long. Max 255 characters.</div>
        <div id="app_wordmark_dark_url-error-invalid" class="form-field-error">The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.</div>
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
  const form = document.getElementById('edit-graphics-form');
  const fields = ['app_logo_url','app_logo_dark_url','app_square_icon_url','app_wordmark_url','app_wordmark_dark_url'];

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
    fetch('edit_appgraphics_process.php?app_id=<?= intval($app_id) ?>', {
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
