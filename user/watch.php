<?php
// user/watch.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$ticket_code = $_GET['ticket_code'] ?? '';

// Get ticket details
$stmt = $pdo->prepare("
    SELECT t.*, m.title, m.streaming_url, m.duration,
           os.show_date, os.show_time
    FROM tickets t
    LEFT JOIN online_schedule os ON t.online_schedule_id = os.id
    LEFT JOIN movies m ON os.movie_id = m.id
    WHERE t.ticket_code = ? AND (t.user_id = ? OR t.user_id IN (SELECT id FROM users WHERE parent_id = ?))
");
$stmt->execute([$ticket_code, $user['id'], $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('Ticket not found', 'error');
    header('Location: purchases.php');
    exit;
}

// Validate ticket for streaming
if ($ticket['ticket_type'] != 'online') {
    setFlash('This ticket is for cinema viewing only', 'error');
    header('Location: purchases.php');
    exit;
}

if ($ticket['status'] != 'paid') {
    setFlash('Ticket must be paid before streaming', 'error');
    header('Location: purchases.php');
    exit;
}

if ($ticket['streaming_views'] >= $ticket['max_streaming_views']) {
    setFlash('Maximum streaming views reached (3/3)', 'error');
    header('Location: purchases.php');
    exit;
}

// Check if it's time to watch
$show_datetime = strtotime($ticket['show_date'] . ' ' . $ticket['show_time']);
if (time() < $show_datetime) {
    $minutes_until = ceil(($show_datetime - time()) / 60);
    setFlash("Streaming will be available in $minutes_until minutes", 'warning');
    header('Location: purchases.php');
    exit;
}

// Increment view count
$stmt = $pdo->prepare("UPDATE tickets SET streaming_views = streaming_views + 1 WHERE id = ?");
$stmt->execute([$ticket['id']]);

// Add to watch history
$stmt = $pdo->prepare("INSERT INTO watch_history (user_id, movie_id) VALUES (?, ?)");
$stmt->execute([$user['id'], $ticket['movie_id']]);

// Get streaming URL (use trailer if no streaming URL)
$streaming_url = $ticket['streaming_url'] ?: 'https://www.youtube.com/embed/dQw4w9WgXcQ';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch <?php echo htmlspecialchars($ticket['title']); ?> - CinemaTicket</title>
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
        
        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 40px;
            margin-bottom: 30px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .back-button:hover {
            border-color: var(--red);
            color: var(--red);
            transform: translateX(-5px);
        }
        
        /* Player Container */
        .player-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .player-container h1 {
            color: var(--red);
            font-size: 2.2rem;
            margin-bottom: 25px;
            font-family: 'Montserrat', sans-serif;
        }
        
        /* Video Wrapper */
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            transition: all 0.3s;
        }
        
        .video-wrapper:hover {
            border-color: var(--red);
            box-shadow: 0 30px 60px rgba(229, 9, 20, 0.2);
        }
        
        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* Player Info */
        .player-info {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 35px;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .player-info::before {
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
        
        /* View Counter */
        .view-counter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 40px;
            color: var(--red);
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .info-item {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .info-value {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .info-value.highlight {
            color: var(--red);
        }
        
        .info-value.code {
            font-family: 'Monaco', 'Courier New', monospace;
            color: var(--red);
        }
        
        /* Warning Message */
        .warning-message {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 18px 25px;
            border-radius: 40px;
            margin: 25px 0;
            border-left: 4px solid var(--red);
        }
        
        /* Terms Section */
        .terms-section {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .terms-section p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .terms-section p i {
            color: var(--red);
        }
        
        .terms-list {
            list-style: none;
            margin-top: 15px;
        }
        
        .terms-list li {
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .terms-list li::before {
            content: '•';
            color: var(--red);
            font-size: 1.2rem;
            position: absolute;
            left: 8px;
            top: -2px;
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
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .player-container h1 {
                font-size: 1.8rem;
            }
            
            .back-button {
                width: 100%;
                justify-content: center;
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
        <a href="purchases.php" class="back-button">
            <span>←</span> Back to My Tickets
        </a>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <div class="player-container">
            <h1><?php echo htmlspecialchars($ticket['title']); ?></h1>
            
            <div class="video-wrapper">
                <iframe src="<?php echo htmlspecialchars($streaming_url); ?>" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            
            <div class="player-info">
                <div class="view-counter">
                    <span>🎥</span> View <?php echo $ticket['streaming_views']; ?> of <?php echo $ticket['max_streaming_views']; ?>
                </div>
                
                <h2 style="color: var(--red); margin-bottom: 20px; font-size: 1.5rem;">Streaming Information</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Ticket Code</div>
                        <div class="info-value code"><?php echo $ticket['ticket_code']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Views Remaining</div>
                        <div class="info-value highlight"><?php echo $ticket['max_streaming_views'] - $ticket['streaming_views']; ?> views</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Valid Until</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime('+30 days', strtotime($ticket['purchase_date']))); ?></div>
                    </div>
                </div>
                
                <?php if ($ticket['streaming_views'] >= $ticket['max_streaming_views'] - 1): ?>
                    <div class="warning-message">
                        ⚠️ This is your last viewing. After this, you'll need to purchase a new ticket.
                    </div>
                <?php endif; ?>
                
                <div class="terms-section">
                    <p><i>📝</i> Terms of Streaming:</p>
                    <ul class="terms-list">
                        <li>You have <?php echo $ticket['max_streaming_views']; ?> total views for this ticket</li>
                        <li>Each view counts when you start streaming</li>
                        <li>Ticket expires 30 days after purchase</li>
                        <li>Do not share your screen or record the content</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>