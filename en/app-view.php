<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../vendor/autoload.php';
require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'app-view';
$version = '0.1';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$jwt = $_SESSION['jwt'] ?? null;
$client_id = $_SESSION['client_id'] ?? ($_GET['app'] ?? ($_GET['client_id'] ?? null));
$buwana_id = $_SESSION['buwana_id'] ?? null;

if (!$buwana_id && $jwt && $client_id) {
    $stmt = $buwana_conn->prepare("SELECT jwt_public_key FROM apps_tb WHERE client_id = ?");
    $stmt->bind_param('s', $client_id);
    $stmt->execute();
    $stmt->bind_result($public_key);
    $stmt->fetch();
    $stmt->close();

    try {
        $decoded = JWT::decode($jwt, new Key($public_key, 'RS256'));
        $sub = $decoded->sub ?? '';
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
    } catch (Exception $e) {
        $buwana_id = null;
    }
}

if (!$buwana_id) {
    $query = [
        'status'   => 'loggedout',
        'redirect' => $page,
    ];
    if (!empty($client_id)) {
        $query['app'] = $client_id;
    } elseif (!empty($_GET['client_id'])) {
        $query['app'] = $_GET['client_id'];
    } elseif (!empty($_GET['app'])) {
        $query['app'] = $_GET['app'];
    }
    if (!empty($buwana_id)) {
        $query['id'] = $buwana_id;
    } elseif (!empty($_GET['id'])) {
        $query['id'] = $_GET['id'];
    }

    header('Location: login.php?' . http_build_query($query));
    exit();
}

$app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
$buwana_id = intval($_SESSION['buwana_id']);

$first_name = '';
$earthling_emoji = '';
$stmt = $buwana_conn->prepare("SELECT first_name, earthling_emoji FROM users_tb WHERE buwana_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $earthling_emoji);
    $stmt->fetch();
    $stmt->close();
}


// Fetch app and verify the user either owns or is connected to it
$stmt = $buwana_conn->prepare("SELECT * FROM apps_tb WHERE app_id = ?");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$result = $stmt->get_result();
$app = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$app) {
    echo "<p>App not found.</p>";
    exit();
}

$client_id = $app['client_id'];

$is_owner = false;
$stmt = $buwana_conn->prepare("SELECT 1 FROM app_owners_tb WHERE app_id = ? AND buwana_id = ? LIMIT 1");
$stmt->bind_param('ii', $app_id, $buwana_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) $is_owner = true;
$stmt->close();

$is_connected = false;
$stmt = $buwana_conn->prepare("SELECT 1 FROM user_app_connections_tb WHERE client_id = ? AND buwana_id = ? LIMIT 1");
$stmt->bind_param('si', $client_id, $buwana_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) $is_connected = true;
$stmt->close();

if (!$is_owner && !$is_connected) {
    echo "<p>App not found or access denied.</p>";
    exit();
}

if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_flags'])) {
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $allow_signup = isset($_POST['allow_signup']) ? 1 : 0;
    $sql = "UPDATE apps_tb a
            JOIN app_owners_tb ao ON ao.app_id = a.app_id
            SET a.is_active=?, a.allow_signup=?
            WHERE a.app_id=? AND ao.buwana_id=?";
    $update_stmt = $buwana_conn->prepare($sql);
    if ($update_stmt) {
        $update_stmt->bind_param('iiii', $is_active, $allow_signup, $app_id, $buwana_id);
        $update_stmt->execute();
        $update_stmt->close();
        $app['is_active'] = $is_active;
        $app['allow_signup'] = $allow_signup;
    }
}

$stmt = $buwana_conn->prepare("SELECT COUNT(*) FROM user_app_connections_tb WHERE client_id = ?");
$stmt->bind_param('s', $app['client_id']);
$stmt->execute();
$stmt->bind_result($total_connections);
$stmt->fetch();
$stmt->close();

