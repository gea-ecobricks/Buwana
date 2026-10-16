<?php
/**
 * POST /api/set_color_mode.php — persist the logged-in user's canonical
 * dark/light UI preference (the source of truth). See docs/color-mode-policy.md
 *
 * Auth, either:
 *  - Bearer access token + scope buwana:profile.write + registered connection:
 *    a client app pushing a toggle made while the user is on that app.
 *  - Buwana SESSION ($_SESSION['buwana_id']) + CSRF token: a user toggling
 *    mode on a Buwana page (footer-2026.php).
 * Clients without profile.write still update the preference via the ?mode=
 * transport on the next /authorize.
 *
 * Body (JSON or form): { mode: "light" | "dark", csrf_token: "..." (session only) }
 */

require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/cors.php';

init_json_api();
// Browser-based clients (EarthCal) call the Bearer path cross-origin.
send_api_cors_headers('POST, OPTIONS');

require_once __DIR__ . '/../buwanaconn_env.php';            // $buwana_conn
require_once __DIR__ . '/../includes/security-headers.php';
require_once __DIR__ . '/../includes/api_auth.php';

send_security_headers(true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('method_not_allowed', 405);
}

$input = parse_request_body();

// Identity comes from the token or the session, never from the request body.
if (preg_match('/^Bearer\s/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    $auth = authenticate_api_request($buwana_conn, 'buwana:profile.write');
    $buwana_id = $auth['buwana_id'];
} else {
    $buwana_id = $_SESSION['buwana_id'] ?? null;
    if (!$buwana_id) {
        api_error('not_authenticated', 401);
    }

    // CSRF: state-changing handler — require a matching session token.
    $csrf = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
        api_error('invalid_csrf', 403);
    }
}

$mode = $input['mode'] ?? null;
if (!in_array($mode, ['light', 'dark'], true)) {
    api_error('invalid_mode', 422);
}

$stmt = $buwana_conn->prepare("UPDATE users_tb SET color_mode = ? WHERE buwana_id = ?");
$stmt->bind_param("si", $mode, $buwana_id);
$stmt->execute();
$stmt->close();

api_json(['status' => 'succeeded', 'color_mode' => $mode]);
