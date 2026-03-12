<?php
// admin/dashboard.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// Get comprehensive statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'admin'")->fetchColumn(),
    'total_staff' => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'staff'")->fetchColumn(),
    'total_customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type IN ('adult', 'teen', 'kid')")->fetchColumn(),
    'total_movies' => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
    'total_cinemas' => $pdo->query("SELECT COUNT(*) FROM cinemas")->fetchColumn(),
    'total_screenings' => $pdo->query("SELECT COUNT(*) FROM screenings WHERE show_date >= CURDATE()")->fetchColumn(),
    'total_online_schedules' => $pdo->query("SELECT COUNT(*) FROM online_schedule WHERE show_date >= CURDATE() AND status = 'scheduled'")->fetchColumn(),
    'total_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'cinema_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE ticket_type = 'cinema'")->fetchColumn(),
    'online_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE ticket_type = 'online'")->fetchColumn(),
    'paid_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'paid'")->fetchColumn(),
    'pending_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'pending'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE status = 'paid'")->fetchColumn(),
    'cinema_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE status = 'paid' AND ticket_type = 'cinema'")->fetchColumn(),
    'online_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE status = 'paid' AND ticket_type = 'online'")->fetchColumn(),
    'today_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE DATE(purchase_date) = CURDATE() AND status = 'paid'")->fetchColumn(),
    'pending_payments' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn(),
    'completed_payments' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'completed'")->fetchColumn(),
];

