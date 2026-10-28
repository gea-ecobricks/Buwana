<?php
ob_start(); // Start output buffering
//require_once '../earthenAuth_helper.php'; // Include the authentication helper functions

require_once("../buwanaconn_env.php");

// Include necessary files and setup JWT creation
require_once '../scripts/earthen_subscribe_functions.php';

session_start();

// PART 0: Only a signup whose code was verified on the server
// (signup-3_process.php) in this browser session may continue.
$buwana_id = (int) ($_POST['buwana_id'] ?? 0);
$verified_buwana_id = (int) ($_SESSION['signup_verified_buwana_id'] ?? 0);
if (!$verified_buwana_id || $buwana_id !== $verified_buwana_id) {
    ob_end_clean();
    header('Location: ' . ($buwana_id ? 'signup-3.php?id=' . $buwana_id : 'signup-1.php'));
    exit();
}

// PART 1: Get the user's data. The email comes from the verified credential,
// never from the form.
$credential_key = null;
$stmt_cred = $buwana_conn->prepare("SELECT credential_key FROM credentials_tb WHERE buwana_id = ? LIMIT 1");
if ($stmt_cred) {
    $stmt_cred->bind_param('i', $buwana_id);
    $stmt_cred->execute();
    $stmt_cred->bind_result($credential_key);
    $stmt_cred->fetch();
    $stmt_cred->close();
}
$first_name = $_POST['first_name'] ?? ''; // Get the first name from POST
$subscribed_newsletters = json_decode($_POST['subscribed_newsletters'] ?? '[]', true);
$ghost_member_id = $_POST['ghost_member_id'] ?? null;

// Ensure we have the user's email
if (empty($credential_key)) {
    die('User email could not be retrieved.');
}

// Retrieve selected subscriptions from the form submission
$selected_subscriptions = $_POST['subscriptions'] ?? [];

// Determine which newsletters to subscribe to and which to unsubscribe from
$to_subscribe = array_diff($selected_subscriptions, $subscribed_newsletters);

// If subscribed_newsletters is empty, treat this as a new user subscription
if (empty($subscribed_newsletters)) {
    // The email may already be an Earthen member with no newsletters; creating
    // it again fails with "Member already exists", so update that member instead.
    $existing_member_id = getMemberIdByEmail($credential_key);
    if ($existing_member_id) {
        if (!empty($selected_subscriptions)) {
            updateSubscribeUser($existing_member_id, $selected_subscriptions);
        }
    } else {
        subscribeUserToNewsletter($credential_key, $to_subscribe, $first_name);
    }
} else {
    // If subscribed_newsletters is not empty, use the provided member ID to update subscriptions
    if ($ghost_member_id) {
        updateSubscribeUser($ghost_member_id, $selected_subscriptions);
    } else {
        error_log('Error: Member ID is missing for updating subscriptions.');
    }
}

// PART 2: Update users_tb buwana database record
if ($buwana_id) {
    $update_user_query = "UPDATE users_tb SET account_status = 'registered and subscribed, no login', terms_of_service = 1 WHERE buwana_id = ?";
    $stmt_update_user = $buwana_conn->prepare($update_user_query);
    if ($stmt_update_user) {
        $stmt_update_user->bind_param('i', $buwana_id);
        $stmt_update_user->execute();
        $stmt_update_user->close();
    }
}

// PART 4: Redirect the user to the finalize page
ob_clean(); // ✨ Clear any accidental output
// echo json_encode([
//     'success' => true,
//     'redirect' => 'signup-6.php?id=' . urlencode($buwana_id)
// ]);
header("Location: signup-6.php?id=" . urlencode($buwana_id));
exit();
?>