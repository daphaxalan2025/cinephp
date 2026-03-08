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
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        .profile-sidebar {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            background: #000;
            border: 3px solid #00ffff;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #00ffff;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
        }
        .profile-name {
            font-size: 1.5rem;
            color: #00ffff;
            margin-bottom: 5px;
        }
        .profile-username {
            color: #888;
            margin-bottom: 20px;
        }
        .profile-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 20px;
            color: #00ffff;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
            padding: 20px 0;
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
        .profile-content {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
        }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .tab-button {
            padding: 10px 20px;
            background: transparent;
            border: 1px solid #00ffff;
            color: #00ffff;
            cursor: pointer;
            border-radius: 4px;
        }
        .tab-button.active {
            background: #00ffff;
            color: #000;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #000;
            border: 1px solid #333;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .activity-details {
            flex: 1;
        }
        .activity-description {
            color: #fff;
            margin-bottom: 5px;
        }
        .activity-time {
            color: #888;
            font-size: 0.8rem;
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
                <a href="online_schedule.php">Online Schedule</a>
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
                <div class="profile-badge">ADMINISTRATOR</div>
                
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
                
                <div style="text-align: left; padding: 0 10px;">
                    <div style="margin-bottom: 15px;">
                        <div style="color: #888;">Email</div>
                        <div style="color: #fff;"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <div style="color: #888;">Phone</div>
                        <div style="color: #fff;"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <div style="color: #888;">Member Since</div>
                        <div style="color: #fff;"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                    <div>
                        <div style="color: #888;">Last Login</div>
                        <div style="color: #fff;"><?php echo $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'First login'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('edit-profile')">Edit Profile</button>
                    <button class="tab-button" onclick="showTab('change-password')">Change Password</button>
                    <button class="tab-button" onclick="showTab('activity')">Recent Activity</button>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit-profile" class="tab-pane active">
                    <h2 style="color: #00ffff; margin-bottom: 20px;">Edit Profile</h2>
                    
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars(str_replace('+63', '', $user['phone'] ?? '')); ?>" 
                                   placeholder="9123456789">
                            <small class="form-text">Enter 10 digits (will be formatted with +63)</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div id="change-password" class="tab-pane">
                    <h2 style="color: #00ffff; margin-bottom: 20px;">Change Password</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small class="form-text">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
                
                <!-- Recent Activity Tab -->
                <div id="activity" class="tab-pane">
                    <h2 style="color: #00ffff; margin-bottom: 20px;">Recent Activity</h2>
                    
                    <?php if (empty($recent_activity)): ?>
                        <p style="color: #888;">No recent activity.</p>
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
                                <div style="color: #00ffff; font-family: monospace;">
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
        function showTab(tabId) {
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
            event.target.classList.add('active');
        }
    </script>
</body>
</html>