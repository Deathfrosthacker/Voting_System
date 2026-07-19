<?php

// Do not auto-start a session here; calling pages must manage role-specific session handling.
require_once "./config/connection.php";

/* Helper: log errors both to error_log and to a session flash for visibility */
function ad_log_error(string $msg): void {
    $full = "auto_declare: " . $msg;
    error_log($full);
    /* Store the last error so calling pages can surface it if desired */
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['auto_declare_error'] = $msg;
    }
}

/*    CREATE RESULTS TABLE */
$createTable = "CREATE TABLE IF NOT EXISTS election_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(255) NOT NULL,
    winner_name VARCHAR(255) NOT NULL,
    total_votes INT NOT NULL DEFAULT 0,
    end_date DATE NOT NULL,
    declared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    region_name VARCHAR(100) DEFAULT NULL,
    affiliation_name VARCHAR(100) DEFAULT NULL
)";
if (mysqli_query($conn, $createTable) === false) {
    ad_log_error("Failed to create election_results table: " . mysqli_error($conn));
}


$expired = mysqli_query($conn, "
    SELECT id, position_name, end_date
    FROM positions p
    WHERE end_date <= CURDATE()
      AND NOT EXISTS (
          SELECT 1 FROM election_results er
          WHERE er.position_name = p.position_name
            AND er.end_date = p.end_date
      )
");
if ($expired === false) {
    ad_log_error("Failed to fetch expired elections: " . mysqli_error($conn));
    exit();
}

/* PROCESS EACH EXPIRED */
while ($pos = mysqli_fetch_assoc($expired)) {

    $pos_id   = (int)$pos['id'];
    $pos_name = $pos['position_name'];
    $end_date = $pos['end_date'];

        $winnerStmt = mysqli_prepare($conn, "
        SELECT c.id, c.name, COUNT(v.candidate_id) AS vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        WHERE c.position = ?
        GROUP BY c.id, c.name
        ORDER BY vote_count DESC, c.id ASC
        LIMIT 1
    ");

        if ($winnerStmt === false) {
        ad_log_error("Failed to prepare winner query for position '$pos_name': " . mysqli_error($conn));
        continue; // Skip this position, try the next one
    }

    mysqli_stmt_bind_param($winnerStmt, "s", $pos_name);
    mysqli_stmt_execute($winnerStmt);
    $winnerQ = mysqli_stmt_get_result($winnerStmt);

    if ($winnerQ && $winner = mysqli_fetch_assoc($winnerQ)) {
        $winner_name = $winner['name'];
        $vote_count  = (int)$winner['vote_count'];

        mysqli_begin_transaction($conn);
        $txSuccess = true;

        /* Save result */
        $saveStmt = mysqli_prepare($conn,
            "INSERT INTO election_results (position_name, winner_name, total_votes, end_date) VALUES (?, ?, ?, ?)"
        );
        if ($saveStmt === false) {
            ad_log_error("Failed to prepare save statement for '$pos_name': " . mysqli_error($conn));
            mysqli_rollback($conn);
            continue;
        }

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
                if (!mysqli_stmt_execute($delVotes)) {
                    ad_log_error("Failed to delete votes for '$pos_name': " . mysqli_stmt_error($delVotes));
                    $txSuccess = false;
                }
            } else {
                ad_log_error("Failed to prepare vote deletion for '$pos_name': " . mysqli_error($conn));
                $txSuccess = false;
            }

            /* Delete candidates */
            $delCand = mysqli_prepare($conn, "DELETE FROM candidates WHERE position = ?");
            if ($delCand) {
                mysqli_stmt_bind_param($delCand, "s", $pos_name);
                if (!mysqli_stmt_execute($delCand)) {
                    ad_log_error("Failed to delete candidates for '$pos_name': " . mysqli_stmt_error($delCand));
                    $txSuccess = false;
                }
            } else {
                ad_log_error("Failed to prepare candidate deletion for '$pos_name': " . mysqli_error($conn));
                $txSuccess = false;
            }

            /* Delete position */
            $delPos = mysqli_prepare($conn, "DELETE FROM positions WHERE id = ?");
            if ($delPos) {
                mysqli_stmt_bind_param($delPos, "i", $pos_id);
                if (!mysqli_stmt_execute($delPos)) {
                    ad_log_error("Failed to delete position '$pos_name': " . mysqli_stmt_error($delPos));
                    $txSuccess = false;
                }
            } else {
                ad_log_error("Failed to prepare position deletion for '$pos_name': " . mysqli_error($conn));
                $txSuccess = false;
            }

            /* Commit or rollback based on success */
            if ($txSuccess) {
                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
                ad_log_error("Transaction rolled back for '$pos_name' -- database left unchanged.");
                continue;
            }

            /* Log */
            if (isset($_SESSION['user_id'])) {
                $uid = (int)$_SESSION['user_id'];
                $act = "Auto-declared winner: $winner_name for $pos_name ($vote_count votes)";
                $logStmt = mysqli_prepare($conn,
                    "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())"
                );
                if ($logStmt) {
                    mysqli_stmt_bind_param($logStmt, "is", $uid, $act);
                    mysqli_stmt_execute($logStmt);
                }
            }
        } else {
            ad_log_error("Failed to insert election result for '$pos_name': " . mysqli_stmt_error($saveStmt));
            mysqli_rollback($conn);
        }
    } else {
        /* No candidates/votes found for this expired position -- still clean it up */
        ad_log_error("No winner could be determined for expired position '$pos_name' (no candidates or votes).");

        mysqli_begin_transaction($conn);
        $txSuccess = true;

        $delCand = mysqli_prepare($conn, "DELETE FROM candidates WHERE position = ?");
        if ($delCand) {
            mysqli_stmt_bind_param($delCand, "s", $pos_name);
            if (!mysqli_stmt_execute($delCand)) $txSuccess = false;
        }

        $delPos = mysqli_prepare($conn, "DELETE FROM positions WHERE id = ?");
        if ($delPos) {
            mysqli_stmt_bind_param($delPos, "i", $pos_id);
            if (!mysqli_stmt_execute($delPos)) $txSuccess = false;
        }

        if ($txSuccess) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
        }
    }
}
?>