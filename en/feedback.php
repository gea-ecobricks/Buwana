<?php
require_once '../earthenAuth_helper.php';

if (!file_exists('../buwanaconn_env.php')) {
    die('Buwana DB config not found.');
}
require_once '../buwanaconn_env.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.23';
$headerFile = __DIR__ . '/../header-2026.php';
$footerFile = __DIR__ . '/../footer-2026.php';
$page = 'feedback';
$lastModified = date("Y-m-d\\TH:i:s\\Z", filemtime(__FILE__));

// Prefer authenticated session context instead of URL parameters.
$session_buwana_id = $_SESSION['buwana_id'] ?? null;
$session_client_id = $_SESSION['client_id'] ?? null;
$requested_buwana_id = isset($_GET['buwana']) ? intval($_GET['buwana']) : null;
$requested_client_id = $_GET['app'] ?? ($_GET['client_id'] ?? null);

if ($requested_buwana_id && $session_buwana_id && $requested_buwana_id !== $session_buwana_id) {
    http_response_code(403);
    die('Mismatched session for requested user.');
}

if ($requested_client_id && $session_client_id && $requested_client_id !== $session_client_id) {
    http_response_code(403);
    die('Mismatched session for requested app.');
}

$buwana_id = $session_buwana_id;
$client_id = $session_client_id ?? $requested_client_id;

if (!$buwana_id || !$client_id) {
    http_response_code(401);
    die('Authentication required to submit feedback.');
}

$sql_connection = "SELECT id FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ?";
$stmt_connection = $buwana_conn->prepare($sql_connection);

if ($stmt_connection) {
    $stmt_connection->bind_param('is', $buwana_id, $client_id);
    $stmt_connection->execute();
    $stmt_connection->bind_result($connection_id);
    $stmt_connection->fetch();
    $stmt_connection->close();

    if (!$connection_id) {
        die('Connection not found.');
    }
    // Persist the authenticated context for the CS API layer.
    $_SESSION['buwana_id'] = $buwana_id;
    $_SESSION['client_id'] = $client_id;
} else {
    die('Error preparing statement for connection lookup: ' . $buwana_conn->error);
}

require_once '../fetch_app_info.php';

$sql_user_info = "SELECT u.full_name, u.first_name, u.email, u.language_id, u.earthling_emoji, u.country_id, u.role,
                         l.language_name_en, l.language_name_es, l.language_name_fr, l.language_name_id, l.languages_native_name,
                         c.country_name
                  FROM users_tb u
                  LEFT JOIN languages_tb l ON u.language_id = l.language_id
                  LEFT JOIN countries_tb c ON u.country_id = c.country_id
                  WHERE u.buwana_id = ?";
$stmt_user_info = $buwana_conn->prepare($sql_user_info);

if ($stmt_user_info) {
    $stmt_user_info->bind_param('i', $buwana_id);
    $stmt_user_info->execute();
    $stmt_user_info->bind_result(
        $full_name,
        $first_name,
        $email,
        $language_id,
        $earthling_emoji,
        $country_id,
        $role,
        $language_name_en,
        $language_name_es,
        $language_name_fr,
        $language_name_id,
        $languages_native_name,
        $country_name
    );
    $stmt_user_info->fetch();
    $stmt_user_info->close();
} else {
    die('Error preparing statement for fetching user info: ' . $buwana_conn->error);
}

$full_name = $full_name ?? '';
$first_name = $first_name ?? '';
$email = $email ?? '';
$earthling_emoji = $earthling_emoji ?? '';
$country_name = $country_name ?? '';
$role = $role ?? '';
$isAdminUser = stripos($role, 'admin') !== false;
$languages_native_name = $languages_native_name ?? '';
$language_name_en = $language_name_en ?? '';
$language_name_es = $language_name_es ?? '';
$language_name_fr = $language_name_fr ?? '';
$language_name_id = $language_name_id ?? '';

$preferred_language_display = '';
switch (strtolower($lang)) {
    case 'es':
        $preferred_language_display = $language_name_es;
        break;
    case 'fr':
        $preferred_language_display = $language_name_fr;
        break;
    case 'id':
        $preferred_language_display = $language_name_id;
        break;
    default:
        $preferred_language_display = $language_name_en;
        break;
}

if (!$preferred_language_display) {
    $preferred_language_display = $languages_native_name ?: $language_name_en;
}

function displayValue($value): string
{
    $stringValue = is_null($value) ? '' : (string) $value;
    $trimmed = trim($stringValue);

    return $trimmed === '' ? '—' : htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<?php require_once("../includes/feedback-inc.php"); ?>

</head>
<body>

<div class="page-panel-group">
    <div id="form-submission-box" style="height:fit-content;">
        <div class="form-container" style="padding-top:120px">
            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="../svgs/bug-report-day.svg"
                 data-dark-img="../svgs/bug-report-night.svg">
            </div>





            <div class="cs-dashboard">
                <div class="cs-dashboard__header">
                     <div style="text-align:center;width:100%;margin:auto;margin-top:1px">
                                    <div id="status-message" data-lang-id="001-support-title">Buwana Support Center</div>
                                    <div id="sub-status-message" data-lang-id="002-support-description">Chat with the with Buwana Development Team.</div>
                                </div>
                    <div class="cs-dashboard__actions cs-dashboard__actions--center" style="margin-top:-15px;">
                        <button type="button" id="cs-new-chat-btn" class="submit-button enabled">💬 New Chat</button>
                    </div>
                </div>

                <div id="cs-loading" class="cs-loading">
                    <span>Loading support chats…</span>
                </div>

                <div id="cs-app-inboxes"></div>
                      <div class="cs-dashboard__actions cs-dashboard__actions--center">
                                    <button type="button" id="cs-refresh-btn" class="submit-button" style="background-color:grey;">🔄 Refresh</button>
                                </div>
            </div>

        </div>
    </div>
</div>

</div>  <!-- close main div that was opened in the header-2026-->

<datalist id="cs-category-list"></datalist>

<?php require_once $footerFile; ?>

<?php require_once("../scripts/app_modals.php");?>

<script>
    window.csSupportConfig = {
        apiBase: '../cs_system/api',
        buwanaId: <?= intval($buwana_id); ?>,
        languageId: <?= intval($language_id ?? 0); ?>,
        clientId: '<?= htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8'); ?>',
        currentAppId: <?= intval($app_info['app_id'] ?? 0); ?>,
        currentAppName: '<?= htmlspecialchars($app_info['app_display_name'] ?? ($app_info['app_name'] ?? 'App'), ENT_QUOTES, 'UTF-8'); ?>',
        isAdmin: <?= $isAdminUser ? 'true' : 'false'; ?>,
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../scripts/jquery.dataTables.js"></script>
<script src="../js/cs_support.js?v=<?= $version; ?>"></script>

</body>
</html>
