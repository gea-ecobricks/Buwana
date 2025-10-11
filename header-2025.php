<!-- PHP starts by laying out canonical URLs for the page and language -->

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['buwana_id']);

// Pull key session values if they haven't been explicitly defined
$client_id = $client_id ?? ($_SESSION['client_id'] ?? null);
$buwana_id = $buwana_id ?? ($_SESSION['buwana_id'] ?? null);

$parts = explode("/", $_SERVER['SCRIPT_NAME']);
$name = $parts[count($parts) - 1];
if (strcmp($name, "welcome.php") == 0) {
    $name = "";
}

// Get full request URI (e.g. "/en/signup-1.php?gbrk_...")
$requestUri = $_SERVER['REQUEST_URI'];

    // Extract the path after the first language directory
    // This assumes the URL structure is always /[lang]/[page]
    $uriParts = explode('/', $requestUri, 3);

    // Set default in case something goes wrong
    $active_url = isset($uriParts[2]) ? $uriParts[2] : '';

$login_url = 'login.php';
if ($client_id) {
    $login_url .= '?app=' . urlencode($client_id);
    if ($buwana_id) {
        $login_url .= '&buwana=' . urlencode($buwana_id);
    }
}

$app_login_url = $app_info['app_login_url'] ?? 'login.php?app=buwana_mgr_001';

$connected_apps = [];
if ($is_logged_in && isset($buwana_conn) && $buwana_id) {
$sql = "SELECT a.app_display_name,
            a.app_login_url,
            a.app_square_icon_url AS app_icon_url,
            a.app_version,
            a.app_slogan
        FROM apps_tb a
        JOIN user_app_connections_tb c ON a.client_id = c.client_id
        WHERE c.buwana_id = ?";
    $stmt = $buwana_conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $buwana_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                $connected_apps = $result->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmt->close();
    }
}
    ?>




	<link rel="canonical" href="https://buwana.ecobricks.org/<?php echo ($lang); ;?>/<?php echo ($name); ;?>">
	<meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">

	<link rel="alternate" href="https://buwana.ecobricks.org/en/<?php echo ($name); ;?>" hreflang="en">
	<link rel="alternate" href="https://buwana.ecobricks.org/id/<?php echo ($name); ;?>" hreflang="id">
	<link rel="alternate" href="https://buwana.ecobricks.org/es/<?php echo ($name); ;?>" hreflang="es">
	<link rel="alternate" href="https://buwana.ecobricks.org/fr/<?php echo ($name); ;?>" hreflang="fr">
	<link rel="alternate" href="http://buwana.ecobricks.org/en/<?php echo ($name); ;?>" hreflang="x-default">


<meta property="og:site_name" content="Buwana EarthenAuth">


<!-- This allows the site to be used a PWA on iPhones-->

<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="apple-mobile-web-app-title" content="Buwana EarthenAuth">
<meta name="apple-mobile-web-app-status-bar-style" content="black">


<link rel="apple-touch-icon" sizes="57x57" href="../icons/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="../icons/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="../icons/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="../icons/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="../icons/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="../icons/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="../icons/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="../icons/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="../icons/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="32x32" href="../icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="../icons/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="../icons/favicon-16x16.png">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="../icons/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">

<!--MAIN STYLE SHEETS

<link rel="stylesheet" type="text/css" href="../styles/content-2025.css?v=3<?php echo ($version); ;?>">
-->

<link rel="stylesheet" type="text/css" href="../styles/mode-slider.css?v=4">

<link rel="stylesheet" type="text/css" href="../styles/main.css?v=6<?php echo ($version); ;?>">

<!--Default Light Styles to load first-->
<link rel="preload" href="../styles/mode-light.css?v=1<?php echo ($version); ;?>" as="style" onload="this.rel='stylesheet'">
 <!--Stylesheets for light and dark mode.  They need to be called here-->
<link rel="stylesheet" href="../styles/mode-light.css?v=1<?php echo ($version); ;?>" media="(prefers-color-scheme: no-preference), (prefers-color-scheme: light)">
<link rel="stylesheet" href="../styles/mode-dark.css?v=1<?php echo ($version); ;?>" media="(prefers-color-scheme: dark)">

<link rel="stylesheet" type="text/css" href="../styles/footer.css?v=<?php echo ($version); ;?>">



<script src="../js/earthcal-config.js?v=<?php echo ($version); ;?>"></script>
<script src="../js/earthcal-init.js?v=<?php echo ($version); ;?>"></script>
<script src="../js/language-switcher.js?v=<?php echo ($version); ;?>2"></script>










