<?php
// admin/screenings.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Check if screening has tickets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE screening_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete screening with existing tickets', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM screenings WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Screening deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: screenings.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $cinema_id = $_POST['cinema_id'] ?? '';
    $screen_number = intval($_POST['screen_number'] ?? 1);
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $available_seats = intval($_POST['available_seats'] ?? 40);
    $screening_id = $_POST['screening_id'] ?? '';
    
    // Validation
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($cinema_id)) $errors[] = 'Cinema is required';
    if ($screen_number < 1) $errors[] = 'Valid screen number is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($available_seats < 1 || $available_seats > 100) $errors[] = 'Available seats must be between 1 and 100';
    
    // Check for duplicate (same cinema, screen, date, time)
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?");
        $check->execute([$cinema_id, $screen_number, $show_date, $show_time, $screening_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Screening already exists for this cinema, screen, date and time';
        }
        
        // Check max 5 screenings per screen per day
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ?");
        $stmt->execute([$cinema_id, $screen_number, $show_date]);
        $count = $stmt->fetchColumn();
        
        if ($count >= 5 && !$screening_id) {
            $errors[] = 'Maximum 5 screenings allowed per screen per day';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($screening_id) {
                // Update
                $sql = "UPDATE screenings SET movie_id=?, cinema_id=?, screen_number=?, show_date=?, show_time=?, price=?, available_seats=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats, $screening_id]);
                setFlash('Screening updated successfully', 'success');
            } else {
                // Insert
                $sql = "INSERT INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats]);
                setFlash('Screening added successfully', 'success');
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: screenings.php');
        exit;
    }
}

// Get all movies
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all cinemas
$cinemas = $pdo->query("SELECT id, name, total_screens FROM cinemas ORDER BY name")->fetchAll();

// Get screenings with details
$screenings = $pdo->query("
    SELECT s.*, m.title as movie_title, m.poster, c.name as cinema_name,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    ORDER BY s.show_date DESC, s.show_time DESC
")->fetchAll();

// Get screening for editing
$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - CinemaTicket</title>
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
        
        /* Cinema Group Cards */
        .cinema-group {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .cinema-group::before {
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
        
        .cinema-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(229, 9, 20, 0.1);
            border-color: rgba(229, 9, 20, 0.2);
        }
        
        .cinema-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            margin-bottom: 25px;
        }
        
        .cinema-header h2 {
            color: #fff;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
            text-shadow: 0 2px 10px rgba(229, 9, 20, 0.3);
        }
        
        .film-strip-badge {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(229, 9, 20, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        /* Screen Rows */
        .screen-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .screen-row:hover {
            background: rgba(30, 30, 30, 0.8);
            border-color: rgba(229, 9, 20, 0.2);
        }
        
        .screen-number {
            font-size: 1.3rem;
            color: var(--red);
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            text-transform: uppercase;
            position: relative;
        }
        
        .screen-number::before {
            content: '';
            position: absolute;
            left: -10px;
            width: 3px;
            height: 70%;
            background: var(--red);
            border-radius: 3px;
        }
        
        /* Time Slots */
        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .time-slot {
            padding: 12px 20px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 30px;
            color: var(--text-primary);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: default;
            position: relative;
            overflow: hidden;
        }
        
        .time-slot::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(229, 9, 20, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .time-slot:hover {
            transform: translateY(-3px) scale(1.02);
            border-color: var(--red);
            box-shadow: 0 10px 20px rgba(229, 9, 20, 0.2);
        }
        
        .time-slot:hover::before {
            left: 100%;
        }
        
        .time-slot .time {
            font-weight: 600;
            color: #fff;
        }
        
        .time-slot .seats {
            color: var(--text-secondary);
            font-size: 0.85rem;
            padding: 3px 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
        }
        
        .time-slot .price {
            color: var(--red);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .time-slot .limited {
            color: var(--red);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 2px 8px;
            background: rgba(229, 9, 20, 0.15);
            border-radius: 20px;
        }
        
        .time-slot .actions {
            display: flex;
            gap: 8px;
            margin-left: 10px;
        }
        
        /* Buttons */
        .btn-small {
            padding: 5px 12px;
            font-size: 0.75rem;
            text-decoration: none;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 20px;
            color: var(--text-primary);
            transition: all 0.3s;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(0, 0, 0, 0.3);
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.2);
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
        
        /* Slots Available */
        .slots-available {
            color: var(--text-secondary);
            padding: 10px 22px;
            background: rgba(10, 10, 10, 0.4);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px dashed rgba(229, 9, 20, 0.3);
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 1px;
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 50px;
            margin-top: 30px;
            margin-bottom: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2.5rem;
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
            width: 100px;
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
            letter-spacing: 2px;
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
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-weight: 300;
            letter-spacing: 0.5px;
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
            margin: 40px 0;
            opacity: 0.5;
        }
        
        /* Stats Summary */
        .stat-box {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 15px 30px;
            transition: all 0.3s;
        }
        
        .stat-box:hover {
            border-color: var(--red);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.15);
        }
        
        .stat-box span {
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .stat-box strong {
            color: var(--red);
            font-size: 1.2rem;
            margin-left: 10px;
            font-weight: 700;
        }
        
        /* Date Headers */
        .date-header {
            color: var(--red);
            margin-bottom: 15px;
            font-weight: 600;
            letter-spacing: 1.5px;
            font-size: 0.95rem;
            text-transform: uppercase;
            position: relative;
            padding-left: 15px;
        }
        
        .date-header::before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--red);
            font-size: 1.2rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .screen-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .nav-links {
                display: none;
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
                <a href="screenings.php" class="active">Screenings</a>
                <a href="online_schedule.php">Schedule</a>
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
            <h1>Screenings</h1>
            <a href="?action=add" class="btn-primary">+ New Screening</a>
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
                    <?php echo $edit_screening ? 'Edit Screening' : 'New Screening'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_screening): ?>
                        <input type="hidden" name="screening_id" value="<?php echo $edit_screening['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Movie</label>
                            <select name="movie_id" required>
                                <option value="">Select a movie</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo $movie['id']; ?>" 
                                        <?php echo (($edit_screening['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($movie['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Cinema</label>
                            <select name="cinema_id" id="cinemaSelect" required onchange="updateScreenOptions()">
                                <option value="">Select a cinema</option>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <option value="<?php echo $cinema['id']; ?>" 
                                        data-screens="<?php echo $cinema['total_screens']; ?>"
                                        <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cinema['name']); ?> (<?php echo $cinema['total_screens']; ?> screens)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Screen</label>
                            <select name="screen_number" id="screenSelect" required>
                                <option value="">Select screen</option>
                                <?php if ($edit_screening): ?>
                                    <option value="<?php echo $edit_screening['screen_number']; ?>" selected>
                                        Screen <?php echo $edit_screening['screen_number']; ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" value="<?php echo $edit_screening['price'] ?? '12.50'; ?>" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="show_date" value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="show_time" value="<?php echo $edit_screening['show_time'] ?? '10:00'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Available Seats</label>
                        <input type="number" name="available_seats" value="<?php echo $edit_screening['available_seats'] ?? '40'; ?>" min="1" max="100" required>
                        <small class="form-text">Maximum 5 screenings per screen per day</small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_screening ? 'Update Screening' : 'Create Screening'; ?>
                        </button>
                        <a href="screenings.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Screenings Display -->
        <?php
        // Group screenings by cinema and screen
        $grouped = [];
        foreach ($screenings as $s) {
            $cinema = $s['cinema_name'];
            $screen = $s['screen_number'];
            $date = $s['show_date'];
            if (!isset($grouped[$cinema])) {
                $grouped[$cinema] = [];
            }
            if (!isset($grouped[$cinema][$screen])) {
                $grouped[$cinema][$screen] = [];
            }
            if (!isset($grouped[$cinema][$screen][$date])) {
                $grouped[$cinema][$screen][$date] = [];
            }
            $grouped[$cinema][$screen][$date][] = $s;
        }
        ?>
        
        <?php if (empty($screenings)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">No screenings scheduled</p>
                <p style="color: var(--text-secondary); font-size: 1rem;">Click the "New Screening" button to add your first screening.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $cinema => $screens): ?>
                <div class="cinema-group">
                    <div class="cinema-header">
                        <h2><?php echo htmlspecialchars($cinema); ?></h2>
                        <span class="film-strip-badge">Now Playing</span>
                    </div>
                    
                    <?php foreach ($screens as $screen => $dates): ?>
                        <div class="screen-row">
                            <div class="screen-number">
                                Screen <?php echo $screen; ?>
                            </div>
                            <div>
                                <?php foreach ($dates as $date => $screenings): ?>
                                    <div style="margin-bottom: 25px;">
                                        <div class="date-header">
                                            <?php echo date('l, F j, Y', strtotime($date)); ?>
                                        </div>
                                        <div class="time-slots">
                                            <?php foreach ($screenings as $s): 
                                                $sold = $s['tickets_sold'];
                                                $available = $s['available_seats'];
                                                $percentBooked = ($sold / $available) * 100;
                                            ?>
                                                <div class="time-slot">
                                                    <span class="time"><?php echo date('g:i A', strtotime($s['show_time'])); ?></span>
                                                    <span class="seats"><?php echo $available - $sold; ?>/<?php echo $available; ?> seats</span>
                                                    <span class="price">$<?php echo number_format($s['price'], 2); ?></span>
                                                    <?php if ($percentBooked >= 80): ?>
                                                        <span class="limited">Limited</span>
                                                    <?php endif; ?>
                                                    <span class="actions">
                                                        <a href="?edit=<?php echo $s['id']; ?>" class="btn-small">Edit</a>
                                                        <a href="?delete=<?php echo $s['id']; ?>" class="btn-small" onclick="return confirm('Delete this screening?')">Delete</a>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (count($screenings) < 5): ?>
                                                <div class="slots-available">
                                                    <?php echo 5 - count($screenings); ?> slot<?php echo (5 - count($screenings)) > 1 ? 's' : ''; ?> available
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Summary Stats -->
            <div style="margin-top: 40px; display: flex; gap: 20px; justify-content: flex-end;">
                <div class="stat-box">
                    <span>Total Screenings</span>
                    <strong><?php echo count($screenings); ?></strong>
                </div>
                <div class="stat-box">
                    <span>Cinemas</span>
                    <strong><?php echo count($grouped); ?></strong>
                </div>
                <div class="stat-box">
                    <span>Seats Available</span>
                    <strong><?php echo array_sum(array_column($screenings, 'available_seats')) - array_sum(array_column($screenings, 'tickets_sold')); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function updateScreenOptions() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            const screenSelect = document.getElementById('screenSelect');
            const selected = cinemaSelect.options[cinemaSelect.selectedIndex];
            
            if (selected && selected.dataset.screens) {
                const totalScreens = parseInt(selected.dataset.screens);
                let options = '<option value="">Select screen</option>';
                for (let i = 1; i <= totalScreens; i++) {
                    options += `<option value="${i}">Screen ${i}</option>`;
                }
                screenSelect.innerHTML = options;
            }
        }
        
        // Initialize on page load if editing
        <?php if ($edit_screening): ?>
        window.onload = function() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            cinemaSelect.value = '<?php echo $edit_screening['cinema_id']; ?>';
            updateScreenOptions();
            setTimeout(() => {
                document.getElementById('screenSelect').value = '<?php echo $edit_screening['screen_number']; ?>';
            }, 100);
        }
        <?php endif; ?>
    </script>
</body>
</html>