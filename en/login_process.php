<?php
require_once '../earthenAuth_helper.php'; // Include the authentication helper functions

// Start a secure session with regeneration to prevent session fixation
startSecureSession();

// PART 1: Grab user credentials from the login form submission
$credential_key = $_POST['credential_key'] ?? '';
$password = $_POST['password'] ?? '';
$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$redirect = $_POST['redirect'] ?? ''; // Capture the redirect variable from POST
$status = isset($_POST['status']) ? filter_var($_POST['status'], FILTER_SANITIZE_SPECIAL_CHARS) : '';

// Sanitize the redirect value using FILTER_SANITIZE_SPECIAL_CHARS
$redirect = filter_var($redirect, FILTER_SANITIZE_SPECIAL_CHARS);

if (empty($credential_key) || empty($password)) {
    header("Location: ../$lang/login.php?status=empty_fields&key=" . urlencode($credential_key));
    exit();
}

// PART 2: GoBrik Account validation
require_once("../gobrikconn_env.php");

// Check the GoBrik database to see if the user has an unactivated account
$sql_check_email = "SELECT ecobricker_id, buwana_activated FROM tb_ecobrickers WHERE email_addr = ?";
$stmt_check_email = $gobrik_conn->prepare($sql_check_email);

if ($stmt_check_email) {
    $stmt_check_email->bind_param('s', $credential_key);
    $stmt_check_email->execute();
    $stmt_check_email->store_result();

    if ($stmt_check_email->num_rows === 1) {
        $stmt_check_email->bind_result($ecobricker_id, $buwana_activated);
        $stmt_check_email->fetch();
//If Account is unactivated send them to activate
        if ($buwana_activated == '0') {
            header("Location: https://gobrik.com/$lang/activate.php?id=$ecobricker_id");
            exit();
        }

        $stmt_check_email->close();
    } else {
        $stmt_check_email->close();
    }
} else {
    error_log('Error preparing statement for checking email: ' . $gobrik_conn->error);
    die('Database query failed.');
}

// PART 3: Check Buwana Database for specific Buwana_id to login
require_once("../buwanaconn_env.php");

