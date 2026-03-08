<?php
// staff/screenings.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;
$cinema_filter = $cinema_id ? "AND cinema_id = $cinema_id" : "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if screening has tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE screening_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        setFlash('Cannot delete screening with existing tickets', 'error');
    } else {
        $stmt = $pdo->prepare("DELETE FROM screenings WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('Screening deleted successfully', 'success');
        }
    }
    header('Location: screenings.php');
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $cinema_id = $_POST['cinema_id'] ?? ($user['cinema_id'] ?? '');
    $screen_number = intval($_POST['screen_number'] ?? 1);
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $available_seats = intval($_POST['available_seats'] ?? 40);
    $screening_id = $_POST['screening_id'] ?? '';
    
    // Validation
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($cinema_id)) $errors[] = 'Cinema is required';
    if ($screen_number < 1) $errors[] = 'Valid screen number is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($available_seats < 1 || $available_seats > 100) $errors[] = 'Available seats must be between 1 and 100';
    
    // Check for duplicate
    if (empty($errors)) {
        $check = $pdo->prepare("
            SELECT id FROM screenings 
            WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?
        ");
        $check->execute([$cinema_id, $screen_number, $show_date, $show_time, $screening_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Screening already exists for this cinema, screen, date and time';
        }
        
        // Check max 5 screenings per screen per day
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM screenings 
            WHERE cinema_id = ? AND screen_number = ? AND show_date = ?
        ");
        $stmt->execute([$cinema_id, $screen_number, $show_date]);
        $count = $stmt->fetchColumn();
        
        if ($count >= 5 && !$screening_id) {
            $errors[] = 'Maximum 5 screenings allowed per screen per day';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($screening_id) {
                // Update
                $sql = "UPDATE screenings SET movie_id=?, cinema_id=?, screen_number=?, show_date=?, show_time=?, price=?, available_seats=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats, $screening_id]);
                setFlash('Screening updated successfully', 'success');
            } else {
                // Insert
                $sql = "INSERT INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$movie_id, $cinema_id, $screen_number, $show_date, $show_time, $price, $available_seats]);
                setFlash('Screening added successfully', 'success');
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: screenings.php');
        exit;
    }
}

// Get all movies for dropdown
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all cinemas for dropdown (filter by staff's assignment)
$cinema_sql = "SELECT id, name, total_screens FROM cinemas";
if ($cinema_id) {
    $cinema_sql .= " WHERE id = $cinema_id";
}
$cinemas = $pdo->query($cinema_sql . " ORDER BY name")->fetchAll();

// Get filter parameters
$filter_cinema = $_GET['cinema_id'] ?? ($cinema_id ?: '');
$filter_movie = $_GET['movie_id'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query for screenings
$sql = "
    SELECT s.*, m.title, m.duration, m.rating, c.name as cinema_name,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE 1=1
";
$params = [];

if ($filter_cinema) {
    $sql .= " AND s.cinema_id = ?";
    $params[] = $filter_cinema;
}
if ($filter_movie) {
    $sql .= " AND s.movie_id = ?";
    $params[] = $filter_movie;
}
if ($filter_date) {
    $sql .= " AND s.show_date = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY s.show_date DESC, s.show_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$screenings = $stmt->fetchAll();

// Get screening for editing
$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}

// Group screenings by date for display
$grouped_screenings = [];
foreach ($screenings as $s) {
    $date = $s['show_date'];
    if (!isset($grouped_screenings[$date])) {
        $grouped_screenings[$date] = [];
    }
    $grouped_screenings[$date][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-section {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #00ffff;
            margin: 30px 0;
        }
        .filter-form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
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
        .screenings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .screening-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        .screening-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 30px rgba(0,255,255,0.3);
        }
        .screening-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        .screening-time {
            font-size: 1.3rem;
            color: #00ffff;
            font-weight: bold;
        }
        .screen-number {
            color: #888;
        }
        .screening-movie {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #fff;
        }
        .screening-details {
            color: #888;
            margin-bottom: 15px;
        }
        .seat-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            padding: 10px;
            background: #000;
            border-radius: 4px;
        }
        .available-seats {
            color: #44ff44;
            font-weight: bold;
        }
        .sold-seats {
            color: #ffff44;
        }
        .progress-bar {
            height: 8px;
            background: #333;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: #00ffff;
            border-radius: 4px;
        }
        .screening-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-small {
            flex: 1;
            text-align: center;
            padding: 8px;
            border: 1px solid #00ffff;
            border-radius: 4px;
            color: #00ffff;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-small:hover {
            background: #00ffff;
            color: #000;
        }
        .btn-small.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        .btn-small.delete:hover {
            background: #ff4444;
            color: #000;
        }
        .staff-notice {
            background: rgba(255,255,68,0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php" class="active">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Manage Screenings</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add New Screening</a>
        </div>
        
        <?php if ($cinema_id): ?>
            <div class="staff-notice">
                ℹ️ You are adding screenings for your assigned cinema only.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div style="background: #1a1a1a; padding: 30px; border: 2px solid #00ffff; border-radius: 8px; margin-bottom: 40px;">
                <h2 style="color:#00ffff; margin-bottom:20px;">
                    <?php echo $edit_screening ? 'Edit Screening' : 'Add New Screening'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_screening): ?>
                        <input type="hidden" name="screening_id" value="<?php echo $edit_screening['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="movie_id">Movie</label>
                            <select id="movie_id" name="movie_id" required>
                                <option value="">Select Movie</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo $movie['id']; ?>"
                                        <?php echo (($edit_screening['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($movie['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="cinema_id">Cinema</label>
                            <select id="cinema_id" name="cinema_id" required>
                                <option value="">Select Cinema</option>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <option value="<?php echo $cinema['id']; ?>"
                                        <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cinema['name']); ?> (<?php echo $cinema['total_screens']; ?> screens)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="screen_number">Screen Number</label>
                            <input type="number" id="screen_number" name="screen_number" 
                                   value="<?php echo $edit_screening['screen_number'] ?? '1'; ?>" 
                                   min="1" max="20" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price ($)</label>
                            <input type="number" id="price" name="price" step="0.01" 
                                   value="<?php echo $edit_screening['price'] ?? '12.50'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="show_date">Show Date</label>
                            <input type="date" id="show_date" name="show_date" 
                                   value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_time">Show Time</label>
                            <input type="time" id="show_time" name="show_time" 
                                   value="<?php echo $edit_screening['show_time'] ?? '10:00'; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="available_seats">Available Seats</label>
                        <input type="number" id="available_seats" name="available_seats" 
                               value="<?php echo $edit_screening['available_seats'] ?? '40'; ?>" 
                               min="1" max="100" required>
                        <small class="form-text">Maximum 5 screenings per screen per day</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_screening ? 'Update Screening' : 'Add Screening'; ?>
                        </button>
                        <a href="screenings.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="filter_cinema">Filter by Cinema</label>
                    <select id="filter_cinema" name="cinema_id">
                        <option value="">All Cinemas</option>
                        <?php foreach ($cinemas as $cinema): ?>
                            <option value="<?php echo $cinema['id']; ?>" <?php echo $filter_cinema == $cinema['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cinema['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_movie">Filter by Movie</label>
                    <select id="filter_movie" name="movie_id">
                        <option value="">All Movies</option>
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo $movie['id']; ?>" <?php echo $filter_movie == $movie['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_date">Filter by Date</label>
                    <input type="date" id="filter_date" name="date" value="<?php echo $filter_date; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="screenings.php" class="btn">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Screenings Display -->
        <?php if (empty($screenings)): ?>
            <div style="text-align: center; padding: 60px; background: #1a1a1a; border: 2px solid #00ffff; border-radius: 8px;">
                <p style="color: #888;">No screenings found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_screenings as $date => $screenings): ?>
                <div class="date-group">
                    <h2 class="date-title"><?php echo date('l, F d, Y', strtotime($date)); ?></h2>
                    
                    <div class="screenings-grid">
                        <?php foreach ($screenings as $screening): 
                            $sold = $screening['tickets_sold'];
                            $available = $screening['available_seats'];
                            $total = $sold + $available;
                            $occupancy = ($sold / $total) * 100;
                        ?>
                            <div class="screening-card">
                                <div class="screening-header">
                                    <span class="screening-time"><?php echo date('h:i A', strtotime($screening['show_time'])); ?></span>
                                    <span class="screen-number">Screen <?php echo $screening['screen_number']; ?></span>
                                </div>
                                
                                <div class="screening-movie">
                                    <?php echo htmlspecialchars($screening['title']); ?>
                                </div>
                                
                                <div class="screening-details">
                                    <?php echo htmlspecialchars($screening['cinema_name']); ?><br>
                                    <?php echo $screening['rating']; ?> • <?php echo $screening['duration']; ?> min
                                </div>
                                
                                <div class="seat-info">
                                    <span>🎟️ Sold: <span class="sold-seats"><?php echo $sold; ?></span></span>
                                    <span>🪑 Left: <span class="available-seats"><?php echo $available; ?></span></span>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $occupancy; ?>%"></div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin: 15px 0;">
                                    <span>Price: $<?php echo number_format($screening['price'], 2); ?></span>
                                    <span>Total: $<?php echo number_format($screening['price'] * $sold, 2); ?></span>
                                </div>
                                
                                <div class="screening-actions">
                                    <a href="?edit=<?php echo $screening['id']; ?>" class="btn-small">Edit</a>
                                    <a href="tickets_list.php?screening_id=<?php echo $screening['id']; ?>" class="btn-small">View Tickets</a>
                                    <a href="?delete=<?php echo $screening['id']; ?>" class="btn-small delete" 
                                       onclick="return confirm('Delete this screening?')">Delete</a>
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