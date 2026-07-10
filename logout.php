<?php
/**
 * LOGOUT - FIXED VERSION
 * Clears ALL role-specific sessions to ensure complete logout.
 */

ini_set('session.cookie_path', '/');
require_once "./config/connection.php";

// All possible role session names
$role_sessions = ['ADMIN_SESSION', 'OFFICER_SESSION', 'OBSERVER_SESSION', 'VOTER_SESSION', 'LOGIN_PORTAL', 'PHPSESSID'];

$logged_user_id = null;

// Try each session to find the active user and log the logout
foreach ($role_sessions as $sess_name) {
    if (isset($_COOKIE[$sess_name])) {
        session_name($sess_name);
        session_start();

        if (isset($_SESSION['user_id']) && !$logged_user_id) {
            $logged_user_id = (int)$_SESSION['user_id'];
        }

        session_unset();
        session_destroy();

        // Also clear the cookie
        $params = session_get_cookie_params();
        setcookie($sess_name, '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// Log the logout activity if we found a user
if ($logged_user_id) {
    $activity = "Logged out";
    $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
    if ($logStmt) {
        mysqli_stmt_bind_param($logStmt, "is", $logged_user_id, $activity);
        mysqli_stmt_execute($logStmt);
    }
}

header("Location: login.php");
exit();
?>