<?php
// user/purchase.php - FIXED: Uses profile_type from session, no parent_id
// FIXED: Added generateSeatMap function, fixed undefined variables
// Removed quantity dropdown (1 ticket per person)
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Get profile_type from session (NOT from user account_type)
$profile_type = $_SESSION['profile_type'] ?? 'adult';

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';

// ============ CHECK PROFILE TYPE FOR PURCHASE RESTRICTIONS ============
// Kid profiles cannot purchase tickets
if ($profile_type == 'kid') {
    setFlash('Kid profiles cannot purchase tickets directly. Please ask a parent or guardian to buy tickets for you.', 'error');
    header('Location: movies.php');
    exit;
}

$screening_id = isset($_GET['screening_id']) ? intval($_GET['screening_id']) : 0;
$movie_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Use constants from functions.php
$processing_fee_cinema = TICKET_FEE; // ₱50
$processing_fee_online = TICKET_FEE; // ₱50

// ========== CHECK IF USER ALREADY BOUGHT A TICKET ==========
function hasUserAlreadyPurchased($pdo, $user_id, $screening_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets 
        WHERE user_id = ? AND screening_id = ? AND status IN ('paid', 'pending')
    ");
    $stmt->execute([$user_id, $screening_id]);
    return $stmt->fetchColumn() > 0;
}

// Initialize variables
$ticket_type = '';
$base_price = 0;
$item_name = '';
$cinema_name = '';
$location = '';
$screen_number = '';
$show_date = '';
$show_time = '';
$duration = '';
$rating = '';
$poster = '';
$available_seats = 0;
$seats = []; // Initialize seats array
$online_schedules = []; // Initialize online_schedules array

