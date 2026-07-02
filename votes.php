<?php
session_start();
require_once "./config/connection.php";
require_once "./election_time_helper.php";

/* SESSION TIMEOUT CHECK (30 minutes)*/
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

/*  ADMIN PROTECTION */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/*    FETCH POSITIONS */
$positions = mysqli_query($conn, "
    SELECT position_name, start_date, end_date
    FROM positions
    ORDER BY start_date DESC
");
if ($positions === false) {
    die("Database error fetching positions.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="votes.css">
<title>Votes | Admin</title>
</head>

<body>

<!-- SIDEBAR -->
<?php include "sidebar.php"; ?>

<div class="main">
    <h2>&#128202; Votes Overview</h2>
    <p style="color:#6b7280;">Total votes and vote timestamps per position (voter identities protected)</p>

    <?php while ($pos = mysqli_fetch_assoc($positions)): ?>

        <?php
        /*FIX: Use prepared statement to prevent SQL injection */
        $pos_name = $pos['position_name'];
        $candStmt = mysqli_prepare($conn, "
            SELECT c.id, c.name, COUNT(v.candidate_id) AS total_votes
            FROM candidates c
            LEFT JOIN votes v ON c.id = v.candidate_id
            WHERE c.position = ?
            GROUP BY c.id
        ");
        mysqli_stmt_bind_param($candStmt, "s", $pos_name);
        mysqli_stmt_execute($candStmt);
        $candidates = mysqli_stmt_get_result($candStmt);
        ?>

        <div class="position-card">

            <div class="position-header">
                <h3><?php echo htmlspecialchars($pos['position_name']); ?></h3>
                <div class="date">
                    <!-- FIX: Display full datetime -->
                    <?php echo format_election_datetime($pos['start_date']); ?> &rarr; <?php echo format_election_datetime($pos['end_date']); ?>
                </div>
            </div>

            <table>
                <tr>
                    <th>Candidate</th>
                    <th>Total Votes</th>
                    <th>Voters</th>
                </tr>

                <?php while ($cand = mysqli_fetch_assoc($candidates)): ?>

                    <?php
                    // Fetch anonymous vote timestamps using prepared statement
                    $voteStmt = mysqli_prepare($conn, "
                        SELECT v.vote_time
                        FROM votes v
                        WHERE v.candidate_id = ?
                        ORDER BY v.vote_time DESC
                    ");
                    mysqli_stmt_bind_param($voteStmt, "i", $cand['id']);
                    mysqli_stmt_execute($voteStmt);
                    $voters = mysqli_stmt_get_result($voteStmt);
                    ?>

                    <tr>
                        <td><?php echo htmlspecialchars($cand['name']); ?></td>
                        <td class="count"><?php echo (int)$cand['total_votes']; ?></td>
                        <td>
                            <?php if (mysqli_num_rows($voters) === 0): ?>
                                <em>No votes yet</em>
                            <?php else: ?>
                                <ul style="margin:0;padding-left:18px;">
                                    <?php while ($v = mysqli_fetch_assoc($voters)): ?>
                                        <li>
                                            Anonymous Voter &mdash; <?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($v['vote_time']))); ?>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            </table>

        </div>

    <?php endwhile; ?>

</div>

</body>
</html>