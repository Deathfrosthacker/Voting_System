<?php
// Session is started by check_session_timeout() in rbac_helper.php
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

/* RBAC: Only admin and election_officer can register voters */
check_session_timeout();
require_auth(['admin', 'election_officer']);

$user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

/* ==================== HANDLE REGISTER VOTER ==================== */
if (isset($_POST['register_voter'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: register_voter.php?status=csrf_error");
        exit();
    }

    $id_number = trim($_POST['id_number']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $date_of_birth = $_POST['date_of_birth'];
    $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validations
    if (!preg_match('/^[0-9]{6,12}$/', $id_number)) {
        header("Location: register_voter.php?status=invalid_id");
        exit();
    }
    if (!preg_match("/^[a-zA-Z\s.'-]{3,100}$/", $name)) {
        header("Location: register_voter.php?status=invalid_name");
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register_voter.php?status=invalid_email");
        exit();
    }
    if (strlen($password) < 8) {
        header("Location: register_voter.php?status=short_password");
        exit();
    }
    if ($password !== $confirm_password) {
        header("Location: register_voter.php?status=password_mismatch");
        exit();
    }

    /* FIX: Validate age (must be 18+) */
    try {
        $dob = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($dob)->y;

        if ($age < 18) {
            header("Location: register_voter.php?status=underage");
            exit();
        }
        if ($dob > $today) {
            header("Location: register_voter.php?status=future_dob");
            exit();
        }
    } catch (Exception $e) {
        header("Location: register_voter.php?status=invalid_dob");
        exit();
    }

    // Check duplicate ID
    $checkId = mysqli_prepare($conn, "SELECT id FROM users WHERE id_number = ? LIMIT 1");
    mysqli_stmt_bind_param($checkId, "s", $id_number);
    mysqli_stmt_execute($checkId);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkId)) > 0) {
        header("Location: register_voter.php?status=duplicate_id");
        exit();
    }

    // Check duplicate email
    $checkEmail = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($checkEmail, "s", $email);
    mysqli_stmt_execute($checkEmail);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkEmail)) > 0) {
        header("Location: register_voter.php?status=duplicate_email");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = "voter";

    // Insert voter (password_changed = 0 forces first-login password change)
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO users (id_number, name, email, date_of_birth, region_id, password, role, password_changed) VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
    );
    mysqli_stmt_bind_param($stmt, "ssssiss", $id_number, $name, $email, $date_of_birth, $region_id, $hashedPassword, $role);

    if (mysqli_stmt_execute($stmt)) {
        $new_voter_id = mysqli_insert_id($conn);

        // Log activity
        log_activity($user_id, "Registered Voter: $name (ID: $id_number)");

        header("Location: register_voter.php?status=success&voter=" . urlencode($name));
        exit();
    } else {
        header("Location: register_voter.php?status=error");
        exit();
    }
}

/* ==================== FETCH STATS ==================== */
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_voters,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_voters,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_voters
    FROM users 
    WHERE role = 'voter'
");
$stats = mysqli_fetch_assoc($statsQuery);

