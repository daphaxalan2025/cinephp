<?php
// admin/payments.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// ============ HANDLE PAYMENT STATUS UPDATE ============
if (isset($_GET['update_payment'])) {
    $payment_id = $_GET['update_payment'];
    $new_status = $_GET['status'] ?? 'completed';
    
    $pdo->beginTransaction();
    try {
        // Update payment status
        $stmt = $pdo->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
        $stmt->execute([$new_status, $payment_id]);
        
        // If payment is completed, update ticket status to paid
        if ($new_status == 'completed') {
            $stmt = $pdo->prepare("
                UPDATE tickets t 
                JOIN payments p ON t.id = p.ticket_id 
                SET t.status = 'paid' 
                WHERE p.id = ?
            ");
            $stmt->execute([$payment_id]);
        }
        
        $pdo->commit();
        setFlash("Payment status updated to $new_status", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('Error updating payment', 'error');
    }
    header('Location: payments.php');
    exit;
}

// ============ HANDLE BULK ACTIONS ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected = $_POST['selected'] ?? [];
    
    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        
        $pdo->beginTransaction();
        try {
            if ($action == 'confirm') {
                // Confirm payments
                $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'completed' WHERE id IN ($placeholders)");
                $stmt->execute($selected);
                
                // Update tickets
                $stmt = $pdo->prepare("
                    UPDATE tickets t 
                    JOIN payments p ON t.id = p.ticket_id 
                    SET t.status = 'paid' 
                    WHERE p.id IN ($placeholders)
                ");
                $stmt->execute($selected);
                
                setFlash(count($selected) . ' payments confirmed', 'success');
            } elseif ($action == 'reject') {
                // Reject payments
                $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'failed' WHERE id IN ($placeholders)");
                $stmt->execute($selected);
                setFlash(count($selected) . ' payments rejected', 'success');
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('Error processing bulk action', 'error');
        }
    }
    header('Location: payments.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "
    SELECT p.*, 
           u.username, u.first_name, u.last_name, u.email,
           t.ticket_code, t.total_price as ticket_price,
           s.show_date,
           m.title as movie_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN tickets t ON p.ticket_id = t.id
    JOIN screenings s ON t.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE 1=1
";
$params = [];

if ($status_filter) {
    $sql .= " AND p.payment_status = ?";
    $params[] = $status_filter;
}
if ($method_filter) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method_filter;
}
if ($date_from) {
    $sql .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY p.payment_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'completed'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'completed'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'failed'")->fetchColumn(),
    'today' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND payment_status = 'completed'")->fetchColumn()
];

// Payment methods with icons
$payment_methods = [
    'credit_card' => ['name' => 'Credit Card', 'icon' => '💳'],
    'paypal' => ['name' => 'PayPal', 'icon' => '🅿️'],
    'gcash' => ['name' => 'GCash', 'icon' => '📱'],
    'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => '🏦'],
    'cash' => ['name' => 'Cash', 'icon' => '💰']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        }
        .filter-section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #00ffff;
            margin-bottom: 30px;
        }
        .bulk-actions {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #00ffff;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .payment-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-pending {
            background: rgba(255, 255, 68, 0.2);
            color: #ffff44;
            border: 1px solid #ffff44;
        }
        .status-completed {
            background: rgba(68, 255, 68, 0.2);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        .status-failed {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }
        .method-icon {
            font-size: 1.2rem;
            margin-right: 5px;
        }
    </style>
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
                <a href="payments.php" class="active">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Payment Management</h1>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['total'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['today'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions">
                <span style="color: #fff;">Selected: <span id="selectedCount">0</span></span>
                <button type="submit" name="bulk_action" value="confirm" class="btn btn-primary btn-small"
                        onclick="return confirm('Confirm selected payments?')">✓ Confirm Selected</button>
                <button type="submit" name="bulk_action" value="reject" class="btn btn-small" 
                        style="border-color: #ff4444; color: #ff4444;"
                        onclick="return confirm('Reject selected payments?')">✗ Reject Selected</button>
                <button type="button" onclick="selectAll()" class="btn btn-small">Select All</button>
                <button type="button" onclick="deselectAll()" class="btn btn-small">Deselect All</button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                    <div style="flex: 1; min-width: 150px;">
                        <label for="status">Status</label>
                        <select id="status" name="status" style="width: 100%; padding: 8px; background: #000; color: #fff; border: 1px solid #00ffff;">
                            <option value="">All</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label for="method">Payment Method</label>
                        <select id="method" name="method" style="width: 100%; padding: 8px; background: #000; color: #fff; border: 1px solid #00ffff;">
                            <option value="">All</option>
                            <?php foreach ($payment_methods as $key => $method): ?>
                                <option value="<?php echo $key; ?>" <?php echo $method_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $method['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" 
                               style="width: 100%; padding: 8px; background: #000; color: #fff; border: 1px solid #00ffff;">
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" 
                               style="width: 100%; padding: 8px; background: #000; color: #fff; border: 1px solid #00ffff;">
                    </div>
                    
                    <div>
                        <button type="submit" formaction="payments.php" class="btn btn-primary">Apply Filters</button>
                        <a href="payments.php" class="btn">Clear</a>
                    </div>
                </div>
            </div>
            
            <!-- Payments Table -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Movie</th>
                        <th>Ticket</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><input type="checkbox" name="selected[]" value="<?php echo $payment['id']; ?>" class="payment-checkbox"></td>
                            <td><span style="color: #00ffff; font-family: monospace;"><?php echo $payment['transaction_id']; ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($payment['username']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($payment['movie_title']); ?></td>
                            <td><span style="font-family: monospace;"><?php echo $payment['ticket_code']; ?></span></td>
                            <td><strong style="color: #00ffff;">$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td>
                                <span class="method-icon"><?php echo $payment_methods[$payment['payment_method']]['icon'] ?? '💰'; ?></span>
                                <?php echo $payment_methods[$payment['payment_method']]['name'] ?? ucfirst($payment['payment_method']); ?>
                            </td>
                            <td>
                                <span class="payment-badge status-<?php echo $payment['payment_status']; ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                            <td>
                                <?php if ($payment['payment_status'] == 'pending'): ?>
                                    <a href="?update_payment=<?php echo $payment['id']; ?>&status=completed" 
                                       class="btn-small" style="border-color: #44ff44; color: #44ff44;"
                                       onclick="return confirm('Confirm this payment?')">✓ Confirm</a>
                                    <a href="?update_payment=<?php echo $payment['id']; ?>&status=failed" 
                                       class="btn-small" style="border-color: #ff4444; color: #ff4444;"
                                       onclick="return confirm('Reject this payment?')">✗ Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function toggleAll(source) {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = source.checked);
            updateSelectedCount();
        }
        
        function selectAll() {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = true);
            document.getElementById('selectAll').checked = true;
            updateSelectedCount();
        }
        
        function deselectAll() {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const count = document.querySelectorAll('.payment-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = count;
        }
        
        document.querySelectorAll('.payment-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
    </script>
</body>
</html>