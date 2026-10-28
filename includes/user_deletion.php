<?php
// Shared cross-app account delete cascade.
//
// Used by processes/delete_accounts.php (a user deleting themselves) and
// api/admin_delete_user.php (a Buwana admin deleting another account from the
// App Manager dashboard). Callers are responsible for authentication, CSRF and
// authorization — this function only performs the deletes.
//
// NEVER call this from a client-app (Bearer token) API endpoint: the delete
// cascade must stay limited to Buwana-hosted, session-authenticated pages.

require_once __DIR__ . '/../scripts/earthen_subscribe_functions.php';

/**
 * Delete a Buwana user and their data in every connected client app.
 *
 * @param string $whose  Possessive used in result messages ('your' for a
 *                       self-delete, e.g. "the user's" for an admin delete).
 * @return array{success: bool, successes: string[], failures: string[]}
 */
function buwana_delete_user_cascade(mysqli $buwana_conn, ?mysqli $gobrik_conn, mysqli $earthcal_conn, int $buwana_id, string $whose = 'your'): array
{
    $successes = [];
    $failures = [];
    $overall_success = true;

    // Fetch user email from Buwana users table
    $sql_fetch_details = "SELECT email FROM users_tb WHERE buwana_id = ?";
    $stmt_fetch_details = $buwana_conn->prepare($sql_fetch_details);
    if (!$stmt_fetch_details) {
        throw new Exception('Error preparing statement for fetching details: ' . $buwana_conn->error);
    }
    $email_addr = null;
    $stmt_fetch_details->bind_param('i', $buwana_id);
    $stmt_fetch_details->execute();
    $stmt_fetch_details->bind_result($email_addr);
    $stmt_fetch_details->fetch();
    $stmt_fetch_details->close();

    // Begin transaction on Buwana DB
    $buwana_conn->begin_transaction();

    // Fetch connected apps for this user
    $sql_fetch_apps = "SELECT client_id FROM user_app_connections_tb WHERE buwana_id = ?";
    $stmt_fetch_apps = $buwana_conn->prepare($sql_fetch_apps);
    if (!$stmt_fetch_apps) {
        throw new Exception('Error preparing statement for fetching connected apps: ' . $buwana_conn->error);
    }
    $stmt_fetch_apps->bind_param('i', $buwana_id);
    $stmt_fetch_apps->execute();
    $result_apps = $stmt_fetch_apps->get_result();
    $client_ids = [];
    while ($row = $result_apps->fetch_assoc()) {
        $client_ids[] = $row['client_id'];
    }
    $stmt_fetch_apps->close();

    // Loop through connected apps and delete user from each
    foreach ($client_ids as $client_id) {
        $app_name = '';
        $stmt_app = $buwana_conn->prepare("SELECT app_name FROM apps_tb WHERE client_id = ?");
        if (!$stmt_app) {
            throw new Exception('Error preparing statement for fetching app name: ' . $buwana_conn->error);
        }
        $stmt_app->bind_param('s', $client_id);
        $stmt_app->execute();
        $stmt_app->bind_result($app_name);
        $stmt_app->fetch();
        $stmt_app->close();

        switch (strtolower((string) $app_name)) {
            case 'gobrik':
                try {
                    if (!$gobrik_conn instanceof mysqli) {
                        throw new Exception('GoBrik DB connection is not available.');
                    }
                    $gobrik_conn->begin_transaction();

                    // Find ecobricker_id via buwana_id
                    $ecobricker_id = null;
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
                    $successes[] = "Deleted {$whose} Gobrik Account";
                } catch (Exception $e) {
                    if ($gobrik_conn instanceof mysqli && $gobrik_conn->in_transaction) {
                        $gobrik_conn->rollback();
                    }
                    $failures[] = "Failed to delete {$whose} Gobrik Account";
                    $overall_success = false;
                }
                break;

            case 'earthcal':
                try {
                    $earthcal_conn->begin_transaction();

                    $tables = [
                        'datecycles_tb',
                        'cal_subscriptions_tb',
                        'calendars_tb',
                        'users_tb'
                    ];

                    foreach ($tables as $table) {
                        $sql = "DELETE FROM {$table} WHERE buwana_id = ?";
                        if ($stmt = $earthcal_conn->prepare($sql)) {
                            $stmt->bind_param('i', $buwana_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }

                    $earthcal_conn->commit();
                    $successes[] = "Deleted {$whose} Earthcal Account";
                } catch (Exception $e) {
                    if ($earthcal_conn->in_transaction) {
                        $earthcal_conn->rollback();
                    }
                    $failures[] = "Failed to delete {$whose} Earthcal Account";
                    $overall_success = false;
                }
                break;

            default:
                // Unsupported apps are ignored for now
                break;
        }
    }

    try {
        // Delete user's connections
        $stmt_delete_connections = $buwana_conn->prepare("DELETE FROM user_app_connections_tb WHERE buwana_id = ?");
        if ($stmt_delete_connections) {
            $stmt_delete_connections->bind_param('i', $buwana_id);
            $stmt_delete_connections->execute();
            $stmt_delete_connections->close();
        }

        // Delete user from users_tb
        $stmt_delete_user = $buwana_conn->prepare("DELETE FROM users_tb WHERE buwana_id = ?");
        if (!$stmt_delete_user) {
            throw new Exception('Error preparing statement for deleting user: ' . $buwana_conn->error);
        }
        $stmt_delete_user->bind_param('i', $buwana_id);
        $stmt_delete_user->execute();
        $stmt_delete_user->close();

        // Delete credentials
        $stmt_delete_credentials = $buwana_conn->prepare("DELETE FROM credentials_tb WHERE buwana_id = ?");
        if (!$stmt_delete_credentials) {
            throw new Exception('Error preparing statement for deleting credentials: ' . $buwana_conn->error);
        }
        $stmt_delete_credentials->bind_param('i', $buwana_id);
        $stmt_delete_credentials->execute();
        $stmt_delete_credentials->close();

        $buwana_conn->commit();
        $successes[] = "Deleted {$whose} core Buwana Account";
    } catch (Exception $e) {
        if ($buwana_conn->in_transaction) {
            $buwana_conn->rollback();
        }
        $failures[] = "Failed to delete {$whose} core Buwana Account";
        $overall_success = false;
    }

    // Call Earthen unsubscribe
    if (!empty($email_addr)) {
        try {
            earthenUnsubscribe($email_addr);
            $successes[] = $whose === 'your' ? 'Unsubscribed you from Earthen' : 'Unsubscribed the user from Earthen';
        } catch (Exception $e) {
            $failures[] = $whose === 'your' ? 'Failed to unsubscribe you from Earthen' : 'Failed to unsubscribe the user from Earthen';
            $overall_success = false;
        }
    }

    return [
        'success' => $overall_success,
        'successes' => $successes,
        'failures' => $failures,
    ];
}
