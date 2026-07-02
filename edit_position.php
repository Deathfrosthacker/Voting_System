<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./election_time_helper.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* SESSION TIMEOUT CHECK (30 minutes)*/
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// Get position ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: positions.php");
    exit();
}

$position_id = (int)$_GET['id'];

// Fetch position data using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM positions WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $position_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: positions.php?status=error");
    exit();
}

$position = mysqli_fetch_assoc($result);

// Fetch regions for dropdown
$regions = mysqli_query($conn, "SELECT id, name FROM regions ORDER BY name ASC");
if ($regions === false) {
    die("Database error fetching regions.");
}

/* ============================================================
   HANDLE UPDATE POSITION - With DATETIME + 24h Validation
   ============================================================ */
if (isset($_POST['update_position'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please try again.";
    } else {
        $name = mysqli_real_escape_string($conn, $_POST['position_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        /* FIX: Use full DATETIME instead of DATE */
        $start = $_POST['start_date']; // datetime-local format
        $end = $_POST['end_date'];     // datetime-local format
        
        // Convert to MySQL DATETIME format
        $start_mysql = date('Y-m-d H:i:s', strtotime($start));
        $end_mysql = date('Y-m-d H:i:s', strtotime($end));

        /*NEW: Handle scope and region changes */
        $scope = $_POST['scope'] ?? 'global';
        $new_region_id = ($scope === 'regional' && !empty($_POST['region_id'])) ? (int)$_POST['region_id'] : null;

        // FIX: Validate datetimes when editing
        $now = date('Y-m-d H:i:s');

        // For editing, we allow the start date to be in the past if it already was
        // But we don't allow changing it to a past date if it was future
        $orig_start = $position['start_date'];
        
        if ($start_mysql < $now && $orig_start >= $now) {
            $error_message = "Start datetime cannot be changed to a past date/time.";
        } elseif ($end_mysql < $now) {
            $error_message = "End datetime cannot be in the past.";
        } else {
            /* FIX: 24-hour minimum duration validation on edit */
            $minDuration = get_minimum_election_duration($conn);
            $durationCheck = validate_election_duration($start_mysql, $end_mysql, $minDuration);
            
            if (!$durationCheck['valid']) {
                $error_message = $durationCheck['error'];
            } else {
                /* FIX: Check for duplicate name (excluding current record)*/
                $checkDup = mysqli_prepare($conn, 
                    "SELECT id FROM positions WHERE LOWER(position_name) = LOWER(?) AND id != ? LIMIT 1"
                );
                mysqli_stmt_bind_param($checkDup, "si", $name, $position_id);
                mysqli_stmt_execute($checkDup);
                $dupResult = mysqli_stmt_get_result($checkDup);
                if (mysqli_num_rows($dupResult) > 0) {
                    $error_message = "A position with this name already exists.";
                } else {
                    /* FIX: Use prepared statement for UPDATE with DATETIME */
                    if ($new_region_id === null) {
                        $updStmt = mysqli_prepare($conn, 
                            "UPDATE positions SET position_name = ?, description = ?, start_date = ?, end_date = ?, region_id = NULL WHERE id = ?"
                        );
                        mysqli_stmt_bind_param($updStmt, "ssssi", $name, $description, $start_mysql, $end_mysql, $position_id);
                    } else {
                        $updStmt = mysqli_prepare($conn, 
                            "UPDATE positions SET position_name = ?, description = ?, start_date = ?, end_date = ?, region_id = ? WHERE id = ?"
                        );
                        mysqli_stmt_bind_param($updStmt, "ssssii", $name, $description, $start_mysql, $end_mysql, $new_region_id, $position_id);
                    }

                    if (mysqli_stmt_execute($updStmt)) {
                        // LOG ACTIVITY
                        $user_id = $_SESSION['user_id'];
                        $activity = "Updated Position: " . $name;

                        $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
                        mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
                        mysqli_stmt_execute($logStmt);

                        header("Location: positions.php?status=updated");
                        exit();
                    } else {
                        $error_message = "Error updating position. Please try again.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="positions2.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Position - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .scope-selector {
            display: flex; gap: 20px; margin-bottom: 16px;
        }
        .scope-option {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 20px; border: 2px solid #e2e8f0;
            border-radius: 8px; cursor: pointer; transition: all 0.3s;
        }
        .scope-option:hover { border-color: #2563eb; }
        .scope-option.selected { border-color: #2563eb; background: #eff6ff; }
        .scope-option input { width: 18px; height: 18px; }
        .region-select-group {
            display: none; margin-bottom: 20px;
        }
        .region-select-group.active { display: block; }
        input[type="datetime-local"] {
            font-family: inherit;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
        }
        .duration-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #166534;
        }
        .duration-info strong {
            color: #15803d;
        }
    </style>
</head>

<body>

<div class="container">
    <!-- Sidebar -->
    <?php include "./sidebar.php"; ?>

    <!-- Main Content -->
    <div class="main">
        <div class="page-header">
            <h1>Edit Position</h1>
            <p>Update position information</p>
        </div>

        <!-- Edit Position Form -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Position Details
            </h3>

            <?php if (isset($error_message)): ?>
                <!-- FIX: Escape error message to prevent XSS -->
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="editPositionForm">
                <?php echo csrf_input_field(); ?>
                <div class="form-group">
                    <label for="position_name">Position Name <span>*</span></label>
                    <input type="text" id="position_name" name="position_name" value="<?php echo htmlspecialchars($position['position_name']); ?>" placeholder="e.g., President, Vice President, Secretary" required>
                </div>

                <div class="form-group">
                    <label for="description">Description <span>*</span></label>
                    <textarea id="description" name="description" placeholder="Brief description of the position's responsibilities and requirements" required><?php echo htmlspecialchars($position['description']); ?></textarea>
                </div>

                <!-- NEW: Scope Selection -->
                <div class="form-group">
                    <label>Election Scope <span>*</span></label>
                    <div class="scope-selector">
                        <div class="scope-option <?php echo $position['region_id'] ? '' : 'selected'; ?>" onclick="selectScope('global')">
                            <input type="radio" name="scope" value="global" id="scope_global" <?php echo $position['region_id'] ? '' : 'checked'; ?>>
                            <label for="scope_global" style="margin:0;cursor:pointer;">
                                <strong>Global</strong><br>
                                <small style="color:#64748b;">All voters can see and vote</small>
                            </label>
                        </div>
                        <div class="scope-option <?php echo $position['region_id'] ? 'selected' : ''; ?>" onclick="selectScope('regional')">
                            <input type="radio" name="scope" value="regional" id="scope_regional" <?php echo $position['region_id'] ? 'checked' : ''; ?>>
                            <label for="scope_regional" style="margin:0;cursor:pointer;">
                                <strong>Region-Specific</strong><br>
                                <small style="color:#64748b;">Only voters from selected region</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- NEW: Region Selection -->
                <div class="form-group region-select-group <?php echo $position['region_id'] ? 'active' : ''; ?>" id="regionSelectGroup">
                    <label for="region_id">Select Region <span>*</span></label>
                    <select name="region_id" id="region_id">
                        <option value="">-- Select Region --</option>
                        <?php 
                        mysqli_data_seek($regions, 0);
                        while ($region = mysqli_fetch_assoc($regions)): 
                        ?>
                            <option value="<?php echo $region['id']; ?>" <?php echo ($position['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- FIX: Changed to datetime-local inputs -->
                <div class="date-inputs">
                    <div class="form-group">
                        <label for="start_date">Start Date & Time <span>*</span></label>
                        <input type="datetime-local" id="start_date" name="start_date" 
                               value="<?php echo format_for_datetime_input($position['start_date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date & Time <span>*</span></label>
                        <input type="datetime-local" id="end_date" name="end_date" 
                               value="<?php echo format_for_datetime_input($position['end_date']); ?>" required
                               min="<?php echo format_for_datetime_input(date('Y-m-d H:i:s', strtotime($position['start_date'] . ' +24 hours'))); ?>">
                    </div>
                </div>

                <!-- NEW: Duration requirement info -->
                <div class="duration-info">
                    <strong>Requirement:</strong> Elections must run for a minimum of <strong>24 hours</strong>.
                    Current duration: <strong><?php 
                        $durSecs = strtotime($position['end_date']) - strtotime($position['start_date']);
                        echo round($durSecs / 3600, 1); 
                    ?> hours</strong>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" name="update_position">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Update Position
                    </button>
                    <a href="positions.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #6b7280; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.2s;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectScope(scope) {
    document.querySelectorAll('.scope-option').forEach(opt => opt.classList.remove('selected'));
    document.getElementById('scope_' + scope).parentElement.classList.add('selected');
    document.getElementById('scope_' + scope).checked = true;

    if (scope === 'regional') {
        document.getElementById('regionSelectGroup').classList.add('active');
        document.getElementById('region_id').setAttribute('required', 'required');
    } else {
        document.getElementById('regionSelectGroup').classList.remove('active');
        document.getElementById('region_id').removeAttribute('required');
    }
}

/* NEW: Client-side 24-hour minimum duration validation */
document.getElementById('editPositionForm').addEventListener('submit', function(e) {
    const startInput = document.getElementById('start_date').value;
    const endInput = document.getElementById('end_date').value;
    
    if (!startInput || !endInput) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Missing Dates',
            text: 'Please fill in both start and end date/time.',
            confirmButtonColor: '#f59e0b'
        });
        return false;
    }
    
    const start = new Date(startInput);
    const end = new Date(endInput);
    const diffHours = (end - start) / (1000 * 60 * 60);
    
    if (diffHours < 24) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Duration Too Short',
            text: `Election duration is ${diffHours.toFixed(1)} hours. Minimum required is 24 hours.`,
            confirmButtonColor: '#ef4444'
        });
        return false;
    }
    
    return true;
});

/* NEW: Auto-adjust end date minimum when start date changes */
document.getElementById('start_date').addEventListener('change', function() {
    const start = new Date(this.value);
    if (!isNaN(start.getTime())) {
        const minEnd = new Date(start.getTime() + (24 * 60 * 60 * 1000));
        document.getElementById('end_date').min = minEnd.toISOString().slice(0, 16);
    }
});
</script>

</body>
</html>