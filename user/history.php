<?php
// user/history.php - FIXED: Consistent navbar matching movies.php and profile.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$profile_type = $_SESSION['profile_type'] ?? 'adult';

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

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch History - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Theme Variables - Matching movies.php */
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
            --danger-color: #ff4444;
            --danger-glow: 0 0 20px rgba(255, 68, 68, 0.3);
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
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(229, 9, 20, 0.1);
            --danger-color: #cc0000;
            --danger-glow: 0 0 20px rgba(204, 0, 0, 0.3);
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
            --glass-bg: rgba(26, 26, 58, 0.7);
            --glass-border: rgba(255, 0, 255, 0.2);
            --danger-color: #ff00ff;
            --danger-glow: 0 0 20px rgba(255, 0, 255, 0.5);
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
            --glass-bg: rgba(10, 26, 10, 0.7);
            --glass-border: rgba(0, 255, 0, 0.2);
            --danger-color: #ff0000;
            --danger-glow: 0 0 20px rgba(255, 0, 0, 0.5);
            --success-color: #00ff00;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, var(--accent) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, var(--accent) 0%, transparent 50%);
            opacity: 0.03;
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
        
        /* Navigation - Matching movies.php style */
        .navbar {
            background: rgba(var(--bg-secondary), 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
            position: relative;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .logo:hover {
            text-shadow: var(--accent-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 8px;
            font-size: 1.2rem;
            filter: drop-shadow(0 0 5px var(--accent));
        }
        
        .nav-links {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
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
        
        .nav-links a:hover {
            color: var(--accent);
        }
        
        .nav-links a:hover::after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--accent);
        }
        
        .nav-links a.active::after {
            width: 60%;
        }
        
        /* Profile Badge in Navbar - Matching movies.php */
        .profile-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(var(--accent), 0.15);
            padding: 6px 15px;
            border-radius: 40px;
            margin-left: 10px;
            font-size: 0.85rem;
        }
        
        .profile-badge .profile-name {
            font-weight: 600;
            color: var(--accent);
        }
        
        .profile-badge .profile-switch {
            color: var(--text-primary);
            text-decoration: none;
            padding: 4px 10px;
            background: rgba(var(--accent), 0.2);
            border-radius: 30px;
            transition: all 0.3s;
            font-size: 0.7rem;
        }
        
        .profile-badge .profile-switch:hover {
            background: var(--accent);
            color: var(--bg-primary);
        }
        
        /* Main Container */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* Page Header - Matching movies.php */
        .page-header {
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
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .account-badge {
            background: rgba(var(--accent), 0.15);
            border: 1px solid var(--accent);
            border-radius: 40px;
            padding: 8px 20px;
            font-size: 0.9rem;
        }
        
        .account-badge span {
            color: var(--text-secondary);
            margin-right: 5px;
        }
        
        .account-badge strong {
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .btn-danger {
            background: transparent;
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
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
            background: var(--danger-color);
            color: var(--bg-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px var(--danger-glow);
        }
        
        /* Date Groups */
        .date-group {
            margin-bottom: 50px;
        }
        
        .date-title {
            color: var(--accent);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
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
            background: var(--accent);
        }
        
        /* History List */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .history-item {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
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
            border-color: var(--accent);
            box-shadow: 0 10px 30px var(--accent-glow);
        }
        
        .history-poster {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .history-item:hover .history-poster {
            border-color: var(--accent);
            transform: scale(1.05);
        }
        
        .history-info {
            flex: 1;
        }
        
        .history-info h3 {
            color: var(--accent);
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
            border: 1px solid var(--success-color);
            color: var(--success-color);
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
            border: 1px solid var(--accent);
            color: var(--accent);
        }
        
        .watch-time {
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .watch-time i {
            color: var(--accent);
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
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .btn-icon:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(var(--accent), 0.1);
            transform: scale(1.1);
        }
        
        .btn-icon.remove:hover {
            border-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
            filter: drop-shadow(0 0 20px var(--accent-glow));
        }
        
        .empty-state h2 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 15px 40px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px var(--accent-glow);
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
            background: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px var(--accent-glow);
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
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .nav-links a {
                padding: 5px 8px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 1024px) {
            .nav-container {
                padding: 0 15px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .profile-badge {
                margin-left: 0;
                margin-top: 5px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
        <div class="page-header">
            <h1>My Watch History</h1>
            <div class="account-badge">
                <span>Profile:</span> 
                <strong><?php echo ucfirst($profile_type); ?></strong>
            </div>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if (!empty($history)): ?>
            <div class="history-header">
                <div></div>
                <a href="?clear=1" class="btn-danger" 
                   onclick="return confirm('Clear your entire watch history?')">Clear All</a>
            </div>
        <?php endif; ?>
        
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
                                    <div style="width:80px; height:120px; background:var(--bg-tertiary); border:2px solid var(--border-color); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
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
                                        <?php if ($item['completed']): ?>
                                            <span style="color: #44ff44; margin-left: 10px;">✓ Completed</span>
                                        <?php endif; ?>
                                        <?php if ($item['watch_duration'] > 0): ?>
                                            <span style="color: var(--text-secondary); margin-left: 10px;">
                                                (Watched: <?php echo floor($item['watch_duration'] / 60); ?> min)
                                            </span>
                                        <?php endif; ?>
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