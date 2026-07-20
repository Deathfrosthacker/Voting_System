<?php
require_once "./config/connection.php";
require_once "./csrf_helper.php";
require_once "./rbac_helper.php";

// RBAC: Only admin can manage officials
check_session_timeout();
require_auth(['admin']);

$user_id = $_SESSION['user_id'];

/* HANDLE ADD OFFICIAL */
if (isset($_POST['add_official'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: manage_officials.php?status=csrf_error");
        exit();
    }

    $id_number = trim($_POST['id_number']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['official_role']; // election_officer or observer
    $password = $_POST['password'];

    // Validations
    if (!preg_match('/^[0-9]{6,12}$/', $id_number)) {
        header("Location: manage_officials.php?status=invalid_id");
        exit();
    }
    if (!preg_match("/^[a-zA-Z\s.'-]{3,100}$/", $name)) {
        header("Location: manage_officials.php?status=invalid_name");
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: manage_officials.php?status=invalid_email");
        exit();
    }
    if (!in_array($role, ['election_officer', 'observer'])) {
        header("Location: manage_officials.php?status=invalid_role");
        exit();
    }
    if (strlen($password) < 8) {
        header("Location: manage_officials.php?status=short_password");
        exit();
    }

    // Check duplicate ID
    $checkId = mysqli_prepare($conn, "SELECT id FROM users WHERE id_number = ? LIMIT 1");
    mysqli_stmt_bind_param($checkId, "s", $id_number);
    mysqli_stmt_execute($checkId);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkId)) > 0) {
        header("Location: manage_officials.php?status=duplicate_id");
        exit();
    }

    // Check duplicate email
    $checkEmail = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($checkEmail, "s", $email);
    mysqli_stmt_execute($checkEmail);
    if (mysqli_num_rows(mysqli_stmt_get_result($checkEmail)) > 0) {
        header("Location: manage_officials.php?status=duplicate_email");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO users (id_number, name, email, password, role) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sssss", $id_number, $name, $email, $hashedPassword, $role);

    if (mysqli_stmt_execute($stmt)) {
        $new_user_id = mysqli_insert_id($conn);

        // Insert into officials table
        $officialStmt = mysqli_prepare($conn, 
            "INSERT INTO officials (user_id, role, added_by, status) VALUES (?, ?, ?, 'active')"
        );
        mysqli_stmt_bind_param($officialStmt, "isi", $new_user_id, $role, $user_id);
        mysqli_stmt_execute($officialStmt);

        // Log activity
        log_activity($user_id, "Added Official: $name ($role)");

        header("Location: manage_officials.php?status=success");
        exit();
    } else {
        header("Location: manage_officials.php?status=error");
        exit();
    }
}

