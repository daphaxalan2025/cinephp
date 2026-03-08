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
    <style>
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .date-group {
            margin-bottom: 40px;
        }
        .date-title {
            color: #00ffff;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00ffff;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .history-item {
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            transition: all 0.3s;
        }
        .history-item:hover {
            transform: translateX(5px);
            box-shadow: 0 0 15px rgba(0,255,255,0.3);
        }
        .history-poster {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #00ffff;
        }
        .history-info {
            flex: 1;
        }
        .history-info h3 {
            color: #00ffff;
            margin-bottom: 10px;
        }
        .history-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            color: #888;
            font-size: 0.9rem;
        }
        .watch-time {
            color: #888;
            font-size: 0.9rem;
        }
        .history-actions {
            display: flex;
            gap: 10px;
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
            border: 1px solid #00ffff;
            color: #00ffff;
        }
        .btn-icon:hover {
            background: #00ffff;
            color: #000;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        @media (max-width:768px) {
            .history-item {
                flex-direction: column;
                text-align: center;
            }
            .history-actions {
                justify-content: center;
            }
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
                <a href="?clear=1" class="btn btn-danger" 
                   onclick="return confirm('Clear your entire watch history?')">Clear All</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="empty-icon">📺</div>
                <h2>No watch history yet</h2>
                <p style="color:#888; margin-bottom:20px;">Movies you watch online will appear here</p>
                <a href="movies.php" class="btn btn-primary">Browse Movies</a>
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
                                    <div style="width:80px; height:120px; background:#000; border:1px solid #00ffff; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#666;">
                                        No Poster
                                    </div>
                                <?php endif; ?>
                                
                                <div class="history-info">
                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                    
                                    <div class="history-meta">
                                        <span class="rating-badge rating-<?php echo $item['rating']; ?>">
                                            <?php echo $item['rating']; ?>
                                        </span>
                                        <span>⏱️ <?php echo $item['duration']; ?> min</span>
                                        <span>🎭 <?php echo htmlspecialchars($item['genre']); ?></span>
                                    </div>
                                    
                                    <div class="watch-time">
                                        Watched at: <?php echo date('h:i A', strtotime($item['watched_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="history-actions">
                                    <a href="movie_detail.php?id=<?php echo $item['movie_id']; ?>" 
                                       class="btn-icon" title="View Movie">▶️</a>
                                    <a href="?remove=<?php echo $item['id']; ?>" 
                                       class="btn-icon" 
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