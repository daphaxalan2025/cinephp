<?php
// admin/movies.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];
$success = '';

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Get poster filename to delete
        $stmt = $pdo->prepare("SELECT poster FROM movies WHERE id = ?");
        $stmt->execute([$id]);
        $movie = $stmt->fetch();
        
        if ($movie && $movie['poster']) {
            $poster_path = '../uploads/posters/' . $movie['poster'];
            if (file_exists($poster_path)) {
                unlink($poster_path); // Delete the poster file
            }
        }
        
        // Check if movie has screenings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete movie with existing screenings', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Movie deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: movies.php');
    exit;
}

// ============ HANDLE ADD/EDIT ============
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = intval($_POST['duration'] ?? 120);
    $rating = $_POST['rating'] ?? 'PG';
    $genre = trim($_POST['genre'] ?? '');
    $price = floatval($_POST['price'] ?? 12.50);
    $trailer_url = trim($_POST['trailer_url'] ?? '');
    $streaming_url = trim($_POST['streaming_url'] ?? '');
    $release_date = $_POST['release_date'] ?? date('Y-m-d');
    $movie_id = $_POST['movie_id'] ?? '';
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required';
    if (strlen($title) < 2) $errors[] = 'Title must be at least 2 characters';
    if (empty($description)) $errors[] = 'Description is required';
    if ($duration < 1 || $duration > 300) $errors[] = 'Duration must be between 1 and 300 minutes';
    if (empty($rating)) $errors[] = 'Rating is required';
    if (empty($genre)) $errors[] = 'Genre is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    // Handle poster upload
    $poster_filename = $_POST['current_poster'] ?? '';
    
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['poster']['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        } elseif ($_FILES['poster']['size'] > $max_size) {
            $errors[] = 'File too large. Maximum size is 5MB.';
        } else {
            // Generate unique filename
            $ext = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $poster_filename = uniqid() . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/posters/' . $poster_filename;
            
            if (!move_uploaded_file($_FILES['poster']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload poster.';
            }
        }
    } elseif (empty($poster_filename) && !$movie_id) {
        $errors[] = 'Poster is required for new movies';
    }
    
    // Format YouTube URL to embed format
    if (!empty($trailer_url)) {
        if (strpos($trailer_url, 'youtube.com/watch?v=') !== false) {
            parse_str(parse_url($trailer_url, PHP_URL_QUERY), $params);
            $video_id = $params['v'] ?? '';
            if ($video_id) {
                $trailer_url = 'https://www.youtube.com/embed/' . $video_id;
            }
        } elseif (strpos($trailer_url, 'youtu.be/') !== false) {
            $video_id = substr($trailer_url, strrpos($trailer_url, '/') + 1);
            $trailer_url = 'https://www.youtube.com/embed/' . $video_id;
        }
    }
    
    // Check for duplicate title
    try {
        $check = $pdo->prepare("SELECT id FROM movies WHERE title = ? AND id != ?");
        $check->execute([$title, $movie_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Movie with this title already exists';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            if ($movie_id) {
                // Update
                $sql = "UPDATE movies SET title=?, description=?, duration=?, rating=?, genre=?, price=?, poster=?, trailer_url=?, streaming_url=?, release_date=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$title, $description, $duration, $rating, $genre, $price, $poster_filename, $trailer_url, $streaming_url, $release_date, $movie_id]);
                
                // Delete old poster if new one was uploaded
                if ($result && isset($_FILES['poster']) && $_FILES['poster']['error'] == 0 && $_POST['current_poster']) {
                    $old_poster = '../uploads/posters/' . $_POST['current_poster'];
                    if (file_exists($old_poster)) {
                        unlink($old_poster);
                    }
                }
                
                if ($result) {
                    setFlash('Movie updated successfully', 'success');
                }
            } else {
                // Insert
                $sql = "INSERT INTO movies (title, description, duration, rating, genre, price, poster, trailer_url, streaming_url, release_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$title, $description, $duration, $rating, $genre, $price, $poster_filename, $trailer_url, $streaming_url, $release_date]);
                
                if ($result) {
                    setFlash('Movie added successfully', 'success');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: movies.php');
        exit;
    }
}

// Get all movies
try {
    $movies = $pdo->query("SELECT * FROM movies ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $movies = [];
    setFlash('Error loading movies: ' . $e->getMessage(), 'error');
}

// Get movie for editing
$edit_movie = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_movie = $stmt->fetch();
        if (!$edit_movie) {
            header('Location: movies.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
}

// Ratings options
$ratings = ['G', 'PG', 'PG-13', 'R'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Movies - CinemaTicket</title>
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
        
        /* Headers */
        h1, h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        h1 {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
        }
        
        /* Movie Grid */
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
            border-radius: 16px;
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
            box-shadow: 0 30px 60px rgba(229, 9, 20, 0.15);
        }
        
        .movie-poster {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            transition: all 0.5s;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        .movie-info {
            padding: 25px;
            background: linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.8) 100%);
        }
        
        .movie-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 1px;
        }
        
        .movie-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .movie-badge {
            padding: 4px 12px;
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .movie-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .movie-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 15px;
        }
        
        .movie-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Table Styling (for list view) */
        .movies-table {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow: hidden;
            margin-top: 30px;
        }
        
        .movies-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .movies-table th {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
        }
        
        .movies-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-primary);
        }
        
        .movies-table tr:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .poster-thumb {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border: 1px solid var(--red);
            border-radius: 4px;
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.2);
        }
        
        .rating-badge {
            padding: 4px 10px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 20px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .trailer-link {
            color: var(--red);
            text-decoration: none;
            padding: 5px 12px;
            border: 1px solid var(--red);
            border-radius: 20px;
            font-size: 0.8rem;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .trailer-link:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        .action-btn {
            padding: 5px 12px;
            margin: 0 3px;
            border: 1px solid var(--red);
            border-radius: 20px;
            color: var(--red);
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .action-btn:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        .action-btn.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .action-btn.delete:hover {
            background: #ff4444;
            color: #fff;
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 50px;
            margin-top: 30px;
            margin-bottom: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 2px;
            position: relative;
            padding-bottom: 20px;
        }
        
        .form-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            letter-spacing: 2px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 400;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group textarea {
            border-radius: 20px;
            resize: vertical;
            min-height: 120px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-text {
            display: block;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .current-poster {
            margin: 15px 0;
            padding: 15px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
        }
        
        .poster-preview {
            max-width: 150px;
            max-height: 200px;
            border: 2px solid var(--red);
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 32px;
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
        
        .btn {
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 32px;
            border-radius: 40px;
            background: rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
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
        
        .alert-info {
            border-left-color: var(--red);
            color: var(--text-primary);
        }
        
        .alert-error {
            border-left-color: var(--red);
            color: #ff6b6b;
        }
        
        .alert-success {
            border-left-color: var(--red);
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
            margin: 40px 0;
            opacity: 0.5;
        }
        
        /* Stats Summary */
        .stats-bar {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .stat-item {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            border-color: var(--red);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.15);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 400;
            margin-right: 10px;
        }
        
        .stat-value {
            color: var(--red);
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php" class="active">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Schedule</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Movie Library</h1>
            <a href="?action=add" class="btn-primary">+ Add Movie</a>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px; margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-container">
                <h2>
                    <?php echo $edit_movie ? 'Edit Movie' : 'Add New Movie'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_movie): ?>
                        <input type="hidden" name="movie_id" value="<?php echo $edit_movie['id']; ?>">
                        <input type="hidden" name="current_poster" value="<?php echo $edit_movie['poster']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Movie Title</label>
                        <input type="text" name="title" 
                               value="<?php echo htmlspecialchars($edit_movie['title'] ?? ''); ?>" 
                               required placeholder="Enter movie title">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="5" 
                                  required placeholder="Enter movie description"><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" 
                                   value="<?php echo $edit_movie['duration'] ?? '120'; ?>" 
                                   min="1" max="300" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Rating</label>
                            <select name="rating" required>
                                <option value="">Select Rating</option>
                                <?php foreach ($ratings as $r): ?>
                                    <option value="<?php echo $r; ?>" 
                                        <?php echo ($edit_movie['rating'] ?? '') == $r ? 'selected' : ''; ?>>
                                        <?php echo $r; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Genre</label>
                            <input type="text" name="genre" 
                                   value="<?php echo htmlspecialchars($edit_movie['genre'] ?? ''); ?>" 
                                   required placeholder="e.g., Action, Comedy, Drama">
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" 
                                   value="<?php echo $edit_movie['price'] ?? '12.50'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Release Date</label>
                        <input type="date" name="release_date" 
                               value="<?php echo $edit_movie['release_date'] ?? date('Y-m-d'); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>Movie Poster</label>
                        <input type="file" name="poster" accept="image/jpeg,image/png,image/gif">
                        <small class="form-text">Allowed: JPG, PNG, GIF (Max: 5MB)</small>
                        
                        <?php if ($edit_movie && $edit_movie['poster']): ?>
                            <div class="current-poster">
                                <p style="color: var(--text-secondary); margin-bottom: 10px;">Current Poster:</p>
                                <img src="../uploads/posters/<?php echo $edit_movie['poster']; ?>" 
                                     alt="Current poster" class="poster-preview">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>YouTube Trailer URL</label>
                        <input type="url" name="trailer_url" 
                               value="<?php echo htmlspecialchars($edit_movie['trailer_url'] ?? ''); ?>" 
                               placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text">Supports youtube.com or youtu.be links</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Streaming URL (Optional)</label>
                        <input type="url" name="streaming_url" 
                               value="<?php echo htmlspecialchars($edit_movie['streaming_url'] ?? ''); ?>" 
                               placeholder="https://...">
                        <small class="form-text">Link for online streaming (if available)</small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_movie ? 'Update Movie' : 'Add Movie'; ?>
                        </button>
                        <a href="movies.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Movies List -->
        <?php if (empty($movies)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">No movies in library</p>
                <p style="color: var(--text-secondary); font-size: 1rem;">Click the "Add Movie" button to add your first movie.</p>
            </div>
        <?php else: ?>
            <div class="movies-table">
                <table>
                    <thead>
                        <tr>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Rating</th>
                            <th>Genre</th>
                            <th>Price</th>
                            <th>Trailer</th>
                            <th>Screenings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $movie): 
                            // Get screening count
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
                                $stmt->execute([$movie['id']]);
                                $screening_count = $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                $screening_count = 0;
                            }
                        ?>
                            <tr>
                                <td>
                                    <?php if ($movie['poster']): ?>
                                        <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                             class="poster-thumb">
                                    <?php else: ?>
                                        <span style="color: #666;">No poster</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: var(--red);"><?php echo htmlspecialchars($movie['title']); ?></strong></td>
                                <td><?php echo $movie['duration']; ?> min</td>
                                <td>
                                    <span class="rating-badge">
                                        <?php echo $movie['rating']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                                <td><span style="color: var(--red); font-weight: 600;">$<?php echo number_format($movie['price'], 2); ?></span></td>
                                <td>
                                    <?php if ($movie['trailer_url']): ?>
                                        <a href="<?php echo htmlspecialchars($movie['trailer_url']); ?>" target="_blank" class="trailer-link">▶ Watch</a>
                                    <?php else: ?>
                                        <span style="color: #666;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $screening_count; ?></td>
                                <td>
                                    <a href="?edit=<?php echo $movie['id']; ?>" class="action-btn">Edit</a>
                                    <a href="?delete=<?php echo $movie['id']; ?>" class="action-btn delete" 
                                       onclick="return confirm('Are you sure you want to delete this movie?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Stats Summary -->
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-label">Total Movies</span>
                    <span class="stat-value"><?php echo count($movies); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Total Screenings</span>
                    <span class="stat-value">
                        <?php 
                        $total_screenings = 0;
                        foreach ($movies as $movie) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
                            $stmt->execute([$movie['id']]);
                            $total_screenings += $stmt->fetchColumn();
                        }
                        echo $total_screenings;
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>