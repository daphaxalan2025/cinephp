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
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Favorites Grid */
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .favorite-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        
        .favorite-card::before {
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
        
        .favorite-card:hover {
            transform: translateY(-10px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 30px 60px rgba(229, 9, 20, 0.2);
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
            transition: transform 0.5s;
        }
        
        .favorite-card:hover .favorite-poster img {
            transform: scale(1.05);
        }
        
        .favorite-overlay {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
        }
        
        .btn-remove {
            background: rgba(229, 9, 20, 0.9);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s;
            border: 2px solid var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
            backdrop-filter: blur(5px);
        }
        
        .btn-remove:hover {
            background: var(--red);
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.5);
        }
        
        .favorite-info {
            padding: 20px;
        }
        
        .favorite-info h3 {
            color: var(--red);
            margin-bottom: 10px;
            font-size: 1.3rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        .movie-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
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
        
        .screenings-badge {
            display: inline-block;
            padding: 6px 15px;
            background: rgba(68, 255, 68, 0.1);
            border: 1px solid #44ff44;
            border-radius: 40px;
            font-size: 0.8rem;
            margin: 10px 0;
            color: #44ff44;
            font-weight: 500;
        }
        
        .favorite-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-view {
            flex: 1;
            padding: 12px;
            background: transparent;
            border: 1px solid var(--red);
            color: var(--red);
            text-decoration: none;
            border-radius: 40px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-view:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
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
            content: '❤️';
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
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .favorites-grid {
                grid-template-columns: 1fr;
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
        <h1>My Favorites</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-icon">❤️</div>
                <h2>No favorites yet</h2>
                <p>Browse our collection and add movies to your favorites list!</p>
                <a href="movies.php" class="btn-primary">Browse Movies</a>
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
                                <div style="width:100%; height:100%; background:var(--deep-gray); display:flex; align-items:center; justify-content:center;">
                                    <span style="color: var(--text-secondary);">No Poster</span>
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
                                <span class="rating-badge rating-<?php echo str_replace('-', '', $movie['rating']); ?>">
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