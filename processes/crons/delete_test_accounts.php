<?php
ob_start();

// Daily maintenance cron that removes test accounts from the Buwana platform and
// any connected applications, then unsubscribes the user from Earthen.
// Steps:
// 1. Find test credentials in Buwana.
// 2. Delete user records from connected apps (Gobrik, Earthcal).
// 3. Delete the user's Buwana account and credentials.
// 4. Unsubscribe the account's email from Earthen.

require_once '../../earthenAuth_helper.php';
require_once '../../gobrikconn_env.php';
require_once '../../buwanaconn_env.php';
require_once '../../calconn_env.php';
require_once '../../scripts/earthen_subscribe_functions.php';

$log_file = __DIR__ . '/delete_test_accounts.log';
$log_messages = [];

try {
    // Pull a small batch of test accounts to process (safety limited to 10 for now).
    $sql = "SELECT c.buwana_id, u.email FROM credentials_tb c JOIN users_tb u ON c.buwana_id = u.buwana_id WHERE c.credential_key LIKE '%@test.com' LIMIT 10"; // Temporary limit to 10 accounts
    $result = $buwana_conn->query($sql);
    if (!$result) {
        throw new Exception('Error fetching test accounts: ' . $buwana_conn->error);
    }

    if ($result->num_rows === 0) {
        $log_messages[] = '[' . date('Y-m-d H:i:s') . "] No test accounts found.";
    } else {
        // Process each test account one at a time so failures are isolated.
        while ($row = $result->fetch_assoc()) {
            $buwana_id = (int) $row['buwana_id'];
            $email_addr = $row['email'];
            $successes = [];
            $failures = [];
            $status = 'success';

            try {
                $buwana_conn->begin_transaction();

                // Fetch connected apps linked to this Buwana account so we can
                // clean up downstream data before removing the core account.
                $sql_fetch_apps = "SELECT client_id FROM user_app_connections_tb WHERE buwana_id = ?";
                $stmt_fetch_apps = $buwana_conn->prepare($sql_fetch_apps);
                if (!$stmt_fetch_apps) {
                    throw new Exception('Error preparing statement for fetching connected apps: ' . $buwana_conn->error);
                }
                $stmt_fetch_apps->bind_param('i', $buwana_id);
                $stmt_fetch_apps->execute();
                $result_apps = $stmt_fetch_apps->get_result();
                $client_ids = [];
                while ($app_row = $result_apps->fetch_assoc()) {
                    $client_ids[] = $app_row['client_id'];
                }
                $stmt_fetch_apps->close();

                // Loop through connected apps and delete user from each.
                foreach ($client_ids as $client_id) {
                    $stmt_app = $buwana_conn->prepare("SELECT app_name FROM apps_tb WHERE client_id = ?");
                    if (!$stmt_app) {
                        throw new Exception('Error preparing statement for fetching app name: ' . $buwana_conn->error);
                    }
                    $stmt_app->bind_param('s', $client_id);
                    $stmt_app->execute();
                    $stmt_app->bind_result($app_name);
                    $stmt_app->fetch();
                    $stmt_app->close();

                    switch (strtolower($app_name)) {
                        case 'gobrik':
                            try {
                                $gobrik_conn->begin_transaction();

                                // Remove the ecobricker profile tied to this Buwana user.
                                $stmt_ecobricker = $gobrik_conn->prepare("SELECT ecobricker_id FROM tb_ecobrickers WHERE buwana_id = ?");
                                if ($stmt_ecobricker) {
                                    $stmt_ecobricker->bind_param('i', $buwana_id);
                                    $stmt_ecobricker->execute();
                                    $stmt_ecobricker->bind_result($ecobricker_id);
                                    $stmt_ecobricker->fetch();
                                    $stmt_ecobricker->close();

                                    if (!empty($ecobricker_id)) {
                                        $stmt_delete_ecobricker = $gobrik_conn->prepare("DELETE FROM tb_ecobrickers WHERE ecobricker_id = ?");
                                        if (!$stmt_delete_ecobricker) {
                                            throw new Exception('Error preparing statement for deleting ecobricker: ' . $gobrik_conn->error);
                                        }
                                        $stmt_delete_ecobricker->bind_param('i', $ecobricker_id);
                                        $stmt_delete_ecobricker->execute();
                                        $stmt_delete_ecobricker->close();
                                    }
                                }

                                $gobrik_conn->commit();
                                $successes[] = 'Deleted Gobrik Account';
                            } catch (Exception $e) {
                                if ($gobrik_conn->in_transaction) {
                                    $gobrik_conn->rollback();
                                }
                                $failures[] = 'Failed to delete Gobrik Account';
                                $status = 'partial';
                            }
                            break;

                        case 'earthcal':
                            try {
                                $cal_conn->begin_transaction();

                                // Remove data across Earthcal tables keyed by buwana_id.
                                $tables = [
                                    'datecycles_tb',
                                    'cal_subscriptions_tb',
                                    'calendars_tb',
                                    'users_tb'
                                ];

                                foreach ($tables as $table) {
                                    $sql = "DELETE FROM {$table} WHERE buwana_id = ?";
                                    if ($stmt = $cal_conn->prepare($sql)) {
                                        $stmt->bind_param('i', $buwana_id);
                                        $stmt->execute();
                                        $stmt->close();
                                    }
                                }

                                $cal_conn->commit();
                                $successes[] = 'Deleted Earthcal Account';
                            } catch (Exception $e) {
                                if ($cal_conn->in_transaction) {
                                    $cal_conn->rollback();
                                }
                                $failures[] = 'Failed to delete Earthcal Account';
                                $status = 'partial';
                            }
                            break;

                        default:
                            // Unsupported apps are ignored; continue cleaning the rest.
                            break;
                    }
                }

                // Delete user's app connection references in Buwana.
                $stmt_delete_connections = $buwana_conn->prepare("DELETE FROM user_app_connections_tb WHERE buwana_id = ?");
                if ($stmt_delete_connections) {
                    $stmt_delete_connections->bind_param('i', $buwana_id);
                    $stmt_delete_connections->execute();
                    $stmt_delete_connections->close();
                }

                // Delete core Buwana user record.
                $stmt_delete_user = $buwana_conn->prepare("DELETE FROM users_tb WHERE buwana_id = ?");
                if (!$stmt_delete_user) {
                    throw new Exception('Error preparing statement for deleting user: ' . $buwana_conn->error);
                }
                $stmt_delete_user->bind_param('i', $buwana_id);
                $stmt_delete_user->execute();
                $stmt_delete_user->close();

                // Delete stored credentials for the user.
                $stmt_delete_credentials = $buwana_conn->prepare("DELETE FROM credentials_tb WHERE buwana_id = ?");
                if (!$stmt_delete_credentials) {
                    throw new Exception('Error preparing statement for deleting credentials: ' . $buwana_conn->error);
                }
                $stmt_delete_credentials->bind_param('i', $buwana_id);
                $stmt_delete_credentials->execute();
                $stmt_delete_credentials->close();

                $buwana_conn->commit();
                $successes[] = 'Deleted Buwana Account';

                // Unsubscribe the user's email from Earthen mailing lists.
                if (!empty($email_addr)) {
                    try {
                        earthenUnsubscribe($email_addr);
                        $successes[] = 'Unsubscribed from Earthen';
                    } catch (Exception $e) {
                        $failures[] = 'Failed to unsubscribe from Earthen';
                        $status = 'partial';
                    }
                }

            } catch (Exception $e) {
                if ($buwana_conn->in_transaction) {
                    $buwana_conn->rollback();
                }
                if (isset($gobrik_conn) && $gobrik_conn->in_transaction) {
                    $gobrik_conn->rollback();
                }
                if (isset($cal_conn) && $cal_conn->in_transaction) {
                    $cal_conn->rollback();
                }
                $failures[] = 'Error: ' . $e->getMessage();
                $status = 'error';
            }

            // Record per-account outcomes for the cron log.
            $log_messages[] = '[' . date('Y-m-d H:i:s') . "] buwana_id {$buwana_id} ({$email_addr}) - {$status}; Successes: " . implode('; ', $successes) . "; Failures: " . implode('; ', $failures);
        }
    }
} catch (Exception $e) {
    $log_messages[] = '[' . date('Y-m-d H:i:s') . "] Cron error: " . $e->getMessage();
}

// Persist log entries for the current run.
file_put_contents($log_file, implode(PHP_EOL, $log_messages) . PHP_EOL, FILE_APPEND);

ob_end_clean();
echo json_encode(['status' => 'completed']);
exit();
?>
