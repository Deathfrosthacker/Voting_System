<?php
session_start();
require_once "./config/connection.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get position ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: positions.php?status=error");
    exit();
}

$position_id = mysqli_real_escape_string($conn, $_GET['id']);

// First, get the position name for logging
$query = "SELECT position_name FROM positions WHERE id = '$position_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header("Location: positions.php?status=error");
    exit();
}

$position = mysqli_fetch_assoc($result);
$position_name = $position['position_name'];

// Delete the position
$delete_sql = "DELETE FROM positions WHERE id = '$position_id'";

if (mysqli_query($conn, $delete_sql)) {

    // LOG ACTIVITY
    $user_id = $_SESSION['user_id'];
    $activity = "Deleted Position: " . $position_name;

    $log_sql = "INSERT INTO logs (user_id, activity, log_time)
                VALUES ('$user_id', '$activity', NOW())";

    mysqli_query($conn, $log_sql);

    header("Location: positions.php?status=deleted");
    exit();

} else {
    header("Location: positions.php?status=error");
    exit();
}
?>