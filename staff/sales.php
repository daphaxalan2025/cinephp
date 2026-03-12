<?php
// staff/sales.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;
$cinema_filter = $cinema_id ? "AND s.cinema_id = $cinema_id" : "";

// Get date range
$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to = $_GET['to'] ?? date('Y-m-d');

// Get sales by day
$stmt = $pdo->query("
    SELECT 
        DATE(t.purchase_date) as sale_date,
        COUNT(*) as ticket_count,
        SUM(t.total_price) as daily_revenue,
        COUNT(DISTINCT t.user_id) as unique_customers
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    WHERE t.status = 'paid' 
      AND DATE(t.purchase_date) BETWEEN '$date_from' AND '$date_to'
      $cinema_filter
    GROUP BY DATE(t.purchase_date)
    ORDER BY sale_date DESC
");
$daily_sales = $stmt->fetchAll();

// Get sales by movie
$stmt = $pdo->query("
    SELECT 
        m.title,
        COUNT(t.id) as ticket_count,
        SUM(t.total_price) as revenue,
        AVG(t.total_price) as avg_ticket_price
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE t.status = 'paid'
      AND DATE(t.purchase_date) BETWEEN '$date_from' AND '$date_to'
      $cinema_filter
    GROUP BY m.id
    ORDER BY revenue DESC
");
$movie_sales = $stmt->fetchAll();

// Get sales by payment method
$stmt = $pdo->query("
    SELECT 
        p.payment_method,
        COUNT(*) as transaction_count,
        SUM(p.amount) as total_amount
    FROM payments p
    JOIN tickets t ON p.ticket_id = t.id
    JOIN screenings s ON t.screening_id = s.id
    WHERE p.payment_status = 'completed'
      AND DATE(p.payment_date) BETWEEN '$date_from' AND '$date_to'
      $cinema_filter
    GROUP BY p.payment_method
");
$payment_methods = $stmt->fetchAll();

// Get hourly breakdown for today
$stmt = $pdo->query("
    SELECT 
        HOUR(t.purchase_date) as hour,
        COUNT(*) as ticket_count,
        SUM(t.total_price) as revenue
    FROM tickets t
    JOIN screenings s ON t.screening_id = s.id
    WHERE t.status = 'paid' 
      AND DATE(t.purchase_date) = CURDATE()
      $cinema_filter
    GROUP BY HOUR(t.purchase_date)
    ORDER BY hour
");
$hourly_sales = $stmt->fetchAll();

// Calculate totals
$total_tickets = array_sum(array_column($daily_sales, 'ticket_count'));
$total_revenue = array_sum(array_column($daily_sales, 'daily_revenue'));
$avg_daily = count($daily_sales) > 0 ? $total_revenue / count($daily_sales) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Staff</title>
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
        .report-header {
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
        }
        
        /* Date Filter */
        .date-filter {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .date-filter::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
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
            letter-spacing: 1px;
            font-size: 0.8rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .filter-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Chart Container */
        .chart-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }
        
        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .chart-container h2 {
            color: var(--red);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        /* Tables */
        .section-title {
            color: var(--red);
            margin: 40px 0 20px;
            font-size: 1.8rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .sales-table th {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .sales-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
        }
        
        .sales-table tr:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .sales-table tr:last-child td {
            border-bottom: none;
        }
        
        .sales-table td strong {
            color: var(--red);
        }
        
        .highlight-number {
            color: var(--red);
            font-weight: 600;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .sales-table {
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
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php" class="active">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="report-header">
            <h1>Sales Report</h1>
            <?php if ($cinema_id): ?>
                <div class="cinema-badge">
                    <?php 
                    $stmt = $pdo->prepare("SELECT name FROM cinemas WHERE id = ?");
                    $stmt->execute([$cinema_id]);
                    $cinema = $stmt->fetch();
                    echo htmlspecialchars($cinema['name'] ?? 'Your Cinema');
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?php echo $date_to; ?>" required>
                </div>
                <div>
                    <button type="submit" class="btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_tickets; ?></div>
                <div class="stat-label">Tickets Sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($avg_daily, 2); ?></div>
                <div class="stat-label">Avg Daily Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($daily_sales); ?></div>
                <div class="stat-label">Days</div>
            </div>
        </div>
        
        <!-- Hourly Sales Chart (for today) -->
        <?php if (!empty($hourly_sales) && ($date_from == date('Y-m-d') || $date_to == date('Y-m-d'))): ?>
        <div class="chart-container">
            <h2>Hourly Sales - Today</h2>
            <canvas id="hourlyChart" style="width:100%; height:300px;"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Daily Sales Table -->
        <h2 class="section-title">Daily Breakdown</h2>
        <table class="sales-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Tickets Sold</th>
                    <th>Unique Customers</th>
                    <th>Revenue</th>
                    <th>Average per Ticket</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_sales as $day): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($day['sale_date'])); ?></td>
                        <td><span class="highlight-number"><?php echo $day['ticket_count']; ?></span></td>
                        <td><?php echo $day['unique_customers']; ?></td>
                        <td><span class="highlight-number">$<?php echo number_format($day['daily_revenue'], 2); ?></span></td>
                        <td>$<?php echo number_format($day['daily_revenue'] / $day['ticket_count'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($daily_sales)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:var(--text-secondary);">No sales data for selected period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Movie Sales Table -->
        <h2 class="section-title">Top Movies</h2>
        <table class="sales-table">
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Tickets Sold</th>
                    <th>Revenue</th>
                    <th>Average Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movie_sales as $movie): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($movie['title']); ?></strong></td>
                        <td><span class="highlight-number"><?php echo $movie['ticket_count']; ?></span></td>
                        <td><span class="highlight-number">$<?php echo number_format($movie['revenue'], 2); ?></span></td>
                        <td>$<?php echo number_format($movie['avg_ticket_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($movie_sales)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px; color:var(--text-secondary);">No movie sales data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Payment Methods -->
        <h2 class="section-title">Payment Methods</h2>
        <table class="sales-table">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Transactions</th>
                    <th>Total Amount</th>
                    <th>Average</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payment_methods as $method): ?>
                    <tr>
                        <td><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></td>
                        <td><span class="highlight-number"><?php echo $method['transaction_count']; ?></span></td>
                        <td><span class="highlight-number">$<?php echo number_format($method['total_amount'], 2); ?></span></td>
                        <td>$<?php echo number_format($method['total_amount'] / $method['transaction_count'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payment_methods)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px; color:var(--text-secondary);">No payment data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
    
    <script>
        <?php if (!empty($hourly_sales)): ?>
        // Hourly Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $hours = array_column($hourly_sales, 'hour');
                    echo implode(',', array_map(function($h) { return "'" . $h . ":00'"; }, $hours));
                ?>],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [<?php echo implode(',', array_column($hourly_sales, 'revenue')); ?>],
                    borderColor: '#e50914',
                    backgroundColor: 'rgba(229, 9, 20, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: '#e50914'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { 
                        labels: { 
                            color: '#fff',
                            font: { family: 'Inter' }
                        } 
                    }
                },
                scales: {
                    y: { 
                        grid: { color: 'rgba(255,255,255,0.1)' }, 
                        ticks: { color: '#b3b3b3' } 
                    },
                    x: { 
                        grid: { color: 'rgba(255,255,255,0.1)' }, 
                        ticks: { color: '#b3b3b3' } 
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>