<?php
session_start(); // Start the session to access session variables

// Retrieve parameters that may be needed after logout
$buwana_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : ($_SESSION['buwana_id'] ?? '');
$client_id = isset($_GET['app']) ? filter_var($_GET['app'], FILTER_SANITIZE_SPECIAL_CHARS) : ($_SESSION['client_id'] ?? '');

// Retrieve the redirect parameter from the query string, if it exists
$redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_SANITIZE_SPECIAL_CHARS) : '';

// Log the action for debugging purposes
// PHP's own error log, never a file in the web root; no session ids in logs.
error_log("[logout] user logging out");

// Unset all session variables, including any JWT that may be stored
unset($_SESSION['jwt']);
$_SESSION = [];

// Destroy the session
if (session_id() !== "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/'); // Clear the session cookie
}
session_destroy(); // Destroy the session

// Clear all cookies related to session or user data
if (isset($_COOKIE['buwana_id'])) {
    setcookie('buwana_id', '', time() - 3600, '/');
}

// Build the redirect URL with status and redirect parameters
$redirect_url = 'login.php?status=logout';
if (!empty($buwana_id)) {
    $redirect_url .= '&id=' . urlencode($buwana_id);
}
if (!empty($client_id)) {
    $redirect_url .= '&app=' . urlencode($client_id);
}
if (!empty($redirect)) {
    $redirect_url .= '&redirect=' . urlencode($redirect);
}

// Redirect to the login page
header('Location: ' . $redirect_url);
exit();
?>
