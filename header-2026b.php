<!-- PHP starts by laying out canonical URLs for the page and language -->

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['buwana_id']);
$isAdminUser = $isAdminUser ?? false;

// Pull key session values if they haven't been explicitly defined
$client_id = $client_id ?? ($_SESSION['client_id'] ?? null);
$buwana_id = $buwana_id ?? ($_SESSION['buwana_id'] ?? null);

$parts = explode("/", $_SERVER['SCRIPT_NAME']);
$name = $parts[count($parts) - 1];
if (strcmp($name, "welcome.php") == 0) {
    $name = "";
}

// Get full request URI (e.g. "/en/signup-1.php?buwana_...")
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
// Sort apps by icon filename length (ascending) for deterministic ordering
usort($connected_apps, fn($a, $b) =>
    strlen(basename($a['app_icon_url'])) - strlen(basename($b['app_icon_url']))
);
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

<link rel="stylesheet" type="text/css" href="../styles/mode-slider.css?v=4">
<link rel="stylesheet" type="text/css" href="../styles/main.css?v=6<?php echo ($version); ;?>">
<link rel="stylesheet" type="text/css" href="../styles/styles-2026.css?v=<?php echo ($version); ;?>">
<link rel="stylesheet" type="text/css" href="../styles/2026-panel-styles.css?v=<?php echo ($version); ;?>">

<!--Default Light Styles to load first-->
<link rel="preload" href="../styles/mode-light.css?v=1<?php echo ($version); ;?>" as="style" onload="this.rel='stylesheet'">
<link rel="stylesheet" href="../styles/mode-light.css?v=1<?php echo ($version); ;?>" media="(prefers-color-scheme: no-preference), (prefers-color-scheme: light)">
<link rel="stylesheet" href="../styles/mode-dark.css?v=1<?php echo ($version); ;?>" media="(prefers-color-scheme: dark)">

<link rel="stylesheet" type="text/css" href="../styles/footer.css?v=<?php echo ($version); ;?>">

