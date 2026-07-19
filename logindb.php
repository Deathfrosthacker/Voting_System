<?php
require_once "./config/connection.php";
require_once "./rbac_helper.php";

// Start LOGIN_PORTAL for rate limiting
ini_set('session.cookie_path', '/');

// Only start if not already active to avoid conflicts
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOGIN_PORTAL');
    session_start();
}

/* Session timeout configuration */
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

/* Rate limiting - max 5 failed attempts per 15 minutes */
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

$role = ''; // Initialize role variable
$redirect_url = 'login.php'; // Default redirect

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if user is locked out
    if ($_SESSION['login_attempts'] >= $maxAttempts) {
        $remaining = ceil(($lockoutTime - (time() - $_SESSION['last_attempt_time'])) / 60);
        $status = "locked_out";
        $lockout_remaining = $remaining;
    } else {

        $id_number = trim($_POST['id'] ?? '');
        $password   = $_POST['password'] ?? '';

        
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $id_number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result === false) {
            error_log("Login DB error: " . mysqli_error($conn));
            $status = "db_error";
            $error_detail = "A database error occurred. Please try again later.";

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

                /* Start the role-specific session properly */
                $role = $user['role'];

                // Map role to session name
                $session_map = [
                    'admin'            => 'ADMIN_SESSION',
                    'election_officer' => 'OFFICER_SESSION',
                    'observer'         => 'OBSERVER_SESSION',
                    'voter'            => 'VOTER_SESSION'
                ];
                $sess_name = $session_map[$role] ?? 'VOTER_SESSION';

                // Properly close LOGIN_PORTAL session before switching
                $_SESSION = array();
                session_destroy();

                // Start the role-specific session fresh
                ini_set('session.cookie_path', '/');
                session_name($sess_name);
                session_start();

                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Set session variables in the ROLE-SPECIFIC session
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['id_number']     = $user['id_number'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();

                /* Check if user needs to change password (first login after officer registration) */
                $password_changed = $user['password_changed'] ?? 1;

                if ($password_changed == 0) {
                    $status = "first_login";
                    $redirect_url = "change_password.php?role=" . urlencode($role);
                } else {
                    $status = "success";
                    // Set redirect based on role
                    $redirect_url = match($role) {
                        'admin' => 'admin_dashboard.php',
                        'election_officer' => 'election_officer_dashboard.php',
                        'observer' => 'observer_dashboard.php',
                        default => 'voter_dashboard.php'
                    };
                }

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
        window.location.href = "<?php echo $redirect_url; ?>";
    });

<?php elseif ($status === "first_login"): ?>
    Swal.fire({
        icon: 'warning',
        title: 'Password Change Required',
        text: 'Your account was registered by an election officer. Please change your password for security.',
        confirmButtonColor: '#f59e0b',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        window.location.href = "<?php echo $redirect_url; ?>";
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