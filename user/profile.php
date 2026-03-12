<?php
// user/profile.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];
$success = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['size'] > 0) {
    $target_dir = UPLOAD_PATH . 'profiles/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
        $errors[] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
    } elseif ($_FILES['profile_pic']['size'] > $max_size) {
        $errors[] = 'File too large. Maximum size is 2MB.';
    } else {
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
            // Delete old profile picture if exists
            if ($user['profile_pic'] && file_exists($target_dir . $user['profile_pic'])) {
                unlink($target_dir . $user['profile_pic']);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$filename, $user['id']]);
            setFlash('Profile picture updated successfully', 'success');
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = 'Failed to upload file.';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check if email exists
    if ($email != $user['email']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $user['id']]);
        if ($check->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    if (empty($errors)) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10) {
            $phone = '+63' . $phone;
        }
        
        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
        if ($stmt->execute([$first_name, $last_name, $email, $phone, $user['id']])) {
            setFlash('Profile updated successfully', 'success');
            header('Location: profile.php');
            exit;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($current)) $errors[] = 'Current password is required';
    if (empty($new)) $errors[] = 'New password is required';
    elseif (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters';
    if ($new !== $confirm) $errors[] = 'Passwords do not match';
    
    if (empty($errors)) {
        if (password_verify($current, $user['password_hash'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            if ($stmt->execute([$hash, $user['id']])) {
                setFlash('Password changed successfully', 'success');
                header('Location: profile.php');
                exit;
            }
        } else {
            $errors[] = 'Current password is incorrect';
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$stmt->execute([$user['id']]);
$total_tickets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'paid'");
$stmt->execute([$user['id']]);
$paid_tickets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE user_id = ? AND status = 'paid'");
$stmt->execute([$user['id']]);
$total_spent = $stmt->fetchColumn();

// Get linked accounts (for adults only)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("
        SELECT id, username, first_name, last_name, account_type, created_at, profile_pic
        FROM users WHERE parent_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}

// Get parent info (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - CinemaTicket</title>
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
        
        /* Profile Container */
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        /* Sidebar */
        .profile-sidebar {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .profile-avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid var(--red);
            object-fit: cover;
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
        }
        
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(229, 9, 20, 0.1);
            border: 3px solid var(--red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
        }
        
        .upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--red);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid var(--black);
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
        }
        
        .upload-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.5);
        }
        
        #profile_pic { display: none; }
        
        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .profile-username {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 40px;
            color: var(--red);
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-item {
            text-align: left;
            margin: 10px 0;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-value {
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Main Content */
        .profile-content {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .tab-button {
            padding: 12px 25px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            cursor: pointer;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .tab-button:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .tab-button.active {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
        }
        
        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-pane h2 {
            color: var(--red);
            font-size: 1.5rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .tab-pane h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--red);
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
            border-radius: 40px;
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-group small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 5px;
            padding-left: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 14px 30px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            width: 100%;
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
        
        .btn-link {
            display: inline-block;
            padding: 12px 25px;
            background: transparent;
            border: 1px solid var(--red);
            color: var(--red);
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-link:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        /* Linked Accounts */
        .linked-account-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .linked-account-card:hover {
            border-color: var(--red);
            transform: translateX(5px);
        }
        
        .linked-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--red);
            object-fit: cover;
        }
        
        .linked-avatar-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(229, 9, 20, 0.1);
            border: 2px solid var(--red);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--red);
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .account-info h4 {
            color: var(--red);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .account-info p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        /* Parent Notice */
        .parent-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
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
            margin: 20px 0;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
            
            .tab-button {
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
                <a href="movies.php">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>My Profile</h1>
        
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
        
        <!-- Parent notice for kids/teens -->
        <?php if ($user['parent_id'] && $parent): ?>
            <div class="parent-notice">
                👤 Linked to parent: <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                (<?php echo htmlspecialchars($parent['email']); ?>)
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar-container">
                    <?php if ($user['profile_pic']): ?>
                        <img src="../uploads/profiles/<?php echo $user['profile_pic']; ?>?t=<?php echo time(); ?>" class="profile-avatar">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="picForm">
                        <label for="profile_pic" class="upload-btn" title="Upload picture">📷</label>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" onchange="document.getElementById('picForm').submit()">
                    </form>
                </div>
                
                <div class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <div class="profile-badge"><?php echo strtoupper($user['account_type']); ?> ACCOUNT</div>
                
                <div class="profile-stats">
                    <div>
                        <div class="stat-value"><?php echo $total_tickets; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $paid_tickets; ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                    <div>
                        <div class="stat-value">$<?php echo number_format($total_spent, 0); ?></div>
                        <div class="stat-label">Spent</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('edit-profile', this)">Edit Profile</button>
                    <button class="tab-button" onclick="showTab('change-password', this)">Change Password</button>
                    <?php if ($user['account_type'] == 'adult'): ?>
                        <button class="tab-button" onclick="showTab('linked-accounts', this)">Linked Accounts</button>
                    <?php endif; ?>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit-profile" class="tab-pane active">
                    <h2>Edit Profile</h2>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars(str_replace('+63', '', $user['phone'] ?? '')); ?>" placeholder="9123456789">
                            <small>Enter 10 digits</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div id="change-password" class="tab-pane">
                    <h2>Change Password</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
                
                <!-- Linked Accounts Tab (Adults Only) -->
                <?php if ($user['account_type'] == 'adult'): ?>
                    <div id="linked-accounts" class="tab-pane">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
                            <h2 style="margin:0;">Linked Family Accounts</h2>
                            <a href="../auth/link_account.php" class="btn-link">➕ Add Linked Account</a>
                        </div>
                        
                        <?php if (empty($linked_accounts)): ?>
                            <p style="color:var(--text-secondary);">No linked accounts yet. Click "Add Linked Account" to create accounts for your children.</p>
                        <?php else: ?>
                            <?php foreach ($linked_accounts as $account): ?>
                                <div class="linked-account-card">
                                    <?php if ($account['profile_pic']): ?>
                                        <img src="../uploads/profiles/<?php echo $account['profile_pic']; ?>" class="linked-avatar">
                                    <?php else: ?>
                                        <div class="linked-avatar-placeholder">
                                            <?php echo strtoupper(substr($account['first_name'],0,1) . substr($account['last_name'],0,1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="account-info">
                                        <h4><?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?></h4>
                                        <p>@<?php echo htmlspecialchars($account['username']); ?> • <?php echo strtoupper($account['account_type']); ?></p>
                                        <p>Joined: <?php echo date('M d, Y', strtotime($account['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        function showTab(tabId, element) {
            document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            element.classList.add('active');
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>