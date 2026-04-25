<?php
// admin/movies.php - WITH SEARCH AND FILTERS
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];
$success = '';

// Get unique genres for filter
$genres = $pdo->query("SELECT DISTINCT genre FROM movies WHERE genre IS NOT NULL AND genre != '' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);

// Handle search and filters
$search = $_GET['search'] ?? '';
$rating_filter = $_GET['rating'] ?? '';
$genre_filter = $_GET['genre'] ?? '';

// Build query with filters
$sql = "SELECT * FROM movies WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($rating_filter)) {
    $sql .= " AND rating = ?";
    $params[] = $rating_filter;
}

if (!empty($genre_filter)) {
    $sql .= " AND genre = ?";
    $params[] = $genre_filter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();

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
                unlink($poster_path);
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
    header('Location: movies.php' . ($search ? '?search=' . urlencode($search) : ''));
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
        $max_size = 5 * 1024 * 1024;
        
        if (!in_array($_FILES['poster']['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        } elseif ($_FILES['poster']['size'] > $max_size) {
            $errors[] = 'File too large. Maximum size is 5MB.';
        } else {
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
        
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
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
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .logo:hover {
            text-shadow: var(--red-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 8px;
            font-size: 1.2rem;
            filter: drop-shadow(0 0 5px var(--red));
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
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
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
        }
        
        /* Search and Filter Bar */
        .filter-bar {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 60px;
            padding: 20px 30px;
            margin: 30px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-filter {
            padding: 12px 24px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-filter:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }
        
        .btn-reset {
            padding: 12px 24px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-reset:hover {
            border-color: var(--red);
            color: var(--red);
        }
        
        .results-count {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .movies-table {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow-x: auto;
            margin-top: 30px;
        }
        
        .movies-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
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
        }
        
        .rating-badge {
            padding: 4px 10px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 20px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-block;
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
        }
        
        .action-btn.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .action-btn.delete:hover {
            background: #ff4444;
            color: #fff;
        }
        
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            margin-top: 30px;
            margin-bottom: 40px;
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .form-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background: var(--red);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            border-radius: 20px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .poster-preview {
            max-width: 150px;
            max-height: 200px;
            border: 2px solid var(--red);
            border-radius: 8px;
            margin-top: 10px;
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
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
        }
        
        .btn {
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
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
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 40px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-error {
            border-left: 4px solid #ff4444;
            color: #ff6b6b;
        }
        
        .alert-success {
            border-left: 4px solid #44ff44;
            color: #44ff44;
        }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .filter-bar {
                flex-direction: column;
                border-radius: 20px;
            }
            .filter-actions {
                width: 100%;
            }
            .btn-filter, .btn-reset {
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
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php" class="active">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <h1>Movie Library</h1>
            <a href="?action=add" class="btn-primary">+ Add Movie</a>
        </div>
        
        <div class="cinema-strip"></div>
        
        <!-- Search and Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>🔍 Search Movies</label>
                <input type="text" id="searchInput" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>🎭 Rating</label>
                <select id="ratingFilter">
                    <option value="">All Ratings</option>
                    <?php foreach ($ratings as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $rating_filter == $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>🎬 Genre</label>
                <select id="genreFilter">
                    <option value="">All Genres</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $genre_filter == $g ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn-filter" onclick="applyFilters()">Apply Filters</button>
                <a href="movies.php" class="btn-reset">Reset</a>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-container">
                <h2><?php echo $edit_movie ? 'Edit Movie' : 'Add New Movie'; ?></h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_movie): ?>
                        <input type="hidden" name="movie_id" value="<?php echo $edit_movie['id']; ?>">
                        <input type="hidden" name="current_poster" value="<?php echo $edit_movie['poster']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Movie Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($edit_movie['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="5" required><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" value="<?php echo $edit_movie['duration'] ?? '120'; ?>" min="1" max="300" required>
                        </div>
                        <div class="form-group">
                            <label>Rating</label>
                            <select name="rating" required>
                                <option value="">Select Rating</option>
                                <?php foreach ($ratings as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo ($edit_movie['rating'] ?? '') == $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Genre</label>
                            <input type="text" name="genre" value="<?php echo htmlspecialchars($edit_movie['genre'] ?? ''); ?>" required placeholder="e.g., Action, Comedy, Drama">
                        </div>
                        <div class="form-group">
                            <label>Price (₱)</label>
                            <input type="number" name="price" step="0.01" value="<?php echo $edit_movie['price'] ?? '12.50'; ?>" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Release Date</label>
                        <input type="date" name="release_date" value="<?php echo $edit_movie['release_date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Movie Poster</label>
                        <input type="file" name="poster" accept="image/jpeg,image/png,image/gif">
                        <small class="form-text">Allowed: JPG, PNG, GIF (Max: 5MB)</small>
                        <?php if ($edit_movie && $edit_movie['poster']): ?>
                            <div>
                                <img src="../uploads/posters/<?php echo $edit_movie['poster']; ?>" class="poster-preview">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>YouTube Trailer URL</label>
                        <input type="url" name="trailer_url" value="<?php echo htmlspecialchars($edit_movie['trailer_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    
                    <div class="form-group">
                        <label>Streaming URL (Optional)</label>
                        <input type="url" name="streaming_url" value="<?php echo htmlspecialchars($edit_movie['streaming_url'] ?? ''); ?>" placeholder="https://...">
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn-primary"><?php echo $edit_movie ? 'Update Movie' : 'Add Movie'; ?></button>
                        <a href="movies.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Movies List -->
        <div class="results-count">
            Found <?php echo count($movies); ?> movie(s)
        </div>
        
        <?php if (empty($movies)): ?>
            <div class="alert" style="text-align: center; padding: 60px;">
                <p>No movies found matching your criteria.</p>
                <?php if ($search || $rating_filter || $genre_filter): ?>
                    <a href="movies.php" class="btn" style="margin-top: 20px;">Clear Filters</a>
                <?php endif; ?>
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
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
                            $stmt->execute([$movie['id']]);
                            $screening_count = $stmt->fetchColumn();
                        ?>
                            <tr>
                                <td>
                                    <?php if ($movie['poster']): ?>
                                        <img src="../uploads/posters/<?php echo $movie['poster']; ?>" class="poster-thumb">
                                    <?php else: ?>
                                        <span style="color: #666;">No poster</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: var(--red);"><?php echo htmlspecialchars($movie['title']); ?></strong></td>
                                <td><?php echo $movie['duration']; ?> min</td>
                                <td><span class="rating-badge"><?php echo $movie['rating']; ?></span></td>
                                <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                                <td><span style="color: var(--red); font-weight: 600;">₱<?php echo number_format($movie['price'], 2); ?></span></td>
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
                                    <a href="?delete=<?php echo $movie['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this movie?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const rating = document.getElementById('ratingFilter').value;
            const genre = document.getElementById('genreFilter').value;
            
            let url = 'movies.php?';
            const params = [];
            if (search) params.push('search=' + encodeURIComponent(search));
            if (rating) params.push('rating=' + encodeURIComponent(rating));
            if (genre) params.push('genre=' + encodeURIComponent(genre));
            
            window.location.href = url + params.join('&');
        }
        
        // Allow Enter key to trigger search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    </script>
</body>
</html>