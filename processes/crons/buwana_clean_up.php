<?php
ob_start();

// --------------------------------------------------------
// Cron log setup
// --------------------------------------------------------
$log_file = __DIR__ . '/buwana_clean_up.log';

file_put_contents(
    $log_file,
    '[' . date('Y-m-d H:i:s') . "] Buwana cleanup cron started\n",
    FILE_APPEND
);

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

// --------------------------------------------------------
// Includes
// --------------------------------------------------------
require_once __DIR__ . '/../../buwanaconn_env.php';

// --------------------------------------------------------
// Helper for logging
// --------------------------------------------------------
function cron_log(string $message): void {
    global $log_file;
    file_put_contents(
        $log_file,
        '[' . date('Y-m-d H:i:s') . "] {$message}\n",
        FILE_APPEND
    );
}

try {
    if (!($buwana_conn instanceof mysqli) || $buwana_conn->connect_errno) {
        throw new Exception('Buwana DB connection is not available.');
    }

    $sql = "DELETE FROM users_tb WHERE account_status = 'name set only'";
    if (!$stmt = $buwana_conn->prepare($sql)) {
        throw new Exception('Error preparing delete statement: ' . $buwana_conn->error);
    }

    $stmt->execute();
    $deleted_rows = $stmt->affected_rows;
    $stmt->close();

    cron_log("Deleted {$deleted_rows} users with account_status = 'name set only'.");
    cron_log('Cron completed successfully.');
} catch (Exception $e) {
    cron_log('Error: ' . $e->getMessage());
}

// Finish output buffering cleanly
ob_end_flush();
