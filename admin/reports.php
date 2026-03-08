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
    <title>Reports - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online Schedule</a>
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
        
        <!-- Date Range Filter -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #00ffff; margin: 30px 0;">
            <form method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="from">From Date</label>
                    <input type="date" id="from" name="from" value="<?php echo $date_from; ?>">
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <label for="to">To Date</label>
                    <input type="date" id="to" name="to" value="<?php echo $date_to; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
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
                <div class="stat-value">$<?php echo number_format($summary['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($summary['avg_daily'], 2); ?></div>
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
        <div style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 1px solid #00ffff; margin: 40px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ffff;">Daily Revenue</h2>
                <a href="?export=revenue&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn btn-small">Export CSV</a>
            </div>
            
            <canvas id="revenueChart" style="width: 100%; height: 400px;"></canvas>
        </div>
        
        <!-- Payment Methods Chart -->
        <div style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 1px solid #00ffff; margin: 40px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ffff;">Payment Methods Breakdown</h2>
                <a href="?export=payments&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn btn-small">Export CSV</a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php 
                $payment_icons = [
                    'credit_card' => '💳',
                    'paypal' => '🅿️',
                    'gcash' => '📱',
                    'bank_transfer' => '🏦',
                    'cash' => '💰'
                ];
                foreach ($payment_methods as $method): 
                ?>
                    <div style="background: #000; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 2rem; margin-bottom: 10px;"><?php echo $payment_icons[$method['payment_method']] ?? '💰'; ?></div>
                        <div style="color: #00ffff; font-weight: bold;"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></div>
                        <div style="font-size: 1.5rem; color: #fff;">$<?php echo number_format($method['total'], 2); ?></div>
                        <div style="color: #888;"><?php echo $method['count']; ?> transactions</div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <canvas id="paymentChart" style="width: 100%; height: 300px;"></canvas>
        </div>
        
        <!-- Top Movies -->
        <div style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 1px solid #00ffff; margin: 40px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #00ffff;">Top Performing Movies</h2>
                <a href="?export=movies&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn btn-small">Export CSV</a>
            </div>
            
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
                            <td><span style="color: #00ffff;"><?php echo htmlspecialchars($movie['title']); ?></span></td>
                            <td><?php echo $movie['ticket_count']; ?></td>
                            <td>$<?php echo number_format($movie['revenue'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
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
                    borderColor: '#00ffff',
                    backgroundColor: 'rgba(0, 255, 255, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                },
                scales: {
                    y: {
                        grid: { color: '#333' },
                        ticks: { color: '#fff' }
                    },
                    x: {
                        grid: { color: '#333' },
                        ticks: { color: '#fff' }
                    }
                }
            }
        });
        
        // Payment Methods Chart
        const ctx2 = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $labels = array_column($payment_methods, 'payment_method');
                    echo "'" . implode("','", array_map('ucfirst', str_replace('_', ' ', $labels))) . "'";
                ?>],
                datasets: [{
                    data: [<?php 
                        $totals = array_column($payment_methods, 'total');
                        echo implode(',', $totals);
                    ?>],
                    backgroundColor: [
                        '#00ffff',
                        '#ff44ff',
                        '#ffff44',
                        '#44ff44',
                        '#ff8844'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: { color: '#fff' }
                    }
                }
            }
        });
    </script>
</body>
</html>