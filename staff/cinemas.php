<?php
// staff/cinemas.php - FIXED: Uses staff_cinemas table instead of users.cinema_id
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Get staff's assigned cinema from staff_cinemas table
$staff_cinema = getStaffCinema($user['id']);
$assigned_cinema_id = $staff_cinema ? $staff_cinema['id'] : 0;
$is_assigned = ($assigned_cinema_id > 0);

// ============ AUTO-EXPIRE OLD CINEMAS AND SCREENINGS ============
$stmt = $pdo->prepare("UPDATE screenings SET status = 'expired' WHERE show_date < CURDATE() AND status != 'expired'");
$stmt->execute();

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // For staff, verify they own this cinema
    if ($is_assigned && $assigned_cinema_id != $id) {
        setFlash('You can only delete your assigned cinema', 'error');
    } else {
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
                // Check if staff can edit this cinema
                if ($is_assigned && $assigned_cinema_id != $cinema_id) {
                    setFlash('You can only edit your assigned cinema', 'error');
                } else {
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
                }
            } else {
                // Staff can only add if not assigned to a cinema
                if ($is_assigned) {
                    setFlash('You cannot add new cinemas. You are already assigned to a cinema.', 'error');
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
    
    // Staff assigned to specific cinema
    if ($is_assigned) {
        $where[] = "c.id = ?";
        $params[] = $assigned_cinema_id;
    }
    
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
        
        // Check if staff can edit this cinema
        if ($is_assigned && $assigned_cinema_id != $edit_cinema['id']) {
            setFlash('You can only edit your assigned cinema', 'error');
            header('Location: cinemas.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
        header('Location: cinemas.php');
        exit;
    }
}

// Calculate total seats across all cinemas (for staff's view)
$total_seats = 0;
foreach ($cinemas as $cinema) {
    $total_seats += ($cinema['total_screens'] * ($cinema['seats_per_screen'] ?? 40));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cinemas - Staff Panel</title>
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
            -webkit-backdrop-filter: blur(10px);
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
            position: relative;
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
        
        .nav-links a.active::after {
            width: 60%;
        }
        
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
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
        }
        
        .staff-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
        }
        
        .filter-bar {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 20px 30px;
            margin: 30px 0;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
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
            letter-spacing: 1.5px;
            margin-bottom: 5px;
            display: block;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 15px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        .cinema-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        
        .form-group input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.2);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-group input:read-only {
            background: rgba(10, 10, 10, 0.3);
            cursor: not-allowed;
        }
        
        .form-text {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 8px;
            padding-left: 15px;
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
        
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
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
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.5;
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
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 10px;
            }
            .nav-links {
                justify-content: center;
            }
            h1 {
                font-size: 2rem;
            }
            .cinemas-grid {
                grid-template-columns: 1fr;
            }
            .cinema-stats {
                grid-template-columns: repeat(2, 1fr);
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Manage Cinemas</h1>
            <?php if (!$is_assigned): ?>
                <a href="?action=add" class="btn-primary">+ Add Cinema</a>
            <?php endif; ?>
        </div>
        
        <div class="cinema-strip"></div>
        
        <?php if ($is_assigned): ?>
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
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
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
                    
                    <div class="form-group">
                        <label>Seats Per Screen</label>
                        <input type="number" name="seats_per_screen" 
                               value="<?php echo $edit_cinema['seats_per_screen'] ?? '40'; ?>" 
                               min="10" max="500" required>
                        <small class="form-text">
                            This is the fixed number of seats in each screen. 
                            Cannot be changed once screenings are active.
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_cinema ? 'Update Cinema' : 'Add Cinema'; ?>
                        </button>
                        <a href="cinemas.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; width: 100%; align-items: flex-end;">
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
                <div class="filter-actions">
                    <button type="submit" class="btn-primary" style="padding: 10px 25px;">Apply Filters</button>
                    <a href="cinemas.php" class="btn" style="padding: 10px 25px;">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Cinemas Grid -->
        <?php if (empty($cinemas)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">No cinemas found</p>
                <p style="color: var(--text-secondary); font-size: 1rem;">
                    <?php if ($is_assigned): ?>
                        You are assigned to a cinema that doesn't exist or has been deleted.
                    <?php else: ?>
                        Click the "Add Cinema" button to create your first cinema.
                    <?php endif; ?>
                </p>
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
                            <?php if (!$is_assigned): ?>
                                <a href="?delete=<?php echo $cinema['id']; ?>" class="btn-small delete" 
                                   onclick="return confirm('Are you sure you want to delete this cinema?\n\nWARNING: This will fail if there are upcoming screenings assigned to this cinema.')">Delete</a>
                            <?php endif; ?>
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
                    <strong><?php echo $total_seats; ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>