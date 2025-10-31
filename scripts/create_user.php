<?php
// create_user.php

require_once '../buwanaconn_env.php'; // Required for updating Buwana DB

// Enable strict error mode for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * ===========================================
 * FUNCTION: Create a user in the client app DB
 * ===========================================
 */
function createUserInClientApp($buwana_id, $userData, $app_name, $client_conn, $buwana_conn, $client_id) {
    $created_at = date('Y-m-d H:i:s');

    // ✅ Robustly check that $client_conn is a valid mysqli connection
    if (!($client_conn instanceof mysqli) || $client_conn->connect_errno) {
        error_log("❌ Invalid or failed client DB connection in create_user.php");
        return ['success' => false, 'error' => 'Client DB connection not initialized properly'];
    }

    error_log("📥 Creating user in client app: $app_name for Buwana ID $buwana_id");

    /**
     * ===========================================
     * SPECIAL CASE: GoBrik Legacy Table
     * ===========================================
     */
    if ($app_name === 'gobrik') {
        $insert_sql = "INSERT INTO tb_ecobrickers (
            buwana_id, first_name, last_name, full_name, email_addr,
            date_registered, terms_of_service, account_notes, country_id,
            language_id, community_id, earthling_emoji, profile_pic,
            continent_code, location_full, location_lat, location_long
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $client_conn->prepare($insert_sql);
        if (!$stmt) {
            error_log('❌ GoBrik DB Prepare Error: ' . $client_conn->error);
            updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'failed');
            return ['success' => false, 'error' => 'GoBrik DB prepare failed'];
        }

        $stmt->bind_param(
            'isssssisisissssdd',
            $buwana_id,
            $userData['first_name'],
            $userData['last_name'],
            $userData['full_name'],
            $userData['email'],
            $created_at,
            $userData['terms_of_service'],
            $userData['notes'],
            $userData['country_id'],
            $userData['language_id'],
            $userData['community_id'],
            $userData['earthling_emoji'],
            $userData['profile_pic'],
            $userData['continent_code'],
            $userData['location_full'],
            $userData['location_lat'],
            $userData['location_long']
        );
    }



    /**
     * ===========================================
     * DEFAULT CASE: Earthcal and other Modern apps using users_tb
     * ===========================================
     */
    else {
        $insert_sql = "INSERT INTO users_tb (
            buwana_id, username, first_name, last_name, full_name, email,
            created_at, terms_of_service, notes, profile_pic, country_id,
            language_id, watershed_id, continent_code, location_full,
            location_watershed, location_lat, location_long, community_id, earthling_emoji
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $client_conn->prepare($insert_sql);
        if (!$stmt) {
            error_log('❌ Client DB Prepare Error: ' . $client_conn->error);
            updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'failed');
            return ['success' => false, 'error' => 'Client DB prepare failed'];
        }

        $stmt->bind_param(
            'issssssisisissssddis',
            $buwana_id,
            $userData['username'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['full_name'],
            $userData['email'],
            $created_at,
            $userData['terms_of_service'],
            $userData['notes'],
            $userData['profile_pic'],
            $userData['country_id'],
            $userData['language_id'],
            $userData['watershed_id'],
            $userData['continent_code'],
            $userData['location_full'],
            $userData['location_watershed'],
            $userData['location_lat'],
            $userData['location_long'],
            $userData['community_id'],
            $userData['earthling_emoji']
        );
    }

    /**
     * ===========================================
     * Execute insert and update Buwana
     * ===========================================
     */
    try {
        $stmt->execute();
        $stmt->close();

        updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'registered', $created_at);
        updateBuwanaUserNotes($buwana_conn, $buwana_id, $app_name, $created_at);

        error_log("✅ User successfully created in client app ($app_name)");
        return ['success' => true];
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $error = $e->getMessage();

        if (strpos($error, 'Duplicate entry') !== false) {
            error_log("⚠️ User already exists in client app ($app_name), continuing...");
            updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'registered', $created_at);
            return ['success' => true, 'note' => 'User already existed, updated app connection.'];
        } else {
            error_log('❌ Client DB Insert Exception: ' . $error);
            updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, 'failed');
            return ['success' => false, 'error' => 'Client DB insert failed'];
        }
    }
}

/**
 * ===========================================
 * FUNCTION: Update user_app_connections_tb status
 * ===========================================
 */
function updateAppConnectionStatus($buwana_conn, $buwana_id, $client_id, $status, $connected_at = null) {
    $connected_at = $connected_at ?? date('Y-m-d H:i:s');

    $update_sql = "UPDATE user_app_connections_tb
                   SET status = ?, connected_at = ?
                   WHERE buwana_id = ? AND client_id = ?";
    $stmt = $buwana_conn->prepare($update_sql);

    if ($stmt) {
        $stmt->bind_param('ssii', $status, $connected_at, $buwana_id, $client_id);
        $stmt->execute();
        $stmt->close();
        error_log("📌 App connection status updated to '$status' for client ID $client_id");
    } else {
        error_log('❌ Failed to update app connection status: ' . $buwana_conn->error);
    }
}

/**
 * ===========================================
 * FUNCTION: Add notes to users_tb
 * ===========================================
 */
function updateBuwanaUserNotes($buwana_conn, $buwana_id, $app_name, $created_at) {
    $note_text = "First registered on $app_name at $created_at.";

    $update_sql = "UPDATE users_tb
                   SET notes = CONCAT(COALESCE(notes, ''), ' ', ?)
                   WHERE buwana_id = ?";
    $stmt = $buwana_conn->prepare($update_sql);

    if ($stmt) {
        $stmt->bind_param('si', $note_text, $buwana_id);
        $stmt->execute();
        $stmt->close();
        error_log("📝 Notes updated for Buwana ID $buwana_id");
    } else {
        error_log('❌ Failed to update Buwana user notes: ' . $buwana_conn->error);
    }
}
?>
