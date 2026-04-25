<?php
// user/movie_detail.php - COMPLETELY REWRITTEN
// NEW FLOW: Ticket type choice first, then cinema/screening selection, then seat selection
// Uses profile_type for age restrictions
require_once '../includes/functions.php';
requireLogin();

$movie_id = $_GET['id'] ?? 0;
$pdo = getDB();
$user = getCurrentUser();

// Auto-archive expired screenings
autoArchiveExpiredScreenings();

// Get movie details
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    setFlash('Movie not found', 'error');
    header('Location: movies.php');
    exit;
}

// Use profile_type for age restrictions (from session)
$profile_type = $_SESSION['profile_type'] ?? $user['account_type'] ?? 'adult';

// Check age restriction based on PROFILE TYPE
$age_restricted = false;
$restriction_message = '';

if ($profile_type == 'kid') {
    if (!in_array($movie['rating'], ['G', 'PG'])) {
        $age_restricted = true;
        $restriction_message = 'This movie is rated ' . $movie['rating'] . '. Kid profiles can only watch G and PG rated movies.';
    }
} elseif ($profile_type == 'teen') {
    if (!in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
        $age_restricted = true;
        $restriction_message = 'This movie is rated ' . $movie['rating'] . '. Teen profiles can only watch G, PG, and PG-13 rated movies.';
    }
}

// Get cinemas that show this movie (for physical tickets)
$cinemas_for_movie = getCinemasForMovie($movie_id);

