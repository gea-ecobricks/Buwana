<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Hardcode the client ID for this page
$_GET['app'] = 'buwana_mgr_001';

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'buwana-index';
$version = '0.7781';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

$buwana_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;

// 🔍 Fetch all apps
$app_query = "SELECT client_id, app_display_name, app_login_url, app_slogan, app_square_icon_url, app_description FROM apps_tb ORDER BY app_display_name ASC";
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
                  data-light-img="../webps/top-buwana-landing-banner.webp"
                  data-dark-img="../webps/top-buwana-landing-banner.webp">
             </div>
<h2  data-lang-id="001-about-buwana" style="text-align:center;">
      Buwana is an open-source login system for regenerative web applications developed by the Global Ecobrick Alliance.
    </h2>
    <p data-lang-id="002-buwana-desc" style="text-align:center;">The Buwana protocol provides the a user authentication alternative for apps that want to escape corporate logins for an ecoystem of resonant, green for-Earth enterprises. The Buwana protocol has only just launched as of June 2025.  Here's the apps that are using it so far...</p>
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
        <div class="app-display-box" data-description="<?= htmlspecialchars($app['app_description']) ?>">
          <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon">
          <h4><?= htmlspecialchars($app['app_display_name']) ?></h4>
          <p class="app-slogan"><?= htmlspecialchars($app['app_slogan']) ?></p>

          <div class="app-actions">
            <div class="button-row">
              <a href="<?= htmlspecialchars($login_link) ?>" class="simple-button" data-lang-id="000-login">Login</a>
              <a href="<?= htmlspecialchars($signup_link) ?>" class="simple-button" data-lang-id="000-signup">Signup</a>
            </div>
           <a href="#" class="about-link" onclick="showAppDescription(event); return false;" data-lang-id="000-about">ℹ️ About</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

<div style="text-align:center; max-width:600px; margin:auto; margin-bottom:25px;">


    <p data-lang-id="003-open-source">The Buwana code-base and documention Wiki is on Github</p>
    <a href="https://github.com/gea-ecobricks/Buwana/tree/main" data-lang-id="004-view-repo">View Repository ↗</a>
 </div>

  </div>
</div>
</div>

<?php require_once("../footer-2025.php"); ?>
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

<?php require_once ("../scripts/app_modals.php");?>
</body>
</html>