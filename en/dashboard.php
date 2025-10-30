<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

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
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/dashboard-en.php"); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
<?php require_once("../footer-2025.php"); ?>

<?php require_once ("../scripts/app_modals.php");?>
</body>
</html>
