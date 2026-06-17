<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";

/* AUTH PROTECTION */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$status  = "";

/* GET POSITION FROM URL */
if (!isset($_GET['position'])) {
    die("Invalid position");
}

$position = mysqli_real_escape_string($conn, $_GET['position']);

/*FETCH POSITION DETAILS */
$posQuery = mysqli_query($conn, "
    SELECT p.*, r.name as region_name
    FROM positions p
    LEFT JOIN regions r ON p.region_id = r.id
    WHERE p.position_name = '$position'
    LIMIT 1
");

if (mysqli_num_rows($posQuery) === 0) {
    die("Position not found");
}

$pos = mysqli_fetch_assoc($posQuery);

/* CHECK IF ALREADY VOTED */
$checkVoted = mysqli_prepare($conn, "
    SELECT v.vote_time, c.name as voted_for
    FROM votes v
    JOIN candidates c ON v.candidate_id = c.id
    WHERE v.user_id = ? AND c.position = ?
");
mysqli_stmt_bind_param($checkVoted, "is", $user_id, $position);
mysqli_stmt_execute($checkVoted);
$votedResult = mysqli_stmt_get_result($checkVoted);
$hasVoted = mysqli_num_rows($votedResult) > 0;
$voteInfo = $hasVoted ? mysqli_fetch_assoc($votedResult) : null;

/* HANDLE VOTE SUBMISSION */
if (isset($_POST['vote']) && !$hasVoted) {
    // ADDED: CSRF validation for vote submission
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $status = "error";
        $error_msg = "Invalid security token. Please refresh the page and try again.";
    } else {
        $candidate_id = (int)$_POST['candidate_id'];

        // Verify candidate exists and get position
        $verifyCand = mysqli_prepare($conn, "SELECT position FROM candidates WHERE id = ?");
        mysqli_stmt_bind_param($verifyCand, "i", $candidate_id);
        mysqli_stmt_execute($verifyCand);
        $candResult = mysqli_stmt_get_result($verifyCand);

        if (mysqli_num_rows($candResult) === 0) {
            $status = "error";
            $error_msg = "Invalid candidate selected.";
        } else {
            $candData = mysqli_fetch_assoc($candResult);

            // Verify candidate belongs to this position
            if ($candData['position'] !== $position) {
                $status = "error";
                $error_msg = "Candidate does not belong to this position.";
            } else {
                // Double-check user hasn't voted for this position
                $doubleCheck = mysqli_prepare($conn, "
                    SELECT COUNT(*) as count 
                    FROM votes v 
                    JOIN candidates c ON v.candidate_id = c.id 
                    WHERE v.user_id = ? AND c.position = ?
                ");
                mysqli_stmt_bind_param($doubleCheck, "is", $user_id, $position);
                mysqli_stmt_execute($doubleCheck);
                $checkResult = mysqli_stmt_get_result($doubleCheck);
                $checkData = mysqli_fetch_assoc($checkResult);

                if ($checkData['count'] > 0) {
                    $status = "error";
                    $error_msg = "You have already voted for this position.";
                } else {
                    // Insert vote
                    $insert = mysqli_prepare($conn, "
                        INSERT INTO votes (user_id, candidate_id, vote_time)
                        VALUES (?, ?, NOW())
                    ");
                    mysqli_stmt_bind_param($insert, "ii", $user_id, $candidate_id);

                    if (mysqli_stmt_execute($insert)) {
                        // AUDIT LOG
                        $activity = "Voted for $position";
                        $logStmt = mysqli_prepare($conn, "
                            INSERT INTO logs (user_id, activity, log_time)
                            VALUES (?, ?, NOW())
                        ");
                        mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
                        mysqli_stmt_execute($logStmt);

                        $status = "success";

                        // Refresh vote status
                        header("Location: vote.php?position=" . urlencode($position) . "&voted=1");
                        exit();
                    } else {
                        $status = "error";
                        $error_msg = "Database error: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

/* FETCH CANDIDATES with affiliation info */
$candidates = mysqli_query($conn, "
    SELECT c.id, c.name, c.is_independent, 
           a.name as affiliation_name, a.color_code
    FROM candidates c
    LEFT JOIN affiliations a ON c.affiliation_id = a.id
    WHERE c.position = '$position'
    ORDER BY c.name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vote | <?php echo htmlspecialchars($position); ?></title>
<link rel="stylesheet" href="vote_candidate.css">
<style>
    /* Affiliation Badge Styles */
    .affiliation-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
    }
    .independent-badge {
        background: #f3f4f6;
        color: #6b7280;
        border: 1px solid #e5e7eb;
    }
    .party-badge {
        border: 1px solid transparent;
    }
    .color-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .candidate-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .scope-info {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 16px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
</style>
</head>

<body>

<div class="container">

    <!-- BACK BUTTON -->
    <a href="voter_dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>

    <!-- HEADER -->
    <div class="header-card">
        <h1>
            🗳️ <?php echo htmlspecialchars($pos['position_name']); ?>
        </h1>

        <?php if (!empty($pos['description'])): ?>
        <p class="description">
            <?php echo htmlspecialchars($pos['description']); ?>
        </p>
        <?php endif; ?>

        <!-- Scope Info -->
        <?php if ($pos['region_id']): ?>
            <div class="scope-info">
                🌍 This is a <strong>region-specific</strong> election for: <?php echo htmlspecialchars($pos['region_name']); ?>
            </div>
        <?php else: ?>
            <div class="scope-info" style="background: #eff6ff; border-color: #bfdbfe; color: #1e40af;">
                🌐 This is a <strong>global</strong> election open to all voters
            </div>
        <?php endif; ?>

        <div class="date-range">
            📅 <?php echo date('M d, Y', strtotime($pos['start_date'])); ?> 
            → <?php echo date('M d, Y', strtotime($pos['end_date'])); ?>
        </div>
    </div>

    <!-- SUCCESS MESSAGE -->
    <?php if (isset($_GET['voted']) && $_GET['voted'] == 1): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✅</span>
            <div>
                <strong>Vote Submitted Successfully!</strong>
                <p style="margin-top: 4px; font-size: 14px;">Your vote has been recorded.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ALREADY VOTED ALERT -->
    <?php if ($hasVoted): ?>
        <div class="alert alert-voted">
            <span class="alert-icon">ℹ️</span>
            <div>
                <strong>You have already voted for this position</strong>
                <p style="margin-top: 4px; font-size: 14px;">
                    Voted on <?php echo date('M d, Y 	 g:i A', strtotime($voteInfo['vote_time'])); ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- ERROR MESSAGE -->
    <?php if ($status === "error"): ?>
        <div class="alert alert-error">
            <span class="alert-icon">❌</span>
            <div>
                <strong>Error submitting vote</strong>
                <p style="margin-top: 4px; font-size: 14px;">
                    <?php echo isset($error_msg) ? htmlspecialchars($error_msg) : 'Please try again or contact support.'; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- CANDIDATES LIST -->
    <div class="candidates-card">
        <h2><?php echo $hasVoted ? 'Candidates' : 'Select Your Candidate'; ?></h2>

        <?php if (mysqli_num_rows($candidates) > 0): ?>

            <?php while ($cand = mysqli_fetch_assoc($candidates)): ?>
                <?php $isVotedFor = ($hasVoted && $voteInfo['voted_for'] === $cand['name']); ?>

                <div class="candidate-item <?php echo $isVotedFor ? 'voted' : ''; ?>">
                    <div class="candidate-meta">
                        <div class="candidate-name">
                            <?php echo htmlspecialchars($cand['name']); ?>
                            <?php if ($isVotedFor): ?>
                                <span class="voted-badge">✓ Your Vote</span>
                            <?php endif; ?>
                        </div>

                        <!-- Affiliation Badge -->
                        <?php if ($cand['is_independent']): ?>
                            <span class="affiliation-badge independent-badge">
                                ⚪ Independent Candidate
                            </span>
                        <?php elseif ($cand['affiliation_name']): ?>
                            <span class="affiliation-badge party-badge" 
                                  style="background: <?php echo htmlspecialchars($cand['color_code']); ?>20; 
                                         color: <?php echo htmlspecialchars($cand['color_code']); ?>;
                                         border-color: <?php echo htmlspecialchars($cand['color_code']); ?>40;">
                                <span class="color-dot" style="background: <?php echo htmlspecialchars($cand['color_code']); ?>"></span>
                                <?php echo htmlspecialchars($cand['affiliation_name']); ?>
                            </span>
                        <?php else: ?>
                            <span class="affiliation-badge independent-badge">
                                No Affiliation
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$hasVoted): ?>
                        <form method="POST" style="margin: 0;">
                            <!-- ADDED: CSRF token field -->
                            <?php echo csrf_input_field(); ?>
                            <input type="hidden" name="candidate_id" value="<?php echo $cand['id']; ?>">
                            <button type="submit" name="vote" class="vote-btn">
                                Vote for <?php echo htmlspecialchars(explode(' ', $cand['name'])[0]); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            <?php endwhile; ?>

        <?php else: ?>
            <div class="no-candidates">
                <h3>No Candidates Available</h3>
                <p>There are no candidates for this position yet.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>