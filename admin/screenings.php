<?php
// admin/screenings.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
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
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: screenings.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $cinema_id = $_POST['cinema_id'] ?? '';
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
    
    // Check for duplicate (same cinema, screen, date, time)
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?");
        $check->execute([$cinema_id, $screen_number, $show_date, $show_time, $screening_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Screening already exists for this cinema, screen, date and time';
        }
        
        // Check max 5 screenings per screen per day
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ?");
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
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: screenings.php');
        exit;
    }
}

// Get all movies
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all cinemas
$cinemas = $pdo->query("SELECT id, name, total_screens FROM cinemas ORDER BY name")->fetchAll();

// Get screenings with details
$screenings = $pdo->query("
    SELECT s.*, m.title as movie_title, c.name as cinema_name,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    ORDER BY s.show_date DESC, s.show_time DESC
")->fetchAll();

// Get screening for editing
$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .screenings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .cinema-group {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .cinema-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #00ffff;
            margin-bottom: 20px;
        }
        .screen-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #000;
            border-radius: 4px;
        }
        .screen-number {
            font-size: 1.2rem;
            color: #00ffff;
            font-weight: bold;
        }
        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .time-slot {
            padding: 8px 15px;
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 4px;
            color: #fff;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .time-slot .seats {
            color: #44ff44;
            font-size: 0.8rem;
        }
        .time-slot .actions {
            display: flex;
            gap: 5px;
            margin-left: 10px;
        }
        .btn-small {
            padding: 3px 8px;
            font-size: 0.8rem;
            text-decoration: none;
            border: 1px solid #00ffff;
            border-radius: 3px;
            color: #00ffff;
        }
        .btn-small:hover {
            background: #00ffff;
            color: #000;
        }
        .warning {
            color: #ffff44;
            font-size: 0.9rem;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php" class="active">Screenings</a>
                <a href="online_schedule.php">Online Schedule</a>
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
            <h1>Manage Screenings</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add New Screening</a>
        </div>
        
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
                            <label>Movie</label>
                            <select name="movie_id" required>
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
                            <label>Cinema</label>
                            <select name="cinema_id" id="cinemaSelect" required onchange="updateScreenOptions()">
                                <option value="">Select Cinema</option>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <option value="<?php echo $cinema['id']; ?>" 
                                        data-screens="<?php echo $cinema['total_screens']; ?>"
                                        <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cinema['name']); ?> (<?php echo $cinema['total_screens']; ?> screens)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Screen Number</label>
                            <select name="screen_number" id="screenSelect" required>
                                <option value="">Select Screen</option>
                                <?php if ($edit_screening): ?>
                                    <option value="<?php echo $edit_screening['screen_number']; ?>" selected>
                                        Screen <?php echo $edit_screening['screen_number']; ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" name="price" step="0.01" value="<?php echo $edit_screening['price'] ?? '12.50'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Show Date</label>
                            <input type="date" name="show_date" value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Show Time</label>
                            <input type="time" name="show_time" value="<?php echo $edit_screening['show_time'] ?? '10:00'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Available Seats</label>
                        <input type="number" name="available_seats" value="<?php echo $edit_screening['available_seats'] ?? '40'; ?>" min="1" max="100" required>
                        <small class="form-text">Maximum 5 screenings per screen per day</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_screening ? 'Update' : 'Add'; ?> Screening
                        </button>
                        <a href="screenings.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Screenings Display -->
        <?php
        // Group screenings by cinema and screen
        $grouped = [];
        foreach ($screenings as $s) {
            $cinema = $s['cinema_name'];
            $screen = $s['screen_number'];
            $date = $s['show_date'];
            if (!isset($grouped[$cinema])) {
                $grouped[$cinema] = [];
            }
            if (!isset($grouped[$cinema][$screen])) {
                $grouped[$cinema][$screen] = [];
            }
            if (!isset($grouped[$cinema][$screen][$date])) {
                $grouped[$cinema][$screen][$date] = [];
            }
            $grouped[$cinema][$screen][$date][] = $s;
        }
        ?>
        
        <?php foreach ($grouped as $cinema => $screens): ?>
            <div class="cinema-group">
                <div class="cinema-header">
                    <h2 style="color:#00ffff;"><?php echo htmlspecialchars($cinema); ?></h2>
                </div>
                
                <?php foreach ($screens as $screen => $dates): ?>
                    <div class="screen-row">
                        <div class="screen-number">Screen <?php echo $screen; ?></div>
                        <div>
                            <?php foreach ($dates as $date => $screenings): ?>
                                <div style="margin-bottom: 15px;">
                                    <div style="color:#00ffff; margin-bottom: 10px;"><?php echo date('F d, Y', strtotime($date)); ?></div>
                                    <div class="time-slots">
                                        <?php foreach ($screenings as $s): 
                                            $sold = $s['tickets_sold'];
                                            $available = $s['available_seats'];
                                        ?>
                                            <div class="time-slot">
                                                <span><?php echo date('h:i A', strtotime($s['show_time'])); ?></span>
                                                <span class="seats"><?php echo $available; ?> seats</span>
                                                <span>$<?php echo number_format($s['price'], 2); ?></span>
                                                <span class="actions">
                                                    <a href="?edit=<?php echo $s['id']; ?>" class="btn-small">Edit</a>
                                                    <a href="?delete=<?php echo $s['id']; ?>" class="btn-small" onclick="return confirm('Delete?')" style="border-color:#ff4444; color:#ff4444;">Delete</a>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($screenings) < 5): ?>
                                            <div style="color:#888; padding:8px;">
                                                <?php echo 5 - count($screenings); ?> slots available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </main>
    
    <script>
        function updateScreenOptions() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            const screenSelect = document.getElementById('screenSelect');
            const selected = cinemaSelect.options[cinemaSelect.selectedIndex];
            
            if (selected && selected.dataset.screens) {
                const totalScreens = parseInt(selected.dataset.screens);
                let options = '<option value="">Select Screen</option>';
                for (let i = 1; i <= totalScreens; i++) {
                    options += `<option value="${i}">Screen ${i}</option>`;
                }
                screenSelect.innerHTML = options;
            }
        }
        
        // Initialize on page load if editing
        <?php if ($edit_screening): ?>
        window.onload = function() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            cinemaSelect.value = '<?php echo $edit_screening['cinema_id']; ?>';
            updateScreenOptions();
            setTimeout(() => {
                document.getElementById('screenSelect').value = '<?php echo $edit_screening['screen_number']; ?>';
            }, 100);
        }
        <?php endif; ?>
    </script>
</body>
</html>