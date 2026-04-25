<?php
// user/movies.php - UPDATED: Uses profile_type for filtering, removed parent_id reference
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// If user is not found (e.g., deleted from DB but session still active), force logout
if (!$user) {
    session_destroy();
    setFlash('Your account was not found. Please login again.', 'error');
    header('Location: ../auth/login.php');
    exit;
}

// Auto-archive expired screenings
autoArchiveExpiredScreenings();

// ============ USE PROFILE_TYPE FOR FILTERING ============
$profile_type = $_SESSION['profile_type'] ?? $user['account_type'] ?? 'adult';

// ============ HANDLE SEARCH & FILTERS ============
$search_query = $_GET['search'] ?? '';
$genre_filter = $_GET['genre'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

// Rating restriction based on PROFILE TYPE (NOT parent_id)
$rating_restriction = '';
if ($profile_type == 'kid') {
    $rating_restriction = "rating IN ('G', 'PG')";
} elseif ($profile_type == 'teen') {
    $rating_restriction = "rating IN ('G', 'PG', 'PG-13')";
} else {
    $rating_restriction = "1=1";
}

// Build WHERE clause
$where_conditions = [];
$params = [];

// Rating restriction
$where_conditions[] = $rating_restriction;

// Search by title or description
if (!empty($search_query)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ? OR genre LIKE ?)";
    $like = "%{$search_query}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Filter by genre
if (!empty($genre_filter) && $genre_filter != 'all') {
    $where_conditions[] = "genre = ?";
    $params[] = $genre_filter;
}

// Filter by rating
if (!empty($rating_filter) && $rating_filter != 'all') {
    $where_conditions[] = "rating = ?";
    $params[] = $rating_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Execute query safely
$sql = "SELECT * FROM movies WHERE $where_clause ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();

// Get unique genres for filter dropdown
$genres = $pdo->query("SELECT DISTINCT genre FROM movies WHERE genre IS NOT NULL AND genre != '' ORDER BY genre")->fetchAll();

// Get unique ratings for filter dropdown (respecting profile type)
$ratings_sql = "SELECT DISTINCT rating FROM movies WHERE rating IS NOT NULL";
if ($profile_type == 'kid') {
    $ratings_sql .= " AND rating IN ('G', 'PG')";
} elseif ($profile_type == 'teen') {
    $ratings_sql .= " AND rating IN ('G', 'PG', 'PG-13')";
}
$ratings = $pdo->query($ratings_sql)->fetchAll();

// Get all cinemas for search
$cinemas = $pdo->query("SELECT id, name, location FROM cinemas ORDER BY name")->fetchAll();

// Handle cinema search
$search_cinema = $_GET['search_cinema'] ?? '';
$selected_cinema_id = $_GET['cinema_id'] ?? '';
$selected_movie_id = $_GET['movie_id'] ?? '';
$cinema_search_results = [];

if ($search_cinema) {
    $cinema_search_results = searchCinemas($search_cinema);
} elseif ($selected_cinema_id && $selected_movie_id) {
    $stmt = $pdo->prepare("SELECT * FROM cinemas WHERE id = ?");
    $stmt->execute([$selected_cinema_id]);
    $cinema_search_results = $stmt->fetchAll();
}

// Get screenings for selected cinema and movie
$screenings_for_search = [];
if ($selected_cinema_id && $selected_movie_id) {
    $screenings_for_search = getScreeningsByCinemaAndMovie($selected_cinema_id, $selected_movie_id);
}

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Theme Variables */
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
        
        /* Navigation */
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
        
        /* Profile Badge in Navbar */
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
        
        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .filter-title {
            color: var(--accent);
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 20px var(--accent-glow);
        }
        
        .filter-group select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-search {
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
        }
        
        .btn-search:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px var(--accent-glow);
        }
        
        .btn-clear {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.85rem;
        }
        
        .btn-clear:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(var(--accent), 0.1);
            transform: translateY(-2px);
        }
        
        .search-results-info {
            margin-top: 15px;
            padding: 10px 15px;
            background: rgba(var(--accent), 0.1);
            border-radius: 30px;
            display: inline-block;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        /* Cinema Search Section */
        .cinema-search-section {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .cinema-search-title {
            color: var(--accent);
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cinema-search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .cinema-search-group {
            flex: 1;
            min-width: 200px;
        }
        
        .cinema-search-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .cinema-search-group input:focus {
            border-color: var(--accent);
            outline: none;
        }
        
        /* Search Results */
        .search-results {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .cinema-result-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .cinema-result-card:hover {
            border-color: var(--accent);
            transform: translateX(5px);
        }
        
        .cinema-name {
            color: var(--accent);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .cinema-location {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .cinema-movies {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .movie-badge {
            display: inline-block;
            padding: 8px 16px;
            background: rgba(var(--accent), 0.1);
            border: 1px solid var(--accent);
            border-radius: 40px;
            margin: 5px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .movie-badge:hover {
            background: var(--accent);
            color: var(--bg-primary);
            transform: translateY(-2px);
        }
        
        .screenings-list {
            margin-top: 15px;
        }
        
        .screening-time {
            display: inline-block;
            padding: 5px 12px;
            background: rgba(var(--accent), 0.15);
            border-radius: 30px;
            margin: 5px;
            font-size: 0.85rem;
            color: var(--accent);
            text-decoration: none;
        }
        
        .screening-time:hover {
            background: var(--accent);
            color: var(--bg-primary);
        }
        
        /* Movies Grid */
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .movie-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
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
            border-color: var(--accent);
            box-shadow: 0 30px 60px var(--accent-glow);
        }
        
        .movie-poster {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-bottom: 1px solid var(--border-color);
            transition: transform 0.5s;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        .movie-info {
            padding: 25px;
        }
        
        .movie-title {
            color: var(--accent);
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
            background: rgba(var(--accent), 0.15);
            border: 1px solid var(--accent);
            border-radius: 30px;
            color: var(--accent);
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
            color: var(--accent);
            margin: 15px 0;
        }
        
        .screening-info {
            display: inline-block;
            padding: 5px 12px;
            background: rgba(var(--success-color), 0.1);
            border: 1px solid var(--success-color);
            border-radius: 30px;
            color: var(--success-color);
            font-size: 0.8rem;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: var(--accent);
            color: var(--bg-primary);
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
            box-shadow: 0 5px 20px var(--accent-glow);
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
            background: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px var(--accent-glow);
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
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-favorite:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(var(--accent), 0.1);
            transform: translateY(-2px);
        }
        
        /* Age-Appropriate Content Notice */
        .age-notice {
            background: rgba(var(--accent), 0.1);
            border: 1px solid var(--accent);
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        
        .age-notice .emoji {
            font-size: 1.5rem;
        }
        
        .age-notice.kid {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }
        
        .age-notice.teen {
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 16px;
            animation: slideIn 0.3s ease;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .alert-info {
            border-left: 4px solid var(--accent);
        }
        
        .alert-warning {
            border-left: 4px solid #ffaa44;
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
            margin: 30px 0;
            opacity: 0.3;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            margin-top: 30px;
        }
        
        .empty-state p:first-child {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .empty-state p:last-child {
            color: var(--text-secondary);
        }
        
        /* Highlight for search terms */
        .highlight {
            background-color: rgba(var(--accent), 0.3);
            padding: 0 2px;
            border-radius: 3px;
            font-weight: bold;
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
            
            .movies-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-actions {
                width: 100%;
            }
            
            .btn-search, .btn-clear {
                flex: 1;
                text-align: center;
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
            <h1>
                <?php 
                if ($profile_type == 'kid') {
                    echo "🎈 Kids Corner";
                } elseif ($profile_type == 'teen') {
                    echo "🎮 Teen Scene";
                } else {
                    echo "🎬 Now Showing";
                }
                ?>
            </h1>
            <div class="account-badge">
                <span>Profile:</span> 
                <strong><?php echo ucfirst($profile_type); ?></strong>
            </div>
        </div>
        
        <!-- Age-Appropriate Content Notice -->
        <div class="age-notice <?php echo $profile_type; ?>">
            <span class="emoji">
                <?php 
                if ($profile_type == 'kid') echo "🧸";
                elseif ($profile_type == 'teen') echo "🎮";
                else echo "🎬";
                ?>
            </span>
            <span>
                <?php 
                if ($profile_type == 'kid') {
                    echo "Showing G and PG rated movies only. Parental guidance is recommended for PG content.";
                } elseif ($profile_type == 'teen') {
                    echo "Showing G, PG, and PG-13 rated movies. Some content may not be suitable for younger audiences.";
                } else {
                    echo "Full access to all movie ratings. Enjoy unlimited cinematic experiences!";
                }
                ?>
            </span>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- ============ SEARCH & FILTER SECTION ============ -->
        <div class="filter-section">
            <div class="filter-title">
                🔍 Find Your Movie
            </div>
            <form method="GET" action="movies.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>🔎 Search by Title, Genre, or Description</label>
                        <input type="text" name="search" placeholder="Search movies..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>🎭 Genre</label>
                        <select name="genre">
                            <option value="all">All Genres</option>
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?php echo htmlspecialchars($genre['genre']); ?>" <?php echo $genre_filter == $genre['genre'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($genre['genre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>⭐ Rating</label>
                        <select name="rating">
                            <option value="all">All Ratings</option>
                            <?php foreach ($ratings as $rating): ?>
                                <option value="<?php echo $rating['rating']; ?>" <?php echo $rating_filter == $rating['rating'] ? 'selected' : ''; ?>>
                                    <?php echo $rating['rating']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-search">Search Movies</button>
                        <a href="movies.php" class="btn-clear">Clear All</a>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($search_query) || (!empty($genre_filter) && $genre_filter != 'all') || (!empty($rating_filter) && $rating_filter != 'all')): ?>
                <div class="search-results-info">
                    📽️ Found <strong><?php echo count($movies); ?></strong> movie(s) 
                    <?php if (!empty($search_query)): ?> matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"<?php endif; ?>
                    <?php if (!empty($genre_filter) && $genre_filter != 'all'): ?> in <strong><?php echo htmlspecialchars($genre_filter); ?></strong><?php endif; ?>
                    <?php if (!empty($rating_filter) && $rating_filter != 'all'): ?> rated <strong><?php echo htmlspecialchars($rating_filter); ?></strong><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cinema Search Section -->
        <div class="cinema-search-section">
            <div class="cinema-search-title">
                🏢 Find by Cinema
            </div>
            <form method="GET" action="movies.php" class="cinema-search-form">
                <div class="cinema-search-group">
                    <input type="text" name="search_cinema" placeholder="Search cinema by name or location..." value="<?php echo htmlspecialchars($search_cinema); ?>">
                </div>
                <button type="submit" class="btn-search">Search Cinema</button>
                <?php if ($search_cinema): ?>
                    <a href="movies.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Cinema Search Results -->
        <?php if (!empty($cinema_search_results)): ?>
            <div class="search-results">
                <div class="filter-title">
                    🏢 Cinema Results
                </div>
                <?php foreach ($cinema_search_results as $cinema): ?>
                    <div class="cinema-result-card">
                        <div class="cinema-name"><?php echo htmlspecialchars($cinema['name']); ?></div>
                        <div class="cinema-location">📍 <?php echo htmlspecialchars($cinema['location']); ?></div>
                        
                        <?php
                        // Get movies showing at this cinema
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT m.id, m.title, m.rating, m.poster 
                            FROM movies m
                            JOIN screenings s ON m.id = s.movie_id
                            WHERE s.cinema_id = ? AND s.show_date >= CURDATE() AND s.status != 'expired'
                            ORDER BY m.title
                        ");
                        $stmt->execute([$cinema['id']]);
                        $cinema_movies = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($cinema_movies)): ?>
                            <div class="cinema-movies">
                                <strong>🎬 Now Showing:</strong><br>
                                <?php foreach ($cinema_movies as $cm): ?>
                                    <a href="movie_detail.php?id=<?php echo $cm['id']; ?>" class="movie-badge">
                                        <?php echo htmlspecialchars($cm['title']); ?>
                                        <span style="font-size:0.7rem;">(<?php echo $cm['rating']; ?>)</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="cinema-movies" style="color: var(--text-secondary);">
                                No current screenings at this cinema.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Movies Grid -->
        <?php if (empty($movies)): ?>
            <div class="empty-state">
                <p>🎬 No movies found</p>
                <p>
                    <?php if (!empty($search_query) || (!empty($genre_filter) && $genre_filter != 'all') || (!empty($rating_filter) && $rating_filter != 'all')): ?>
                        Try adjusting your search filters or clear them to see all available movies.
                    <?php else: ?>
                        No movies available for your age group. Check back later for new releases.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query) || (!empty($genre_filter) && $genre_filter != 'all') || (!empty($rating_filter) && $rating_filter != 'all')): ?>
                    <a href="movies.php" class="btn-search" style="display: inline-block; margin-top: 20px;">View All Movies</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($movies as $movie): 
                    // Get screening count
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ? AND show_date >= CURDATE() AND status != 'expired'");
                    $stmt->execute([$movie['id']]);
                    $screening_count = $stmt->fetchColumn();
                    
                    // Highlight search term in title
                    $display_title = $movie['title'];
                    if (!empty($search_query)) {
                        $display_title = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="highlight">$1</span>', $movie['title']);
                    }
                ?>
                    <div class="movie-card">
                        <?php if ($movie['poster']): ?>
                            <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                 class="movie-poster">
                        <?php else: ?>
                            <div style="width: 100%; height: 400px; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center;">
                                <span style="color: var(--text-secondary);">No Poster</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="movie-info">
                            <h3 class="movie-title"><?php echo $display_title; ?></h3>
                            
                            <div class="movie-meta">
                                <span class="rating-badge"><?php echo $movie['rating']; ?></span>
                                <span>⏱️ <?php echo $movie['duration']; ?> min</span>
                                <span>🎭 <?php echo htmlspecialchars($movie['genre']); ?></span>
                            </div>
                            
                            <p class="movie-description">
                                <?php 
                                $desc = htmlspecialchars(substr($movie['description'], 0, 100)) . '...';
                                if (!empty($search_query)) {
                                    $desc = preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<span class="highlight">$1</span>', $desc);
                                }
                                echo $desc;
                                ?>
                            </p>
                            
                            <div class="price">From ₱<?php echo number_format($movie['price'], 2); ?></div>
                            
                            <?php if ($screening_count > 0): ?>
                                <div class="screening-info">
                                    🎬 <?php echo $screening_count; ?> screenings available
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