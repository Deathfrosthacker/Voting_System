<?php
require_once "./config/connection.php";
require_once "./rbac_helper.php";

check_session_timeout();
require_auth(['admin', 'election_officer', 'observer']);
require_permission('view_votes', 'unauthorized.php?role=' . urlencode($_SESSION['role']));

$role = $_SESSION['role'];

$totalVotes = 0;
$totalPositions = 0;
$totalCandidates = 0;
$latestVotes = [];
$positionVotes = [];

$votesResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM votes");
if ($votesResult) {
    $totalVotes = mysqli_fetch_assoc($votesResult)['total'] ?? 0;
}

$positionResult = mysqli_query($conn, "
    SELECT c.position AS position_name, COUNT(v.id) AS vote_count
    FROM votes v
    JOIN candidates c ON v.candidate_id = c.id
    GROUP BY c.position
    ORDER BY vote_count DESC, c.position ASC
");

if ($positionResult) {
    while ($row = mysqli_fetch_assoc($positionResult)) {
        $positionVotes[] = $row;
    }
}

$candidateCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM candidates");
if ($candidateCountResult) {
    $totalCandidates = mysqli_fetch_assoc($candidateCountResult)['total'] ?? 0;
}

$positionCountResult = mysqli_query($conn, "SELECT COUNT(DISTINCT position) AS total FROM votes");
if ($positionCountResult) {
    $totalPositions = mysqli_fetch_assoc($positionCountResult)['total'] ?? 0;
}

$latestVotesResult = mysqli_query($conn, "
    SELECT v.vote_time, u.name AS voter_name, c.position
    FROM votes v
    JOIN users u ON v.user_id = u.id
    JOIN candidates c ON v.candidate_id = c.id
    ORDER BY v.vote_time DESC
    LIMIT 15
");

if ($latestVotesResult) {
    while ($row = mysqli_fetch_assoc($latestVotesResult)) {
        $latestVotes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vote Overview | Voting System</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        background: #f8fafc;
    }
    .main-content {
        margin-left: 260px;
        padding: 30px;
        min-height: 100vh;
    }
    .top-bar {
        background: white;
        padding: 24px 28px;
        border-radius: 18px;
        margin-bottom: 28px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    .top-bar h1 {
        font-size: 28px;
        color: #0f172a;
    }
    .top-bar p {
        color: #475569;
        font-size: 14px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }
    .stat-card {
        background: white;
        padding: 22px;
        border-radius: 18px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .stat-card h3 {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 14px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .stat-card p {
        font-size: 34px;
        font-weight: 700;
        color: #111827;
    }
    .section {
        margin-bottom: 28px;
    }
    .section h2 {
        font-size: 22px;
        color: #111827;
        margin-bottom: 18px;
    }
    .table-card {
        background: white;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }
    .table-card table {
        width: 100%;
        border-collapse: collapse;
    }
    .table-card th,
    .table-card td {
        padding: 18px 22px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        font-size: 14px;
        color: #334155;
    }
    .table-card th {
        background: #f1f5f9;
        color: #475569;
        font-weight: 700;
    }
    .table-card tr:last-child td {
        border-bottom: none;
    }
    .empty-state {
        background: white;
        border-radius: 18px;
        padding: 40px 32px;
        text-align: center;
        color: #64748b;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h1>Votes Overview</h1>
            <p>Track vote counts and recent voting activity for completed and ongoing elections.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Votes Cast</h3>
            <p><?php echo number_format($totalVotes); ?></p>
        </div>
        <div class="stat-card">
            <h3>Positions with Votes</h3>
            <p><?php echo number_format($totalPositions); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Candidates</h3>
            <p><?php echo number_format($totalCandidates); ?></p>
        </div>
    </div>

    <div class="section">
        <h2>Votes by Position</h2>
        <?php if (!empty($positionVotes)): ?>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Total Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positionVotes as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                        <td><?php echo number_format($row['vote_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No vote records available yet.</h3>
                <p>Votes will appear here once voters have started participating.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Recent Vote Activity</h2>
        <p style="margin-bottom: 16px; color: #475569; font-size: 14px;">Individual voter selections are anonymized for privacy; only the voter name, position, and timestamp are shown.</p>
        <?php if (!empty($latestVotes)): ?>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Voter</th>
                        <th>Position</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestVotes as $vote): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vote['voter_name']); ?></td>
                        <td><?php echo htmlspecialchars($vote['position']); ?></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($vote['vote_time'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No recent votes yet.</h3>
                <p>Vote activity will appear here after elections begin.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
    /* Affiliation Badge Styles */