<!-- Sidebar structural CSS — shared with GoBrik, no brand colours -->
<link rel="stylesheet" href="../styles/header-2026.css?v=1">

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
  src: url("../fonts/Mulish-Light.ttf") format("truetype");
  font-display: swap;
  font-weight: 300;
  font-style: normal;
 }

 @font-face {
  font-family: "Mulish";
  src: url("../fonts/Mulish-Regular.ttf") format("truetype");
  font-display: swap;
  font-weight: 400;
  font-style: normal;
 }

 @font-face {
  font-family: "Mulish";
  src: url("../fonts/Mulish-Medium.ttf") format("truetype");
  font-display: swap;
  font-weight: 500;
  font-style: normal;
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

@media screen and (max-width: 700px) {
.the-app-logo {
max-height: 200px;
}
}

/* Client app logo in the side menu — driven by $app_info at render time */
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

#buwana-top-logo {
  background: url('../svgs/b-logo.svg') center no-repeat;
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



<!-- MAIN MENU — sidebar on desktop (≥ 1201px), full-screen overlay on mobile -->
<div id="main-menu-overlay" class="overlay-settings" style="display:none;">
  <!-- Mobile/tablet close button -->
  <button type="button" onclick="closeSettings()" aria-label="Click to close main menu" class="x-button"></button>
  <!-- Desktop sidebar close button — shown only when .sidebar-panel is active -->
  <button type="button" onclick="closeSettings()" aria-label="Close menu" class="sidebar-close-btn" style="display:none;">✕</button>
  <div class="overlay-content-settings">

<?php
// Resolve client app logo for the side menu.
// $app_info is set by the calling page (e.g. overridden to the managed app in BAM pages).
// We read data-light-logo / data-dark-logo so mode-toggle.mjs.js can swap them.
$menu_logo_light = htmlspecialchars($app_info['app_logo_url'] ?? '');
$menu_logo_dark  = htmlspecialchars($app_info['app_logo_dark_url'] ?? $app_info['app_logo_url'] ?? '');
$menu_app_name   = htmlspecialchars($app_info['app_display_name'] ?? 'App');
$menu_app_ver    = htmlspecialchars($app_info['app_version'] ?? '');
$menu_app_slogan = htmlspecialchars($app_info['app_slogan'] ?? '');
?>

<div class="the-app-logo"
     alt="<?= $menu_app_name ?> App Logo"
     title="<?= $menu_app_name ?> <?= $menu_app_ver ?> | <?= $menu_app_slogan ?>"
     data-light-logo="<?= $menu_logo_light ?>"
     data-dark-logo="<?= $menu_logo_dark ?>">
</div>


 <?php if (empty($_SESSION['buwana_id'])): ?>
   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($app_info['app_login_url'] ?? $login_url) ?>">
       <?= $menu_app_name ?> <span data-lang-id="1000-login" style="margin-left: 6px; margin-right:auto;text-align:left !important">Login</span>
     </a>
     <span class="status-circle" style="background-color: limegreen;" title="Login directly"></span>
   </div>
 <?php else: ?>
   <?php
     $menu_first_name = '';
     $menu_emoji = '';
     $menu_buwana_id = intval($_SESSION['buwana_id']);
     if (isset($buwana_conn)) {
         $stmt = $buwana_conn->prepare('SELECT first_name, earthling_emoji FROM users_tb WHERE buwana_id = ?');
         if ($stmt) {
             $stmt->bind_param('i', $menu_buwana_id);
             if ($stmt->execute()) {
                 $stmt->bind_result($menu_first_name, $menu_emoji);
                 $stmt->fetch();
             }
             $stmt->close();
         }
     }

    $logout_url = 'logout.php';
    $params = [];
    if (!empty($menu_buwana_id)) {
        $params[] = 'buwana=' . urlencode($menu_buwana_id);
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

    $lang_segment = isset($lang) && $lang !== '' ? '/' . rawurlencode($lang) : '/en';

    $profile_url = $lang_segment . '/edit-profile.php';
    $profile_params = [];
    if (!empty($menu_buwana_id)) {
        $profile_params[] = 'buwana=' . urlencode($menu_buwana_id);
    }
    if (!empty($client_id)) {
        $profile_params[] = 'app=' . urlencode($client_id);
    }
    $connection_id = $_SESSION['connection_id'] ?? null;
    if (!empty($connection_id)) {
        $profile_params[] = 'con=' . urlencode($connection_id);
    }
    if (!empty($profile_params)) {
        $profile_url .= '?' . implode('&', $profile_params);
    }
   ?>
   <div class="menu-page-item" style="pointer-events:auto;">
     <a href=""><span style="margin-right:5px;">Logged in as </span><span style="margin-right:auto;"><?= htmlspecialchars($menu_first_name) ?></span></a>
     <span style="margin-right:-8px;"><?= htmlspecialchars($menu_emoji) ?></span>
   </div>

   <div class="menu-page-item">
       <a href="<?= htmlspecialchars($app_info['app_dashboard_url'] ?? $lang_segment . '/dashboard.php') ?>">Dashboard</a>
       <span class="status-circle" style="background-color: GREEN;" title="Dashboard"></span>
   </div>

   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($profile_url) ?>">Edit user profile</a>
     <span class="status-circle" style="background-color: LIMEGREEN;" title="Edit profile"></span>
   </div>

   <div class="menu-page-item">
     <a href="<?= htmlspecialchars($logout_url) ?>">Log out</a>
     <span style="margin-right:-8px;" title="Log out">🐳</span>
   </div>

 <?php endif; ?>

<div class="menu-page-item" style="text-align:left !important">
  <a href="#" onclick="openTermsModal(); return false;"><span><?= $menu_app_name ?></span><span data-lang-id="1000-terms-of-use-X" style="margin-left: 6px;margin-right:auto;text-align:left !important">Terms</span></a>
  <span class="status-circle" style="background-color: YELLOW;" title="Terms of Use"></span>
</div>

<div class="menu-page-item">
  <a href="javascript:void(0);" onclick="openAboutApp()">
    About <?= $menu_app_name ?>
  </a>
  <span class="status-circle" style="background-color: fuchsia;" title="About the app"></span>
</div>

<div class="app-slogan"><?= $menu_app_slogan ?></div>

<div class="menu-auth-by" data-lang-id="1000-authentication-by">Authentication by Buwana</div>

<p class="app-slogan-links" style="margin:0 auto 12px; text-align:center; font-family: 'Mulish', sans-serif; font-size:0.85rem;">
    <a href="javascript:void(0);" onclick="openAboutBuwana(); return false;">Buwana</a>
    <span style="margin: 0 6px;">|</span>
    <a href="javascript:void(0);" onclick="openBuwanaPrivacy(); return false;">Privacy</a>
    <span style="margin: 0 6px;">|</span>
    <a href="javascript:void(0);" onclick="openAboutEarthen(); return false;">Earthen</a>
</p>

  </div> <!-- close overlay-content-settings -->
</div> <!-- close main menu -->



<div id="page-content" class="page-wrapper"> <!--modal blur added here-->




<!-- HEADER / TOP MENU -->
<div id="header" class="top-menu">
  <!-- Left Menu Button -->
  <button type="button" class="side-menu-button" onclick="openSideMenu()" aria-label="Open Main Menu"></button>

  <!-- Center header: show Buwana logo on Buwana-named pages, client app wordmark on others -->
  <?php if (stripos($page, 'buwana') === false): ?>
      <div id="top-app-logo"
             title="<?= htmlspecialchars($app_info['app_display_name'] ?? '') ?> | v<?= htmlspecialchars($app_info['app_version'] ?? '') ?>"
             onclick="redirectToAppHome('<?= htmlspecialchars($app_info['app_url'] ?? '') ?>')"
             data-light-wordmark="<?= htmlspecialchars($app_info['app_wordmark_url'] ?? '') ?>"
             data-dark-wordmark="<?= htmlspecialchars($app_info['app_wordmark_dark_url'] ?? '') ?>">
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

      <!-- Top row of controls (always visible when settings panel is open) -->
      <div id="settings-btn-row">
        <button type="button"
                id="top-settings-button"
                aria-label="Toggle settings menu"
                aria-expanded="false"
                onclick="toggleSettingsMenu()">
        </button>

        <!-- Language Switch -->
        <div id="language-code"
             onclick="showLangSelector()"
             role="button"
             tabindex="0"
             aria-haspopup="true"
             aria-expanded="false"
             aria-label="Switch language">
          🌐 <span data-lang-id="000-language-code">EN</span>
        </div>

        <!-- Login Services -->
        <button type="button"
                class="top-login-button"
                onclick="loginOrMenu('<?php echo htmlspecialchars($app_login_url); ?>', <?php echo $is_logged_in ? 'true' : 'false'; ?>)"
                aria-haspopup="true"
                aria-expanded="false"
                aria-label="Login Services">
        </button>

        <!-- Dark Mode Toggle -->
        <dark-mode-toggle
          id="dark-mode-toggle-5"
          class="slider"
          style="min-width:82px;margin-top:-5px;margin-bottom:-15px;"
          appearance="toggle">
        </dark-mode-toggle>
      </div><!-- /settings-btn-row -->

      <!-- Expandable grid panel — grows downward when lang or app button is clicked -->
      <div id="settings-expand-panel">

        <!-- Language Grid -->
        <div id="language-menu-slider" class="expand-grid-section" tabindex="-1" role="menu">
          <div class="bap-header">
            <span class="bap-title">Switch Language</span>
          </div>
          <div class="bap-grid">
            <a class="bap-lang-tile" href="../id/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../id/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇮🇩</div>
              <span class="bap-app-name">Bahasa</span>
            </a>
            <a class="bap-lang-tile" href="../es/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../es/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇪🇸</div>
              <span class="bap-app-name">Español</span>
            </a>
            <a class="bap-lang-tile" href="../fr/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../fr/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇫🇷</div>
              <span class="bap-app-name">Français</span>
            </a>
            <a class="bap-lang-tile" href="../en/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../en/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇬🇧</div>
              <span class="bap-app-name">English</span>
            </a>
            <a class="bap-lang-tile" href="../ar/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../ar/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇸🇦</div>
              <span class="bap-app-name">العربية</span>
            </a>
            <a class="bap-lang-tile" href="../zh/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../zh/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇨🇳</div>
              <span class="bap-app-name">中文</span>
            </a>
            <a class="bap-lang-tile" href="../de/<?php echo htmlspecialchars($active_url); ?>" onclick="navigateTo('../de/<?php echo htmlspecialchars($active_url); ?>'); return false;">
              <div class="bap-lang-flag">🇩🇪</div>
              <span class="bap-app-name">Deutsch</span>
            </a>
          </div>
        </div><!-- /language-menu-slider -->

        <!-- My Buwana Apps Grid -->
        <div id="login-menu-slider" class="expand-grid-section" tabindex="-1" role="dialog" aria-label="My Buwana Apps">
          <div class="bap-header">
            <span class="bap-title">My Buwana Apps</span>
          </div>
          <div class="bap-grid" id="login-selector-box">
            <?php if ($is_logged_in && !empty($connected_apps)): ?>
                <?php foreach ($connected_apps as $connected_app): ?>
                    <a class="bap-app-tile" target="_blank" href="<?= htmlspecialchars($connected_app['app_login_url']) ?>"
                       title="<?= htmlspecialchars($connected_app['app_display_name']) ?> | <?= htmlspecialchars($connected_app['app_slogan']) ?>">
                      <div class="bap-app-icon"
                           data-light-logo="<?= htmlspecialchars($connected_app['app_icon_url']) ?>"
                           data-dark-logo="<?= htmlspecialchars($connected_app['app_icon_url']) ?>">
                      </div>
                      <span class="bap-app-name"><?= htmlspecialchars($connected_app['app_display_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div><!-- /login-menu-slider -->

      </div><!-- /settings-expand-panel -->
    </div><!-- /settings-buttons -->
  </div><!-- /function-icons -->
</div><!-- /header -->

<!-- Sidebar JS — overrides openSideMenu() and closeSettings() with sidebar behaviour -->
<script src="../js/header-2026.js?v=1"></script>

<div id="main" style="padding-top: 40px;">
