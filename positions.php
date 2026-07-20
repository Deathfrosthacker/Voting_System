<?php
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

// RBAC: Only admin and election_officer can manage positions
check_session_timeout();
require_auth(['admin', 'election_officer']);

/* SESSION TIMEOUT CHECK (30 minutes) */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

/*HANDLE ADD POSITION */
if (isset($_POST['add_position'])) {

    //ADDED: CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: positions.php?status=csrf_error");
        exit();
    }

    $name  = trim($_POST['position_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';

    // NEW: Region scope
    $scope = $_POST['scope'] ?? 'global';
    $region_id = ($scope === 'regional' && !empty($_POST['region_id'])) ? (int)$_POST['region_id'] : null;

    // Validate dates - prevent past dates
    $today = date('Y-m-d');

    if ($start < $today) {
        header("Location: positions.php?status=past_start_date");
        exit();
    }

    if ($end < $today) {
        header("Location: positions.php?status=past_end_date");
        exit();
    }

    if ($end < $start) {
        header("Location: positions.php?status=invalid_date_range");
        exit();
    }

    /* Check for duplicate position name (case-insensitive) */
    $checkDup = mysqli_prepare($conn, "SELECT id FROM positions WHERE LOWER(position_name) = LOWER(?) LIMIT 1");
    mysqli_stmt_bind_param($checkDup, "s", $name);
    mysqli_stmt_execute($checkDup);
    $dupResult = mysqli_stmt_get_result($checkDup);
    if (mysqli_num_rows($dupResult) > 0) {
        header("Location: positions.php?status=duplicate_name");
        exit();
    }

    if ($region_id === null) {
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO positions (position_name, description, start_date, end_date, region_id) VALUES (?, ?, ?, ?, NULL)"
        );
        mysqli_stmt_bind_param($stmt, "ssss", $name, $description, $start, $end);
    } else {
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO positions (position_name, description, start_date, end_date, region_id) VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssssi", $name, $description, $start, $end, $region_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        // LOG ACTIVITY using prepared statement
        $user_id  = $_SESSION['user_id'];
        $activity = "Made Position";

        $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
        mysqli_stmt_execute($logStmt);

        //REDIRECT to prevent form resubmission
        header("Location: positions.php?status=success");
        exit();

    } else {
        //REDIRECT with error
        header("Location: positions.php?status=error");
        exit();
    }
}

/* Fetch all positions with region info */
$positions = mysqli_query(
    $conn,
    "SELECT p.*, r.name as region_name 
     FROM positions p 
     LEFT JOIN regions r ON p.region_id = r.id 
     ORDER BY p.start_date DESC"
);
if ($positions === false) {
    die("Database error fetching positions.");
}

