<?php
ob_start(); // Start output buffering to prevent unexpected output

require_once '../earthenAuth_helper.php';
require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';
require_once '../config/earthcal_env.php'; // Include EarthCal database connection
require_once '../scripts/earthen_subscribe_functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$earthcal_conn = $client_conn ?? null; // Normalize EarthCal connection handle

$response = [];
$successes = [];
$failures = [];
$overall_success = true;

try {
    if (!$earthcal_conn instanceof mysqli || $earthcal_conn->connect_errno) {
        throw new Exception('EarthCal DB connection is not available.');
    }

    // Accept buwana_id from query or session
    $buwana_id = $_GET['buwana_id'] ?? $_SESSION['buwana_id'] ?? '';
    if (empty($buwana_id) || !is_numeric($buwana_id)) {
        throw new Exception('Invalid Buwana ID. Please provide a valid ID.');
    }

    // Fetch user email from Buwana users table
    $sql_fetch_details = "SELECT email FROM users_tb WHERE buwana_id = ?";
    $stmt_fetch_details = $buwana_conn->prepare($sql_fetch_details);
    if (!$stmt_fetch_details) {
        throw new Exception('Error preparing statement for fetching details: ' . $buwana_conn->error);
    }
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

                    // Find ecobricker_id via buwana_id
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
                    $successes[] = 'Deleted your Gobrik Account';
                } catch (Exception $e) {
                    if ($gobrik_conn->in_transaction) {
                        $gobrik_conn->rollback();
                    }
                    $failures[] = 'Failed to delete your Gobrik Account';
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
                    $successes[] = 'Deleted your Earthcal Account';
                } catch (Exception $e) {
                    if ($earthcal_conn->in_transaction) {
                        $earthcal_conn->rollback();
                    }
                    $failures[] = 'Failed to delete your Earthcal Account';
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
        $successes[] = 'Deleted your core Buwana Account';
    } catch (Exception $e) {
        if ($buwana_conn->in_transaction) {
            $buwana_conn->rollback();
        }
        $failures[] = 'Failed to delete your core Buwana Account';
        $overall_success = false;
    }

    // Call Earthen unsubscribe
    if (!empty($email_addr)) {
        try {
            earthenUnsubscribe($email_addr);
            $successes[] = 'Unsubscribed you from Earthen';
        } catch (Exception $e) {
            $failures[] = 'Failed to unsubscribe you from Earthen';
            $overall_success = false;
        }
    }

    // Clear user session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    $query = http_build_query([
        'successes' => $successes,
        'failures' => $failures
    ]);

    $response = [
        'success' => $overall_success,
        'message' => $overall_success ? 'User deleted successfully.' : 'User deletion completed with some errors.',
        'redirect' => 'goodbye.php?' . $query
    ];
} catch (Exception $e) {
    if ($buwana_conn->in_transaction) {
        $buwana_conn->rollback();
    }
    if (isset($gobrik_conn) && $gobrik_conn->in_transaction) {
        $gobrik_conn->rollback();
    }
    if ($earthcal_conn instanceof mysqli && $earthcal_conn->in_transaction) {
        $earthcal_conn->rollback();
    }

    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

ob_end_clean();
echo json_encode($response);
exit();
