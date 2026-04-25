<?php
// admin/online_schedule.php - WITH 3-MONTH RELEASE DATE VALIDATION
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// Auto-expire past online schedules
$stmt = $pdo->prepare("UPDATE online_schedule SET status = 'expired' WHERE show_date < CURDATE() AND status = 'scheduled'");
$stmt->execute();

$errors = [];
$search_movie = $_GET['search_movie'] ?? '';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE online_schedule_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            setFlash('Cannot delete schedule with existing tickets', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM online_schedule WHERE id = ?");
            if ($stmt->execute([$id])) setFlash('Schedule deleted successfully', 'success');
        }
    } catch (PDOException $e) { setFlash('Error: ' . $e->getMessage(), 'error'); }
    header('Location: online_schedule.php' . ($search_movie ? '?search_movie=' . urlencode($search_movie) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $max_viewers = intval($_POST['max_viewers'] ?? 100);
    $price = floatval($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'scheduled';
    $schedule_id = $_POST['schedule_id'] ?? '';
    
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($max_viewers < 1) $errors[] = 'Max viewers must be at least 1';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    // ============ 3-MONTH RELEASE DATE VALIDATION ============
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT release_date, title FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        $movie_data = $stmt->fetch();
        
        if ($movie_data && $movie_data['release_date']) {
            $release = $movie_data['release_date'];
            $cutoff = date('Y-m-d', strtotime($release . ' +3 months'));
            
            // show_date must be >= release_date
            if ($show_date < $release) {
                $errors[] = 'Online screening cannot start before the movie release date (' . $release . ')';
            }
            // show_date must be <= cutoff
            if ($show_date > $cutoff) {
                $errors[] = 'Cannot add online screening more than 3 months after release date. Cutoff: ' . $cutoff;
            }
        }
    }
    
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM online_schedule WHERE movie_id = ? AND show_date = ? AND show_time = ? AND id != ?");
        $check->execute([$movie_id, $show_date, $show_time, $schedule_id ?: 0]);
        if ($check->fetch()) $errors[] = 'Schedule already exists for this date and time';
    }
    
    if (empty($errors)) {
        try {
            if ($schedule_id) {
                $stmt = $pdo->prepare("UPDATE online_schedule SET movie_id=?, show_date=?, show_time=?, max_viewers=?, price=?, status=? WHERE id=?");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status, $schedule_id]);
                setFlash('Schedule updated successfully', 'success');
            } else {
                $stmt = $pdo->prepare("INSERT INTO online_schedule (movie_id, show_date, show_time, max_viewers, price, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status]);
                setFlash('Schedule added successfully', 'success');
            }
        } catch (PDOException $e) { setFlash('Error: ' . $e->getMessage(), 'error'); }
        header('Location: online_schedule.php' . ($search_movie ? '?search_movie=' . urlencode($search_movie) : ''));
        exit;
    }
}

// Get movies with search filter - include release_date
$movie_sql = "SELECT id, title, price, release_date FROM movies";
$movie_params = [];
if (!empty($search_movie)) {
    $movie_sql .= " WHERE title LIKE ?";
    $movie_params[] = "%$search_movie%";
}
$movie_sql .= " ORDER BY title";
$movie_stmt = $pdo->prepare($movie_sql);
$movie_stmt->execute($movie_params);
$movies = $movie_stmt->fetchAll();

// Get schedules with movie filter
$schedule_sql = "
    SELECT os.*, m.title, m.price as movie_price, m.release_date,
           (SELECT COUNT(*) FROM tickets WHERE online_schedule_id = os.id) as current_viewers
    FROM online_schedule os 
    JOIN movies m ON os.movie_id = m.id 
";
if (!empty($search_movie)) {
    $schedule_sql .= " WHERE m.title LIKE ?";
    $schedule_params = ["%$search_movie%"];
} else {
    $schedule_params = [];
}
$schedule_sql .= " ORDER BY os.show_date DESC, os.show_time DESC";
$schedule_stmt = $pdo->prepare($schedule_sql);
$schedule_stmt->execute($schedule_params);
$schedules = $schedule_stmt->fetchAll();

$edit_schedule = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM online_schedule WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_schedule = $stmt->fetch();
}

$status_options = ['scheduled', 'live', 'ended', 'cancelled', 'expired'];

