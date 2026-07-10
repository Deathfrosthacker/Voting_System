<?php
// MUST be first - set the correct session name before rbac_helper starts it
ini_set('session.cookie_path', '/');
session_name('OBSERVER_SESSION');
session_start();

require_once "./config/connection.php";
require_once "./auto_declare.php";
require_once "./rbac_helper.php";

/* RBAC: Only observer can access */
check_session_timeout();
require_auth(['observer']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* Observer stats - read-only view */
$totalPositions = 0;
$totalCandidates = 0;
$totalVotes = 0;
$totalVoters = 0;
$completedElections = 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM positions");
$totalPositions = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM candidates");
$totalCandidates = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM votes");
$totalVotes = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'voter'");
$totalVoters = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM election_results");
$completedElections = $res ? mysqli_fetch_assoc($res)['total'] : 0;

/* Latest Election Results */
$resultsQuery = "
    SELECT position_name, winner_name, total_votes, end_date, declared_at
    FROM election_results
    ORDER BY declared_at DESC
    LIMIT 10
";
$resultsResult = mysqli_query($conn, $resultsQuery);

/* Activity Logs (Observer can view all logs for transparency) */
$logsQuery = "
    SELECT logs.activity, logs.log_time, users.name, users.id_number, users.role
    FROM logs
    JOIN users ON logs.user_id = users.id
    ORDER BY logs.log_time DESC
    LIMIT 15
";
$logsResult = mysqli_query($conn, $logsQuery);

/* Active Elections */
$activeElectionsQuery = "
    SELECT p.*, r.name as region_name
    FROM positions p
    LEFT JOIN regions r ON p.region_id = r.id
    WHERE CURDATE() BETWEEN p.start_date AND p.end_date
    ORDER BY p.end_date ASC
";
$activeElectionsResult = mysqli_query($conn, $activeElectionsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observer Dashboard | Voting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: #f1f5f9;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title-section h1 {
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .title-section p {
            font-size: 14px;
            color: #64748b;
        }
        .role-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
        }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.teal::before { background: linear-gradient(90deg, #14b8a6, #0d9488); }
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .stat-card h3 {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }
        .icon-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .icon-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .icon-green { background: linear-gradient(135deg, #10b981, #059669); }
        .icon-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .icon-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .stat-card p {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .read-only-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
        }
        .section-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        .section-card h2 {
            color: #1e293b;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-card h2 i { color: #059669; }
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        table thead {
            background: linear-gradient(135deg, #059669, #047857);
        }
        table th {
            padding: 12px 16px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 12px 16px;
            color: #334155;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        table tbody tr { transition: all 0.2s; }
        table tbody tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-officer { background: #dbeafe; color: #1e40af; }
        .badge-voter { background: #f3f4f6; color: #6b7280; }
        .two-column {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .quick-link {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .quick-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
            border-color: #059669;
        }
        .quick-link-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        .quick-link-icon.logs { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .quick-link-icon.results { background: linear-gradient(135deg, #10b981, #059669); }
        .quick-link-icon.votes { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .quick-link-content h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 3px;
        }
        .quick-link-content p {
            font-size: 12px;
            color: #64748b;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
            color: #cbd5e1;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; gap: 16px; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .two-column { grid-template-columns: 1fr; }
            .quick-links { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div class="title-section">
            <h1><i class="fas fa-eye" style="color: #059669; margin-right: 10px;"></i>Observer Dashboard</h1>
            <p>Read-only access for transparency and audit purposes</p>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="read-only-badge">
                <i class="fas fa-lock"></i> Read-Only Access
            </span>
            <span class="role-display" style="background: <?php echo get_role_bg_color($role); ?>; color: <?php echo get_role_color($role); ?>">
                <i class="fas fa-shield-alt"></i>
                <?php echo get_role_display_name($role); ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Observer Notice -->
    <div class="observer-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Observer Mode:</strong> You have read-only access to system data for transparency and audit purposes. You cannot modify any data.
        </div>
    </div>

    <!-- QUICK LINKS -->
    <div class="quick-links">
        <a href="activity_logs.php" class="quick-link">
            <div class="quick-link-icon logs"><i class="fas fa-history"></i></div>
            <div class="quick-link-content">
                <h4>Activity Logs</h4>
                <p>View complete audit trail</p>
            </div>
        </a>
        <a href="winners.php" class="quick-link">
            <div class="quick-link-icon results"><i class="fas fa-trophy"></i></div>
            <div class="quick-link-content">
                <h4>Election Results</h4>
                <p>View completed election winners</p>
            </div>
        </a>
        <a href="votes.php" class="quick-link">
            <div class="quick-link-icon votes"><i class="fas fa-vote-yea"></i></div>
            <div class="quick-link-content">
                <h4>Votes Overview</h4>
                <p>Monitor vote counts per position</p>
            </div>
        </a>
    </div>

    <!-- STATS CARDS -->
    <div class="stats">
        <div class="stat-card blue">
            <div class="stat-card-header">
                <h3>Total Positions</h3>
                <div class="stat-icon icon-blue"><i class="fas fa-briefcase"></i></div>
            </div>
            <p><?php echo $totalPositions; ?></p>
        </div>
        <div class="stat-card purple">
            <div class="stat-card-header">
                <h3>Total Candidates</h3>
                <div class="stat-icon icon-purple"><i class="fas fa-users"></i></div>
            </div>
            <p><?php echo $totalCandidates; ?></p>
        </div>
        <div class="stat-card green">
            <div class="stat-card-header">
                <h3>Total Votes Cast</h3>
                <div class="stat-icon icon-green"><i class="fas fa-vote-yea"></i></div>
            </div>
            <p><?php echo $totalVotes; ?></p>
        </div>
        <div class="stat-card orange">
            <div class="stat-card-header">
                <h3>Registered Voters</h3>
                <div class="stat-icon icon-orange"><i class="fas fa-user-check"></i></div>
            </div>
            <p><?php echo $totalVoters; ?></p>
        </div>
        <div class="stat-card teal">
            <div class="stat-card-header">
                <h3>Completed Elections</h3>
                <div class="stat-icon icon-teal"><i class="fas fa-check-circle"></i></div>
            </div>
            <p><?php echo $completedElections; ?></p>
        </div>
    </div>

    <!-- ACTIVE ELECTIONS -->
    <div class="section-card" style="margin-bottom: 24px;">
        <h2><i class="fas fa-bolt"></i> Active Elections</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Region</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($activeElectionsResult && mysqli_num_rows($activeElectionsResult) > 0): ?>
                        <?php while ($pos = mysqli_fetch_assoc($activeElectionsResult)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pos['position_name']); ?></strong></td>
                            <td><?php echo $pos['region_name'] ? htmlspecialchars($pos['region_name']) : '<span style="color:#94a3b8;">Global</span>'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($pos['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($pos['end_date'])); ?></td>
                            <td><span class="badge badge-success"><i class="fas fa-circle" style="font-size:8px;margin-right:4px;"></i>Active</span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 30px;">
                                <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 8px; display: block; color: #cbd5e1;"></i>
                                No active elections at this time
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TWO COLUMN LAYOUT -->
    <div class="two-column">
        <!-- ELECTION RESULTS -->
        <div class="section-card">
            <h2><i class="fas fa-trophy"></i> Recent Results</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Winner</th>
                            <th>Votes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultsResult && mysqli_num_rows($resultsResult) > 0): ?>
                            <?php while ($res = mysqli_fetch_assoc($resultsResult)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($res['position_name']); ?></strong></td>
                                <td><span class="badge badge-success"><?php echo htmlspecialchars($res['winner_name']); ?></span></td>
                                <td style="font-weight: 700; color: #2563eb;"><?php echo $res['total_votes']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #94a3b8; padding: 30px;">
                                    <i class="fas fa-hourglass-half" style="font-size: 24px; margin-bottom: 8px; display: block; color: #cbd5e1;"></i>
                                    No completed elections yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="section-card">
            <h2><i class="fas fa-history"></i> Recent Activity</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logsResult && mysqli_num_rows($logsResult) > 0): ?>
                            <?php while ($log = mysqli_fetch_assoc($logsResult)): 
                                $log_role = $log['role'] ?? 'voter';
                                $badge_class = match($log_role) {
                                    'admin' => 'badge-admin',
                                    'election_officer' => 'badge-officer',
                                    'observer' => 'badge-info',
                                    default => 'badge-voter'
                                };
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($log['name']); ?></strong></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($log_role); ?></span></td>
                                <td><?php echo htmlspecialchars($log['activity']); ?></td>
                                <td style="font-size: 12px; color: #94a3b8;"><?php echo date('M d, h:i A', strtotime($log['log_time'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 8px; display: block; color: #cbd5e1;"></i>
                                    No activity logs yet
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

</body>
</html>

<?php 
if (isset($resultsResult) && $resultsResult) mysqli_free_result($resultsResult);
if (isset($logsResult) && $logsResult) mysqli_free_result($logsResult);
if (isset($activeElectionsResult) && $activeElectionsResult) mysqli_free_result($activeElectionsResult);
if (isset($conn)) mysqli_close($conn);
?>