<?php
// user/history.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Handle clear history
if (isset($_GET['clear'])) {
    $stmt = $pdo->prepare("DELETE FROM watch_history WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    setFlash('Watch history cleared', 'success');
    header('Location: history.php');
    exit;
}

// Handle remove single item
if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM watch_history WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    setFlash('Item removed from history', 'success');
    header('Location: history.php');
    exit;
}

// Get user's watch history
$stmt = $pdo->prepare("
    SELECT h.*, m.title, m.poster, m.duration, m.rating, m.genre
    FROM watch_history h
    JOIN movies m ON h.movie_id = m.id
    WHERE h.user_id = ?
    ORDER BY h.watched_at DESC
");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();

// Group by date
$grouped_history = [];
foreach ($history as $item) {
    $date = date('Y-m-d', strtotime($item['watched_at']));
    if (!isset($grouped_history[$date])) {
        $grouped_history[$date] = [];
    }
    $grouped_history[$date][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch History - CinemaTicket</title>
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
        
        /* Header */
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        h1 {
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
        
        .btn-danger {
            background: transparent;
            border: 1px solid #ff4444;
            color: #ff4444;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 12px 24px;
            border-radius: 40px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            background: #ff4444;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 68, 68, 0.3);
        }
        
        /* Date Groups */
        .date-group {
            margin-bottom: 50px;
        }
        
        .date-title {
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(229, 9, 20, 0.2);
            position: relative;
            font-family: 'Montserrat', sans-serif;
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
        
        /* History List */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .history-item {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .history-item::before {
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
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .history-item:hover {
            transform: translateX(5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.15);
        }
        
        .history-poster {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid rgba(229, 9, 20, 0.3);
            transition: all 0.3s;
        }
        
        .history-item:hover .history-poster {
            border-color: var(--red);
            transform: scale(1.05);
        }
        
        .history-info {
            flex: 1;
        }
        
        .history-info h3 {
            color: var(--red);
            margin-bottom: 8px;
            font-size: 1.2rem;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
        }
        
        .history-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }
        
        .rating-badge {
            padding: 3px 10px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
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
        
        .watch-time {
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .watch-time i {
            color: var(--red);
        }
        
        .history-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .btn-icon:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: scale(1.1);
        }
        
        .btn-icon.remove:hover {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '📺';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 8rem;
            opacity: 0.03;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(229, 9, 20, 0.3));
        }
        
        .empty-state h2 {
            font-size: 2rem;
            color: #fff;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 15px 40px;
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
        
        /* Alert */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 16px;
            animation: slideIn 0.3s ease;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-left: 4px solid var(--red);
            color: var(--text-primary);
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
            
            .history-item {
                flex-direction: column;
                text-align: center;
                padding: 25px;
            }
            
            .history-actions {
                justify-content: center;
                margin-top: 10px;
            }
            
            .history-meta {
                justify-content: center;
            }
            
            .empty-state {
                padding: 60px 20px;
            }
            
            .empty-state h2 {
                font-size: 1.5rem;
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
                <a href="history.php" class="active">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="history-header">
            <h1>My Watch History</h1>
            <?php if (!empty($history)): ?>
                <a href="?clear=1" class="btn-danger" 
                   onclick="return confirm('Clear your entire watch history?')">Clear All</a>
            <?php endif; ?>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">📺</div>
                <h2>No watch history yet</h2>
                <p>Movies you watch online will appear here. Start exploring our collection!</p>
                <a href="movies.php" class="btn-primary">Browse Movies</a>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_history as $date => $items): ?>
                <div class="date-group">
                    <h2 class="date-title"><?php echo date('l, F d, Y', strtotime($date)); ?></h2>
                    
                    <div class="history-list">
                        <?php foreach ($items as $item): ?>
                            <div class="history-item">
                                <?php if ($item['poster']): ?>
                                    <img src="../uploads/posters/<?php echo $item['poster']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                         class="history-poster">
                                <?php else: ?>
                                    <div style="width:80px; height:120px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
                                        No Poster
                                    </div>
                                <?php endif; ?>
                                
                                <div class="history-info">
                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                    
                                    <div class="history-meta">
                                        <span class="rating-badge rating-<?php echo str_replace('-', '', $item['rating']); ?>">
                                            <?php echo $item['rating']; ?>
                                        </span>
                                        <span>⏱️ <?php echo $item['duration']; ?> min</span>
                                        <span>🎭 <?php echo htmlspecialchars($item['genre']); ?></span>
                                    </div>
                                    
                                    <div class="watch-time">
                                        <i>⏰</i> Watched at: <?php echo date('h:i A', strtotime($item['watched_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="history-actions">
                                    <a href="movie_detail.php?id=<?php echo $item['movie_id']; ?>" 
                                       class="btn-icon" title="View Movie">▶️</a>
                                    <a href="?remove=<?php echo $item['id']; ?>" 
                                       class="btn-icon remove" 
                                       onclick="return confirm('Remove this from history?')"
                                       title="Remove">✕</a>
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