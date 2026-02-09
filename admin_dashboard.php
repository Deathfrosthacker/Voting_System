<?php
session_start();
require_once "./config/connection.php";

/* Simple admin protection */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$logsQuery = "
    SELECT logs.activity, logs.log_time, users.name, users.student_id
    FROM logs
    JOIN users ON logs.user_id = users.id
    ORDER BY logs.log_time DESC
    LIMIT 10
";
$logsResult = mysqli_query($conn, $logsQuery);


/* Fetch statistics */
$totalUsers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM positions"))['total'];
$totalCandidates = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM candidates"))['total'];
$totalVotes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM votes"))['total'];

/* Latest Position */
$latestPosition = mysqli_query(
    $conn,
    "SELECT position_name, start_date, end_date 
     FROM positions 
     ORDER BY id DESC 
     LIMIT 1"
);

/* Latest Candidate */
$latestCandidate = mysqli_query(
    $conn,
    "SELECT name, position, start_date, end_date 
     FROM candidates 
     ORDER BY id DESC 
     LIMIT 1"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Voting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
         
            min-height: 100vh;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 240px;
            padding: 30px;
            min-height: 100vh;
        }

        /* TOP BAR */
        .top-bar {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .title-section h1 {
            color: #1e293b;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .title-section p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* STATS CARDS */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .icon-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .icon-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .icon-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-card p {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* INFO CARDS */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .info-card h3 {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card h3 i {
            color: #667eea;
        }

        .info-card .info-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin: 10px 0;
        }

        .info-card .info-meta {
            color: #64748b;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .info-card .info-meta i {
            color: #667eea;
        }

        .info-card .no-data {
            color: #94a3b8;
            font-size: 14px;
            font-style: italic;
        }

        /* LOGS SECTION */
        .logs-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .logs-section h2 {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logs-section h2 i {
            color: #667eea;
        }

        /* TABLE */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        table thead {
            background: linear-gradient(135deg, #667eea 0%, #1e235e 100%);
        }

        table th {
            padding: 16px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 16px;
            color: #334155;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        table tbody tr {
            transition: all 0.2s ease;
        }

        table tbody tr:hover {
            background: #f8fafc;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .title-section h1 {
                font-size: 24px;
            }

            .stats,
            .info-cards {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 12px 8px;
            }
        }

        /* ANIMATIONS */
       

        

      
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- TOP BAR -->
    <div class="top-bar">
        <div class="title-section">
            <h1><i class="fas fa-chart-line"></i> Dashboard Overview</h1>
            <p>Monitor your voting system's performance and track all activities in real-time</p>
        </div>
        <div class="logout-section">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Total Positions</h3>
                <div class="stat-icon icon-blue">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>
            <p><?php echo $totalUsers; ?></p>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Total Candidates</h3>
                <div class="stat-icon icon-purple">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <p><?php echo $totalCandidates; ?></p>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <h3>Total Votes Cast</h3>
                <div class="stat-icon icon-green">
                    <i class="fas fa-vote-yea"></i>
                </div>
            </div>
            <p><?php echo $totalVotes; ?></p>
        </div>
    </div>

    <!-- INFO CARDS -->
    <div class="info-cards">
        <!-- Latest Position -->
        <div class="info-card">
            <h3>
                <i class="fas fa-clipboard-list"></i>
                Latest Position Added
            </h3>

            <?php if (mysqli_num_rows($latestPosition) > 0): 
                $pos = mysqli_fetch_assoc($latestPosition); ?>
                
                <div class="info-title">
                    <?php echo htmlspecialchars($pos['position_name']); ?>
                </div>
                <div class="info-meta">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('M d, Y', strtotime($pos['start_date'])); ?> 
                    → 
                    <?php echo date('M d, Y', strtotime($pos['end_date'])); ?>
                </div>

            <?php else: ?>
                <p class="no-data">No position added yet</p>
            <?php endif; ?>
        </div>

        <!-- Latest Candidate -->
        <div class="info-card">
            <h3>
                <i class="fas fa-user-plus"></i>
                Latest Candidate Added
            </h3>

            <?php if (mysqli_num_rows($latestCandidate) > 0): 
                $cand = mysqli_fetch_assoc($latestCandidate); ?>

                <div class="info-title">
                    <?php echo htmlspecialchars($cand['name']); ?>
                </div>
                <div class="info-meta">
                    <i class="fas fa-tag"></i>
                    <?php echo htmlspecialchars($cand['position']); ?>
                </div>
                <div class="info-meta">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('M d, Y', strtotime($cand['start_date'])); ?> 
                    → 
                    <?php echo date('M d, Y', strtotime($cand['end_date'])); ?>
                </div>

            <?php else: ?>
                <p class="no-data">No candidate added yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- LOGS SECTION -->
    <div class="logs-section">
        <h2>
            <i class="fas fa-history"></i>
            Recent Activity Logs
        </h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card"></i> Student ID</th>
                        <th><i class="fas fa-user"></i> Name</th>
                        <th><i class="fas fa-tasks"></i> Activity</th>
                        <th><i class="fas fa-clock"></i> Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($logsResult) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($logsResult)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($log['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($log['name']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($log['activity']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y - h:i A', strtotime($log['log_time'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                No activity logs yet
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>