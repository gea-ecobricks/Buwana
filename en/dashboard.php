<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../vendor/autoload.php';
require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'dashboard';
$version = '0.1';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

// Grab JWT and client_id from session
$jwt = $_SESSION['jwt'] ?? null;
$client_id = $_SESSION['client_id'] ?? ($_GET['app'] ?? ($_GET['client_id'] ?? null));

if (!$jwt || !$client_id) {
    $query = ['status' => 'loggedout', 'redirect' => $page];
    if ($client_id) $query['app'] = $client_id;
    header('Location: login.php?' . http_build_query($query));
    exit();
}

// Get the app's public key
$stmt = $buwana_conn->prepare("SELECT jwt_public_key FROM apps_tb WHERE client_id = ?");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->bind_result($public_key);
$stmt->fetch();
$stmt->close();

try {
    $decoded = JWT::decode($jwt, new Key($public_key, 'RS256'));
    $sub = $decoded->sub ?? '';
    $buwana_id = 0;
    if (preg_match('/^buwana_(\d+)$/', $sub, $m)) {
        $buwana_id = (int)$m[1];
    } else {
        $stmt = $buwana_conn->prepare("SELECT buwana_id FROM users_tb WHERE open_id = ? LIMIT 1");
        $stmt->bind_param('s', $sub);
        $stmt->execute();
        $stmt->bind_result($buwana_id);
        $stmt->fetch();
        $stmt->close();
    }
    $_SESSION['buwana_id'] = $buwana_id;
    $first_name = $decoded->given_name ?? '';
    $earthling_emoji = $decoded->{'buwana:earthlingEmoji'} ?? '';
} catch (Exception $e) {
    error_log("JWT Decode Error: " . $e->getMessage());
    $query = ['status' => 'loggedout', 'redirect' => $page];
    if ($client_id) $query['app'] = $client_id;
    header('Location: login.php?' . http_build_query($query));
    exit();
}

// Fetch apps either owned by or connected to this user
$sql = "SELECT DISTINCT a.app_id, a.client_id, a.app_display_name, a.app_description, a.app_square_icon_url,
               (SELECT COUNT(*) FROM user_app_connections_tb u WHERE u.client_id = a.client_id) AS user_count,
               (SELECT COUNT(*) FROM user_app_connections_tb u WHERE u.client_id = a.client_id AND u.connected_at >= (NOW() - INTERVAL 1 MONTH)) AS new_users_month
        FROM apps_tb a
        LEFT JOIN app_owners_tb ao ON ao.app_id = a.app_id AND ao.buwana_id = ?
        LEFT JOIN user_app_connections_tb uc ON uc.client_id = a.client_id AND uc.buwana_id = ?
        WHERE ao.buwana_id IS NOT NULL OR uc.buwana_id IS NOT NULL
        ORDER BY a.app_display_name";
