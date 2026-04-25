<?php
// admin/users.php - FIXED: Added Staff Cinema Assignment
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    if ($id == $_SESSION['user_id']) {
        setFlash('You cannot delete your own account', 'error');
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
            $stmt->execute([$id]);
            $ticket_count = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
            $stmt->execute([$id]);
            $payment_count = $stmt->fetchColumn();
            
            if ($ticket_count > 0 || $payment_count > 0) {
                setFlash('Cannot delete user with existing tickets or payments. Deactivate instead.', 'error');
            } else {
                // Also delete staff_cinemas entries
                $stmt = $pdo->prepare("DELETE FROM staff_cinemas WHERE staff_id = ?");
                $stmt->execute([$id]);
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$id])) {
                    setFlash('User deleted successfully', 'success');
                }
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
    }
    header('Location: users.php');
    exit;
}

// ============ HANDLE TOGGLE STATUS ============
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('User status updated', 'success');
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: users.php');
    exit;
}

// ============ HANDLE STAFF CINEMA ASSIGNMENT ============
if (isset($_GET['assign_cinema'])) {
    $staff_id = $_GET['assign_cinema'];
    $cinema_id = $_POST['cinema_id'] ?? 0;
    
    try {
        // First, remove existing assignments
        $stmt = $pdo->prepare("DELETE FROM staff_cinemas WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        
        if ($cinema_id > 0) {
            // Add new assignment
            $stmt = $pdo->prepare("INSERT INTO staff_cinemas (staff_id, cinema_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$staff_id, $cinema_id, $_SESSION['user_id']]);
            setFlash("Staff member assigned to cinema successfully", 'success');
        } else {
            setFlash("Staff cinema assignment removed", 'success');
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: users.php');
    exit;
}

// ============ HANDLE ADD/EDIT ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['search'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $account_type = $_POST['account_type'] ?? 'user';
    $user_id = $_POST['user_id'] ?? '';
    
    // Validation
    if (empty($username)) $errors[] = 'Username is required';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
    
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    if (!$user_id && empty($password)) $errors[] = 'Password is required for new users';
    elseif ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    
    // Check unique username
    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $user_id ?: 0]);
        if ($check->fetch()) $errors[] = 'Username already exists';
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    // Check unique email
    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $user_id ?: 0]);
        if ($check->fetch()) $errors[] = 'Email already exists';
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            if ($user_id) {
                // Update
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password_hash=?, first_name=?, last_name=?, account_type=? WHERE id=?");
                    $stmt->execute([$username, $email, $hash, $first_name, $last_name, $account_type, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, first_name=?, last_name=?, account_type=? WHERE id=?");
                    $stmt->execute([$username, $email, $first_name, $last_name, $account_type, $user_id]);
                }
                setFlash('User updated successfully', 'success');
            } else {
                // Insert with all required columns
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $birthdate = '2000-01-01';
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, email, password_hash, first_name, last_name,
                        birthdate, account_type, gender, country, phone,
                        parent_id, cinema_id, is_active, theme_preference
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username, $email, $hash, $first_name, $last_name,
                    $birthdate, $account_type, 'other', 'PH', '0000000000',
                    null, null, 1, 'dark'
                ]);
                setFlash('User added successfully', 'success');
                
                // If adding a staff member, they need cinema assignment
                if ($account_type == 'staff') {
                    setFlash('Note: Staff member needs to be assigned to a cinema from the table below.', 'info');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: users.php');
        exit;
    }
}

// ============ HANDLE SEARCH ============
$search_term = '';
$search_sql = "";
$search_params = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $search_sql = "WHERE (u.id LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.account_type LIKE ?)";
    $like = "%{$search_term}%";
    $search_params = [$like, $like, $like, $like, $like, $like];
}

// Get all users with payment stats (with optional search)
try {
    $sql = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count,
               (SELECT COUNT(*) FROM payments WHERE user_id = u.id) as payment_count,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = u.id AND payment_status = 'completed') as total_spent
        FROM users u
        {$search_sql}
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($search_params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    setFlash('Error loading users: ' . $e->getMessage(), 'error');
}

// Get all cinemas for staff assignment dropdown
$all_cinemas = $pdo->query("SELECT id, name FROM cinemas ORDER BY name")->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_user = $stmt->fetch();
        if (!$edit_user) {
            setFlash('User not found', 'error');
            header('Location: users.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
        header('Location: users.php');
        exit;
    }
}

