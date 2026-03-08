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
    <style>
        .movie-header {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
            background: #1a1a1a;
            padding: 30px;
            border: 2px solid #00ffff;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .movie-poster { width: 100%; border-radius: 8px; border: 2px solid #00ffff; }
        .movie-info h1 { color: #00ffff; margin-bottom: 20px; }
        .movie-meta { color: #888; margin-bottom: 20px; line-height: 1.8; }
        .rating-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 10px;
        }
        .rating-G { background: #44ff44; color: #000; }
        .rating-PG { background: #ffff44; color: #000; }
        .rating-PG-13 { background: #ff8844; color: #000; }
        .rating-R { background: #ff4444; color: #fff; }
        .trailer-container {
            position: relative;
            padding-bottom: 45%;
            height: 0;
            overflow: hidden;
            border: 2px solid #00ffff;
            border-radius: 8px;
            margin: 30px 0;
        }
        .trailer-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin: 30px 0;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .tab {
            padding: 10px 30px;
            background: transparent;
            border: 1px solid #00ffff;
            color: #00ffff;
            cursor: pointer;
            border-radius: 4px;
            font-size: 1.1rem;
        }
        .tab.active {
            background: #00ffff;
            color: #000;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .date-group { margin-bottom: 30px; }
        .date-title {
            color: #00ffff;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #00ffff;
        }
        .screenings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .screening-card {
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        .screening-card:hover {
            border-color: #00ffff;
            transform: translateY(-3px);
        }
        .screening-time {
            font-size: 1.3rem;
            color: #00ffff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .screening-cinema { color: #888; margin: 5px 0; }
        .screening-screen { color: #888; }
        .screening-seats { color: #44ff44; margin: 10px 0; }
        .screening-price {
            font-size: 1.2rem;
            color: #fff;
            font-weight: bold;
        }
        .select-btn {
            display: inline-block;
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: #00ffff;
            color: #000;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .select-btn:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .kid-notice {
            background: rgba(255, 255, 68, 0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .favorite-btn {
            display: inline-block;
            padding: 10px 20px;
            margin-left: 10px;
            border: 1px solid #00ffff;
            color: #00ffff;
            text-decoration: none;
            border-radius: 4px;
        }
        .favorite-btn.active {
            background: #00ffff;
            color: #000;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
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
        <?php if ($user['account_type'] == 'kid'): ?>
            <div class="kid-notice">
                ⚠️ As a Kid account, you can only view G and PG movies. 
                <?php if ($parent): ?>
                    To purchase tickets, your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) will be notified.
                <?php endif; ?>
            </div>
        <?php elseif ($user['account_type'] == 'teen'): ?>
            <div class="kid-notice">
                ⚠️ As a Teen account, you can view G, PG, and PG-13 movies.
                <?php if ($parent): ?>
                    Ticket purchases will be sent to your parent for approval.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Movie Header -->
        <div class="movie-header">
            <div>
                <?php if ($movie['poster']): ?>
                    <img src="../uploads/posters/<?php echo $movie['poster']; ?>" class="movie-poster">
                <?php else: ?>
                    <div style="width:100%; height:400px; background:#000; border:2px solid #00ffff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#666;">
                        No Poster
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="movie-info">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
                    <?php if ($is_favorite): ?>
                        <a href="favorites.php?remove=<?php echo $movie['id']; ?>" class="favorite-btn active">❤️ In Favorites</a>
                    <?php else: ?>
                        <a href="favorites.php?add=<?php echo $movie['id']; ?>" class="favorite-btn">🤍 Add to Favorites</a>
                    <?php endif; ?>
                </div>
                
                <div class="movie-meta">
                    <span class="rating-badge rating-<?php echo $movie['rating']; ?>">
                        <?php echo $movie['rating']; ?>
                    </span>
                    ⏱️ <?php echo $movie['duration']; ?> minutes<br>
                    🎭 <?php echo htmlspecialchars($movie['genre']); ?><br>
                    📅 Released: <?php echo date('F d, Y', strtotime($movie['release_date'])); ?>
                </div>
                
                <div style="color: #ccc; line-height: 1.8; margin-bottom: 20px;">
                    <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                </div>
                
                <div style="font-size: 1.5rem; color: #00ffff;">
                    From $<?php echo number_format($movie['price'], 2); ?>
                </div>
            </div>
        </div>
        
        <!-- Trailer (embedded) -->
        <?php if ($movie['trailer_url']): ?>
            <h2 style="color:#00ffff; margin:30px 0 15px;">Trailer</h2>
            <div class="trailer-container">
                <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>
        
        <!-- Watch Options Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('cinema')">🎬 Watch in Cinema</button>
            <button class="tab" onclick="showTab('online')">🎥 Watch Online</button>
        </div>
        
        <!-- Cinema Tab -->
        <div id="cinema-tab" class="tab-content active">
            <?php if (empty($cinema_screenings)): ?>
                <p style="color:#888; text-align:center; padding:40px;">No cinema screenings available</p>
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
                        <div class="screening-cinema"><?php echo htmlspecialchars($screening['cinema_name']); ?></div>
                        <div class="screening-screen">Screen <?php echo $screening['screen_number']; ?></div>
                        <div class="screening-cinema">📍 <?php echo htmlspecialchars($screening['location']); ?></div>
                        <div class="screening-seats">🪑 <?php echo $screening['available_seats']; ?> seats available</div>
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
                <p style="color:#888; text-align:center; padding:40px;">No online streaming schedules available</p>
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
                        <div class="screening-cinema">Online Streaming</div>
                        <div class="screening-seats">👥 <?php echo $slot['max_viewers'] - $slot['current_viewers']; ?> spots left</div>
                        <div class="screening-price">$<?php echo number_format($slot['price'], 2); ?></div>
                        <a href="select_online.php?schedule_id=<?php echo $slot['id']; ?>" class="select-btn">Select This Time</a>
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