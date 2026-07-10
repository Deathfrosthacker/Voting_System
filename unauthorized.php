<?php
// FIX: Don't call session_start here - rbac_helper.php handles it
require_once "./rbac_helper.php";

// Ensure session is active with proper cookie path
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    session_start();
}

// Determine correct dashboard URL based on role
$role = $_SESSION['role'] ?? '';
$dashboard_url = match($role) {
    'admin' => 'admin_dashboard.php',
    'election_officer' => 'election_officer_dashboard.php',
    'observer' => 'observer_dashboard.php',
    default => 'voter_dashboard.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access | Voting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        .card {
            background: white;
            padding: 60px 50px;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .icon {
            width: 100px;
            height: 100px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        .icon i {
            font-size: 48px;
            color: #dc2626;
        }
        h1 {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 12px;
        }
        p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .role-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .role-info h3 {
            font-size: 14px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            margin-left: 12px;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .permissions-list {
            text-align: left;
            margin-top: 20px;
        }
        .permissions-list h4 {
            font-size: 13px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .permissions-list ul {
            list-style: none;
            padding: 0;
        }
        .permissions-list li {
            padding: 8px 0;
            color: #475569;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .permissions-list li:last-child {
            border-bottom: none;
        }
        .permissions-list li i {
            color: #10b981;
            width: 16px;
        }
        .permissions-list li.denied i {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page. This area requires higher privileges than your current role allows.</p>

        <?php 
        if (isset($_SESSION['role'])): 
            $role = $_SESSION['role'];
            $permissions = get_role_permissions($role);
        ?>
        <div class="role-info">
            <h3>Your Current Role</h3>
            <span class="role-badge" style="background: <?php echo get_role_bg_color($role); ?>; color: <?php echo get_role_color($role); ?>">
                <i class="fas fa-user-tag" style="margin-right: 6px;"></i>
                <?php echo get_role_display_name($role); ?>
            </span>

            <div class="permissions-list">
                <h4>Your Permissions</h4>
                <ul>
                    <?php foreach ($permissions as $perm): 
                        $perm_name = str_replace('_', ' ', $perm);
                        $perm_name = ucwords($perm_name);
                    ?>
                    <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($perm_name); ?></li>
                    <?php endforeach; ?>
                    <?php if (empty($permissions)): ?>
                    <li class="denied"><i class="fas fa-times-circle"></i> No special permissions</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="btn btn-primary">
                <i class="fas fa-home"></i> Go to Dashboard
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</body>
</html>