// Helper function to add months to a date
function addMonths($date, $months) {
    $d = new DateTime($date);
    $d->modify("+$months months");
    return $d->format('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Schedule - CinemaTicket</title>
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
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --card-gradient: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
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
        }
        .logo:hover { text-shadow: 0 0 20px rgba(229, 9, 20, 0.3); }
        .logo::before { content: "🎬"; margin-right: 8px; }
        
        .nav-links { display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .nav-links a:hover { color: var(--red); }
        .nav-links a.active { color: var(--red); }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 30px 0;
            text-transform: uppercase;
        }
        
        /* Movie Search Section */
        .movie-search-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 60px;
            padding: 20px 30px;
            margin-bottom: 30px;
        }
        
        .search-wrapper {
            position: relative;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .search-input-group {
            flex: 1;
            min-width: 250px;
        }
        
        .search-input-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.7rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .search-input-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .search-input-group input:focus {
            outline: none;
            border-color: var(--red);
        }
        
        .btn-search {
            padding: 14px 28px;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }
        
        .btn-clear {
            padding: 14px 28px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-clear:hover {
            border-color: var(--red);
            color: var(--red);
        }
        
        .form-wrapper {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2rem;
            margin-bottom: 30px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--red);
            display: inline-block;
            width: 100%;
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
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--red);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .date-info {
            background: rgba(229, 9, 20, 0.1);
            border-radius: 40px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #ff8844;
            text-align: center;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            padding: 14px 30px;
            border-radius: 40px;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 14px 30px;
            border-radius: 40px;
            text-decoration: none;
            text-align: center;
            width: 100%;
        }
        
        .schedule-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        
        .schedule-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .schedule-card:hover {
            border-color: var(--red);
            transform: translateX(5px);
        }
        
        .schedule-movie {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 10px;
        }
        
        .schedule-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.1);
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-item {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .detail-item strong {
            color: var(--red);
        }
        
        .capacity-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .capacity-fill {
            height: 100%;
            background: var(--red);
            border-radius: 3px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-scheduled {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .status-live {
            background: rgba(0, 255, 0, 0.15);
            color: #00ff00;
            border: 1px solid #00ff00;
        }
        
        .status-ended, .status-expired {
            background: rgba(136, 136, 136, 0.15);
            color: #888;
            border: 1px solid #888;
        }
        
        .schedule-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 0.75rem;
            text-decoration: none;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 30px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
        }
        
        .add-button-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .add-button {
            padding: 12px 24px;
            background: var(--red);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
        }
        
        .add-button:hover {
            background: var(--red-dark);
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
        
        .results-count {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .warning-text {
            color: #ff8844;
            font-size: 0.75rem;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .search-wrapper { flex-direction: column; }
            .search-wrapper .btn-search,
            .search-wrapper .btn-clear {
                width: 100%;
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
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php" class="active">Online</a>
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
            <h1>Online Schedule</h1>
            <a href="?action=add" class="add-button">➕ Add Time Slot</a>
        </div>
        
        <div class="cinema-strip"></div>
        
        <!-- Movie Search Dropdown Section -->
        <div class="movie-search-section">
            <form method="GET" action="">
                <div class="search-wrapper">
                    <div class="search-input-group">
                        <label>🔍 Search by Movie Name</label>
                        <input type="text" name="search_movie" placeholder="Type movie name..." value="<?php echo htmlspecialchars($search_movie); ?>" autocomplete="off" list="movie-suggestions">
                        <datalist id="movie-suggestions">
                            <?php 
                            $all_movies = $pdo->query("SELECT title FROM movies ORDER BY title")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($all_movies as $m): 
                            ?>
                                <option value="<?php echo htmlspecialchars($m); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <button type="submit" class="btn-search">🔍 Search</button>
                    <?php if ($search_movie): ?>
                        <a href="online_schedule.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px;"><?php foreach($errors as $e) echo "<li>$e</li>"; ?></ul>
            </div>
        <?php endif; ?>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-wrapper">
                <div class="form-container">
                    <h2><?php echo $edit_schedule ? '✏️ Edit Time Slot' : '➕ Add New Time Slot'; ?></h2>
                    
                    <form method="POST" id="scheduleForm">
                        <?php if ($edit_schedule): ?>
                            <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>🎬 Select Movie</label>
                                <select name="movie_id" id="movieSelect" required>
                                    <option value="">-- Select a movie --</option>
                                    <?php foreach ($movies as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" 
                                            data-price="<?php echo $m['price']; ?>"
                                            data-release="<?php echo $m['release_date']; ?>"
                                            <?php echo (($edit_schedule['movie_id']??'')==$m['id'])?'selected':''; ?>>
                                            <?php echo htmlspecialchars($m['title']); ?> (₱<?php echo number_format($m['price'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>💰 Price (₱)</label>
                                <input type="number" name="price" id="priceInput" step="0.01" 
                                       value="<?php echo $edit_schedule['price'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Show Date</label>
                                <input type="date" name="show_date" id="showDate" 
                                       value="<?php 
                                            if ($edit_schedule) {
                                                echo $edit_schedule['show_date'];
                                            } else {
                                                // Auto-suggest release date if movie selected
                                                echo '';
                                            }
                                       ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <div id="dateInfo" class="date-info"></div>
                            </div>
                            
                            <div class="form-group">
                                <label>⏰ Show Time</label>
                                <input type="time" name="show_time" value="<?php echo $edit_schedule['show_time']??'20:00'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>👥 Maximum Viewers</label>
                                <input type="number" name="max_viewers" value="<?php echo $edit_schedule['max_viewers']??'100'; ?>" min="1" max="1000" required>
                            </div>
                            
                            <div class="form-group">
                                <label>📊 Status</label>
                                <select name="status" required>
                                    <?php foreach($status_options as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo (($edit_schedule['status']??'scheduled')==$s)?'selected':''; ?>>
                                            <?php echo ucfirst($s); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn-primary"><?php echo $edit_schedule ? '💾 Update' : '✨ Add'; ?></button>
                            <a href="online_schedule.php<?php echo $search_movie ? '?search_movie=' . urlencode($search_movie) : ''; ?>" class="btn-secondary">❌ Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Schedules List -->
        <div class="results-count">
            Found <?php echo count($schedules); ?> schedule(s)
        </div>
        
        <div class="schedule-cards">
            <?php if (empty($schedules)): ?>
                <div style="text-align:center; padding:60px; background:var(--card-gradient); border-radius:24px;">
                    🎬 No online schedules found.
                    <?php if ($search_movie): ?>
                        <br><a href="online_schedule.php" style="color: var(--red);">Clear search</a>
                    <?php else: ?>
                        <br>Click "Add Time Slot" to create one.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($schedules as $s): 
                    $available = $s['max_viewers'] - ($s['current_viewers'] ?? 0);
                    $capacity_percent = (($s['current_viewers'] ?? 0) / $s['max_viewers']) * 100;
                    $is_expired = $s['show_date'] < date('Y-m-d');
                    $formattedDate = date('l, F j, Y', strtotime($s['show_date']));
                    $formattedTime = date('g:i A', strtotime($s['show_time']));
                    
                    // Check if date is within valid range
                    $cutoff = date('Y-m-d', strtotime($s['release_date'] . ' +3 months'));
                    $is_outside_range = $s['show_date'] < $s['release_date'] || $s['show_date'] > $cutoff;
                ?>
                    <div class="schedule-card">
                        <div class="schedule-movie">🎬 <?php echo htmlspecialchars($s['title']); ?></div>
                        <div class="schedule-details">
                            <div class="detail-item">📅 <strong>Date:</strong> <?php echo $formattedDate; ?></div>
                            <div class="detail-item">⏰ <strong>Time:</strong> <?php echo $formattedTime; ?></div>
                            <div class="detail-item">💰 <strong>Price:</strong> ₱<?php echo number_format($s['price'], 2); ?></div>
                            <div class="detail-item">
                                <strong>👥 Viewers:</strong> <?php echo $s['current_viewers'] ?? 0; ?>/<?php echo $s['max_viewers']; ?>
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo $capacity_percent; ?>%;"></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <strong>📊 Status:</strong> 
                                <span class="status-badge <?php echo $is_expired ? 'status-expired' : 'status-' . $s['status']; ?>">
                                    <?php echo ucfirst($is_expired ? 'expired' : $s['status']); ?>
                                </span>
                            </div>
                            <?php if ($is_outside_range && !$is_expired): ?>
                                <div class="detail-item">
                                    <strong>⚠️ Warning:</strong> 
                                    <span style="color: #ff8844;">Outside valid date range (Release: <?php echo $s['release_date']; ?> | Cutoff: <?php echo $cutoff; ?>)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="schedule-actions">
                            <a href="?edit=<?php echo $s['id']; ?><?php echo $search_movie ? '&search_movie=' . urlencode($search_movie) : ''; ?>" class="btn-small">✏️ Edit</a>
                            <a href="?delete=<?php echo $s['id']; ?><?php echo $search_movie ? '&search_movie=' . urlencode($search_movie) : ''; ?>" class="btn-small" onclick="return confirm('Delete this schedule?')">🗑️ Delete</a>
                            <a href="tickets.php?online_id=<?php echo $s['id']; ?>" class="btn-small">🎟️ Tickets</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Auto-populate price and date info when movie is selected
        const movieSelect = document.getElementById('movieSelect');
        const priceInput = document.getElementById('priceInput');
        const showDateInput = document.getElementById('showDate');
        const dateInfoDiv = document.getElementById('dateInfo');
        
        function updatePriceFromMovie() {
            if (movieSelect && priceInput && movieSelect.value) {
                const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                const moviePrice = selectedOption.getAttribute('data-price');
                if (moviePrice) {
                    priceInput.value = parseFloat(moviePrice).toFixed(2);
                }
            }
        }
        
        function updateDateInfo() {
            if (movieSelect && movieSelect.value) {
                const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                const releaseDate = selectedOption.getAttribute('data-release');
                
                if (releaseDate) {
                    // Calculate cutoff (release date + 3 months)
                    const release = new Date(releaseDate);
                    const cutoff = new Date(release);
                    cutoff.setMonth(cutoff.getMonth() + 3);
                    
                    const releaseStr = release.toISOString().split('T')[0];
                    const cutoffStr = cutoff.toISOString().split('T')[0];
                    
                    dateInfoDiv.innerHTML = `📅 Release: ${releaseStr} | ✅ Valid until: ${cutoffStr}`;
                    
                    // Auto-suggest show_date = release_date for new schedules
                    if (!showDateInput.value || showDateInput.value === '') {
                        showDateInput.value = releaseStr;
                    }
                    
                    // Set min date to release date
                    showDateInput.min = releaseStr;
                    showDateInput.max = cutoffStr;
                } else {
                    dateInfoDiv.innerHTML = '';
                    showDateInput.min = '<?php echo date('Y-m-d'); ?>';
                    showDateInput.max = '';
                }
            } else {
                dateInfoDiv.innerHTML = '';
                showDateInput.min = '<?php echo date('Y-m-d'); ?>';
                showDateInput.max = '';
            }
        }
        
        if (movieSelect) {
            movieSelect.addEventListener('change', function() {
                updatePriceFromMovie();
                updateDateInfo();
            });
            if (movieSelect.value) {
                updatePriceFromMovie();
                updateDateInfo();
            } else if (!showDateInput.value) {
                // Set default date if no movie selected
                const today = new Date().toISOString().split('T')[0];
                showDateInput.value = today;
            }
        }
        
        // Validate date when changed
        if (showDateInput) {
            showDateInput.addEventListener('change', function() {
                if (movieSelect && movieSelect.value) {
                    const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                    const releaseDate = selectedOption.getAttribute('data-release');
                    const showDate = this.value;
                    
                    if (releaseDate && showDate) {
                        if (showDate < releaseDate) {
                            dateInfoDiv.innerHTML += '<br><span style="color: #ff4444;">⚠️ Warning: Date is BEFORE release date!</span>';
                        } else {
                            const release = new Date(releaseDate);
                            const cutoff = new Date(release);
                            cutoff.setMonth(cutoff.getMonth() + 3);
                            const cutoffStr = cutoff.toISOString().split('T')[0];
                            
                            if (showDate > cutoffStr) {
                                dateInfoDiv.innerHTML += '<br><span style="color: #ff8844;">⚠️ Warning: Date is more than 3 months after release!</span>';
                            } else {
                                dateInfoDiv.innerHTML = dateInfoDiv.innerHTML.replace(/<br>.*/, '');
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>