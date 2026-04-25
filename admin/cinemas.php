<?php
// admin/cinemas.php - ADDED seats_per_screen field
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// ============ AUTO-EXPIRE OLD CINEMAS AND SCREENINGS ============
$stmt = $pdo->prepare("UPDATE screenings SET status = 'expired' WHERE show_date < CURDATE() AND status != 'expired'");
$stmt->execute();

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ? AND show_date >= CURDATE() AND status != 'expired'");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete cinema with upcoming screenings. Delete the screenings first.', 'error');
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
    $seats_per_screen = intval($_POST['seats_per_screen'] ?? 40);
    $cinema_id = $_POST['cinema_id'] ?? '';
    
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
    
    if ($seats_per_screen < 10) {
        $errors[] = 'Seats per screen must be at least 10';
    } elseif ($seats_per_screen > 500) {
        $errors[] = 'Seats per screen cannot exceed 500';
    }
    
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
    
    if (empty($errors)) {
        try {
            if ($cinema_id) {
                $sql = "UPDATE cinemas SET name = ?, location = ?, total_screens = ?, seats_per_screen = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$name, $location, $total_screens, $seats_per_screen, $cinema_id]);
                
                if ($result) {
                    setFlash('Cinema updated successfully', 'success');
                    header('Location: cinemas.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update cinema';
                }
            } else {
                $sql = "INSERT INTO cinemas (name, location, total_screens, seats_per_screen) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$name, $location, $total_screens, $seats_per_screen]);
                
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

// ============ GET FILTER PARAMETERS ============
$filter_status = $_GET['status'] ?? 'all';
$filter_search = trim($_GET['search'] ?? '');

// ============ GET ALL CINEMAS WITH FILTER ============
try {
    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND status != 'expired' AND show_date >= CURDATE()) as total_screenings,
               (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND show_date >= CURDATE() AND status != 'expired') as upcoming_screenings,
               (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND status = 'expired') as expired_screenings
        FROM cinemas c
    ";
    
    $where = [];
    $params = [];
    
    if ($filter_search) {
        $where[] = "(c.name LIKE ? OR c.location LIKE ?)";
        $params[] = "%$filter_search%";
        $params[] = "%$filter_search%";
    }
    
    if ($filter_status === 'has_upcoming') {
        $where[] = "EXISTS (SELECT 1 FROM screenings WHERE cinema_id = c.id AND show_date >= CURDATE() AND status != 'expired')";
    } elseif ($filter_status === 'no_upcoming') {
        $where[] = "NOT EXISTS (SELECT 1 FROM screenings WHERE cinema_id = c.id AND show_date >= CURDATE() AND status != 'expired')";
    } elseif ($filter_status === 'has_expired') {
        $where[] = "EXISTS (SELECT 1 FROM screenings WHERE cinema_id = c.id AND status = 'expired')";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

// Calculate total seats (sum of total_screens * seats_per_screen)
$total_seats = 0;
foreach ($cinemas as $c) {
    $total_seats += $c['total_screens'] * ($c['seats_per_screen'] ?? 40);
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
        /* ... (keep existing styles from original) ... */
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            min-height: 100vh;
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
        }
        
        .logo::before { content: "🎬"; margin-right: 8px; }
        
        .nav-links { display: flex; gap: 5px; flex-wrap: wrap; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .nav-links a:hover { color: var(--red); }
        .nav-links a.active { color: var(--red); }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 30px 0;
        }
        
        .filter-bar {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 20px 30px;
            margin: 30px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            margin-bottom: 5px;
            display: block;
        }
        
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px 15px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
        }
        
        .cinemas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .cinema-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s;
        }
        
        .cinema-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
        }
        
        .cinema-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .cinema-name { color: var(--red); font-size: 1.4rem; font-weight: 700; }
        .cinema-badge { background: rgba(229, 9, 20, 0.15); border: 1px solid var(--red); border-radius: 30px; padding: 4px 12px; font-size: 0.8rem; }
        
        .cinema-location { color: var(--text-secondary); margin-bottom: 20px; }
        
        .cinema-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; color: var(--red); font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; }
        
        .cinema-actions { display: flex; gap: 10px; margin-top: 20px; }
        
        .btn-small {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .btn-small:hover { border-color: var(--red); color: var(--red); }
        .btn-small.delete { border-color: rgba(255, 68, 68, 0.3); color: #ff4444; }
        
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            margin-top: 30px;
        }
        
        .form-container h2 { color: #fff; font-size: 2rem; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { color: var(--red); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; margin-bottom: 8px; display: block; }
        .form-group input { width: 100%; padding: 15px 20px; background: rgba(10, 10, 10, 0.6); border: 1px solid rgba(229, 9, 20, 0.2); border-radius: 40px; color: var(--text-primary); }
        .form-group input:focus { outline: none; border-color: var(--red); }
        .form-text { display: block; font-size: 0.75rem; color: var(--text-secondary); margin-top: 8px; }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
        }
        
        .btn-primary:hover { background: var(--red-dark); transform: translateY(-2px); }
        
        .btn {
            border: 1px solid rgba(229, 9, 20, 0.3);
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .btn:hover { border-color: var(--red); color: var(--red); }
        
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-error { border-left: 4px solid #ff4444; color: #ff6b6b; }
        .alert-success { border-left: 4px solid #44ff44; color: #44ff44; }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.3;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .stat-summary {
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
        }
        
        .stat-summary span { color: var(--text-secondary); margin-right: 10px; }
        .stat-summary strong { color: var(--red); font-size: 1.1rem; }
        
        @media (max-width: 768px) {
            .cinemas-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .cinema-stats { grid-template-columns: repeat(2, 1fr); }
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
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Manage Cinemas</h1>
            <a href="?action=add" class="btn-primary">+ Add Cinema</a>
        </div>
        
        <div class="cinema-strip"></div>
        
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Cinema name or location..." value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
                <div class="filter-group">
                    <label>Filter by Status</label>
                    <select name="status">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Cinemas</option>
                        <option value="has_upcoming" <?php echo $filter_status == 'has_upcoming' ? 'selected' : ''; ?>>Has Upcoming Screenings</option>
                        <option value="no_upcoming" <?php echo $filter_status == 'no_upcoming' ? 'selected' : ''; ?>>No Upcoming Screenings</option>
                        <option value="has_expired" <?php echo $filter_status == 'has_expired' ? 'selected' : ''; ?>>Has Expired Screenings</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="padding: 10px 25px;">Apply</button>
                    <a href="cinemas.php" class="btn" style="padding: 10px 25px;">Clear</a>
                </div>
            </form>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $error) echo "<li>$error</li>"; ?></ul>
            </div>
        <?php endif; ?>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-container">
                <h2><?php echo $edit_cinema ? 'Edit Cinema' : 'Add New Cinema'; ?></h2>
                
                <form method="POST">
                    <?php if ($edit_cinema): ?>
                        <input type="hidden" name="cinema_id" value="<?php echo $edit_cinema['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Cinema Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_cinema['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($edit_cinema['location'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Screens</label>
                        <input type="number" name="total_screens" value="<?php echo $edit_cinema['total_screens'] ?? '4'; ?>" min="1" max="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Seats Per Screen</label>
                        <input type="number" name="seats_per_screen" value="<?php echo $edit_cinema['seats_per_screen'] ?? '40'; ?>" min="10" max="500" required>
                        <small class="form-text">This is the fixed number of seats in each screen. Cannot be changed once screenings are active.</small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary"><?php echo $edit_cinema ? 'Update Cinema' : 'Add Cinema'; ?></button>
                        <a href="cinemas.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <?php if (empty($cinemas)): ?>
            <div class="alert" style="text-align: center; padding: 60px;">
                <p>No cinemas found. Click "Add Cinema" to create your first cinema.</p>
            </div>
        <?php else: ?>
            <div class="cinemas-grid">
                <?php foreach ($cinemas as $cinema): ?>
                    <div class="cinema-card">
                        <div class="cinema-header">
                            <span class="cinema-name"><?php echo htmlspecialchars($cinema['name']); ?></span>
                            <span class="cinema-badge"><?php echo $cinema['total_screens']; ?> Screens</span>
                        </div>
                        <div class="cinema-location">📍 <?php echo htmlspecialchars($cinema['location']); ?></div>
                        
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
                                <div class="stat-value"><?php echo $cinema['expired_screenings'] ?? 0; ?></div>
                                <div class="stat-label">Expired</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $cinema['seats_per_screen'] ?? 40; ?></div>
                                <div class="stat-label">Seats/Screen</div>
                            </div>
                        </div>
                        
                        <div class="cinema-actions">
                            <a href="?edit=<?php echo $cinema['id']; ?>" class="btn-small">Edit</a>
                            <a href="screenings.php?cinema_id=<?php echo $cinema['id']; ?>" class="btn-small">Screenings</a>
                            <a href="?delete=<?php echo $cinema['id']; ?>" class="btn-small delete" onclick="return confirm('Delete this cinema?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="stats-bar">
                <div class="stat-summary"><span>Total Cinemas</span><strong><?php echo count($cinemas); ?></strong></div>
                <div class="stat-summary"><span>Total Screens</span><strong><?php echo array_sum(array_column($cinemas, 'total_screens')); ?></strong></div>
                <div class="stat-summary"><span>Total Seats</span><strong><?php echo $total_seats; ?></strong></div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>