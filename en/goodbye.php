<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'goodbye';
$version = '0.1';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$successes = isset($_GET['successes']) ? (array)$_GET['successes'] : [];
$failures = isset($_GET['failures']) ? (array)$_GET['failures'] : [];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
<?php require_once ("../includes/goodbye-inc.php");?>

<!-- PAGE CONTENT -->
<div id="top-page-image" class="regen-top top-page-image"></div>

<div id="form-submission-box" class="landing-page-form">
    <div class="form-container">

      <div style="text-align:center;width:100%;margin:auto;">

        <h1 data-lang-id="001-good-bye">Goodbye!</h1>
        <p data-lang-id="002-successfuly-deleted">Your account has been successfully deleted.</p>
        <?php if (!empty($successes)) : ?>
          <ul style="list-style:none;padding:0;">
            <?php foreach ($successes as $item) : ?>
              <li>✅ <?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (!empty($failures)) : ?>
          <ul style="list-style:none;padding:0;">
            <?php foreach ($failures as $item) : ?>
              <li>❌ <?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p data-lang-id="003-change-mind">If you change your mind, you can <a href="signup-1.php">create a new account</a> anytime.</p>
      </div>
    </div>
</div>

<?php require_once ("../footer-2025.php");?>

</body>
</html>
