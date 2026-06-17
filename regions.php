<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/*    HANDLE ADD REGION */
if (isset($_POST['add_region'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: regions.php?status=csrf_error");
        exit();
    }

    $name = mysqli_real_escape_string($conn, $_POST['region_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');

    // Check for duplicate region name
    $check = mysqli_query($conn, "SELECT id FROM regions WHERE name = '$name'");
    if (mysqli_num_rows($check) > 0) {
        header("Location: regions.php?status=duplicate");
        exit();
    }

    $sql = "INSERT INTO regions (name, description) VALUES ('$name', '$description')";
    if (mysqli_query($conn, $sql)) {
        $user_id = $_SESSION['user_id'];
        $activity = "Added Region: " . $name;
        mysqli_query($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES ('$user_id', '$activity', NOW())");
        header("Location: regions.php?status=success");
        exit();
    } else {
        header("Location: regions.php?status=error");
        exit();
    }
}

/*    HANDLE DELETE REGION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_region'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: regions.php?status=csrf_error");
        exit();
    }

    $region_id = mysqli_real_escape_string($conn, $_POST['region_id']);

    // Check if region has voters or positions assigned
    $checkVoters = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE region_id = '$region_id'");
    $checkPositions = mysqli_query($conn, "SELECT COUNT(*) as total FROM positions WHERE region_id = '$region_id'");

    $voterCount = mysqli_fetch_assoc($checkVoters)['total'];
    $positionCount = mysqli_fetch_assoc($checkPositions)['total'];

    if ($voterCount > 0 || $positionCount > 0) {
        header("Location: regions.php?status=in_use");
        exit();
    }

    $regionName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM regions WHERE id = '$region_id'"))['name'];

    if (mysqli_query($conn, "DELETE FROM regions WHERE id = '$region_id'")) {
        $user_id = $_SESSION['user_id'];
        $activity = "Deleted Region: " . $regionName;
        mysqli_query($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES ('$user_id', '$activity', NOW())");
        header("Location: regions.php?status=deleted");
        exit();
    } else {
        header("Location: regions.php?status=error");
        exit();
    }
}

/* Fetch all regions with stats */
$regions = mysqli_query($conn, "
    SELECT r.*, 
           COUNT(DISTINCT u.id) as voter_count,
           COUNT(DISTINCT p.id) as position_count
    FROM regions r
    LEFT JOIN users u ON r.id = u.region_id
    LEFT JOIN positions p ON r.id = p.region_id
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Regions - VoteSystem</title>
    <link rel="stylesheet" href="positions2.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .region-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .region-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .region-info h4 {
            color: #1e40af;
            font-size: 18px;
            margin-bottom: 4px;
        }
        .region-info p {
            color: #64748b;
            font-size: 13px;
        }
        .region-stats {
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
    </style>
</head>
<body>

<div class="container">
    <?php include "./sidebar.php"; ?>

    <div class="main">
        <div class="page-header">
            <h1>Manage Regions</h1>
            <p>Create and manage institutional regions (campuses, chapters, branches)</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Regions</h4>
                <div class="value"><?php echo mysqli_num_rows($regions); ?></div>
                <div class="label">Active regions in system</div>
            </div>
        </div>

        <!-- Add Region Form -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Region
            </h3>
            <form method="POST">
                <?php echo csrf_input_field(); ?>
                <div class="form-group">
                    <label for="region_name">Region Name <span>*</span></label>
                    <input type="text" id="region_name" name="region_name" 
                           placeholder="e.g., Main Campus, Nairobi Chapter, North Branch" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" 
                           placeholder="Brief description of this region">
                </div>
                <button type="submit" name="add_region">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Region
                </button>
            </form>
        </div>

        <!-- Regions List -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"></path>
                </svg>
                All Regions
            </h3>

            <?php 
            mysqli_data_seek($regions, 0);
            if(mysqli_num_rows($regions) > 0): 
                while($row = mysqli_fetch_assoc($regions)): 
            ?>
            <div class="region-card">
                <div class="region-info">
                    <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                    <p><?php echo htmlspecialchars($row['description'] ?: 'No description'); ?></p>
                </div>
                <div class="region-stats">
                    <div class="stat-item">
                        <div class="number"><?php echo $row['voter_count']; ?></div>
                        <div class="label">Voters</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo $row['position_count']; ?></div>
                        <div class="label">Positions</div>
                    </div>
                    <?php if ($row['voter_count'] == 0 && $row['position_count'] == 0): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this region?');">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="region_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_region" class="btn-delete-small">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
                endwhile; 
            else: 
            ?>
            <div class="empty-state">
                <p>No regions added yet. Create your first region above.</p>
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
        text: 'Region has been created successfully',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php elseif ($_GET['status'] === "deleted"): ?>
    Swal.fire({
        icon: 'success', title: 'Deleted!',
        text: 'Region has been deleted successfully',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php elseif ($_GET['status'] === "duplicate"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate!',
        text: 'A region with this name already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php elseif ($_GET['status'] === "in_use"): ?>
    Swal.fire({
        icon: 'error', title: 'Cannot Delete!',
        text: 'This region has voters or positions assigned to it.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error', title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error', title: 'Error!',
        text: 'Something went wrong. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'regions.php'); });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>