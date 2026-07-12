<?php
// Database configuration
$host = "localhost";
$username = "root";      // change if different
$password = "";          // add password if set
$database = "voting_system";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}

// Optional: uncomment to test connection
// echo "Connected successfully";
?>
