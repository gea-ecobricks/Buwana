<?php
require_once '../earthenAuth_helper.php';
require_once '../vendor/autoload.php';
require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

startSecureSession();

// Logging helper for authentication flow
$authLogFile = dirname(__DIR__) . '/logs/auth.log';
function auth_log($message) {
    global $authLogFile;
    if (!file_exists(dirname($authLogFile))) {
        mkdir(dirname($authLogFile), 0777, true);
    }
    error_log('[' . date('Y-m-d H:i:s') . "] PROCESS: " . $message . PHP_EOL, 3, $authLogFile);
}

auth_log('Login process started');

$credential_key = $_POST['credential_key'] ?? '';
$password = $_POST['password'] ?? '';
$lang = $_POST['lang'] ?? 'en';

$redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
$status   = isset($_POST['status']) ? filter_var($_POST['status'], FILTER_SANITIZE_SPECIAL_CHARS) : '';
$client_id = $_POST['client_id'] ?? ($_SESSION['client_id'] ?? null);
$csrf_token = $_POST['csrf_token'] ?? '';

// CSRF check
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    auth_log('CSRF token validation failed');
    header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=invalid_csrf");
    exit();
}


if (empty($credential_key) || empty($password)) {
    auth_log('Empty credential key or password');
    header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=empty_fields&key=" . urlencode($credential_key));
    exit();
}

// ------------------------------------------------------------------
// GoBrik legacy account check
// ------------------------------------------------------------------
if ($client_id === 'gbrk_f2c61a85a4cd4b8b89a7') {
    $sql_legacy = "SELECT ecobricker_id, buwana_id FROM tb_ecobrickers WHERE email_addr = ?";
    $stmt_legacy = $gobrik_conn->prepare($sql_legacy);
    if ($stmt_legacy) {
        $stmt_legacy->bind_param('s', $credential_key);
        $stmt_legacy->execute();
        $stmt_legacy->bind_result($ecobricker_id, $legacy_buwana_id);
        if ($stmt_legacy->fetch() && empty($legacy_buwana_id)) {
            auth_log('Legacy GoBrik account detected for ' . $credential_key);
            header("Location: ../$lang/activate-gobrik-legacy.php?id=$ecobricker_id");
            exit();
        }
        $stmt_legacy->close();
    }
}

// Check credentials_tb
$sql_credential = "SELECT buwana_id FROM credentials_tb WHERE credential_key = ?";
$stmt_credential = $buwana_conn->prepare($sql_credential);

