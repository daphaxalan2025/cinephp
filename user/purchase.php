<?php
// user/purchase.php - COMPLETE FIXED VERSION
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Check if user is kid (cannot purchase)
if ($user['account_type'] == 'kid') {
    setFlash('Kid accounts cannot purchase tickets. Please ask a parent/guardian.', 'error');
    header('Location: movies.php');
    exit;
}

$screening_id = isset($_GET['screening_id']) ? intval($_GET['screening_id']) : 0;
$movie_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

$processing_fee = 3.00; // ₱150 = ~$3.00

if ($screening_id) {
    // Get screening details for physical ticket
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, m.description, m.poster, m.duration, m.rating, m.genre,
               c.name as cinema_name, c.location
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
    
    // Generate seat map
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
    
    // Check age restriction
    if ($user['account_type'] == 'teen' && !in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
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
    $quantity = intval($_POST['quantity'] ?? 1);
    $selected_seats = isset($_POST['seats']) ? explode(',', $_POST['seats']) : [];
    $for_user_id = $_POST['for_user_id'] ?? $user['id'];
    
    // Calculate total with processing fee
    $subtotal = $base_price * $quantity;
    $total_fee = $processing_fee * $quantity;
    $total_price = $subtotal + $total_fee;
    
    // Validate
    $errors = [];
    
    if ($quantity < 1 || $quantity > 10) {
        $errors[] = 'Invalid quantity';
    }
    
    if ($available_seats < $quantity) {
        $errors[] = 'Not enough seats available! Only ' . $available_seats . ' left.';
    }
    
    if (empty($selected_seats)) {
        $errors[] = 'Please select your seats';
    } elseif (count($selected_seats) != $quantity) {
        $errors[] = 'Please select exactly ' . $quantity . ' seat(s)';
    } else {
        // Check if seats are still available
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
            $errors[] = 'Some seats are no longer available: ' . implode(', ', $conflicts);
        }
    }
    
    if (empty($errors)) {
        // Redirect to payment
        $seats_param = implode(',', $selected_seats);
        header("Location: payment.php?screening_id={$screening_id}&quantity={$quantity}&seats={$seats_param}" . 
               ($for_user_id != $user['id'] ? "&for_user_id={$for_user_id}" : ""));
        exit;
    }
}

