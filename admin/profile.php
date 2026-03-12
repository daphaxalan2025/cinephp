<?php
// admin/profile.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($first_name)) $errors[] = 'First name is required';
        if (empty($last_name)) $errors[] = 'Last name is required';
        if (empty($email)) $errors[] = 'Email is required';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        
        // Check if email exists (excluding current user)
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
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current)) $errors[] = 'Current password is required';
        if (empty($new)) $errors[] = 'New password is required';
        elseif (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters';
        if ($new !== $confirm) $errors[] = 'Passwords do not match';
        
        if (empty($errors)) {
            // Verify current password
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
}

// Get admin stats
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_movies' => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
    'total_screenings' => $pdo->query("SELECT COUNT(*) FROM screenings WHERE show_date >= CURDATE()")->fetchColumn(),
    'total_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tickets WHERE status = 'paid'")->fetchColumn()
];

// Get recent activity
$recent_activity = $pdo->query("
    (SELECT 'ticket' as type, ticket_code as ref, CONCAT('New ticket purchased for ', m.title) as description, purchase_date as date
     FROM tickets t
     JOIN screenings s ON t.screening_id = s.id
     JOIN movies m ON s.movie_id = m.id
     ORDER BY purchase_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'user' as type, username as ref, CONCAT('New user registered: ', username) as description, created_at as date
     FROM users
     ORDER BY created_at DESC LIMIT 5)
    ORDER BY date DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - CinemaTicket</title>
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
            margin: 0 0 30px 0;
            text-transform: uppercase;
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
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
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
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            background: rgba(229, 9, 20, 0.1);
            border: 3px solid var(--red);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .profile-avatar::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(229, 9, 20, 0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-avatar:hover::after {
            opacity: 1;
        }
        
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
            font-size: 1rem;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            border-radius: 40px;
            color: var(--red);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 25px;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 25px 0;
            padding: 20px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 5px;
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
            margin-bottom: 15px;
            padding: 0 10px;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .info-value i {
            color: var(--red);
            margin-right: 5px;
        }
        
        /* Main Content */
        .profile-content {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }
        
        /* Tab Buttons */
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding-bottom: 15px;
        }
        
        .tab-button {
            padding: 12px 25px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            cursor: pointer;
            border-radius: 40px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .tab-button:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .tab-button.active {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
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
            font-size: 1.8rem;
            margin-bottom: 25px;
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
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 20px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-text {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 8px;
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
        
        /* Activity Items */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(10, 10, 10, 0.4);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            border-color: rgba(229, 9, 20, 0.3);
            background: rgba(20, 20, 20, 0.6);
            transform: translateX(5px);
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--red);
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-description {
            color: #fff;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .activity-time {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .activity-ref {
            color: var(--red);
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.8rem;
            padding: 4px 10px;
            background: rgba(229, 9, 20, 0.1);
            border-radius: 30px;
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
        
        .alert-error {
            border-left-color: var(--red);
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
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Admin Profile</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <div class="profile-badge">Administrator</div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Users</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_movies']; ?></div>
                        <div class="stat-label">Movies</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_screenings']; ?></div>
                        <div class="stat-label">Screenings</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                        <div class="stat-label">Tickets</div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">📧 Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📱 Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📅 Member Since</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">⏱️ Last Login</div>
                    <div class="info-value"><?php echo $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'First login'; ?></div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('edit-profile', this)">Edit Profile</button>
                    <button class="tab-button" onclick="showTab('change-password', this)">Change Password</button>
                    <button class="tab-button" onclick="showTab('activity', this)">Recent Activity</button>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit-profile" class="tab-pane active">
                    <h2>Edit Profile</h2>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" 
                                   value="<?php echo htmlspecialchars(str_replace('+63', '', $user['phone'] ?? '')); ?>" 
                                   placeholder="9123456789">
                            <small class="form-text">Enter 10 digits (will be formatted with +63)</small>
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
                            <small class="form-text">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
                
                <!-- Recent Activity Tab -->
                <div id="activity" class="tab-pane">
                    <h2>Recent Activity</h2>
                    
                    <?php if (empty($recent_activity)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            No recent activity found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php echo $activity['type'] == 'ticket' ? '🎟️' : '👤'; ?>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-description">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M d, Y H:i', strtotime($activity['date'])); ?>
                                    </div>
                                </div>
                                <div class="activity-ref">
                                    <?php echo htmlspecialchars($activity['ref']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function showTab(tabId, element) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            element.classList.add('active');
        }
    </script>
</body>
</html>