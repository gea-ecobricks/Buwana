<?php
require_once __DIR__ . '/../earthenAuth_helper.php';

if (!file_exists(__DIR__ . '/../buwanaconn_env.php')) {
    die('Buwana DB config not found.');
}
require_once __DIR__ . '/../buwanaconn_env.php';

$langInput = $_GET['lang'] ?? 'en';
$lang = strtolower(preg_replace('/[^a-z]/i', '', $langInput) ?: 'en');
$version = '0.33';
$page = 'cs-chats';
$lastModified = date("Y-m-d\\TH:i:s\\Z", filemtime(__FILE__));
$headerFile = __DIR__ . '/../header-2026.php';
$footerFile = __DIR__ . '/../footer-2026.php';

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
    die('Authentication required to access chat dashboard.');
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

    $_SESSION['buwana_id'] = $buwana_id;
    $_SESSION['client_id'] = $client_id;
} else {
    die('Error preparing statement for connection lookup: ' . $buwana_conn->error);
}

require_once __DIR__ . '/../fetch_app_info.php';

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

$role = $role ?? '';
$isAdminUser = stripos($role, 'admin') !== false;

if (!$isAdminUser) {
    http_response_code(403);
    die('Access restricted to admins.');
}
?>

<?php require_once __DIR__ . '/../includes/feedback-inc.php'; ?>
<script>
    document.body.classList.add('page-cs-chats');
</script>

<div class="page-panel-group">


            <div class="cs-dashboard">
                <div class="page-panels page-panels--split">
                    <section class="page-panel page-panel--welcome">
                        <div class="cs-dashboard__header">
                            <div class="cs-dashboard__intro">
                                <div id="status-message">Admin Chat Support</div>
                                <p id="sub-status-message">View and manage support requests from all Buwana users across all Buwana apps</p>
                            </div>
                            <div id="cs-active-app-icons" class="cs-dashboard__icons" aria-live="polite" aria-label="Apps with open chats"></div>
                        </div>
                    </section>

                    <div class="page-panel-stack">
                        <div id="cs-loading" class="cs-loading">
                            <span>Loading support chats…</span>
                        </div>

                        <section id="cs-admin-global-section" class="cs-panel hidden page-panel page-panel--inboxes cs-admin-panel" data-cs-admin-panel="global">
                            <div class="cs-panel__body">
                                <div id="cs-admin-global" class="cs-panel-block"></div>
                            </div>
                            <div class="cs-panel__actions page-panel__actions">
                                <button type="button" class="cs-button cs-button--secondary cs-refresh-btn">🔄 Refresh</button>
                            </div>
                        </section>

                        <section id="cs-admin-personal-section" class="cs-panel hidden page-panel page-panel--inboxes cs-admin-panel" data-cs-admin-panel="personal">
                            <div class="cs-panel__body">
                                <div id="cs-admin-personal" class="cs-panel-block"></div>
                            </div>
                            <div class="cs-panel__actions page-panel__actions">
                                <button type="button" class="cs-button cs-button--secondary cs-refresh-btn">🔄 Refresh</button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

</div>

</div>  <!-- close main div that was opened in the header-2026-->

<datalist id="cs-category-list"></datalist>

<?php require_once $footerFile; ?>

<?php require_once __DIR__ . '/../scripts/app_modals.php';?>

<script>
    window.csSupportConfig = {
        apiBase: '../cs_system/api',
        buwanaId: <?= intval($buwana_id); ?>,
        languageId: <?= intval($language_id ?? 0); ?>,
        clientId: '<?= htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8'); ?>',
        currentAppId: <?= intval($app_info['app_id'] ?? 0); ?>,
        currentAppName: '<?= htmlspecialchars($app_info['app_display_name'] ?? ($app_info['app_name'] ?? 'App'), ENT_QUOTES, 'UTF-8'); ?>',
        isAdmin: true,
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../scripts/jquery.dataTables.js"></script>
<script src="../js/cs_support.js?v=<?= $version; ?>"></script>

</body>
</html>