$recent_users = [];
$stmt = $buwana_conn->prepare("SELECT u.*, cn.country_name FROM users_tb u JOIN user_app_connections_tb uc ON u.buwana_id = uc.buwana_id LEFT JOIN countries_tb cn ON u.country_id = cn.country_id WHERE uc.client_id = ? AND uc.status = 'registered' ORDER BY u.created_at DESC LIMIT 100");
if ($stmt) {
    $stmt->bind_param('s', $app['client_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($recent_users as &$user_row) {
        foreach ($user_row as $field => $value) {
            if ($value === null) {
                $user_row[$field] = '';
            }
        }
    }
    unset($user_row);
    $stmt->close();
}

$current_owners = [];
if ($is_owner) {
    $stmt = $buwana_conn->prepare("SELECT u.buwana_id, u.full_name FROM app_owners_tb ao JOIN users_tb u ON ao.buwana_id = u.buwana_id WHERE ao.app_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $app_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $current_owners = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/app-view-en.php"); ?>
    <link rel="stylesheet" href="../styles/jquery.dataTables.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../scripts/jquery.dataTables.js"></script>
    <?php require_once("../includes/dashboard-inc.php"); ?>
    <style>
      .top-wrapper {
        background: var(--darker-lighter);
      }
      #owner-results {
        position: absolute;
        background: var(--table-background-1);
        border: 1px solid var(--subdued-text);
        max-height: 150px;
        overflow-y: auto;
        z-index: 20;
        width: 100%;
        top: 100%;
        left: 0;
      }
      #owner-results div {
        padding: 4px 8px;
        cursor: pointer;
      }
      #owner-results div:hover {
        background: var(--lighter);
      }
      .owner-box {
        background: var(--lighter);
        border-radius: 8px;
        padding: 4px 8px;
        margin: 2px;
        display: inline-flex;
        align-items: center;
      }
      .current-owner {
        background: var(--emblem-green);
        color: #fff;
      }
      .owner-box .remove-owner {
        cursor: pointer;
        margin-right: 6px;
      }
      .remove-current-owner {
        cursor: pointer;
        margin-right: 6px;
      }
      #add-owner {
        background: var(--table-background-heading);
      }

      #add-owner:hover:not(:disabled) {
        background: var(--button-2-1);
        cursor: pointer;
      }

      .save-owner-btn {
        background: var(--button-2-2);
        color: #fff;
      }

      .save-owner-btn:hover {
        background: var(--button-2-2-over);
        cursor: pointer;
      }
    </style>
