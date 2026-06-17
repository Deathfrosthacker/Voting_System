<?php
session_start();
require_once "./config/connection.php";

// Log the logout activity if user was logged in
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $activity = "Logged out";
    $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
    if ($logStmt) {
        mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
        mysqli_stmt_execute($logStmt);
    }
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
?>