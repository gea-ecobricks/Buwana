<?php
require_once '../earthenAuth_helper.php'; // Include the authentication helper functions
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../buwanaconn_env.php';         // Sets up $buwana_conn

// Logging helper for authentication steps
$authLogFile = dirname(__DIR__) . '/logs/auth.log';
function auth_log($message) {
    global $authLogFile;
    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . "] LOGIN: " . $message . PHP_EOL, 3, $authLogFile);
}
auth_log('Login page requested from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// If the user was redirected here with a loggedout status, clear any
// existing session data to avoid redirect loops back to the dashboard
$status = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
if ($status === 'loggedout') {
    unset($_SESSION['buwana_id'], $_SESSION['jwt']);
    session_destroy();
    startSecureSession();
}

// 1️⃣ If arriving via OAuth, client_id already stored in pending_oauth_request
if (isset($_SESSION['pending_oauth_request']['client_id'])) {
    $_SESSION['client_id'] = $_SESSION['pending_oauth_request']['client_id'];
} else {
    // 2️⃣ Otherwise, check query param ?app= or fallback to session
    $client_id_param = $_GET['app'] ?? ($_GET['client_id'] ?? null);
    if (!$client_id_param && isset($_SESSION['client_id'])) {
        $client_id_param = $_SESSION['client_id'];
    }
    if (!$client_id_param) {
        header('Location: index.php');
        exit();
    }
    $_SESSION['client_id'] = filter_var($client_id_param, FILTER_SANITIZE_SPECIAL_CHARS);
}



require_once '../fetch_app_info.php';         // Retrieves designated app's core data

if (!empty($app_info['client_id'])) {
    $_SESSION['client_id'] = $app_info['client_id'];
}

// Set page variables
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.779';
$page = 'login';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));
$is_logged_in = false; // Ensure not logged in for this page

// Initialize user variables
$first_name = '';
$buwana_id = '';
$is_logged_in = isLoggedIn(); // Check if the user is logged in using the helper function

// Check if user is logged in and session active
if ($is_logged_in) {
    if (isset($_SESSION['pending_oauth_request'])) {
        // Redirect to authorize.php to continue the flow
        $params = http_build_query($_SESSION['pending_oauth_request']);
        auth_log("Logged in. Redirecting to authorize.php with saved OAuth params.");
        header("Location: /authorize.php?$params");
        exit();
    }

    // Fallback to dashboard if no OAuth flow in progress
    $redirect_url = $app_info['app_dashboard_url'] ?? 'dashboard.php';
    auth_log('User already logged in, redirecting to ' . $redirect_url);
    header("Location: $redirect_url");
    exit();
}


// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$is_logged_in = '';
// Get the id (buwana_id), code, and key (credential_key) from URL
$buwana_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : '';
$code = isset($_GET['code']) ? filter_var($_GET['code'], FILTER_SANITIZE_SPECIAL_CHARS) : ''; // Extract code from the URL
$credential_key = ''; // Initialize $credential_key as empty
$first_name = '';  // Initialize the first_name variable
$redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
auth_log("Query parameters - status: $status, id: $buwana_id, redirect: $redirect");

// Check if buwana_id is available and valid to fetch corresponding email and first_name from users_tb
if (!empty($buwana_id)) {
    require_once '../buwanaconn_env.php'; // Sets up buwana_conn database connection

    // Prepare the query to fetch the email and first_name from users_tb
    $sql = "SELECT email, first_name FROM users_tb WHERE buwana_id = ?";

    if ($stmt = $buwana_conn->prepare($sql)) {
        // Bind the buwana_id parameter
        $stmt->bind_param("i", $buwana_id);

        // Execute the statement
        if ($stmt->execute()) {
            // Bind the result
            $stmt->bind_result($fetched_email, $fetched_first_name);

            // Fetch the result and overwrite the email and first_name if found
            if ($stmt->fetch()) {
                $credential_key = $fetched_email;  // Store the fetched email
                $first_name = $fetched_first_name;  // Store the fetched first_name
                auth_log("Fetched user info for buwana_id $buwana_id");
            }
        }

        // Close the statement
        $stmt->close();
    } else {
        auth_log('Error preparing statement: ' . $buwana_conn->error);
    }

    auth_log('Database connection closed');
    $buwana_conn->close();
}

// Echo the HTML structure
echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">
<head>
<meta charset="UTF-8">

';

// JavaScript variables for dynamic use
echo '<script>';
echo 'const status = "' . addslashes($status) . '";';
echo 'const firstName = "' . addslashes($first_name) . '";';
echo 'const buwanaId = "' . addslashes($buwana_id) . '";';
echo 'const code = "' . addslashes($code) . '";';
echo 'const appDisplayName = "' . addslashes($app_info['app_display_name'] ?? '') . '";';
echo '</script>';
?>


<!-- Include necessary scripts and styles -->
<?php require_once ("../includes/login-inc.php");?>

<!--
    <div class="splash-title-block"></div>
    <div id="splash-bar"></div>-->

<!-- PAGE CONTENT -->
   <?php
   $page_key = str_replace('-', '_', $page); // e.g. 'signup-1' → 'signup_1'
   ?>

   <div id="top-page-image"
        class="top-page-image"
        data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
        data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
   </div>

