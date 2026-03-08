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
    <style>
        .player-container {
            max-width: 1000px;
            margin: 30px auto;
        }
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .player-info {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin-top: 30px;
        }
        .view-counter {
            display: inline-block;
            padding: 5px 15px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 20px;
            color: #00ffff;
            margin-bottom: 20px;
        }
        .warning-message {
            background: rgba(255,255,68,0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background: #00ffff;
            color: #000;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .back-button:hover {
            box-shadow: 0 0 20px #00ffff;
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
        <a href="purchases.php" class="back-button">← Back to My Tickets</a>
        
        <div class="player-container">
            <h1 style="color:#00ffff; margin-bottom:20px;"><?php echo htmlspecialchars($ticket['title']); ?></h1>
            
            <div class="video-wrapper">
                <iframe src="<?php echo htmlspecialchars($streaming_url); ?>" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            
            <div class="player-info">
                <div class="view-counter">
                    🎥 View <?php echo $ticket['streaming_views']; ?> of <?php echo $ticket['max_streaming_views']; ?>
                </div>
                
                <h2 style="color:#00ffff; margin-bottom:15px;">Streaming Information</h2>
                
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:20px;">
                    <div>
                        <div style="color:#888;">Ticket Code</div>
                        <div style="color:#fff; font-family:monospace;"><?php echo $ticket['ticket_code']; ?></div>
                    </div>
                    <div>
                        <div style="color:#888;">Views Remaining</div>
                        <div style="color:#00ffff;"><?php echo $ticket['max_streaming_views'] - $ticket['streaming_views']; ?> views</div>
                    </div>
                    <div>
                        <div style="color:#888;">Valid Until</div>
                        <div style="color:#fff;"><?php echo date('M d, Y', strtotime('+30 days', strtotime($ticket['purchase_date']))); ?></div>
                    </div>
                </div>
                
                <?php if ($ticket['streaming_views'] >= $ticket['max_streaming_views'] - 1): ?>
                    <div class="warning-message">
                        ⚠️ This is your last viewing. After this, you'll need to purchase a new ticket.
                    </div>
                <?php endif; ?>
                
                <div style="color:#888; font-size:0.9rem; margin-top:20px;">
                    <p>📝 Terms of Streaming:</p>
                    <ul style="margin-left:20px; margin-top:10px;">
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