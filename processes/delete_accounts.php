<?php
ob_start(); // Start output buffering to prevent unexpected output

require_once '../earthenAuth_helper.php';
require_once '../gobrikconn_env.php';
require_once '../buwanaconn_env.php';
require_once '../scripts/earthen_subscribe_functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [];

try {
    $target_ecobricker_id = $_GET['id'] ?? '';
    if (empty($target_ecobricker_id) || !is_numeric($target_ecobricker_id)) {
        throw new Exception('Invalid ecobricker ID. Please provide a valid ID.');
    }

    // Fetch email_addr and buwana_id from tb_ecobrickers
    $sql_fetch_details = "SELECT email_addr, buwana_id FROM tb_ecobrickers WHERE ecobricker_id = ?";
    $stmt_fetch_details = $gobrik_conn->prepare($sql_fetch_details);
    if (!$stmt_fetch_details) {
        throw new Exception('Error preparing statement for fetching details: ' . $gobrik_conn->error);
    }
    $stmt_fetch_details->bind_param('i', $target_ecobricker_id);
    $stmt_fetch_details->execute();
    $stmt_fetch_details->bind_result($email_addr, $buwana_id);
    $stmt_fetch_details->fetch();
    $stmt_fetch_details->close();

    if (empty($email_addr)) {
        throw new Exception('No email found for the given ecobricker ID.');
    }

    // Begin transactions
    $buwana_conn->begin_transaction();
    $gobrik_conn->begin_transaction();

    // Delete from tb_ecobrickers
    $sql_delete_ecobricker = "DELETE FROM tb_ecobrickers WHERE ecobricker_id = ?";
    $stmt_delete_ecobricker = $gobrik_conn->prepare($sql_delete_ecobricker);
    if (!$stmt_delete_ecobricker) {
        throw new Exception('Error preparing statement for deleting ecobricker: ' . $gobrik_conn->error);
    }
    $stmt_delete_ecobricker->bind_param('i', $target_ecobricker_id);
    $stmt_delete_ecobricker->execute();
    $stmt_delete_ecobricker->close();

    // Delete from users_tb
    $sql_delete_user = "DELETE FROM users_tb WHERE buwana_id = ?";
    $stmt_delete_user = $buwana_conn->prepare($sql_delete_user);
    if (!$stmt_delete_user) {
        throw new Exception('Error preparing statement for deleting user: ' . $buwana_conn->error);
    }
    $stmt_delete_user->bind_param('i', $buwana_id);
    $stmt_delete_user->execute();
    $stmt_delete_user->close();

    // Delete from credentials_tb
    $sql_delete_credentials = "DELETE FROM credentials_tb WHERE buwana_id = ?";
    $stmt_delete_credentials = $buwana_conn->prepare($sql_delete_credentials);
    if (!$stmt_delete_credentials) {
        throw new Exception('Error preparing statement for deleting credentials: ' . $buwana_conn->error);
    }
    $stmt_delete_credentials->bind_param('i', $buwana_id);
    $stmt_delete_credentials->execute();
    $stmt_delete_credentials->close();

    // Call Earthen unsubscribe
    if (!empty($email_addr)) {
        earthenUnsubscribe($email_addr);
    }

    // Commit transactions
    $buwana_conn->commit();
    $gobrik_conn->commit();

    // Clear user session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Respond with redirect instruction
    $response = [
        'success' => true,
        'message' => 'User deleted successfully.',
        'redirect' => 'goodbye.php'
    ];
} catch (Exception $e) {
    // Rollback on error
    if ($buwana_conn->in_transaction) {
        $buwana_conn->rollback();
    }
    if ($gobrik_conn->in_transaction) {
        $gobrik_conn->rollback();
    }

    $response = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

// Clean output buffer and return JSON
ob_end_clean();
echo json_encode($response);
exit();
?>
