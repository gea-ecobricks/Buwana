<?php
ob_start(); // Start output buffering
//require_once '../earthenAuth_helper.php'; // Include the authentication helper functions

require_once("../buwanaconn_env.php");

// Include necessary files and setup JWT creation
require_once '../scripts/earthen_subscribe_functions.php';

// PART 1: Get the user's data from the POST request
$buwana_id = $_POST['buwana_id'] ?? null;
$credential_key = $_POST['credential_key'] ?? null;
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
    // Pass $first_name to the function
    subscribeUserToNewsletter($credential_key, $to_subscribe, $first_name);
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