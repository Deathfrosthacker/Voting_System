<?php
/**
 * CHANGE PASSWORD - FIXED VERSION
 * Security fixes:
 * 1. Session timeout and role check
 * 2. Regenerate session ID on password change
 * 3. Proper redirect after change
 * 4. FIXED: Session detection now uses role hint from URL first, preventing
 *    admin session from being picked when user is a different role
 */

ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Strict');

// Role to session name mapping
$session_map = [
    'admin'            => 'ADMIN_SESSION',
    'election_officer' => 'OFFICER_SESSION',
    'observer'         => 'OBSERVER_SESSION',
    'voter'            => 'VOTER_SESSION'
];

// ============================================================
// FIX: Use role hint from URL as PRIMARY session selector.
// When login redirects here with ?role=election_officer, we
// MUST use OFFICER_SESSION, not whatever cookie happens to exist.
// ============================================================
$role_hint = $_GET['role'] ?? null;
$session_started = false;

if ($role_hint && isset($session_map[$role_hint])) {
    // Primary: Use the role from URL parameter (set by logindb.php)
    $target_sess = $session_map[$role_hint];
    if (isset($_COOKIE[$target_sess])) {
        session_name($target_sess);
        session_start();
        $session_started = true;
    }
}

// Fallback: If no role hint or cookie not found, try cookie detection
if (!$session_started) {
    // Try each role session to find an active one
    foreach ($session_map as $sess_role => $sess_name) {
        if (isset($_COOKIE[$sess_name])) {
            session_name($sess_name);
            session_start();
            $session_started = true;
            break;
        }
    }
}

// Last resort: generic session (for backward compatibility)
if (!$session_started && session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

/* Use centralized session security from rbac_helper.php */
check_session_timeout();
require_auth();

// FIX: Ensure we have valid session data before proceeding
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['id_number'])) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$id_number = $_SESSION['id_number'];

