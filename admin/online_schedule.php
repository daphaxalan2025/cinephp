<?php
// admin/online_schedule.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Check if schedule has tickets
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE online_schedule_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete schedule with existing tickets', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM online_schedule WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Schedule deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: online_schedule.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $max_viewers = intval($_POST['max_viewers'] ?? 100);
    $price = floatval($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'scheduled';
    $schedule_id = $_POST['schedule_id'] ?? '';
    
    // Validation
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($max_viewers < 1) $errors[] = 'Max viewers must be at least 1';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    // Check for duplicate
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM online_schedule WHERE movie_id = ? AND show_date = ? AND show_time = ? AND id != ?");
        $check->execute([$movie_id, $show_date, $show_time, $schedule_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Schedule already exists for this date and time';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($schedule_id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE online_schedule 
                    SET movie_id=?, show_date=?, show_time=?, max_viewers=?, price=?, status=? 
                    WHERE id=?
                ");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status, $schedule_id]);
                setFlash('Schedule updated successfully', 'success');
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO online_schedule (movie_id, show_date, show_time, max_viewers, price, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$movie_id, $show_date, $show_time, $max_viewers, $price, $status]);
                setFlash('Schedule added successfully', 'success');
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: online_schedule.php');
        exit;
    }
}

// Get all movies for dropdown
$movies = $pdo->query("SELECT id, title FROM movies ORDER BY title")->fetchAll();

// Get all schedules with movie details
$schedules = $pdo->query("
    SELECT os.*, m.title 
    FROM online_schedule os
    JOIN movies m ON os.movie_id = m.id
    ORDER BY os.show_date DESC, os.show_time DESC
")->fetchAll();

// Get schedule for editing
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM online_schedule WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_schedule = $stmt->fetch();
}

// Status options
$status_options = ['scheduled', 'live', 'ended', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Schedule - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .status-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-scheduled {
            background: rgba(0, 255, 255, 0.2);
            color: #00ffff;
            border: 1px solid #00ffff;
        }
        .status-live {
            background: rgba(68, 255, 68, 0.2);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        .status-ended {
            background: rgba(255, 68, 68, 0.2);
            color: #ff4444;
            border: 1px solid #ff4444;
        }
        .status-cancelled {
            background: rgba(255, 255, 68, 0.2);
            color: #ffff44;
            border: 1px solid #ffff44;
        }
        .capacity-bar {
            width: 100px;
            height: 6px;
            background: #333;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        .capacity-fill {
            height: 100%;
            background: #00ffff;
            border-radius: 3px;
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
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php" class="active">Online Schedule</a>
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
            <h1>Online Streaming Schedule</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add Time Slot</a>
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
                    <?php echo $edit_schedule ? 'Edit Time Slot' : 'Add New Time Slot'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_schedule): ?>
                        <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="movie_id">Movie</label>
                            <select id="movie_id" name="movie_id" required>
                                <option value="">Select Movie</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo $movie['id']; ?>"
                                        <?php echo (($edit_schedule['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($movie['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price ($)</label>
                            <input type="number" id="price" name="price" step="0.01" 
                                   value="<?php echo $edit_schedule['price'] ?? '10.00'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="show_date">Show Date</label>
                            <input type="date" id="show_date" name="show_date" 
                                   value="<?php echo $edit_schedule['show_date'] ?? date('Y-m-d'); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="show_time">Show Time</label>
                            <input type="time" id="show_time" name="show_time" 
                                   value="<?php echo $edit_schedule['show_time'] ?? '20:00'; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_viewers">Maximum Viewers</label>
                            <input type="number" id="max_viewers" name="max_viewers" 
                                   value="<?php echo $edit_schedule['max_viewers'] ?? '100'; ?>" 
                                   min="1" max="1000" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <?php foreach ($status_options as $s): ?>
                                    <option value="<?php echo $s; ?>"
                                        <?php echo (($edit_schedule['status'] ?? 'scheduled') == $s) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($s); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_schedule ? 'Update Schedule' : 'Add Schedule'; ?>
                        </button>
                        <a href="online_schedule.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Schedule List -->
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Movie</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Capacity</th>
                        <th>Booked</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): 
                        $available = $schedule['max_viewers'] - $schedule['current_viewers'];
                        $capacity_percent = ($schedule['current_viewers'] / $schedule['max_viewers']) * 100;
                    ?>
                        <tr>
                            <td><?php echo $schedule['id']; ?></td>
                            <td><strong style="color: #00ffff;"><?php echo htmlspecialchars($schedule['title']); ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($schedule['show_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($schedule['show_time'])); ?></td>
                            <td>$<?php echo number_format($schedule['price'], 2); ?></td>
                            <td><?php echo $schedule['max_viewers']; ?></td>
                            <td><?php echo $schedule['current_viewers']; ?></td>
                            <td>
                                <?php echo $available; ?>
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo $capacity_percent; ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $schedule['status']; ?>">
                                    <?php echo ucfirst($schedule['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $schedule['id']; ?>" class="btn-small">Edit</a>
                                <a href="?delete=<?php echo $schedule['id']; ?>" class="btn-small" 
                                   onclick="return confirm('Delete this schedule?')" 
                                   style="border-color: #ff4444; color: #ff4444;">Delete</a>
                                <a href="tickets.php?online_id=<?php echo $schedule['id']; ?>" class="btn-small">View Tickets</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($schedules)): ?>
            <div style="text-align: center; padding: 60px; background: #1a1a1a; border: 2px solid #00ffff; border-radius: 8px; margin-top: 30px;">
                <p style="color: #888;">No online schedules found.</p>
                <p style="color: #888; margin-top: 10px;">Click "Add Time Slot" to create your first online streaming schedule.</p>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>