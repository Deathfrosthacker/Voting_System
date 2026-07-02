<?php
/**
 * ============================================================
 * DATABASE MIGRATION: DATE -> DATETIME + 24H MINIMUM DURATION
 * ============================================================
 * 
 * Run this script once to upgrade your election system from
 * DATE-only fields to DATETIME fields with 24-hour minimum
 * election duration validation.
 * 
 * BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
 * 
 * To run: php migration_datetime.php
 * Or visit via browser if placed in web root.
 */

require_once "./config/connection.php";

echo "<h2>Election System - DATETIME Migration</h2>";
echo "<pre>\n";

$errors = [];
$success = [];

/* ============================================================
   STEP 1: Alter positions table - DATE to DATETIME
   ============================================================ */
$alterPositions = "ALTER TABLE positions 
    MODIFY start_date DATETIME NOT NULL,
    MODIFY end_date DATETIME NOT NULL";

if (mysqli_query($conn, $alterPositions)) {
    $success[] = "positions table: start_date and end_date upgraded to DATETIME";
} else {
    $errors[] = "positions table ALTER failed: " . mysqli_error($conn);
}

/* ============================================================
   STEP 2: Alter candidates table - DATE to DATETIME
   ============================================================ */
$alterCandidates = "ALTER TABLE candidates 
    MODIFY start_date DATETIME NOT NULL,
    MODIFY end_date DATETIME NOT NULL";

if (mysqli_query($conn, $alterCandidates)) {
    $success[] = "candidates table: start_date and end_date upgraded to DATETIME";
} else {
    $errors[] = "candidates table ALTER failed: " . mysqli_error($conn);
}

/* ============================================================
   STEP 3: Add election_settings table for configurable rules
   ============================================================ */
$settingsTable = "CREATE TABLE IF NOT EXISTS election_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $settingsTable)) {
    $success[] = "election_settings table created (or already exists)";
} else {
    $errors[] = "election_settings table creation failed: " . mysqli_error($conn);
}

/* ============================================================
   STEP 4: Insert default minimum duration (24 hours in seconds)
   ============================================================ */
$minDuration = 86400; // 24 hours in seconds
$insertSetting = "INSERT INTO election_settings (setting_name, setting_value, description) 
    VALUES ('minimum_election_duration_seconds', '$minDuration', 'Minimum allowed duration between election start and end datetime. Default: 86400 seconds (24 hours).')
    ON DUPLICATE KEY UPDATE setting_value = '$minDuration', description = 'Minimum allowed duration between election start and end datetime. Default: 86400 seconds (24 hours).'";

if (mysqli_query($conn, $insertSetting)) {
    $success[] = "Default minimum election duration set to 24 hours (86400 seconds)";
} else {
    $errors[] = "Failed to insert minimum duration setting: " . mysqli_error($conn);
}

/* ============================================================
   STEP 5: Validate existing elections against new 24h rule
   ============================================================ */
echo "\n--- Validating Existing Elections Against 24-Hour Minimum Rule ---\n";

$checkExisting = mysqli_query($conn, 
    "SELECT id, position_name, start_date, end_date, 
            TIMESTAMPDIFF(SECOND, start_date, end_date) AS duration_seconds
     FROM positions"
);

$invalidCount = 0;
if ($checkExisting) {
    while ($row = mysqli_fetch_assoc($checkExisting)) {
        $duration = (int)$row['duration_seconds'];
        if ($duration < $minDuration) {
            $invalidCount++;
            $errors[] = "INVALID ELECTION FOUND: '{$row['position_name']}' "
                . "(ID: {$row['id']}) - Duration: " . round($duration/3600, 1) . " hours. "
                . "Starts: {$row['start_date']}, Ends: {$row['end_date']}";
        }
    }
    if ($invalidCount === 0) {
        $success[] = "All existing elections meet the 24-hour minimum duration requirement";
    }
} else {
    $errors[] = "Could not validate existing elections: " . mysqli_error($conn);
}

/* ============================================================
   STEP 6: Validate existing candidates against new 24h rule
   ============================================================ */
$checkCandidates = mysqli_query($conn, 
    "SELECT id, name, position, start_date, end_date,
            TIMESTAMPDIFF(SECOND, start_date, end_date) AS duration_seconds
     FROM candidates"
);

$invalidCandCount = 0;
if ($checkCandidates) {
    while ($row = mysqli_fetch_assoc($checkCandidates)) {
        $duration = (int)$row['duration_seconds'];
        if ($duration < $minDuration) {
            $invalidCandCount++;
            $errors[] = "INVALID CANDIDATE FOUND: '{$row['name']}' for '{$row['position']}' "
                . "(ID: {$row['id']}) - Duration: " . round($duration/3600, 1) . " hours";
        }
    }
    if ($invalidCandCount === 0) {
        $success[] = "All existing candidate records meet the 24-hour minimum duration";
    }
} else {
    $errors[] = "Could not validate existing candidates: " . mysqli_error($conn);
}

/* ============================================================
   SUMMARY
   ============================================================ */
echo "\n========== MIGRATION SUMMARY ==========\n";
echo "Successes: " . count($success) . "\n";
foreach ($success as $s) {
    echo "  [OK] $s\n";
}

echo "\nIssues Found: " . count($errors) . "\n";
foreach ($errors as $e) {
    echo "  [!] $e\n";
}

if ($invalidCount > 0 || $invalidCandCount > 0) {
    echo "\n*** ACTION REQUIRED ***\n";
    echo "Found $invalidCount invalid elections and $invalidCandCount invalid candidates.\n";
    echo "These elections have durations LESS than 24 hours.\n";
    echo "Options:\n";
    echo "  1. Delete them and recreate with proper durations\n";
    echo "  2. Manually update their end dates to be at least 24h after start\n";
    echo "  3. Temporarily lower the minimum duration setting\n";
}

echo "\n========== END OF MIGRATION ==========\n";
echo "</pre>";

mysqli_close($conn);
?>