/* ==================== FETCH USER DATA WITH VALIDATION ==================== */
$stmt = mysqli_prepare($conn, "SELECT id, name, id_number, password_changed, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$userResult = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($userResult);

// Verify the session user matches the database user
// FIX: Use loose type comparison to handle string/int differences from DB
if (!$user || (int)$user['id'] !== (int)$user_id || $user['role'] !== $role || $user['id_number'] !== $id_number) {
    // Session data doesn't match database - possible tampering or corruption
    session_unset();
    session_destroy();
    header("Location: login.php?error=session_corrupted");
    exit();
}

/* Pre-define redirect URL for cancel button and success redirect */
$redirect_url = match($role) {
    'admin' => 'admin_dashboard.php',
    'election_officer' => 'election_officer_dashboard.php',
    'observer' => 'observer_dashboard.php',
    default => 'voter_dashboard.php'
};

/* Check if this is optional password change or forced */
$is_optional = isset($_GET['mode']) && $_GET['mode'] === 'optional';

/* If password already changed and this is NOT optional mode, redirect to dashboard */
if ($user['password_changed'] == 1 && !$is_optional) {
    header("Location: " . $redirect_url);
    exit();
}

/* ==================== HANDLE PASSWORD CHANGE ==================== */
if (isset($_POST['change_password'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Re-validate session before sensitive operation
        check_session_timeout();

        // Validate current password
        $checkStmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($checkStmt, "i", $user_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $userData = mysqli_fetch_assoc($checkResult);

        if (!password_verify($current_password, $userData['password'])) {
            $error_message = "Current password is incorrect.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirm password do not match.";
        } elseif (password_verify($new_password, $userData['password'])) {
            $error_message = "New password cannot be the same as your current password.";
        } else {
            // Hash new password
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and set password_changed = 1
            $updStmt = mysqli_prepare($conn, 
                "UPDATE users SET password = ?, password_changed = 1 WHERE id = ?"
            );
            mysqli_stmt_bind_param($updStmt, "si", $hashedPassword, $user_id);

            if (mysqli_stmt_execute($updStmt)) {
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                // Log activity
                $activity = "Changed password";
                $logStmt = mysqli_prepare($conn, 
                    "INSERT INTO logs (user_id, activity, log_time) VALUES (?, ?, NOW())"
                );
                mysqli_stmt_bind_param($logStmt, "is", $user_id, $activity);
                mysqli_stmt_execute($logStmt);

                $success_message = "Password changed successfully! Redirecting to your dashboard...";

                // Set a session flag to show success on redirect
                $_SESSION['password_changed_success'] = true;
            } else {
                $error_message = "Could not update password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | VoteSystem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }
        .header h1 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .header p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
        .notice {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #92400e;
            font-size: 14px;
        }
        .notice i { font-size: 20px; margin-top: 2px; }
        .user-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .user-avatar {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; font-weight: 700;
        }
        .user-details h4 {
            font-size: 16px; color: #0f172a; margin-bottom: 2px;
        }
        .user-details p {
            font-size: 13px; color: #64748b;
        }
        .session-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        .form-group label span { color: #ef4444; }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            width: 100%;
            padding: 12px 44px 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .password-wrapper input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
        }
        .toggle-password:hover { color: #64748b; }
        .strength-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
        }
        .strength-weak { background: #ef4444; color: #dc2626; }
        .strength-fair { background: #f59e0b; color: #d97706; }
        .strength-good { background: #3b82f6; color: #2563eb; }
        .strength-strong { background: #10b981; color: #059669; }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37,99,235,0.3);
        }
        .error-box {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-box {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .requirements {
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .requirements h4 {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .requirements ul {
            list-style: none;
            padding: 0;
        }
        .requirements li {
            font-size: 13px;
            color: #64748b;
            padding: 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .requirements li i {
            font-size: 12px;
            width: 16px;
        }
        .req-met { color: #059669 !important; }
        .req-met i { color: #10b981; }
        @media (max-width: 480px) {
            .card { padding: 24px; }
            .header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <div class="icon">
            <i class="fas fa-lock"></i>
        </div>
        <h1><?php echo $is_optional ? 'Change Password' : 'Change Your Password'; ?></h1>
        <p><?php echo $is_optional ? 'Update your password to keep your account secure.' : 'Your account was registered by an election officer. For security, you must change your password before accessing the system.'; ?></p>
    </div>

    <?php if (!$is_optional): ?>
    <div class="notice">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Security Notice:</strong> The password given to you by the officer was temporary. Please create a strong, unique password that only you know.
        </div>
    </div>
    <?php endif; ?>

    <div class="user-info">
        <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
        <div class="user-details">
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p>ID: <?php echo htmlspecialchars($user['id_number']); ?></p>
            <span class="session-badge" style="background: <?php echo get_role_bg_color($role); ?>; color: <?php echo get_role_color($role); ?>">
                <i class="fas fa-shield-alt"></i> <?php echo get_role_display_name($role); ?>
            </span>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="error-box">
        <i class="fas fa-times-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="success-box">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <script>
        setTimeout(() => {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 2000);
    </script>
    <?php else: ?>

    <form method="POST" id="passwordForm">
        <?php echo csrf_input_field(); ?>

        <div class="form-group">
            <label>Current Password <span>*</span></label>
            <div class="password-wrapper">
                <input type="password" name="current_password" id="current_password" required placeholder="<?php echo $is_optional ? 'Enter your current password' : 'Enter the temporary password given by the officer'; ?>">
                <button type="button" class="toggle-password" onclick="toggleVisibility('current_password', this)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label>New Password <span>*</span></label>
            <div class="password-wrapper">
                <input type="password" name="new_password" id="new_password" required minlength="8" placeholder="Create a strong password">
                <button type="button" class="toggle-password" onclick="toggleVisibility('new_password', this)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="strength-bar">
                <div class="strength-fill" id="strengthFill"></div>
            </div>
            <div class="strength-text" id="strengthText"></div>
        </div>

        <div class="requirements">
            <h4>Password Requirements</h4>
            <ul>
                <li id="req-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                <li id="req-upper"><i class="fas fa-circle"></i> One uppercase letter</li>
                <li id="req-lower"><i class="fas fa-circle"></i> One lowercase letter</li>
                <li id="req-number"><i class="fas fa-circle"></i> One number</li>
                <li id="req-special"><i class="fas fa-circle"></i> One special character (!@#$%^&*)</li>
            </ul>
        </div>

        <div class="form-group">
            <label>Confirm New Password <span>*</span></label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" required minlength="8" placeholder="Re-enter your new password">
                <button type="button" class="toggle-password" onclick="toggleVisibility('confirm_password', this)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" name="change_password" class="btn-primary" id="submitBtn" style="flex: 1;">
                <i class="fas fa-shield-alt"></i> Update Password
            </button>
            <?php if ($is_optional): ?>
            <a href="<?php echo htmlspecialchars($redirect_url); ?>" style="padding: 14px 24px; background: #f1f5f9; color: #475569; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 15px; text-align: center;">
                Cancel
            </a>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function toggleVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function updatePasswordStrength() {
    const password = document.getElementById('new_password')?.value || '';
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');

    const requirements = [
        { id: 'req-length', regex: /.{8,}/ },
        { id: 'req-upper', regex: /[A-Z]/ },
        { id: 'req-lower', regex: /[a-z]/ },
        { id: 'req-number', regex: /[0-9]/ },
        { id: 'req-special', regex: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/\?]/ }
    ];

    let metCount = 0;

    requirements.forEach(({ id, regex }) => {
        const item = document.getElementById(id);
        const icon = item?.querySelector('i');
        const met = regex.test(password);

        if (item) {
            item.classList.toggle('req-met', met);
        }

        if (icon) {
            icon.className = met ? 'fas fa-check' : 'fas fa-circle';
        }

        if (met) metCount++;
    });

    if (!password) {
        fill.style.width = '0%';
        fill.className = 'strength-fill';
        text.textContent = '';
        text.className = 'strength-text';
        return;
    }

    let strengthClass = 'strength-weak';
    let strengthLabel = 'Weak';

    if (metCount >= 5) {
        strengthClass = 'strength-strong';
        strengthLabel = 'Strong';
    } else if (metCount === 4) {
        strengthClass = 'strength-good';
        strengthLabel = 'Good';
    } else if (metCount === 3) {
        strengthClass = 'strength-fair';
        strengthLabel = 'Fair';
    }

    fill.style.width = `${Math.max(20, metCount * 20)}%`;
    fill.className = `strength-fill ${strengthClass}`;
    text.textContent = `${strengthLabel} password`;
    text.className = `strength-text ${strengthClass}`;
}

function validatePasswordForm(event) {
    const newPass = document.getElementById('new_password')?.value || '';
    const confirmPass = document.getElementById('confirm_password')?.value || '';

    if (newPass !== confirmPass) {
        event.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Passwords Do Not Match',
            text: 'Please ensure your new password and confirmation match.',
            confirmButtonColor: '#ef4444'
        });
        return false;
    }

    const requirements = [/.{8,}/, /[A-Z]/, /[a-z]/, /[0-9]/, /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/\?]/];
    const allMet = requirements.every(regex => regex.test(newPass));

    if (!allMet) {
        event.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Password Too Weak',
            text: 'Please meet all password requirements before continuing.',
            confirmButtonColor: '#f59e0b'
        });
        return false;
    }

    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('new_password');
    if (passwordField) {
        passwordField.addEventListener('input', updatePasswordStrength);
        updatePasswordStrength();
    }

    document.getElementById('passwordForm')?.addEventListener('submit', validatePasswordForm);
});
</script>

</body>
</html>