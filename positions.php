<?php
session_start();
require_once "./config/connection.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* =========================
   HANDLE ADD POSITION
========================= */
if (isset($_POST['add_position'])) {

    $name  = mysqli_real_escape_string($conn, $_POST['position_name']);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $start = $_POST['start_date'];
    $end   = $_POST['end_date'];

    $sql = "INSERT INTO positions (position_name, description, start_date, end_date)
            VALUES ('$name', '$description', '$start', '$end')";

    if (mysqli_query($conn, $sql)) {

        // LOG ACTIVITY
        $user_id  = $_SESSION['user_id'];
        $activity = "Made Position";

        $log_sql = "INSERT INTO logs (user_id, activity, log_time)
                    VALUES ('$user_id', '$activity', NOW())";

        mysqli_query($conn, $log_sql);

        // ✅ REDIRECT to prevent form resubmission
        header("Location: positions.php?status=success");
        exit();

    } else {
        // ✅ REDIRECT with error
        header("Location: positions.php?status=error");
        exit();
    }
}

/* Fetch all positions */
$positions = mysqli_query(
    $conn,
    "SELECT * FROM positions ORDER BY start_date DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="positions2.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    

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
                <div class="form-group">
                    <label for="position_name">Position Name <span>*</span></label>
                    <input type="text" id="position_name" name="position_name" placeholder="e.g., President, Vice President, Secretary" required>
                </div>

                <div class="form-group">
                    <label for="description">Description <span>*</span></label>
                    <textarea id="description" name="description" placeholder="Brief description of the position's responsibilities and requirements" required></textarea>
                </div>

                <div class="date-inputs">
                    <div class="form-group">
                        <label for="start_date">Start Date <span>*</span></label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date <span>*</span></label>
                        <input type="date" id="end_date" name="end_date" required>
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
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($positions)): ?>
                        <tr>
                            <td class="position-name"><?= htmlspecialchars($row['position_name']) ?></td>
                            <td class="description-cell"><?= htmlspecialchars($row['description']) ?></td>
                            <td>
                                <span class="date-badge">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?= date('M d, Y', strtotime($row['start_date'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="date-badge">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_position.php?id=<?= $row['id'] ?>" class="btn-edit" title="Edit">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['position_name'], ENT_QUOTES) ?>')" class="btn-delete" title="Delete">
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
function confirmDelete(id, positionName) {
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete the position: <strong>${positionName}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `delete_position.php?id=${id}`;
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