<?php
require_once "./config/connection.php";

/* =========================
   CREATE RESULTS TABLE
========================= */
$createTable = "CREATE TABLE IF NOT EXISTS election_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(255) NOT NULL,
    winner_name VARCHAR(255) NOT NULL,
    total_votes INT NOT NULL DEFAULT 0,
    end_date DATE NOT NULL,
    declared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createTable);

/* =========================
   FIND EXPIRED ELECTIONS
========================= */
$expired = mysqli_query($conn, "
    SELECT id, position_name, end_date
    FROM positions
    WHERE end_date < CURDATE()
      AND position_name NOT IN (
          SELECT position_name FROM election_results
      )
");

/* =========================
   PROCESS EACH EXPIRED
========================= */
while ($pos = mysqli_fetch_assoc($expired)) {

    $pos_id   = (int)$pos['id'];
    $pos_name = mysqli_real_escape_string($conn, $pos['position_name']);
    $end_date = $pos['end_date'];

    /* Find winner */
    $winnerQ = mysqli_query($conn, "
        SELECT c.id, c.name, COUNT(v.id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        WHERE c.position = '$pos_name'
        GROUP BY c.id
        ORDER BY vote_count DESC, c.id ASC
        LIMIT 1
    ");

    if ($winner = mysqli_fetch_assoc($winnerQ)) {
        $winner_name = mysqli_real_escape_string($conn, $winner['name']);
        $vote_count  = (int)$winner['vote_count'];

        /* Save result */
        mysqli_query($conn, "
            INSERT INTO election_results
                (position_name, winner_name, total_votes, end_date)
            VALUES
                ('$pos_name', '$winner_name', $vote_count, '$end_date')
        ");

        /* Delete votes for this position */
        mysqli_query($conn, "
            DELETE v FROM votes v
            JOIN candidates c ON v.candidate_id = c.id
            WHERE c.position = '$pos_name'
        ");

        /* Delete candidates */
        mysqli_query($conn, "
            DELETE FROM candidates WHERE position = '$pos_name'
        ");

        /* Delete position */
        mysqli_query($conn, "
            DELETE FROM positions WHERE id = $pos_id
        ");

        /* Log */
        if (isset($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
            $act = mysqli_real_escape_string($conn,
                "Auto-declared winner: $winner_name for $pos_name ($vote_count votes)"
            );
            mysqli_query($conn, "
                INSERT INTO logs (user_id, activity, log_time)
                VALUES ($uid, '$act', NOW())
            ");
        }
    }
}
