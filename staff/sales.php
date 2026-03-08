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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .date-filter {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            color: #00ffff;
            font-weight: bold;
        }
        .stat-label {
            color: #888;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .chart-container {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            overflow: hidden;
        }
        .sales-table th {
            background: #000;
            color: #00ffff;
            padding: 15px;
            text-align: left;
        }
        .sales-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            color: #888;
        }
        .sales-table tr:hover {
            background: #000;
        }
        .export-btn {
            padding: 10px 20px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .export-btn:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .cinema-badge {
            background: #00ffff;
            color: #000;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
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
        
        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="from">From Date</label>
                    <input type="date" id="from" name="from" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="filter-group">
                    <label for="to">To Date</label>
                    <input type="date" id="to" name="to" value="<?php echo $date_to; ?>" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
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
        <?php if ($date_from == date('Y-m-d') || $date_to == date('Y-m-d')): ?>
        <div class="chart-container">
            <h2 style="color:#00ffff; margin-bottom:20px;">Hourly Sales - Today</h2>
            <canvas id="hourlyChart" style="width:100%; height:300px;"></canvas>
        </div>
        <?php endif; ?>
        
        <!-- Daily Sales Table -->
        <h2 style="color:#00ffff; margin:30px 0 15px;">Daily Breakdown</h2>
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
                        <td><?php echo $day['ticket_count']; ?></td>
                        <td><?php echo $day['unique_customers']; ?></td>
                        <td>$<?php echo number_format($day['daily_revenue'], 2); ?></td>
                        <td>$<?php echo number_format($day['daily_revenue'] / $day['ticket_count'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($daily_sales)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px;">No sales data for selected period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Movie Sales Table -->
        <h2 style="color:#00ffff; margin:30px 0 15px;">Top Movies</h2>
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
                        <td><strong style="color:#00ffff;"><?php echo htmlspecialchars($movie['title']); ?></strong></td>
                        <td><?php echo $movie['ticket_count']; ?></td>
                        <td>$<?php echo number_format($movie['revenue'], 2); ?></td>
                        <td>$<?php echo number_format($movie['avg_ticket_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($movie_sales)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px;">No movie sales data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Payment Methods -->
        <h2 style="color:#00ffff; margin:30px 0 15px;">Payment Methods</h2>
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
                        <td><?php echo $method['transaction_count']; ?></td>
                        <td>$<?php echo number_format($method['total_amount'], 2); ?></td>
                        <td>$<?php echo number_format($method['total_amount'] / $method['transaction_count'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payment_methods)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px;">No payment data</td>
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
                    borderColor: '#00ffff',
                    backgroundColor: 'rgba(0,255,255,0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: '#fff' } }
                },
                scales: {
                    y: { grid: { color: '#333' }, ticks: { color: '#fff' } },
                    x: { grid: { color: '#333' }, ticks: { color: '#fff' } }
                }
            }
        });
        <?php endif; ?>
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>