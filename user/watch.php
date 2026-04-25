<?php
// user/watch.php - COMPLETELY FIXED
// Added: Watch history recording when user watches a movie
// Removed: View counting (using date-based expiry only)
// Now inserts into watch_history table automatically
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$ticket_code = $_GET['ticket_code'] ?? '';

// ========== MAIN PAGE LOAD – VALIDATE TICKET WITH DATE CHECK ==========
$validation = validateTicketByCode($ticket_code);
if (!$validation['valid']) {
    $reason = $validation['reason'];
    $error_message = '';
    switch ($reason) {
        case 'not_found': $error_message = 'Ticket not found'; break;
        case 'expired': $error_message = 'This ticket has expired'; break;
        case 'used': $error_message = 'This ticket has already been used'; break;
        case 'not_paid': $error_message = 'Payment pending for this ticket'; break;
        default: $error_message = 'Invalid ticket';
    }
    setFlash($error_message, 'error');
    header('Location: purchases.php');
    exit;
}

$ticket = $validation['ticket'];

// ========== DATE-BASED VALIDATION ==========
$today = date('Y-m-d');

// Get show_date (release date) from online_schedule
$stmt = $pdo->prepare("
    SELECT os.show_date, os.movie_id, t.week_expiry
    FROM tickets t
    JOIN online_schedule os ON t.online_schedule_id = os.id
    WHERE t.ticket_code = ?
");
$stmt->execute([$ticket_code]);
$schedule_data = $stmt->fetch();

$show_date = $schedule_data['show_date'] ?? null;
$movie_id = $schedule_data['movie_id'] ?? null;
$week_expiry = $schedule_data['week_expiry'] ?? $ticket['week_expiry'] ?? null;

// Check if streaming has started
if ($show_date && $today < $show_date) {
    setFlash('This movie is not available for streaming yet. Available from: ' . date('M d, Y', strtotime($show_date)), 'error');
    header('Location: purchases.php');
    exit;
}

// Check if ticket has expired
if ($week_expiry && $today > $week_expiry) {
    // Auto-mark as used
    $pdo->prepare("UPDATE tickets SET status = 'used' WHERE ticket_code = ?")->execute([$ticket_code]);
    setFlash('This ticket has expired. It was valid until ' . date('M d, Y', strtotime($week_expiry)), 'error');
    header('Location: purchases.php');
    exit;
}

// ========== RECORD IN WATCH HISTORY ==========
// Check if already recorded to avoid duplicates
$stmt = $pdo->prepare("
    SELECT id FROM watch_history 
    WHERE user_id = ? AND movie_id = ? AND DATE(watched_at) = CURDATE()
");
$stmt->execute([$user['id'], $movie_id]);
$already_recorded = $stmt->fetch();

if (!$already_recorded && $movie_id) {
    // Insert into watch history
    $stmt = $pdo->prepare("
        INSERT INTO watch_history (user_id, movie_id, watched_at, completed, watch_duration)
        VALUES (?, ?, NOW(), 0, 0)
    ");
    $stmt->execute([$user['id'], $movie_id]);
    
    // Optional: Update ticket to mark that viewing started (but not 'used' yet)
    // This is for analytics only - ticket remains 'paid' until expiry
}

// Get streaming URL
$stmt = $pdo->prepare("
    SELECT m.streaming_url, m.title, m.duration, m.id as movie_id
    FROM tickets t
    JOIN online_schedule os ON t.online_schedule_id = os.id
    JOIN movies m ON os.movie_id = m.id
    WHERE t.ticket_code = ?
");
$stmt->execute([$ticket_code]);
$movie = $stmt->fetch();

if (!$movie || empty($movie['streaming_url'])) {
    setFlash('Streaming URL not available for this movie', 'error');
    header('Location: purchases.php');
    exit;
}

$streaming_url = $movie['streaming_url'];
$movie_id_for_history = $movie['movie_id'];

// Convert YouTube URLs to embed format
if (strpos($streaming_url, 'youtube.com/watch?v=') !== false) {
    parse_str(parse_url($streaming_url, PHP_URL_QUERY), $params);
    $video_id = $params['v'] ?? '';
    if ($video_id) $streaming_url = 'https://www.youtube.com/embed/' . $video_id;
} elseif (strpos($streaming_url, 'youtu.be/') !== false) {
    $video_id = substr($streaming_url, strrpos($streaming_url, '/') + 1);
    $video_id = preg_replace('/\?.*/', '', $video_id);
    $streaming_url = 'https://www.youtube.com/embed/' . $video_id;
}

// Calculate days left for display
$days_left = 0;
if ($week_expiry) {
    $days_left = (strtotime($week_expiry) - strtotime($today)) / 86400;
}

// Get watch history count for this movie
$stmt = $pdo->prepare("
    SELECT COUNT(*) as watch_count, MAX(watched_at) as last_watch
    FROM watch_history 
    WHERE user_id = ? AND movie_id = ?
");
$stmt->execute([$user['id'], $movie_id_for_history]);
$watch_stats = $stmt->fetch();

$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch <?php echo htmlspecialchars($movie['title']); ?> - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-glow: 0 0 20px rgba(229,9,20,0.3);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(26,26,26,0.9) 0%, rgba(20,20,20,0.95) 100%);
        }
        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-glow: 0 0 20px rgba(229,9,20,0.2);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,240,240,0.95) 100%);
        }
        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-glow: 0 0 20px rgba(255,0,255,0.5);
            --border-color: rgba(255,0,255,0.3);
            --card-bg: linear-gradient(135deg, rgba(26,26,58,0.9) 0%, rgba(20,20,50,0.95) 100%);
        }
        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-glow: 0 0 20px rgba(0,255,0,0.5);
            --border-color: rgba(0,255,0,0.3);
            --card-bg: linear-gradient(135deg, rgba(10,26,10,0.9) 0%, rgba(5,20,5,0.95) 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
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
        .nav-links { display: flex; gap: 5px; flex-wrap: wrap; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .back-button:hover { border-color: var(--accent); color: var(--accent); transform: translateX(-5px); }
        .player-container h1 { color: var(--accent); font-size: 2rem; margin-bottom: 20px; }
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border: 2px solid var(--border-color);
            border-radius: 24px;
        }
        .video-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .player-info {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-top: 30px;
        }
        .validity-badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(var(--accent),0.1);
            border: 1px solid var(--accent);
            border-radius: 40px;
            margin-bottom: 20px;
        }
        .validity-badge.warning {
            border-color: #ff8844;
            color: #ff8844;
        }
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .info-item { background: rgba(0,0,0,0.3); border-radius: 16px; padding: 15px; }
        .info-label { color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 5px; }
        .info-value { font-size: 1.1rem; font-weight: 600; }
        .info-value.highlight { color: var(--accent); }
        .info-value.warning { color: #ff8844; }
        .watch-stats {
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        .watch-stats p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        .terms-list { list-style: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); }
        .terms-list li { margin: 10px 0; padding-left: 25px; position: relative; }
        .terms-list li::before { content: '•'; color: var(--accent); position: absolute; left: 8px; }
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .info-grid { grid-template-columns: repeat(2, 1fr); }
            .player-container h1 { font-size: 1.5rem; }
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
        <a href="purchases.php" class="back-button">← Back to My Tickets</a>
        
        <div class="cinema-strip"></div>
        
        <div class="player-container">
            <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
            
            <div class="video-wrapper">
                <iframe src="<?php echo htmlspecialchars($streaming_url); ?>" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            
            <div class="player-info">
                <!-- Validity display -->
                <div class="validity-badge <?php echo $days_left <= 3 ? 'warning' : ''; ?>">
                    📅 Valid until: <?php echo date('M d, Y', strtotime($week_expiry)); ?>
                    <?php if ($days_left <= 3): ?>
                        <span> (⚠️ <?php echo ceil($days_left); ?> days left)</span>
                    <?php endif; ?>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Ticket Code</div>
                        <div class="info-value highlight"><?php echo $ticket['ticket_code']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Days Remaining</div>
                        <div class="info-value <?php echo $days_left <= 3 ? 'warning' : ''; ?>">
                            <?php echo ceil($days_left); ?> days
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Valid Until</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($week_expiry)); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration</div>
                        <div class="info-value"><?php echo $movie['duration']; ?> min</div>
                    </div>
                </div>
                
                <!-- Watch History Stats -->
                <?php if ($watch_stats['watch_count'] > 0): ?>
                <div class="watch-stats">
                    <p>
                        🎬 You've watched this movie <?php echo $watch_stats['watch_count']; ?> time(s)<br>
                        Last watched: <?php echo date('M d, Y h:i A', strtotime($watch_stats['last_watch'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <ul class="terms-list">
                    <li>This ticket is valid for unlimited views until the expiry date</li>
                    <li>Streaming starts on the movie's release date</li>
                    <li>Do not share your screen or record the content</li>
                    <li>Once expired, you cannot access this content anymore</li>
                    <li>Your watch history is automatically saved to your profile</li>
                </ul>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>