<?php
session_start();
require_once "./config/connection.php";

/* SESSION TIMEOUT CHECK (30 minutes)*/
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

/* Protect voter page */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'voter') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/*FIX: Use prepared statement for fetching voter's region */
$stmt = mysqli_prepare($conn, "SELECT region_id FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$userResult = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($userResult);
$voter_region_id = $userData['region_id'] ?? null;

/* Fetch active positions - filtered by voter's region */
$regionFilter = $voter_region_id ? "OR region_id = ?" : "";
$query = "SELECT p.*, r.name as region_name 
          FROM positions p 
          LEFT JOIN regions r ON p.region_id = r.id
          WHERE CURDATE() BETWEEN p.start_date AND p.end_date
            AND (p.region_id IS NULL $regionFilter)
          ORDER BY p.start_date ASC";

$posStmt = mysqli_prepare($conn, $query);
if ($voter_region_id) {
    mysqli_stmt_bind_param($posStmt, "i", $voter_region_id);
}
mysqli_stmt_execute($posStmt);
$positions = mysqli_stmt_get_result($posStmt);
if ($positions === false) {
    die("Database error fetching positions.");
}

/* Count total active positions (for display) */
$totalPositions = mysqli_num_rows($positions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            min-height: 100vh; padding: 20px;
        }
        .header {
            background: white; border-radius: 16px; padding: 24px 32px;
            margin-bottom: 32px; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        .header h1 { font-size: 24px; color: #1a202c; font-weight: 600; }
        .logout-btn {
            background: #ef4444; color: white; padding: 10px 24px;
            border-radius: 8px; text-decoration: none; font-size: 14px;
            font-weight: 500; transition: background 0.3s;
        }
        .logout-btn:hover { background: #dc2626; }
        .main-container { max-width: 1200px; margin: 0 auto; }
        .welcome-box {
            background: white; border-radius: 16px; padding: 32px;
            margin-bottom: 32px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        .welcome-box h2 { font-size: 28px; color: #1a202c; margin-bottom: 8px; }
        .welcome-box p { color: #718096; font-size: 16px; }
        .info-bar {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px;
            padding: 16px 24px; margin-bottom: 24px; display: flex;
            align-items: center; gap: 12px; color: #1e40af; font-size: 14px;
        }
        .info-bar strong { color: #1e40af; }
        .positions-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;
        }
        .position-card {
            background: white; border-radius: 16px; padding: 28px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease; border: 2px solid transparent;
        }
        .position-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
        }
        .position-card h3 { font-size: 20px; color: #667eea; margin-bottom: 12px; font-weight: 600; }
        .position-card .description { color: #4a5568; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .scope-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 12px;
            font-size: 12px; font-weight: 600; margin-bottom: 16px;
        }
        .scope-global { background: #dbeafe; color: #1e40af; }
        .scope-regional { background: #d1fae5; color: #065f46; }
        .date-info {
            background: #f7fafc; border-radius: 8px;
            padding: 12px; margin-bottom: 20px;
        }
        .date-info p { font-size: 13px; color: #4a5568; margin-bottom: 6px; }
        .date-info p:last-child { margin-bottom: 0; }
        .date-info strong { color: #2d3748; }
        .vote-button {
            display: block; width: 100%; background: #667eea; color: white;
            padding: 12px 20px; border-radius: 8px; text-decoration: none;
            text-align: center; font-size: 15px; font-weight: 500;
            transition: background 0.3s;
        }
        .vote-button:hover { background: #5568d3; }
        .no-elections {
            background: white; border-radius: 16px; padding: 60px 40px;
            text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        .no-elections h3 { font-size: 24px; color: #2d3748; margin-bottom: 12px; }
        .no-elections p { color: #718096; font-size: 16px; }
        @media (max-width: 768px) {
            body { padding: 12px; }
            .header { padding: 20px; flex-direction: column; gap: 16px; text-align: center; }
            .welcome-box { padding: 24px; }
            .welcome-box h2 { font-size: 24px; }
            .positions-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="main-container">
    <!-- HEADER -->
    <div class="header">
        <h1>&#128499; Voting System</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- WELCOME -->
    <div class="welcome-box">
        <h2>Welcome, Voter! &#128075;</h2>
        <p>Select a position below to cast your vote in the active elections</p>
    </div>

    <!-- INFO BAR -->
    <?php if ($totalPositions > 0): ?>
    <div class="info-bar">
        <span>&#8505;&#65039;</span>
        <div>
            <strong><?php echo $totalPositions; ?></strong> active election(s) available for you. 
            <?php if ($voter_region_id): ?>
                Showing global elections plus elections for your region.
            <?php else: ?>
                Showing all global elections.
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- POSITIONS -->
    <?php if (mysqli_num_rows($positions) > 0): ?>
        <div class="positions-grid">
            <?php while ($pos = mysqli_fetch_assoc($positions)): ?>
                <div class="position-card">
                    <h3><?php echo htmlspecialchars($pos['position_name']); ?></h3>

                    <!-- Scope Badge -->
                    <?php if ($pos['region_id']): ?>
                        <span class="scope-badge scope-regional">
                            &#127757; <?php echo htmlspecialchars($pos['region_name']); ?>
                        </span>
                    <?php else: ?>
                        <span class="scope-badge scope-global">
                            &#127760; Global Election
                        </span>
                    <?php endif; ?>

                    <p class="description">
                        <?php echo htmlspecialchars($pos['description']); ?>
                    </p>

                    <div class="date-info">
                        <p><strong>Starts:</strong> <?php echo date('M d, Y', strtotime($pos['start_date'])); ?></p>
                        <p><strong>Ends:</strong> <?php echo date('M d, Y', strtotime($pos['end_date'])); ?></p>
                    </div>

                    <a href="vote.php?position=<?php echo urlencode($pos['position_name']); ?>" 
                       class="vote-button">
                        Cast Your Vote &rarr;
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-elections">
            <h3>&#128236; No Active Elections</h3>
            <p>There are no elections currently running for your region. Please check back later.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>