<?php
// user/movie_detail.php
require_once '../includes/functions.php';
requireLogin();

$movie_id = $_GET['id'] ?? 0;
$pdo = getDB();
$user = getCurrentUser();

// Get movie details
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    setFlash('Movie not found', 'error');
    header('Location: movies.php');
    exit;
}

// Check age restriction
if ($user['account_type'] == 'kid' && !in_array($movie['rating'], ['G', 'PG'])) {
    setFlash('This movie is not available for your age group', 'error');
    header('Location: movies.php');
    exit;
} elseif ($user['account_type'] == 'teen' && !in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
    setFlash('This movie is not available for your age group', 'error');
    header('Location: movies.php');
    exit;
}

// Get cinema screenings
$stmt = $pdo->prepare("
    SELECT s.*, c.name as cinema_name, c.location 
    FROM screenings s
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.movie_id = ? AND s.show_date >= CURDATE()
    ORDER BY s.show_date, s.screen_number, s.show_time
");
$stmt->execute([$movie_id]);
$cinema_screenings = $stmt->fetchAll();

// Get online schedule
$online_schedule = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM online_schedule 
        WHERE movie_id = ? AND show_date >= CURDATE() AND status = 'scheduled'
        ORDER BY show_date, show_time
    ");
    $stmt->execute([$movie_id]);
    $online_schedule = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
    $online_schedule = [];
}

// Check if user has parent (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}

