<?php
// admin/payments.php - BASED ON YOUR ACTUAL XAMPP DATABASE
// Columns that exist: transaction_id, paymongo_checkout_id, description, payment_method
// Columns REMOVED: payment_status, proof_of_payment (deleted from your DB)
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$user = getCurrentUser();

// ============ HANDLE PAYMENT STATUS UPDATE ============
if (isset($_GET['update_payment'])) {
    $payment_id = (int)$_GET['update_payment'];
    $new_status = $_GET['status'] ?? 'completed';
    
    $pdo->beginTransaction();
    try {
        // Get ticket_id
        $stmt = $pdo->prepare("SELECT ticket_id FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // Since payment_status column doesn't exist, we track status via ticket status only
        // Update ticket based on action
        if ($new_status == 'completed' && $payment['ticket_id']) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'paid' WHERE id = ?");
            $stmt->execute([$payment['ticket_id']]);
            setFlash('Ticket activated successfully', 'success');
        }
        
        if ($new_status == 'failed' && $payment['ticket_id']) {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            $stmt->execute([$payment['ticket_id']]);
            setFlash('Ticket cancelled', 'success');
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: payments.php');
    exit;
}

// ============ BULK ACTIONS ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected = $_POST['selected'] ?? [];
    
    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        
        $pdo->beginTransaction();
        try {
            // Get all ticket_ids
            $stmt = $pdo->prepare("SELECT ticket_id FROM payments WHERE id IN ($placeholders)");
            $stmt->execute($selected);
            $ticket_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($action == 'confirm') {
                // Update tickets to paid
                if (!empty($ticket_ids)) {
                    $ticket_placeholders = implode(',', array_fill(0, count($ticket_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'paid' WHERE id IN ($ticket_placeholders)");
                    $stmt->execute($ticket_ids);
                }
                setFlash(count($selected) . ' payment(s) confirmed, tickets activated', 'success');
            } elseif ($action == 'reject') {
                // Update tickets to cancelled
                if (!empty($ticket_ids)) {
                    $ticket_placeholders = implode(',', array_fill(0, count($ticket_ids), '?'));
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled', cancelled_at = NOW() WHERE id IN ($ticket_placeholders)");
                    $stmt->execute($ticket_ids);
                }
                setFlash(count($selected) . ' payment(s) rejected, tickets cancelled', 'success');
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
    } else {
        setFlash('No payments selected', 'error');
    }
    header('Location: payments.php');
    exit;
}

// ============ GET FILTERS ============
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query - ONLY columns that exist in your actual XAMPP database
$sql = "
    SELECT 
        p.id,
        p.user_id,
        p.ticket_id,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.paymongo_checkout_id,
        p.description,
        p.payment_date,
        u.username,
        u.first_name,
        u.last_name,
        u.email,
        t.ticket_code,
        t.ticket_type,
        t.status as ticket_status,
        CASE 
            WHEN t.ticket_type = 'cinema' THEN (SELECT title FROM movies WHERE id = (SELECT movie_id FROM screenings WHERE id = t.screening_id))
            WHEN t.ticket_type = 'online' THEN (SELECT title FROM movies WHERE id = (SELECT movie_id FROM online_schedule WHERE id = t.online_schedule_id))
            ELSE NULL
        END as movie_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN tickets t ON p.ticket_id = t.id
    WHERE 1=1
";
$params = [];

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

// Statistics - based on ticket status since no payment_status column
$stats = [
    'total' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn(),
    'pending_count' => $pdo->query("SELECT COUNT(*) FROM payments p JOIN tickets t ON p.ticket_id = t.id WHERE t.status = 'pending'")->fetchColumn(),
    'paid_count' => $pdo->query("SELECT COUNT(*) FROM payments p JOIN tickets t ON p.ticket_id = t.id WHERE t.status = 'paid'")->fetchColumn(),
    'cancelled_count' => $pdo->query("SELECT COUNT(*) FROM payments p JOIN tickets t ON p.ticket_id = t.id WHERE t.status = 'cancelled'")->fetchColumn(),
    'today' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE()")->fetchColumn()
];

// Payment methods
$payment_methods = [
    'gcash' => ['name' => 'GCash', 'icon' => '📱'],
    'paymaya' => ['name' => 'PayMaya', 'icon' => '💙'],
    'credit_card' => ['name' => 'Credit Card', 'icon' => '💳'],
    'grab_pay' => ['name' => 'GrabPay', 'icon' => '🟢'],
    'paypal' => ['name' => 'PayPal', 'icon' => '🅿️'],
    'online_banking' => ['name' => 'Online Banking', 'icon' => '🏦']
];

$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.3);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(26,26,26,0.9) 0%, rgba(20,20,20,0.95) 100%);
            --success-color: #44ff44;
            --danger-color: #ff4444;
            --warning-color: #ffff44;
        }
        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.2);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,240,240,0.95) 100%);
            --success-color: #00aa00;
            --danger-color: #cc0000;
            --warning-color: #cc8800;
        }
        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255,0,255,0.5);
            --border-color: rgba(255,0,255,0.3);
            --card-bg: linear-gradient(135deg, rgba(26,26,58,0.9) 0%, rgba(20,20,50,0.95) 100%);
            --success-color: #00ffff;
            --danger-color: #ff00ff;
            --warning-color: #ffff00;
        }
        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0,255,0,0.5);
            --border-color: rgba(0,255,0,0.3);
            --card-bg: linear-gradient(135deg, rgba(10,26,10,0.9) 0%, rgba(5,20,5,0.95) 100%);
            --success-color: #00ff00;
            --danger-color: #ff0000;
            --warning-color: #ffff00;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            position: relative;
            transition: background-color 0.3s ease;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, var(--accent) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, var(--accent) 0%, transparent 50%);
            opacity: 0.03;
            pointer-events: none;
            z-index: -1;
        }
        .navbar {
            background: rgba(var(--bg-secondary), 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.8rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-container {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        .logo {
            color: var(--accent);
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s;
        }
        .logo:hover { text-shadow: var(--accent-glow); }
        .logo::before { content: "🎬"; margin-right: 8px; font-size: 1.2rem; filter: drop-shadow(0 0 5px var(--accent)); }
        .nav-links { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            background: var(--accent);
            transition: width 0.3s;
        }
        .nav-links a:hover { color: var(--accent); }
        .nav-links a:hover::after { width: 60%; }
        .nav-links a.active { color: var(--accent); }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 30px 0;
            text-transform: uppercase;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px 15px;
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
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
            border-color: var(--accent);
            box-shadow: 0 20px 40px var(--accent-glow);
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }
        .stat-value.revenue { color: var(--accent); }
        .stat-label {
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .bulk-actions {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .selected-info {
            color: var(--text-primary);
            font-weight: 500;
            margin-right: auto;
        }
        .selected-info span {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.2rem;
            margin-left: 5px;
        }
        .filter-section {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .filter-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 20px var(--accent-glow);
        }
        .filter-actions { display: flex; gap: 10px; }
        .ticket-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-paid {
            background: rgba(68,255,68,0.15);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        .status-pending {
            background: rgba(229,9,20,0.15);
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        .status-cancelled {
            background: rgba(255,68,68,0.15);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        .method-icon { font-size: 1.2rem; margin-right: 5px; }
        .table-container {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow-x: auto;
            margin-top: 30px;
            padding: 5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 1000px;
        }
        .data-table th {
            background: rgba(229,9,20,0.15);
            color: var(--accent);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table tr:hover { background: rgba(229,9,20,0.05); }
        .transaction-id {
            color: var(--accent);
            font-family: monospace;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .amount { color: var(--accent); font-weight: 700; }
        .payment-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: var(--accent); }
        .btn-small {
            padding: 5px 12px;
            font-size: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 30px;
            color: var(--text-primary);
            transition: all 0.3s;
            background: rgba(0,0,0,0.3);
            display: inline-block;
            text-decoration: none;
        }
        .btn-small:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-small.confirm {
            border-color: var(--success-color);
            color: var(--success-color);
        }
        .btn-small.reject {
            border-color: var(--danger-color);
            color: var(--danger-color);
        }
        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
        }
        .btn {
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
            background: rgba(0,0,0,0.3);
            display: inline-block;
        }
        .btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent);
        }
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            h1 { font-size: 2rem; }
            .filter-grid { flex-direction: column; }
            .bulk-actions { flex-direction: column; align-items: flex-start; }
            .selected-info { margin-right: 0; margin-bottom: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
                <a href="payments.php" class="active">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php">Profile</a>                
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Payment Management</h1>
        <div class="cinema-strip"></div>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value revenue">₱<?php echo number_format($stats['total'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--accent);"><?php echo $stats['pending_count']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success-color);"><?php echo $stats['paid_count']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--danger-color);"><?php echo $stats['cancelled_count']; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value revenue">₱<?php echo number_format($stats['today'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>
        
        <form method="POST" id="bulkForm">
            <div class="bulk-actions">
                <div class="selected-info">Selected: <span id="selectedCount">0</span></div>
                <button type="submit" name="bulk_action" value="confirm" class="btn-small confirm" onclick="return confirm('Confirm selected payments? This will activate tickets.')">✓ Confirm</button>
                <button type="submit" name="bulk_action" value="reject" class="btn-small reject" onclick="return confirm('Reject selected payments? This will cancel tickets.')">✗ Reject</button>
                <button type="button" onclick="selectAll()" class="btn-small">Select All</button>
                <button type="button" onclick="deselectAll()" class="btn-small">Deselect All</button>
            </div>
            
            <div class="filter-section">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Payment Method</label>
                        <select name="method">
                            <option value="">All</option>
                            <?php foreach ($payment_methods as $key => $method): ?>
                                <option value="<?php echo $key; ?>" <?php echo $method_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $method['icon']; ?> <?php echo $method['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary">Apply</button>
                        <a href="payments.php" class="btn">Clear</a>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox" onclick="toggleAll(this)" class="payment-checkbox"></th>
                            <th>Transaction ID</th>
                            <th>Customer</th>
                            <th>Movie</th>
                            <th>Ticket Code</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Ticket Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:60px;">No payments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected[]" value="<?php echo $payment['id']; ?>" class="payment-checkbox"></td>
                                    <td><span class="transaction-id"><?php echo htmlspecialchars($payment['transaction_id']); ?></span></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                                        <small><?php echo '@' . htmlspecialchars($payment['username']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['movie_title'] ?? 'N/A'); ?></td>
                                    <td><small><?php echo htmlspecialchars($payment['ticket_code']); ?></small></td>
                                    <td><span class="amount">₱<?php echo number_format($payment['amount'], 2); ?></span></td>
                                    <td>
                                        <span class="method-icon"><?php echo $payment_methods[$payment['payment_method']]['icon'] ?? '💰'; ?></span>
                                        <?php echo $payment_methods[$payment['payment_method']]['name'] ?? ucfirst($payment['payment_method']); ?>
                                    </td>
                                    <td>
                                        <span class="ticket-status-badge status-<?php echo $payment['ticket_status']; ?>">
                                            <?php echo strtoupper($payment['ticket_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <?php if ($payment['ticket_status'] == 'pending'): ?>
                                            <a href="?update_payment=<?php echo $payment['id']; ?>&status=completed" class="btn-small confirm" onclick="return confirm('Activate this ticket?')">Activate</a>
                                            <a href="?update_payment=<?php echo $payment['id']; ?>&status=failed" class="btn-small reject" onclick="return confirm('Cancel this ticket?')">Cancel</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </main>
    
    <script>
        function toggleAll(source) {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = source.checked);
            updateSelectedCount();
        }
        function selectAll() {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = true);
            if(document.getElementById('selectAllCheckbox')) document.getElementById('selectAllCheckbox').checked = true;
            updateSelectedCount();
        }
        function deselectAll() {
            document.querySelectorAll('.payment-checkbox').forEach(cb => cb.checked = false);
            if(document.getElementById('selectAllCheckbox')) document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedCount();
        }
        function updateSelectedCount() {
            const count = document.querySelectorAll('.payment-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = count;
        }
        document.querySelectorAll('.payment-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));
    </script>
</body>
</html>