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
            margin: 0;
            text-transform: uppercase;
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
            padding: 25px 20px;
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
        
        /* Filters Section */
        .filters-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .filters-form {
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
            margin-left: auto;
        }
        
        /* Table Container */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
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
        
        /* Ticket Status Badges */
        .ticket-status {
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
        
        .status-used {
            background: rgba(136, 136, 136, 0.15);
            border: 1px solid #888;
            color: #888;
        }
        
        /* Ticket Elements */
        .ticket-code {
            color: var(--red);
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        
        .customer-name {
            color: #fff;
            font-weight: 600;
        }
        
        .customer-username {
            color: var(--red);
            font-size: 0.8rem;
        }
        
        .movie-title {
            color: var(--red);
            font-weight: 500;
        }
        
        .show-time {
            color: var(--red);
            font-size: 0.8rem;
        }
        
        .seat-numbers {
            color: var(--red);
            font-weight: 500;
        }
        
        .amount {
            color: var(--red);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .transaction-id {
            color: var(--red);
            font-size: 0.7rem;
            font-family: monospace;
        }
        
        /* Status Select */
        .status-select {
            padding: 6px 10px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: #fff;
            font-size: 0.75rem;
            width: 100%;
            margin-bottom: 5px;
            cursor: pointer;
        }
        
        .status-select option {
            background: var(--deep-gray);
            color: #fff;
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
            font-size: 0.8rem;
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
            font-size: 0.8rem;
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
            text-align: center;
        }
        
        .btn-small:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-info {
            border-left-color: var(--red);
            color: var(--text-primary);
        }
        
        .alert-error {
            border-left-color: var(--red);
            color: #ff6b6b;
        }
        
        .alert-success {
            border-left-color: var(--red);
            color: var(--text-primary);
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.5;
        }
        
        /* Summary Stats */
        .summary-bar {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .summary-item {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .summary-item:hover {
            border-color: var(--red);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.15);
        }
        
        .summary-label {
            color: var(--text-secondary);
            font-weight: 400;
            margin-right: 10px;
        }
        
        .summary-value {
            color: var(--red);
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .summary-value.revenue {
            color: #44ff44;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 1400px;
            }
        }
        
        @media (max-width: 768px) {
            .filters-form {
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
            
            .summary-bar {
                flex-direction: column;
                align-items: flex-end;
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
                <a href="online_schedule.php">Schedule</a>
                <a href="users.php">Users</a>
                <a href="tickets.php" class="active">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Ticket Management</h1>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #44ff44;"><?php echo $stats['paid']; ?></div>
                <div class="stat-label">Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--red);"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #888;"><?php echo $stats['used']; ?></div>
                <div class="stat-label">Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #44ff44;">$<?php echo number_format($stats['revenue'], 2); ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_payments']; ?></div>
                <div class="stat-label">Pending Payments</div>
                <div class="stat-link">
                    <a href="payments.php?status=pending">Process →</a>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="used" <?php echo $status_filter == 'used' ? 'selected' : ''; ?>>Used</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Movie</label>
                    <select name="movie">
                        <option value="">All Movies</option>
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo $movie['id']; ?>" <?php echo $movie_filter == $movie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>User</label>
                    <select name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
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
                    <button type="submit" class="btn-primary">Apply</button>
                    <a href="tickets.php" class="btn">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Tickets Table -->
        <?php if (empty($tickets)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">No tickets found</p>
                <p style="color: var(--text-secondary); font-size: 1rem;">Adjust your filters or add some tickets to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
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
                                <td><span class="ticket-code"><?php echo $ticket['ticket_code']; ?></span></td>
                                <td>
                                    <div class="customer-name"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                    <div class="customer-username">@<?php echo htmlspecialchars($ticket['username']); ?></div>
                                </td>
                                <td><span class="movie-title"><?php echo htmlspecialchars($ticket['title']); ?></span></td>
                                <td><?php echo htmlspecialchars($ticket['cinema_name']); ?></td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($ticket['show_date'])); ?></div>
                                    <div class="show-time"><?php echo date('h:i A', strtotime($ticket['show_time'])); ?></div>
                                </td>
                                <td><span class="seat-numbers"><?php echo $ticket['seat_numbers']; ?></span></td>
                                <td><?php echo $ticket['quantity']; ?></td>
                                <td><span class="amount">$<?php echo number_format($ticket['total_price'], 2); ?></span></td>
                                <td>
                                    <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                        <?php echo strtoupper($ticket['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ticket['payment_id']): ?>
                                        <span class="ticket-status status-<?php echo $ticket['payment_status']; ?>" style="font-size: 0.7rem;">
                                            <?php echo ucfirst($ticket['payment_status']); ?>
                                        </span>
                                        <div class="transaction-id"><?php echo $ticket['transaction_id']; ?></div>
                                    <?php else: ?>
                                        <span style="color: #888;">No payment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select onchange="updateStatus(<?php echo $ticket['id']; ?>, this.value)" class="status-select">
                                        <option value="">Change Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="paid">Paid</option>
                                        <option value="used">Used</option>
                                    </select>
                                    <?php if ($ticket['payment_id']): ?>
                                        <a href="payments.php?view=<?php echo $ticket['payment_id']; ?>" class="btn-small" style="display: block; text-align: center;">Payment</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
            
            <!-- Summary -->
            <div class="summary-bar">
                <div class="summary-item">
                    <span class="summary-label">Displaying:</span>
                    <span class="summary-value"><?php echo count($tickets); ?> tickets</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total Revenue:</span>
                    <span class="summary-value revenue">$<?php echo number_format(array_sum(array_column($tickets, 'total_price')), 2); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function updateStatus(ticketId, status) {
            if (status) {
                if (confirm('🎬 Update ticket status to ' + status + '?')) {
                    window.location.href = 'tickets.php?update_status=' + ticketId + '&status=' + status;
                }
            }
        }
    </script>
</body>
</html>