// Check if in favorites
$is_favorite = false;
try {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
    $stmt->execute([$user['id'], $movie_id]);
    $is_favorite = $stmt->fetch() ? true : false;
} catch (Exception $e) {
    // Favorites table might not exist yet
    $is_favorite = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - CinemaTicket</title>
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
        
        /* Age Restriction Notice */
        .age-notice {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 30px;
            color: var(--text-primary);
            border-left: 4px solid var(--red);
        }
        
        /* Movie Header */
        .movie-header {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .movie-header::before {
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
        
        .movie-poster {
            width: 100%;
            border-radius: 16px;
            border: 2px solid rgba(229, 9, 20, 0.3);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
            transition: all 0.3s;
        }
        
        .movie-poster:hover {
            transform: scale(1.02);
            border-color: var(--red);
        }
        
        .movie-info {
            display: flex;
            flex-direction: column;
        }
        
        .movie-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .movie-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .favorite-btn {
            display: inline-block;
            padding: 10px 25px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .favorite-btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .favorite-btn.active {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
        }
        
        .movie-meta {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.8;
            font-size: 1rem;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 700;
            margin-right: 10px;
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
        
        .movie-description {
            color: var(--text-primary);
            line-height: 1.8;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        
        .movie-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--red);
            margin-top: auto;
            text-shadow: 0 0 10px rgba(229, 9, 20, 0.3);
        }
        
        /* Trailer */
        .trailer-section {
            margin: 40px 0;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        .trailer-container {
            position: relative;
            padding-bottom: 45%;
            height: 0;
            overflow: hidden;
            border: 2px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            margin: 20px 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }
        
        .trailer-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 15px;
            margin: 40px 0 30px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding-bottom: 15px;
        }
        
        .tab {
            padding: 12px 30px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            cursor: pointer;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .tab:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .tab.active {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Date Groups */
        .date-group {
            margin-bottom: 40px;
        }
        
        .date-title {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 600;
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
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
        
        .screening-time {
            font-size: 1.5rem;
            color: var(--red);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .screening-detail {
            color: var(--text-secondary);
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .screening-detail i {
            color: var(--red);
            width: 20px;
        }
        
        .screening-seats {
            color: #44ff44;
            margin: 10px 0;
            font-weight: 600;
        }
        
        .screening-price {
            font-size: 1.3rem;
            color: var(--red);
            font-weight: 700;
            margin: 15px 0;
        }
        
        .select-btn {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 1px solid var(--red);
            color: var(--red);
            text-align: center;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .select-btn:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
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
            color: var(--text-secondary);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .movie-header {
                grid-template-columns: 1fr;
            }
            
            .movie-poster {
                max-width: 350px;
                margin: 0 auto;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .movie-title {
                font-size: 2rem;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                width: 100%;
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
    
    <main class="container">
        <!-- Kid/Teen notice -->
        <?php if ($user['account_type'] == 'kid' || $user['account_type'] == 'teen'): ?>
            <div class="age-notice">
                <?php if ($user['account_type'] == 'kid'): ?>
                    <strong>⚠️ Kid Account:</strong> You can only view G and PG movies. 
                <?php else: ?>
                    <strong>⚠️ Teen Account:</strong> You can view G, PG, and PG-13 movies.
                <?php endif; ?>
                <?php if ($parent): ?>
                    Ticket purchases will be sent to your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) for approval.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Movie Header -->
        <div class="movie-header">
            <div>
                <?php if ($movie['poster']): ?>
                    <img src="../uploads/posters/<?php echo $movie['poster']; ?>" class="movie-poster">
                <?php else: ?>
                    <div style="width:100%; height:450px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:16px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
                        No Poster Available
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="movie-info">
                <div class="movie-header-content">
                    <h1 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                    <?php if ($is_favorite): ?>
                        <a href="favorites.php?remove=<?php echo $movie['id']; ?>" class="favorite-btn active">❤️ In Favorites</a>
                    <?php else: ?>
                        <a href="favorites.php?add=<?php echo $movie['id']; ?>" class="favorite-btn">🤍 Add to Favorites</a>
                    <?php endif; ?>
                </div>
                
                <div class="movie-meta">
                    <span class="rating-badge rating-<?php echo str_replace('-', '', $movie['rating']); ?>">
                        <?php echo $movie['rating']; ?>
                    </span>
                    <span>⏱️ <?php echo $movie['duration']; ?> minutes</span><br>
                    <span>🎭 <?php echo htmlspecialchars($movie['genre']); ?></span><br>
                    <span>📅 Released: <?php echo date('F d, Y', strtotime($movie['release_date'])); ?></span>
                </div>
                
                <div class="movie-description">
                    <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                </div>
                
                <div class="movie-price">
                    From $<?php echo number_format($movie['price'], 2); ?>
                </div>
            </div>
        </div>
        
        <!-- Trailer (embedded) -->
        <?php if ($movie['trailer_url']): ?>
            <div class="trailer-section">
                <h2 class="section-title">Trailer</h2>
                <div class="trailer-container">
                    <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Watch Options Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('cinema')">🎬 Cinema Screenings</button>
            <button class="tab" onclick="showTab('online')">🎥 Online Streaming</button>
        </div>
        
        <!-- Cinema Tab -->
        <div id="cinema-tab" class="tab-content active">
            <?php if (empty($cinema_screenings)): ?>
                <div class="empty-state">
                    <p style="margin-bottom: 10px;">No cinema screenings available</p>
                    <p style="color: var(--text-secondary);">Check back later for new showtimes</p>
                </div>
            <?php else: ?>
                <?php 
                $current_date = '';
                foreach ($cinema_screenings as $screening): 
                    $date = $screening['show_date'];
                    if ($date != $current_date):
                        if ($current_date != '') echo '</div></div>';
                        $current_date = $date;
                        echo '<div class="date-group">';
                        echo '<div class="date-title">' . date('l, F d, Y', strtotime($date)) . '</div>';
                        echo '<div class="screenings-grid">';
                    endif;
                ?>
                    <div class="screening-card">
                        <div class="screening-time"><?php echo date('h:i A', strtotime($screening['show_time'])); ?></div>
                        <div class="screening-detail">
                            <i>🎬</i> <?php echo htmlspecialchars($screening['cinema_name']); ?>
                        </div>
                        <div class="screening-detail">
                            <i>🎥</i> Screen <?php echo $screening['screen_number']; ?>
                        </div>
                        <div class="screening-detail">
                            <i>📍</i> <?php echo htmlspecialchars($screening['location']); ?>
                        </div>
                        <div class="screening-seats">
                            <i>🪑</i> <?php echo $screening['available_seats']; ?> seats available
                        </div>
                        <div class="screening-price">$<?php echo number_format($screening['price'], 2); ?></div>
                        <a href="select_seats.php?screening_id=<?php echo $screening['id']; ?>" class="select-btn">Select Seats</a>
                    </div>
                <?php 
                endforeach;
                if ($current_date != '') echo '</div></div>';
                ?>
            <?php endif; ?>
        </div>
        
        <!-- Online Tab -->
        <div id="online-tab" class="tab-content">
            <?php if (empty($online_schedule)): ?>
                <div class="empty-state">
                    <p style="margin-bottom: 10px;">No online streaming schedules available</p>
                    <p style="color: var(--text-secondary);">Check back later for streaming options</p>
                </div>
            <?php else: ?>
                <?php 
                $current_date = '';
                foreach ($online_schedule as $slot): 
                    $date = $slot['show_date'];
                    if ($date != $current_date):
                        if ($current_date != '') echo '</div></div>';
                        $current_date = $date;
                        echo '<div class="date-group">';
                        echo '<div class="date-title">' . date('l, F d, Y', strtotime($date)) . '</div>';
                        echo '<div class="screenings-grid">';
                    endif;
                ?>
                    <div class="screening-card">
                        <div class="screening-time"><?php echo date('h:i A', strtotime($slot['show_time'])); ?></div>
                        <div class="screening-detail">
                            <i>🎥</i> Online Streaming
                        </div>
                        <div class="screening-detail">
                            <i>🌐</i> Watch from home
                        </div>
                        <div class="screening-seats">
                            <i>👥</i> <?php echo $slot['max_viewers'] - $slot['current_viewers']; ?> spots left
                        </div>
                        <div class="screening-price">$<?php echo number_format($slot['price'], 2); ?></div>
                        <a href="select_online.php?schedule_id=<?php echo $slot['id']; ?>" class="select-btn">Book Now</a>
                    </div>
                <?php 
                endforeach;
                if ($current_date != '') echo '</div></div>';
                ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'cinema') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('cinema-tab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('online-tab').classList.add('active');
            }
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>