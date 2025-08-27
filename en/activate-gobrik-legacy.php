<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';
require_once '../earthenAuth_helper.php'; // Include the authentication helper functions

// function build_login_url($base, array $params) {
//     $delimiter = (strpos($base, '?') !== false) ? '&' : '?';
//     return $base . $delimiter . http_build_query($params);
// }

// Page setup
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.4';
$page = 'activate';
$version = '0.776';
$lastModified = date("Y-m-d\TH:i:s\Z", filemtime(__FILE__));

// Already logged in?
if (!empty($_SESSION['buwana_id'])) {
    $redirect_url = $_SESSION['redirect_url'] ?? $app_info['app_url'] ?? '/';
    echo "<script>
        alert('Looks like you’re already logged in! Redirecting to your dashboard...');
        window.location.href = '$redirect_url';
    </script>";
    exit();
}

$is_logged_in = false; // Ensure not logged in for this page

$response = ['success' => false];
$ecobricker_id = $_GET['id'] ?? null;
$first_name = '';
$email_addr = '';

// PART 2: Check if ecobricker_id is passed in the URL
if (is_null($ecobricker_id)) {
    echo '<script>
        alert("Hmm... something went wrong. No ecobricker ID was passed along. Please try logging in again. If this problem persists, you\'ll need to create a new account.");
        window.location.href = "login.php";
    </script>';
    exit();
}

// PART 3: Look up user information using ecobricker_id provided in URL

//gobrik_conn creds
require_once ("../gobrikconn_env.php");

// Prepare and execute SQL statement to fetch user details
$sql_user_info = "SELECT first_name, email_addr FROM tb_ecobrickers WHERE ecobricker_id = ?";
$stmt_user_info = $gobrik_conn->prepare($sql_user_info);
if ($stmt_user_info) {
    $stmt_user_info->bind_param('i', $ecobricker_id);
    $stmt_user_info->execute();
    $stmt_user_info->bind_result($first_name, $email_addr);
    $stmt_user_info->fetch();
    $stmt_user_info->close();
} else {
    die('Error preparing statement for fetching user info: ' . $gobrik_conn->error);
}

$gobrik_conn->close();
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<?php require_once ("../includes/activate-inc.php");?>

<!-- PAGE CONTENT -->
<?php
$page_key = 'signup_1';
?>
<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;">

            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

            <div style="text-align:center;width:100%;margin:auto;">
                <h2><?php echo htmlspecialchars($first_name); ?>, <span data-lang-id="0001-activate-notice">since you've last logged in, we've made a massive upgrade to GoBrik.</span></h2>

                <p data-lang-id="0002-activate-explantion-1" style="font-weight:bold">Our old version of GoBrik ran on corporate servers and code.   We've let this pass pass away.</p>

                <p><span data-lang-id="0002-activate-explantion-2">In its place, we have migrated all our data to our own independent, self-run server.  Our new GoBrik 3.0 is now 100% open source fully focused on ecological accountability.  As an alternative to logging in with Google, Apple or Facebook we've developed our own login system (what we're calling Buwana accounts).  To join us on the regenerated GoBrik with please take a minute to upgrade your old </span> <?php echo htmlspecialchars($email_addr); ?> <span data-lang-id="0002-activate-explantion-3">account to our new system.</span></p>
            </div>

            <!--SIGNUP FORM-->
            <form id="activate-confirmation" method="post" action="confirm-email.php?id=<?php echo htmlspecialchars($ecobricker_id); ?>">
                <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                    <div id="submit-section" style="text-align:center;margin-top:20px;padding-right:15px;padding-left:15px" title="Start Activation process" data-lang-id="0003-activate-button">
                        <input type="submit" id="submit-button" value="🍃 Upgrade Account!" class="submit-button activate">
                    </div>
                </div>
            </form>

            <p data-lang-id="0004-buwana-accounts" style="font-size:1em; text-align: center;">Buwana accounts are designed with ecology, security, and privacy in mind. Soon, you'll be able to login to other great regenerative apps movement in the same way you login to GoBrik!.</p>
            <div style="display:flex;flex-flow:row;justify-content:center;width:100%;margin-top:10px">
                <div><a href="#" onclick="showModalInfo('terms')" class="underline-link" style="margin:auto;padding: 15px;text-align:center;display:block;background: var(--lighter);  border-radius: 10px;margin: 5px;" data-lang-id="0005-new-terms">New Buwana & GoBrik Terms of Service</a></div>

                <div><a href="https://earthen.io/gobrik-regen" class="underline-link" target="_blank" style="margin:auto;padding: 15px;text-align:center;display:block;background: var(--lighter);  border-radius: 10px;margin: 5px;" data-lang-id="0005-regen-blog">Why?  Read our 'Great GoBrik Regeneration' blog post.</a></div>
                <div><a href="https://github.com/gea-ecobricks/gobrik-3.0" class="underline-link" target="_blank" style="margin:auto;padding: 15px;text-align:center;display:block;background: var(--lighter);  border-radius: 10px;margin: 5px;" data-lang-id="0006-github-code">New Github Source Code Repository</a></div>
           </div>

            <div class="form-item" style="margin: 70px 10px 40px 10px;">
                <p style="text-align:center;"><span data-lang-id="0007-not-interested">If you're not interested and would like your old </span><?php echo htmlspecialchars($email_addr); ?><span data-lang-id="0009-that-too"> account completely deleted, you can do that too.</span></p>
                <!-- DELETE ACCOUNT FORM -->
                <form id="delete-account-form" method="post" action="../api/delete_accounts.php?id=<?php echo htmlspecialchars($ecobricker_id); ?>">
                    <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                        <button type="button" class="submit-button delete" onclick="confirmDeletion()" data-lang-id="0010-delete-button">Delete My Account</button>
                    </div>

                </form>
                <p data-lang-id="0011-warning" style="font-size:medium; text-align: center;">WARNING: This cannot be undone.</p>
                <br>
             </div>
        </div>
    </div>
</div>
</div>

<!--FOOTER STARTS HERE-->
<?php require_once ("../footer-2025.php"); ?>

<script>
function confirmDeletion() {
    if (confirm("Are you certain you wish to delete your account? This cannot be undone.")) {
        if (confirm("Ok. We will delete your account! Note that this does not affect ecobrick data that has been permanently archived in the brikchain. If you have a Buwana account and/or a subscription to our Earthen newsletter it will also be deleted.")) {
            document.getElementById('delete-account-form').submit();
        }
    }
}
</script>

</body>
</html>
