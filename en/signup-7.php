<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'signup-7';
$version = '0.777';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

// 🧩 Validate buwana_id
$buwana_id = $_GET['id'] ?? null;
if (!$buwana_id || !is_numeric($buwana_id)) {
    die("⚠️ Invalid or missing Buwana ID.");
}

// 🔍 Fetch user info
$first_name = 'User';
$earthling_emoji = '🌍';
$time_zone = 'Etc/GMT'; // Default fallback

$stmt = $buwana_conn->prepare("SELECT first_name, earthling_emoji, time_zone FROM users_tb WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $earthling_emoji, $time_zone);
    $stmt->fetch();
    $stmt->close();
}

// 🔗 Get app info for redirect to the client app's url
$app_display_name = $app_info['app_display_name'] ?? 'Your App';
$app_login_url = $app_info['app_login_url'] ?? null;

function build_login_url($base, array $params) {
    $delimiter = (strpos($base, '?') !== false) ? '&' : '?';
    return $base . $delimiter . http_build_query($params);
}

$redirect_url = $app_login_url
      ? build_login_url($app_login_url, [
        'lang' => $lang,
        'id' => $buwana_id,
        'firstname' => $first_name,
        'status' => 'firsttime',
        'timezone' => $time_zone,
        'app' => $app_info['client_id'] ?? ''
      ])
      : '/';

// Echo the HTML structure
echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">
<head>
<meta charset="UTF-8">
';

?>

<!--
Buwana EarthenAuth
Developed and made open source by the Global Ecobrick Alliance
See our git hub repository for the full code and to help out:
https://github.com/gea-ecobricks/buwana/-->


<?php require_once ("../includes/signup-7-inc.php");?>

<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form" style="min-height:calc( 100vh - 54px)">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;margin:auto;">

      <div id="top-page-image" class="top-page-image"></div>

      <div style="text-align:center;width:100%;margin:auto;">
        <div class="emoji-banner" style="text-align:center;font-size:5em;">
          <?= htmlspecialchars($earthling_emoji) ?>
        </div>
        <h1>
          <span data-lang-id="001-hurray">Hurray</span> <?= htmlspecialchars($first_name) ?>!
        </h1>
        <h4 data-lang-id="002-your-buwana-create">Your Buwana account has been created.</h4>
        <p>
          <span data-lang-id="003-you-will-be">You'll be redirected to login to </span><?= htmlspecialchars($app_display_name) ?> <span data-lang-id="004-after">after</span> <span id="countdown">5</span> <span data-lang-id="005-seconds">seconds</span>.
        </p>
      </div>
    </div>

    <div id="browser-back-link" style="font-size: small; text-align: center; margin: auto; align-self: center; padding-top: 40px; padding-bottom: 40px; margin-top: 0px;">
        <p><span data-lang-id="006-manual-redirect">If you're not redirected automatically,</span><a href="<?= htmlspecialchars($redirect_url) ?>"> <span data-lang-id="007-click-here">click here</span></a>.
        </p>
    </div>
  </div>
</div>
</div>

<?php require_once ("../footer-2025.php"); ?>

<script>
  setTimeout(() => {
    window.location.href = <?= json_encode($redirect_url) ?>;
  }, 6000);
</script>


<script>
  let seconds = 5;
  const countdownEl = document.getElementById('countdown');

  const countdown = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
      clearInterval(countdown);
    } else {
      countdownEl.textContent = seconds;
    }
  }, 1000);

  setTimeout(() => {
    window.location.href = <?= json_encode($redirect_url) ?>;
  }, 5000);



//
//   // 👀 Injected emoji array for the app
//   window.appEmojis = <?= json_encode(json_decode($app_info['app_emojis_array'] ?? '[]'), JSON_UNESCAPED_UNICODE) ?>;
//
//   // 🔁 Continuous emoji spinner for signup-7.php
//   function runSignup7EmojiSpinner(selector) {
//     const emojiContainer = document.querySelector(selector);
//     if (!emojiContainer || !window.appEmojis || !window.appEmojis.length) return;
//
//     const emojis = window.appEmojis;
//     let index = 0;
//
//     setTimeout(() => {
//       setInterval(() => {
//         emojiContainer.textContent = emojis[index];
//         emojiContainer.style.opacity = 1;
//
//         // Optional: fade-out effect
//         setTimeout(() => {
//           emojiContainer.style.opacity = 0.7;
//         }, 200); // fade slightly after 200ms
//
//         index = (index + 1) % emojis.length; // loop back
//       }, 200); // ⏱ 30% faster (was 400ms)
//     }, 1000); // Initial delay
//   }
//
//   runSignup7EmojiSpinner('.emoji-banner');
</script>


</body>
</html>
