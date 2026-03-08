<?php
// staff/dashboard.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

// Get today's date
$today = date('Y-m-d');

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;
$cinema_name = 'All Cinemas';
if ($cinema_id) {
    $stmt = $pdo->prepare("SELECT name FROM cinemas WHERE id = ?");
    $stmt->execute([$cinema_id]);
    $cinema = $stmt->fetch();
    $cinema_name = $cinema ? $cinema['name'] : 'All Cinemas';
}

// Build query based on staff's cinema assignment
$cinema_filter = $cinema_id ? "AND s.cinema_id = $cinema_id" : "";

// Get today's screenings
$stmt = $pdo->query("
    SELECT s.*, m.title, m.poster, m.duration, c.name as cinema_name,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id AND status IN ('paid', 'used')) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.show_date = '$today' $cinema_filter
    ORDER BY s.show_time
");
$today_screenings = $stmt->fetchAll();

// Get today's statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT t.id) as tickets_sold,
        COALESCE(SUM(t.total_price), 0) as revenue,
        COUNT(DISTINCT s.id) as screenings_count
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    WHERE s.show_date = '$today' AND t.status = 'paid'
    $cinema_filter
");
$stats = $stmt->fetch();

// Get recent verifications
$stmt = $pdo->query("
    SELECT t.ticket_code, t.used_at, m.title, u.first_name, u.last_name
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN users u ON t.user_id = u.id
    WHERE t.used_at IS NOT NULL
    ORDER BY t.used_at DESC
    LIMIT 10
");
$recent_verifications = $stmt->fetchAll();

// Get pending verifications for today
$stmt = $pdo->query("
    SELECT COUNT(*) as pending
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    WHERE s.show_date = '$today' AND t.status = 'paid' AND t.used_at IS NULL
    $cinema_filter
");
$pending = $stmt->fetch();
$pending_verifications = $pending['pending'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .cinema-badge {
            background: #00ffff;
            color: #000;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        .stat-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 30px rgba(0,255,255,0.3);
        }
        .stat-icon {
            font-size: 2.5rem;
            width: 60px;
            height: 60px;
            background: #000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-content h3 {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 5px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #00ffff;
        }
        .quick-actions {
            margin: 40px 0;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .action-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            text-decoration: none;
            color: #fff;
            transition: all 0.3s;
        }
        .action-card:hover {
            background: #00ffff;
            color: #000;
            transform: scale(1.05);
        }
        .action-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
        }
        .screenings-list {
            margin-top: 20px;
        }
        .screening-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .screening-item:hover {
            transform: translateX(5px);
            box-shadow: 0 0 20px rgba(0,255,255,0.2);
        }
        .screening-time {
            font-size: 1.2rem;
            font-weight: bold;
            color: #00ffff;
            min-width: 100px;
        }
        .screening-info {
            flex: 1;
        }
        .screening-info h4 {
            margin-bottom: 5px;
            color: #fff;
        }
        .screening-info p {
            color: #888;
            font-size: 0.9rem;
        }
        .seat-progress {
            width: 150px;
        }
        .progress-bar {
            height: 8px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #00ffff;
            border-radius: 4px;
        }
        .verifications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .verifications-table th {
            text-align: left;
            padding: 15px;
            background: #000;
            color: #00ffff;
            border-bottom: 2px solid #00ffff;
        }
        .verifications-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            color: #888;
        }
        .verifications-table td code {
            color: #00ffff;
            background: #000;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .badge-success {
            background: rgba(68,255,68,0.2);
            color: #44ff44;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .pending-badge {
            background: rgba(255,255,68,0.2);
            color: #ffff44;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="staff-header">
            <h1>Staff Dashboard</h1>
            <div class="cinema-badge">
                🎬 <?php echo htmlspecialchars($cinema_name); ?>
            </div>
        </div>
        
        <!-- Pending Verifications Alert -->
        <?php if ($pending_verifications > 0): ?>
            <div class="pending-badge">
                ⏳ You have <?php echo $pending_verifications; ?> tickets waiting to be verified today!
            </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📽️</div>
                <div class="stat-content">
                    <h3>Screenings Today</h3>
                    <div class="stat-number"><?php echo count($today_screenings); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎟️</div>
                <div class="stat-content">
                    <h3>Tickets Sold Today</h3>
                    <div class="stat-number"><?php echo $stats['tickets_sold'] ?? 0; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Today's Revenue</h3>
                    <div class="stat-number">$<?php echo number_format($stats['revenue'] ?? 0, 2); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3>Pending Verification</h3>
                    <div class="stat-number"><?php echo $pending_verifications; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="actions-grid">
                <a href="verify.php" class="action-card">
                    <span class="action-icon">✅</span>
                    <span>Verify Ticket</span>
                </a>
                <a href="scan.php" class="action-card">
                    <span class="action-icon">📱</span>
                    <span>Scan QR Code</span>
                </a>
                <a href="screenings.php?action=add" class="action-card">
                    <span class="action-icon">➕</span>
                    <span>Add Screening</span>
                </a>
                <a href="cinemas.php?action=add" class="action-card">
                    <span class="action-icon">🏛️</span>
                    <span>Add Cinema</span>
                </a>
                <a href="sales.php" class="action-card">
                    <span class="action-icon">📊</span>
                    <span>Sales Report</span>
                </a>
            </div>
        </div>
        
        <!-- Today's Screenings -->
        <h2>Today's Screenings</h2>
        <?php if (empty($today_screenings)): ?>
            <p style="color: #888; text-align: center; padding: 40px;">No screenings scheduled for today.</p>
        <?php else: ?>
            <div class="screenings-list">
                <?php foreach ($today_screenings as $screening): 
                    $total_seats = 40; // Default
                    $sold = $screening['tickets_sold'];
                    $available = $screening['available_seats'];
                    $occupancy = ($sold / $total_seats) * 100;
                ?>
                    <div class="screening-item">
                        <div class="screening-time">
                            <?php echo date('h:i A', strtotime($screening['show_time'])); ?>
                        </div>
                        <div class="screening-info">
                            <h4><?php echo htmlspecialchars($screening['title']); ?></h4>
                            <p>Screen <?php echo $screening['screen_number']; ?> • <?php echo $screening['cinema_name']; ?></p>
                        </div>
                        <div class="seat-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $occupancy; ?>%"></div>
                            </div>
                            <small style="color: #888;"><?php echo $sold; ?>/<?php echo $total_seats; ?> seats</small>
                        </div>
                        <a href="tickets_list.php?screening_id=<?php echo $screening['id']; ?>" class="btn-small">View Tickets</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Verifications -->
        <h2 style="margin-top: 40px;">Recent Verifications</h2>
        <table class="verifications-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Ticket Code</th>
                    <th>Movie</th>
                    <th>Customer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_verifications as $verification): ?>
                    <tr>
                        <td><?php echo date('h:i A', strtotime($verification['used_at'])); ?></td>
                        <td><code><?php echo $verification['ticket_code']; ?></code></td>
                        <td><?php echo htmlspecialchars($verification['title']); ?></td>
                        <td><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></td>
                        <td><span class="badge-success">Verified</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_verifications)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: #888;">No recent verifications</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>