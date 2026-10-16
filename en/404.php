<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'buwana-index';
$version = '0.7781';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

$buwana_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;

// 🔍 Fetch all apps
$app_query = "SELECT client_id, app_display_name, app_login_url, app_slogan, app_square_icon_url FROM apps_tb ORDER BY app_display_name ASC";
$app_results = $buwana_conn->query($app_query);

$apps = [];
if ($app_results && $app_results->num_rows > 0) {
    while ($row = $app_results->fetch_assoc()) {
        $apps[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">


<?php require_once ("../includes/buwana-index-inc.php");?>



<div id="form-submission-box" class="landing-page-form">

  <div class="form-container">
      <div id="top-page-image" class="buwana-lead-banner"
                  style=""
                  data-light-img="../webps/buwana-404-day.webp"
                  data-dark-img="../webps/buwana-404-day.webp">
             </div>
<h2  data-lang-id="001-404-title" style="text-align:center;">
      Sorry we couldn't find that page!
    </h2>
    <p data-lang-id="002-404-sub" style="text-align:center;">Please try finding the page on the main menu or choose one of the apps in our ecosystem...</p>
    <div class="app-grid">
      <?php foreach ($apps as $app):
          $client_id  = urlencode($app['client_id']);
          $login_link = $app['app_login_url'];
          if ($buwana_id) {
              $connector = strpos($login_link, '?') === false ? '?' : '&';
              $login_link .= $connector . 'id=' . $buwana_id;

          }
          $signup_link = "signup-1.php?app=$client_id";
      ?>
        <div class="app-display-box">
          <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon">
          <h4><?= htmlspecialchars($app['app_display_name']) ?></h4>
          <p class="app-slogan"><?= htmlspecialchars($app['app_slogan']) ?></p>

          <div class="app-actions">
            <a href="<?= htmlspecialchars($login_link) ?>" class="simple-button">Login</a>
            <a href="<?= htmlspecialchars($signup_link) ?>" class="simple-button">Signup</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

<div style="text-align:center; max-width:600px; margin:auto; margin-bottom:25px;">


    <p>The Buwana code-base and documention Wiki is on Github</p>
    <a href="https://github.com/gea-ecobricks/Buwana/tree/main">Check it out ↗</a>
 </div>

  </div>
</div>
</div>

<?php require_once("../footer-2026.php"); ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const boxes = document.querySelectorAll('.app-display-box');
    boxes.forEach(box => {
      box.addEventListener('click', function (e) {
        if (window.innerWidth <= 600 && !e.target.closest('.app-actions')) {
          e.preventDefault();
          this.classList.toggle('active');
        }
      });
    });
  });
</script>
</body>
</html>
