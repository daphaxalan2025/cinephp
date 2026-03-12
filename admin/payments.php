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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        
        /* Bulk Actions */
        .bulk-actions {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .selected-info {
            color: #fff;
            font-weight: 500;
            margin-right: auto;
        }
        
        .selected-info span {
            color: var(--red);
            font-weight: 700;
            font-size: 1.2rem;
            margin-left: 5px;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
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
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group select,
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
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Payment Badges */
        .payment-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-pending {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .status-completed {
            background: rgba(68, 255, 68, 0.15);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        
        .status-failed {
            background: rgba(255, 68, 68, 0.15);
            color: #ff4444;
            border: 1px solid #ff4444;
        }
        
        .method-icon {
            font-size: 1.2rem;
            margin-right: 5px;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Table Container */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow: hidden;
            margin-top: 30px;
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
        
        .transaction-id {
            color: var(--red);
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #fff;
        }
        
        .customer-username {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .movie-title {
            color: var(--red);
            font-weight: 500;
        }
        
        .ticket-code {
            font-family: 'Monaco', 'Courier New', monospace;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .amount {
            color: var(--red);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        /* Checkbox */
        .payment-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--red);
        }
        
        /* Buttons */
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
            margin: 2px;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-small.confirm {
            border-color: #44ff44;
            color: #44ff44;
        }
        
        .btn-small.confirm:hover {
            background: rgba(68, 255, 68, 0.1);
            color: #44ff44;
            border-color: #44ff44;
        }
        
        .btn-small.reject {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-small.reject:hover {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
            border-color: #ff4444;
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
        
        .btn {
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 12px 28px;
            border-radius: 40px;
            background: rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn:hover {
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
        @media (max-width: 1024px) {
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 1200px;
            }
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                flex-direction: column;
            }
            
            .filter-actions {
                margin-left: 0;
                width: 100%;
            }
            
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .selected-info {
                margin-right: 0;
                margin-bottom: 10px;
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
                <a href="payments.php" class="active">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Payment Management</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value revenue">$<?php echo number_format($stats['total'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--red);"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #44ff44;"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ff4444;"><?php echo $stats['failed']; ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value revenue">$<?php echo number_format($stats['today'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions">
                <div class="selected-info">
                    Selected: <span id="selectedCount">0</span>
                </div>
                <button type="submit" name="bulk_action" value="confirm" class="btn-small confirm"
                        onclick="return confirm('Confirm selected payments?')">✓ Confirm Selected</button>
                <button type="submit" name="bulk_action" value="reject" class="btn-small reject"
                        onclick="return confirm('Reject selected payments?')">✗ Reject Selected</button>
                <button type="button" onclick="selectAll()" class="btn-small">Select All</button>
                <button type="button" onclick="deselectAll()" class="btn-small">Deselect All</button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Method</label>
                        <select name="method">
                            <option value="">All</option>
                            <?php foreach ($payment_methods as $key => $method): ?>
                                <option value="<?php echo $key; ?>" <?php echo $method_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $method['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" formaction="payments.php" class="btn-primary">Apply</button>
                        <a href="payments.php" class="btn">Clear</a>
                    </div>
                </div>
            </div>
            
            <!-- Payments Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="payment-checkbox"></th>
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
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                    No payments found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected[]" value="<?php echo $payment['id']; ?>" class="payment-checkbox"></td>
                                    <td><span class="transaction-id"><?php echo $payment['transaction_id']; ?></span></td>
                                    <td>
                                        <div class="customer-name"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                                        <div class="customer-username">@<?php echo htmlspecialchars($payment['username']); ?></div>
                                    </td>
                                    <td><span class="movie-title"><?php echo htmlspecialchars($payment['movie_title']); ?></span></td>
                                    <td><span class="ticket-code"><?php echo $payment['ticket_code']; ?></span></td>
                                    <td><span class="amount">$<?php echo number_format($payment['amount'], 2); ?></span></td>
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
                                               class="btn-small confirm"
                                               onclick="return confirm('Confirm this payment?')">Confirm</a>
                                            <a href="?update_payment=<?php echo $payment['id']; ?>&status=failed" 
                                               class="btn-small reject"
                                               onclick="return confirm('Reject this payment?')">Reject</a>
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