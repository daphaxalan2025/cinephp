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
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online Schedule</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Admin Dashboard</h1>
        
        <!-- Key Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    Admins: <?php echo $stats['total_admins']; ?> | 
                    Staff: <?php echo $stats['total_staff']; ?> | 
                    Customers: <?php echo $stats['total_customers']; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_movies']; ?></div>
                <div class="stat-label">Total Movies</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_cinemas']; ?></div>
                <div class="stat-label">Cinemas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_screenings']; ?></div>
                <div class="stat-label">Cinema Screenings</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_online_schedules']; ?></div>
                <div class="stat-label">Online Slots</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                <div class="stat-label">Total Tickets</div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    Cinema: <?php echo $stats['cinema_tickets']; ?> | 
                    Online: <?php echo $stats['online_tickets']; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    Cinema: $<?php echo number_format($stats['cinema_revenue'], 2); ?> | 
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
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    <a href="payments.php?status=pending" style="color: #00ffff;">View →</a>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_payments']; ?></div>
                <div class="stat-label">Completed Payments</div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    <a href="payments.php?status=completed" style="color: #00ffff;">View →</a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div style="margin: 40px 0;">
            <h2>Quick Actions</h2>
            <div style="display: flex; gap: 20px; margin-top: 20px; flex-wrap: wrap;">
                <a href="movies.php?action=add" class="btn btn-primary">➕ Add Movie</a>
                <a href="cinemas.php?action=add" class="btn btn-primary">➕ Add Cinema</a>
                <a href="screenings.php?action=add" class="btn btn-primary">➕ Add Screening</a>
                <a href="online_schedule.php?action=add" class="btn btn-primary">🌐 Add Online Slot</a>
                <a href="users.php?action=add" class="btn btn-primary">👤 Add User</a>
                <a href="payments.php?status=pending" class="btn btn-primary">💰 Process Payments</a>
                <a href="reports.php" class="btn btn-primary">📊 Generate Report</a>
            </div>
        </div>
        
        <!-- Upcoming Online Schedules -->
        <?php if (!empty($upcoming_online)): ?>
            <h2>Upcoming Online Screenings</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                <?php foreach ($upcoming_online as $online): ?>
                    <div style="background: #1a1a1a; border: 2px solid #00ffff; border-radius: 8px; padding: 15px;">
                        <h3 style="color: #00ffff; margin-bottom: 10px;"><?php echo htmlspecialchars($online['title']); ?></h3>
                        <p style="color: #888;">📅 <?php echo date('M d, Y', strtotime($online['show_date'])); ?></p>
                        <p style="color: #888;">⏰ <?php echo date('h:i A', strtotime($online['show_time'])); ?></p>
                        <p style="color: #888;">👥 <?php echo $online['current_viewers']; ?>/<?php echo $online['max_viewers']; ?> viewers</p>
                        <p style="color: #00ffff;">$<?php echo number_format($online['price'], 2); ?></p>
                        <a href="online_schedule.php?edit=<?php echo $online['id']; ?>" class="btn-small">Manage</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Payments -->
        <h2>Recent Payments</h2>
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
                        <td><span style="color: #00ffff; font-family: monospace;"><?php echo $payment['transaction_id']; ?></span></td>
                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td>
                            <span class="ticket-status status-<?php echo $payment['payment_status']; ?>">
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
        
        <!-- Recent Tickets -->
        <h2 style="margin-top: 40px;">Recent Ticket Purchases</h2>
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
                        <td><span style="color: #00ffff; font-family: monospace;"><?php echo $ticket['ticket_code']; ?></span></td>
                        <td><?php echo htmlspecialchars($ticket['username']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['title'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($ticket['ticket_type']); ?></td>
                        <td><?php echo $ticket['seat_numbers'] ?: 'N/A'; ?></td>
                        <td>$<?php echo number_format($ticket['total_price'], 2); ?></td>
                        <td>
                            <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                <?php echo strtoupper($ticket['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($ticket['purchase_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Recent Users -->
        <h2 style="margin-top: 40px;">Recent Registrations</h2>
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
                        <td><?php echo $user['id']; ?></td>
                        <td><span style="color: #00ffff;"><?php echo htmlspecialchars($user['username']); ?></span></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span style="padding: 3px 10px; background: #000; border: 1px solid #00ffff; border-radius: 15px;">
                                <?php echo strtoupper($user['account_type']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>