<?php
// ── users_apps.php ──────────────────────────────────────────────────────────
// Returns the Buwana apps connected to a given buwana_id.
// Called cross-origin by GoBrik (and other client apps) to populate the
// "My Buwana Apps" drawer in the header-2026b settings panel.
//
// GET /api/users_apps.php?buwana_id=<int>
// Response: { ok: true, apps: [ { app_display_name, app_login_url,
//             app_square_icon_url, app_slogan, app_version }, ... ] }
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../buwanaconn_env.php';

header('Content-Type: application/json');

$allowed_origins = [
    'https://gobrik.com',
    'https://beta.gobrik.com',
    'https://buwana.ecobricks.org',
    'https://ecobricks.org',
    'https://earthcal.app',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$buwana_id = isset($_GET['buwana_id']) ? (int)$_GET['buwana_id'] : 0;

if (!$buwana_id) {
    echo json_encode(['ok' => false, 'apps' => []]);
    exit();
}

$sql = "SELECT a.app_display_name,
               a.app_login_url,
               a.app_square_icon_url,
               a.app_slogan,
               a.app_version
        FROM apps_tb a
        JOIN user_app_connections_tb c ON a.client_id = c.client_id
        WHERE c.buwana_id = ?
        ORDER BY c.connected_at ASC";

$apps = [];
if ($stmt = $buwana_conn->prepare($sql)) {
    $stmt->bind_param('i', $buwana_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            $apps = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}

echo json_encode(['ok' => true, 'apps' => $apps]);
exit();
?>
