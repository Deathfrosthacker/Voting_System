<?php
session_start();
require_once "./config/connection.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

echo "<h2>Database Structure Diagnostic</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
    th { background: #2563eb; color: white; }
    .error { color: #dc2626; font-weight: bold; }
    .success { color: #16a34a; font-weight: bold; }
    .warning { color: #ea580c; font-weight: bold; }
</style>";

// 1. Check votes table structure
echo "<div class='section'>";
echo "<h3>1. Votes Table Structure</h3>";
$votesStructure = mysqli_query($conn, "DESCRIBE votes");
if ($votesStructure) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($votesStructure)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>Error: " . mysqli_error($conn) . "</p>";
}
echo "</div>";

// 2. Check for unique constraints
echo "<div class='section'>";
echo "<h3>2. Votes Table Indexes/Constraints</h3>";
$indexes = mysqli_query($conn, "SHOW INDEXES FROM votes");
if ($indexes) {
    echo "<table>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th><th>Index Type</th></tr>";
    while ($row = mysqli_fetch_assoc($indexes)) {
        $unique = $row['Non_unique'] == 0 ? '<span class="warning">YES (UNIQUE)</span>' : 'No';
        echo "<tr>";
        echo "<td>{$row['Key_name']}</td>";
        echo "<td>{$row['Column_name']}</td>";
        echo "<td>{$unique}</td>";
        echo "<td>{$row['Index_type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>Error: " . mysqli_error($conn) . "</p>";
}
echo "</div>";

// 3. Check candidates table structure
echo "<div class='section'>";
echo "<h3>3. Candidates Table Structure</h3>";
$candidatesStructure = mysqli_query($conn, "DESCRIBE candidates");
if ($candidatesStructure) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($candidatesStructure)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>Error: " . mysqli_error($conn) . "</p>";
}
echo "</div>";

// 4. Sample data check
echo "<div class='section'>";
echo "<h3>4. Sample Data Check</h3>";

// Count votes
$voteCount = mysqli_query($conn, "SELECT COUNT(*) as total FROM votes");
$voteTotal = mysqli_fetch_assoc($voteCount)['total'];
echo "<p><strong>Total Votes:</strong> {$voteTotal}</p>";

// Count candidates
$candCount = mysqli_query($conn, "SELECT COUNT(*) as total FROM candidates");
$candTotal = mysqli_fetch_assoc($candCount)['total'];
echo "<p><strong>Total Candidates:</strong> {$candTotal}</p>";

// Show sample votes with details
echo "<h4>Recent Votes (with candidate info):</h4>";
$recentVotes = mysqli_query($conn, "
    SELECT v.id, v.user_id, v.candidate_id, v.vote_time, 
           c.name as candidate_name, c.position
    FROM votes v
    LEFT JOIN candidates c ON v.candidate_id = c.id
    ORDER BY v.vote_time DESC
    LIMIT 10
");

if (mysqli_num_rows($recentVotes) > 0) {
    echo "<table>";
    echo "<tr><th>Vote ID</th><th>User ID</th><th>Candidate ID</th><th>Candidate Name</th><th>Position</th><th>Vote Time</th></tr>";
    while ($row = mysqli_fetch_assoc($recentVotes)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['candidate_id']}</td>";
        echo "<td>" . ($row['candidate_name'] ?? '<span class="error">NULL/DELETED</span>') . "</td>";
        echo "<td>" . ($row['position'] ?? '<span class="error">NULL</span>') . "</td>";
        echo "<td>{$row['vote_time']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No votes found.</p>";
}
echo "</div>";

// 5. Check for orphaned votes
echo "<div class='section'>";
echo "<h3>5. Data Integrity Check</h3>";

$orphanedVotes = mysqli_query($conn, "
    SELECT v.id, v.user_id, v.candidate_id 
    FROM votes v
    LEFT JOIN candidates c ON v.candidate_id = c.id
    WHERE c.id IS NULL
");

$orphanCount = mysqli_num_rows($orphanedVotes);
if ($orphanCount > 0) {
    echo "<p class='error'>⚠️ Found {$orphanCount} orphaned votes (votes pointing to non-existent candidates)!</p>";
    echo "<table>";
    echo "<tr><th>Vote ID</th><th>User ID</th><th>Invalid Candidate ID</th></tr>";
    while ($row = mysqli_fetch_assoc($orphanedVotes)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td class='error'>{$row['candidate_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>✓ No orphaned votes found - all votes reference valid candidates.</p>";
}
echo "</div>";

// 6. Recommendations
echo "<div class='section'>";
echo "<h3>6. Recommendations</h3>";
echo "<ul>";

// Check if there's a unique constraint on user_id
$hasUserUnique = mysqli_query($conn, "
    SELECT * FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'votes' 
    AND column_name = 'user_id' 
    AND non_unique = 0
");

if (mysqli_num_rows($hasUserUnique) > 0) {
    echo "<li class='error'>⚠️ There is a UNIQUE constraint on 'user_id' in votes table. This prevents users from voting for multiple positions! You should remove this constraint.</li>";
    echo "<li><strong>Fix SQL:</strong> <code>ALTER TABLE votes DROP INDEX user_id;</code></li>";
} else {
    echo "<li class='success'>✓ No problematic unique constraint on user_id alone.</li>";
}

// Check for proper composite unique key
$hasCompositeUnique = mysqli_query($conn, "
    SELECT * FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'votes' 
    AND index_name LIKE '%user%candidate%'
    AND non_unique = 0
");

if (mysqli_num_rows($hasCompositeUnique) > 0) {
    echo "<li class='success'>✓ Found composite unique constraint on user_id + candidate_id (good for preventing duplicate votes).</li>";
} else {
    echo "<li class='warning'>Consider adding a composite UNIQUE constraint to prevent duplicate votes:</li>";
    echo "<li><strong>Recommended SQL:</strong> <code>ALTER TABLE votes ADD UNIQUE KEY unique_vote (user_id, candidate_id);</code></li>";
}

echo "</ul>";
echo "</div>";
?>