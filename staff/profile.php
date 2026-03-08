<?php
// staff/profile.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['size'] > 0) {
    $target_dir = UPLOAD_PATH . 'profiles/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024;
    
    if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
        $errors[] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
    } elseif ($_FILES['profile_pic']['size'] > $max_size) {
        $errors[] = 'File too large. Maximum size is 2MB.';
    } else {
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user['id'] . '_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
            if ($user['profile_pic'] && file_exists($target_dir . $user['profile_pic'])) {
                unlink($target_dir . $user['profile_pic']);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$filename, $user['id']]);
            setFlash('Profile picture updated', 'success');
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
            setFlash('Profile updated', 'success');
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
                setFlash('Password changed', 'success');
                header('Location: profile.php');
                exit;
            }
        } else {
            $errors[] = 'Current password is incorrect';
        }
    }
}

// Get staff statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE verified_by = ?");
$stmt->execute([$user['id']]);
$tickets_verified = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT DATE(used_at)) FROM tickets 
    WHERE verified_by = ? AND used_at IS NOT NULL
");
$stmt->execute([$user['id']]);
$days_worked = $stmt->fetchColumn();

// Get cinema info
$cinema_name = 'Not Assigned';
if ($user['cinema_id']) {
    $stmt = $pdo->prepare("SELECT name, location FROM cinemas WHERE id = ?");
    $stmt->execute([$user['cinema_id']]);
    $cinema = $stmt->fetch();
    $cinema_name = $cinema ? $cinema['name'] : 'Not Assigned';
    $cinema_location = $cinema ? $cinema['location'] : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile - CinemaTicket</title>
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
            border: 3px solid #00ffff;
            object-fit: cover;
        }
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #000;
            border: 3px solid #00ffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #00ffff;
        }
        .upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #00ffff;
            color: #000;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid #000;
        }
        .upload-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 20px #00ffff;
        }
        #profile_pic { display: none; }
        .profile-name {
            font-size: 1.5rem;
            color: #00ffff;
            margin-bottom: 5px;
        }
        .profile-username {
            color: #888;
            margin-bottom: 20px;
        }
        .staff-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #00ffff;
            color: #000;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .cinema-info {
            background: #000;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .cinema-name {
            color: #00ffff;
            font-weight: bold;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
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
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 4px;
        }
        .form-group input:focus {
            border-color: #00ffff;
            outline: none;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Staff Profile</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
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
                            <?php echo strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="picForm">
                        <label for="profile_pic" class="upload-btn">📷</label>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" onchange="document.getElementById('picForm').submit()">
                    </form>
                </div>
                
                <div class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                <div class="staff-badge">STAFF</div>
                
                <div class="cinema-info">
                    <div style="color:#888;">Assigned Cinema</div>
                    <div class="cinema-name"><?php echo htmlspecialchars($cinema_name); ?></div>
                    <?php if (isset($cinema_location)): ?>
                        <div style="color:#888; font-size:0.9rem; margin-top:5px;"><?php echo htmlspecialchars($cinema_location); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-stats">
                    <div>
                        <div class="stat-value"><?php echo $tickets_verified; ?></div>
                        <div class="stat-label">Verified</div>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $days_worked; ?></div>
                        <div class="stat-label">Days</div>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo date('Y'); ?></div>
                        <div class="stat-label">Year</div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('edit-profile')">Edit Profile</button>
                    <button class="tab-button" onclick="showTab('change-password')">Change Password</button>
                </div>
                
                <!-- Edit Profile Tab -->
                <div id="edit-profile" class="tab-pane active">
                    <h2 style="color:#00ffff; margin-bottom:20px;">Edit Profile</h2>
                    
                    <form method="POST">
                        <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars(str_replace('+63', '', $user['phone'] ?? '')); ?>" placeholder="9123456789">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password Tab -->
                <div id="change-password" class="tab-pane">
                    <h2 style="color:#00ffff; margin-bottom:20px;">Change Password</h2>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small style="color:#888;">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>