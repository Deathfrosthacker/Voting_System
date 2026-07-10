<?php
// Session is started by check_session_timeout() in rbac_helper.php
require_once "./config/connection.php";
require_once "./auto_declare.php";
require_once "./rbac_helper.php";

/* RBAC: Admin, Election Officer, and Observer can view results */
check_session_timeout();
require_auth(['admin', 'election_officer', 'observer']);

$results = mysqli_query($conn, "
    SELECT * FROM election_results
    ORDER BY declared_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Election Results | Voting System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
}
.main-content {
    margin-left: 240px;
    padding: 30px;
    min-height: 100vh;
}
.top-bar {
    background: rgba(255,255,255,0.95);
    padding: 25px 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.top-bar h1 {
    font-size: 28px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.top-bar p { color: #64748b; font-size: 14px; }

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 25px;
}
.result-card {
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.result-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}
.result-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 100%; height: 4px;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}
.result-card .position {
    font-size: 13px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.result-card .winner {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 12px;
}
.result-card .stats {
    display: flex;
    gap: 20px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}
.stat {
    display: flex;
    flex-direction: column;
}
.stat-label {
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
}
.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #334155;
}
.empty-state {
    text-align: center;
    padding: 60px;
    color: #94a3b8;
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    display: block;
    color: #cbd5e1;
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 20px; }
    .top-bar { flex-direction: column; gap: 16px; text-align: center; }
}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h1><i class="fas fa-trophy"></i> Election Results</h1>
            <p>Winners from completed elections</p>
        </div>
    </div>

    <?php if (mysqli_num_rows($results) > 0): ?>
        <div class="results-grid">
            <?php while ($r = mysqli_fetch_assoc($results)): ?>
                <div class="result-card">
                    <div class="position"><?php echo htmlspecialchars($r['position_name']); ?></div>
                    <div class="winner">
                        <i class="fas fa-crown" style="color: #f59e0b; margin-right: 8px;"></i>
                        <?php echo htmlspecialchars($r['winner_name']); ?>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <span class="stat-label">Votes Received</span>
                            <span class="stat-value"><?php echo $r['total_votes']; ?></span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Election Ended</span>
                            <span class="stat-value"><?php echo date('M d, Y', strtotime($r['end_date'])); ?></span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Declared</span>
                            <span class="stat-value"><?php echo date('M d, Y', strtotime($r['declared_at'])); ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No completed elections yet</h3>
            <p>Results will appear here once an election reaches its end date.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>