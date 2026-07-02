<?php
// FIXED: Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "./config/connection.php";
require_once "./election_time_helper.php";

/* FIX: Removed session timeout check that was blocking auto-declaration.
   The auto-declare engine must run regardless of session state 
   since it processes expired elections automatically. */

/*    CREATE RESULTS TABLE */
$createTable = "CREATE TABLE IF NOT EXISTS election_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(255) NOT NULL,
    winner_name VARCHAR(255) NOT NULL,
    total_votes INT NOT NULL DEFAULT 0,
    end_date DATETIME NOT NULL,
    declared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (mysqli_query($conn, $createTable) === false) {
    error_log("auto_declare: Failed to create election_results table: " . mysqli_error($conn));
}

/* ============================================================
   FIX: Find expired elections using NOW() instead of CURDATE()
   
   Previously: end_date < CURDATE() (would expire at midnight)
   Now: end_date < NOW() (expires at the exact end datetime)
   
   This ensures elections run their full course until the exact
   end time, not just until the end of the start day.
   ============================================================ */
$expired = mysqli_query($conn, "
    SELECT id, position_name, end_date
    FROM positions
    WHERE end_date < NOW()
      AND position_name NOT IN (
          SELECT position_name FROM election_results
      )
");
if ($expired === false) {
    error_log("auto_declare: Failed to fetch expired elections: " . mysqli_error($conn));
    exit();
}

/*    PROCESS EACH EXPIRED */
while ($pos = mysqli_fetch_assoc($expired)) {

    $pos_id   = (int)$pos['id'];
    $pos_name = $pos['position_name'];
    $end_date = $pos['end_date'];

    /* Find winner using prepared statement */
    $winnerStmt = mysqli_prepare($conn, "
        SELECT c.id, c.name, COUNT(v.id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        WHERE c.position = ?
        GROUP BY c.id
        ORDER BY vote_count DESC, c.id ASC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($winnerStmt, "s", $pos_name);
    mysqli_stmt_execute($winnerStmt);
    $winnerQ = mysqli_stmt_get_result($winnerStmt);

    if ($winnerQ && $winner = mysqli_fetch_assoc($winnerQ)) {
        $winner_name = $winner['name'];
        $vote_count  = (int)$winner['vote_count'];

        /* Save result using prepared statement - stores full DATETIME */
        $saveStmt = mysqli_prepare($conn, 
            "INSERT INTO election_results (position_name, winner_name, total_votes, end_date) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($saveStmt, "ssis", $pos_name, $winner_name, $vote_count, $end_date);

        if (mysqli_stmt_execute($saveStmt)) {
            /* Delete votes for this position */
            $delVotes = mysqli_prepare($conn, "
                DELETE v FROM votes v
                JOIN candidates c ON v.candidate_id = c.id
                WHERE c.position = ?
            ");
            if ($delVotes) {
                mysqli_stmt_bind_param($delVotes, "s", $pos_name);
                mysqli_stmt_execute($delVotes);
            }

            /* Delete candidates */
            $delCand = mysqli_prepare($conn, "DELETE FROM candidates WHERE position = ?");
            if ($delCand) {
                mysqli_stmt_bind_param($delCand, "s", $pos_name);
                mysqli_stmt_execute($delCand);
            }

            /* Delete position */
            $delPos = mysqli_prepare($conn, "DELETE FROM positions WHERE id = ?");
            if ($delPos) {
                mysqli_stmt_bind_param($delPos, "i", $pos_id);
                mysqli_stmt_execute($delPos);
            }

            /* Log */
            if (isset($_SESSION['user_id'])) {
                $uid = (int)$SESSION['user_id'];
                $act = "Auto-declared winner: $winner_name for $pos_name ($vote_count votes)";
                $logStmt = mysqli_prepare($conn, 
                    "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())"
                );
                if ($logStmt) {
                    mysqli_stmt_bind_param($logStmt, "is", $uid, $act);
                    mysqli_stmt_execute($logStmt);
                }
            }
        }
    }
}
?>