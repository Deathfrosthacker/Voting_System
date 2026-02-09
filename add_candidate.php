<?php
session_start();
require_once "./config/connection.php";

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$candidates = mysqli_query(
    $conn,
    "SELECT * FROM candidates ORDER BY start_date DESC"
);

// Fetch positions
$positions = mysqli_query(
    $conn,
    "SELECT * FROM positions ORDER BY start_date DESC"
);

// Handle candidate insert
if (isset($_POST['add_candidate'])) {
    $candidate_name = mysqli_real_escape_string($conn, $_POST['candidate_name']);
    $position       = $_POST['position_name'];
    $start_date     = $_POST['start_date'];
    $end_date       = $_POST['end_date'];

    $sql = "INSERT INTO candidates 
            (name, position, start_date, end_date)
            VALUES 
            ('$candidate_name', '$position', '$start_date', '$end_date')";

    if (mysqli_query($conn, $sql)) {
        // Log activity
        $user_id  = $_SESSION['user_id'];
        $activity = "Added Candidate";

        mysqli_query(
            $conn,
            "INSERT INTO logs (id, activity, log_time)
             VALUES ('$user_id', '$activity', NOW())"
        );

        $status = "success";
    } else {
        $status = "error";
    }
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
                    <div class="position-card" onclick="openForm(
                        '<?php echo htmlspecialchars($pos['position_name']); ?>',
                        '<?php echo $pos['start_date']; ?>',
                        '<?php echo $pos['end_date']; ?>'
                    )">
                        <h4><?php echo htmlspecialchars($pos['position_name']); ?></h4>
                        <p class="description"><?php echo htmlspecialchars($pos['description']); ?></p>
                        <div class="position-dates">
                            <div class="date-item">
                              
                                <span>Start: <?php echo date('M d, Y', strtotime($pos['start_date'])); ?></span>
                            </div>
                            <div class="date-item">
                                <span>End: <?php echo date('M d, Y', strtotime($pos['end_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Candidate Form -->
        <div class="form-box" id="candidateForm">
            <h3 id="formTitle">Add Candidate</h3>
            <form method="POST">
                <input type="hidden" name="position_name" id="position_name">
                <input type="hidden" name="start_date" id="start_date">
                <input type="hidden" name="end_date" id="end_date">

                <div class="form-group">
                    <label for="candidate_name">Candidate Name</label>
                    <input type="text" name="candidate_name" id="candidate_name" required placeholder="Enter candidate full name">
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
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($candidates)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['position']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
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
</script>

<?php if (isset($status)): ?>
<script>
<?php if ($status === "success"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Candidate added successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.location.href = "add_candidate.php";
    });
<?php else: ?>
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