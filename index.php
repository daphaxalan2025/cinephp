<?php
// index.php
require_once 'includes/functions.php';
$user = getCurrentUser();

// Get featured movies (latest 3 movies)
$pdo = getDB();
$featured_movies = $pdo->query("
    SELECT * FROM movies 
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CinemaTicket - Premium Movie Experience</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.03"><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="%23e50914" stroke-width="0.5"/><circle cx="50" cy="50" r="30" fill="none" stroke="%23e50914" stroke-width="0.5"/></svg>') repeat;
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
        
        .username-badge {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 30px;
            padding: 4px 12px;
            font-size: 0.8rem;
            color: var(--red);
            margin-left: 5px;
        }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Hero Section */
        .hero-section {
            text-align: center;
            padding: 100px 20px;
            position: relative;
            overflow: hidden;
            border-radius: 32px;
            margin-bottom: 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--red), var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .hero-section::after {
            content: 'CINEMA';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 8rem;
            font-weight: 900;
            opacity: 0.03;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 5px;
            position: relative;
            z-index: 1;
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            z-index: 1;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 16px 40px;
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
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.5);
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-secondary {
            background: transparent;
            color: #fff;
            border: 1px solid var(--red);
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 16px 40px;
            border-radius: 40px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: rgba(229, 9, 20, 0.1);
            border-color: var(--red);
            color: var(--red);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.2);
        }
        
        /* Features Section */
        .features-section {
            margin: 80px 0;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 50px;
            text-transform: uppercase;
            letter-spacing: 3px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background: var(--red);
            margin: 15px auto 0;
            border-radius: 3px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .feature-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(229, 9, 20, 0.4);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
        }
        
        .feature-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(229, 9, 20, 0.3));
        }
        
        .feature-title {
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .feature-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        /* Featured Movies */
        .movies-section {
            margin: 80px 0;
        }
        
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .movie-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        
        .movie-card:hover {
            transform: translateY(-10px);
            border-color: rgba(229, 9, 20, 0.4);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
        }
        
        .movie-poster {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .movie-info {
            padding: 20px;
        }
        
        .movie-title {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .movie-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        
        .movie-price {
            color: var(--red);
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .movie-btn {
            display: inline-block;
            padding: 10px 20px;
            background: transparent;
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .movie-btn:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 60px 0;
            opacity: 0.3;
        }
        
        /* Alert */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-success {
            border-left-color: #44ff44;
            color: #44ff44;
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
        @media (max-width: 1024px) {
            .features-grid,
            .movies-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                max-width: 300px;
            }
            
            .features-grid,
            .movies-grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <?php if (isLoggedIn()): ?>
                    <?php if ($_SESSION['account_type'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Dashboard</a>
                    <?php elseif ($_SESSION['account_type'] == 'staff'): ?>
                        <a href="staff/dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a href="user/movies.php">Movies</a>
                    <?php endif; ?>
                    <a href="user/purchases.php">My Tickets</a>
                    <a href="user/profile.php">Profile</a>
                    <a href="auth/logout.php">
                        Logout
                        <span class="username-badge"><?php echo htmlspecialchars($user['username']); ?></span>
                    </a>
                <?php else: ?>
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Hero Section -->
        <div class="hero-section">
            <h1 class="hero-title">Experience Cinema</h1>
            <p class="hero-subtitle">
                Book your movie tickets online with interactive seat selection. 
                The ultimate cinematic experience awaits you.
            </p>
            
            <?php if (!isLoggedIn()): ?>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn-primary">Get Started</a>
                    <a href="auth/login.php" class="btn-secondary">Login</a>
                </div>
            <?php else: ?>
                <div class="hero-buttons">
                    <a href="user/movies.php" class="btn-primary">Browse Movies</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Features Section -->
        <div class="features-section">
            <h2 class="section-title">The Ultimate Experience</h2>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎬</div>
                    <h3 class="feature-title">Wide Selection</h3>
                    <p class="feature-description">
                        Choose from the latest blockbusters, indie films, and classic cinema favorites.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🪑</div>
                    <h3 class="feature-title">Choose Your Seat</h3>
                    <p class="feature-description">
                        Interactive seat selection with real-time availability and premium options.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🎟️</div>
                    <h3 class="feature-title">Digital Tickets</h3>
                    <p class="feature-description">
                        Easy verification with QR codes. No printing required - show on your phone.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Featured Movies -->
        <?php if (!empty($featured_movies)): ?>
            <div class="movies-section">
                <h2 class="section-title">Now Showing</h2>
                
                <div class="movies-grid">
                    <?php foreach ($featured_movies as $movie): ?>
                        <div class="movie-card">
                            <?php if ($movie['poster']): ?>
                                <img src="uploads/posters/<?php echo $movie['poster']; ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                     class="movie-poster">
                            <?php else: ?>
                                <div style="height: 400px; background: var(--deep-gray); display: flex; align-items: center; justify-content: center;">
                                    <span style="color: var(--text-secondary);">No Poster</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="movie-info">
                                <h3 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                                
                                <div class="movie-meta">
                                    <span><?php echo $movie['duration']; ?> min</span>
                                    <span>•</span>
                                    <span><?php echo $movie['rating']; ?></span>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($movie['genre']); ?></span>
                                </div>
                                
                                <div class="movie-price">$<?php echo number_format($movie['price'], 2); ?></div>
                                
                                <?php if (isLoggedIn() && $_SESSION['account_type'] == 'user'): ?>
                                    <a href="user/screenings.php?movie_id=<?php echo $movie['id']; ?>" class="movie-btn">Book Now</a>
                                <?php elseif (!isLoggedIn()): ?>
                                    <a href="auth/login.php" class="movie-btn">Login to Book</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Call to Action -->
        <div style="text-align: center; margin: 60px 0;">
            <h2 style="font-size: 2rem; color: #fff; margin-bottom: 20px;">Ready for the show?</h2>
            <?php if (!isLoggedIn()): ?>
                <a href="auth/register.php" class="btn-primary" style="padding: 16px 60px;">Join Now</a>
            <?php else: ?>
                <a href="user/movies.php" class="btn-primary" style="padding: 16px 60px;">Book Tickets</a>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/js/script.js"></script>
</body>
</html>