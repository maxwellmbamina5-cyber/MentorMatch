<?php
// logout.php
session_start();

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once 'includes/functions.php';
    logActivity($_SESSION['user_id'], 'User logged out');
}

// Destroy all session data
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login.php');
exit();
?>