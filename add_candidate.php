<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./election_time_helper.php";

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* SESSION TIMEOUT CHECK (30 minutes) */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

$candidates = mysqli_query(
    $conn,
    "SELECT c.*, a.name as affiliation_name, a.color_code 
     FROM candidates c 
     LEFT JOIN affiliations a ON c.affiliation_id = a.id 
     ORDER BY c.start_date DESC"
);
if ($candidates === false) {
    die("Database error fetching candidates.");
}

// Fetch positions
$positions = mysqli_query(
    $conn,
    "SELECT * FROM positions ORDER BY start_date DESC"
);
if ($positions === false) {
    die("Database error fetching positions.");
}

// Fetch affiliations for dropdown
$affiliations = mysqli_query($conn, "SELECT id, name, color_code FROM affiliations ORDER BY name ASC");
if ($affiliations === false) {
    die("Database error fetching affiliations.");
}

// Handle candidate insert
if (isset($_POST['add_candidate'])) {
    //ADDED: CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        /* FIX: Use GET-based redirect for CSRF errors so popup will show */
        header("Location: add_candidate.php?status=csrf_error");
        exit();
    }

    $candidate_name = mysqli_real_escape_string($conn, $_POST['candidate_name']);
    $position       = $_POST['position_name'];
    
    /* FIX: Use DATETIME values from the position */
    $start_date = $_POST['start_date']; // Already in MySQL DATETIME format from form
    $end_date   = $_POST['end_date'];   // Already in MySQL DATETIME format from form

    // Validate position exists and check its duration
    $checkPos = mysqli_prepare($conn, "SELECT start_date, end_date FROM positions WHERE position_name = ? LIMIT 1");
    mysqli_stmt_bind_param($checkPos, "s", $position);
    mysqli_stmt_execute($checkPos);
    $checkPosResult = mysqli_stmt_get_result($checkPos);
    
    if ($checkPosResult === false || mysqli_num_rows($checkPosResult) == 0) {
        header("Location: add_candidate.php?status=invalid_position");
        exit();
    }
    
    $posData = mysqli_fetch_assoc($checkPosResult);
    
    /* FIX: Validate election duration meets 24h minimum */
    $minDuration = get_minimum_election_duration($conn);
    $durationCheck = validate_election_duration($posData['start_date'], $posData['end_date'], $minDuration);
    
    if (!$durationCheck['valid']) {
        header("Location: add_candidate.php?status=invalid_election_duration&error=" . urlencode($durationCheck['error']));
        exit();
    }

    $is_independent = isset($_POST['is_independent']) ? 1 : 0;
    $affiliation_id = !$is_independent && !empty($_POST['affiliation_id']) ? (int)$_POST['affiliation_id'] : null;

    // Candidate name validation
    if (!preg_match("/^[a-zA-Z\s.'-]{3,100}$/", $candidate_name)) {
        header("Location: add_candidate.php?status=invalid_name");
        exit();
    }

    // FIX 2: Check for similar candidate names using prepared statement (case-insensitive, fuzzy matching)
    $normalized_input = strtolower(trim($candidate_name));
    $normalized_input = preg_replace('/[^a-z0-9]/', '', $normalized_input);

    /* FIX: Use prepared statement to fetch existing candidates for this position */
    $checkStmt = mysqli_prepare($conn, "SELECT name FROM candidates WHERE position = ?");
    mysqli_stmt_bind_param($checkStmt, "s", $position);
    mysqli_stmt_execute($checkStmt);
    $check_similar = mysqli_stmt_get_result($checkStmt);
    
    $similar_found = false;
    $similar_name = "";

    if ($check_similar !== false) {
        while ($existing = mysqli_fetch_assoc($check_similar)) {
            $existing_normalized = strtolower(trim($existing['name']));
            $existing_normalized = preg_replace('/[^a-z0-9]/', '', $existing_normalized);

            // Check if names are identical (exact match)
            if ($normalized_input === $existing_normalized) {
                $similar_found = true;
                $similar_name = $existing['name'];
                break;
            }

            // Check similarity with INCREASED threshold (90% instead of 80%)
            similar_text($normalized_input, $existing_normalized, $percent);
            if ($percent >= 90) {
                $similar_found = true;
                $similar_name = $existing['name'];
                break;
            }
        }
    }

    if ($similar_found) {
        header("Location: add_candidate.php?status=similar_name&similar=" . urlencode($similar_name));
        exit();
    }

    /* FIX 3: Use prepared statement for INSERT with DATETIME */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO candidates
        (name, position, start_date, end_date, is_independent, affiliation_id)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    if ($stmt === false) {
        header("Location: add_candidate.php?status=error");
        exit();
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssssii",
        $candidate_name,
        $position,
        $start_date,
        $end_date,
        $is_independent,
        $affiliation_id
    );

    if (!mysqli_stmt_execute($stmt)) {

        if (mysqli_errno($conn) == 1062) {
            header(
                "Location: add_candidate.php?status=duplicate_candidate"
            );
            exit();
        }

        error_log(mysqli_error($conn));

        header(
            "Location: add_candidate.php?status=error"
        );
        exit();
    }

    /* Candidate inserted successfully */

    // Log activity
    $user_id = $_SESSION['user_id'];
    $activity = "Added Candidate";

    $logStmt = mysqli_prepare(
        $conn,
        "INSERT INTO logs (user_id, activity, log_time)
         VALUES (?, ?, NOW())"
    );

    if ($logStmt) {
        mysqli_stmt_bind_param(
            $logStmt,
            "is",
            $user_id,
            $activity
        );

        mysqli_stmt_execute($logStmt);
        mysqli_stmt_close($logStmt);
    }

    mysqli_stmt_close($stmt);
    /* FIX: Redirect with success status so popup shows via $_GET */
    header("Location: add_candidate.php?status=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Candidate - VoteSystem</title>
    <link rel="stylesheet" href="style3.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .affiliation-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .independent-badge {
            background: #f3f4f6;
            color: #6b7280;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .checkbox-group label {
            cursor: pointer;
            font-weight: 500;
        }
        .affiliation-select {
            transition: all 0.3s ease;
        }
        .affiliation-select.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        /* FIX: Duration warning styling */
        .duration-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 8px;
            font-size: 12px;
            color: #92400e;
            display: none;
        }
        .duration-warning.show {
            display: block;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Include Sidebar -->
    <?php include './sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <div class="page-header">
            <h1>Add Candidate</h1>
            <p>Select a position and add candidates to the voting system</p>
        </div>

        <!-- Positions Grid -->
        <div class="card">
            <h3>Available Positions</h3>
            <div class="positions-grid">
                <?php while ($pos = mysqli_fetch_assoc($positions)): ?>
                    <!-- FIX: Calculate duration for display -->
                    <?php 
                        $durSecs = strtotime($pos['end_date']) - strtotime($pos['start_date']);
                        $durHours = round($durSecs / 3600, 1);
                        $isValidDur = $durSecs >= 86400;
                    ?>
                    <div class="position-card <?php echo !$isValidDur ? 'invalid-duration' : ''; ?>" 
                         onclick="<?php echo $isValidDur ? "openForm('" . htmlspecialchars($pos['position_name'], ENT_QUOTES) . "', '" . $pos['start_date'] . "', '" . $pos['end_date'] . "')" : "showDurationError('$durHours')"; ?>">
                        <h4><?php echo htmlspecialchars($pos['position_name']); ?></h4>
                        <p class="description"><?php echo htmlspecialchars($pos['description']); ?></p>
                        <div class="position-dates">
                            <div class="date-item">
                                <span>Start: <?php echo format_election_datetime($pos['start_date']); ?></span>
                            </div>
                            <div class="date-item">
                                <span>End: <?php echo format_election_datetime($pos['end_date']); ?></span>
                            </div>
                        </div>
                        <!-- FIX: Show duration badge -->
                        <div class="duration-badge" style="
                            margin-top: 8px; padding: 4px 10px; border-radius: 12px; 
                            font-size: 11px; font-weight: 600; display: inline-block;
                            background: <?php echo $isValidDur ? '#d1fae5' : '#fee2e2'; ?>;
                            color: <?php echo $isValidDur ? '#065f46' : '#991b1b'; ?>;
                        ">
                            <?php echo $isValidDur ? '&#9989;' : '&#9888;&#65039;'; ?> 
                            <?php echo $durHours; ?> hours
                            <?php if (!$isValidDur): ?>- Invalid (min 24h)<?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <!-- FIX: Duration warning popup -->
            <div class="duration-warning" id="durationWarning">
                This election's duration is too short. Cannot add candidates until the position meets the 24-hour minimum requirement.
            </div>
        </div>

        <!-- Candidate Form -->
        <div class="form-box" id="candidateForm">
            <h3 id="formTitle">Add Candidate</h3>
            <form method="POST">
                <!-- ADDED: CSRF token field -->
                <?php echo csrf_input_field(); ?>

                <input type="hidden" name="position_name" id="position_name">
                <input type="hidden" name="start_date" id="start_date">
                <input type="hidden" name="end_date" id="end_date">

                <div class="form-group">
                    <label for="candidate_name">Candidate Name</label>
                    <input type="text" name="candidate_name" id="candidate_name" required placeholder="Enter candidate full name">
                </div>

                <!-- NEW: Independent Candidate Checkbox (IEBC Independent Candidate equivalent) -->
                <div class="checkbox-group">
                    <input type="checkbox" id="is_independent" name="is_independent" onchange="toggleAffiliation()">
                    <label for="is_independent">Independent Candidate (no affiliation)</label>
                </div>

                <!-- NEW: Affiliation Dropdown (IEBC Political Party equivalent) -->
                <div class="form-group affiliation-select" id="affiliationGroup">
                    <label for="affiliation_id">Affiliation / Group / Party</label>
                    <select name="affiliation_id" id="affiliation_id">
                        <option value="">-- Select Affiliation --</option>
                        <?php 
                        mysqli_data_seek($affiliations, 0);
                        while ($aff = mysqli_fetch_assoc($affiliations)): 
                        ?>
                            <option value="<?php echo $aff['id']; ?>">
                                <?php echo htmlspecialchars($aff['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" name="add_candidate" class="btn-primary">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Candidate
                </button>
            </form>
        </div>

        <!-- All Candidates Table -->
        <div class="card">
            <h3>All Candidates</h3>
            <table>
                <thead>
                    <tr>
                        <th>Candidate Name</th>
                        <th>Position</th>
                        <th>Affiliation</th>
                        <th>Starts</th>
                        <th>Ends</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($candidates)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['position']); ?></td>
                        <td>
                            <?php if ($row['is_independent']): ?>
                                <span class="affiliation-badge independent-badge">Independent</span>
                            <?php elseif ($row['affiliation_name']): ?>
                                <span class="affiliation-badge" style="background: <?php echo htmlspecialchars($row['color_code']); ?>20; color: <?php echo htmlspecialchars($row['color_code']); ?>">
                                    <span class="color-dot" style="width:8px;height:8px;border-radius:50%;background:<?php echo htmlspecialchars($row['color_code']); ?>;display:inline-block;"></span>
                                    <?php echo htmlspecialchars($row['affiliation_name']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <!-- FIX: Display full datetime -->
                        <td><?php echo format_election_datetime($row['start_date']); ?></td>
                        <td><?php echo format_election_datetime($row['end_date']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function openForm(position, start, end) {
    const form = document.getElementById("candidateForm");
    form.classList.add('active');
    form.style.display = "block";

    document.getElementById("formTitle").innerText = "Add Candidate for " + position;
    document.getElementById("position_name").value = position;
    document.getElementById("start_date").value = start;
    document.getElementById("end_date").value = end;

    // Scroll to form smoothly
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function toggleAffiliation() {
    const isIndependent = document.getElementById('is_independent').checked;
    const affiliationGroup = document.getElementById('affiliationGroup');
    const affiliationSelect = document.getElementById('affiliation_id');

    if (isIndependent) {
        affiliationGroup.classList.add('disabled');
        affiliationSelect.value = '';
    } else {
        affiliationGroup.classList.remove('disabled');
    }
}

/* NEW: Show duration error for invalid elections */
function showDurationError(hours) {
    const warning = document.getElementById('durationWarning');
    warning.innerHTML = '<strong>Cannot add candidate:</strong> This election only runs for ' + hours + ' hours. Minimum required is 24 hours. Please edit the position to extend the duration.';
    warning.classList.add('show');
    setTimeout(() => warning.classList.remove('show'), 5000);
}
</script>

<!-- FIX: Changed from PHP /* comment to proper HTML comment -->
<!-- Completely rewritten popup system - now checks $_GET['status'] for ALL status types -->
<?php if (isset($_GET['status'])): ?>
<script>
<?php if ($_GET['status'] === "success"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Candidate added successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.location.href = "add_candidate.php";
    });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    });
<?php elseif ($_GET['status'] === "invalid_position"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Invalid Position!',
        text: 'The selected position does not exist in the system.',
        confirmButtonColor: '#ef4444'
    });
<?php elseif ($_GET['status'] === "invalid_election_duration"): ?>
    /* NEW: Handler for election duration too short */
    Swal.fire({
        icon: 'warning',
        title: 'Invalid Election Duration!',
        text: '<?php echo htmlspecialchars(urldecode($_GET['error'] ?? 'This election does not meet the 24-hour minimum duration.'), ENT_QUOTES); ?>',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'add_candidate.php');
    });
<?php elseif ($_GET['status'] === "invalid_name"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Invalid Candidate Name!',
        text: 'Name must be 3-100 characters and contain only letters, spaces, and basic punctuation.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'add_candidate.php');
    });
<?php elseif ($_GET['status'] === "duplicate_candidate"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Duplicate Candidate!',
        text: 'This candidate already exists for the selected position.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'add_candidate.php');
    });
<?php elseif ($_GET['status'] === "similar_name"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Similar Name Found!',
        text: 'A candidate with a similar name ("<?php echo htmlspecialchars($_GET['similar'] ?? '', ENT_QUOTES); ?>") already exists for this position. Please use a different name.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'add_candidate.php');
    });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Could not add candidate. Please try again.',
        confirmButtonColor: '#ef4444'
    });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>