$stmt = $buwana_conn->prepare($sql);
$stmt->bind_param('ii', $buwana_id, $buwana_id);
$stmt->execute();
$result = $stmt->get_result();
$apps = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Count new accounts connected in the last 24 hours for apps this user owns or is connected to
$new_account_count_for_user = 0;
$stmt = $buwana_conn->prepare("SELECT COUNT(*) FROM user_app_connections_tb u
    JOIN apps_tb a ON u.client_id = a.client_id
    LEFT JOIN app_owners_tb ao ON ao.app_id = a.app_id AND ao.buwana_id = ?
    LEFT JOIN user_app_connections_tb uc ON uc.client_id = a.client_id AND uc.buwana_id = ?
    WHERE (ao.buwana_id IS NOT NULL OR uc.buwana_id IS NOT NULL)
      AND u.connected_at >= (NOW() - INTERVAL 1 DAY)");
$stmt->bind_param('ii', $buwana_id, $buwana_id);
$stmt->execute();
$stmt->bind_result($new_account_count_for_user);
$stmt->fetch();
$stmt->close();

$user_app_count = count($apps);

// Check for unresolved admin alerts
$admin_alert_msg = '';
$stmt = $buwana_conn->prepare("SELECT COUNT(*) FROM admin_alerts WHERE addressed = 0");
$stmt->execute();
$stmt->bind_result($alert_count);
$stmt->fetch();
$stmt->close();

$admin_alert_msg = ($alert_count > 0) ? 'There are admin alerts 🔴' : 'All systems go 🟢';

// Admins also see the latest general Buwana signups (any app)
$role = '';
$stmt = $buwana_conn->prepare("SELECT role FROM users_tb WHERE buwana_id = ? LIMIT 1");
$stmt->bind_param('i', $buwana_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();
$isAdminUser = stripos($role ?? '', 'admin') !== false;

$latest_users = [];
if ($isAdminUser) {
    // Explicit column list: never send password hashes or reset tokens to the page
    $sql = "SELECT u.buwana_id, u.open_id, u.full_name, u.first_name, u.last_name, u.email,
                   u.account_status, u.role, u.gea_status, u.created_at, u.last_login, u.login_count,
                   u.signup_timetaken, u.honeypotted, u.js_enabled_catch, u.bot_score,
                   u.terms_of_service, u.earthen_newsletter_join, u.flagged, u.suspended,
                   u.deleteable, u.validated, u.language_id, u.location_full, u.earthling_emoji,
                   cn.country_name, cm.com_name AS community_name,
                   (SELECT GROUP_CONCAT(CONCAT(a.app_display_name, ' (', uc.status, ')') ORDER BY uc.connected_at SEPARATOR ', ')
                      FROM user_app_connections_tb uc
                      JOIN apps_tb a ON a.client_id = uc.client_id
                     WHERE uc.buwana_id = u.buwana_id) AS connected_apps
            FROM users_tb u
            LEFT JOIN countries_tb cn ON u.country_id = cn.country_id
            LEFT JOIN communities_tb cm ON u.community_id = cm.community_id
            ORDER BY u.created_at DESC, u.buwana_id DESC
            LIMIT 100";
    $result = $buwana_conn->query($sql);
    $latest_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Map account_status to a short pill label + colour class
function signup_status_pill(?string $status): array {
    switch ($status) {
        case 'name set only':
            return ['Step 1 only', 'pill-incomplete'];
        case 'signup-2_process run. Email unverified':
            return ['Email unverified', 'pill-pending'];
        case 'registered and subscribed, no login':
            return ['Registered', 'pill-registered'];
        case 'legacy account activated':
            return ['Legacy activated', 'pill-legacy'];
        case null:
        case '':
            return ['Unknown', 'pill-unknown'];
        default:
            return [$status, 'pill-unknown'];
    }
}

// Bot score (0–100, set at signup-2) → rating label + colour class
function bot_rating(?string $score): array {
    if ($score === null || $score === '') return ['—', 'bot-na'];
    $n = (int) $score;
    if ($n > 70) return [$n . ' High', 'bot-high'];
    if ($n >= 30) return [$n . ' Med', 'bot-med'];
    return [$n . ' Low', 'bot-low'];
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/dashboard-en.php"); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($isAdminUser): ?>
    <link rel="stylesheet" href="../styles/jquery.dataTables.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../scripts/jquery.dataTables.js"></script>
<?php endif; ?>
    <?php require_once("../includes/dashboard-inc.php"); ?>
<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div class="top-wrapper">
      <div class="login-status"><?= htmlspecialchars($earthling_emoji) ?> Logged in as <?= htmlspecialchars($first_name) ?></div>
      <div style="text-align:right;">
        <div class="page-name">App Manager Dashboard</div>
        <div class="admin-status login-status"><?= htmlspecialchars($admin_alert_msg) ?></div>
      </div>
    </div>
    <div class="chart-container dashboard-module">
      <canvas id="growthChart"></canvas>
      <div class="chart-controls">

        <select id="timeRange" style="width:auto;font-size:0.9em;color:var(--subdued-text);background:none;border:1px solid var(--subdued-text);border-radius:4px;padding:2px 4px;">

          <option value="24h">Last 24hrs</option>
          <option value="week">Last Week</option>
          <option value="month" selected>Last Month</option>
          <option value="year">Last Year</option>
        </select>
      </div>
    </div>
    <p class="welcome-msg" style="text-align: center;">Welcome back <?= htmlspecialchars($first_name) ?>!  You have <?= intval($new_account_count_for_user) ?> new users in the last 24hrs.  Manage your <?= $user_app_count ?> apps here...</p>
    <div class="app-grid">
      <?php foreach ($apps as $app): ?>
        <a href="app-view.php?app_id=<?= intval($app['app_id']) ?>" class="app-display-box" title="<?= htmlspecialchars($app['app_display_name']) ?>">
          <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon">
          <p>
            <?= intval($app['user_count']) ?> users
            <?php $change = intval($app['new_users_month']); ?>
            <span class="monthly-change-<?php echo $change >= 0 ? 'positive' : 'negative'; ?>">
              <?= $change >= 0 ? '+' : '' ?><?= $change ?>
            </span>
          </p>
        </a>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-bottom:20px;">
      <a href="app-wizard.php" class="kick-ass-submit">Create New App</a>
    </div>
<?php if ($isAdminUser): ?>
    <div class="latest-users dashboard-module">
      <h4 style="margin-top:0;">Latest Buwana Signups</h4>
      <p class="chart-caption">The 100 most recent Buwana accounts across all apps. Click a status pill for account details.</p>
      <div class="latest-users-scroll">
      <table id="latestUsersTable" class="display" style="width:100%">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Bot Rating</th>
            <th>Registered</th>
            <th>Signup Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($latest_users as $u): ?>
            <?php [$pillLabel, $pillClass] = signup_status_pill($u['account_status']); ?>
            <?php [$botLabel, $botClass] = bot_rating($u['bot_score'] === null ? null : (string) $u['bot_score']); ?>
            <tr id="user-row-<?= intval($u['buwana_id']) ?>">
              <td><?= htmlspecialchars($u['earthling_emoji'] ?? '') ?> <?= htmlspecialchars($u['full_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
              <td data-order="<?= $u['bot_score'] === null ? -1 : intval($u['bot_score']) ?>"><span class="bot-rating <?= $botClass ?>"><?= htmlspecialchars($botLabel) ?></span></td>
              <td data-order="<?= htmlspecialchars($u['created_at'] ?? '') ?>"><?= htmlspecialchars($u['created_at'] ? date('Y-m-d H:i', strtotime($u['created_at'])) : '—') ?></td>
              <td>
                <div class="status-cell">
                  <button type="button" class="status-pill <?= $pillClass ?>"
                          data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8") ?>'
                          title="<?= htmlspecialchars($u['account_status'] ?? '') ?>"><?= htmlspecialchars($pillLabel) ?></button>
                  <?php if ((int) $u['buwana_id'] !== (int) $buwana_id): ?>
                    <button type="button" class="delete-user-btn"
                            data-id="<?= intval($u['buwana_id']) ?>"
                            data-name="<?= htmlspecialchars($u['full_name'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
                            title="Delete this account" aria-label="Delete <?= htmlspecialchars($u['full_name'] ?? '') ?>">🗑️</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof updateChartTextColor === 'function') {
    updateChartTextColor();
  }

  const ctx = document.getElementById('growthChart').getContext('2d');
  let growthChart;

  function loadChart(range = 'month') {
    fetch('../analytics/get-user-growth.php?range=' + range)
      .then(r => r.json())
      .then(chartData => {
        if (growthChart) {
          growthChart.data = chartData;
          growthChart.update();
        } else {
          growthChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
              responsive: true,
              plugins: {
                legend: { position: 'bottom' }
              }
            }
          });
        }
      });
  }

  document.getElementById('timeRange').addEventListener('change', function() {
    loadChart(this.value);
  });

  loadChart();
});
</script>
<?php if ($isAdminUser): ?>
<script>
(function () {
  var CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

  function esc(v) {
    return String(v === null || v === undefined || v === '' ? '—' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function yesNo(v) {
    if (v === null || v === undefined || v === '') return '—';
    return String(v) === '1' ? 'Yes' : 'No';
  }

  function showUserDetails(u) {
    var rows = [
      ['Buwana ID', u.buwana_id],
      ['Open ID', u.open_id],
      ['Full name', u.full_name],
      ['Email', u.email],
      ['Signup status', u.account_status],
      ['Role', u.role],
      ['GEA status', u.gea_status],
      ['Registered', u.created_at],
      ['Last login', u.last_login],
      ['Login count', u.login_count],
      ['Validated', yesNo(u.validated)],
      ['Terms accepted', yesNo(u.terms_of_service)],
      ['Earthen newsletter', yesNo(u.earthen_newsletter_join)],
      ['Connected apps', u.connected_apps],
      ['Country', u.country_name],
      ['Community', u.community_name],
      ['Location', u.location_full],
      ['Language', u.language_id],
      ['Bot score', u.bot_score],
      ['Signup time taken', u.signup_timetaken !== null && u.signup_timetaken !== '' ? u.signup_timetaken + 's' : ''],
      ['Honeypot tripped', yesNo(u.honeypotted)],
      ['JS enabled at signup', yesNo(u.js_enabled_catch)],
      ['Flagged', yesNo(u.flagged)],
      ['Suspended', yesNo(u.suspended)],
      ['Deleteable', yesNo(u.deleteable)]
    ];
    var html = '<h4 style="text-align:center;">' + esc(u.earthling_emoji) + ' ' + esc(u.full_name) + '</h4>' +
               '<table class="basic-table user-detail-table">';
    rows.forEach(function (r) {
      html += '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>';
    });
    html += '</table>';
    openModal(html);
  }

  function openDeleteUserModal(btn) {
    var id = btn.dataset.id;
    var label = btn.dataset.name + (btn.dataset.email ? ' (' + btn.dataset.email + ')' : '');
    openAlertModal({
      title: 'Delete this account?',
      body:
        '<div class="delete-app-warning">' +
          '<strong>This action is permanent and cannot be undone.</strong> ' +
          'The Buwana account for <strong>' + esc(label) + '</strong> will be deleted, along with its ' +
          'GoBrik and EarthCal data, its app connections, and its Earthen subscription.' +
        '</div>',
      actions:
        '<button type="button" class="alert-modal-btn alert-modal-btn-cancel" onclick="closeAlertModal()">Cancel</button>' +
        '<button type="button" id="delete-user-confirm-btn" class="alert-modal-btn alert-modal-btn-danger">Delete User</button>',
      onOpen: function () {
        var confirmBtn = document.getElementById('delete-user-confirm-btn');
        confirmBtn.addEventListener('click', function () { confirmDeleteUser(id, confirmBtn); });
      }
    });
  }

  function showResult(title, msg) {
    openAlertModal({
      title: title,
      body: '<p>' + esc(msg) + '</p>',
      actions: '<button type="button" class="alert-modal-btn alert-modal-btn-cancel" onclick="closeAlertModal()">Close</button>'
    });
  }

  function confirmDeleteUser(id, confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting\u2026';
    fetch('../api/admin_delete_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ buwana_id: id, csrf_token: CSRF_TOKEN })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.success) {
        closeAlertModal();
        var row = document.getElementById('user-row-' + id);
        if (row) latestUsersTable.row(row).remove().draw(false);
      } else if (d.failures) {
        showResult('Deleted with errors', d.failures.join('. '));
      } else {
        showResult('Delete Failed', d.error || 'An unexpected error occurred. Please try again.');
      }
    })
    .catch(function () {
      showResult('Network Error', 'Could not connect to the server. Please check your connection and try again.');
    });
  }

  var latestUsersTable = $('#latestUsersTable').DataTable({
    order: [[3, 'desc']],
    pageLength: 25
  });

  $('#latestUsersTable tbody').on('click', '.status-pill', function () {
    showUserDetails($(this).data('user'));
  });
  $('#latestUsersTable tbody').on('click', '.delete-user-btn', function () {
    openDeleteUserModal(this);
  });
}());
</script>
<?php endif; ?>
<?php require_once("../footer-2026.php"); ?>

<?php require_once ("../scripts/app_modals.php");?>
</body>
</html>
