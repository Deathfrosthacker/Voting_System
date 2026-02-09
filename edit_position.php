<?php
session_start();
require_once "./config/connection.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get position ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: positions.php");
    exit();
}

$position_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch position data
$query = "SELECT * FROM positions WHERE id = '$position_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header("Location: positions.php?status=error");
    exit();
}

$position = mysqli_fetch_assoc($result);

/* =========================
   HANDLE UPDATE POSITION
========================= */
if (isset($_POST['update_position'])) {

    $name = mysqli_real_escape_string($conn, $_POST['position_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];

    $sql = "UPDATE positions 
            SET position_name = '$name', 
                description = '$description', 
                start_date = '$start', 
                end_date = '$end'
            WHERE id = '$position_id'";

    if (mysqli_query($conn, $sql)) {

        // LOG ACTIVITY
        $user_id = $_SESSION['user_id'];
        $activity = "Updated Position: " . $name;

        $log_sql = "INSERT INTO logs (user_id, activity, log_time)
                    VALUES ('$user_id', '$activity', NOW())";

        mysqli_query($conn, $log_sql);

        header("Location: positions.php?status=updated");
        exit();

    } else {
        $error_message = "Error updating position. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="positions.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Position - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="position_name">Position Name <span>*</span></label>
                    <input type="text" id="position_name" name="position_name" value="<?= htmlspecialchars($position['position_name']) ?>" placeholder="e.g., President, Vice President, Secretary" required>
                </div>

                <div class="form-group">
                    <label for="description">Description <span>*</span></label>
                    <textarea id="description" name="description" placeholder="Brief description of the position's responsibilities and requirements" required><?= htmlspecialchars($position['description']) ?></textarea>
                </div>

                <div class="date-inputs">
                    <div class="form-group">
                        <label for="start_date">Start Date <span>*</span></label>
                        <input type="date" id="start_date" name="start_date" value="<?= $position['start_date'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date <span>*</span></label>
                        <input type="date" id="end_date" name="end_date" value="<?= $position['end_date'] ?>" required>
                    </div>
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

</body>
</html>