$account_types = ['user', 'staff', 'admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CinemaTicket</title>
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
        
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
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
        
        h1, h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
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
            margin-bottom: 20px;
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
            padding: 14px 20px;
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
        
        .form-group input[readonly] {
            background: rgba(30, 30, 30, 0.4);
            border-color: rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
            cursor: not-allowed;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 28px;
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
            padding: 14px 28px;
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
        
        /* ========== ORGANIZED ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 0.7rem;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
        }
        
        .btn-action:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-action.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-action.delete:hover {
            background: #ff4444;
            color: #fff;
            border-color: #ff4444;
        }
        
        .btn-action.enable {
            border-color: #44ff44;
            color: #44ff44;
        }
        
        .btn-action.enable:hover {
            background: #44ff44;
            color: #000;
            border-color: #44ff44;
        }
        
        /* Search Bar Styles */
        .search-container {
            margin: 30px 0 20px 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 50px;
            padding: 5px;
        }
        
        .search-input {
            background: transparent;
            border: none;
            padding: 12px 20px;
            color: var(--text-primary);
            font-size: 0.9rem;
            width: 250px;
            outline: none;
        }
        
        .search-input::placeholder {
            color: var(--text-secondary);
        }
        
        .search-btn {
            background: var(--red);
            border: none;
            border-radius: 50px;
            padding: 0 20px;
            color: white;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .search-btn:hover {
            background: var(--red-dark);
            transform: scale(1.02);
        }
        
        .clear-search {
            background: rgba(50, 50, 50, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 50px;
            padding: 0 20px;
            color: var(--text-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .clear-search:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .search-results-info {
            margin: 15px 0;
            padding: 10px 15px;
            background: rgba(229, 9, 20, 0.1);
            border-radius: 30px;
            display: inline-block;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        /* Table and other styles remain unchanged */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            overflow-x: auto;
            margin-top: 30px;
            padding: 5px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            min-width: 1200px;
        }
        
        .data-table th {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-primary);
        }
        
        .data-table tr:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-active {
            background: rgba(68, 255, 68, 0.15);
            border: 1px solid #44ff44;
            color: #44ff44;
        }
        
        .status-inactive {
            background: rgba(255, 68, 68, 0.15);
            border: 1px solid #ff4444;
            color: #ff4444;
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 30px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .user-id {
            color: var(--red);
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
        }
        
        .username {
            color: #fff;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
        }
        
        .total-spent {
            color: var(--red);
            font-weight: 700;
        }
        
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
        
        .stat-item {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            padding: 12px 25px;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            border-color: var(--red);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.15);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 400;
            margin-right: 10px;
        }
        
        .stat-value {
            color: var(--red);
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .stat-value.active {
            color: #44ff44;
        }
        
        .stat-value.inactive {
            color: #ff4444;
        }
        
        .cinema-assignment {
            background: rgba(229, 9, 20, 0.1);
            border-radius: 8px;
            padding: 8px;
            margin-top: 5px;
        }
        
        .cinema-select {
            padding: 6px 10px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 20px;
            color: #fff;
            font-size: 0.7rem;
            width: 140px;
        }
        
        .assign-btn {
            padding: 4px 10px;
            font-size: 0.65rem;
            background: var(--red);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .assign-btn:hover {
            background: var(--red-dark);
            transform: translateY(-1px);
        }
        
        .assigned-cinema {
            font-size: 0.7rem;
            color: #44ff44;
            margin-top: 3px;
        }
        
        .not-assigned {
            font-size: 0.7rem;
            color: #ff8844;
            margin-top: 3px;
        }
        
        @media (max-width: 1200px) {
            .nav-links a {
                padding: 5px 8px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 1024px) {
            .nav-container {
                padding: 0 15px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 10px;
            }
            .nav-links {
                justify-content: center;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            h1 {
                font-size: 2rem;
            }
            .stats-bar {
                flex-direction: column;
                align-items: flex-end;
            }
            .action-buttons {
                justify-content: center;
            }
            .search-container {
                justify-content: center;
            }
            .search-form {
                width: 100%;
            }
            .search-input {
                width: 100%;
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
                <a href="online_schedule.php">Online</a>
                <a href="users.php" class="active">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <h1>User Management</h1>
            <a href="?action=add" class="btn-primary">+ Add User</a>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search by ID, Username, Name, Email, Type..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="search-btn">🔍 Search</button>
            </form>
            <?php if (!empty($search_term)): ?>
                <a href="users.php" class="clear-search">✕ Clear</a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($search_term)): ?>
            <div class="search-results-info">
                🔍 Showing results for: <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong> | 
                Found <strong><?php echo count($users); ?></strong> user(s)
            </div>
        <?php endif; ?>
        
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
        
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div class="form-container">
                <h2><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h2>
                <form method="POST">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" required <?php echo $edit_user ? 'readonly' : ''; ?>>
                            <?php if ($edit_user): ?>
                                <small class="form-text">Username cannot be changed</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php echo $edit_user ? 'New Password' : 'Password'; ?></label>
                            <input type="password" name="password" <?php echo !$edit_user ? 'required' : ''; ?> placeholder="<?php echo $edit_user ? 'Leave blank to keep current' : 'Enter password'; ?>">
                            <?php if ($edit_user): ?>
                                <small class="form-text">Minimum 6 characters if changing</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Account Type</label>
                            <select name="account_type" required>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo (($edit_user['account_type'] ?? 'user') == $type) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 40px;">
                        <button type="submit" class="btn-primary"><?php echo $edit_user ? 'Update User' : 'Add User'; ?></button>
                        <a href="users.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <?php if (empty($users)): ?>
            <div class="alert alert-info" style="text-align: center; padding: 60px 40px; margin-top: 30px;">
                <p style="font-size: 1.3rem; margin-bottom: 20px; color: #fff;">
                    <?php echo !empty($search_term) ? 'No users found matching your search.' : 'No users found'; ?>
                </p>
                <p style="color: var(--text-secondary); font-size: 1rem;">
                    <?php echo !empty($search_term) ? 'Try a different search term or clear the search.' : 'Click the "Add User" button to create your first user.'; ?>
                </p>
                <?php if (!empty($search_term)): ?>
                    <a href="users.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">View All Users</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Assigned Cinema</th>
                            <th>Status</th>
                            <th>Tickets</th>
                            <th>Payments</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><span class="user-id">#<?php echo $user['id']; ?></span></td>
                                <td><span class="username"><?php echo htmlspecialchars($user['username']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="type-badge"><?php echo strtoupper($user['account_type']); ?></span></td>
                                
                                <!-- Assigned Cinema Column -->
                                <td>
                                    <?php if ($user['account_type'] == 'staff'): ?>
                                        <?php 
                                        $staff_cinema = getStaffCinema($user['id']);
                                        ?>
                                        <form method="POST" action="?assign_cinema=<?php echo $user['id']; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                            <select name="cinema_id" class="cinema-select">
                                                <option value="0">-- None --</option>
                                                <?php foreach ($all_cinemas as $cinema): ?>
                                                    <option value="<?php echo $cinema['id']; ?>" <?php echo ($staff_cinema && $staff_cinema['id'] == $cinema['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cinema['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="assign-btn">Assign</button>
                                        </form>
                                        <?php if ($staff_cinema): ?>
                                            <div class="assigned-cinema">
                                                ✓ Current: <?php echo htmlspecialchars($staff_cinema['name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="not-assigned">
                                                ⚠️ Not assigned to any cinema
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.7rem;">— Not applicable —</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['ticket_count']; ?></td>
                                <td><?php echo $user['payment_count']; ?></td>
                                <td><span class="total-spent">₱<?php echo number_format($user['total_spent'], 2); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $user['id']; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="btn-action">Edit</a>
                                        <a href="?toggle=<?php echo $user['id']; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="btn-action <?php echo $user['is_active'] ? 'delete' : 'enable'; ?>">
                                            <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete=<?php echo $user['id']; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" class="btn-action delete" onclick="return confirm('Delete this user? This action cannot be undone.')">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-label"><?php echo !empty($search_term) ? 'Search Results' : 'Total Users'; ?></span>
                    <span class="stat-value"><?php echo count($users); ?></span>
                </div>
                <?php if (empty($search_term)): ?>
                <div class="stat-item">
                    <span class="stat-label">Active</span>
                    <span class="stat-value active"><?php echo count(array_filter($users, fn($u) => $u['is_active'])); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Inactive</span>
                    <span class="stat-value inactive"><?php echo count(array_filter($users, fn($u) => !$u['is_active'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>