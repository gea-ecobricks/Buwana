<?php
ob_start(); // Start output buffering to prevent unexpected output

require_once '../earthenAuth_helper.php';
require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';
require_once '../calconn_env.php'; // Include EarthCal database connection
require_once '../scripts/earthen_subscribe_functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [];

try {
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
                break;

            case 'earthcal':
                $cal_conn->begin_transaction();

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
                break;

            default:
                // Unsupported apps are ignored for now
                break;
        }
    }

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

    // Call Earthen unsubscribe
    if (!empty($email_addr)) {
        earthenUnsubscribe($email_addr);
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

    $response = [
        'success' => true,
        'message' => 'User deleted successfully.',
        'redirect' => 'goodbye.php'
    ];
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

    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

ob_end_clean();
echo json_encode($response);
exit();

