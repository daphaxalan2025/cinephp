<?php
// admin/tickets.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// ============ HANDLE STATUS UPDATE ============
if (isset($_GET['update_status'])) {
    $ticket_id = $_GET['update_status'];
    $new_status = $_GET['status'] ?? 'paid';
    
    try {
        $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $ticket_id])) {
            setFlash("Ticket status updated to $new_status", 'success');
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: tickets.php');
    exit;
}

// ============ FILTERS ============
$status_filter = $_GET['status'] ?? '';
$movie_filter = $_GET['movie'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "
    SELECT t.*, 
           u.username, u.first_name, u.last_name, u.email,
           m.title, m.poster,
           s.show_date, s.show_time,
           c.name as cinema_name,
           p.id as payment_id, p.payment_status, p.payment_method, p.transaction_id, p.amount as payment_amount
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    JOIN screenings s ON t.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    LEFT JOIN payments p ON t.id = p.ticket_id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}
if ($movie_filter) {
    $sql .= " AND m.id = ?";
    $params[] = $movie_filter;
}
if ($user_filter) {
    $sql .= " AND u.id = ?";
    $params[] = $user_filter;
}
if ($date_from) {
    $sql .= " AND DATE(t.purchase_date) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(t.purchase_date) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY t.purchase_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Get all movies for filter
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all users for filter
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'paid' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'paid'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'pending'")->fetchColumn(),
    'used' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'used'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE status = 'paid'")->fetchColumn(),
    'pending_payments' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Management - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <a href="tickets.php" class="active">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Ticket Management</h1>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['paid']; ?></div>
                <div class="stat-label">Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['used']; ?></div>
                <div class="stat-label">Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['revenue'], 2); ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_payments']; ?></div>
                <div class="stat-label">Pending Payments</div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                    <a href="payments.php?status=pending" style="color: #00ffff;">Process →</a>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #00ffff; margin: 30px 0;">
            <form method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="used" <?php echo $status_filter == 'used' ? 'selected' : ''; ?>>Used</option>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="movie">Movie</label>
                    <select id="movie" name="movie">
                        <option value="">All Movies</option>
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo $movie['id']; ?>" <?php echo $movie_filter == $movie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="user">User</label>
                    <select id="user" name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="tickets.php" class="btn">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Tickets Table -->
        <?php if (empty($tickets)): ?>
            <div class="alert alert-info">No tickets found.</div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket Code</th>
                            <th>Customer</th>
                            <th>Movie</th>
                            <th>Cinema</th>
                            <th>Date/Time</th>
                            <th>Seats</th>
                            <th>Qty</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><span style="color: #00ffff; font-family: monospace;"><?php echo $ticket['ticket_code']; ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></strong>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($ticket['username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['cinema_name']); ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($ticket['show_date'])); ?>
                                    <br><small><?php echo date('h:i A', strtotime($ticket['show_time'])); ?></small>
                                </td>
                                <td><?php echo $ticket['seat_numbers']; ?></td>
                                <td><?php echo $ticket['quantity']; ?></td>
                                <td>$<?php echo number_format($ticket['total_price'], 2); ?></td>
                                <td>
                                    <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                        <?php echo strtoupper($ticket['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ticket['payment_id']): ?>
                                        <span class="ticket-status status-<?php echo $ticket['payment_status']; ?>" style="font-size: 11px;">
                                            <?php echo ucfirst($ticket['payment_status']); ?>
                                        </span>
                                        <br><small><?php echo $ticket['transaction_id']; ?></small>
                                    <?php else: ?>
                                        <span style="color: #888;">No payment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select onchange="updateStatus(<?php echo $ticket['id']; ?>, this.value)" 
                                            style="padding: 5px; background: #000; color: #fff; border: 1px solid #00ffff; margin-bottom: 5px;">
                                        <option value="">Change Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="used">Used</option>
                                    </select>
                                    <?php if ($ticket['payment_id']): ?>
                                        <a href="payments.php?view=<?php echo $ticket['payment_id']; ?>" class="btn-small" style="display: block; text-align: center;">View Payment</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function updateStatus(ticketId, status) {
            if (status) {
                if (confirm('Update ticket status to ' + status + '?')) {
                    window.location.href = 'tickets.php?update_status=' + ticketId + '&status=' + status;
                }
            }
        }
    </script>
</body>
</html>