if ($stmt_credential) {
    $stmt_credential->bind_param('s', $credential_key);
    $stmt_credential->execute();
    $stmt_credential->store_result();

    if ($stmt_credential->num_rows === 1) {
        $stmt_credential->bind_result($buwana_id);
        $stmt_credential->fetch();
        $stmt_credential->close();

        // Check recent failed attempts
        $sql_failed = "SELECT failed_last_tm, failed_password_count FROM credentials_tb WHERE credential_key = ?";
        $stmt_failed = $buwana_conn->prepare($sql_failed);
        if ($stmt_failed) {
            $stmt_failed->bind_param('s', $credential_key);
            $stmt_failed->execute();
            $stmt_failed->bind_result($failed_last_tm, $failed_password_count);
            $stmt_failed->fetch();
            $stmt_failed->close();

            $current_time = new DateTime();
            $last_failed_time = $failed_last_tm ? new DateTime($failed_last_tm) : null;

            if ($last_failed_time && $current_time->getTimestamp() - $last_failed_time->getTimestamp() <= 600 && $failed_password_count >= 5) {
                auth_log('Too many recent failed attempts');
                header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=too_many_attempts&key=" . urlencode($credential_key));
                exit();
            }

            if (is_null($last_failed_time) || $current_time->getTimestamp() - $last_failed_time->getTimestamp() > 600) {
                $failed_password_count = 0;
            }
        }

        // Fetch user
        $sql_user = "SELECT password_hash, first_name, email, open_id FROM users_tb WHERE buwana_id = ?";
        $stmt_user = $buwana_conn->prepare($sql_user);

        if ($stmt_user) {
            $stmt_user->bind_param('i', $buwana_id);
            $stmt_user->execute();
            $stmt_user->store_result();

            if ($stmt_user->num_rows === 1) {
                $stmt_user->bind_result($password_hash, $first_name, $email, $open_id);
                $stmt_user->fetch();

                if (password_verify($password, $password_hash)) {

                    // Successful login, update login stats
                    $buwana_conn->query("UPDATE users_tb SET last_login = NOW(), login_count = login_count + 1 WHERE buwana_id = $buwana_id");
                    $buwana_conn->query("UPDATE credentials_tb SET last_login = NOW(), times_used = times_used + 1 WHERE buwana_id = $buwana_id");

                    // Unified session user ID for OIDC
                    $_SESSION['user_id'] = $buwana_id;
                    $_SESSION['buwana_id'] = $buwana_id;

                    // Verify user has already connected to this application
                    require_once __DIR__ . '/../api/check_user_app_connection.php';
                    check_user_app_connection($buwana_conn, $buwana_id, $client_id, $lang);

                    // ------------------------------------------------------------------
                    // Generate JWT for session based auth
                    // ------------------------------------------------------------------

                    $private_key = null;
                    if ($client_id) {
                        $stmt_key = $buwana_conn->prepare("SELECT jwt_private_key FROM apps_tb WHERE client_id = ?");
                        if ($stmt_key) {
                            $stmt_key->bind_param('s', $client_id);
                            if ($stmt_key->execute()) {
                                $stmt_key->bind_result($private_key);
                                $stmt_key->fetch();
                            }
                            $stmt_key->close();
                        }
                    }

                    if ($private_key) {
                        $now  = time();
                        $exp  = $now + 3600; // 1 hour expiry
                        // ✅ Ensure OpenID is present
                        if (empty($open_id)) {
                            // If no OpenID exists for this user, you can either generate it here or throw error.
                            // For now, we error to be safe.
                            die("Internal error: open_id missing.");
                        }

                        $payload = [
                            'iss' => 'https://buwana.ecobricks.org',
                            'sub' => $open_id,           // ✅ federation-wide OpenID UUID as subject
                            'buwana_id' => $buwana_id,   // ✅ your local internal ID as a separate claim
                            'aud' => $client_id,
                            'exp' => $exp,
                            'iat' => $now,
                            'email' => $email,
                            'given_name' => $first_name
                        ];

                        try {
                            $jwt_token = JWT::encode($payload, $private_key, 'RS256', $client_id);
                            $_SESSION['jwt'] = $jwt_token;
                        } catch (Exception $e) {
                            auth_log('JWT generation failed: ' . $e->getMessage());
                        }
                    } else {
                        auth_log('Private key not found for client_id ' . $client_id);
                    }

                    // Check if part of OAuth/OpenID flow
                    if (isset($_SESSION['pending_oauth_request'])) {
                        $params = http_build_query($_SESSION['pending_oauth_request']);
                        unset($_SESSION['pending_oauth_request']);
                        header("Location: ../authorize?$params");
                        exit();
                    }

                    // Otherwise proceed as normal
                    $app_dashboard_url = "../$lang/dashboard.php"; // default fallback
                    if ($client_id) {
                        $stmt_dash = $buwana_conn->prepare("SELECT app_dashboard_url FROM apps_tb WHERE client_id = ?");
                        if ($stmt_dash) {
                            $stmt_dash->bind_param('s', $client_id);
                            if ($stmt_dash->execute()) {
                                $stmt_dash->bind_result($app_dashboard_url_db);
                                if ($stmt_dash->fetch() && !empty($app_dashboard_url_db)) {
                                    $app_dashboard_url = $app_dashboard_url_db;
                                }
                            }
                            $stmt_dash->close();
                        }
                    }

                    // Fetch allowed app domains
                    $allowed_domains = [];
                    $stmt_domains = $buwana_conn->prepare("SELECT app_domain FROM apps_tb");
                    if ($stmt_domains) {
                        if ($stmt_domains->execute()) {
                            $stmt_domains->bind_result($domain_value);
                            while ($stmt_domains->fetch()) {
                                $allowed_domains[] = strtolower(trim($domain_value));
                            }
                        }
                        $stmt_domains->close();
                    }

                    // Resolve redirect URL
                    if (!empty($redirect)) {
                        if (preg_match('/^https?:\/\//i', $redirect) || strpos($redirect, '//') === 0) {
                            $redirect_host = parse_url($redirect, PHP_URL_HOST);
                            $approved = false;
                            if ($redirect_host) {
                                foreach ($allowed_domains as $domain) {
                                    if ($domain !== '' && stripos($redirect_host, $domain) !== false) {
                                        $approved = true;
                                        break;
                                    }
                                }
                            }
                            if (!$approved) {
                                echo "<script>alert('Uh oh... looks like you\'re trying to access buwana from an un-approved domain name.  Please contact the app\'s admin to fix this in their Buwana App Management Core Data panel.'); window.location.href='../$lang/login.php?app=" . urlencode($client_id) . "';</script>";
                                exit();
                            }
                            $redirect_url = $redirect;
                        } elseif (strpos($redirect, '/') === 0) {
                            $redirect_url = $redirect;
                        } else {
                            $redirect_url = "../$lang/" . ltrim($redirect, '/');
                        }
                    } else {
                        $redirect_url = $app_dashboard_url;
                    }

                    if (!empty($status) && $status !== 'loggedout') {
                        $delimiter = (strpos($redirect_url, '?') !== false) ? '&' : '?';
                        $redirect_url .= $delimiter . 'status=' . urlencode($status);
                    }
                    header("Location: $redirect_url");
                    exit();
                } else {
                    auth_log('Invalid password');

                    // Update failed attempt counters
                    $sql_check_failed = "SELECT failed_last_tm, failed_password_count FROM credentials_tb WHERE credential_key = ?";
                    $stmt_check_failed = $buwana_conn->prepare($sql_check_failed);
                    if ($stmt_check_failed) {
                        $stmt_check_failed->bind_param('s', $credential_key);
                        $stmt_check_failed->execute();
                        $stmt_check_failed->bind_result($failed_last_tm, $failed_password_count);
                        $stmt_check_failed->fetch();
                        $stmt_check_failed->close();

                        $current_time = new DateTime();
                        $last_failed_time = $failed_last_tm ? new DateTime($failed_last_tm) : null;

                        if (is_null($last_failed_time) || $current_time->getTimestamp() - $last_failed_time->getTimestamp() > 600) {
                            $failed_password_count = 0;
                        }

                        $failed_password_count += 1;

                        $sql_update_failed = "UPDATE credentials_tb SET failed_last_tm = NOW(), failed_password_count = ? WHERE credential_key = ?";
                        $stmt_update_failed = $buwana_conn->prepare($sql_update_failed);
                        if ($stmt_update_failed) {
                            $stmt_update_failed->bind_param('is', $failed_password_count, $credential_key);
                            $stmt_update_failed->execute();
                            $stmt_update_failed->close();
                        }
                    }

                    if ($failed_password_count >= 5) {
                        header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=too_many_attempts&key=" . urlencode($credential_key));
                    } else {
                        header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=invalid_password&key=" . urlencode($credential_key));
                    }
                    exit();
                }
            }
            $stmt_user->close();
        }
    } else {
        auth_log('Credential not found');
        header("Location: ../$lang/login.php?app=" . urlencode($client_id) . "&status=invalid_credential&key=" . urlencode($credential_key));
        exit();
    }
} else {
    auth_log('Error preparing credential query');
    die('Database error.');
}

$buwana_conn->close();
?>
