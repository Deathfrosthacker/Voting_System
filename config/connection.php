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
    die("Database connection failed: " . mysqli_connect_error());
}

// Optional: uncomment to test connection
// echo "Connected successfully";
?>