<script src="../js/core-2025.js?v=3<?php echo ($version); ;?>"></script>


<!--This enables the Light and Dark mode switching-->
<script type="module" src="../js/mode-toggle.mjs.js?v=<?php echo ($version); ;?>"></script>







<!-- Inline styling to lay out the most important part of the page for first load view-->

<STYLE>

@font-face {
  font-family: "Mulish";
  src: url("../fonts/Mulish-Light.ttf");
  font-display: swap;
  font-weight: 300;
 }

 @font-face {
  font-family: "Mulish";
  src: url("../fonts/Mulish-Regular.ttf");
  font-display: swap;
  font-weight: 500;
 }

 @font-face {
  font-family: "Arvo";
  src: url("../fonts/Arvo-Regular.ttf");
  font-display: swap;
 }




/*-----------------------------------------

INFO MODAL

--------------------------------------*/

#form-modal-message {
position: fixed;
top: 0px;
left: 0px;
width: 100%;
height: 100%;
background-color: var(--show-hide);
justify-content: center;
align-items: center;
z-index: 1000;
display: none;
}

.modal-content-box {

  position: relative;
  color: var(--h1);
  font-family: 'Mulish', sans-serif;
  display: flex;
  margin: auto;
}

@media screen and (min-width: 700px) {


    .modal-content-box {
        padding: 20px;
        border-radius: 10px;
        max-width: 90%;
        max-height: 80vh;
        min-height: 50%;
        min-width: 70%;
        width: 50%;

    }
}

@media screen and (max-width: 700px) {

    .modal-content-box {
        padding: 18px;
        border-radius: 10px;
        width: 98%;
        height: 95%;
        max-height: 98vh;
        /*background: none !important;*/
    }

}

.modal-message {
  margin: auto;
}



.modal-hidden {
    display: none;
}

.modal-shown {
    display: flex;
}

 .buwana-word-mark {
 background: url('../svgs/b-logo.svg') center no-repeat;
   background-size: contain;
  height: 30px;
  width: 200px;
  margin: auto;
  margin-top: 5px;

  }


@media screen and (max-width: 700px) {
.the-app-logo {
max-height: 200px;
}

 .buwana-word-mark {
  max-height: 22px;
  }
}


.the-app-logo {
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 80%;
  height: 25%;
  margin: auto;
}

#top-app-logo {
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  height: 80%;
  display: flex;
  cursor: pointer;
  width: 100%;
  margin-right: 70px;
  margin-top: 5px;
}





</style>





</HEAD>


<BODY>


<div id="form-modal-message" class="modal-hidden">
    <button type="button" onclick="closeInfoModal()" aria-label="Click to close modal" class="x-button"></button>
    <div class="modal-content-box" id="modal-content-box">
        <div class="modal-message"></div>
    </div>
    <div class="modal-photo-box" id="modal-photo-box">
        <div class ="modal-photo"></div>
    </div>

</div>



<!-- MAIN MENU -->
<div id="main-menu-overlay" class="overlay-settings" style="display:none;">
  <button type="button" onclick="closeMainMenu()" aria-label="Click to close main menu page" class="x-button"></button>
  <div class="overlay-content-settings">

<div class="the-app-logo"
     alt="<?= htmlspecialchars($app_info['app_display_name']) ?> App Logo"
     title="<?= htmlspecialchars($app_info['app_display_name']); ?> <?= htmlspecialchars($app_info['app_version']); ?> | <?= htmlspecialchars($app_info['app_slogan']) ?>"
     data-light-logo="<?= htmlspecialchars($app_info['app_logo_url']); ?>"
     data-dark-logo="<?= htmlspecialchars($app_info['app_logo_dark_url']) ?>">
