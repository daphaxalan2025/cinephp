<?php
// user/movies.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Get movies based on account type
$rating_filter = '';
if ($user['account_type'] == 'kid') {
    $rating_filter = "WHERE rating IN ('G', 'PG')";
} elseif ($user['account_type'] == 'teen') {
    $rating_filter = "WHERE rating IN ('G', 'PG', 'PG-13')";
}

$sql = "SELECT * FROM movies $rating_filter ORDER BY created_at DESC";
$movies = $pdo->query($sql)->fetchAll();

// Get screening counts
$screening_counts = [];
foreach ($movies as $movie) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ? AND show_date >= CURDATE()");
    $stmt->execute([$movie['id']]);
    $screening_counts[$movie['id']] = $stmt->fetchColumn();
}

// Check if user has parent (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies - CinemaTicket</title>
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
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .account-badge {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 40px;
            padding: 8px 20px;
            font-size: 0.9rem;
        }
        
        .account-badge span {
            color: var(--text-secondary);
            margin-right: 5px;
        }
        
        .account-badge strong {
            color: var(--red);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Parent Alert */
        .parent-alert {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease;
        }
        
        .parent-alert.info {
            border-left: 4px solid var(--red);
        }
        
        .parent-alert.warning {
            border-left: 4px solid #ff4444;
        }
        
        .parent-icon {
            font-size: 1.5rem;
        }
        
        .parent-message {
            flex: 1;
            color: var(--text-primary);
        }
        
        .parent-message strong {
            color: var(--red);
        }
        
        /* Movies Grid */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .movie-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }
        
        .movie-card::before {
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
        
        .movie-card:hover {
            transform: translateY(-10px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 30px 60px rgba(229, 9, 20, 0.2);
        }
        
        .movie-poster {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            transition: transform 0.5s;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        .movie-info {
            padding: 25px;
        }
        
        .movie-title {
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .movie-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 3px 8px;
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .movie-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--red);
            margin: 15px 0;
        }
        
        .screening-info {
            display: inline-block;
            padding: 5px 12px;
            background: rgba(68, 255, 68, 0.1);
            border: 1px solid #44ff44;
            border-radius: 30px;
            color: #44ff44;
            font-size: 0.8rem;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 12px 20px;
            border-radius: 40px;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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
        
        .btn-favorite {
            display: inline-block;
            width: 100%;
            text-align: center;
            padding: 10px;
            margin-top: 10px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-favorite:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 16px;
            animation: slideIn 0.3s ease;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
        }
        
        .alert-info {
            border-left: 4px solid var(--red);
        }
        
        .alert-warning {
            border-left: 4px solid #ff4444;
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
            margin: 30px 0;
            opacity: 0.3;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 32px;
            margin-top: 30px;
        }
        
        .empty-state p:first-child {
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 15px;
        }
        
        .empty-state p:last-child {
            color: var(--text-secondary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .movies-grid {
                grid-template-columns: 1fr;
            }
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
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="page-header">
            <h1>
                <?php 
                if ($user['account_type'] == 'kid') echo "Kids Corner";
                elseif ($user['account_type'] == 'teen') echo "Teen Scene";
                else echo "Now Showing";
                ?>
            </h1>
            <div class="account-badge">
                <span>Account:</span> 
                <strong><?php echo $user['account_type']; ?></strong>
            </div>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Parent notification for kids/teens -->
        <?php if ($user['account_type'] == 'kid' || $user['account_type'] == 'teen'): ?>
            <?php if ($parent): ?>
                <div class="parent-alert info">
                    <span class="parent-icon">👤</span>
                    <div class="parent-message">
                        Linked to parent: <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                        <br>Your ticket purchases will be sent to them for approval.
                    </div>
                </div>
            <?php else: ?>
                <div class="parent-alert warning">
                    <span class="parent-icon">⚠️</span>
                    <div class="parent-message">
                        <strong>No parent linked.</strong> Please ask an adult to create a linked account for you.
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($movies)): ?>
            <div class="empty-state">
                <p>No movies available for your age group</p>
                <p>Check back later for new releases or ask a parent to update your account settings.</p>
            </div>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($movies as $movie): ?>
                    <div class="movie-card">
                        <?php if ($movie['poster']): ?>
                            <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                 class="movie-poster">
                        <?php else: ?>
                            <div style="width: 100%; height: 400px; background: var(--deep-gray); display: flex; align-items: center; justify-content: center;">
                                <span style="color: var(--text-secondary);">No Poster</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="movie-info">
                            <h3 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                            
                            <div class="movie-meta">
                                <span class="rating-badge"><?php echo $movie['rating']; ?></span>
                                <span>⏱️ <?php echo $movie['duration']; ?> min</span>
                                <span>🎭 <?php echo htmlspecialchars($movie['genre']); ?></span>
                            </div>
                            
                            <p class="movie-description">
                                <?php echo htmlspecialchars(substr($movie['description'], 0, 100)) . '...'; ?>
                            </p>
                            
                            <div class="price">From $<?php echo number_format($movie['price'], 2); ?></div>
                            
                            <?php if ($screening_counts[$movie['id']] > 0): ?>
                                <div class="screening-info">
                                    🎬 <?php echo $screening_counts[$movie['id']]; ?> screenings available
                                </div>
                            <?php endif; ?>
                            
                            <a href="movie_detail.php?id=<?php echo $movie['id']; ?>" class="btn-primary">View Details</a>
                            
                            <!-- Add to favorites button -->
                            <a href="favorites.php?add=<?php echo $movie['id']; ?>" class="btn-favorite">❤️ Add to Favorites</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>