<div id="form-submission-box" class="landing-page-form">
  <div class="form-container">
    <div class="top-wrapper">
      <div>
        <div class="login-status"><?= htmlspecialchars($earthling_emoji) ?> Logged in as <?= htmlspecialchars($first_name) ?></div>
        <div style="font-size:0.9em;color:grey;margin-bottom: auto;">
          <?php if($app['is_active']): ?>
            🟢 <?= htmlspecialchars($app['app_display_name']) ?> is active
          <?php else: ?>
            ⚪ <?= htmlspecialchars($app['app_display_name']) ?> is not active
          <?php endif; ?>
        </div>
        <div style="font-size:0.9em;color:grey;">
          <?php if($app['allow_signup']): ?>
            🟢 <?= htmlspecialchars($app['app_display_name']) ?> signups enabled
          <?php else: ?>
            ⚪ <?= htmlspecialchars($app['app_display_name']) ?> signups off
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;flex-flow:column;margin-left:auto;">
          <div style="display:flex;align-items:center;margin-left:auto;">

                <div style="text-align:right;margin-right:10px;">
                  <div class="page-name">Manage: <?= htmlspecialchars($app['app_display_name']) ?></div>
                  <div class="client-id">Client ID: <?= htmlspecialchars($app['client_id']) ?></div>
                </div>
                <img src="<?= htmlspecialchars($app['app_square_icon_url']) ?>" alt="<?= htmlspecialchars($app['app_display_name']) ?> Icon" title="<?= htmlspecialchars($app['app_display_name']) ?>" width="60" height="60">
          </div>
      </div>

    </div>
    <div class="breadcrumb" style="text-align:right;margin-left:auto;margin-right: 15px;">
                          <a href="dashboard.php">Dashboard</a> &gt;
                          Manage <?= htmlspecialchars($app['app_display_name']) ?>
                        </div>
    <div class="chart-container dashboard-module" style="margin-bottom:15px;">
      <canvas id="growthChart"></canvas>
      <div class="chart-controls">
        <select id="timeRange" style="width:auto;font-size:0.9em;color:var(--subdued-text);background:none;border:1px solid var(--subdued-text);border-radius:4px;padding:2px 4px;">
          <option value="24h">Last 24hrs</option>
          <option value="week">Last Week</option>
          <option value="month" selected>Last Month</option>
          <option value="year">Last Year</option>
        </select>
      </div>
      <p class="chart-caption">App Manager user growth. Total connections: <?= intval($total_connections) ?>.</p>
    </div>


      <table id="userTable" class="display" style="width:100%">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Country</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Emoji</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_users as $u): ?>
            <tr data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8") ?>'>
              <td><?= htmlspecialchars($u['full_name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['country_name']) ?></td>
              <td><?= htmlspecialchars($u['account_status']) ?></td>
              <td><?= htmlspecialchars($u['created_at']) ?></td>
              <td><?= htmlspecialchars($u['earthling_emoji']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

<?php if ($is_owner): ?>
      <div class="edit-app-params dashboard-module" style="margin-top:20px;">
        <h5 style="text-align:center;">Edit App Parameters</h5>
        <p>Adjust the way your app functions and displays on the Buwana platform and signup flow.</p>
        <div class="edit-button-row">
          <a href="edit-app-core.php?app_id=<?= intval($app_id) ?>" class="simple-button">Core Data</a>
          <a href="edit-app-texts.php?app_id=<?= intval($app_id) ?>" class="simple-button">App texts</a>
          <a href="edit-app-graphics.php?app_id=<?= intval($app_id) ?>" class="simple-button">Logos &amp; Icons</a>
        <a href="edit-app-signup.php?app_id=<?= intval($app_id) ?>" class="simple-button">Signup banners</a>
        </div>
      </div>

      <div class="dashboard-module" id="owner-module" style="margin-top:20px; position:relative;">
        <h5 style="text-align:center;">App Owners</h5>
        <div id="current-owners" style="margin-bottom:10px;display:flex;flex-wrap:wrap;">
          <?php foreach ($current_owners as $co): ?>
            <div class="owner-box current-owner" data-id="<?= intval($co['buwana_id']) ?>"><span class="remove-current-owner">✖</span><?= htmlspecialchars($co['full_name']) ?></div>
          <?php endforeach; ?>
        </div>
        <p>Search, select and add app managers as owners of <?= htmlspecialchars($app['app_display_name']) ?>. Careful, owners will have full master admin privileges to edit the core settings, visibility and status of the app.</p>
        <div style="display:flex; gap:10px; align-items:center; position:relative;">
          <input type="text" id="owner-search" placeholder="Type a name" style="flex:1;">
          <button id="add-owner" class="simple-button" disabled>+ Select Admin</button>
          <div id="owner-results" style="display:none;"></div>
        </div>
        <div id="selected-owners" style="margin-top:10px; display:flex; flex-wrap:wrap;"></div>
        <button id="save-owners" class="simple-button save-owner-btn" style="margin-top:10px; display:none;">✅ Make Owner</button>
      </div>

      <div class="dashboard-module" style="margin-top:20px; border:1px solid red;">
        <div class="toggle-row" style="margin-bottom:10px;">
          <span><h5>Enable <?= htmlspecialchars($app['app_display_name']) ?> Signups:</h5></span>
          <label class="toggle-switch">
            <input type="checkbox" id="allow_signup" <?= $app['allow_signup'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>
        <p >This turns off signups on your app but it is still available to users.</p>
      </div>

      <div class="dashboard-module" style="margin-top:20px; border:1px solid orange;">
        <div class="toggle-row" style="margin:10px 0;">
          <span><h5>Activate <?= htmlspecialchars($app['app_display_name']) ?>:</h5></span>
          <label class="toggle-switch">
            <input type="checkbox" id="is_active" <?= $app['is_active'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>
        <p >This turns off all logins and signups on your app</p>
      </div>
<?php endif; ?>
  </div>
</div>
</div> <!-- closes main -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof updateChartTextColor === 'function') {
    updateChartTextColor();
  }

  const ctx = document.getElementById('growthChart').getContext('2d');
  let growthChart;

  function loadChart(range = 'month') {
    fetch('../analytics/get-growth-data.php?app_id=<?= intval($app_id) ?>&range=' + range)
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
              plugins: { legend: { position: 'bottom' } }
            }
          });
        }
      });
  }

  document.getElementById('timeRange').addEventListener('change', function() {
    loadChart(this.value);
  });

  loadChart();

  var table = $('#userTable').DataTable({
    order: [[4, 'desc']]
  });
  $('#userTable_wrapper').addClass('dashboard-module');

  var searchInput = document.getElementById('owner-search');
  var addBtn = document.getElementById('add-owner');
  var resultsBox = document.getElementById('owner-results');
  var selectedBox = document.getElementById('selected-owners');
  var saveOwners = document.getElementById('save-owners');
  var selectedOwners = {};
  var currentOwners = <?php echo json_encode(array_column($current_owners,'full_name','buwana_id')); ?>;

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      var q = this.value.trim();
      addBtn.disabled = true;
      addBtn.dataset.id = '';
      if (q.length < 3) { resultsBox.style.display = 'none'; return; }
      fetch('../api/search_app_users.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(list => {
          if (!list.length) { resultsBox.style.display = 'none'; return; }
          resultsBox.innerHTML = list.map(u => '<div data-id="' + u.buwana_id + '">' + u.full_name + '</div>').join('');
          resultsBox.style.display = 'block';
        });
    });

    resultsBox.addEventListener('click', function(e) {
      var id = e.target.dataset.id;
      if (!id) return;
      searchInput.value = e.target.textContent;
      addBtn.dataset.id = id;
      addBtn.disabled = false;
      resultsBox.style.display = 'none';
    });

    addBtn.addEventListener('click', function() {
      var id = this.dataset.id;
      var name = searchInput.value.trim();
      if (!id || !name || selectedOwners[id]) return;
      var div = document.createElement('div');
      div.className = 'owner-box';
      div.dataset.id = id;
      div.innerHTML = '<span class="remove-owner">✖</span>' + name;
      selectedBox.appendChild(div);
      selectedOwners[id] = name;
      searchInput.value = '';
      this.disabled = true;
      saveOwners.style.display = 'inline-block';
    });

    selectedBox.addEventListener('click', function(e) {
      if (e.target.classList.contains('remove-owner')) {
        var div = e.target.parentElement;
        delete selectedOwners[div.dataset.id];
        div.remove();
        if (!Object.keys(selectedOwners).length) {
          saveOwners.style.display = 'none';
        }
      }
    });

    saveOwners.addEventListener('click', function() {
      var ids = Object.keys(currentOwners).concat(Object.keys(selectedOwners));
      fetch('../api/update_app_owners.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({app_id: <?= intval($app_id) ?>, owners: ids})
      }).then(r => r.json()).then(d => {
        if (d.success) {
          saveOwners.style.display = 'none';
          for (var k in selectedOwners) {
            currentOwners[k] = selectedOwners[k];
            var div = document.createElement('div');
            div.className = 'owner-box current-owner';
            div.dataset.id = k;
            div.innerHTML = '<span class="remove-current-owner">✖</span>' + selectedOwners[k];
            document.getElementById('current-owners').appendChild(div);
          }
          selectedOwners = {};
          selectedBox.innerHTML = '';
        } else {
          alert('Error saving owners');
        }
      });
    });

    document.getElementById('current-owners').addEventListener('click', function(e){
      if(e.target.classList.contains('remove-current-owner')){
        var div = e.target.parentElement;
        var id = div.dataset.id;
        if(confirm('Are you sure that want to remove this user from full administrative control of this app?')){
          delete currentOwners[id];
          var ids = Object.keys(currentOwners).concat(Object.keys(selectedOwners));
          fetch('../api/update_app_owners.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({app_id: <?= intval($app_id) ?>, owners: ids})
          }).then(r=>r.json()).then(d=>{
            if(d.success){
              div.remove();
            }else{
              alert('Error removing owner');
              currentOwners[id] = div.textContent.trim();
            }
          });
        }
      }
    });
  }

  function updateFlag(field, val) {
    fetch('../api/update_app_flag.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        app_id: <?= intval($app_id) ?>,
        field: field,
        value: val
      })
    }).then(r => r.json()).then(d => {
      if (!d.success) {
        alert('Error updating ' + field);
      }
    });
  }

  var allowElem = document.getElementById('allow_signup');
  if (allowElem) {
    allowElem.addEventListener('change', function() {
      updateFlag('allow_signup', this.checked ? 1 : 0);
    });
  }

  var activeElem = document.getElementById('is_active');
  if (activeElem) {
    activeElem.addEventListener('change', function() {
      updateFlag('is_active', this.checked ? 1 : 0);
    });
  }

  $('#userTable tbody').on('click', 'tr', function() {
    var user = $(this).data('user');
    if (!user) return;
    var html = '<table class="basic-table">';
    for (var k in user) {
      if (Object.prototype.hasOwnProperty.call(user, k)) {
        html += '<tr><th>' + k + '</th><td>' + user[k] + '</td></tr>';
      }
    }
    html += '</table>';
    openModal(html);
  });
});
</script>
<?php require_once("../footer-2025.php"); ?>
</body>
</html>