// SQL query to get buwana_id from credentials_tb using credential_key
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

        // SQL query to get password_hash from users_tb using buwana_id in Buwana database
        $sql_user = "SELECT password_hash FROM users_tb WHERE buwana_id = ?";
        $stmt_user = $buwana_conn->prepare($sql_user);

        if ($stmt_user) {
            $stmt_user->bind_param('i', $buwana_id);
            $stmt_user->execute();
            $stmt_user->store_result();

            if ($stmt_user->num_rows === 1) {
                $stmt_user->bind_result($password_hash);
                $stmt_user->fetch();

                // Verify the password entered by the user
                if (password_verify($password, $password_hash)) {

                    // PART 4: If login successfull Update Buwana Account
                    $sql_update_user = "UPDATE users_tb SET last_login = NOW(), login_count = login_count + 1 WHERE buwana_id = ?";
                    $stmt_update_user = $buwana_conn->prepare($sql_update_user);

                    if ($stmt_update_user) {
                        $stmt_update_user->bind_param('i', $buwana_id);
                        $stmt_update_user->execute();
                        $stmt_update_user->close();
                    } else {
                        die('Error preparing statement for updating users_tb: ' . $buwana_conn->error);
                    }

                    $sql_update_credential = "UPDATE credentials_tb SET last_login = NOW(), times_used = times_used + 1 WHERE buwana_id = ?";
                    $stmt_update_credential = $buwana_conn->prepare($sql_update_credential);

                    if ($stmt_update_credential) {
                        $stmt_update_credential->bind_param('i', $buwana_id);
                        $stmt_update_credential->execute();
                        $stmt_update_credential->close();
                    } else {
                        die('Error preparing statement for updating credentials_tb: ' . $buwana_conn->error);
                    }

       // Set session variable to indicate the user is logged in
       $_SESSION['buwana_id'] = $buwana_id;

       $client_id = $_SESSION['client_id'] ?? null;
       $app_dashboard_url = 'dashboard.php'; // default fallback

       if ($client_id) {
           // Get app's dashboard URL
           $sql = "SELECT app_dashboard_url FROM apps_tb WHERE client_id = ?";
           $stmt = $buwana_conn->prepare($sql);
           if ($stmt) {
               $stmt->bind_param('s', $client_id);
               $stmt->execute();
               $stmt->bind_result($app_dashboard_url);
               $stmt->fetch();
               $stmt->close();
           }

           // 🔍 Check if the user is already connected to this app and grab the connection id
           $check_sql = "SELECT id FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? LIMIT 1";
           $check_stmt = $buwana_conn->prepare($check_sql);
           if ($check_stmt) {
               $check_stmt->bind_param('is', $buwana_id, $client_id);
               $check_stmt->execute();
               $check_stmt->bind_result($connection_id);
               $check_stmt->fetch();
               $check_stmt->close();

               if (!$connection_id) {
                   // 🚪 Not yet connected → send to app-connect page
                   header("Location: app-connect.php?id=$buwana_id&client_id=$client_id");
                   exit();
               } else {
                   $_SESSION['connection_id'] = $connection_id;
               }
           }
       }

       // ✅ Default redirect
       $redirect_url = !empty($redirect) ? $redirect : $app_dashboard_url;
       if ($status === 'firsttime' && empty($redirect)) {
           $additional_params = [];
           if (strpos($redirect_url, 'status=') === false) {
               $additional_params[] = 'status=firsttime';
           }
           if (strpos($redirect_url, 'id=') === false) {
               $additional_params[] = 'id=' . urlencode($buwana_id);
           }
           if (!empty($additional_params)) {
               $delimiter = (strpos($redirect_url, '?') !== false) ? '&' : '?';
               $redirect_url .= $delimiter . implode('&', $additional_params);
           }
       }
       header("Location: $redirect_url");
       exit();


                } else {
                    // PART 6: Handle failed login attempts
                    $sql_check_failed = "SELECT failed_last_tm, failed_password_count FROM credentials_tb WHERE credential_key = ?";
                    $stmt_check_failed = $buwana_conn->prepare($sql_check_failed);

                    if ($stmt_check_failed) {
                        $stmt_check_failed->bind_param('s', $credential_key);
                        $stmt_check_failed->execute();
                        $stmt_check_failed->bind_result($failed_last_tm, $failed_password_count);
                        $stmt_check_failed->fetch();
                        $stmt_check_failed->close();

                        // Check if failed_last_tm exists and if it's within the last 10 minutes
                        $current_time = new DateTime();
                        $last_failed_time = $failed_last_tm ? new DateTime($failed_last_tm) : null;

                        if (is_null($last_failed_time) || $current_time->getTimestamp() - $last_failed_time->getTimestamp() > 600) {
                            // Reset failed_password_count if no entry or if the last failure was more than 10 minutes ago
                            $failed_password_count = 0;
                        }

                        // Increment failed_password_count and update failed_last_tm
                        $failed_password_count += 1;

                        $sql_update_failed = "UPDATE credentials_tb
                                              SET failed_last_tm = NOW(),
                                                  failed_password_count = ?
                                              WHERE credential_key = ?";
                        $stmt_update_failed = $buwana_conn->prepare($sql_update_failed);

                        if ($stmt_update_failed) {
                            $stmt_update_failed->bind_param('is', $failed_password_count, $credential_key);
                            $stmt_update_failed->execute();
                            $stmt_update_failed->close();
                        } else {
                            error_log('Error preparing statement for updating failed login attempts: ' . $buwana_conn->error);
                        }
                    } else {
                        error_log('Error preparing statement for checking failed login attempts: ' . $buwana_conn->error);
                    }

                    // Redirect the user to the login page with an error status
                    header("Location: ../$lang/login.php?status=invalid_password&key=" . urlencode($credential_key));
                    exit();

                }
            } else {
                header("Location: ../$lang/login.php?status=invalid_user&key=" . urlencode($credential_key));
                exit();
            }
            $stmt_user->close();
        } else {
            die('Error preparing statement for users_tb: ' . $buwana_conn->error);
        }
    } else {
        header("Location: ../$lang/login.php?status=invalid_credential&key=" . urlencode($credential_key));
        exit();
    }
} else {
    die('Error preparing statement for credentials_tb: ' . $buwana_conn->error);
}

$buwana_conn->close();
$gobrik_conn->close();
?>