</div>




 <?php if (empty($_SESSION['buwana_id'])): ?>
   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($app_info['app_login_url']) ?>">
       <?= htmlspecialchars($app_info['app_display_name']) ?> <span data-lang-id="1000-login" style="margin-left: 6px; margin-right:auto;text-align:left !important">Login</span>
     </a>
     <span class="status-circle" style="background-color: limegreen;" title="Login directly"></span>
   </div>
 <?php else: ?>
   <?php
     $first_name = '';
     $earthling_emoji = '';
     $buwana_id = intval($_SESSION['buwana_id']);
     if (isset($buwana_conn)) {
         $stmt = $buwana_conn->prepare('SELECT first_name, earthling_emoji FROM users_tb WHERE buwana_id = ?');
         if ($stmt) {
             $stmt->bind_param('i', $buwana_id);
             if ($stmt->execute()) {
                 $stmt->bind_result($first_name, $earthling_emoji);
                 $stmt->fetch();
             }
             $stmt->close();
         }
     }

    // Build the logout link so that it goes through the dedicated logout script
    // which clears the session before redirecting back to login.php
    $logout_url = 'logout.php';

    // Append query parameters if they are available
    $params = [];
    if (!empty($buwana_id)) {
        $params[] = 'buwana=' . urlencode($buwana_id);
    }
    if (!empty($client_id)) {
        $params[] = 'app=' . urlencode($client_id);
    }
    if (!empty($page)) {
        $params[] = 'redirect=' . urlencode($page);
    }

    if (!empty($params)) {
        $logout_url .= '?' . implode('&', $params);
    }
   ?>
   <div class="menu-page-item" style="pointer-events:auto;">
     <a href=""><span style="margin-right:5px;">Logged in as </span><span style="margin-right:auto;"><?= htmlspecialchars($first_name) ?></span></a>
     <span style="margin-right:-8px;"><?= htmlspecialchars($earthling_emoji) ?></span>
   </div>
   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($logout_url) ?>">Log out</a>
     <span style="margin-right:-8px;" title="Log out">🐳</span>

   </div>
   <?php

    $profile_url = 'edit-profile.php';
    $params = [];

    if (!empty($buwana_id)) {
        $params[] = 'buwana=' . urlencode($buwana_id);
    }

    if (!empty($client_id)) {
        $params[] = 'app=' . urlencode($client_id);
    }

    $connection_id = $_SESSION['connection_id'] ?? null;
    if (!empty($connection_id)) {
        $params[] = 'con=' . urlencode($connection_id);
    }

    if (!empty($params)) {
        $profile_url .= '?' . implode('&', $params);
    }
   ?>
    <div class="menu-page-item">
        <a href="<?= htmlspecialchars($app_info['app_dashboard_url']) ?>">Dashboard</a>
          <span class="status-circle" style="background-color: GREEN;" title="Terms of Use"></span>

      </div>
   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($profile_url) ?>">Edit user profile</a>
       <span class="status-circle" style="background-color: LIMEGREEN;" title="Terms of Use"></span>

   </div>

 <?php endif; ?>


<!--<div class="menu-page-item">
      <a href="bug-report.php" data-lang-id="1000-bug-report">
        Report a Bug

      </a>
      <span class="status-circle" style="background-color: blue;" title="Under development"></span>

      -->

<div class="menu-page-item" style="text-align:left !important">
<a href="#" onclick="openTermsModal(); return false;"><span><?= htmlspecialchars($app_info['app_display_name']) ?></span><span data-lang-id="1000-terms-of-use-X" style="margin-left: 6px;margin-right:auto;text-align:left !important">Terms</span></a>

  <span class="status-circle" style="background-color: YELLOW;" title="Terms of Use"></span>
</div>
<div class="menu-page-item">
<a href="#" onclick="openBuwanaPrivacy(); return false;" data-lang-id="1000-privacy-policy">Privacy</a>

  <span class="status-circle" style="background-color: RED;" title="Privacy Policy"></span>
</div>

<div class="menu-page-item">
        <a href="javascript:void(0);" onclick="openAboutApp()">
          About <?= htmlspecialchars($app_info['app_display_name']) ?>
        </a>
    <span class="status-circle" style="background-color: fuchsia;" title="About the app"></span>
    </div>

<div class="menu-page-item">
        <a href="javascript:void(0);" onclick="openAboutEarthen()" data-lang-id="1000-about-earthen">
          About Earthen
        </a>
    <span class="status-circle" style="background-color: fuchsia;" title="Under development"></span>
    </div>


<div class="menu-page-item">
  <a href="javascript:void(0);" onclick="openAboutBuwana()" data-lang-id="1000-about-buwana">
    About Buwana
  </a>
  <span class="status-circle" style="background-color: Blue;" title="About the Buwana Project"></span>
</div>


<h4 class="app-slogan" style="margin-top:30px"><?= htmlspecialchars($app_info['app_slogan']) ?></h4>

<p style="margin:auto;margin-bottom: 5px;font-size: smaller; text-align: center;" data-lang-id="1000-authentication-by" >Authentication by</p>
<div class="buwana-word-mark" alt="Buwana Logo" title="Authentication by Buwana" onclick="navigateTo('index.php')" style="cursor:pointer;"></div>

  </div> <!-- close overlay-content-settings -->
</div> <!-- close main menu -->