// Get online schedules for this movie (for online tickets)
$stmt = $pdo->prepare("
    SELECT os.*, 
           (SELECT COUNT(*) FROM tickets WHERE online_schedule_id = os.id AND status = 'paid') as current_viewers
    FROM online_schedule os
    WHERE os.movie_id = ? 
    AND os.show_date >= CURDATE() 
    AND os.status = 'scheduled'
    AND (SELECT COUNT(*) FROM tickets WHERE online_schedule_id = os.id AND status = 'paid') < os.max_viewers
    ORDER BY os.show_date, os.show_time
");
$stmt->execute([$movie_id]);
$online_schedules = $stmt->fetchAll();

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - CinemaTicket</title>
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
            --success-color: #44ff44;
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
            --success-color: #00aa00;
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
            --success-color: #00ffff;
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
            --success-color: #00ff00;
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
            text-decoration: none;
            text-transform: uppercase;
        }
        .logo::before { content: "🎬"; margin-right: 8px; }
        
        .nav-links { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        
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
        
        .profile-switch:hover {
            background: #e50914;
            color: white !important;
        }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        
        .movie-header {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .movie-poster {
            width: 100%;
            border-radius: 16px;
            border: 2px solid var(--border-color);
        }
        
        .movie-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        
        .movie-meta { color: var(--text-secondary); margin-bottom: 20px; }
        .movie-description { margin-bottom: 20px; line-height: 1.6; }
        .movie-price { font-size: 2rem; font-weight: 800; color: var(--accent); margin-top: auto; }
        
        .primary-buy-btn {
            display: block;
            width: 100%;
            background: var(--accent);
            color: var(--bg-primary);
            text-align: center;
            padding: 18px;
            border-radius: 40px;
            font-weight: 800;
            font-size: 1.3rem;
            text-transform: uppercase;
            margin-bottom: 30px;
            cursor: pointer;
            border: none;
        }
        
        .primary-buy-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px var(--accent-glow); background: var(--accent-dark); }
        
        .kid-notice {
            background: rgba(255, 255, 68, 0.1);
            border: 1px solid #ffff44;
            border-radius: 24px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
            color: #ffff44;
        }
        
        .age-restricted {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid #ff4444;
            border-radius: 24px;
            padding: 25px;
            text-align: center;
            margin-bottom: 30px;
            color: #ff4444;
        }
        
        /* Ticket Type Section */
        .ticket-type-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .ticket-type-buttons {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .ticket-type-btn {
            flex: 1;
            min-width: 250px;
            padding: 30px 20px;
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
            border-radius: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .ticket-type-btn:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 15px 30px var(--accent-glow);
        }
        
        .ticket-type-icon { font-size: 3.5rem; display: block; margin-bottom: 15px; }
        .ticket-type-title { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 10px; }
        .ticket-type-desc { font-size: 0.9rem; color: var(--text-secondary); }
        
        /* Physical Ticket Flow */
        .physical-flow {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            display: none;
        }
        
        .physical-flow.visible { display: block; }
        
        .cinema-selector, .screening-selector {
            margin-bottom: 25px;
        }
        
        .physical-flow label {
            color: var(--accent);
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        
        .physical-flow select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            cursor: pointer;
        }
        
        .physical-flow select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .screening-times {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .screening-time-card {
            padding: 15px 20px;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .screening-time-card:hover {
            border-color: var(--accent);
            transform: translateX(5px);
            background: rgba(var(--accent), 0.05);
        }
        
        .screening-time-card.selected {
            border: 2px solid var(--accent);
            background: rgba(var(--accent), 0.1);
        }
        
        .screening-time-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .screening-date {
            color: var(--accent);
            font-weight: 600;
        }
        
        .screening-time {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .screening-screen {
            color: var(--text-secondary);
        }
        
        .screening-price {
            color: var(--accent);
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .screening-seats {
            color: var(--success-color);
            font-size: 0.85rem;
        }
        
        .proceed-seat-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s;
        }
        
        .proceed-seat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--accent-glow);
        }
        
        .proceed-seat-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Online Ticket Flow */
        .online-flow {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            display: none;
        }
        
        .online-flow.visible { display: block; }
        
        .online-schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 25px;
        }
        
        .online-schedule-card {
            background: rgba(0,0,0,0.2);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .online-schedule-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px var(--accent-glow);
        }
        
        .online-schedule-card.selected {
            border-color: var(--accent);
            background: rgba(var(--accent), 0.1);
        }
        
        .online-date {
            color: var(--accent);
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .online-time {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .online-availability {
            color: var(--success-color);
            font-size: 0.9rem;
            margin: 10px 0;
        }
        
        .online-availability.warning {
            color: #ff8844;
        }
        
        .online-price {
            color: var(--accent);
            font-size: 1.3rem;
            font-weight: 700;
            margin: 15px 0;
        }
        
        .proceed-payment-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .proceed-payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--accent-glow);
        }
        
        .proceed-payment-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .back-btn {
            margin-top: 15px;
            padding: 12px 20px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .back-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        .trailer-section { margin: 40px 0; }
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent);
            display: inline-block;
        }
        
        .trailer-container {
            position: relative;
            padding-bottom: 45%;
            height: 0;
            overflow: hidden;
            border-radius: 24px;
            margin-top: 20px;
        }
        .trailer-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 30px 0;
            opacity: 0.3;
        }
        
        .no-screenings {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 1024px) { .movie-header { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .nav-links { display: none; } 
            .movie-title { font-size: 2rem; } 
            .ticket-type-buttons { flex-direction: column; }
            .screening-time-card { flex-direction: column; text-align: center; }
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
    
    <main class="container">
        <!-- Movie Header -->
        <div class="movie-header">
            <div>
                <?php if ($movie['poster']): ?>
                    <img src="../uploads/posters/<?php echo $movie['poster']; ?>" class="movie-poster" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                <?php else: ?>
                    <div style="width:100%; height:450px; background:var(--bg-tertiary); border-radius:16px; display:flex; align-items:center; justify-content:center;">No Poster Available</div>
                <?php endif; ?>
            </div>
            <div class="movie-info">
                <h1 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                <div class="movie-meta">
                    <span>⭐ <?php echo $movie['rating']; ?> | ⏱️ <?php echo $movie['duration']; ?> min | 🎭 <?php echo htmlspecialchars($movie['genre']); ?></span><br>
                    <span>📅 Released: <?php echo date('F d, Y', strtotime($movie['release_date'])); ?></span>
                </div>
                <div class="movie-description"><?php echo nl2br(htmlspecialchars($movie['description'])); ?></div>
                <div class="movie-price">₱<?php echo number_format($movie['price'], 2); ?></div>
            </div>
        </div>
        
        <!-- Age restriction or kid notice -->
        <?php if ($age_restricted): ?>
            <div class="age-restricted">
                ⚠️ <?php echo $restriction_message; ?>
                <br><br>
                <a href="movies.php" class="btn-action" style="display: inline-block; padding: 10px 25px; background: var(--accent); color: var(--bg-primary); text-decoration: none; border-radius: 40px;">← Back to Movies</a>
            </div>
        <?php elseif ($profile_type == 'kid'): ?>
            <div class="kid-notice">
                👶 Kid profiles cannot purchase tickets directly. Please ask a parent or guardian to buy tickets for you.
                <br><br>
                <a href="movies.php" class="btn-action" style="display: inline-block; padding: 10px 25px; background: #ffff44; color: #000; text-decoration: none; border-radius: 40px;">← Browse More Movies</a>
            </div>
        <?php else: ?>
            <!-- Step 1: Primary Buy Button -->
            <button id="primaryBuyBtn" class="primary-buy-btn">🎟️ Buy Tickets</button>
            
            <!-- Step 1: Ticket Type Selection (shown after Buy Tickets clicked) -->
            <div id="ticketTypeSection" class="ticket-type-section" style="display: none;">
                <h2 style="color: var(--accent); margin-bottom: 15px;">Choose How to Watch</h2>
                <div class="ticket-type-buttons">
                    <div id="physicalTypeBtn" class="ticket-type-btn">
                        <span class="ticket-type-icon">🎬</span>
                        <div class="ticket-type-title">Physical (In Cinema)</div>
                        <div class="ticket-type-desc">Watch on the big screen<br>Select your preferred seats</div>
                    </div>
                    <div id="onlineTypeBtn" class="ticket-type-btn">
                        <span class="ticket-type-icon">💻</span>
                        <div class="ticket-type-title">Online Streaming</div>
                        <div class="ticket-type-desc">Watch from anywhere<br>7-day access after purchase</div>
                    </div>
                </div>
                <button id="backFromTypeBtn" class="back-btn" style="margin-top: 25px;">← Back</button>
            </div>
            
            <!-- Step 2A: Physical Ticket Flow -->
            <div id="physicalFlow" class="physical-flow">
                <h3 style="color: var(--accent); margin-bottom: 20px;">🎬 Select Cinema & Screening</h3>
                
                <div class="cinema-selector">
                    <label>🏛️ Select Cinema</label>
                    <select id="cinemaSelect">
                        <option value="">-- Select a cinema --</option>
                        <?php foreach ($cinemas_for_movie as $cinema): ?>
                            <option value="<?php echo $cinema['id']; ?>" data-name="<?php echo htmlspecialchars($cinema['name']); ?>">
                                <?php echo htmlspecialchars($cinema['name']); ?> - <?php echo htmlspecialchars($cinema['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="screening-selector" id="screeningSelector" style="display: none;">
                    <label>🎟️ Select Screening Time</label>
                    <div id="screeningTimes" class="screening-times">
                        <!-- AJAX loaded screening times will appear here -->
                    </div>
                </div>
                
                <button id="proceedToSeatBtn" class="proceed-seat-btn" disabled>Proceed to Seat Selection →</button>
                <button id="backFromPhysicalBtn" class="back-btn">← Back to Ticket Types</button>
            </div>
            
            <!-- Step 2B: Online Ticket Flow -->
            <div id="onlineFlow" class="online-flow">
                <h3 style="color: var(--accent); margin-bottom: 20px;">💻 Select Streaming Time</h3>
                
                <?php if (empty($online_schedules)): ?>
                    <div class="no-screenings">
                        🎬 No online streaming schedules available for this movie.
                    </div>
                <?php else: ?>
                    <div id="onlineScheduleGrid" class="online-schedule-grid">
                        <?php foreach ($online_schedules as $schedule): 
                            $available = $schedule['max_viewers'] - ($schedule['current_viewers'] ?? 0);
                            $availability_class = $available <= 5 ? 'warning' : '';
                        ?>
                            <div class="online-schedule-card" data-id="<?php echo $schedule['id']; ?>" data-price="<?php echo $schedule['price']; ?>">
                                <div class="online-date">📅 <?php echo date('F d, Y', strtotime($schedule['show_date'])); ?></div>
                                <div class="online-time">⏰ <?php echo date('h:i A', strtotime($schedule['show_time'])); ?></div>
                                <div class="online-availability <?php echo $availability_class; ?>">
                                    👥 <?php echo $available; ?> spots available
                                </div>
                                <div class="online-price">₱<?php echo number_format($schedule['price'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button id="proceedToPaymentBtn" class="proceed-payment-btn" disabled>Proceed to Payment →</button>
                <?php endif; ?>
                
                <button id="backFromOnlineBtn" class="back-btn">← Back to Ticket Types</button>
            </div>
        <?php endif; ?>
        
        <!-- Trailer -->
        <?php if ($movie['trailer_url']): ?>
            <div class="trailer-section">
                <h2 class="section-title">Trailer</h2>
                <div class="trailer-container">
                    <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        // State variables
        let selectedScreeningId = null;
        let selectedOnlineScheduleId = null;
        let selectedOnlinePrice = null;
        
        // DOM Elements
        const primaryBuyBtn = document.getElementById('primaryBuyBtn');
        const ticketTypeSection = document.getElementById('ticketTypeSection');
        const physicalTypeBtn = document.getElementById('physicalTypeBtn');
        const onlineTypeBtn = document.getElementById('onlineTypeBtn');
        const physicalFlow = document.getElementById('physicalFlow');
        const onlineFlow = document.getElementById('onlineFlow');
        const backFromTypeBtn = document.getElementById('backFromTypeBtn');
        const backFromPhysicalBtn = document.getElementById('backFromPhysicalBtn');
        const backFromOnlineBtn = document.getElementById('backFromOnlineBtn');
        const cinemaSelect = document.getElementById('cinemaSelect');
        const screeningSelector = document.getElementById('screeningSelector');
        const screeningTimes = document.getElementById('screeningTimes');
        const proceedToSeatBtn = document.getElementById('proceedToSeatBtn');
        const proceedToPaymentBtn = document.getElementById('proceedToPaymentBtn');
        
        // Step 1: Show Buy Tickets -> Show Ticket Type Selection
        if (primaryBuyBtn) {
            primaryBuyBtn.addEventListener('click', function() {
                primaryBuyBtn.style.display = 'none';
                ticketTypeSection.style.display = 'block';
                ticketTypeSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        
        // Step 2A: Physical Ticket Selected
        if (physicalTypeBtn) {
            physicalTypeBtn.addEventListener('click', function() {
                ticketTypeSection.style.display = 'none';
                physicalFlow.classList.add('visible');
                physicalFlow.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        
        // Step 2B: Online Ticket Selected
        if (onlineTypeBtn) {
            onlineTypeBtn.addEventListener('click', function() {
                ticketTypeSection.style.display = 'none';
                onlineFlow.classList.add('visible');
                onlineFlow.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        
        // Back buttons
        if (backFromTypeBtn) {
            backFromTypeBtn.addEventListener('click', function() {
                ticketTypeSection.style.display = 'none';
                primaryBuyBtn.style.display = 'block';
                primaryBuyBtn.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        
        if (backFromPhysicalBtn) {
            backFromPhysicalBtn.addEventListener('click', function() {
                physicalFlow.classList.remove('visible');
                ticketTypeSection.style.display = 'block';
                ticketTypeSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Reset selections
                selectedScreeningId = null;
                proceedToSeatBtn.disabled = true;
                cinemaSelect.value = '';
                screeningSelector.style.display = 'none';
            });
        }
        
        if (backFromOnlineBtn) {
            backFromOnlineBtn.addEventListener('click', function() {
                onlineFlow.classList.remove('visible');
                ticketTypeSection.style.display = 'block';
                ticketTypeSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Reset selections
                selectedOnlineScheduleId = null;
                if (proceedToPaymentBtn) proceedToPaymentBtn.disabled = true;
                document.querySelectorAll('.online-schedule-card').forEach(card => {
                    card.classList.remove('selected');
                });
            });
        }
        
        // Cinema selection -> AJAX load screenings
        if (cinemaSelect) {
            cinemaSelect.addEventListener('change', function() {
                const cinemaId = this.value;
                if (!cinemaId) {
                    screeningSelector.style.display = 'none';
                    selectedScreeningId = null;
                    proceedToSeatBtn.disabled = true;
                    return;
                }
                
                screeningSelector.style.display = 'block';
                screeningTimes.innerHTML = '<div style="text-align:center; padding:20px;">Loading screenings...</div>';
                
                fetch(`ajax_get_screenings.php?movie_id=<?php echo $movie_id; ?>&cinema_id=${cinemaId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            screeningTimes.innerHTML = '<div class="no-screenings">No screenings available for this cinema.</div>';
                            selectedScreeningId = null;
                            proceedToSeatBtn.disabled = true;
                        } else {
                            screeningTimes.innerHTML = '';
                            data.forEach(screening => {
                                const card = document.createElement('div');
                                card.className = 'screening-time-card';
                                card.setAttribute('data-id', screening.id);
                                card.setAttribute('data-price', screening.price);
                                card.innerHTML = `
                                    <div class="screening-time-info">
                                        <span class="screening-date">📅 ${formatDate(screening.show_date)}</span>
                                        <span class="screening-time">⏰ ${formatTime(screening.show_time)}</span>
                                        <span class="screening-screen">📺 Screen ${screening.screen_number}</span>
                                        <span class="screening-seats">🪑 ${screening.available_seats} seats left</span>
                                    </div>
                                    <div class="screening-price">₱${parseFloat(screening.price).toFixed(2)}</div>
                                `;
                                
                                card.addEventListener('click', function() {
                                    // Remove selected class from all cards
                                    document.querySelectorAll('.screening-time-card').forEach(c => {
                                        c.classList.remove('selected');
                                    });
                                    // Add selected class to this card
                                    this.classList.add('selected');
                                    selectedScreeningId = this.getAttribute('data-id');
                                    proceedToSeatBtn.disabled = false;
                                });
                                
                                screeningTimes.appendChild(card);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        screeningTimes.innerHTML = '<div class="no-screenings">Error loading screenings. Please try again.</div>';
                    });
            });
        }
        
        // Proceed to Seat Selection (Physical)
        if (proceedToSeatBtn) {
            proceedToSeatBtn.addEventListener('click', function() {
                if (selectedScreeningId) {
                    window.location.href = `select_seat.php?screening_id=${selectedScreeningId}`;
                } else {
                    alert('Please select a screening time first');
                }
            });
        }
        
        // Online schedule card selection
        const onlineCards = document.querySelectorAll('.online-schedule-card');
        if (onlineCards.length > 0) {
            onlineCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    onlineCards.forEach(c => c.classList.remove('selected'));
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    selectedOnlineScheduleId = this.getAttribute('data-id');
                    selectedOnlinePrice = parseFloat(this.getAttribute('data-price'));
                    
                    if (proceedToPaymentBtn) {
                        proceedToPaymentBtn.disabled = false;
                    }
                });
            });
        }
        
        // Proceed to Payment (Online)
        if (proceedToPaymentBtn) {
            proceedToPaymentBtn.addEventListener('click', function() {
                if (selectedOnlineScheduleId) {
                    // Quantity fixed to 1 for online tickets
                    window.location.href = `payment.php?type=online&id=${selectedOnlineScheduleId}&quantity=1`;
                } else {
                    alert('Please select a streaming time first');
                }
            });
        }
        
        // Helper functions
        function formatTime(time) {
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }
        
        function formatDate(date) {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    </script>
</body>
</html>