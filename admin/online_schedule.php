<?php
// admin/online_schedule.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Check if schedule has tickets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE online_schedule_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete schedule with existing tickets', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM online_schedule WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Schedule deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: online_schedule.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $max_viewers = intval($_POST['max_viewers'] ?? 100);
    $price = floatval($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'scheduled';
    $schedule_id = $_POST['schedule_id'] ?? '';
    
    // Validation
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($max_viewers < 1) $errors[] = 'Max viewers must be at least 1';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    // Check for duplicate
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM online_schedule WHERE movie_id = ? AND show_date = ? AND show_time = ? AND id != ?");
        $check->execute([$movie_id, $show_date, $show_time, $schedule_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Schedule already exists for this date and time';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($schedule_id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE online_schedule 
                    SET movie_id=?, show_date=?, show_time=?, max_viewers=?, price=?, status=? 
                    WHERE id=?
                ");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status, $schedule_id]);
                setFlash('Schedule updated successfully', 'success');
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO online_schedule (movie_id, show_date, show_time, max_viewers, price, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status]);
                setFlash('Schedule added successfully', 'success');
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: online_schedule.php');
        exit;
    }
}

// Get all movies for dropdown
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all schedules with movie details
$schedules = $pdo->query("
    SELECT os.*, m.title 
    FROM online_schedule os
    JOIN movies m ON os.movie_id = m.id
    ORDER BY os.show_date DESC, os.show_time DESC
")->fetchAll();

// Get schedule for editing
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM online_schedule WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_schedule = $stmt->fetch();
}

// Status options
$status_options = ['scheduled', 'live', 'ended', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Schedule - CinemaTicket</title>
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
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-scheduled {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        .status-live {
            background: rgba(68, 255, 68, 0.15);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        .status-ended {
            background: rgba(136, 136, 136, 0.15);
            color: #888;
            border: 1px solid #888;
        }
        .status-cancelled {
            background: rgba(255, 68, 68, 0.15);
            color: #ff4444;
            border: 1px solid #ff4444;
        }
        
        /* Capacity Bar */
        .capacity-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .capacity-bar {
            width: 120px;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        .capacity-fill {
            height: 100%;
            background: var(--red);
            border-radius: 3px;
            transition: width 0.3s;
        }
        .capacity-numbers {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            margin-top: 30px;
            margin-bottom: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 2px;
            position: relative;
            padding-bottom: 20px;
        }
        
        .form-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 400;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-text {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-weight: 300;
            letter-spacing: 0.5px;
            padding-left: 15px;
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
        
        .movie-title {
            color: var(--red);
            font-weight: 600;
        }
        
        .price-style {
            color: var(--red);
            font-weight: 700;
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
        
        .btn-small.delete {
            border-color: rgba(255, 68, 68, 0.3);
            color: #ff4444;
        }
        
        .btn-small.delete:hover {
            border-color: #ff4444;
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
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
            padding: 14px 32px;
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
            padding: 14px 32px;
            border-radius: 40px;
            background: rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
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
        
        /* Stats Summary */
        .stats-bar {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .stat-summary {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .stat-summary:hover {
            border-color: var(--red);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.15);
        }
        
        .stat-summary span {
            color: var(--text-secondary);
            font-weight: 400;
            margin-right: 10px;
        }
        
        .stat-summary strong {
            color: var(--red);
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            margin-top: 30px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .empty-state p:first-child {
            color: #fff;
            font-size: 1.3rem;
            margin-bottom: 20px;
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
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .stats-bar {
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
                <a href="online_schedule.php" class="active">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Online Schedule</h1>
            <a href="?action=add" class="btn-primary">+ Add Time Slot</a>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px; margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-container">
                <h2>
                    <?php echo $edit_schedule ? 'Edit Time Slot' : 'Add New Time Slot'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_schedule): ?>
                        <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Movie</label>
                            <select name="movie_id" required>
                                <option value="">Select Movie</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo $movie['id']; ?>"
                                        <?php echo (($edit_schedule['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($movie['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" 
                                   value="<?php echo $edit_schedule['price'] ?? '10.00'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Show Date</label>
                            <input type="date" name="show_date" 
                                   value="<?php echo $edit_schedule['show_date'] ?? date('Y-m-d'); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Show Time</label>
                            <input type="time" name="show_time" 
                                   value="<?php echo $edit_schedule['show_time'] ?? '20:00'; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Maximum Viewers</label>
                            <input type="number" name="max_viewers" 
                                   value="<?php echo $edit_schedule['max_viewers'] ?? '100'; ?>" 
                                   min="1" max="1000" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <?php foreach ($status_options as $s): ?>
                                    <option value="<?php echo $s; ?>"
                                        <?php echo (($edit_schedule['status'] ?? 'scheduled') == $s) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_schedule ? 'Update Schedule' : 'Add Schedule'; ?>
                        </button>
                        <a href="online_schedule.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Schedule List -->
        <?php if (empty($schedules)): ?>
            <div class="empty-state">
                <p>No online schedules found</p>
                <p>Click "Add Time Slot" to create your first online streaming schedule.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Movie</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Capacity</th>
                            <th>Booked</th>
                            <th>Available</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): 
                            $available = $schedule['max_viewers'] - $schedule['current_viewers'];
                            $capacity_percent = ($schedule['current_viewers'] / $schedule['max_viewers']) * 100;
                        ?>
                            <tr>
                                <td><span class="movie-title">#<?php echo $schedule['id']; ?></span></td>
                                <td><span class="movie-title"><?php echo htmlspecialchars($schedule['title']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($schedule['show_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($schedule['show_time'])); ?></td>
                                <td><span class="price-style">$<?php echo number_format($schedule['price'], 2); ?></span></td>
                                <td><?php echo $schedule['max_viewers']; ?></td>
                                <td><?php echo $schedule['current_viewers']; ?></td>
                                <td>
                                    <div class="capacity-container">
                                        <span><?php echo $available; ?></span>
                                        <div class="capacity-bar">
                                            <div class="capacity-fill" style="width: <?php echo $capacity_percent; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $schedule['status']; ?>">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $schedule['id']; ?>" class="btn-small">Edit</a>
                                    <a href="?delete=<?php echo $schedule['id']; ?>" class="btn-small delete" 
                                       onclick="return confirm('Delete this schedule?')">Delete</a>
                                    <a href="tickets.php?online_id=<?php echo $schedule['id']; ?>" class="btn-small">Tickets</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Stats Summary -->
            <div class="stats-bar">
                <div class="stat-summary">
                    <span>Total Slots</span>
                    <strong><?php echo count($schedules); ?></strong>
                </div>
                <div class="stat-summary">
                    <span>Live Now</span>
                    <strong><?php echo count(array_filter($schedules, fn($s) => $s['status'] == 'live')); ?></strong>
                </div>
                <div class="stat-summary">
                    <span>Total Capacity</span>
                    <strong><?php echo array_sum(array_column($schedules, 'max_viewers')); ?></strong>
                </div>
                <div class="stat-summary">
                    <span>Total Booked</span>
                    <strong><?php echo array_sum(array_column($schedules, 'current_viewers')); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>