// Get user's linked accounts (for adults)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, account_type FROM users WHERE parent_id = ?");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Tickets - CinemaTicket</title>
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
        
        .purchase-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Movie Summary */
        .movie-summary {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
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
            background: linear-gradient(90deg, transparent, var(--red), transparent);
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
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .summary-poster:hover {
            border-color: var(--red);
            transform: scale(1.05);
        }
        
        .summary-details h1 {
            color: var(--red);
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
        
        .rating-G {
            background: rgba(68, 255, 68, 0.15);
            border: 1px solid #44ff44;
            color: #44ff44;
        }
        
        .rating-PG {
            background: rgba(255, 255, 68, 0.15);
            border: 1px solid #ffff44;
            color: #ffff44;
        }
        
        .rating-PG-13 {
            background: rgba(255, 136, 68, 0.15);
            border: 1px solid #ff8844;
            color: #ff8844;
        }
        
        .rating-R {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            color: var(--red);
        }
        
        .location-info {
            color: var(--text-secondary);
            line-height: 1.8;
        }
        
        .location-info strong {
            color: var(--red);
        }
        
        /* Online Schedule */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .schedule-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
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
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            transform: translateX(-100%);
            animation: slideBorder 3s infinite;
        }
        
        .schedule-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .schedule-card.selected {
            background: rgba(229, 9, 20, 0.15);
            border: 2px solid var(--red);
        }
        
        .schedule-time {
            font-size: 1.3rem;
            color: var(--red);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .schedule-date {
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .availability {
            color: #44ff44;
            margin: 10px 0;
            font-weight: 600;
        }
        
        .availability.warning {
            color: #ffff44;
        }
        
        .schedule-price {
            font-size: 1.2rem;
            color: var(--red);
            font-weight: 700;
            margin: 15px 0;
        }
        
        /* Online Benefits */
        .online-benefits {
            background: rgba(229, 9, 20, 0.05);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .online-benefits h3 {
            color: var(--red);
            margin-bottom: 15px;
        }
        
        .online-benefits ul {
            list-style: none;
            padding: 0;
        }
        
        .online-benefits li {
            color: var(--text-secondary);
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .online-benefits li:before {
            content: "✓";
            color: var(--red);
            position: absolute;
            left: 0;
            font-weight: 700;
        }
        
        /* Seat Selection */
        .seat-selection {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .seat-selection h2 {
            color: var(--red);
            margin-bottom: 20px;
        }
        
        .screen {
            background: linear-gradient(90deg, transparent, var(--red), transparent);
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
            border: 2px solid rgba(255, 255, 255, 0.1);
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
        
        .seat.available:hover {
            border-color: var(--red);
            color: var(--red);
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
        }
        
        .seat.selected {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
            transform: scale(1.05);
        }
        
        .seat.booked {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.2);
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0 30px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border: 2px solid;
            border-radius: 4px;
        }
        
        .legend-box.available { border-color: rgba(255, 255, 255, 0.3); }
        .legend-box.selected { background: var(--red); border-color: var(--red); }
        .legend-box.booked { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.1); }
        
        /* Selected Info */
        .selected-info {
            margin: 20px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 40px;
            color: var(--red);
            text-align: center;
            font-weight: 600;
        }
        
        .selected-info span {
            color: #fff;
        }
        
        /* Purchase Form */
        .purchase-form {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .purchase-form h2 {
            color: var(--red);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: var(--red);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        /* Price Breakdown */
        .price-breakdown {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            color: var(--text-secondary);
        }
        
        .price-row.total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--red);
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .price-row span:last-child {
            color: var(--red);
            font-weight: 600;
        }
        
        /* Proceed Button */
        .proceed-btn {
            width: 100%;
            padding: 16px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .proceed-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .proceed-btn:hover:not(:disabled) {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
        }
        
        .proceed-btn:hover:not(:disabled)::before {
            left: 100%;
        }
        
        .proceed-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .movie-summary {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .meta-info {
                justify-content: center;
            }
            
            .seat-map {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="movies.php">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container purchase-container">
        <!-- Movie Summary -->
        <div class="movie-summary">
            <?php if ($poster): ?>
                <img src="../uploads/posters/<?php echo $poster; ?>" 
                     alt="<?php echo htmlspecialchars($item_name); ?>" 
                     class="summary-poster">
            <?php else: ?>
                <div style="width:120px; height:170px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
                    No Poster
                </div>
            <?php endif; ?>
            
            <div class="summary-details">
                <h1><?php echo htmlspecialchars($item_name); ?></h1>
                
                <div class="meta-info">
                    <span class="rating-badge rating-<?php echo str_replace('-', '', $rating); ?>">
                        <?php echo $rating; ?>
                    </span>
                    <span>⏱️ <?php echo $duration; ?> min</span>
                    <?php if (isset($genre)): ?>
                        <span>🎭 <?php echo htmlspecialchars($genre); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($ticket_type == 'physical'): ?>
                    <div class="location-info">
                        <strong><?php echo htmlspecialchars($cinema_name); ?></strong><br>
                        📍 <?php echo htmlspecialchars($location); ?><br>
                        🎬 Screen <?php echo $screen_number; ?><br>
                        📅 <?php echo date('F d, Y', strtotime($show_date)); ?> at <?php echo date('h:i A', strtotime($show_time)); ?>
                    </div>
                <?php else: ?>
                    <div class="location-info">
                        <strong>Online Streaming</strong><br>
                        Watch anywhere, anytime with 3 views per ticket
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if ($ticket_type == 'online'): ?>
            <!-- Online Schedule Selection -->
            <div class="purchase-form">
                <h2>Select Streaming Time</h2>
                
                <div class="online-benefits">
                    <h3>🎥 Online Streaming Benefits</h3>
                    <ul>
                        <li>Watch on any device (phone, tablet, computer)</li>
                        <li>3 views included per ticket</li>
                        <li>Valid for 30 days after purchase</li>
                        <li>HD streaming quality</li>
                        <li>Pause and resume anytime</li>
                        <li><span style="color: var(--red);">20% discount</span> applied to online tickets</li>
                    </ul>
                </div>
                
                <div class="schedule-grid" id="scheduleGrid">
                    <?php foreach ($online_schedules as $schedule): 
                        $available = $schedule['max_viewers'] - $schedule['current_viewers'];
                        $status_class = $available <= 5 ? 'warning' : '';
                    ?>
                        <div class="schedule-card" onclick="selectSchedule(<?php echo $schedule['id']; ?>, <?php echo $schedule['price']; ?>, this)">
                            <div class="schedule-time">
                                🕐 <?php echo date('h:i A', strtotime($schedule['show_time'])); ?>
                            </div>
                            <div class="schedule-date">
                                <?php echo date('F d, Y', strtotime($schedule['show_date'])); ?>
                            </div>
                            <div class="availability <?php echo $status_class; ?>">
                                👥 <?php echo $available; ?> spots available
                            </div>
                            <div class="schedule-price">
                                $<?php echo number_format($schedule['price'], 2); ?> per ticket
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form method="GET" action="payment.php" id="onlineForm" style="margin-top: 30px;">
                    <input type="hidden" name="online_id" id="selectedScheduleId">
                    
                    <div class="form-group">
                        <label>Number of Tickets</label>
                        <select name="quantity" id="quantity" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="price-breakdown" id="priceBreakdown" style="display: none;">
                        <div class="price-row">
                            <span>Price per ticket:</span>
                            <span id="perTicketPrice">$0.00</span>
                        </div>
                        <div class="price-row">
                            <span>Processing Fee (₱150 each):</span>
                            <span>$<?php echo number_format($processing_fee, 2); ?></span>
                        </div>
                        <div class="price-row" id="subtotalRow">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span id="total">$0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                        Proceed to Payment
                    </button>
                </form>
            </div>
            
            <script>
                let selectedSchedulePrice = 0;
                const processingFee = <?php echo $processing_fee; ?>;
                
                function selectSchedule(scheduleId, price, element) {
                    document.querySelectorAll('.schedule-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    
                    element.classList.add('selected');
                    
                    selectedSchedulePrice = price;
                    document.getElementById('selectedScheduleId').value = scheduleId;
                    
                    document.getElementById('priceBreakdown').style.display = 'block';
                    document.getElementById('perTicketPrice').textContent = '$' + price.toFixed(2);
                    
                    document.getElementById('proceedBtn').disabled = false;
                    
                    updatePrice();
                }
                
                function updatePrice() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    const subtotal = selectedSchedulePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                document.getElementById('quantity').addEventListener('change', updatePrice);
                
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
                    <div class="legend-item">
                        <div class="legend-box available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box selected"></div>
                        <span>Selected</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box booked"></div>
                        <span>Booked</span>
                    </div>
                </div>
                
                <div class="screen">SCREEN</div>
                
                <div class="seat-map" id="seatMap">
                    <?php foreach ($seats as $seat): ?>
                        <div class="seat <?php echo $seat['available'] ? 'available' : 'booked'; ?>" 
                             data-seat="<?php echo $seat['number']; ?>"
                             onclick="selectSeat(this, <?php echo $available_seats; ?>)">
                            <?php echo $seat['number']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="selected-info" id="selectedInfo">
                    Selected Seats: <span id="selectedSeatsDisplay">None</span>
                </div>
            </div>
            
            <!-- Purchase Form for Physical Tickets -->
            <div class="purchase-form">
                <form method="POST" id="physicalForm">
                    <?php if (!empty($linked_accounts)): ?>
                        <div class="form-group">
                            <label>Purchase for:</label>
                            <select name="for_user_id" id="for_user_id">
                                <option value="<?php echo $user['id']; ?>">
                                    Myself (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                                </option>
                                <?php foreach ($linked_accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?> 
                                        (<?php echo ucfirst($account['account_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Number of Tickets</label>
                        <select name="quantity" id="quantity" onchange="updateQuantity()">
                            <?php for ($i = 1; $i <= min(10, $available_seats); $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <input type="hidden" name="seats" id="selectedSeatsInput">
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Price per ticket:</span>
                            <span>$<?php echo number_format($base_price, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Processing Fee (₱150 each):</span>
                            <span>$<?php echo number_format($processing_fee, 2); ?></span>
                        </div>
                        <div class="price-row" id="subtotalRow">
                            <span>Subtotal:</span>
                            <span id="subtotal">$<?php echo number_format($base_price, 2); ?></span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span id="total">$<?php echo number_format($base_price + $processing_fee, 2); ?></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                        Proceed to Payment
                    </button>
                </form>
            </div>
            
            <script>
                let selectedSeats = [];
                const basePrice = <?php echo $base_price; ?>;
                const processingFee = <?php echo $processing_fee; ?>;
                
                function selectSeat(seat, maxSeats) {
                    if (seat.classList.contains('booked')) return;
                    
                    const quantity = parseInt(document.getElementById('quantity').value);
                    
                    if (seat.classList.contains('selected')) {
                        seat.classList.remove('selected');
                        selectedSeats = selectedSeats.filter(s => s !== seat.dataset.seat);
                    } else {
                        if (selectedSeats.length < quantity) {
                            seat.classList.add('selected');
                            selectedSeats.push(seat.dataset.seat);
                        } else {
                            alert('You can only select ' + quantity + ' seat(s)');
                        }
                    }
                    
                    updateSelectedSeats();
                    updateProceedButton();
                    updateTotal();
                }
                
                function updateSelectedSeats() {
                    const display = document.getElementById('selectedSeatsDisplay');
                    const input = document.getElementById('selectedSeatsInput');
                    
                    if (selectedSeats.length) {
                        display.textContent = selectedSeats.join(', ');
                        input.value = selectedSeats.join(',');
                    } else {
                        display.textContent = 'None';
                        input.value = '';
                    }
                }
                
                function updateProceedButton() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    document.getElementById('proceedBtn').disabled = selectedSeats.length !== quantity;
                }
                
                function updateQuantity() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    
                    while (selectedSeats.length > quantity) {
                        const removed = selectedSeats.pop();
                        const seat = document.querySelector(`[data-seat="${removed}"]`);
                        if (seat) seat.classList.remove('selected');
                    }
                    
                    updateSelectedSeats();
                    updateProceedButton();
                    
                    const subtotal = basePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                function updateTotal() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    const subtotal = basePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                document.getElementById('physicalForm').addEventListener('submit', function(e) {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    if (selectedSeats.length !== quantity) {
                        e.preventDefault();
                        alert('Please select ' + quantity + ' seat(s)');
                    }
                });
            </script>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>