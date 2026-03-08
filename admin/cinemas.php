<?php
// admin/cinemas.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Check if cinema has screenings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete cinema with existing screenings. Delete the screenings first.', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM cinemas WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Cinema deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: cinemas.php');
    exit;
}

// ============ HANDLE ADD/EDIT ============
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $total_screens = intval($_POST['total_screens'] ?? 1);
    $cinema_id = $_POST['cinema_id'] ?? '';
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Cinema name is required';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Cinema name must be at least 3 characters';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required';
    }
    
    if ($total_screens < 1) {
        $errors[] = 'Total screens must be at least 1';
    } elseif ($total_screens > 20) {
        $errors[] = 'Total screens cannot exceed 20';
    }
    
    // Check for duplicate name (only if no other errors)
    if (empty($errors)) {
        try {
            $check = $pdo->prepare("SELECT id FROM cinemas WHERE name = ? AND id != ?");
            $check->execute([$name, $cinema_id ?: 0]);
            if ($check->fetch()) {
                $errors[] = 'Cinema with this name already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // If no errors, proceed with insert/update
    if (empty($errors)) {
        try {
            if ($cinema_id) {
                // UPDATE existing cinema
                $sql = "UPDATE cinemas SET name = ?, location = ?, total_screens = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$name, $location, $total_screens, $cinema_id]);
                
                if ($result) {
                    setFlash('Cinema updated successfully', 'success');
                    header('Location: cinemas.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update cinema';
                }
            } else {
                // INSERT new cinema
                $sql = "INSERT INTO cinemas (name, location, total_screens) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$name, $location, $total_screens]);
                
                if ($result) {
                    setFlash('Cinema added successfully', 'success');
                    header('Location: cinemas.php');
                    exit;
                } else {
                    $errors[] = 'Failed to add cinema';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============ GET ALL CINEMAS ============
try {
    $stmt = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id) as total_screenings,
               (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND show_date >= CURDATE()) as upcoming_screenings
        FROM cinemas c
        ORDER BY c.created_at DESC
    ");
    $cinemas = $stmt->fetchAll();
} catch (PDOException $e) {
    $cinemas = [];
    setFlash('Error loading cinemas: ' . $e->getMessage(), 'error');
}

// ============ GET CINEMA FOR EDITING ============
$edit_cinema = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cinemas WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_cinema = $stmt->fetch();
        
        if (!$edit_cinema) {
            setFlash('Cinema not found', 'error');
            header('Location: cinemas.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
        header('Location: cinemas.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cinemas - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .cinemas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .cinema-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 25px;
            transition: all 0.3s ease;
        }
        .cinema-card:hover {
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            transform: translateY(-5px);
        }
        .cinema-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        .cinema-name {
            color: #00ffff;
            font-size: 1.3rem;
            font-weight: bold;
        }
        .cinema-location {
            color: #888;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .cinema-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            color: #00ffff;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #888;
        }
        .cinema-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-small {
            flex: 1;
            text-align: center;
            padding: 8px;
            border: 1px solid #00ffff;
            border-radius: 4px;
            color: #00ffff;
            text-decoration: none;
            font-size: 0.9rem;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php" class="active">Cinemas</a>
                <a href="screenings.php">Screenings</a>
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
            <h1>Manage Cinemas</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add New Cinema</a>
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
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 2px solid #00ffff; margin-bottom: 40px;">
                <h2 style="color: #00ffff; margin-bottom: 20px;">
                    <?php echo $edit_cinema ? 'Edit Cinema' : 'Add New Cinema'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_cinema): ?>
                        <input type="hidden" name="cinema_id" value="<?php echo $edit_cinema['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Cinema Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($edit_cinema['name'] ?? ''); ?>" 
                               required placeholder="e.g., IMAX Cinemas, SM Cinema">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($edit_cinema['location'] ?? ''); ?>" 
                               required placeholder="e.g., Mall of Asia, Ayala Center">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_screens">Total Screens *</label>
                        <input type="number" id="total_screens" name="total_screens" 
                               value="<?php echo $edit_cinema['total_screens'] ?? '4'; ?>" 
                               min="1" max="20" required>
                        <small class="form-text">Number of cinema screens/halls (1-20)</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_cinema ? 'Update Cinema' : 'Add Cinema'; ?>
                        </button>
                        <a href="cinemas.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Cinemas Grid -->
        <?php if (empty($cinemas)): ?>
            <div class="alert alert-info">No cinemas found. Click "Add New Cinema" to create your first cinema.</div>
        <?php else: ?>
            <div class="cinemas-grid">
                <?php foreach ($cinemas as $cinema): ?>
                    <div class="cinema-card">
                        <div class="cinema-header">
                            <span class="cinema-name"><?php echo htmlspecialchars($cinema['name']); ?></span>
                            <span style="color: #00ffff;">🎬 <?php echo $cinema['total_screens']; ?> Screens</span>
                        </div>
                        
                        <div class="cinema-location">
                            📍 <?php echo htmlspecialchars($cinema['location']); ?>
                        </div>
                        
                        <div class="cinema-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['total_screenings'] ?? 0; ?></div>
                                <div class="stat-label">Total Screenings</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['upcoming_screenings'] ?? 0; ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['total_screens'] * 40; ?></div>
                                <div class="stat-label">Total Seats</div>
                            </div>
                        </div>
                        
                        <div class="cinema-actions">
                            <a href="?edit=<?php echo $cinema['id']; ?>" class="btn-small">Edit</a>
                            <a href="screenings.php?cinema_id=<?php echo $cinema['id']; ?>" class="btn-small">View Screenings</a>
                            <a href="?delete=<?php echo $cinema['id']; ?>" class="btn-small delete" 
                               onclick="return confirm('Are you sure you want to delete this cinema?\n\nWARNING: This will fail if there are screenings assigned to this cinema.')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>