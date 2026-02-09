<?php
session_start();
require_once "./config/connection.php";

/* =====================
   ADMIN PROTECTION
===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* =====================
   FETCH POSITIONS
===================== */
$positions = mysqli_query($conn, "
    SELECT position_name, start_date, end_date
    FROM positions
    ORDER BY start_date DESC
");
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
    <h2>📊 Votes Overview</h2>
    <p style="color:#6b7280;">Total votes and voters per position</p>

    <?php while ($pos = mysqli_fetch_assoc($positions)): ?>

        <?php
        // Fetch candidates + vote counts
        $candidates = mysqli_query($conn, "
            SELECT 
                c.id,
                c.name,
                COUNT(v.candidate_id) AS total_votes
            FROM candidates c
            LEFT JOIN votes v ON c.id = v.candidate_id
            WHERE c.position = '{$pos['position_name']}'
            GROUP BY c.id
        ");
        ?>

        <div class="position-card">

            <div class="position-header">
                <h3><?php echo htmlspecialchars($pos['position_name']); ?></h3>
                <div class="date">
                    <?php echo $pos['start_date']; ?> → <?php echo $pos['end_date']; ?>
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
                    // Fetch voters for this candidate
                    $voters = mysqli_query($conn, "
                        SELECT u.name, u.student_id, v.vote_time
                        FROM votes v
                        JOIN users u ON v.user_id = u.id
                        WHERE v.candidate_id = {$cand['id']}
                        ORDER BY v.vote_time DESC
                    ");
                    ?>

                    <tr>
                        <td><?php echo htmlspecialchars($cand['name']); ?></td>
                        <td class="count"><?php echo $cand['total_votes']; ?></td>
                        <td>
                            <?php if (mysqli_num_rows($voters) === 0): ?>
                                <em>No votes yet</em>
                            <?php else: ?>
                                <ul style="margin:0;padding-left:18px;">
                                    <?php while ($v = mysqli_fetch_assoc($voters)): ?>
                                        <li>
                                            <?php echo $v['name']; ?>
                                            (<?php echo $v['student_id']; ?>)
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
