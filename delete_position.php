<?php
// Session is started by check_session_timeout() in rbac_helper.php
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

/* RBAC: Only admin and election_officer can delete positions */
check_session_timeout();
require_auth(['admin', 'election_officer']);

/* SESSION TIMEOUT CHECK (30 minutes)*/
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// CHANGED: Accept POST only, reject GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || empty($_POST['id'])) {
    header("Location: positions.php?status=error");
    exit();
}

// ADDED: CSRF validation for deletion
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: positions.php?status=csrf_error");
    exit();
}

$position_id = (int)$_POST['id'];

// First, get the position name for logging
$stmt = mysqli_prepare($conn, "SELECT position_name FROM positions WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $position_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: positions.php?status=error");
    exit();
}

$position = mysqli_fetch_assoc($result);
$position_name = $position['position_name'];

/*FIX: Cascade delete - remove related candidates and votes first */

// 1. Delete votes for candidates of this position
$delVotes = mysqli_prepare($conn, 
    "DELETE v FROM votes v 
     JOIN candidates c ON v.candidate_id = c.id 
     WHERE c.position = ?"
);
if ($delVotes) {
    mysqli_stmt_bind_param($delVotes, "s", $position_name);
    mysqli_stmt_execute($delVotes);
}

// 2. Delete candidates for this position
$delCandidates = mysqli_prepare($conn, "DELETE FROM candidates WHERE position = ?");
if ($delCandidates) {
    mysqli_stmt_bind_param($delCandidates, "s", $position_name);
    mysqli_stmt_execute($delCandidates);
}

// 3. Delete the position
$delPos = mysqli_prepare($conn, "DELETE FROM positions WHERE id = ?");
mysqli_stmt_bind_param($delPos, "i", $position_id);

if (mysqli_stmt_execute($delPos)) {
    // LOG ACTIVITY
    $user_id = $_SESSION['user_id'];
    $activity = "Deleted Position: " . $position_name;

    $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
    mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
    mysqli_stmt_execute($logStmt);

    header("Location: positions.php?status=deleted");
    exit();

} else {
    header("Location: positions.php?status=error");
    exit();
}
?>