<div id="page-content" class="page-wrapper"> <!--modal blur added here-->





<!-- HEADER / TOP MENU -->
<div id="header" class="top-menu">
  <!-- Left Menu Button -->
  <button type="button" class="side-menu-button" onclick="openSideMenu()" aria-label="Open Main Menu"></button>

  <!-- Center header App Logo -->


  <?php if (stripos($page, 'buwana') === false): ?>
      <div id="top-app-logo"
             title="<?= htmlspecialchars($app_info['app_display_name']) ?> | v<?= htmlspecialchars($app_info['app_version']) ?>"
             onclick="redirectToAppHome('<?= htmlspecialchars($app_info['app_url']) ?>')"
             data-light-wordmark="<?= htmlspecialchars($app_info['app_wordmark_url']) ?>"
             data-dark-wordmark="<?= htmlspecialchars($app_info['app_wordmark_dark_url']) ?>">
        </div>
  <?php else: ?>
      <div id="buwana-top-logo"
          alt="Buwana Logo"
          title="Authentication by Buwana">
      </div>
  <?php endif; ?>



  <!-- Right Settings Buttons -->
  <div id="function-icons">
    <div id="settings-buttons" aria-label="App Settings Panel">
      <button type="button"
              id="top-settings-button"
              aria-label="Toggle settings menu"
              aria-expanded="false"
              aria-controls="language-menu-slider login-menu-slider"
              onclick="toggleSettingsMenu()">
      </button>

      <!-- Language Switch -->
      <div id="language-code"
           onclick="showLangSelector()"
           role="button"
           tabindex="0"
           aria-haspopup="true"
           aria-expanded="false"
           aria-controls="language-menu-slider"
           aria-label="Switch language">
        🌐 <span data-lang-id="000-language-code">EN</span>
      </div>

      <!-- Login Services -->
      <button type="button"
              class="top-login-button"
              onclick="loginOrMenu('<?php echo htmlspecialchars($app_login_url); ?>', <?php echo $is_logged_in ? 'true' : 'false'; ?>)"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="login-menu-slider"
              aria-label="Login Services">
      </button>

      <!-- Dark Mode Toggle -->
      <dark-mode-toggle
        id="dark-mode-toggle-5"
        class="slider"
        style="min-width:82px;margin-top:-5px;margin-bottom:-15px;"
        appearance="toggle">
      </dark-mode-toggle>
    </div>
  </div>
</div>


<!-- LANGUAGE SELECTOR -->
<div id="language-menu-slider" class="top-slider-menu" tabindex="-1" role="menu">
  <div class="lang-selector-box">
    <button onclick="navigateTo('../id/<?php echo $active_url; ?>')">🇮🇩 IN</button>
    <button onclick="navigateTo('../es/<?php echo $active_url; ?>')">🇪🇸 ES</button>
    <button onclick="navigateTo('../fr/<?php echo $active_url; ?>')">🇫🇷 FR</button>
    <button onclick="navigateTo('../en/<?php echo $active_url; ?>')">🇬🇧 EN</button>
    <button onclick="navigateTo('../ar/<?php echo $active_url; ?>')">🇸🇦 AR</button>
    <button onclick="navigateTo('../zh/<?php echo $active_url; ?>')">🇨🇳 中文</button>
    <button onclick="navigateTo('../de/<?php echo $active_url; ?>')">🇩🇪 DE</button>
  </div>
</div>



<!-- LOGIN SELECTOR -->
<div id="login-menu-slider" class="top-slider-menu" tabindex="-1" role="menu">
  <div class="login-selector-box" id="login-selector-box">
    <?php if ($is_logged_in && !empty($connected_apps)): ?>
        <?php foreach ($connected_apps as $connected_app): ?>
            <a class="login-app-logo" target="_blank" href="<?= htmlspecialchars($connected_app['app_login_url']) ?>"
               alt="<?= htmlspecialchars($connected_app['app_display_name']) ?> App Logo"
               title="<?= htmlspecialchars($connected_app['app_display_name']) ?> <?= htmlspecialchars($connected_app['app_version']) ?> | <?= htmlspecialchars($connected_app['app_slogan']) ?>"
               data-light-logo="<?= htmlspecialchars($connected_app['app_icon_url']) ?>"
               data-dark-logo="<?= htmlspecialchars($connected_app['app_icon_url']) ?>">
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <button type="button" class="login-selector-close" id="login-selector-close" onclick="hideLoginSelector()" aria-label="Close login selector"></button>
</div>


<div id="main" style="padding-top: 40px;">
