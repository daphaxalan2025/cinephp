<?php
// admin/reports.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// Get date range
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['to'] ?? date('Y-m-d');

// ============ REVENUE BY DAY ============
$stmt = $pdo->prepare("
    SELECT DATE(purchase_date) as date, 
           COUNT(*) as ticket_count,
           SUM(total_price) as daily_revenue
    FROM tickets
    WHERE status = 'paid' AND DATE(purchase_date) BETWEEN ? AND ?
    GROUP BY DATE(purchase_date)
    ORDER BY date DESC
");
$stmt->execute([$date_from, $date_to]);
$daily_revenue = $stmt->fetchAll();

// ============ PAYMENT METHODS BREAKDOWN ============
$stmt = $pdo->prepare("
    SELECT payment_method, 
           COUNT(*) as count,
           SUM(amount) as total
    FROM payments
    WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$date_from, $date_to]);
$payment_methods = $stmt->fetchAll();

// ============ TOP MOVIES ============
$top_movies = $pdo->prepare("
    SELECT m.title, 
           COUNT(t.id) as ticket_count,
           SUM(t.total_price) as revenue
    FROM movies m
    JOIN screenings s ON m.id = s.movie_id
    JOIN tickets t ON s.id = t.screening_id
    WHERE t.status = 'paid' AND DATE(t.purchase_date) BETWEEN ? AND ?
    GROUP BY m.id
    ORDER BY revenue DESC
    LIMIT 10
");
$top_movies->execute([$date_from, $date_to]);
$top_movies = $top_movies->fetchAll();

// ============ USER STATISTICS ============
$user_stats = [
    'new_users' => $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?")->execute([$date_from, $date_to]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0,
    'active_users' => $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM tickets WHERE DATE(purchase_date) BETWEEN ? AND ?")->execute([$date_from, $date_to]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0,
    'paying_users' => $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM payments WHERE DATE(payment_date) BETWEEN ? AND ? AND payment_status = 'completed'")->execute([$date_from, $date_to]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0
];

// ============ SUMMARY ============
$summary = [
    'total_tickets' => array_sum(array_column($daily_revenue, 'ticket_count')),
    'total_revenue' => array_sum(array_column($daily_revenue, 'daily_revenue')),
    'avg_daily' => count($daily_revenue) > 0 ? array_sum(array_column($daily_revenue, 'daily_revenue')) / count($daily_revenue) : 0,
    'total_payments' => array_sum(array_column($payment_methods, 'count'))
];

// ============ EXPORT ============
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filename = "report_{$export_type}_{$date_from}_to_{$date_to}.csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type == 'revenue') {
        fputcsv($output, ['Date', 'Tickets Sold', 'Revenue']);
        foreach ($daily_revenue as $row) {
            fputcsv($output, [$row['date'], $row['ticket_count'], $row['daily_revenue']]);
        }
    } elseif ($export_type == 'payments') {
        fputcsv($output, ['Payment Method', 'Transactions', 'Total Amount']);
        foreach ($payment_methods as $row) {
            fputcsv($output, [$row['payment_method'], $row['count'], $row['total']]);
        }
    } elseif ($export_type == 'movies') {
        fputcsv($output, ['Movie', 'Tickets Sold', 'Revenue']);
        foreach ($top_movies as $row) {
            fputcsv($output, [$row['title'], $row['ticket_count'], $row['revenue']]);
        }
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin: 0;
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
        
        /* Filter Section */
        .filter-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .filter-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            text-align: center;
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
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-value.revenue {
            color: var(--red);
        }
        
        .stat-label {
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Chart Containers */
        .chart-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin: 40px 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .export-btn {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: #fff;
            padding: 10px 25px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .export-btn:hover {
            background: var(--red);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(229, 9, 20, 0.3);
        }
        
        canvas {
            max-height: 400px;
            width: 100% !important;
        }
        
        /* Payment Method Cards */
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .payment-card {
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .payment-card:hover {
            border-color: var(--red);
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(229, 9, 20, 0.15);
        }
        
        .payment-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(229, 9, 20, 0.3));
        }
        
        .payment-name {
            color: var(--red);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .payment-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .payment-count {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        /* Table Container */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            overflow: hidden;
            margin: 40px 0;
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
        
        .movie-title {
            color: var(--red);
            font-weight: 600;
        }
        
        .percentage {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Buttons */
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
        
        .btn-small {
            padding: 5px 12px;
            font-size: 0.7rem;
            text-decoration: none;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 30px;
            color: var(--text-primary);
            transition: all 0.3s;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(0, 0, 0, 0.3);
            display: inline-block;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .payment-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php" class="active">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Reports & Analytics</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Date Range Filter -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?php echo $date_to; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn-primary">Generate</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $summary['total_tickets']; ?></div>
                <div class="stat-label">Tickets Sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-value revenue">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value revenue">$<?php echo number_format($summary['avg_daily'], 2); ?></div>
                <div class="stat-label">Avg Daily Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $summary['total_payments']; ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user_stats['new_users']; ?></div>
                <div class="stat-label">New Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user_stats['paying_users']; ?></div>
                <div class="stat-label">Paying Users</div>
            </div>
        </div>
        
        <!-- Revenue Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h2>Daily Revenue</h2>
                <a href="?export=revenue&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="export-btn">📊 Export CSV</a>
            </div>
            
            <canvas id="revenueChart" style="width: 100%; height: 400px;"></canvas>
        </div>
        
        <!-- Payment Methods -->
        <div class="chart-container">
            <div class="chart-header">
                <h2>Payment Methods Breakdown</h2>
                <a href="?export=payments&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="export-btn">📊 Export CSV</a>
            </div>
            
            <?php 
            $payment_icons = [
                'credit_card' => '💳',
                'paypal' => '🅿️',
                'gcash' => '📱',
                'bank_transfer' => '🏦',
                'cash' => '💰'
            ];
            ?>
            
            <?php if (!empty($payment_methods)): ?>
                <div class="payment-grid">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="payment-card">
                            <div class="payment-icon"><?php echo $payment_icons[$method['payment_method']] ?? '💰'; ?></div>
                            <div class="payment-name"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></div>
                            <div class="payment-total">$<?php echo number_format($method['total'], 2); ?></div>
                            <div class="payment-count"><?php echo $method['count']; ?> transactions</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <canvas id="paymentChart" style="width: 100%; height: 300px;"></canvas>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    No payment data available for the selected period.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Movies -->
        <div class="chart-container">
            <div class="chart-header">
                <h2>Top Performing Movies</h2>
                <a href="?export=movies&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="export-btn">📊 Export CSV</a>
            </div>
            
            <?php if (!empty($top_movies)): ?>
                <div class="table-container" style="margin: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Movie</th>
                                <th>Tickets Sold</th>
                                <th>Revenue</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_movies as $movie): 
                                $percentage = $summary['total_revenue'] > 0 ? ($movie['revenue'] / $summary['total_revenue'] * 100) : 0;
                            ?>
                                <tr>
                                    <td><span class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></span></td>
                                    <td><?php echo $movie['ticket_count']; ?></td>
                                    <td><span style="color: var(--red);">$<?php echo number_format($movie['revenue'], 2); ?></span></td>
                                    <td><span class="percentage"><?php echo number_format($percentage, 1); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    No movie data available for the selected period.
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        <?php if (!empty($daily_revenue)): ?>
        // Revenue Chart
        const ctx1 = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: [<?php 
                    $dates = array_column($daily_revenue, 'date');
                    echo "'" . implode("','", array_reverse($dates)) . "'";
                ?>],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [<?php 
                        $revenues = array_column($daily_revenue, 'daily_revenue');
                        echo implode(',', array_reverse($revenues));
                    ?>],
                    borderColor: '#e50914',
                    backgroundColor: 'rgba(229, 9, 20, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: '#e50914',
                    pointBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { 
                            color: '#fff',
                            font: { family: 'Inter', size: 12 }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { 
                            color: '#b3b3b3',
                            callback: function(value) { return '$' + value; }
                        }
                    },
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#b3b3b3' }
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if (!empty($payment_methods)): ?>
        // Payment Methods Chart
        const ctx2 = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $labels = array_column($payment_methods, 'payment_method');
                    echo "'" . implode("','", array_map(function($label) {
                        return ucfirst(str_replace('_', ' ', $label));
                    }, $labels)) . "'";
                ?>],
                datasets: [{
                    data: [<?php 
                        $totals = array_column($payment_methods, 'total');
                        echo implode(',', $totals);
                    ?>],
                    backgroundColor: [
                        '#e50914',
                        '#ff4444',
                        '#b2070f',
                        '#ff6b6b',
                        '#8b0000'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { 
                            color: '#fff',
                            font: { family: 'Inter', size: 12 }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>