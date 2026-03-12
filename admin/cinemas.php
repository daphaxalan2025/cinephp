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
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
            font-size: 1.4rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 1px;
        }
        
        .cinema-badge {
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
            margin-bottom: 20px;
            font-size: 0.95rem;
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
            position: relative;
        }
        
        .stat-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 30px;
            width: 1px;
            background: rgba(229, 9, 20, 0.2);
        }
        
        .stat-value {
            font-size: 1.5rem;
            color: var(--red);
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            line-height: 1.2;
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
            font-size: 0.8rem;
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .btn-small:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-small.delete {
            border-color: rgba(255, 68, 68, 0.3);
            color: #ff4444;
        }
        
        .btn-small.delete:hover {
            border-color: #ff4444;
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            margin-top: 30px;
            margin-bottom: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 2.2rem;
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
            width: 80px;
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
            letter-spacing: 1.5px;
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-text {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-weight: 300;
            letter-spacing: 0.5px;
            padding-left: 15px;
        }
        
        /* Buttons */
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
        
        /* Alerts */
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
            margin: 30px 0;
            opacity: 0.5;
        }
        
        /* Stats Summary */
        .stats-bar {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .stat-summary {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .stat-summary:hover {
            border-color: var(--red);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.15);
        }
        
        .stat-summary span {
            color: var(--text-secondary);
            font-weight: 400;
            margin-right: 10px;
        }
        
        .stat-summary strong {
            color: var(--red);
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .cinemas-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-direction: column;
                align-items: flex-end;
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
                <a href="cinemas.php" class="active">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online</a>
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
            <a href="?action=add" class="btn-primary">+ Add Cinema</a>
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
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_cinema ? 'Update Cinema' : 'Add Cinema'; ?>
                        </button>
                        <a href="cinemas.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Cinemas Grid -->
        <?php if (empty($cinemas)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">No cinemas found</p>
                <p style="color: var(--text-secondary); font-size: 1rem;">Click the "Add Cinema" button to create your first cinema.</p>
            </div>
        <?php else: ?>
            <div class="cinemas-grid">
                <?php foreach ($cinemas as $cinema): ?>
                    <div class="cinema-card">
                        <div class="cinema-header">
                            <span class="cinema-name"><?php echo htmlspecialchars($cinema['name']); ?></span>
                            <span class="cinema-badge"><?php echo $cinema['total_screens']; ?> Screens</span>
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
                                <div class="stat-value"><?php echo $cinema['total_screens'] * 40; ?></div>
                                <div class="stat-label">Seats</div>
                            </div>
                        </div>
                        
                        <div class="cinema-actions">
                            <a href="?edit=<?php echo $cinema['id']; ?>" class="btn-small">Edit</a>
                            <a href="screenings.php?cinema_id=<?php echo $cinema['id']; ?>" class="btn-small">Screenings</a>
                            <a href="?delete=<?php echo $cinema['id']; ?>" class="btn-small delete" 
                               onclick="return confirm('Are you sure you want to delete this cinema?\n\nWARNING: This will fail if there are screenings assigned to this cinema.')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Stats Summary -->
            <div class="stats-bar">
                <div class="stat-summary">
                    <span>Total Cinemas</span>
                    <strong><?php echo count($cinemas); ?></strong>
                </div>
                <div class="stat-summary">
                    <span>Total Screens</span>
                    <strong><?php echo array_sum(array_column($cinemas, 'total_screens')); ?></strong>
                </div>
                <div class="stat-summary">
                    <span>Total Seats</span>
                    <strong><?php echo array_sum(array_column($cinemas, 'total_screens')) * 40; ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>