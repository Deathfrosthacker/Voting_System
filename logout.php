<?php
session_start();
require_once "./config/connection.php";

/* Log logout activity (optional but professional) */
if (isset($_SESSION['user_id'])) {
    $user_id  = $_SESSION['user_id'];
    $activity = "Logged out";

    mysqli_query(
        $conn,
        "INSERT INTO logs (id, Activity, TimeStamp)
         VALUES ('$user_id', '$activity', NOW())"
    );
}

/* Destroy session */
session_unset();
session_destroy();

/* Redirect to login */
header("Location: login.php");
exit();
