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
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 30px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Staff Notice */
        .staff-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
        }
        
        /* Cinemas Grid */
        .cinemas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .cinema-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            padding: 25px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .cinema-card::before {
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
        
        .cinema-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .cinema-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .cinema-name {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        .screens-badge {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 30px;
            padding: 4px 12px;
            color: var(--red);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .cinema-location {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .cinema-location i {
            color: var(--red);
        }
        
        .cinema-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            color: var(--red);
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cinema-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-small {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
            background: transparent;
            font-weight: 500;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-small.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-small.delete:hover {
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
            padding: 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .form-container h2 {
            color: var(--red);
            font-size: 2rem;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-text {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 5px;
            padding-left: 15px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
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
            padding: 14px 30px;
            border-radius: 40px;
            background: transparent;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            border-color: var(--red);
            color: var(--red);
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '🎬';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 6rem;
            opacity: 0.03;
            pointer-events: none;
        }
        
        .empty-state p {
            color: var(--text-secondary);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .cinema-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .cinema-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
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
        <h1>Manage Cinemas</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if ($user['cinema_id']): ?>
            <div class="staff-notice">
                ℹ️ You are assigned to a specific cinema. You can only view and manage that cinema.
            </div>
        <?php endif; ?>
        
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
                    <?php echo $edit_cinema ? 'Edit Cinema' : 'Add New Cinema'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_cinema): ?>
                        <input type="hidden" name="cinema_id" value="<?php echo $edit_cinema['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Cinema Name</label>
                        <input type="text" name="name" 
                               value="<?php echo htmlspecialchars($edit_cinema['name'] ?? ''); ?>" 
                               required placeholder="e.g., IMAX Cinemas, SM Cinema">
                    </div>
                    
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" 
                               value="<?php echo htmlspecialchars($edit_cinema['location'] ?? ''); ?>" 
                               required placeholder="e.g., Mall of Asia, Ayala Center">
                    </div>
                    
                    <div class="form-group">
                        <label>Total Screens</label>
                        <input type="number" name="total_screens" 
                               value="<?php echo $edit_cinema['total_screens'] ?? '4'; ?>" 
                               min="1" max="20" required>
                        <small class="form-text">Number of cinema screens/halls (1-20)</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_cinema ? 'Update Cinema' : 'Add Cinema'; ?>
                        </button>
                        <a href="cinemas.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Cinemas Grid -->
        <?php if (empty($cinemas)): ?>
            <div class="empty-state">
                <p style="margin-bottom: 10px;">No cinemas found.</p>
                <?php if (!$user['cinema_id']): ?>
                    <p style="color: var(--text-secondary);">Click "Add New Cinema" to create your first cinema.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="cinemas-grid">
                <?php foreach ($cinemas as $cinema): ?>
                    <div class="cinema-card">
                        <div class="cinema-header">
                            <span class="cinema-name"><?php echo htmlspecialchars($cinema['name']); ?></span>
                            <span class="screens-badge">🎬 <?php echo $cinema['total_screens']; ?> Screens</span>
                        </div>
                        
                        <div class="cinema-location">
                            <i>📍</i> <?php echo htmlspecialchars($cinema['location']); ?>
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
                            <a href="screenings.php?cinema_id=<?php echo $cinema['id']; ?>" class="btn-small">Screenings</a>
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