/* ==================== FETCH RECENT VOTERS ==================== */
$recentVoters = mysqli_query($conn, "
    SELECT u.*, r.name as region_name
    FROM users u
    LEFT JOIN regions r ON u.region_id = r.id
    WHERE u.role = 'voter'
    ORDER BY u.created_at DESC
    LIMIT 10
");

/* ==================== FETCH REGIONS ==================== */
$regions = mysqli_query($conn, "SELECT id, name FROM regions ORDER BY name ASC");
if ($regions === false) {
    die("Database error fetching regions.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Voter - VoteSystem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        .top-bar {
            background: white;
            padding: 24px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .top-bar h1 {
            font-size: 26px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .top-bar h1 i { color: #10b981; }
        .top-bar p { color: #64748b; font-size: 14px; margin-top: 4px; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #059669;
        }
        .stat-card .label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .card h3 {
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h3 i { color: #10b981; }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        .form-group label span { color: #ef4444; }
        .form-group input, .form-group select {
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
        }
        .form-group small {
            color: #64748b;
            font-size: 12px;
        }
        .btn-primary {
            padding: 12px 24px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-primary:hover { background: #059669; transform: translateY(-1px); }

        /* Recent Voters Table */
        .voters-table { width: 100%; border-collapse: collapse; }
        .voters-table th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .voters-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .voters-table tr:hover { background: #f8fafc; }
        .voter-info { display: flex; align-items: center; gap: 12px; }
        .voter-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 13px; font-weight: 700;
        }
        .voter-details { display: flex; flex-direction: column; }
        .voter-name { font-weight: 600; color: #0f172a; }
        .voter-id { font-size: 12px; color: #94a3b8; }
        .region-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }
        .date-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 6px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h1><i class="fas fa-user-plus"></i> Register Voter</h1>
            <p>Register new voters on their behalf. All fields are required.</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?php echo $stats['total_voters'] ?? 0; ?></div>
            <div class="label">Total Voters</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color: #2563eb;"><?php echo $stats['today_voters'] ?? 0; ?></div>
            <div class="label">Registered Today</div>
        </div>
        <div class="stat-card">
            <div class="value" style="color: #8b5cf6;"><?php echo $stats['week_voters'] ?? 0; ?></div>
            <div class="label">This Week</div>
        </div>
    </div>

    <!-- Registration Form -->
    <div class="card">
        <h3><i class="fas fa-id-card"></i> Voter Registration Form</h3>
        <form method="POST">
            <?php echo csrf_input_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>ID Number <span>*</span></label>
                    <input type="text" name="id_number" placeholder="6-12 digits" required pattern="[0-9]{6,12}">
                    <small>Unique identification number (numbers only)</small>
                </div>
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <input type="text" name="name" placeholder="Enter full name" required>
                    <small>As it appears on official documents</small>
                </div>
                <div class="form-group">
                    <label>Email Address <span>*</span></label>
                    <input type="email" name="email" placeholder="email@example.com" required>
                    <small>Valid email for notifications</small>
                </div>
                <div class="form-group">
                    <label>Date of Birth <span>*</span> <small style="color:#6b7280;">(Must be 18+)</small></label>
                    <input type="date" name="date_of_birth" id="date_of_birth" required max="">
                </div>
                <div class="form-group">
                    <label>Region / Campus <span>*</span></label>
                    <select name="region_id" required>
                        <option value="">-- Select Region --</option>
                        <?php while ($region = mysqli_fetch_assoc($regions)): ?>
                            <option value="<?php echo $region['id']; ?>">
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small>Voter's institutional location</small>
                </div>
                <div class="form-group">
                    <label>Password <span>*</span></label>
                    <input type="password" name="password" placeholder="Minimum 8 characters" required minlength="8">
                    <small>Temporary password - voter should change after first login</small>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span>*</span></label>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="8">
                </div>
            </div>
            <button type="submit" name="register_voter" class="btn-primary">
                <i class="fas fa-user-check"></i> Register Voter
            </button>
        </form>
    </div>

    <!-- Recent Voters -->
    <div class="card">
        <h3><i class="fas fa-users"></i> Recently Registered Voters</h3>

        <?php if ($recentVoters && mysqli_num_rows($recentVoters) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="voters-table">
                <thead>
                    <tr>
                        <th>Voter</th>
                        <th>ID Number</th>
                        <th>Email</th>
                        <th>Region</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($voter = mysqli_fetch_assoc($recentVoters)): ?>
                    <tr>
                        <td>
                            <div class="voter-info">
                                <div class="voter-avatar"><?php echo strtoupper(substr($voter['name'], 0, 1)); ?></div>
                                <div class="voter-details">
                                    <span class="voter-name"><?php echo htmlspecialchars($voter['name']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($voter['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                        <td>
                            <?php if ($voter['region_name']): ?>
                                <span class="region-badge">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($voter['region_name']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="date-badge">
                                <i class="fas fa-clock"></i>
                                <?php echo date('M d, Y', strtotime($voter['created_at'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 12px; display: block; color: #cbd5e1;"></i>
            <p>No voters registered yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Set max date to 18 years ago
    const today = new Date();
    const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    document.getElementById('date_of_birth').max = maxDate.toISOString().split('T')[0];
</script>

<?php if (isset($_GET['status'])): ?>
<script>
<?php if ($_GET['status'] === "success"): ?>
    Swal.fire({
        icon: 'success', 
        title: 'Voter Registered!',
        text: '<?php echo htmlspecialchars($_GET["voter"] ?? "The voter"); ?> has been registered successfully.',
        confirmButtonColor: '#10b981', 
        timer: 2500
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error', title: 'Registration Failed!',
        text: 'Could not register voter. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error', title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "duplicate_id"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate ID!',
        text: 'A voter with this ID number already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "duplicate_email"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate Email!',
        text: 'A voter with this email already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "invalid_id"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid ID!',
        text: 'ID must be 6-12 digits (numbers only).',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "invalid_name"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Name!',
        text: 'Name must be 3-100 characters and contain only letters, spaces, and basic punctuation.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "invalid_email"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Email!',
        text: 'Please enter a valid email address.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "short_password"): ?>
    Swal.fire({
        icon: 'warning', title: 'Password Too Short!',
        text: 'Password must be at least 8 characters long.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "password_mismatch"): ?>
    Swal.fire({
        icon: 'warning', title: 'Passwords Do Not Match!',
        text: 'Please ensure both passwords are identical.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "underage"): ?>
    Swal.fire({
        icon: 'warning', title: 'Age Requirement Not Met!',
        text: 'Voter must be at least 18 years old.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "future_dob"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Date of Birth!',
        text: 'Date of birth cannot be in the future.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php elseif ($_GET['status'] === "invalid_dob"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Date of Birth!',
        text: 'Please enter a valid date of birth.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'register_voter.php'); });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>