<div id="form-submission-box" class="landing-page-form">
    <div class="form-container">

     <!-- This is the welcome header and subtitle that are custom generated by the javascript depending on the status returned in the url

     Update to include translations and variations of the H4 tag-->

    <div style="text-align:center;width:100%;margin:auto;" >
        <div id="status-message">Login to <?= htmlspecialchars($app_info['app_display_name']) ?></div>
        <div id="sub-status-message">Please signin with your account credentials.</div>
    </div>

   <!-- Form starts here-->
<form id="login" method="post" action="../processes/login_process_jwt.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>"> <!-- Add this line -->
    <input type="hidden" name="client_id" value="<?= htmlspecialchars($app_info['client_id']) ?>">
    <input type="hidden" name="response_type" value="id_token">
    <input type="hidden" name="scope" value="openid email profile">

    <div class="form-item" style="border-radius: 10px 10px 0px 0px;">
        <!--<p style="text-align:center;">Login with your Buwana account credentials.</p>-->
        <div id="credential-input-field" class="input-wrapper" style="position: relative;">
            <input type="text" id="credential_key" name="credential_key" required placeholder="Your e-mail...">
            <span class="toggle-select-key" style="cursor: pointer; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);font-size:18px;">🌏</span>
            <div id="dropdown-menu" style="display: none; position: absolute; right: 10px; top: 100%; z-index: 1000; background: white; border: 1px solid #ccc; width: 150px; text-align: left;">
                <div class="dropdown-item" value="Your email...">E-mail</div>
                <div class="dropdown-item disabled" style="opacity: 0.5;">SMS</div>
                <div class="dropdown-item disabled" style="opacity: 0.5;">Phone</div>
                <div class="dropdown-item disabled" style="opacity: 0.5;">GEA Peer</div>
            </div>
        </div>
        <div id="no-buwana-email" data-lang-id="001-cant-find" class="form-field-error" style="display:none;margin-top: 0px;margin-bottom:-15px;">🤔 We can't find this credential in the database.</div>
    </div>

    <div class="form-item" id="password-form" style="height:111px;margin-top: -8px;border-radius: 0px 0px 10px 10px;">
        <div class="password-wrapper" style="position: relative;">
            <div data-lang-id="005-password-field-placeholder">
                <input type="password" id="password" name="password" placeholder="Your password..." required>
            </div>
            <span toggle="#password" class="toggle-password" style="cursor: pointer; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);font-size:18px;">🙈</span>
        </div>
        <div id="password-error" data-lang-id="002-password-is-wrong" class="form-field-error" style="display:none;margin-top: 0px;margin-bottom:-  5px;">👉 Password is wrong.</div>
        <p class="form-caption"><span data-lang-id="003-forgot-your-password">Forgot your password?</span> <a href="#" onclick="showPasswordReset('reset', '<?php echo $lang; ?>', '')" class="underline-link" data-lang-id="000-reset-it">Reset it.</a></p>
    </div>

                        <div class="form-item" id="code-form" style="text-align:center;margin-top: -8px;border-radius: 0px 0px 10px 10px;">

                            <div class="code-wrapper" style="position: relative;">
                                <input type="text" maxlength="1" class="code-box" placeholder="-">
                                <input type="text" maxlength="1" class="code-box" placeholder="-">
                                <input type="text" maxlength="1" class="code-box" placeholder="-">
                                <input type="text" maxlength="1" class="code-box" placeholder="-">
                                <input type="text" maxlength="1" class="code-box" placeholder="-">
                            </div>
                        <p id="code-status" class="form-caption" data-lang-id="003-code-status" style="margin-top:5px;">A code to login will be sent to your email.</p>

                        </div>

                        <div style="text-align:center;width:100%;margin:auto;margin-top:16px;max-width:500px;" id="login-buttons">
                             <div class="toggle-container">
                                                        <input type="radio" id="password" name="toggle" value="password" checked>
                                                        <input type="radio" id="code" name="toggle" value="code">
                                                        <div class="toggle-button password">🔑</div>
                                                        <div class="toggle-button code">📱</div>
                                                        <div class="login-slider"></div>
                                                        <span data-lang-id="004-login-button">
                                                            <input type="submit" id="submit-password-button" value="Login" class="login-button-75">
                                                        </span>
                                                        <input type="button" id="send-code-button" value="📨 Send Code" class="code-button-75" style="display:none;">
                                                    </div>
                            <div id="code-error" data-lang-id="002-password-wrong" class="form-field-error" style="display:none;margin-top: 5px;margin-bottom:-15px;">👉 Entry is incorrect.</div>
                        </div>
                    </form>



    </div>

 <div style="font-size: medium; text-align: center; margin: auto; align-self: center;padding-top:40px;padding-bottom:50px;margin-top: 0px;height:100%;">
        <p style="font-size:medium;" data-lang-id="000-no-account-yet">Don't have an account yet? <a href="signup-1.php?app=<?= urlencode($app_info['client_id']) ?>">Signup!</a></p>
    </div>

</div>

</div>

</div>

<!-- FOOTER STARTS HERE -->
<?php require_once ("../footer-2025.php");?>

<script src="../js/login.js?v=<?php echo ($version); ;?>4" defer></script>
<?php require_once ("../scripts/app_modals.php");?>


<script>




</script>



</body>
</html>
