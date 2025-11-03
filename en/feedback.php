<?php
require_once '../earthenAuth_helper.php';

if (!file_exists('../buwanaconn_env.php')) {
    die('Buwana DB config not found.');
}
require_once '../buwanaconn_env.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.1';
$page = 'feedback';
$lastModified = date("Y-m-d\\TH:i:s\\Z", filemtime(__FILE__));

$buwana_id = isset($_GET['buwana']) ? intval($_GET['buwana']) : null;
$client_id = $_GET['app'] ?? ($_GET['client_id'] ?? null);

if (!$buwana_id || !$client_id) {
    die('Missing buwana ID or client ID.');
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
} else {
    die('Error preparing statement for connection lookup: ' . $buwana_conn->error);
}

require_once '../fetch_app_info.php';

$sql_user_info = "SELECT u.full_name, u.first_name, u.email, u.language_id, u.earthling_emoji, u.country_id,
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

<div class="page-panel-group">
    <div id="form-submission-box" style="height:fit-content;">
        <div class="form-container" style="padding-top:120px">
            <div id="top-page-image"
                 class="top-page-image"
                 data-light-img="../svgs/bug-report-day.svg"
                 data-dark-img="../svgs/bug-report-night.svg">
            </div>

            <div style="text-align:center;width:100%;margin:auto;">
                <div id="status-message" data-lang-id="001-support-title">👥 Your Support Conversations</div>
                <div id="sub-status-message" data-lang-id="002-support-description">Contact and converse the with Buwana Development Team.</div>
            </div>

            <div class="support-details">
                <h3 data-lang-id="003-user-info-heading">Account details for this conversation</h3>
                <dl>
                    <div class="info-row">
                        <dt data-lang-id="004-buwana-id"><strong>Buwana ID:</strong></dt>
                        <dd><?= displayValue($buwana_id); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="005-full-name"><strong>Full Name:</strong></dt>
                        <dd><?= displayValue($full_name); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="006-first-name"><strong>First Name:</strong></dt>
                        <dd><?= displayValue($first_name); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="007-email"><strong>Email:</strong></dt>
                        <dd><?= displayValue($email); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="008-language"><strong>Preferred Language:</strong></dt>
                        <dd><?= displayValue($preferred_language_display); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="009-emoji"><strong>User Emoji:</strong></dt>
                        <dd><?= displayValue($earthling_emoji); ?></dd>
                    </div>
                    <div class="info-row">
                        <dt data-lang-id="010-country"><strong>Country:</strong></dt>
                        <dd><?= displayValue($country_name); ?></dd>
                    </div>
                </dl>
            </div>

            <div class="support-placeholder" data-lang-id="011-under-construction">
                🚧 Support functionality is under construction! Come back next week.
            </div>
        </div>
    </div>
</div>

<?php require_once("../footer-2025.php"); ?>

<?php require_once("../scripts/app_modals.php");?>

</body>
</html>
