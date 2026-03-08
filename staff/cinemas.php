<?php
// staff/cinemas.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Check if staff has cinema assignment
$cinema_filter = "";
if ($user['cinema_id']) {
    $cinema_filter = "WHERE id = " . $user['cinema_id'];
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if cinema has screenings
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        setFlash('Cannot delete cinema with existing screenings', 'error');
    } else {
        $stmt = $pdo->prepare("DELETE FROM cinemas WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('Cinema deleted successfully', 'success');
        }
    }
    header('Location: cinemas.php');
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $total_screens = intval($_POST['total_screens'] ?? 1);
    $cinema_id = $_POST['cinema_id'] ?? '';
    
    // Validation
    if (empty($name)) $errors[] = 'Cinema name is required';
    if (strlen($name) < 3) $errors[] = 'Cinema name must be at least 3 characters';
    if (empty($location)) $errors[] = 'Location is required';
    if ($total_screens < 1 || $total_screens > 20) $errors[] = 'Total screens must be between 1 and 20';
    
    // Check for duplicate name
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM cinemas WHERE name = ? AND id != ?");
        $check->execute([$name, $cinema_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Cinema with this name already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($cinema_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE cinemas SET name=?, location=?, total_screens=? WHERE id=?");
                $stmt->execute([$name, $location, $total_screens, $cinema_id]);
                setFlash('Cinema updated successfully', 'success');
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO cinemas (name, location, total_screens) VALUES (?, ?, ?)");
                $stmt->execute([$name, $location, $total_screens]);
                setFlash('Cinema added successfully', 'success');
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: cinemas.php');
        exit;
    }
}

// Get all cinemas with stats
$stmt = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id) as total_screenings,
           (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND show_date >= CURDATE()) as upcoming_screenings,
           (SELECT COUNT(DISTINCT movie_id) FROM screenings WHERE cinema_id = c.id) as movies_showing
    FROM cinemas c
    $cinema_filter
    ORDER BY c.name
");
$cinemas = $stmt->fetchAll();

// Get cinema for editing
$edit_cinema = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM cinemas WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_cinema = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cinemas - Staff</title>
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
            transition: all 0.3s;
        }
        .cinema-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 30px rgba(0,255,255,0.3);
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
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
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
                <a href="cinemas.php" class="active">Cinemas</a>
                <a href="screenings.php">Screenings</a>
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
            <h1>Manage Cinemas</h1>
            <?php if (!$user['cinema_id']): ?>
                <a href="?action=add" class="btn btn-primary">➕ Add New Cinema</a>
            <?php endif; ?>
        </div>
        
        <?php if ($user['cinema_id']): ?>
            <div class="staff-notice">
                ℹ️ You are assigned to a specific cinema. You can only view and manage that cinema.
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
                    <?php echo $edit_cinema ? 'Edit Cinema' : 'Add New Cinema'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_cinema): ?>
                        <input type="hidden" name="cinema_id" value="<?php echo $edit_cinema['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Cinema Name</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($edit_cinema['name'] ?? ''); ?>" 
                               required placeholder="e.g., IMAX Cinemas, SM Cinema">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo htmlspecialchars($edit_cinema['location'] ?? ''); ?>" 
                               required placeholder="e.g., Mall of Asia, Ayala Center">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_screens">Total Screens</label>
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
            <div style="text-align: center; padding: 60px; background: #1a1a1a; border: 2px solid #00ffff; border-radius: 8px;">
                <p style="color: #888;">No cinemas found.</p>
                <?php if (!$user['cinema_id']): ?>
                    <p style="color: #888; margin-top: 10px;">Click "Add New Cinema" to create your first cinema.</p>
                <?php endif; ?>
            </div>
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
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['upcoming_screenings'] ?? 0; ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['movies_showing'] ?? 0; ?></div>
                                <div class="stat-label">Movies</div>
                            </div>
                        </div>
                        
                        <div class="cinema-actions">
                            <a href="?edit=<?php echo $cinema['id']; ?>" class="btn-small">Edit</a>
                            <a href="screenings.php?cinema_id=<?php echo $cinema['id']; ?>" class="btn-small">View Screenings</a>
                            <?php if (!$user['cinema_id']): ?>
                                <a href="?delete=<?php echo $cinema['id']; ?>" class="btn-small delete" 
                                   onclick="return confirm('Delete this cinema?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>