/* Fetch regions for dropdown */
$regions = mysqli_query($conn, "SELECT id, name FROM regions ORDER BY name ASC");
if ($regions === false) {
    die("Database error fetching regions.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="positions2.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .scope-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
        }
        .scope-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .scope-option:hover {
            border-color: #2563eb;
        }
        .scope-option.selected {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .scope-option input {
            width: 18px;
            height: 18px;
        }
        .region-select-group {
            display: none;
            margin-bottom: 20px;
        }
        .region-select-group.active {
            display: block;
        }
        .scope-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .scope-global {
            background: #dbeafe;
            color: #1e40af;
        }
        .scope-regional {
            background: #d1fae5;
            color: #065f46;
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
            <h1>Manage Positions</h1>
            <p>Create and manage voting positions for elections</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Positions</h4>
                <div class="value"><?php echo mysqli_num_rows($positions); ?></div>
                <div class="label">Active positions in system</div>
            </div>
        </div>

        <!-- Add Position Form -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Position
            </h3>

            <form method="POST">
                <!--ADDED: CSRF token field -->
                <?php echo csrf_input_field(); ?>

                <div class="form-group">
                    <label for="position_name">Position Name <span>*</span></label>
                    <input type="text" id="position_name" name="position_name" placeholder="e.g., President, Vice President, Secretary" required>
                </div>

                <div class="form-group">
                    <label for="description">Description <span>*</span></label>
                    <textarea id="description" name="description" placeholder="Brief description of the position's responsibilities and requirements" required></textarea>
                </div>

                <!-- NEW: Scope Selection -->
                <div class="form-group">
                    <label>Election Scope <span>*</span></label>
                    <div class="scope-selector">
                        <div class="scope-option selected" onclick="selectScope('global')">
                            <input type="radio" name="scope" value="global" id="scope_global" checked>
                            <label for="scope_global" style="margin:0;cursor:pointer;">
                                <strong>Global</strong><br>
                                <small style="color:#64748b;">All voters can see and vote</small>
                            </label>
                        </div>
                        <div class="scope-option" onclick="selectScope('regional')">
                            <input type="radio" name="scope" value="regional" id="scope_regional">
                            <label for="scope_regional" style="margin:0;cursor:pointer;">
                                <strong>Region-Specific</strong><br>
                                <small style="color:#64748b;">Only voters from selected region</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- NEW: Region Selection (shown only when regional selected) -->
                <div class="form-group region-select-group" id="regionSelectGroup">
                    <label for="region_id">Select Region <span>*</span></label>
                    <select name="region_id" id="region_id">
                        <option value="">-- Select Region --</option>
                        <?php while ($region = mysqli_fetch_assoc($regions)): ?>
                            <option value="<?php echo $region['id']; ?>">
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="date-inputs">
                    <div class="form-group">
                        <label for="start_date">Start Date <span>*</span></label>
                        <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date <span>*</span></label>
                        <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" name="add_position">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Position
                </button>
            </form>
        </div>

        <!-- Positions Table -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                All Positions
            </h3>

            <?php 
            mysqli_data_seek($positions, 0); // Reset pointer
            if(mysqli_num_rows($positions) > 0): 
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Description</th>
                            <th>Scope</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($positions)): ?>
                        <tr>
                            <td class="position-name"><?php echo htmlspecialchars($row['position_name']); ?></td>
                            <td class="description-cell"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td>
                                <?php if ($row['region_id']): ?>
                                    <span class="scope-badge scope-regional">
                                        &#127757; <?php echo htmlspecialchars($row['region_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="scope-badge scope-global">
                                        &#127760; Global
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="date-badge">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?php echo date('M d, Y', strtotime($row['start_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-badge">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?php echo date('M d, Y', strtotime($row['end_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_position.php?id=<?php echo $row['id']; ?>" class="btn-edit" title="Edit">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['position_name'], ENT_QUOTES); ?>')" class="btn-delete" title="Delete">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p>No positions added yet. Create your first position above.</p>
            </div>
            <?php endif; ?>
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

function confirmDelete(id, positionName) {
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete: <strong>${positionName}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_position.php';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generate_csrf_token(); ?>';
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php if (isset($_GET['status'])): ?>
<script>
<?php if ($_GET['status'] === "success"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Position has been created successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Could not add position. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "updated"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Updated!',
        text: 'Position has been updated successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "deleted"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Deleted!',
        text: 'Position has been deleted successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "past_start_date"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Invalid Start Date!',
        text: 'Start date cannot be in the past. Please select today or a future date.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "past_end_date"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Invalid End Date!',
        text: 'End date cannot be in the past. Please select today or a future date.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "invalid_date_range"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Invalid Date Range!',
        text: 'End date must be after or equal to the start date.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php elseif ($_GET['status'] === "duplicate_name"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Duplicate Position Name!',
        text: 'A position with this name already exists. Please use a different name.',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.replaceState({}, document.title, 'positions.php');
    });
<?php endif; ?>
</script>
<?php endif; ?>

<style>
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-edit,
.btn-delete {
    padding: 6px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-edit {
    background: #3b82f6;
    color: white;
    text-decoration: none;
}

.btn-edit:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-edit svg,
.btn-delete svg {
    width: 18px;
    height: 18px;
}
</style>

</body>
</html>