<?php
session_start();
require_once "./config/connection.php";
require_once "./csrf_helper.php";  // ADDED: CSRF protection

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/*    HANDLE ADD ADMIN */
if (isset($_POST['add_admin'])) {

    // ADDED: CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: adminadd.php?status=csrf_error");
        exit();
    }

    $id_number  = mysqli_real_escape_string($conn, $_POST['id_number']);  // CHANGED: student_id → id_number
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = 'admin';
    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (id_number, name, email, password, role)
            VALUES ('$id_number', '$name', '$email', '$hashedPassword', '$role')";

    if (mysqli_query($conn, $sql)) {

        // LOG ACTIVITY
        $user_id  = $_SESSION['user_id'];
        $activity = "Added Admin: " . $name;

        $log_sql = "INSERT INTO logs (user_id, activity, log_time)
                    VALUES ('$user_id', '$activity', NOW())";

        mysqli_query($conn, $log_sql);

        header("Location: adminadd.php?status=success");
        exit();

    } else {
        header("Location: adminadd.php?status=error");
        exit();
    }
}

/* Fetch all admins */
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
    <title>Manage Admins - VoteSystem</title>
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
        input[type="date"], input[type="email"],input[type="password"] {
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
        input[type="date"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

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

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .main {
                padding: 20px;
            }

            .table-container {
                overflow-x: scroll;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>

<body>

<div class="container">
    <?php include "./sidebar.php"; ?>

    <div class="main">

        <div class="card">
            <h3>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Admin
            </h3>
            <form method="POST">
                <!-- ADDED: CSRF token field -->
                <?php echo csrf_input_field(); ?>

                <div class="form-group">
                    <label>ID Number</label>  <!-- CHANGED: Student ID → ID Number -->
                    <input placeholder="Enter ID Number" type="text" name="id_number" required>  <!-- ✅ CHANGED: name="id" → name="id_number" -->
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input placeholder="Enter Full Name" type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input placeholder="Enter Email Address" type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input placeholder="8 characters minimum" type="password" name="password" required>
                </div>

                <button type="submit" name="add_admin">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Admin
                </button>
            </form>
        </div>

        <div class="card">
            <h3>Current Admins</h3>

            <?php 
            mysqli_data_seek($admins, 0);
            if(mysqli_num_rows($admins) > 0): 
            ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>  <!--  CHANGED: Student ID → ID Number -->
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($admins)): ?>
                        <tr>
                            <td class="position-name"><?= htmlspecialchars($row['id_number']) ?></td>  <!-- ✅ CHANGED: student_id → id_number -->
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <span class="date-badge">
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
                <p>No admins added yet. Create your first admin above.</p>
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
        text: 'Admin has been added successfully',
        confirmButtonColor: '#2563eb',
        timer: 2000
    }).then(() => {
        window.history.replaceState({}, document.title, 'adminadd.php');
    });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Could not add admin. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => {
        window.history.replaceState({}, document.title, 'adminadd.php');
    });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => {
        window.history.replaceState({}, document.title, 'adminadd.php');
    });
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>