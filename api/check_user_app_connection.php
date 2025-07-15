<?php
require_once __DIR__ . '/../earthenAuth_helper.php';
require_once __DIR__ . '/../buwanaconn_env.php';

function check_user_app_connection($buwana_conn, $buwana_id, $client_id, $lang = 'en') {
    if (!$buwana_id || !$client_id) {
        return false;
    }

    // Ensure we check for an existing registered connection only
    $check_sql = "SELECT id FROM user_app_connections_tb WHERE buwana_id = ? AND client_id = ? AND status = 'registered' LIMIT 1";
    $check_stmt = $buwana_conn->prepare($check_sql);
    if ($check_stmt) {
        $check_stmt->bind_param('is', $buwana_id, $client_id);
        $check_stmt->execute();
        $check_stmt->bind_result($connection_id);
        $check_stmt->fetch();
        $check_stmt->close();

        if (!$connection_id) {
            header("Location: ../$lang/app-connect.php?id=$buwana_id&client_id=$client_id");
            exit();
        } else {
            $_SESSION['connection_id'] = $connection_id;
            return true;
        }
    }
    return false;
}
?>