// Get recent activities
$recent_tickets = $pdo->query("
    SELECT t.*, u.username, 
           CASE 
               WHEN t.ticket_type = 'cinema' THEN (SELECT title FROM movies WHERE id = (SELECT movie_id FROM screenings WHERE id = t.screening_id))
               WHEN t.ticket_type = 'online' THEN (SELECT title FROM movies WHERE id = (SELECT movie_id FROM online_schedule WHERE id = t.online_schedule_id))
           END as title
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.purchase_date DESC
    LIMIT 10
")->fetchAll();

$recent_payments = $pdo->query("
    SELECT p.*, u.username, u.first_name, u.last_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
    LIMIT 10
")->fetchAll();

$recent_users = $pdo->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get upcoming online schedules
$upcoming_online = $pdo->query("
    SELECT os.*, m.title 
    FROM online_schedule os
    JOIN movies m ON os.movie_id = m.id
    WHERE os.show_date >= CURDATE() AND os.status = 'scheduled'
    ORDER BY os.show_date, os.show_time
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CinemaTicket</title>
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
        
        /* Glassmorphism Base */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
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
        
        /* Headers */
        h1, h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        h1 {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 30px 0;
            text-transform: uppercase;
        }
        
        h2 {
            font-size: 1.8rem;
            color: #fff;
            margin: 40px 0 20px 0;
            position: relative;
            padding-bottom: 10px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 25px;
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
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .stat-details {
            font-size: 0.8rem;
            color: var(--text-secondary);
            border-top: 1px solid rgba(229, 9, 20, 0.2);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .stat-link {
            margin-top: 10px;
            font-size: 0.75rem;
        }
        
        .stat-link a {
            color: var(--red);
            text-decoration: none;
            border: 1px solid var(--red);
            padding: 4px 12px;
            border-radius: 30px;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .stat-link a:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        /* Quick Actions */
        .quick-actions {
            margin: 40px 0;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            padding: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: #fff;
            padding: 12px 25px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            background: var(--red);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(229, 9, 20, 0.3);
        }
        
        /* Online Schedule Cards */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .schedule-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .schedule-card:hover {
            transform: translateY(-5px);
            border-color: var(--red);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
        }
        
        .schedule-title {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .schedule-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .schedule-detail i {
            color: var(--red);
            width: 20px;
        }
        
        .schedule-price {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
            margin: 15px 0;
        }
        
        .viewers-bar {
            background: rgba(0, 0, 0, 0.3);
            height: 6px;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .viewers-progress {
            height: 100%;
            background: var(--red);
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        /* Tables */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow: hidden;
            margin: 20px 0 40px;
            padding: 5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .data-table th {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-primary);
        }
        
        .data-table tr:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-pending {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            color: var(--red);
        }
        
        .status-paid {
            background: rgba(68, 255, 68, 0.15);
            border: 1px solid #44ff44;
            color: #44ff44;
        }
        
        .status-completed {
            background: rgba(68, 255, 68, 0.15);
            border: 1px solid #44ff44;
            color: #44ff44;
        }
        
        .status-used {
            background: rgba(136, 136, 136, 0.15);
            border: 1px solid #888;
            color: #888;
        }
        
        /* Type Badge */
        .type-badge {
            display: inline-block;
            padding: 3px 10px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Code Style */
        .code-style {
            color: var(--red);
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        /* Username Style */
        .username-style {
            color: var(--red);
            font-weight: 600;
        }
        
        /* Amount Style */
        .amount-style {
            color: var(--red);
            font-weight: 700;
        }
        
        /* Buttons */
        .btn-small {
            padding: 5px 12px;
            font-size: 0.7rem;
            text-decoration: none;
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            transition: all 0.3s;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(0, 0, 0, 0.3);
            display: inline-block;
        }
        
        .btn-small:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 12px 28px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 800px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Dashboard</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Key Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-details">
                    Admins: <?php echo $stats['total_admins']; ?> • 
                    Staff: <?php echo $stats['total_staff']; ?> • 
                    Customers: <?php echo $stats['total_customers']; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_movies']; ?></div>
                <div class="stat-label">Movies</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_cinemas']; ?></div>
                <div class="stat-label">Cinemas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_screenings']; ?></div>
                <div class="stat-label">Screenings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_online_schedules']; ?></div>
                <div class="stat-label">Online Slots</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                <div class="stat-label">Tickets Sold</div>
                <div class="stat-details">
                    Cinema: <?php echo $stats['cinema_tickets']; ?> • 
                    Online: <?php echo $stats['online_tickets']; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-details">
                    Cinema: $<?php echo number_format($stats['cinema_revenue'], 2); ?> • 
                    Online: $<?php echo number_format($stats['online_revenue'], 2); ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['today_revenue'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_payments']; ?></div>
                <div class="stat-label">Pending Payments</div>
                <div class="stat-link">
                    <a href="payments.php?status=pending">View →</a>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_payments']; ?></div>
                <div class="stat-label">Completed</div>
                <div class="stat-link">
                    <a href="payments.php?status=completed">View →</a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 style="margin: 0 0 20px 0;">Quick Actions</h2>
            <div class="action-buttons">
                <a href="movies.php?action=add" class="action-btn">➕ Add Movie</a>
                <a href="cinemas.php?action=add" class="action-btn">➕ Add Cinema</a>
                <a href="screenings.php?action=add" class="action-btn">➕ Add Screening</a>
                <a href="online_schedule.php?action=add" class="action-btn">🌐 Add Online Slot</a>
                <a href="users.php?action=add" class="action-btn">👤 Add User</a>
                <a href="payments.php?status=pending" class="action-btn">💰 Process Payments</a>
                <a href="reports.php" class="action-btn">📊 Generate Report</a>
            </div>
        </div>
        
        <!-- Upcoming Online Schedules -->
        <?php if (!empty($upcoming_online)): ?>
            <h2>Upcoming Online Screenings</h2>
            <div class="schedule-grid">
                <?php foreach ($upcoming_online as $online): ?>
                    <div class="schedule-card">
                        <div class="schedule-title"><?php echo htmlspecialchars($online['title']); ?></div>
                        <div class="schedule-detail">
                            <i>📅</i> <?php echo date('M d, Y', strtotime($online['show_date'])); ?>
                        </div>
                        <div class="schedule-detail">
                            <i>⏰</i> <?php echo date('h:i A', strtotime($online['show_time'])); ?>
                        </div>
                        <div class="schedule-detail">
                            <i>👥</i> <?php echo $online['current_viewers']; ?>/<?php echo $online['max_viewers']; ?> viewers
                        </div>
                        <div class="viewers-bar">
                            <div class="viewers-progress" style="width: <?php echo ($online['current_viewers'] / $online['max_viewers']) * 100; ?>%"></div>
                        </div>
                        <div class="schedule-price">$<?php echo number_format($online['price'], 2); ?></div>
                        <a href="online_schedule.php?edit=<?php echo $online['id']; ?>" class="btn-small">Manage</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Payments -->
        <h2>Recent Payments</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_payments as $payment): ?>
                        <tr>
                            <td><span class="code-style"><?php echo $payment['transaction_id']; ?></span></td>
                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                            <td><span class="amount-style">$<?php echo number_format($payment['amount'], 2); ?></span></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                            <td>
                                <a href="payments.php?view=<?php echo $payment['id']; ?>" class="btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Tickets -->
        <h2>Recent Ticket Purchases</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket Code</th>
                        <th>User</th>
                        <th>Movie</th>
                        <th>Type</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $ticket): ?>
                        <tr>
                            <td><span class="code-style"><?php echo $ticket['ticket_code']; ?></span></td>
                            <td><span class="username-style"><?php echo htmlspecialchars($ticket['username']); ?></span></td>
                            <td><?php echo htmlspecialchars($ticket['title'] ?? 'N/A'); ?></td>
                            <td><span class="type-badge"><?php echo ucfirst($ticket['ticket_type']); ?></span></td>
                            <td><?php echo $ticket['seat_numbers'] ?: 'N/A'; ?></td>
                            <td><span class="amount-style">$<?php echo number_format($ticket['total_price'], 2); ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                    <?php echo strtoupper($ticket['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($ticket['purchase_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Users -->
        <h2>Recent Registrations</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><span class="code-style">#<?php echo $user['id']; ?></span></td>
                            <td><span class="username-style"><?php echo htmlspecialchars($user['username']); ?></span></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="type-badge">
                                    <?php echo strtoupper($user['account_type']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>