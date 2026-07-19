<?php
// Session is started by check_session_timeout() in rbac_helper.php
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

// RBAC: Only admin can add other admins
check_session_timeout();
require_auth(['admin']);

/* SESSION TIMEOUT CHECK (30 minutes) */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

/* HANDLE ADD ADMIN */
if (isset($_POST['add_admin'])) {

    // ADDED: CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: adminadd.php?status=csrf_error");
        exit();
    }
    $role = 'admin';
    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check duplicate id_number
    $checkId = mysqli_prepare($conn, "SELECT id FROM users WHERE id_number = ? LIMIT 1");
    $id_number = trim($_POST['id_number']);
    // Basic validation for ID number (only digits, length between 6 and 12)
    if (!preg_match('/^[0-9]{6,12}$/', $id_number)) {
        header("Location: adminadd.php?status=invalid_id");
        exit();
    }
    $name = trim($_POST['name']);
    // Basic validation for name (only letters, spaces, and certain punctuation)
    if (!preg_match("/^[a-zA-Z\s.'-]{3,100}$/", $name)) {
        header("Location: adminadd.php?status=invalid_name");
        exit();
    }
    $email = trim($_POST['email']);
    // Basic email format validation
   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: adminadd.php?status=invalid_email");
        exit();
    }

    mysqli_stmt_bind_param($checkId, "s", $id_number);
    mysqli_stmt_execute($checkId);
    $idResult = mysqli_stmt_get_result($checkId);
    if (mysqli_num_rows($idResult) > 0) {
        header("Location: adminadd.php?status=duplicate_id");
        exit();
    }

    // Check duplicate email
    $checkEmail = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($checkEmail, "s", $email);
    mysqli_stmt_execute($checkEmail);
    $emailResult = mysqli_stmt_get_result($checkEmail);
    if (mysqli_num_rows($emailResult) > 0) {
        header("Location: adminadd.php?status=duplicate_email");
        exit();
    }

    // Password length validation
    if (strlen($password) < 8) {
        header("Location: adminadd.php?status=short_password");
        exit();
    }

    $stmt = mysqli_prepare($conn, 
        "INSERT INTO users (id_number, name, email, password, role) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sssss", $id_number, $name, $email, $hashedPassword, $role);

    if (mysqli_stmt_execute($stmt)) {
        // LOG ACTIVITY using prepared statement
        $user_id  = $_SESSION['user_id'];
        $activity = "Added Admin: " . $name;

        $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
        mysqli_stmt_execute($logStmt);

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
    "SELECT * FROM users WHERE role = 'admin' ORDER BY created_at DESC"
);
if ($admins === false) {
    die("Database error fetching admins.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - VoteSystem</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }
        .container { display: flex; min-height: 100vh; }
        .main { margin-left: 250px; padding: 40px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .page-header p { color: #64748b; font-size: 14px; }
        .card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card h3 {
            font-size: 18px; font-weight: 600; color: #0f172a;
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .card h3 svg { width: 24px; height: 24px; color: #2563eb; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 14px; font-weight: 500;
            color: #0f172a; margin-bottom: 8px;
        }
        .form-group label span { color: #ef4444; }
        input[type="text"], input[type="date"], input[type="email"], input[type="password"] {
            width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-size: 14px; transition: all 0.3s ease;
            font-family: inherit; background: #ffffff;
        }
        input[type="text"]:focus, input[type="date"]:focus, input[type="email"]:focus, input[type="password"]:focus {
            outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        button[type="submit"] {
            width: 100%; padding: 12px 24px; background: #2563eb; color: white;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px;
        }
        button[type="submit"]:hover {
            background: #1d4ed8; transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        thead { background: #f8fafc; }
        th {
            padding: 14px 16px; text-align: left; font-size: 13px;
            font-weight: 600; color: #64748b; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-size: 14px; }
        tbody tr { transition: background 0.2s ease; }
        tbody tr:hover { background: #f8fafc; }
        .position-name { font-weight: 600; color: #2563eb; }
        .date-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; background: #e0e7ff;
            color: #3730a3; border-radius: 6px; font-size: 12px; font-weight: 500;
        }
        .empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
        @media (max-width: 768px) {
            .main { padding: 20px; }
            .table-container { overflow-x: scroll; }
            table { min-width: 600px; }
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
                    <label>ID Number <span>*</span></label>
                    <input placeholder="Enter ID Number" type="text" name="id_number" required>
                </div>
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <input placeholder="Enter Full Name" type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email Address <span>*</span></label>
                    <input placeholder="Enter Email Address" type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password <span>*</span> <small style="color:#64748b;">(minimum 8 characters)</small></label>
                    <input placeholder="8 characters minimum" type="password" name="password" required minlength="8">
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
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($admins)): ?>
                        <tr>
                            <td class="position-name"><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <span class="date-badge">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
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
        icon: 'success', title: 'Success!',
        text: 'Admin has been added successfully',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error', title: 'Error!',
        text: 'Could not add admin. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error', title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "duplicate_id"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate ID Number!',
        text: 'An admin with this ID number already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "duplicate_email"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate Email!',
        text: 'An admin with this email address already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "short_password"): ?>
    Swal.fire({
        icon: 'warning', title: 'Password Too Short!',
        text: 'Password must be at least 8 characters long.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "invalid_id"): ?>
    /* FIX 2: Added missing SweetAlert handler for invalid_id */
    Swal.fire({
        icon: 'warning', title: 'Invalid ID Number!',
        text: 'ID number must be 6-12 digits (numbers only). Please check and try again.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "invalid_name"): ?>
    /* FIX 3: Added missing SweetAlert handler for invalid_name */
    Swal.fire({
        icon: 'warning', title: 'Invalid Name!',
        text: 'Name must be 3-100 characters and contain only letters, spaces, and basic punctuation.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php elseif ($_GET['status'] === "invalid_email"): ?>
    /* FIX 4: Added missing SweetAlert handler for invalid_email */
    Swal.fire({
        icon: 'warning', title: 'Invalid Email!',
        text: 'Please enter a valid email address format (e.g., name@example.com).',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'adminadd.php'); });
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>