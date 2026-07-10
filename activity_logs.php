<?php
// Session is started by check_session_timeout() in rbac_helper.php
require_once "./config/connection.php";
require_once "./rbac_helper.php";

// Admin, Election Officer and Observer can view logs
check_session_timeout();
require_auth(['admin', 'election_officer', 'observer']);

$current_role = $_SESSION['role'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filter
$filter_role = $_GET['filter_role'] ?? '';
$filter_activity = $_GET['filter_activity'] ?? '';

// Build query
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter_role) {
    $where .= " AND u.role = ?";
    $params[] = $filter_role;
    $types .= "s";
}
if ($filter_activity) {
    $where .= " AND l.activity LIKE ?";
    $params[] = "%$filter_activity%";
    $types .= "s";
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM logs l JOIN users u ON l.user_id = u.id $where";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total / $per_page);

// Fetch logs with user details
$sql = "
    SELECT l.*, u.name, u.id_number, u.role
    FROM logs l
    JOIN users u ON l.user_id = u.id
    $where
    ORDER BY l.log_time DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $sql);

// Bind all params including limit/offset
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . "ii";
mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);

// Get unique activity types for filter
$activities = mysqli_query($conn, "SELECT DISTINCT activity FROM logs ORDER BY activity ASC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - VoteSystem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .top-bar h1 {
            font-size: 26px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .top-bar h1 i { color: #f59e0b; }
        .top-bar p { color: #64748b; font-size: 14px; margin-top: 4px; }

        .observer-notice {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #92400e;
            font-size: 14px;
        }
        .observer-notice i { font-size: 20px; }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select, .filter-group input {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 180px;
            background: white;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .btn-filter {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-filter:hover { background: #1d4ed8; }
        .btn-reset {
            padding: 10px 20px;
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-reset:hover { background: #e2e8f0; }

        .logs-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .logs-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logs-header h3 { font-size: 16px; color: #0f172a; }
        .logs-count { font-size: 13px; color: #64748b; }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        tr:hover { background: #f8fafc; }

        .log-user { display: flex; align-items: center; gap: 10px; }
        .user-avatar-small {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 12px; font-weight: 700;
        }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: #0f172a; font-size: 13px; }
        .user-id { font-size: 11px; color: #94a3b8; }

        .activity-text { font-size: 13px; color: #475569; line-height: 1.4; }
        .activity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-login { background: #dbeafe; color: #1e40af; }
        .badge-logout { background: #fee2e2; color: #991b1b; }
        .badge-vote { background: #d1fae5; color: #065f46; }
        .badge-position { background: #e0e7ff; color: #3730a3; }
        .badge-candidate { background: #fef3c7; color: #92400e; }
        .badge-admin { background: #f3e8ff; color: #7c3aed; }
        .badge-default { background: #f1f5f9; color: #475569; }

        .log-time { font-size: 12px; color: #94a3b8; white-space: nowrap; }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .page-link {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .page-link:hover { background: #f1f5f9; }
        .page-link.active { background: #2563eb; color: white; border-color: #2563eb; }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; color: #cbd5e1; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .top-bar { flex-direction: column; align-items: flex-start; }
            .filters { flex-direction: column; }
            table { font-size: 12px; }
            th, td { padding: 10px 12px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h1><i class="fas fa-clock"></i> Activity Logs</h1>
            <p>Complete audit trail of all system activities</p>
        </div>
        <?php if (is_observer()): ?>
        <span class="activity-badge badge-admin" style="font-size: 12px;">
            <i class="fas fa-eye"></i> Read-Only Mode
        </span>
        <?php endif; ?>
    </div>

    <?php if (is_observer()): ?>
    <div class="observer-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Observer Access:</strong> You have read-only access to system logs for transparency and audit purposes. 
            You cannot modify any data.
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="filters" method="GET">
        <div class="filter-group">
            <label>Filter by Role</label>
            <select name="filter_role">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="election_officer" <?php echo $filter_role === 'election_officer' ? 'selected' : ''; ?>>Election Officer</option>
                <option value="observer" <?php echo $filter_role === 'observer' ? 'selected' : ''; ?>>Observer</option>
                <option value="voter" <?php echo $filter_role === 'voter' ? 'selected' : ''; ?>>Voter</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Search Activity</label>
            <input type="text" name="filter_activity" placeholder="e.g., Login, Vote..." 
                   value="<?php echo htmlspecialchars($filter_activity); ?>">
        </div>
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
        <a href="activity_logs.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
    </form>

    <!-- Logs Table -->
    <div class="logs-card">
        <div class="logs-header">
            <h3><i class="fas fa-list"></i> Log Entries</h3>
            <span class="logs-count"><?php echo $total; ?> total entries</span>
        </div>

        <?php if (mysqli_num_rows($logs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Activity</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($log = mysqli_fetch_assoc($logs)): 
                    // Determine badge type
                    $activity_lower = strtolower($log['activity']);
                    if (strpos($activity_lower, 'login') !== false) $badge_class = 'badge-login';
                    elseif (strpos($activity_lower, 'logout') !== false) $badge_class = 'badge-logout';
                    elseif (strpos($activity_lower, 'voted') !== false) $badge_class = 'badge-vote';
                    elseif (strpos($activity_lower, 'position') !== false) $badge_class = 'badge-position';
                    elseif (strpos($activity_lower, 'candidate') !== false) $badge_class = 'badge-candidate';
                    elseif (strpos($activity_lower, 'admin') !== false || strpos($activity_lower, 'official') !== false) $badge_class = 'badge-admin';
                    else $badge_class = 'badge-default';
                ?>
                <tr>
                    <td>
                        <div class="log-user">
                            <div class="user-avatar-small"><?php echo strtoupper(substr($log['name'], 0, 1)); ?></div>
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($log['name']); ?></span>
                                <span class="user-id">ID: <?php echo htmlspecialchars($log['id_number']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo get_role_badge($log['role']); ?></td>
                    <td>
                        <span class="activity-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($log['activity']); ?>
                        </span>
                    </td>
                    <td class="log-time">
                        <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($log['log_time'])); ?><br>
                        <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($log['log_time'])); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?page=<?php echo max(1, $page-1); ?>&filter_role=<?php echo urlencode($filter_role); ?>&filter_activity=<?php echo urlencode($filter_activity); ?>" 
               class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">
               <i class="fas fa-chevron-left"></i>
            </a>

            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter_role=<?php echo urlencode($filter_role); ?>&filter_activity=<?php echo urlencode($filter_activity); ?>" 
               class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <a href="?page=<?php echo min($total_pages, $page+1); ?>&filter_role=<?php echo urlencode($filter_role); ?>&filter_activity=<?php echo urlencode($filter_activity); ?>" 
               class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
               <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No logs found</h3>
            <p>No activity logs match your current filters.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>