if ($screening_id) {
    // Get screening details for physical ticket
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, m.description, m.poster, m.duration, m.rating, m.genre,
               c.name as cinema_name, c.location, c.seats_per_screen
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        JOIN cinemas c ON s.cinema_id = c.id
        WHERE s.id = ? AND s.show_date >= CURDATE()
    ");
    $stmt->execute([$screening_id]);
    $screening = $stmt->fetch();
    
    if (!$screening) {
        setFlash('Screening not found or expired', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Check if user already purchased a ticket for this screening
    if (hasUserAlreadyPurchased($pdo, $user['id'], $screening_id)) {
        setFlash('You have already purchased a ticket for this screening. One ticket per person only.', 'error');
        header('Location: movies.php');
        exit;
    }
    
    $ticket_type = 'physical';
    $base_price = $screening['price'];
    $item_name = $screening['title'];
    $cinema_name = $screening['cinema_name'];
    $location = $screening['location'];
    $screen_number = $screening['screen_number'];
    $show_date = $screening['show_date'];
    $show_time = $screening['show_time'];
    $duration = $screening['duration'];
    $rating = $screening['rating'];
    $poster = $screening['poster'];
    $available_seats = $screening['available_seats'];
    
    // Generate seat map using the function from functions.php
    $seats = generateSeatMap($screening_id);
    
} elseif ($movie_id && $type == 'online') {
    // Get movie details for online ticket
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movie_id]);
    $movie = $stmt->fetch();
    
    if (!$movie) {
        setFlash('Movie not found', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Check age restriction based on PROFILE_TYPE
    if ($profile_type == 'teen' && !in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
        setFlash('This movie is not available for your age group', 'error');
        header('Location: movies.php');
        exit;
    }
    if ($profile_type == 'kid' && !in_array($movie['rating'], ['G', 'PG'])) {
        setFlash('This movie is not available for your age group', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Get available online schedules
    $stmt = $pdo->prepare("
        SELECT * FROM online_schedule 
        WHERE movie_id = ? 
        AND show_date >= CURDATE() 
        AND status = 'scheduled'
        AND current_viewers < max_viewers
        ORDER BY show_date, show_time
    ");
    $stmt->execute([$movie_id]);
    $online_schedules = $stmt->fetchAll();
    
    if (empty($online_schedules)) {
        setFlash('No online streaming schedules available for this movie', 'error');
        header('Location: movie_detail.php?id=' . $movie_id);
        exit;
    }
    
    $ticket_type = 'online';
    $item_name = $movie['title'];
    $poster = $movie['poster'];
    $duration = $movie['duration'];
    $rating = $movie['rating'];
    $genre = $movie['genre'];
    
} else {
    setFlash('Invalid request', 'error');
    header('Location: movies.php');
    exit;
}

// Handle form submission for physical tickets
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $ticket_type == 'physical') {
    $quantity = 1;
    $selected_seats = isset($_POST['seats']) ? explode(',', $_POST['seats']) : [];
    $for_user_id = $_POST['for_user_id'] ?? $user['id'];
    
    $subtotal = $base_price * $quantity;
    $total_fee = $processing_fee_cinema * $quantity;
    $total_price = $subtotal + $total_fee;
    
    $errors = [];
    
    if ($available_seats < $quantity) {
        $errors[] = 'Not enough seats available!';
    }
    
    if (empty($selected_seats)) {
        $errors[] = 'Please select your seat';
    } elseif (count($selected_seats) != $quantity) {
        $errors[] = 'Please select exactly 1 seat';
    } else {
        $stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('paid', 'pending')");
        $stmt->execute([$screening_id]);
        $booked_seats = [];
        while ($row = $stmt->fetch()) {
            if ($row['seat_numbers']) {
                $booked_seats = array_merge($booked_seats, explode(',', $row['seat_numbers']));
            }
        }
        
        $conflicts = array_intersect($selected_seats, $booked_seats);
        if (!empty($conflicts)) {
            $errors[] = 'This seat is no longer available: ' . implode(', ', $conflicts);
        }
    }
    
    if (empty($errors)) {
        $seats_param = implode(',', $selected_seats);
        header("Location: payment.php?type=cinema&id={$screening_id}&quantity=1&seats={$seats_param}" . 
            ($for_user_id != $user['id'] ? "&for_user_id={$for_user_id}" : ""));
        exit;
    } else {
        foreach ($errors as $error) {
            setFlash($error, 'error');
        }
    }
}

// Get user's linked child accounts (for adults) - feature disabled
$linked_accounts = [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Tickets - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
            --glass-bg: rgba(26, 26, 26, 0.7);
            --glass-border: rgba(255, 255, 255, 0.05);
            --success-color: #44ff44;
            --warning-color: #ffff44;
        }

        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e0e0e0;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.2);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(240, 240, 240, 0.95) 100%);
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(229, 9, 20, 0.1);
            --success-color: #00aa00;
            --warning-color: #cc8800;
        }

        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --bg-tertiary: #2a2a4a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255, 0, 255, 0.5);
            --border-color: rgba(255, 0, 255, 0.3);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 58, 0.9) 0%, rgba(20, 20, 50, 0.95) 100%);
            --glass-bg: rgba(26, 26, 58, 0.7);
            --glass-border: rgba(255, 0, 255, 0.2);
            --success-color: #00ffff;
            --warning-color: #ffff00;
        }

        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --bg-tertiary: #0f2a0f;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0, 255, 0, 0.5);
            --border-color: rgba(0, 255, 0, 0.3);
            --card-bg: linear-gradient(135deg, rgba(10, 26, 10, 0.9) 0%, rgba(5, 20, 5, 0.95) 100%);
            --glass-bg: rgba(10, 26, 10, 0.7);
            --glass-border: rgba(0, 255, 0, 0.2);
            --success-color: #00ff00;
            --warning-color: #ffff00;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, var(--accent) 0%, transparent 50%);
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
            white-space: nowrap;
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
            background: var(--accent);
            transition: width 0.3s;
        }
        .nav-links a:hover { color: var(--accent); }
        .nav-links a:hover::after { width: 60%; }
        .nav-links a.active { color: var(--accent); }
        
        .profile-switch {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid #e50914;
            border-radius: 40px;
            padding: 6px 15px !important;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .profile-switch:hover { background: #e50914; color: white !important; }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        .purchase-container { max-width: 1200px; margin: 0 auto; }
        
        .movie-summary {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .movie-summary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .summary-poster {
            width: 120px;
            height: 170px;
            object-fit: cover;
            border: 2px solid var(--border-color);
            border-radius: 12px;
        }
        
        .summary-details h1 {
            color: var(--accent);
            font-size: 2rem;
            margin-bottom: 15px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .meta-info {
            display: flex;
            gap: 20px;
            color: var(--text-secondary);
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .rating-badge {
            padding: 3px 10px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .rating-G { background: rgba(68,255,68,0.15); border: 1px solid var(--success-color); color: var(--success-color); }
        .rating-PG { background: rgba(255,255,68,0.15); border: 1px solid #ffff44; color: #ffff44; }
        .rating-PG-13 { background: rgba(255,136,68,0.15); border: 1px solid #ff8844; color: #ff8844; }
        .rating-R { background: rgba(229,9,20,0.15); border: 1px solid var(--accent); color: var(--accent); }
        
        .location-info { color: var(--text-secondary); line-height: 1.8; }
        .location-info strong { color: var(--accent); }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .schedule-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .schedule-card::before {
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
        
        .schedule-card:hover { transform: translateY(-5px); border-color: var(--accent); box-shadow: 0 20px 40px var(--accent-glow); }
        .schedule-card.selected { background: rgba(var(--accent), 0.15); border: 2px solid var(--accent); }
        
        .schedule-time { font-size: 1.3rem; color: var(--accent); font-weight: 700; margin-bottom: 10px; }
        .schedule-date { color: var(--text-secondary); margin-bottom: 10px; }
        .availability { color: var(--success-color); margin: 10px 0; font-weight: 600; }
        .availability.warning { color: var(--warning-color); }
        .schedule-price { font-size: 1.2rem; color: var(--accent); font-weight: 700; margin: 15px 0; }
        
        .online-benefits {
            background: rgba(var(--accent), 0.05);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        .online-benefits h3 { color: var(--accent); margin-bottom: 15px; }
        .online-benefits ul { list-style: none; padding: 0; }
        .online-benefits li { color: var(--text-secondary); margin: 10px 0; padding-left: 25px; position: relative; }
        .online-benefits li:before { content: "✓"; color: var(--accent); position: absolute; left: 0; font-weight: 700; }
        
        .seat-selection {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .seat-selection h2 { color: var(--accent); margin-bottom: 20px; }
        
        .screen {
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            height: 5px;
            width: 80%;
            margin: 0 auto 50px;
            text-align: center;
            padding-top: 15px;
            color: var(--text-secondary);
        }
        
        .seat-map {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 10px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .seat {
            aspect-ratio: 1;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--text-secondary);
        }
        
        .seat.available:hover { border-color: var(--accent); color: var(--accent); transform: scale(1.1); box-shadow: 0 0 20px var(--accent-glow); }
        .seat.selected { background: var(--accent); border-color: var(--accent); color: var(--bg-primary); transform: scale(1.05); }
        .seat.booked { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2); cursor: not-allowed; text-decoration: line-through; }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0 30px;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
        .legend-box { width: 20px; height: 20px; border: 2px solid; border-radius: 4px; }
        .legend-box.available { border-color: var(--border-color); }
        .legend-box.selected { background: var(--accent); border-color: var(--accent); }
        .legend-box.booked { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        
        .selected-info {
            margin: 20px 0;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 40px;
            color: var(--accent);
            text-align: center;
            font-weight: 600;
        }
        .selected-info span { color: var(--text-primary); }
        
        .purchase-form {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .purchase-form h2 { color: var(--accent); margin-bottom: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            color: var(--accent);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
        }
        .form-group select:focus, .form-group input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 20px var(--accent-glow); }
        
        .price-breakdown {
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        .price-row { display: flex; justify-content: space-between; margin: 10px 0; color: var(--text-secondary); }
        .price-row.total { margin-top: 15px; padding-top: 15px; border-top: 2px solid var(--accent); color: var(--accent); font-size: 1.3rem; font-weight: 700; }
        .price-row span:last-child { color: var(--accent); font-weight: 600; }
        
        .proceed-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            overflow: hidden;
        }
        .proceed-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .proceed-btn:hover:not(:disabled) { background: var(--accent-dark); transform: translateY(-3px); box-shadow: 0 10px 30px var(--accent-glow); }
        .proceed-btn:hover:not(:disabled)::before { left: 100%; }
        .proceed-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .cinema-strip { height: 2px; background: linear-gradient(90deg, transparent, var(--accent), transparent); margin: 20px 0; opacity: 0.3; }
        
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent);
            color: var(--text-primary);
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @media (max-width: 1200px) { .nav-links a { padding: 5px 8px; font-size: 0.7rem; } }
        @media (max-width: 1024px) { .nav-container { padding: 0 15px; } }
        @media (max-width: 768px) {
            .nav-container { flex-direction: column; gap: 10px; }
            .nav-links { justify-content: center; }
            .movie-summary { flex-direction: column; align-items: center; text-align: center; }
            .meta-info { justify-content: center; }
            .seat-map { grid-template-columns: repeat(4, 1fr); }
            .legend { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="movies.php" class="active">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <div class="profile-badge">
                    <span>👤</span>
                    <span class="profile-name"><?php echo htmlspecialchars($_SESSION['profile_name'] ?? 'Profile'); ?></span>
                    <a href="select_profile.php" class="profile-switch">Switch</a>
                </div>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container purchase-container">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        
        <!-- Movie Summary -->
        <div class="movie-summary">
            <?php if (!empty($poster)): ?>
                <img src="../uploads/posters/<?php echo htmlspecialchars($poster); ?>" class="summary-poster" onerror="this.src='../uploads/posters/default.jpg'">
            <?php else: ?>
                <div style="width:120px; height:170px; background:var(--bg-tertiary); border:2px solid var(--border-color); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">No Poster</div>
            <?php endif; ?>
            
            <div class="summary-details">
                <h1><?php echo htmlspecialchars($item_name); ?></h1>
                
                <div class="meta-info">
                    <?php if (!empty($rating)): ?>
                    <span class="rating-badge rating-<?php echo str_replace('-', '', $rating); ?>"><?php echo htmlspecialchars($rating); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($duration)): ?>
                    <span>⏱️ <?php echo htmlspecialchars($duration); ?> min</span>
                    <?php endif; ?>
                    <?php if (isset($genre) && !empty($genre)): ?>
                    <span>🎭 <?php echo htmlspecialchars($genre); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($ticket_type == 'physical'): ?>
                    <div class="location-info">
                        <strong><?php echo htmlspecialchars($cinema_name); ?></strong><br>
                        📍 <?php echo htmlspecialchars($location); ?><br>
                        🎬 Screen <?php echo htmlspecialchars($screen_number); ?><br>
                        📅 <?php echo date('F d, Y', strtotime($show_date)); ?> at <?php echo date('h:i A', strtotime($show_time)); ?>
                    </div>
                <?php else: ?>
                    <div class="location-info">
                        <strong>Online Streaming</strong><br>Watch anywhere, anytime
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="cinema-strip"></div>
        
        <?php if ($ticket_type == 'online'): ?>
            <!-- Online Schedule Selection -->
            <div class="purchase-form">
                <h2>Select Streaming Time</h2>
                
                <div class="online-benefits">
                    <h3>🎥 Online Streaming Benefits</h3>
                    <ul>
                        <li>Watch on any device (phone, tablet, computer)</li>
                        <li>Valid for 7 days after purchase</li>
                        <li>HD streaming quality</li>
                        <li>Pause and resume anytime</li>
                    </ul>
                </div>
                
                <div class="schedule-grid" id="scheduleGrid">
                    <?php foreach ($online_schedules as $schedule): 
                        $available = $schedule['max_viewers'] - $schedule['current_viewers'];
                        $status_class = $available <= 5 ? 'warning' : '';
                    ?>
                        <div class="schedule-card" onclick="selectSchedule(<?php echo $schedule['id']; ?>, <?php echo $schedule['price']; ?>, this)">
                            <div class="schedule-time">🕐 <?php echo date('h:i A', strtotime($schedule['show_time'])); ?></div>
                            <div class="schedule-date"><?php echo date('F d, Y', strtotime($schedule['show_date'])); ?></div>
                            <div class="availability <?php echo $status_class; ?>">👥 <?php echo $available; ?> spots available</div>
                            <div class="schedule-price">₱<?php echo number_format($schedule['price'], 2); ?> per ticket</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form method="GET" action="payment.php" id="onlineForm" style="margin-top: 30px;">
                    <input type="hidden" name="type" value="online">
                    <input type="hidden" name="id" id="selectedScheduleId">
                    <input type="hidden" name="quantity" value="1">
                    
                    <div class="price-breakdown" id="priceBreakdown" style="display: none;">
                        <div class="price-row"><span>Price per ticket:</span><span id="perTicketPrice">₱0.00</span></div>
                        <div class="price-row"><span>Service Fee:</span><span>₱<?php echo number_format($processing_fee_online, 2); ?></span></div>
                        <div class="price-row total"><span>Total:</span><span id="total">₱0.00</span></div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>Proceed to Payment</button>
                </form>
            </div>
            
            <script>
                let selectedSchedulePrice = 0;
                const processingFee = <?php echo $processing_fee_online; ?>;
                
                function selectSchedule(scheduleId, price, element) {
                    document.querySelectorAll('.schedule-card').forEach(card => card.classList.remove('selected'));
                    element.classList.add('selected');
                    selectedSchedulePrice = price;
                    document.getElementById('selectedScheduleId').value = scheduleId;
                    document.getElementById('priceBreakdown').style.display = 'block';
                    document.getElementById('perTicketPrice').textContent = '₱' + price.toFixed(2);
                    const total = price + processingFee;
                    document.getElementById('total').textContent = '₱' + total.toFixed(2);
                    document.getElementById('proceedBtn').disabled = false;
                }
                
                document.getElementById('onlineForm').addEventListener('submit', function(e) {
                    if (!document.getElementById('selectedScheduleId').value) {
                        e.preventDefault();
                        alert('Please select a streaming time');
                    }
                });
            </script>
            
        <?php else: ?>
            <!-- Physical Ticket Seat Selection -->
            <div class="seat-selection">
                <h2>Select Your Seats</h2>
                
                <div class="legend">
                    <div class="legend-item"><div class="legend-box available"></div><span>Available</span></div>
                    <div class="legend-item"><div class="legend-box selected"></div><span>Selected</span></div>
                    <div class="legend-item"><div class="legend-box booked"></div><span>Booked</span></div>
                </div>
                
                <div class="screen">SCREEN</div>
                
                <div class="seat-map" id="seatMap">
                    <?php foreach ($seats as $seat): ?>
                        <div class="seat <?php echo $seat['available'] ? 'available' : 'booked'; ?>" 
                             data-seat="<?php echo htmlspecialchars($seat['number']); ?>"
                             onclick="selectSeat(this)">
                            <?php echo htmlspecialchars($seat['number']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="selected-info" id="selectedInfo">
                    Selected Seat: <span id="selectedSeatsDisplay">None</span>
                </div>
            </div>
            
            <!-- Purchase Form for Physical Tickets -->
            <div class="purchase-form">
                <form method="POST" id="physicalForm">
                    <?php if (!empty($linked_accounts)): ?>
                        <div class="form-group">
                            <label>Purchase for:</label>
                            <select name="for_user_id" id="for_user_id">
                                <option value="<?php echo $user['id']; ?>">Myself (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)</option>
                                <?php foreach ($linked_accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?> 
                                        (<?php echo ucfirst($account['account_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="seats" id="selectedSeatsInput">
                    
                    <div class="price-breakdown">
                        <div class="price-row"><span>Price per ticket:</span><span>₱<?php echo number_format($base_price, 2); ?></span></div>
                        <div class="price-row"><span>Service Fee:</span><span>₱<?php echo number_format($processing_fee_cinema, 2); ?></span></div>
                        <div class="price-row total"><span>Total:</span><span>₱<?php echo number_format($base_price + $processing_fee_cinema, 2); ?></span></div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>Proceed to Payment</button>
                </form>
            </div>
            
            <script>
                let selectedSeats = [];
                
                function selectSeat(seatElement) {
                    if (seatElement.classList.contains('booked')) return;
                    const seatNumber = seatElement.dataset.seat;
                    
                    if (seatElement.classList.contains('selected')) {
                        seatElement.classList.remove('selected');
                        selectedSeats = selectedSeats.filter(s => s !== seatNumber);
                    } else {
                        if (selectedSeats.length < 1) {
                            seatElement.classList.add('selected');
                            selectedSeats.push(seatNumber);
                        } else {
                            alert('You can only select 1 seat');
                        }
                    }
                    updateSelectedSeats();
                    updateProceedButton();
                }
                
                function updateSelectedSeats() {
                    const display = document.getElementById('selectedSeatsDisplay');
                    const input = document.getElementById('selectedSeatsInput');
                    if (selectedSeats.length > 0) {
                        display.textContent = selectedSeats.join(', ');
                        input.value = selectedSeats.join(',');
                    } else {
                        display.textContent = 'None';
                        input.value = '';
                    }
                }
                
                function updateProceedButton() {
                    document.getElementById('proceedBtn').disabled = selectedSeats.length !== 1;
                }
                
                document.getElementById('physicalForm').addEventListener('submit', function(e) {
                    if (selectedSeats.length !== 1) {
                        e.preventDefault();
                        alert('Please select 1 seat');
                    }
                });
            </script>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>