/* HANDLE TOGGLE STATUS */
if (isset($_POST['toggle_status'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        header("Location: manage_officials.php?status=csrf_error");
        exit();
    }

    $official_id = (int)$_POST['official_id'];
    $new_status = $_POST['new_status'];

    if (!in_array($new_status, ['active', 'suspended'])) {
        header("Location: manage_officials.php?status=invalid_status");
        exit();
    }

    // Update officials table
    $updStmt = mysqli_prepare($conn, "UPDATE officials SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($updStmt, "si", $new_status, $official_id);

    if (mysqli_stmt_execute($updStmt)) {
        // Get official info for logging
        $infoStmt = mysqli_prepare($conn, 
            "SELECT u.name, o.role FROM officials o JOIN users u ON o.user_id = u.id WHERE o.id = ?"
        );
        mysqli_stmt_bind_param($infoStmt, "i", $official_id);
        mysqli_stmt_execute($infoStmt);
        $info = mysqli_fetch_assoc(mysqli_stmt_get_result($infoStmt));

        log_activity($user_id, "Changed status of {$info['name']} ({$info['role']}) to $new_status");

        header("Location: manage_officials.php?status=status_updated");
        exit();
    }
}

/* FETCH ALL OFFICIALS */
$officials = mysqli_query($conn, "
    SELECT o.id, o.user_id, o.role, o.status, o.created_at, o.added_by,
           u.id_number, u.name, u.email,
           a.name as added_by_name
    FROM officials o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN users a ON o.added_by = a.id
    ORDER BY o.created_at DESC
");

/* FETCH STATS*/
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'election_officer' THEN 1 ELSE 0 END) as election_officers,
        SUM(CASE WHEN role = 'observer' THEN 1 ELSE 0 END) as observers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
    FROM officials
");
$stats = mysqli_fetch_assoc($statsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officials - VoteSystem</title>
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
        .top-bar h1 i { color: #2563eb; }
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
            color: #1e40af;
        }
        .stat-card .label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .stat-card.active .value { color: #059669; }
        .stat-card.suspended .value { color: #dc2626; }
        .stat-card.officer .value { color: #2563eb; }
        .stat-card.observer .value { color: #7c3aed; }

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
        .card h3 i { color: #2563eb; }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
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
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .btn-primary {
            padding: 12px 24px;
            background: #2563eb;
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
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* Officials Table */
        .officials-table { width: 100%; border-collapse: collapse; }
        .officials-table th {
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
        .officials-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .officials-table tr:hover { background: #f8fafc; }

        .official-info { display: flex; align-items: center; gap: 12px; }
        .official-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 14px; font-weight: 700;
        }
        .official-details { display: flex; flex-direction: column; }
        .official-name { font-weight: 600; color: #0f172a; }
        .official-id { font-size: 12px; color: #94a3b8; }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-election_officer { background: #dbeafe; color: #1e40af; }
        .badge-observer { background: #d1fae5; color: #065f46; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-suspended { background: #fee2e2; color: #991b1b; }

        .btn-toggle {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-suspend { background: #fee2e2; color: #dc2626; }
        .btn-suspend:hover { background: #fecaca; }
        .btn-activate { background: #d1fae5; color: #059669; }
        .btn-activate:hover { background: #a7f3d0; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; color: #cbd5e1; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .officials-table { font-size: 12px; }
            .officials-table th, .officials-table td { padding: 10px 8px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h1><i class="fas fa-user-shield"></i> Manage Officials</h1>
            <p>Add and manage Election Officers and Observers</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="label">Total Officials</div>
        </div>
        <div class="stat-card officer">
            <div class="value"><?php echo $stats['election_officers'] ?? 0; ?></div>
            <div class="label">Election Officers</div>
        </div>
        <div class="stat-card observer">
            <div class="value"><?php echo $stats['observers'] ?? 0; ?></div>
            <div class="label">Observers</div>
        </div>
        <div class="stat-card active">
            <div class="value"><?php echo $stats['active'] ?? 0; ?></div>
            <div class="label">Active</div>
        </div>
        <div class="stat-card suspended">
            <div class="value"><?php echo $stats['suspended'] ?? 0; ?></div>
            <div class="label">Suspended</div>
        </div>
    </div>

    <!-- Add Official Form -->
    <div class="card">
        <h3><i class="fas fa-user-plus"></i> Add New Official</h3>
        <form method="POST">
            <?php echo csrf_input_field(); ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>ID Number <span>*</span></label>
                    <input type="text" name="id_number" placeholder="6-12 digits" required pattern="[0-9]{6,12}">
                </div>
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <input type="text" name="name" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Email <span>*</span></label>
                    <input type="email" name="email" placeholder="email@example.com" required>
                </div>
                <div class="form-group">
                    <label>Role <span>*</span></label>
                    <select name="official_role" required>
                        <option value="">Select Role</option>
                        <option value="election_officer">Election Officer</option>
                        <option value="observer">Observer / Auditor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <span>*</span> <small style="color:#64748b;">(min 8 chars)</small></label>
                    <input type="password" name="password" placeholder="8+ characters" required minlength="8">
                </div>
            </div>
            <button type="submit" name="add_official" class="btn-primary">
                <i class="fas fa-plus"></i> Add Official
            </button>
        </form>
    </div>

    <!-- Officials List -->
    <div class="card">
        <h3><i class="fas fa-list"></i> All Officials</h3>

        <?php if ($officials && mysqli_num_rows($officials) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="officials-table">
                <thead>
                    <tr>
                        <th>Official</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Added By</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($off = mysqli_fetch_assoc($officials)): ?>
                    <tr>
                        <td>
                            <div class="official-info">
                                <div class="official-avatar"><?php echo strtoupper(substr($off['name'], 0, 1)); ?></div>
                                <div class="official-details">
                                    <span class="official-name"><?php echo htmlspecialchars($off['name']); ?></span>
                                    <span class="official-id">ID: <?php echo htmlspecialchars($off['id_number']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge badge-<?php echo $off['role']; ?>">
                                <i class="fas fa-<?php echo $off['role'] === 'election_officer' ? 'user-tie' : 'eye'; ?>"></i>
                                <?php echo get_role_display_name($off['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $off['status']; ?>">
                                <i class="fas fa-<?php echo $off['status'] === 'active' ? 'check-circle' : 'pause-circle'; ?>"></i>
                                <?php echo ucfirst($off['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($off['added_by_name'] ?? 'System'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($off['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_input_field(); ?>
                                <input type="hidden" name="official_id" value="<?php echo $off['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $off['status'] === 'active' ? 'suspended' : 'active'; ?>">
                                <button type="submit" name="toggle_status" class="btn-toggle btn-<?php echo $off['status'] === 'active' ? 'suspend' : 'activate'; ?>">
                                    <i class="fas fa-<?php echo $off['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                    <?php echo $off['status'] === 'active' ? 'Suspend' : 'Activate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No Officials Added</h3>
            <p>Add your first election officer or observer above.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
<script>
<?php if ($_GET['status'] === "success"): ?>
    Swal.fire({
        icon: 'success', title: 'Official Added!',
        text: 'The official has been registered successfully.',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "status_updated"): ?>
    Swal.fire({
        icon: 'success', title: 'Status Updated!',
        text: 'Official status has been changed.',
        confirmButtonColor: '#2563eb', timer: 2000
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "csrf_error"): ?>
    Swal.fire({
        icon: 'error', title: 'Security Error!',
        text: 'Invalid CSRF token. Please refresh and try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "duplicate_id"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate ID!',
        text: 'An official with this ID number already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "duplicate_email"): ?>
    Swal.fire({
        icon: 'warning', title: 'Duplicate Email!',
        text: 'An official with this email already exists.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "invalid_id"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid ID!',
        text: 'ID must be 6-12 digits.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "invalid_name"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Name!',
        text: 'Name must be 3-100 characters.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "invalid_email"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Email!',
        text: 'Please enter a valid email address.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "invalid_role"): ?>
    Swal.fire({
        icon: 'warning', title: 'Invalid Role!',
        text: 'Please select a valid official role.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "short_password"): ?>
    Swal.fire({
        icon: 'warning', title: 'Password Too Short!',
        text: 'Password must be at least 8 characters.',
        confirmButtonColor: '#f59e0b'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php elseif ($_GET['status'] === "error"): ?>
    Swal.fire({
        icon: 'error', title: 'Error!',
        text: 'Something went wrong. Please try again.',
        confirmButtonColor: '#ef4444'
    }).then(() => { window.history.replaceState({}, document.title, 'manage_officials.php'); });
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>