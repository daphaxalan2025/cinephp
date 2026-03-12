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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #0a0a0a;
            --deep-gray: #1a1a1a;
            --medium-gray: #2a2a2a;
            --light-gray: #333333;
            --red: #e50914;
            --red-dark: #b2070f;
            --red-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --glass-bg: rgba(26, 26, 26, 0.7);
            --glass-border: rgba(255, 255, 255, 0.05);
            --card-gradient: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(229, 9, 20, 0.03) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(229, 9, 20, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }
        
        .logo {
            color: var(--red);
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            transition: all 0.3s;
        }
        
        .logo:hover {
            text-shadow: var(--red-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 10px;
            font-size: 1.5rem;
            filter: drop-shadow(0 0 5px var(--red));
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--red);
        }
        
        .nav-links a:hover::after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--red);
        }
        
        .nav-links a.active::after {
            width: 60%;
        }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Header */
        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .cinema-badge {
            background: var(--red);
            color: #fff;
            padding: 12px 25px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            letter-spacing: 1px;
        }
        
        /* Pending Alert */
        .pending-badge {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 25px;
            border-radius: 40px;
            margin-bottom: 25px;
            border-left: 4px solid var(--red);
            font-weight: 500;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            transform: translateX(-100%);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            width: 70px;
            height: 70px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--red);
        }
        
        .stat-content h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
        }
        
        /* Quick Actions */
        .quick-actions {
            margin: 50px 0;
        }
        
        .quick-actions h2 {
            color: var(--red);
            font-size: 1.8rem;
            margin-bottom: 20px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .action-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            transform: translateX(-100%);
            animation: slideBorder 3s infinite;
        }
        
        .action-card:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: var(--red);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
        }
        
        .action-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 15px;
            color: var(--red);
        }
        
        .action-card span:last-child {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }
        
        /* Screenings List */
        .screenings-list {
            margin-top: 20px;
        }
        
        .screening-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .screening-item:hover {
            transform: translateX(5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.15);
        }
        
        .screening-time {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--red);
            min-width: 120px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .screening-info {
            flex: 1;
        }
        
        .screening-info h4 {
            margin-bottom: 5px;
            color: #fff;
            font-size: 1.2rem;
        }
        
        .screening-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .seat-progress {
            width: 180px;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--red);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .seat-progress small {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .btn-small {
            padding: 8px 20px;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        /* Verifications Table */
        .verifications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .verifications-table th {
            text-align: left;
            padding: 18px 15px;
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .verifications-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
        }
        
        .verifications-table tr:last-child td {
            border-bottom: none;
        }
        
        .verifications-table td code {
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            padding: 4px 10px;
            border-radius: 30px;
            font-family: 'Monaco', monospace;
        }
        
        .badge-success {
            background: rgba(68, 255, 68, 0.15);
            color: #44ff44;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #44ff44;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .screening-item {
                flex-wrap: wrap;
            }
            
            .screening-time {
                min-width: auto;
            }
            
            .seat-progress {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .staff-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .screening-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .verifications-table {
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
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
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Pending Verifications Alert -->
        <?php if ($pending_verifications > 0): ?>
            <div class="pending-badge">
                ⏳ You have <strong><?php echo $pending_verifications; ?></strong> tickets waiting to be verified today!
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
        <h2 style="color: var(--red); margin: 30px 0 20px;">Today's Screenings</h2>
        <?php if (empty($today_screenings)): ?>
            <div style="text-align: center; padding: 40px; background: var(--card-gradient); border-radius: 16px; border: 1px solid rgba(229,9,20,0.1);">
                <p style="color: var(--text-secondary);">No screenings scheduled for today.</p>
            </div>
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
                            <small><?php echo $sold; ?>/<?php echo $total_seats; ?> seats</small>
                        </div>
                        <a href="tickets_list.php?screening_id=<?php echo $screening['id']; ?>" class="btn-small">View Tickets</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Verifications -->
        <h2 style="color: var(--red); margin: 40px 0 20px;">Recent Verifications</h2>
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
                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-secondary);">No recent verifications</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>