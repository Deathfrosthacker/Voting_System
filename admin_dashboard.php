<?php
ini_set('session.cookie_path', '/');
session_name('ADMIN_SESSION');
session_start();

require_once "./config/connection.php";
require_once "./auto_declare.php";
require_once "./rbac_helper.php";

/* RBAC: Only admin can access */
check_session_timeout();
require_auth(['admin']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* Admin sees everything */
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM positions");
$totalPositions = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM candidates");
$totalCandidates = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM votes");
$totalVotes = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'voter'");
$totalVoters = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM officials WHERE status = 'active'");
$totalOfficials = $res ? mysqli_fetch_assoc($res)['total'] : 0;

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
$totalAdmins = $res ? mysqli_fetch_assoc($res)['total'] : 0;

/* Latest Election Results */
$resultsQuery = "
    SELECT position_name, winner_name, total_votes, end_date
    FROM election_results
    ORDER BY declared_at DESC
    LIMIT 5
";
$resultsResult = mysqli_query($conn, $resultsQuery);

/* Activity Logs */
$logsQuery = "
    SELECT logs.activity, logs.log_time, users.name, users.id_number, users.role
    FROM logs
    JOIN users ON logs.user_id = users.id
    ORDER BY logs.log_time DESC
    LIMIT 10
";
$logsResult = mysqli_query($conn, $logsQuery);

/* Latest Position */
$latestPosition = mysqli_query($conn, "
    SELECT position_name, start_date, end_date 
    FROM positions 
    ORDER BY id DESC 
    LIMIT 1
");

/* Latest Candidate */
$latestCandidate = mysqli_query($conn, "
    SELECT name, position, start_date, end_date 
    FROM candidates 
    ORDER BY id DESC 
    LIMIT 1
");

/* Active Elections Count */
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM positions WHERE CURDATE() BETWEEN start_date AND end_date");
$activeElections = $res ? mysqli_fetch_assoc($res)['total'] : 0;

/* System Health */
$totalRegions = 0;
$totalAffiliations = 0;
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM regions");
$totalRegions = $res ? mysqli_fetch_assoc($res)['total'] : 0;
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM affiliations");
$totalAffiliations = $res ? mysqli_fetch_assoc($res)['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Voting System</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 24px;
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
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .stat-card.teal::before { background: linear-gradient(90deg, #14b8a6, #0d9488); }
        .stat-card.pink::before { background: linear-gradient(90deg, #ec4899, #db2777); }
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .stat-card h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        .icon-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .icon-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .icon-green { background: linear-gradient(135deg, #10b981, #059669); }
        .icon-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .icon-red { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .icon-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .icon-pink { background: linear-gradient(135deg, #ec4899, #db2777); }
        .stat-card p {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        .info-card h3 {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-card h3 i { color: #2563eb; }
        .info-card .info-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 8px 0;
        }
        .info-card .info-meta {
            color: #64748b;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .info-card .no-data {
            color: #94a3b8;
            font-size: 14px;
            font-style: italic;
        }
        .logs-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .logs-section h2 {
            color: #1e293b;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logs-section h2 i { color: #2563eb; }
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
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
        }
        table th {
            padding: 14px 16px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 14px 16px;
            color: #334155;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        table tbody tr { transition: all 0.2s; }
        table tbody tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-officer { background: #dbeafe; color: #1e40af; }
        .badge-observer { background: #d1fae5; color: #065f46; }
        .badge-voter { background: #f3f4f6; color: #6b7280; }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .action-card {
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
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
            border-color: #dc2626;
        }
        .action-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        .action-icon.positions { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .action-icon.candidates { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .action-icon.officials { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .action-icon.admins { background: linear-gradient(135deg, #ec4899, #db2777); }
        .action-icon.regions { background: linear-gradient(135deg, #10b981, #059669); }
        .action-icon.affiliations { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .action-icon.diagnostic { background: linear-gradient(135deg, #6b7280, #4b5563); }
        .action-content h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 3px;
        }
        .action-content p {
            font-size: 12px;
            color: #64748b;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; gap: 16px; text-align: center; }
            .stats, .info-cards { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div class="title-section">
            <h1><i class="fas fa-crown" style="color: #dc2626; margin-right: 10px;"></i>System Administrator</h1>
            <p>Full system control and oversight</p>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span class="role-display" style="background: <?php echo get_role_bg_color($role); ?>; color: <?php echo get_role_color($role); ?>">
                <i class="fas fa-shield-alt"></i>
                <?php echo get_role_display_name($role); ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <h2 style="font-size: 18px; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-bolt" style="color: #f59e0b;"></i> Quick Actions
    </h2>
    <div class="quick-actions">
        <a href="positions.php" class="action-card">
            <div class="action-icon positions"><i class="fas fa-briefcase"></i></div>
            <div class="action-content">
                <h4>Manage Elections</h4>
                <p>Create and edit positions</p>
            </div>
        </a>
        <a href="add_candidate.php" class="action-card">
            <div class="action-icon candidates"><i class="fas fa-users"></i></div>
            <div class="action-content">
                <h4>Manage Candidates</h4>
                <p>Add and manage candidates</p>
            </div>
        </a>
        <a href="manage_officials.php" class="action-card">
            <div class="action-icon officials"><i class="fas fa-user-shield"></i></div>
            <div class="action-content">
                <h4>Manage Officials</h4>
                <p>Election officers & observers</p>
            </div>
        </a>
        <a href="register_voter.php" class="action-card">
            <div class="action-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-user-plus"></i></div>
            <div class="action-content">
                <h4>Register Voter</h4>
                <p>Register voters on their behalf</p>
            </div>
        </a>

        <a href="adminadd.php" class="action-card">
            <div class="action-icon admins"><i class="fas fa-user-cog"></i></div>
            <div class="action-content">
                <h4>Manage Admins</h4>
                <p>Add system administrators</p>
            </div>
        </a>
        <a href="regions.php" class="action-card">
            <div class="action-icon regions"><i class="fas fa-globe"></i></div>
            <div class="action-content">
                <h4>Manage Regions</h4>
                <p>Configure voting regions</p>
            </div>
        </a>
        <a href="affiliations.php" class="action-card">
            <div class="action-icon affiliations"><i class="fas fa-flag"></i></div>
            <div class="action-content">
                <h4>Affiliations</h4>
                <p>Parties and groups</p>
            </div>
        </a>
        <a href="diagnostic.php" class="action-card">
            <div class="action-icon diagnostic"><i class="fas fa-stethoscope"></i></div>
            <div class="action-content">
                <h4>Diagnostics</h4>
                <p>System health check</p>
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
        <div class="stat-card red">
            <div class="stat-card-header">
                <h3>Active Officials</h3>
                <div class="stat-icon icon-red"><i class="fas fa-user-shield"></i></div>
            </div>
            <p><?php echo $totalOfficials; ?></p>
        </div>
        <div class="stat-card pink">
            <div class="stat-card-header">
                <h3>Administrators</h3>
                <div class="stat-icon icon-pink"><i class="fas fa-user-cog"></i></div>
            </div>
            <p><?php echo $totalAdmins; ?></p>
        </div>
        <div class="stat-card teal">
            <div class="stat-card-header">
                <h3>Active Elections</h3>
                <div class="stat-icon icon-teal"><i class="fas fa-bolt"></i></div>
            </div>
            <p><?php echo $activeElections; ?></p>
        </div>
    </div>

    <!-- INFO CARDS -->
    <div class="info-cards">
        <?php if ($latestPosition): ?>
        <div class="info-card">
            <h3><i class="fas fa-clipboard-list"></i>Latest Position Added</h3>
            <?php if (mysqli_num_rows($latestPosition) > 0): 
                $pos = mysqli_fetch_assoc($latestPosition); ?>
                <div class="info-title"><?php echo htmlspecialchars($pos['position_name']); ?></div>
                <div class="info-meta">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('M d, Y', strtotime($pos['start_date'])); ?> 
                    &rarr; 
                    <?php echo date('M d, Y', strtotime($pos['end_date'])); ?>
                </div>
            <?php else: ?>
                <p class="no-data">No position added yet</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($latestCandidate): ?>
        <div class="info-card">
            <h3><i class="fas fa-user-plus"></i>Latest Candidate Added</h3>
            <?php if (mysqli_num_rows($latestCandidate) > 0): 
                $cand = mysqli_fetch_assoc($latestCandidate); ?>
                <div class="info-title"><?php echo htmlspecialchars($cand['name']); ?></div>
                <div class="info-meta"><i class="fas fa-tag"></i><?php echo htmlspecialchars($cand['position']); ?></div>
                <div class="info-meta">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('M d, Y', strtotime($cand['start_date'])); ?> 
                    &rarr; 
                    <?php echo date('M d, Y', strtotime($cand['end_date'])); ?>
                </div>
            <?php else: ?>
                <p class="no-data">No candidate added yet</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ELECTION RESULTS -->
    <div class="logs-section" style="margin-bottom: 30px;">
        <h2><i class="fas fa-trophy"></i> Recent Election Results</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-briefcase" style="margin-right: 6px;"></i>Position</th>
                        <th><i class="fas fa-crown" style="margin-right: 6px;"></i>Winner</th>
                        <th><i class="fas fa-vote-yea" style="margin-right: 6px;"></i>Votes</th>
                        <th><i class="fas fa-calendar-alt" style="margin-right: 6px;"></i>Ended On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultsResult && mysqli_num_rows($resultsResult) > 0): ?>
                        <?php while ($res = mysqli_fetch_assoc($resultsResult)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($res['position_name']); ?></strong></td>
                            <td><span class="badge badge-success"><?php echo htmlspecialchars($res['winner_name']); ?></span></td>
                            <td style="font-size: 18px; color: #2563eb; font-weight: 700;"><?php echo $res['total_votes']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($res['end_date'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8; padding: 40px;">
                                <i class="fas fa-hourglass-half" style="font-size: 36px; margin-bottom: 10px; display: block; color: #cbd5e1;"></i>
                                No elections have ended yet. Results will appear automatically.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ACTIVITY LOGS -->
    <?php if ($logsResult): ?>
    <div class="logs-section">
        <h2><i class="fas fa-history"></i> Recent Activity Logs</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card" style="margin-right: 6px;"></i>ID Number</th>
                        <th><i class="fas fa-user" style="margin-right: 6px;"></i>Name</th>
                        <th><i class="fas fa-user-tag" style="margin-right: 6px;"></i>Role</th>
                        <th><i class="fas fa-tasks" style="margin-right: 6px;"></i>Activity</th>
                        <th><i class="fas fa-clock" style="margin-right: 6px;"></i>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($logsResult) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($logsResult)): 
                            $log_role = $log['role'] ?? 'voter';
                            $badge_class = match($log_role) {
                                'admin' => 'badge-admin',
                                'election_officer' => 'badge-officer',
                                'observer' => 'badge-observer',
                                default => 'badge-voter'
                            };
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($log['id_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($log['name']); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($log_role); ?></span></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($log['activity']); ?></span></td>
                            <td><?php echo date('M d, Y - h:i A', strtotime($log['log_time'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 10px; display: block; color: #cbd5e1;"></i>
                                No activity logs yet
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>

<?php 
if (isset($resultsResult) && $resultsResult) mysqli_free_result($resultsResult);
if (isset($logsResult) && $logsResult) mysqli_free_result($logsResult);
if (isset($latestPosition) && $latestPosition) mysqli_free_result($latestPosition);
if (isset($latestCandidate) && $latestCandidate) mysqli_free_result($latestCandidate);
if (isset($conn)) mysqli_close($conn);
?>