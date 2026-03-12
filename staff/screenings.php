<?php
// staff/screenings.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;
$cinema_filter = $cinema_id ? "AND cinema_id = $cinema_id" : "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
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
    header('Location: screenings.php');
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $cinema_id = $_POST['cinema_id'] ?? ($user['cinema_id'] ?? '');
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
    
    // Check for duplicate
    if (empty($errors)) {
        $check = $pdo->prepare("
            SELECT id FROM screenings 
            WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?
        ");
        $check->execute([$cinema_id, $screen_number, $show_date, $show_time, $screening_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Screening already exists for this cinema, screen, date and time';
        }
        
        // Check max 5 screenings per screen per day
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM screenings 
            WHERE cinema_id = ? AND screen_number = ? AND show_date = ?
        ");
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
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: screenings.php');
        exit;
    }
}

// Get all movies for dropdown
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all cinemas for dropdown (filter by staff's assignment)
$cinema_sql = "SELECT id, name, total_screens FROM cinemas";
if ($cinema_id) {
    $cinema_sql .= " WHERE id = $cinema_id";
}
$cinemas = $pdo->query($cinema_sql . " ORDER BY name")->fetchAll();

// Get filter parameters
$filter_cinema = $_GET['cinema_id'] ?? ($cinema_id ?: '');
$filter_movie = $_GET['movie_id'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query for screenings
$sql = "
    SELECT s.*, m.title, m.duration, m.rating, c.name as cinema_name,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE 1=1
";
$params = [];

if ($filter_cinema) {
    $sql .= " AND s.cinema_id = ?";
    $params[] = $filter_cinema;
}
if ($filter_movie) {
    $sql .= " AND s.movie_id = ?";
    $params[] = $filter_movie;
}
if ($filter_date) {
    $sql .= " AND s.show_date = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY s.show_date DESC, s.show_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$screenings = $stmt->fetchAll();

// Get screening for editing
$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}

// Group screenings by date for display
$grouped_screenings = [];
foreach ($screenings as $s) {
    $date = $s['show_date'];
    if (!isset($grouped_screenings[$date])) {
        $grouped_screenings[$date] = [];
    }
    $grouped_screenings[$date][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - Staff</title>
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
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 30px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Staff Notice */
        .staff-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 25px;
            margin: 30px 0;
            position: relative;
            overflow: hidden;
        }
        
        .filter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
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
        
        .filter-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 12px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        /* Date Groups */
        .date-group {
            margin-bottom: 40px;
        }
        
        .date-title {
            color: var(--red);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(229, 9, 20, 0.2);
            position: relative;
        }
        
        .date-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 2px;
            background: var(--red);
        }
        
        .screenings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .screening-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .screening-card::before {
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
        
        .screening-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .screening-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .screening-time {
            font-size: 1.3rem;
            color: var(--red);
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        .screen-number {
            color: var(--text-secondary);
        }
        
        .screening-movie {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #fff;
            font-weight: 600;
        }
        
        .screening-details {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .seat-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 40px;
        }
        
        .available-seats {
            color: #44ff44;
            font-weight: 600;
        }
        
        .sold-seats {
            color: #ffff44;
            font-weight: 600;
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--red);
            border-radius: 4px;
        }
        
        .screening-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-small {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .btn-small.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-small.delete:hover {
            background: #ff4444;
            color: #fff;
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .form-container h2 {
            color: var(--red);
            font-size: 2rem;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-text {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 5px;
            padding-left: 15px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
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
            padding: 14px 30px;
            border-radius: 40px;
            background: transparent;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            border-color: var(--red);
            color: var(--red);
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-error {
            border-left: 4px solid #ff4444;
            color: #ff6b6b;
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '🎬';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 6rem;
            opacity: 0.03;
            pointer-events: none;
        }
        
        .empty-state p {
            color: var(--text-secondary);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .screening-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .screening-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php" class="active">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Manage Screenings</h1>
            <a href="?action=add" class="btn-primary">➕ Add Screening</a>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if ($cinema_id): ?>
            <div class="staff-notice">
                ℹ️ You are adding screenings for your assigned cinema only.
            </div>
        <?php endif; ?>
        
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
                    <?php echo $edit_screening ? 'Edit Screening' : 'Add New Screening'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_screening): ?>
                        <input type="hidden" name="screening_id" value="<?php echo $edit_screening['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Movie</label>
                            <select name="movie_id" required>
                                <option value="">Select Movie</option>
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
                            <select name="cinema_id" required>
                                <option value="">Select Cinema</option>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <option value="<?php echo $cinema['id']; ?>"
                                        <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cinema['name']); ?> (<?php echo $cinema['total_screens']; ?> screens)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Screen Number</label>
                            <input type="number" name="screen_number" 
                                   value="<?php echo $edit_screening['screen_number'] ?? '1'; ?>" 
                                   min="1" max="20" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" 
                                   value="<?php echo $edit_screening['price'] ?? '12.50'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Show Date</label>
                            <input type="date" name="show_date" 
                                   value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Show Time</label>
                            <input type="time" name="show_time" 
                                   value="<?php echo $edit_screening['show_time'] ?? '10:00'; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Available Seats</label>
                        <input type="number" name="available_seats" 
                               value="<?php echo $edit_screening['available_seats'] ?? '40'; ?>" 
                               min="1" max="100" required>
                        <small class="form-text">Maximum 5 screenings per screen per day</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_screening ? 'Update Screening' : 'Add Screening'; ?>
                        </button>
                        <a href="screenings.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Filter by Cinema</label>
                    <select name="cinema_id">
                        <option value="">All Cinemas</option>
                        <?php foreach ($cinemas as $cinema): ?>
                            <option value="<?php echo $cinema['id']; ?>" <?php echo $filter_cinema == $cinema['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cinema['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Filter by Movie</label>
                    <select name="movie_id">
                        <option value="">All Movies</option>
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo $movie['id']; ?>" <?php echo $filter_movie == $movie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Filter by Date</label>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn-primary">Apply</button>
                    <a href="screenings.php" class="btn">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Screenings Display -->
        <?php if (empty($screenings)): ?>
            <div class="empty-state">
                <p style="margin-bottom: 10px;">No screenings found.</p>
                <p style="color: var(--text-secondary);">Use the filters above or add a new screening.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_screenings as $date => $screenings): ?>
                <div class="date-group">
                    <h2 class="date-title"><?php echo date('l, F d, Y', strtotime($date)); ?></h2>
                    
                    <div class="screenings-grid">
                        <?php foreach ($screenings as $screening): 
                            $sold = $screening['tickets_sold'];
                            $available = $screening['available_seats'];
                            $total = $sold + $available;
                            $occupancy = ($sold / $total) * 100;
                        ?>
                            <div class="screening-card">
                                <div class="screening-header">
                                    <span class="screening-time"><?php echo date('h:i A', strtotime($screening['show_time'])); ?></span>
                                    <span class="screen-number">Screen <?php echo $screening['screen_number']; ?></span>
                                </div>
                                
                                <div class="screening-movie">
                                    <?php echo htmlspecialchars($screening['title']); ?>
                                </div>
                                
                                <div class="screening-details">
                                    <?php echo htmlspecialchars($screening['cinema_name']); ?><br>
                                    <?php echo $screening['rating']; ?> • <?php echo $screening['duration']; ?> min
                                </div>
                                
                                <div class="seat-info">
                                    <span>🎟️ Sold: <span class="sold-seats"><?php echo $sold; ?></span></span>
                                    <span>🪑 Left: <span class="available-seats"><?php echo $available; ?></span></span>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $occupancy; ?>%"></div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin: 15px 0;">
                                    <span>Price: <span style="color: var(--red);">$<?php echo number_format($screening['price'], 2); ?></span></span>
                                    <span>Total: <span style="color: var(--red);">$<?php echo number_format($screening['price'] * $sold, 2); ?></span></span>
                                </div>
                                
                                <div class="screening-actions">
                                    <a href="?edit=<?php echo $screening['id']; ?>" class="btn-small">Edit</a>
                                    <a href="tickets_list.php?screening_id=<?php echo $screening['id']; ?>" class="btn-small">Tickets</a>
                                    <a href="?delete=<?php echo $screening['id']; ?>" class="btn-small delete" 
                                       onclick="return confirm('Delete this screening?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>