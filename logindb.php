<?php
session_start();
require_once "./config/connection.php";
require_once "./rbac_helper.php";

/* FIX 1: Session timeout configuration */
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

/* FIX 2: Rate limiting - max 5 failed attempts per 15 minutes */
$maxAttempts = 5;
$lockoutTime = 900; // 15 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Reset attempts after lockout period
if (time() - $_SESSION['last_attempt_time'] > $lockoutTime) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if user is locked out
    if ($_SESSION['login_attempts'] >= $maxAttempts) {
        $remaining = ceil(($lockoutTime - (time() - $_SESSION['last_attempt_time'])) / 60);
        $status = "locked_out";
        $lockout_remaining = $remaining;
    } else {

        $id_number = mysqli_real_escape_string($conn, $_POST['id']);
        $password   = $_POST['password'];

        /* FIX 3: Use prepared statement to prevent SQL injection */
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $id_number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result === false) {
            $status = "db_error";
            $error_detail = mysqli_error($conn);

            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();

            $failActivity = "Failed login - DB error for ID: $id_number";
            $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (0, ?, NOW())");
            mysqli_stmt_bind_param($logStmt, "s", $failActivity);
            mysqli_stmt_execute($logStmt);

        } elseif (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

            // Verify password
            if (password_verify($password, $user['password'])) {

                // Check if official is suspended
                if (in_array($user['role'], ['election_officer', 'observer'])) {
                    $checkStatus = mysqli_prepare($conn, "SELECT status FROM officials WHERE user_id = ? LIMIT 1");
                    mysqli_stmt_bind_param($checkStatus, "i", $user['id']);
                    mysqli_stmt_execute($checkStatus);
                    $statusResult = mysqli_stmt_get_result($checkStatus);
                    if ($statusResult && mysqli_num_rows($statusResult) > 0) {
                        $officialStatus = mysqli_fetch_assoc($statusResult)['status'];
                        if ($officialStatus === 'suspended') {
                            $status = "suspended";
                            goto end_login;
                        }
                    }
                }

                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Reset login attempts on success
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = time();
                $_SESSION['created'] = time();

                // Set session variables
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['id_number']  = $user['id_number'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['last_activity'] = time();

                $status = "success";
                $role   = $user['role'];

                $user_id = $user['id'];
                $activity = "Logged in";

                // Log successful login
                $logStmt = mysqli_prepare($conn, 
                    "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())"
                );
                mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
                mysqli_stmt_execute($logStmt);

            } else {
                $status = "wrong_password";

                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();

                $failActivity = "Failed login - wrong password for ID: $id_number (Attempt " . $_SESSION['login_attempts'] . "/$maxAttempts)";
                $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (0, ?, NOW())");
                mysqli_stmt_bind_param($logStmt, "s", $failActivity);
                mysqli_stmt_execute($logStmt);
            }

        } else {
            $status = "not_found";

            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();

            $failActivity = "Failed login - ID not found: $id_number (Attempt " . $_SESSION['login_attempts'] . "/$maxAttempts)";
            $logStmt = mysqli_prepare($conn, "INSERT INTO logs (user_id, activity, log_time) VALUES (0, ?, NOW())");
            mysqli_stmt_bind_param($logStmt, "s", $failActivity);
            mysqli_stmt_execute($logStmt);
        }
    }
}

end_login:
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if (isset($status)): ?>
<script>
<?php if ($status === "success"): ?>
    Swal.fire({
        icon: 'success',
        title: 'Login Successful',
        confirmButtonColor: '#1e40af'
    }).then(() => {
        <?php 
        // RBAC-aware redirect based on role
        if ($role === "admin" || $role === "election_officer" || $role === "observer"): 
        ?>
            window.location.href = "admin_dashboard.php";
        <?php else: ?>
            window.location.href = "voter_dashboard.php";
        <?php endif; ?>
    });

<?php elseif ($status === "suspended"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Account Suspended',
        text: 'Your official account has been suspended. Please contact the system administrator.',
        confirmButtonColor: '#dc2626'
    }).then(() => {
        window.history.back();
    });

<?php elseif ($status === "wrong_password"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Incorrect Password',
        text: '<?php echo ($_SESSION["login_attempts"] >= $maxAttempts) ? "Too many failed attempts. Please try again in 15 minutes." : "Please try again (Attempt " . $_SESSION["login_attempts"] . "/" . $maxAttempts . ")"; ?>',
        confirmButtonColor: '#dc2626'
    }).then(() => {
        window.history.back();
    });

<?php elseif ($status === "not_found"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'ID Number Not Found',
        text: 'Please check your ID Number',
        confirmButtonColor: '#f59e0b'
    }).then(() => {
        window.history.back();
    });

<?php elseif ($status === "locked_out"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Account Temporarily Locked',
        text: 'Too many failed login attempts. Please try again in <?php echo $lockout_remaining; ?> minutes.',
        confirmButtonColor: '#dc2626'
    }).then(() => {
        window.history.back();
    });

<?php elseif ($status === "db_error"): ?>
    Swal.fire({
        icon: 'error',
        title: 'Database Error',
        text: '<?php echo addslashes($error_detail); ?>',
        confirmButtonColor: '#dc2626'
    }).then(() => {
        window.history.back();
    });

<?php else: ?>
    Swal.fire({
        icon: 'error',
        title: 'Login Failed',
        text: 'Something went wrong',
        confirmButtonColor: '#dc2626'
    }).then(() => {
        window.history.back();
    });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>