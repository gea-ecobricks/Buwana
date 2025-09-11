<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// Clear any existing session data and cookies to ensure the user is fully logged out
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'goodbye';
$version = '0.2';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$successes = isset($_GET['successes']) ? (array)$_GET['successes'] : [];
$failures = isset($_GET['failures']) ? (array)$_GET['failures'] : [];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
<?php require_once ("../includes/goodbye-inc.php");?>

<script>
    // Clear caches, storage, and cookies to remove any lingering JWT tokens
    if ('caches' in window) {
        caches.keys().then(function(names) {
            for (let name of names) caches.delete(name);
        });
    }
    localStorage.clear();
    sessionStorage.clear();
    document.cookie.split(';').forEach(function(c) {
        document.cookie = c.replace(/^ +/, '').replace(/=.*/, '=;expires=' + new Date().toUTCString() + ';path=/');
    });
</script>

<!-- PAGE CONTENT -->
<div class="page-panel-group">

    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container">
    <div id="regen-top" class="regen-top top-page-image"></div>

          <div style="text-align:center;width:100%;margin:auto;">

            <h1 data-lang-id="001-good-bye">Goodbye!</h1>
            <p data-lang-id="002-successfuly-deleted">Your account has been successfully deleted.</p>
            <?php if (!empty($successes)) : ?>
              <ul style="list-style:none;padding:0;margin:0 auto;display:inline-block;text-align:left;">
                <?php foreach ($successes as $item) : ?>
                  <li>✅ <?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if (!empty($failures)) : ?>
              <ul style="list-style:none;padding:0;margin:0 auto;display:inline-block;text-align:left;">
                <?php foreach ($failures as $item) : ?>
                  <li>❌ <?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <p data-lang-id="003-change-mind">If you change your mind, you can <a href="signup-1.php">create a new account</a> anytime.</p>
          </div>
        </div>
    </div>
</div>
</div>

<?php require_once ("../footer-2025.php");?>

</body>
</html>
