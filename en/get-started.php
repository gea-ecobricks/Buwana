<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Hardcode the client ID for this page
$_GET['app'] = 'buwana_mgr_001';

require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$is_logged_in = !empty($_SESSION['buwana_id']);
$buwana_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;
if (!$buwana_id && $is_logged_in) {
    $buwana_id = intval($_SESSION['buwana_id']);
}

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'get-started';
$version = '0.1';
$lastModified = date("Y-m-d\\TH:i:s\\Z", filemtime(__FILE__));

// Echo the HTML structure
// We use echo to minimize PHP/HTML switching

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

<?php require_once ("../includes/get-started-inc.php");?>

<!-- PAGE CONTENT -->
   <?php
   $page_key = 'signup_1';
   ?>
<div class="page-panel-group">
    <div id="form-submission-box" class="landing-page-form" style="min-height:calc( 100vh - 54px)">
        <div class="form-container" style="box-shadow: #0000001f 0px 5px 20px;margin:auto;">

            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_light']) ?>"
                 data-dark-img="<?= htmlspecialchars($app_info[$page_key . '_top_img_dark']) ?>">
            </div>

            <div style="padding:20px; max-width:800px; margin:auto;">
                <h1 data-lang-id="001-get-started-title">Getting Started with Buwana SSO</h1>
                <p data-lang-id="002-intro">Welcome, fellow Earth-dweller! 🌍 You're about to tap into the enchanted ecosystem of the <strong>Buwana Federated Login System</strong> — a soulful, secure, and sovereign way to authenticate users across regenerative apps. Whether you're building a bioregional barter bazaar or a community compost tracker, Buwana is here to connect your users under one enchanted identity.</p>

                <h2 data-lang-id="003-what-is-title">💡 What is Buwana SSO?</h2>
                <p data-lang-id="004-what-is-desc">Think of it as the <em>magic ring</em> 💍 of access — a <a href="https://github.com/GoBrik/Buwana/wiki/Buwana-JWT-login---How-It-Works">JWT-based login system</a> blessed by the village gem master (a.k.a. the Buwana Auth Server). Once your users sign in through Buwana, they receive a unique token that grants access to all participating Buwana apps, securely and seamlessly.</p>

                <h2 data-lang-id="005-why-use-title">🌍 Why Use Buwana?</h2>
                <ul>
                    <li data-lang-id="005a-one-login">✨ <strong>One login, many apps:</strong> Reduce login fatigue and unify identities.</li>
                    <li data-lang-id="005b-privacy-first">🔒 <strong>Privacy-first:</strong> No data-mining, no corporate overlords — just ethical authentication.</li>
                    <li data-lang-id="005c-decentralized-open">🪴 <strong>Decentralized + Open:</strong> Built with open standards like <a href="https://github.com/GoBrik/Buwana/wiki/JWT-Buwana-Federation-&-OpenID-Architecture">OpenID Connect</a> and OAuth2.</li>
                    <li data-lang-id="005d-regenerative-values">💚 <strong>Regenerative values:</strong> Aligned with Earth, community, and bioregional flow.</li>
                </ul>

                <h2 data-lang-id="006-how-work-title">🔧 How Does It Work?</h2>
                <p data-lang-id="007-how-work-desc">The login flow is a dance between your app and the Buwana Auth Server. Here’s the groove:</p>
                <ol>
                    <li data-lang-id="008a-step1">User logs in at <code>/login-jwt.php</code></li>
                    <li data-lang-id="008b-step2"><code>login_process_jwt.php</code> validates the password, issues a signed JWT</li>
                    <li data-lang-id="008c-step3">JWT is stored in session and/or sent to your app as a Bearer token</li>
                    <li data-lang-id="008d-step4">Your app verifies it via the public JWKS endpoint: <code>/.well-known/jwks.php</code></li>
                    <li data-lang-id="008e-step5">Optional: Call <code>/userinfo.php</code> to retrieve claims based on scopes</li>
                </ol>
                <p data-lang-id="009-more-deets">More deets in the <a href="https://github.com/GoBrik/Buwana/wiki/Buwana-JWT-login---How-It-Works">"How It Works"</a> scroll in our sacred GitHub temple.</p>

                <h2 data-lang-id="010-integrate-title">🚀 Integrate Buwana Into Your App</h2>
                <p data-lang-id="011-integrate-desc">Ready to make the leap? Here’s what to do:</p>
                <ol>
                    <li data-lang-id="012a-integrate-step1">👩‍💻 <strong>Register your app:</strong> Contact the Buwana team to register your <code>client_id</code> and get your private key into <code>apps_tb</code>.</li>
                    <li data-lang-id="012b-integrate-step2">🔑 <strong>Add login logic:</strong> Use our sample login form and connect to <code>authorize.php</code> with PKCE if you’re frontend-only.</li>
                    <li data-lang-id="012c-integrate-step3">🧪 <strong>Test your JWTs:</strong> Validate your issued tokens using <code>validate-test.php</code>.</li>
                    <li data-lang-id="012d-integrate-step4">📜 <strong>Explore the scopes:</strong> Use <a href="https://github.com/GoBrik/Buwana/wiki/JWT-Buwana-Federation-&-OpenID-Architecture#core-concepts">predefined scopes</a> like <code>profile</code>, <code>email</code>, and <code>buwana:earthlingEmoji</code>.</li>
                </ol>

                <h2 data-lang-id="013-earthenauth-title">🗺️ The EarthenAuth_db</h2>
                <p data-lang-id="014-earthenauth-desc">Your users’ credentials, profiles, communities, and even bioregional data — all lovingly structured in our <code>EarthenAuth_db</code>. Learn more in our <a href="https://github.com/GoBrik/Buwana/wiki/Buwana-System">Buwana System wiki</a>.</p>

                <h2 data-lang-id="015-dive-deeper-title">📚 Dive Deeper</h2>
                <ul>
                    <li><a data-lang-id="016a-dive-resource1" href="https://github.com/GoBrik/Buwana/wiki/Buwana-JWT-login---How-It-Works">JWT Login – How It Works</a></li>
                    <li><a data-lang-id="016b-dive-resource2" href="https://github.com/GoBrik/Buwana/wiki/JWT-Buwana-Federation-&-OpenID-Architecture">Federation Architecture</a></li>
                    <li><a data-lang-id="016c-dive-resource3" href="https://github.com/GoBrik/Buwana/wiki/Buwana-System">EarthenAuth_db Table Overview</a></li>
                </ul>

                <h2 data-lang-id="017-co-weave-title">🙏 Let’s Co-Weave the Future</h2>
                <p data-lang-id="018-co-weave-desc">If you’re building apps for the Earth and her people, Buwana is here for you. Reach out, plug in, and let's weave the web of regenerative tech together. 🌿</p>
            </div>
        </div>
    </div>
</div>

<!-- FOOTER STARTS HERE -->
<?php require_once ("../footer-2025.php");?>

</body>
</html>
