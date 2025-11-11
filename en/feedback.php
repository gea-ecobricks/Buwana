<?php
require_once '../earthenAuth_helper.php';

if (!file_exists('../buwanaconn_env.php')) {
    die('Buwana DB config not found.');
}
require_once '../buwanaconn_env.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$version = '0.22';
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





            <div class="cs-dashboard">
                <div class="cs-dashboard__header">
                     <div style="text-align:center;width:100%;margin:auto;margin-top:1px">
                                    <div id="status-message" data-lang-id="001-support-title">Buwana Support Center</div>
                                    <div id="sub-status-message" data-lang-id="002-support-description">Chat with the with Buwana Development Team.</div>
                                </div>
                    <div class="cs-dashboard__actions" style="margin:auto;margin-top:5px;">
                        <button type="button" id="cs-new-chat-btn" class="submit-button enabled">💬 New Chat</button>
                    </div>
                </div>

                <div id="cs-loading" class="cs-loading">
                    <span>Loading support chats…</span>
                </div>

                <div id="cs-app-inboxes"></div>

                <section id="cs-admin-section" class="hidden">
                    <h3 class="cs-inbox__title">Support admin inbox</h3>
                    <div id="cs-admin-personal"></div>
                    <div id="cs-admin-global"></div>
                </section>
                      <div class="cs-dashboard__actions" style="margin:auto;">
                                    <button type="button" id="cs-refresh-btn" class="submit-button" style="background-color:grey;">🔄 Refresh</button>
                                </div>
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
                                <dt><strong>User Role:</strong></dt>
                                <dd><?= displayValue($role); ?></dd>
                            </div>
                            <div class="info-row">
                                <dt data-lang-id="010-country"><strong>Country:</strong></dt>
                                <dd><?= displayValue($country_name); ?></dd>
                            </div>
                        </dl>
                    </div>
        </div>
    </div>
</div>

<div id="cs-chat-modal" class="cs-modal">
    <div class="cs-modal__dialog">
        <div class="cs-modal__header">
            <div>
                <h2 id="cs-chat-modal-title" class="cs-dashboard__title" style="font-size:1.5rem;margin:0;"></h2>
                <div class="cs-dashboard__subtitle" id="cs-chat-modal-subtitle"></div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" id="cs-chat-modal-upvote" class="cs-button cs-button--secondary">Upvote</button>
                <button type="button" class="cs-modal__close" data-close>&times;</button>
            </div>
        </div>
        <div class="cs-modal__body" style="position:relative;">
            <div id="cs-chat-loading" class="cs-loading">
                <span>Loading chat…</span>
            </div>
            <form id="cs-chat-meta-form" class="cs-form">
                <div class="cs-form__row">
                    <div class="cs-form__field">
                        <label for="cs-chat-meta-priority">Priority</label>
                        <select id="cs-chat-meta-priority" name="priority"></select>
                    </div>
                    <div class="cs-form__field">
                        <label for="cs-chat-meta-status">Status</label>
                        <select id="cs-chat-meta-status" name="status"></select>
                    </div>
                    <div class="cs-form__field">
                        <label for="cs-chat-meta-category">Category</label>
                        <input type="text" id="cs-chat-meta-category" name="category" list="cs-category-list" placeholder="Select or type a category">
                    </div>
                    <div class="cs-form__field">
                        <label for="cs-chat-meta-assigned">Assigned to</label>
                        <select id="cs-chat-meta-assigned" name="assigned_to"></select>
                    </div>
                </div>
                <div class="cs-form__field">
                    <label>Tags</label>
                    <div id="cs-chat-meta-tags" class="cs-tag-list"></div>
                    <input type="text" id="cs-chat-meta-custom-tags" placeholder="Add new tags separated by commas">
                </div>
                <button id="cs-chat-meta-save" type="submit" class="cs-button cs-button--secondary" style="align-self:flex-start;">Save updates</button>
            </form>

            <div id="cs-chat-thread" class="cs-chat-thread"></div>

            <form id="cs-message-form" class="cs-message-input">
                <label for="cs-message-body" style="font-weight:600;">Reply</label>
                <textarea id="cs-message-body" name="body" placeholder="Type your response"></textarea>
                <div class="cs-message-input__actions">
                    <div>
                        <input type="file" id="cs-message-attachments" accept="image/*" multiple>
                        <div id="cs-message-attachment-preview" class="cs-attachment-preview"></div>
                    </div>
                    <button type="submit" class="cs-button">Send reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="cs-new-chat-modal" class="cs-modal">
    <div class="cs-modal__dialog" style="max-width:720px;">
        <div class="cs-modal__header">
            <h2 style="margin:0;">Start a new support chat</h2>
            <button type="button" class="cs-modal__close" data-close>&times;</button>
        </div>
        <form id="cs-new-chat-form" class="cs-form">
            <div class="cs-modal__body">
                <div class="cs-form__row">
                    <div class="cs-form__field">
                        <label for="cs-new-chat-title">Title</label>
                        <input type="text" id="cs-new-chat-title" name="title" required>
                    </div>
                    <div class="cs-form__field">
                        <label for="cs-new-chat-app">App</label>
                        <select id="cs-new-chat-app" name="app_id" required></select>
                    </div>
                </div>
                <div class="cs-form__row">
                    <div class="cs-form__field">
                        <label for="cs-new-chat-priority">Priority</label>
                        <select id="cs-new-chat-priority" name="priority"></select>
                    </div>
                    <div class="cs-form__field">
                        <label for="cs-new-chat-category">Category</label>
                        <input type="text" id="cs-new-chat-category" name="category" list="cs-category-list">
                    </div>
                </div>
                <div class="cs-form__field">
                    <label for="cs-new-chat-description">Describe your issue</label>
                    <textarea id="cs-new-chat-description" name="description" required></textarea>
                </div>
                <div class="cs-form__field">
                    <label>Tags</label>
                    <div id="cs-new-chat-tags" class="cs-tag-list"></div>
                    <input type="text" id="cs-new-chat-custom-tags" placeholder="Add new tags separated by commas">
                </div>
                <div class="cs-form__field">
                    <label for="cs-new-chat-attachments">Attach images</label>
                    <input type="file" id="cs-new-chat-attachments" accept="image/*" multiple>
                    <div id="cs-new-chat-attachment-preview" class="cs-attachment-preview"></div>
                </div>
            </div>
            <div class="cs-modal__footer">
                <button type="button" class="cs-button cs-button--secondary" data-close>Cancel</button>
                <button type="submit" class="cs-button">Create chat</button>
            </div>
        </form>
    </div>
</div>
</div>  <!-- close main div that was opened in the header-2025-->

<datalist id="cs-category-list"></datalist>

<?php require_once("../footer-2025.php"); ?>

<?php require_once("../scripts/app_modals.php");?>

<script>
    window.csSupportConfig = {
        apiBase: '../cs_system/api',
        buwanaId: <?= intval($buwana_id); ?>,
        languageId: <?= intval($language_id ?? 0); ?>,
        clientId: '<?= htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8'); ?>',
        currentAppId: <?= intval($app_info['app_id'] ?? 0); ?>,
        currentAppName: '<?= htmlspecialchars($app_info['app_display_name'] ?? ($app_info['app_name'] ?? 'App'), ENT_QUOTES, 'UTF-8'); ?>',
        isAdmin: <?= (strcasecmp($role ?? '', 'admin') === 0) ? 'true' : 'false'; ?>,
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../scripts/jquery.dataTables.js"></script>
<script src="../js/cs_support.js?v=<?= $version; ?>"></script>

</body>
</html>
