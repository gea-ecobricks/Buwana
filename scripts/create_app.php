<?php
require_once '../buwanaconn_env.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$owner_id = $_SESSION['buwana_id'] ?? null;
if (!$owner_id) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

function generateClientId($name) {
    $prefix = substr(preg_replace('/[^a-zA-Z0-9]/', '', strtolower($name)), 0, 4);
    return $prefix . '_' . bin2hex(random_bytes(6));
}

$app_name = trim($_POST['app_name'] ?? '');
$client_id = generateClientId($app_name ?: 'app');
$client_secret = bin2hex(random_bytes(16));
$app_registration_dt = date('Y-m-d H:i:s');

$sql = "INSERT INTO apps_tb (
    app_name, app_registration_dt, client_id, client_secret,
    redirect_uris, app_login_url, scopes, app_domain, app_url, app_favicon,
    app_dashboard_url, app_description, app_version, app_display_name,
    contact_email, app_slogan, app_terms_txt, app_privacy_txt,
    app_emojis_array, app_logo_url, app_logo_dark_url, app_square_icon_url,
    app_wordmark_url, app_wordmark_dark_url,
    signup_1_top_img_light, signup_1_top_img_dark, signup_2_top_img_light, signup_2_top_img_dark,
    signup_3_top_img_light, signup_3_top_img_dark, signup_4_top_img_light, signup_4_top_img_dark,
    signup_5_top_img_light, signup_5_top_img_dark, signup_6_top_img_light, signup_6_top_img_dark,
    signup_7_top_img_light, signup_7_top_img_dark, login_top_img_light, login_top_img_dark,
    is_active, allow_signup, require_verification, last_used_dt, updated_dt
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, NOW(), NOW())";

$stmt = $buwana_conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $buwana_conn->error]);
    exit();
}

$params = [
    $app_name, $app_registration_dt, $client_id, $client_secret,
    $_POST['redirect_uris'] ?? '', $_POST['app_login_url'] ?? '', $_POST['scopes'] ?? '',
    $_POST['app_domain'] ?? '', $_POST['app_url'] ?? '', $_POST['app_favicon'] ?? '', $_POST['app_dashboard_url'] ?? '',
    $_POST['app_description'] ?? '', $_POST['app_version'] ?? '', $_POST['app_display_name'] ?? '',
    $_POST['contact_email'] ?? '', $_POST['app_slogan'] ?? '', $_POST['app_terms_txt'] ?? '',
    $_POST['app_privacy_txt'] ?? '', $_POST['app_emojis_array'] ?? '',
    $_POST['app_logo_url'] ?? '', $_POST['app_logo_dark_url'] ?? '', $_POST['app_square_icon_url'] ?? '',
    $_POST['app_wordmark_url'] ?? '', $_POST['app_wordmark_dark_url'] ?? '',
    $_POST['signup_1_top_img_light'] ?? '', $_POST['signup_1_top_img_dark'] ?? '',
    $_POST['signup_2_top_img_light'] ?? '', $_POST['signup_2_top_img_dark'] ?? '', $_POST['signup_3_top_img_light'] ?? '',
    $_POST['signup_3_top_img_dark'] ?? '', $_POST['signup_4_top_img_light'] ?? '', $_POST['signup_4_top_img_dark'] ?? '',
    $_POST['signup_5_top_img_light'] ?? '', $_POST['signup_5_top_img_dark'] ?? '', $_POST['signup_6_top_img_light'] ?? '',
    $_POST['signup_6_top_img_dark'] ?? '', $_POST['signup_7_top_img_light'] ?? '', $_POST['signup_7_top_img_dark'] ?? '',
    $_POST['login_top_img_light'] ?? '', $_POST['login_top_img_dark'] ?? ''
];
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $app_id = $stmt->insert_id;
    $stmt->close();
    $owner_stmt = $buwana_conn->prepare("INSERT INTO app_owners_tb (app_id, buwana_id, is_primary) VALUES (?, ?, 1)");
    if ($owner_stmt) {
        $owner_stmt->bind_param('ii', $app_id, $owner_id);
        $owner_stmt->execute();
        $owner_stmt->close();
    }
    echo json_encode(['success' => true, 'redirect' => "app-view.php?app_id=$app_id"]);
} else {
    $error = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => $error]);
}
