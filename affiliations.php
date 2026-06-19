<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/*    HANDLE ADD AFFILIATION */
if (isset($_POST['add_affiliation'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: affiliations.php?status=csrf_error");
        exit();
    }

    $$name = trim($_POST['affiliation_name']);
    if (!preg_match("/^[a-zA-Z0-9\s&.,'-]{3,100}$/", $name)) {
    header("Location: affiliations.php?status=invalid_name");
    exit();
}
    $description = trim($_POST['description'] ?? '');
    $color_code = trim($_POST['color_code'] ?? '#2563eb');

    // Check for duplicate name
   $check = mysqli_prepare(
    $conn,
    "SELECT id FROM affiliations WHERE name = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($check, "s", $name);
    mysqli_stmt_execute($check);

    $result = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($check);
        header("Location: affiliations.php?status=duplicate");
        exit();
    }

mysqli_stmt_close($check);

    $stmt = mysqli_prepare(
    $conn,
    "INSERT INTO affiliations
    (name, description, color_code)
    VALUES (?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $name,
    $description,
    $color_code
);

if (!mysqli_stmt_execute($stmt)) {

    if (mysqli_errno($conn) == 1062) {
        header("Location: affiliations.php?status=duplicate");
        exit();
    }

    error_log(mysqli_error($conn));

    header("Location: affiliations.php?status=error");
    exit();
}

mysqli_stmt_close($stmt);
}

/*    HANDLE DELETE AFFILIATION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_affiliation'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: affiliations.php?status=csrf_error");
        exit();
    }

    $aff_id = (int) $_POST['affiliation_id'];

    // Check if affiliation has candidates
    $checkCandidates = mysqli_query($conn, "SELECT COUNT(*) as total FROM candidates WHERE affiliation_id = '$aff_id'");
    $candidateCount = mysqli_fetch_assoc($checkCandidates)['total'];

    if ($candidateCount > 0) {
        header("Location: affiliations.php?status=in_use");
        exit();
    }

    $affName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM affiliations WHERE id = '$aff_id'"))['name'];

    $deleteStmt = mysqli_prepare(
    $conn,
    "DELETE FROM affiliations WHERE id = ?"
);

mysqli_stmt_bind_param(
    $deleteStmt,
    "i",
    $aff_id
);

if (mysqli_stmt_execute($deleteStmt)) {

    $user_id = $_SESSION['user_id'];
    $activity = "Deleted Affiliation: " . $affName;

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

    mysqli_stmt_close($deleteStmt);

    header("Location: affiliations.php?status=deleted");
    exit();

} else {

    error_log(mysqli_error($conn));

    mysqli_stmt_close($deleteStmt);

    header("Location: affiliations.php?status=error");
    exit();
}
}

/* Fetch all affiliations with candidate counts */
$affiliations = mysqli_query($conn, "
    SELECT a.*, COUNT(c.id) as candidate_count
    FROM affiliations a
    LEFT JOIN candidates c ON a.id = c.affiliation_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Affiliations - VoteSystem</title>
    <link rel="stylesheet" href="positions2.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .affiliation-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid var(--aff-color, #2563eb);
        }
        .affiliation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .affiliation-info h4 {
            font-size: 18px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }
        .affiliation-info p {
            color: #64748b;
            font-size: 13px;
        }
        .affiliation-stats {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .stat-item {
            text-align: center;
        }
        .stat-item .number {
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
        }
        .stat-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
        }
        .btn-delete-small {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .btn-delete-small:hover {
            background: #dc2626;
        }
        .color-picker-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        input[type="color"] {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="container">
    <?php include "./sidebar.php"; ?>

    <div class="main">
        <div class="page-header">
            <h1>Manage Affiliations</h1>
            <p>Create and manage institutional groups (schools, departments, clubs, parties)</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Affiliations</h4>
                <div class="value"><?php echo mysqli_num_rows($affiliations); ?></div>
                <div class="label">Active affiliations in system</div>
            </div>
        </div>

        <!-- Add Affiliation Form -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Affiliation
            </h3>
            <form method="POST">
                <?php echo csrf_input_field(); ?>
                <div class="form-group">
                    <label for="affiliation_name">Affiliation Name <span>*</span></label>
                    <input type="text" id="affiliation_name" name="affiliation_name" 
                           placeholder="e.g., School of Computing, Business Club, Engineering Dept" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" 
                           placeholder="Brief description of this group">
                </div>
                <div class="form-group">
                    <label for="color_code">Color Code</label>
                    <div class="color-picker-wrapper">
                        <input type="color" id="color_code" name="color_code" value="#2563eb">
                        <span style="color: #64748b; font-size: 14px;">Used for badges and visual identification</span>
                    </div>
                </div>
                <button type="submit" name="add_affiliation">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Affiliation
                </button>
            </form>
        </div>

        <!-- Affiliations List -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                All Affiliations
            </h3>

            <?php 
            mysqli_data_seek($affiliations, 0);
            if(mysqli_num_rows($affiliations) > 0): 
                while($row = mysqli_fetch_assoc($affiliations)): 
            ?>
            <div class="affiliation-card" style="--aff-color: <?php echo htmlspecialchars($row['color_code']); ?>">
                <div class="affiliation-info">
                    <h4>
                        <span class="color-dot" style="background-color: <?php echo htmlspecialchars($row['color_code']); ?>"></span>
                        <?php echo htmlspecialchars($row['name']); ?>
                        <?php if ($row['name'] === 'Independent'): ?>
                            <span style="font-size: 11px; background: #f3f4f6; padding: 2px 8px; border-radius: 12px; color: #6b7280;">Default</span>
                        <?php endif; ?>
                    </h4>
                    <p><?php echo htmlspecialchars($row['description'] ?: 'No description'); ?></p>
                </div>
                <div class="affiliation-stats">
                    <div class="stat-item">
                        <div class="number"><?php echo $row['candidate_count']; ?></div>
                        <div class="label">Candidates</div>
                    </div>
                    <?php if ($row['candidate_count'] == 0 && $row['name'] !== 'Independent'): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this affiliation?');">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="affiliation_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_affiliation" class="btn-delete-small">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
                endwhile; 
            else: 
            ?>
            <div class="empty-state">
                <p>No affiliations added yet. Create your first affiliation above.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
<script>
<?php if ($_GET['status'] === "success"): ?>
    Swal.fire({
        icon: 'success', title: 'Success!',
        text: 'Affiliation has been created successfully',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php elseif ($_GET['status'] === "deleted"): ?>
    Swal.fire({
        icon: 'success', title: 'Deleted!',
        text: 'Affiliation has been deleted successfully',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php elseif ($_GET['status'] === "duplicate"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate!',
        text: 'An affiliation with this name already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php elseif ($_GET['status'] === "in_use"): ?>
    Swal.fire({
        icon: 'error', title: 'Cannot Delete!',
        text: 'This affiliation has candidates assigned to it.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error', title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error', title: 'Error!',
        text: 'Something went wrong. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'affiliations.php'); });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>