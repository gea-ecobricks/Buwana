<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start session only if not already started
}

require_once 'buwanaconn_env.php'; // DB connection ($buwana_conn)

$default_client_id = 'gbrk_f2c61a85a4cd4b8b89a7';

// ✅ Step 1: Determine client_id
$client_id = $_GET['client_id'] ?? $_GET['app'] ?? null;

if ($client_id) {
    $client_id = trim($client_id);
    $_SESSION['client_id'] = $client_id;
} else {
    $client_id = $_SESSION['client_id'] ?? $default_client_id;
}

// ✅ Step 2: Fetch app info from DB
$sql = "SELECT * FROM apps_tb WHERE client_id = ?";
$stmt = $buwana_conn->prepare($sql);
$app_info = [];

if ($stmt) {
    $stmt->bind_param("s", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $app_info = $result->fetch_assoc();
    } else {
        // Fallback to default app info (GoBrik)
        $client_id = $default_client_id;
        $_SESSION['client_id'] = $default_client_id;
        $stmt->close();

        $stmt = $buwana_conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $client_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $app_info = $result ? $result->fetch_assoc() : [];
        }
    }

    $stmt->close();
} else {
    error_log("Error preparing app info statement: " . $buwana_conn->error);
}

// ✅ Step 3: Apply fallback values if needed
$app_info = array_merge([
    'app_name' => 'gobrik',
    'app_display_name' => 'GoBrik',
    'app_logo_url' => 'https://buwana.ecobricks.org/app/gobrik-logo.svg',
    'app_logo_dark_url' => 'https://buwana.ecobricks.org/app/gobrik-logo-dark.svg',
    'app_wordmark_url' => 'https://buwana.ecobricks.org/app/gobrik-wordmark.svg',
    'app_wordmark_dark_url' => 'https://buwana.ecobricks.org/app/gobrik-wordmark-dark.svg',
    'signup_1_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_1_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_2_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_2_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_3_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_3_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_4_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_4_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_5_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_5_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_6_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_6_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'signup_7_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-light.svg',
    'signup_7_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-dark.svg',
    'login_top_img_light' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-top-day.svg',
    'login_top_img_dark' => 'https://buwana.ecobricks.org/app/gobrik-signup-banner-top-night.svg',
    'app_slogan' => 'Track your plastic and build with purpose.',
    'app_description' => 'Ecological platform to log your plastic and build with ecobricks.',
    'app_url' => 'https://gobrik.com',
    'app_version' => '3.1',
    'redirect_uris' => 'https://gobrik.com/en/index.html',
    'privacy_policy_url' => 'https://gobrik.com/en/privacy.html',
    'terms_url' => 'https://gobrik.com/en/terms.html'
], $app_info);

// ✅ Step 4: Set redirect_url
$redirect_uris = explode(',', $app_info['redirect_uris'] ?? '');
$_SESSION['redirect_url'] = trim($redirect_uris[0]) ?: $app_info['app_url'];

// ✅ Step 5: Update last_used_dt
$update_sql = "UPDATE apps_tb SET last_used_dt = NOW() WHERE client_id = ?";
$update_stmt = $buwana_conn->prepare($update_sql);
if ($update_stmt) {
    $update_stmt->bind_param("s", $client_id);
    $update_stmt->execute();
    $update_stmt->close();
}
?>
