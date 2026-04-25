<?php
// admin/screenings.php - COMPLETELY FIXED
// SQL concatenation removed - using prepared statements only
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// Auto-expire past screenings
$stmt = $pdo->prepare("UPDATE screenings SET status = 'expired' WHERE CONCAT(show_date, ' ', show_time) < NOW() AND status != 'expired'");
$stmt->execute();

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
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
    $screen_number = intval($_POST['screen_number'] ?? 0);
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
    if ($available_seats < 1 || $available_seats > 500) $errors[] = 'Available seats must be between 1 and 500';
    
    // 3-month release date validation
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT release_date, title FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        $movie_data = $stmt->fetch();
        
        if ($movie_data && $movie_data['release_date']) {
            $cutoff = date('Y-m-d', strtotime($movie_data['release_date'] . ' +3 months'));
            if ($show_date > $cutoff) {
                $errors[] = 'Cannot schedule a screening more than 3 months after the release date. Release date: ' . $movie_data['release_date'] . ' | Cutoff: ' . $cutoff;
            }
            if ($show_date < $movie_data['release_date']) {
                $errors[] = 'Screening date cannot be before the movie release date (' . $movie_data['release_date'] . ')';
            }
        }
    }
    
    if (empty($errors)) {
        // Check for duplicate screening using prepared statements
        if ($screening_id) {
            $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?");
            $check->execute([$cinema_id, $screen_number, $show_date, $show_time, $screening_id]);
        } else {
            $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ?");
            $check->execute([$cinema_id, $screen_number, $show_date, $show_time]);
        }
        
        if ($check->fetch()) {
            $errors[] = 'A screening already exists for this cinema, screen, date and time';
        }
        
        // Check max 5 screenings per day per screen
        if (!$screening_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ?");
            $stmt->execute([$cinema_id, $screen_number, $show_date]);
            $count = $stmt->fetchColumn();
            
            if ($count >= 5) {
                $errors[] = 'Maximum 5 screenings allowed per screen per day';
            }
        }
    }
    
    if (empty($errors)) {
        $screening_datetime = strtotime("$show_date $show_time");
        $status = ($screening_datetime < time()) ? 'expired' : 'scheduled';
        
        try {
            if ($screening_id) {
                $sql = "UPDATE screenings SET movie_id = ?, cinema_id = ?, screen_number = ?, show_date = ?, show_time = ?, price = ?, available_seats = ?, status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats, $status, $screening_id]);
                
                if ($result) {
                    setFlash('Screening updated successfully', 'success');
                } else {
                    setFlash('Failed to update screening', 'error');
                }
            } else {
                $sql = "INSERT INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats, $status]);
                
                if ($result) {
                    $newId = $pdo->lastInsertId();
                    setFlash("Screening added successfully", 'success');
                } else {
                    setFlash('Failed to add screening', 'error');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
    } else {
        setFlash(implode('<br>', $errors), 'error');
    }
    
    header('Location: screenings.php');
    exit;
}

// Get data for dropdowns with release_date
$movies = $pdo->query("SELECT id, title, price, release_date FROM movies ORDER BY title")->fetchAll();
$cinemas = $pdo->query("SELECT id, name, total_screens, seats_per_screen FROM cinemas ORDER BY name")->fetchAll();

// Get all screenings - USING PREPARED STATEMENTS (NO CONCATENATION)
$sql = "
    SELECT s.*, m.title as movie_title, m.price as movie_price, m.release_date, c.name as cinema_name, c.seats_per_screen,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    ORDER BY c.name, s.show_date DESC, s.show_time DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$screenings_by_cinema_raw = $stmt->fetchAll();

$grouped_screenings = [];
foreach ($screenings_by_cinema_raw as $s) {
    $grouped_screenings[$s['cinema_name']][] = $s;
}

$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}

$show_form = (isset($_GET['action']) && $_GET['action'] == 'add') || isset($_GET['edit']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Same styles as staff version - keeping consistent */
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
        
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
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
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .logo:hover { text-shadow: var(--red-glow); }
        .logo::before { content: "🎬"; margin-right: 8px; font-size: 1.2rem; filter: drop-shadow(0 0 5px var(--red)); }
        
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
            white-space: nowrap;
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
        .nav-links a:hover { color: var(--red); }
        .nav-links a:hover::after { width: 60%; }
        .nav-links a.active { color: var(--red); }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
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
        
        .search-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .search-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .search-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .search-group select,
        .search-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .search-group select:focus,
        .search-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .btn-search {
            padding: 14px 28px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }
        
        .btn-clear {
            padding: 14px 28px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .results-summary {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .form-wrapper {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            padding-bottom: 15px;
            position: relative;
        }
        
        .form-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--red);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-group input:read-only {
            background: rgba(10, 10, 10, 0.3);
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-text {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 5px;
            padding-left: 15px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
            border-radius: 40px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 14px 30px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .cinema-dropdown-section {
            margin: 40px 0 30px;
        }
        
        .cinema-selector {
            background: var(--card-gradient);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .cinema-selector select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            cursor: pointer;
        }
        
        .screening-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .screening-card {
            background: var(--card-gradient);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .screening-card:hover {
            border-color: var(--red);
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.15);
        }
        
        .screening-movie {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 10px;
        }
        
        .screening-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.1);
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-item {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .detail-item strong {
            color: var(--red);
        }
        
        .screening-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-scheduled {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .status-expired {
            background: rgba(136, 136, 136, 0.15);
            color: #888;
            border: 1px solid #888;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 15px;
            font-size: 0.75rem;
            text-decoration: none;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 30px;
            color: var(--text-primary);
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.3);
        }
        
        .btn-action:hover {
            border-color: var(--red);
            color: var(--red);
        }
        
        .btn-action.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-action.delete:hover {
            background: #ff4444;
            color: #fff;
        }
        
        .add-button-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .add-button {
            padding: 12px 24px;
            background: var(--red);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-button:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        .no-screenings {
            text-align: center;
            padding: 60px 40px;
            background: var(--card-gradient);
            border-radius: 24px;
            color: var(--text-secondary);
        }
        
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-error {
            border-left: 4px solid #ff4444;
            color: #ff6b6b;
        }
        
        .alert-success {
            border-left: 4px solid #44ff44;
            color: #44ff44;
        }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            h1 { font-size: 2rem; }
            .form-row { grid-template-columns: 1fr; }
            .form-container { padding: 25px; margin: 20px; }
            .button-group { flex-direction: column; }
            .screening-details { grid-template-columns: 1fr; }
            .search-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET ADMIN</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php" class="active">Screenings</a>
                <a href="online_schedule.php">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Manage Screenings</h1>
        
        <div class="add-button-container">
            <a href="?action=add" class="add-button">➕ Add New Screening</a>
        </div>
        
        <div class="cinema-strip"></div>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_form): ?>
            <div class="form-wrapper">
                <div class="form-container">
                    <h2><?php echo $edit_screening ? '✏️ Edit Screening' : '➕ Add New Screening'; ?></h2>
                    
                    <form method="POST" action="">
                        <?php if ($edit_screening): ?>
                            <input type="hidden" name="screening_id" value="<?php echo $edit_screening['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>🎬 Select Movie</label>
                                <select name="movie_id" id="movieSelect" required>
                                    <option value="">-- Select a movie --</option>
                                    <?php foreach ($movies as $movie): ?>
                                        <option value="<?php echo $movie['id']; ?>" 
                                            data-price="<?php echo $movie['price']; ?>"
                                            data-release="<?php echo $movie['release_date']; ?>"
                                            <?php echo (($edit_screening['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($movie['title']); ?> (₱<?php echo number_format($movie['price'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>🏛️ Cinema</label>
                                <select name="cinema_id" id="cinemaSelect" required onchange="updateScreenOptions()">
                                    <option value="">-- Select a cinema --</option>
                                    <?php foreach ($cinemas as $cinema): ?>
                                        <option value="<?php echo $cinema['id']; ?>" 
                                            data-screens="<?php echo $cinema['total_screens']; ?>"
                                            data-seats="<?php echo $cinema['seats_per_screen'] ?? 40; ?>"
                                            <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cinema['name']); ?> (<?php echo $cinema['total_screens']; ?> screens, <?php echo $cinema['seats_per_screen'] ?? 40; ?> seats/screen)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📺 Screen Number</label>
                                <select name="screen_number" id="screenSelect" required>
                                    <option value="">-- Select screen --</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>💰 Price (₱)</label>
                                <input type="number" name="price" id="priceInput" step="0.01" 
                                       value="<?php echo $edit_screening['price'] ?? ''; ?>" 
                                       required placeholder="Auto-fills from movie">
                                <small class="form-text">Auto-fills when you select a movie</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Show Date</label>
                                <input type="date" name="show_date" id="showDate" value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" required>
                                <small class="form-text" id="releaseWarning" style="color: #ff8844;"></small>
                            </div>
                            
                            <div class="form-group">
                                <label>⏰ Show Time</label>
                                <input type="time" name="show_time" value="<?php echo $edit_screening['show_time'] ?? '19:00'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>🪑 Available Seats</label>
                            <input type="number" name="available_seats" id="availableSeats" value="<?php echo $edit_screening['available_seats'] ?? '40'; ?>" min="1" max="500" required readonly>
                            <small class="form-text">Auto-filled from cinema's seats per screen. Maximum 5 screenings per screen per day.</small>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn-primary">
                                <?php echo $edit_screening ? '💾 Update Screening' : '✨ Create Screening'; ?>
                            </button>
                            <a href="screenings.php" class="btn-secondary">❌ Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Cinema Selection -->
        <div class="cinema-dropdown-section">
            <div class="cinema-selector">
                <select id="cinemaFilter" onchange="filterScreenings()">
                    <option value="">-- Select a cinema --</option>
                    <?php foreach ($cinemas as $cinema): ?>
                        <option value="<?php echo htmlspecialchars($cinema['name']); ?>">
                            <?php echo htmlspecialchars($cinema['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="search-group" style="margin-top: 15px;">
                    <label>🔍 Search by Movie Name</label>
                    <input type="text" id="movieSearchInput" placeholder="Type movie name..." autocomplete="off">
                </div>
                <div style="margin-top: 15px;">
                    <button class="btn-search" onclick="applyMovieSearch()">🔍 Search</button>
                    <button class="btn-clear" onclick="clearSearch()">Clear</button>
                </div>
                <div class="results-summary" id="resultsSummary"></div>
            </div>
        </div>
        
        <div id="screeningsContainer" class="screening-cards">
            <div class="no-screenings">
                🎬 Select a cinema from the dropdown above to view its screenings
            </div>
        </div>
    </main>
    
    <script>
        const allScreenings = <?php echo json_encode($grouped_screenings); ?>;
        let currentCinema = '';
        let currentSearchTerm = '';
        
        function filterScreenings() {
            currentCinema = document.getElementById('cinemaFilter').value;
            currentSearchTerm = document.getElementById('movieSearchInput').value;
            renderScreenings();
        }
        
        function applyMovieSearch() {
            currentSearchTerm = document.getElementById('movieSearchInput').value;
            renderScreenings();
        }
        
        function clearSearch() {
            document.getElementById('movieSearchInput').value = '';
            currentSearchTerm = '';
            renderScreenings();
        }
        
        function renderScreenings() {
            const container = document.getElementById('screeningsContainer');
            const resultsSummary = document.getElementById('resultsSummary');
            
            if (!currentCinema) {
                container.innerHTML = '<div class="no-screenings">🎬 Select a cinema from the dropdown above to view its screenings</div>';
                resultsSummary.innerHTML = '';
                return;
            }
            
            let screenings = allScreenings[currentCinema] || [];
            
            if (currentSearchTerm) {
                screenings = screenings.filter(s => 
                    s.movie_title.toLowerCase().includes(currentSearchTerm.toLowerCase())
                );
            }
            
            if (screenings.length === 0) {
                if (currentSearchTerm) {
                    container.innerHTML = '<div class="no-screenings">📭 No screenings found for "' + escapeHtml(currentSearchTerm) + '" in this cinema</div>';
                    resultsSummary.innerHTML = 'No results found for "' + escapeHtml(currentSearchTerm) + '"';
                } else {
                    container.innerHTML = '<div class="no-screenings">📭 No screenings found for this cinema</div>';
                    resultsSummary.innerHTML = '';
                }
                return;
            }
            
            if (currentSearchTerm) {
                resultsSummary.innerHTML = `Found ${screenings.length} screening(s) matching "${escapeHtml(currentSearchTerm)}" in ${escapeHtml(currentCinema)}`;
            } else {
                resultsSummary.innerHTML = `Showing all ${screenings.length} screening(s) for ${escapeHtml(currentCinema)}`;
            }
            
            let html = '';
            for (const s of screenings) {
                const now = new Date();
                const showDateTime = new Date(s.show_date + 'T' + s.show_time);
                const isExpired = showDateTime < now || s.status === 'expired';
                const sold = s.tickets_sold || 0;
                const available = s.available_seats;
                const percentBooked = (sold / available) * 100;
                
                let movieTitle = escapeHtml(s.movie_title);
                if (currentSearchTerm) {
                    const regex = new RegExp(`(${escapeRegex(currentSearchTerm)})`, 'gi');
                    movieTitle = movieTitle.replace(regex, '<mark style="background: var(--red); color: white; padding: 0 4px; border-radius: 4px;">$1</mark>');
                }
                
                html += `
                    <div class="screening-card">
                        <div class="screening-movie">🎬 ${movieTitle}</div>
                        <div class="screening-details">
                            <div class="detail-item">📅 <strong>Date:</strong> ${new Date(s.show_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                            <div class="detail-item">⏰ <strong>Time:</strong> ${new Date(showDateTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</div>
                            <div class="detail-item">📺 <strong>Screen:</strong> ${s.screen_number}</div>
                            <div class="detail-item">💰 <strong>Price:</strong> ₱${parseFloat(s.price).toFixed(2)}</div>
                            <div class="detail-item">🪑 <strong>Seats:</strong> ${sold}/${available} sold (${Math.round(percentBooked)}% full)</div>
                            <div class="detail-item">📊 <strong>Status:</strong> <span class="screening-status ${isExpired ? 'status-expired' : 'status-scheduled'}">${isExpired ? 'Expired' : 'Scheduled'}</span></div>
                        </div>
                        <div class="action-buttons">
                            ${!isExpired ? `<a href="?edit=${s.id}" class="btn-action">✏️ Edit</a>` : ''}
                            <a href="?delete=${s.id}" class="btn-action delete" onclick="return confirm('Delete this screening?')">🗑️ Delete</a>
                            <a href="tickets.php?screening_id=${s.id}" class="btn-action" style="border-color:#44ff44; color:#44ff44;">🎟️ Tickets</a>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        document.getElementById('movieSearchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyMovieSearch();
        });
        
        const movieSelect = document.getElementById('movieSelect');
        const priceInput = document.getElementById('priceInput');
        const showDateInput = document.getElementById('showDate');
        const releaseWarning = document.getElementById('releaseWarning');
        
        function updatePriceFromMovie() {
            if (movieSelect && priceInput && movieSelect.value) {
                priceInput.value = parseFloat(movieSelect.options[movieSelect.selectedIndex].getAttribute('data-price')).toFixed(2);
            }
        }
        
        function checkReleaseDate() {
            if (movieSelect && movieSelect.value && showDateInput && showDateInput.value) {
                const releaseDate = movieSelect.options[movieSelect.selectedIndex].getAttribute('data-release');
                const showDate = showDateInput.value;
                
                if (releaseDate && showDate) {
                    if (showDate < releaseDate) {
                        releaseWarning.innerHTML = '⚠️ Warning: Screening date is BEFORE movie release date!';
                        releaseWarning.style.color = '#ff4444';
                    } else {
                        const cutoffDate = new Date(releaseDate);
                        cutoffDate.setMonth(cutoffDate.getMonth() + 3);
                        const cutoffDateStr = cutoffDate.toISOString().split('T')[0];
                        
                        if (showDate > cutoffDateStr) {
                            releaseWarning.innerHTML = `⚠️ Warning: Screening date is more than 3 months after release date (${releaseDate}). Cutoff: ${cutoffDateStr}`;
                            releaseWarning.style.color = '#ff8844';
                        } else {
                            releaseWarning.innerHTML = '';
                        }
                    }
                }
            }
        }
        
        if (movieSelect) {
            movieSelect.addEventListener('change', function() {
                updatePriceFromMovie();
                checkReleaseDate();
            });
            if (movieSelect.value) {
                updatePriceFromMovie();
                checkReleaseDate();
            }
        }
        
        if (showDateInput) {
            showDateInput.addEventListener('change', checkReleaseDate);
        }
        
        function updateScreenOptions() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            const screenSelect = document.getElementById('screenSelect');
            const availableSeatsInput = document.getElementById('availableSeats');
            
            if (cinemaSelect && screenSelect && cinemaSelect.value) {
                const selected = cinemaSelect.options[cinemaSelect.selectedIndex];
                if (selected && selected.dataset.screens) {
                    const totalScreens = parseInt(selected.dataset.screens);
                    const seatsPerScreen = parseInt(selected.dataset.seats);
                    
                    if (availableSeatsInput && seatsPerScreen) {
                        availableSeatsInput.value = seatsPerScreen;
                    }
                    
                    let options = '<option value="">-- Select screen --</option>';
                    for (let i = 1; i <= totalScreens; i++) {
                        options += `<option value="${i}">Screen ${i}</option>`;
                    }
                    screenSelect.innerHTML = options;
                    
                    <?php if ($edit_screening): ?>
                    if (<?php echo json_encode($edit_screening['screen_number']); ?>) {
                        screenSelect.value = <?php echo json_encode($edit_screening['screen_number']); ?>;
                    }
                    <?php endif; ?>
                }
            }
        }
        
        if (document.getElementById('cinemaSelect') && document.getElementById('cinemaSelect').value) {
            updateScreenOptions();
        }
        
        <?php if ($edit_screening): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            if (cinemaSelect) {
                cinemaSelect.value = '<?php echo $edit_screening['cinema_id']; ?>';
                updateScreenOptions();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>