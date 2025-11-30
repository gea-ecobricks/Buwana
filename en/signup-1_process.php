<?php
require_once '../buwanaconn_env.php';
require_once '../fetch_app_info.php';

$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ✅ Sanitize input
    $raw_name = trim($_POST['first_name'] ?? '');
    $credential = trim($_POST['credential'] ?? '');

    if (empty($raw_name) || empty($credential)) {
        error_log("Missing first_name or credential type.");
        header("Location: signup-1.php?error=missing_fields");
        exit();
    }

    $name_parts = preg_split('/\s+/', $raw_name);
    $first_name_raw = array_shift($name_parts);
    $remaining_name = implode(' ', $name_parts);

    $first_name = mb_convert_case(mb_strtolower($first_name_raw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    $last_name = '';

    if (!empty($remaining_name)) {
        $last_name = mb_convert_case(mb_strtolower($remaining_name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    $full_name = trim($first_name . ' ' . $last_name);

    $created_at = $last_login = date("Y-m-d H:i:s");
    $account_status = 'name set only';
    $role = 'ecobricker';
    $notes = "Step 1 complete: Buwana beta testing";

    // ➤ Insert into users_tb
    $sql_user = "INSERT INTO users_tb (first_name, last_name, full_name, created_at, last_login, account_status, role, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_user = $buwana_conn->prepare($sql_user);

    if ($stmt_user) {
        $stmt_user->bind_param("ssssssss", $first_name, $last_name, $full_name, $created_at, $last_login, $account_status, $role, $notes);

        if ($stmt_user->execute()) {
            $buwana_id = $buwana_conn->insert_id;

            // ➤ Register client app connection
            $client_id = $_SESSION['client_id'] ?? $default_client_id;
            $sql_connect = "INSERT INTO user_app_connections_tb (buwana_id, client_id) VALUES (?, ?)";
            $stmt_connect = $buwana_conn->prepare($sql_connect);
            if ($stmt_connect) {
                $stmt_connect->bind_param("is", $buwana_id, $client_id);
                $stmt_connect->execute();
                $stmt_connect->close();
            } else {
                error_log("⚠️ Error inserting into user_app_connections_tb: " . $buwana_conn->error);
            }

            // ➤ Insert into credentials_tb
            $sql_cred = "INSERT INTO credentials_tb (buwana_id, credential_type, times_used, failed_password_count, last_login)
                         VALUES (?, ?, 0, 0, ?)";
            $stmt_cred = $buwana_conn->prepare($sql_cred);
            if ($stmt_cred) {
                $stmt_cred->bind_param("iss", $buwana_id, $credential, $last_login);
                if ($stmt_cred->execute()) {
                    $success = true;
                    echo json_encode([
                        'success' => true,
                        'redirect' => "signup-2.php?id=$buwana_id"
                    ]);
                    exit();
                } else {
                    error_log("❌ Failed to insert into credentials_tb: " . $stmt_cred->error);
                }
                $stmt_cred->close();
            } else {
                error_log("❌ Error preparing credentials_tb: " . $buwana_conn->error);
            }
        } else {
            error_log("❌ Error inserting into users_tb: " . $stmt_user->error);
        }
        $stmt_user->close();
    } else {
        error_log("❌ Error preparing users_tb insert: " . $buwana_conn->error);
    }

    $buwana_conn->close();

    // Redirect back to signup page with error (optional)
    echo json_encode([
        'success' => true,
        'redirect' => "signup-2.php?id=$buwana_id"
    ]);
    exit();

}
?>
