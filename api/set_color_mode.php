<?php
/**
 * POST /api/set_color_mode.php — persist the logged-in user's canonical
 * dark/light UI preference (the source of truth). See docs/color-mode-policy.md
 *
 * Auth: Buwana SESSION ($_SESSION['buwana_id']) + CSRF token. This is the
 * Buwana-hosted surface (a user toggling mode while on a Buwana page); client
 * apps update the preference instead via the ?mode= transport on /authorize.
 *
 * Body (JSON or form): { mode: "light" | "dark", csrf_token: "..." }
 */

require_once __DIR__ . '/../includes/api_response.php';

init_json_api();

require_once __DIR__ . '/../buwanaconn_env.php';            // $buwana_conn
require_once __DIR__ . '/../includes/security-headers.php';

send_security_headers(true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('method_not_allowed', 405);
}

// Identity comes from the session, never from the request body.
$buwana_id = $_SESSION['buwana_id'] ?? null;
if (!$buwana_id) {
    api_error('not_authenticated', 401);
}

$input = parse_request_body();

// CSRF: state-changing handler — require a matching session token.
$csrf = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
    api_error('invalid_csrf', 403);
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
