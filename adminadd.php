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

    $student_id  = mysqli_real_escape_string($conn, $_POST['id']);
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = 'admin';
    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);


    $sql = "INSERT INTO users (student_id, name, email, password, role)
            VALUES ('$student_id', '$name', '$email', '$hashedPassword', '$role')";

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
$admins = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE role = 'admin'"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main {
            margin-left: 250px;
            padding: 40px;
            overflow-y: auto;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #64748b;
            font-size: 14px;
        }

        /* Card Styles */
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3 svg {
            width: 24px;
            height: 24px;
            color: #2563eb;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .form-group label span {
            color: #ef4444;
        }

        input[type="text"],
        input[type="date"], input[type="email"],input[type="password"],
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #ffffff;
        }

        input[type="text"]:focus,
        input[type="date"]:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Date Inputs Grid */
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Button */
        button[type="submit"] {
            width: 100%;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
        }

        button[type="submit"]:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        button[type="submit"] svg {
            width: 20px;
            height: 20px;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #0f172a;
            font-size: 14px;
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .position-name {
            font-weight: 600;
            color: #2563eb;
        }

        .description-cell {
            max-width: 300px;
            color: #64748b;
            font-size: 13px;
        }

        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .date-badge svg {
            width: 14px;
            height: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main {
                padding: 20px;
            }

            .date-inputs {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: scroll;
            }

            table {
                min-width: 600px;
            }
        }

        /* Stats Card */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-card h4 {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-card .label {
            font-size: 12px;
            opacity: 0.8;
        }
    </style>
</head>

<body>

<div class="container">
    <!-- Sidebar -->
    <?php include "./sidebar.php"; ?>

    <!-- Main Content -->
    <div class="main">
    
        <!-- Add Position Form -->
        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Admin
            </h3>
            <form method="POST">
            <div class="form-group">
                <label>Student ID</label>
                <input placeholder="Enter your Student ID" type="text" name="id" required>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input placeholder="Enter your Full Name" type="text" name="name" required>
            </div>
             <div class="form-group">
                <label>Email Address</label>
                <input placeholder="Enter your Email Address" type="email" name="email" required>
            </div>
               <div class="form-group">
                <label>Password</label>
                <input placeholder="8 characters minimum" type="Password" type="password" name="password" required>
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
              
               Current Admins
            </h3>

            <?php 
            mysqli_data_seek($admins, 0); // Reset pointer
            if(mysqli_num_rows($admins) > 0): 
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($admins)): ?>
                        <tr>
                            <td class="position-name"><?= htmlspecialchars($row['student_id']) ?></td>
                            <td class="description-cell"><?= htmlspecialchars($row['name']) ?></td>
                            <td class="description-cell"><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <span class="date-badge">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                </span>
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
        // Remove the status parameter from URL
        window.history.replaceState({}, document.title, 'manage_positions.php');
    });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Could not add position. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => {
        // Remove the status parameter from URL
        window.history.replaceState({}, document.title, 'manage_positions.php');
    });
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>