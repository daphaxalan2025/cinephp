<?php
// user/favorites.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Handle add to favorites
if (isset($_GET['add'])) {
    $movie_id = $_GET['add'];
    
    // Check if movie exists and is allowed for user's age group
    $stmt = $pdo->prepare("SELECT rating FROM movies WHERE id = ?");
    $stmt->execute([$movie_id]);
    $movie = $stmt->fetch();
    
    if ($movie) {
        // Check age restriction
        $allowed = true;
        if ($user['account_type'] == 'kid' && !in_array($movie['rating'], ['G', 'PG'])) {
            $allowed = false;
        } elseif ($user['account_type'] == 'teen' && !in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
            $allowed = false;
        }
        
        if ($allowed) {
            // Check if already in favorites
            $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
            $check->execute([$user['id'], $movie_id]);
            
            if (!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO favorites (user_id, movie_id) VALUES (?, ?)");
                $stmt->execute([$user['id'], $movie_id]);
                setFlash('Added to favorites', 'success');
            }
        }
    }
    header('Location: favorites.php');
    exit;
}

// Handle remove from favorites
if (isset($_GET['remove'])) {
    $movie_id = $_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND movie_id = ?");
    $stmt->execute([$user['id'], $movie_id]);
    setFlash('Removed from favorites', 'success');
    header('Location: favorites.php');
    exit;
}

// Handle toggle (add/remove)
if (isset($_GET['toggle'])) {
    $movie_id = $_GET['toggle'];
    
    // Check if already in favorites
    $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
    $check->execute([$user['id'], $movie_id]);
    
    if ($check->fetch()) {
        // Remove
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND movie_id = ?");
        $stmt->execute([$user['id'], $movie_id]);
        setFlash('Removed from favorites', 'success');
    } else {
        // Add
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, movie_id) VALUES (?, ?)");
        $stmt->execute([$user['id'], $movie_id]);
        setFlash('Added to favorites', 'success');
    }
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'favorites.php');
    exit;
}

// Get user's favorites with movie details
$stmt = $pdo->prepare("
    SELECT f.*, m.*,
           (SELECT COUNT(*) FROM screenings WHERE movie_id = m.id AND show_date >= CURDATE()) as upcoming_screenings
    FROM favorites f
    JOIN movies m ON f.movie_id = m.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$user['id']]);
$favorites = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .favorite-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        .favorite-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(0,255,255,0.3);
        }
        .favorite-poster {
            height: 350px;
            overflow: hidden;
            position: relative;
        }
        .favorite-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .favorite-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .btn-remove {
            background: rgba(255,68,68,0.9);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s;
            border: 1px solid #ff4444;
        }
        .btn-remove:hover {
            background: #ff4444;
            transform: scale(1.1);
        }
        .favorite-info {
            padding: 20px;
        }
        .favorite-info h3 {
            color: #00ffff;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        .movie-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            color: #888;
            font-size: 0.9rem;
        }
        .rating-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        .rating-G { background: #44ff44; color: #000; }
        .rating-PG { background: #ffff44; color: #000; }
        .rating-PG-13 { background: #ff8844; color: #000; }
        .rating-R { background: #ff4444; color: #fff; }
        .screenings-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 10px 0;
            color: #00ffff;
        }
        .favorite-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-view {
            flex: 1;
            padding: 10px;
            background: #00ffff;
            color: #000;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        .btn-view:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            margin-top: 30px;
        }
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <a href="movies.php">Movies</a>
                <a href="favorites.php" class="active">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>My Favorite Movies</h1>
        
        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-icon">❤️</div>
                <h2>No favorites yet</h2>
                <p style="color:#888; margin-bottom:20px;">Browse movies and add them to your favorites list!</p>
                <a href="movies.php" class="btn btn-primary">Browse Movies</a>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $movie): ?>
                    <div class="favorite-card">
                        <div class="favorite-poster">
                            <?php if ($movie['poster']): ?>
                                <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>">
                            <?php else: ?>
                                <div style="width:100%; height:100%; background:#000; display:flex; align-items:center; justify-content:center; color:#666;">
                                    No Poster
                                </div>
                            <?php endif; ?>
                            
                            <div class="favorite-overlay">
                                <a href="?remove=<?php echo $movie['id']; ?>" 
                                   class="btn-remove" 
                                   onclick="return confirm('Remove from favorites?')">✕</a>
                            </div>
                        </div>
                        
                        <div class="favorite-info">
                            <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                            
                            <div class="movie-meta">
                                <span class="rating-badge rating-<?php echo $movie['rating']; ?>">
                                    <?php echo $movie['rating']; ?>
                                </span>
                                <span>⏱️ <?php echo $movie['duration']; ?> min</span>
                                <span>🎭 <?php echo htmlspecialchars($movie['genre']); ?></span>
                            </div>
                            
                            <?php if ($movie['upcoming_screenings'] > 0): ?>
                                <div class="screenings-badge">
                                    🎬 <?php echo $movie['upcoming_screenings']; ?> upcoming screenings
                                </div>
                            <?php endif; ?>
                            
                            <div class="favorite-actions">
                                <a href="movie_detail.php?id=<?php echo $movie['id']; ?>" class="btn-view">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>