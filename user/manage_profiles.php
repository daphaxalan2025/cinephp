<?php
// user/manage_profiles.php - Profile management (add/edit/delete)
// UPDATED: Adult-only access, avatar selection from predefined list, back button to select_profile.php
require_once '../includes/functions.php';

// Require login but NOT profile selection
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = getCurrentUser();
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

// ONLY ADULTS CAN MANAGE PROFILES
if ($_SESSION['account_type'] != 'adult') {
    setFlash('Only adult accounts can manage profiles.', 'error');
    header('Location: select_profile.php');
    exit;
}

$pdo = getDB();
$errors = [];
$success = '';

// Get all profiles for this user
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ? ORDER BY profile_type, profile_name");
$stmt->execute([$_SESSION['user_id']]);
$profiles = $stmt->fetchAll();
$profile_count = count($profiles);
$can_add_more = $profile_count < 5;

// Predefined avatars list (from your uploads/avatars folder)
$avatar_list = [
    'cool.png' => 'Cool',
    'dinosaur.png' => 'Dinosaur',
    'father.png' => 'Father',
    'girl.png' => 'Girl',
    'girlwithglass.png' => 'Girl with Glasses',
    'hacker.png' => 'Hacker',
    'happy.png' => 'Happy',
    'mom.png' => 'Mom',
    'muslim.png' => 'Muslim',
    'pikchu.png' => 'Pikachu',
    'pinkshirtgirl.png' => 'Pink Shirt Girl',
    'rabbit.png' => 'Rabbit',
    'robot.png' => 'Robot',
    'student.png' => 'Student',
    'woman.png' => 'Woman'
];

// Handle Add Profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add' && $can_add_more) {
        $profile_name = trim($_POST['profile_name'] ?? '');
        $profile_type = $_POST['profile_type'] ?? 'adult';
        $selected_avatar = $_POST['avatar_select'] ?? '';
        $pin = !empty($_POST['pin']) ? password_hash($_POST['pin'], PASSWORD_DEFAULT) : null;
        
        // Validate
        if (empty($profile_name)) {
            $errors[] = 'Profile name is required';
        } elseif (strlen($profile_name) > 50) {
            $errors[] = 'Profile name must be less than 50 characters';
        }
        
        if (!in_array($profile_type, ['adult', 'teen', 'kid'])) {
            $profile_type = 'adult';
        }
        
        if (!empty($_POST['pin']) && strlen($_POST['pin']) < 4) {
            $errors[] = 'PIN must be at least 4 digits';
        }
        
        // Use selected avatar from predefined list
        $avatar_filename = null;
        if (!empty($selected_avatar) && array_key_exists($selected_avatar, $avatar_list)) {
            $avatar_filename = $selected_avatar;
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, profile_name, profile_type, pin, avatar) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $profile_name, $profile_type, $pin, $avatar_filename]);
            $success = 'Profile added successfully!';
            
            // Refresh profiles list
            $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ? ORDER BY profile_type, profile_name");
            $stmt->execute([$_SESSION['user_id']]);
            $profiles = $stmt->fetchAll();
            $profile_count = count($profiles);
            $can_add_more = $profile_count < 5;
        }
    }
    
    // Handle Edit Profile
    elseif ($_POST['action'] == 'edit') {
        $profile_id = (int)$_POST['profile_id'];
        $profile_name = trim($_POST['profile_name'] ?? '');
        $profile_type = $_POST['profile_type'] ?? 'adult';
        $selected_avatar = $_POST['avatar_select'] ?? '';
        
        // Verify profile belongs to user
        $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profile_id, $_SESSION['user_id']]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            if (empty($profile_name)) {
                $errors[] = 'Profile name is required';
            } elseif (strlen($profile_name) > 50) {
                $errors[] = 'Profile name must be less than 50 characters';
            }
            
            if (!in_array($profile_type, ['adult', 'teen', 'kid'])) {
                $profile_type = 'adult';
            }
            
            // Handle PIN update
            $pin = $profile['pin'];
            if (!empty($_POST['pin'])) {
                if (strlen($_POST['pin']) >= 4) {
                    $pin = password_hash($_POST['pin'], PASSWORD_DEFAULT);
                } else {
                    $errors[] = 'PIN must be at least 4 digits';
                }
            } elseif (isset($_POST['remove_pin']) && $_POST['remove_pin'] == '1') {
                $pin = null;
            }
            
            // Handle avatar selection
            $avatar_filename = $profile['avatar'];
            if (!empty($selected_avatar) && array_key_exists($selected_avatar, $avatar_list)) {
                $avatar_filename = $selected_avatar;
            }
            
            if (empty($errors)) {
                $stmt = $pdo->prepare("
                    UPDATE user_profiles 
                    SET profile_name = ?, profile_type = ?, pin = ?, avatar = ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$profile_name, $profile_type, $pin, $avatar_filename, $profile_id, $_SESSION['user_id']]);
                $success = 'Profile updated successfully!';
                
                // Refresh profiles list
                $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ? ORDER BY profile_type, profile_name");
                $stmt->execute([$_SESSION['user_id']]);
                $profiles = $stmt->fetchAll();
            }
        }
    }
    
    // Handle Delete Profile
    elseif ($_POST['action'] == 'delete') {
        $profile_id = (int)$_POST['profile_id'];
        
        // Check if this is the last profile
        if ($profile_count <= 1) {
            $errors[] = 'Cannot delete the last profile';
        } else {
            // Get avatar filename to delete (only if it's a custom uploaded file, not predefined)
            $stmt = $pdo->prepare("SELECT avatar FROM user_profiles WHERE id = ? AND user_id = ?");
            $stmt->execute([$profile_id, $_SESSION['user_id']]);
            $profile = $stmt->fetch();
            
            if ($profile) {
                // Delete profile
                $stmt = $pdo->prepare("DELETE FROM user_profiles WHERE id = ? AND user_id = ?");
                $stmt->execute([$profile_id, $_SESSION['user_id']]);
                $success = 'Profile deleted successfully!';
                
                // Refresh profiles list
                $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ? ORDER BY profile_type, profile_name");
                $stmt->execute([$_SESSION['user_id']]);
                $profiles = $stmt->fetchAll();
                $profile_count = count($profiles);
                $can_add_more = $profile_count < 5;
            }
        }
    }
}

// Get profile for editing
$edit_profile = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $_SESSION['user_id']]);
    $edit_profile = $stmt->fetch();
}

// Function to get avatar image path
function getAvatarImagePath($avatar_filename) {
    if (!empty($avatar_filename) && file_exists('../uploads/avatars/' . $avatar_filename)) {
        return '../uploads/avatars/' . $avatar_filename;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profiles - CinemaTicket</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a0a 50%, #0a0a0a 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        
        .navbar {
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding: 1rem 0;
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            color: #e50914;
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #e50914 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(229, 9, 20, 0.3);
        }
        
        /* Profiles Grid */
        .profiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .profile-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .profile-item:hover {
            border-color: rgba(229, 9, 20, 0.3);
            transform: translateY(-2px);
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2a2a2a, #1a1a1a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-adult { background: rgba(229, 9, 20, 0.2); color: #e50914; }
        .badge-teen { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .badge-kid { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        
        .profile-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            color: #fff;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .btn-icon:hover {
            background: rgba(229, 9, 20, 0.5);
        }
        
        .btn-delete {
            background: rgba(229, 9, 20, 0.2);
            color: #e50914;
        }
        
        .btn-delete:hover {
            background: #e50914;
            color: white;
        }
        
        /* Add Profile Form */
        .add-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            border: 2px dashed rgba(229, 9, 20, 0.3);
        }
        
        .add-form h2 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #e50914;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px 16px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: #fff;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e50914;
        }
        
        /* Avatar Selection Grid */
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
        }
        
        .avatar-option {
            text-align: center;
            cursor: pointer;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .avatar-option:hover {
            background: rgba(229,9,20,0.2);
            transform: scale(1.05);
        }
        
        .avatar-option.selected {
            border-color: #e50914;
            background: rgba(229,9,20,0.3);
        }
        
        .avatar-option img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            background: #2a2a2a;
        }
        
        .avatar-option span {
            display: block;
            font-size: 0.7rem;
            margin-top: 5px;
            color: #b3b3b3;
        }
        
        .avatar-current {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
        }
        
        .avatar-current img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .btn-primary {
            background: #e50914;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: #b2070f;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(229, 9, 14, 0.2);
            border-left: 4px solid #e50914;
            color: #ff6b6b;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            color: #6bff6b;
        }
        
        .info-text {
            color: #b3b3b3;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .profiles-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            
            .avatar-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Profiles</h1>
            <a href="select_profile.php" class="btn-back">← Back to Profiles</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Profiles List -->
        <div class="profiles-grid">
            <?php foreach ($profiles as $profile): ?>
                <div class="profile-item">
                    <div class="profile-avatar">
                        <?php if (!empty($profile['avatar'])): ?>
                            <?php $avatar_path = getAvatarImagePath($profile['avatar']); ?>
                            <?php if ($avatar_path): ?>
                                <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($profile['profile_name']); ?>">
                            <?php else: ?>
                                <?php 
                                $emoji = '👤';
                                if ($profile['profile_type'] == 'adult') $emoji = '🎬';
                                elseif ($profile['profile_type'] == 'teen') $emoji = '🎮';
                                elseif ($profile['profile_type'] == 'kid') $emoji = '🧸';
                                echo $emoji;
                                ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php 
                            $emoji = '👤';
                            if ($profile['profile_type'] == 'adult') $emoji = '🎬';
                            elseif ($profile['profile_type'] == 'teen') $emoji = '🎮';
                            elseif ($profile['profile_type'] == 'kid') $emoji = '🧸';
                            echo $emoji;
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?php echo htmlspecialchars($profile['profile_name']); ?></div>
                        <span class="profile-badge badge-<?php echo $profile['profile_type']; ?>">
                            <?php echo ucfirst($profile['profile_type']); ?>
                        </span>
                        <?php if ($profile['pin']): ?>
                            <div class="info-text">🔒 PIN protected</div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-actions">
                        <a href="?edit=<?php echo $profile['id']; ?>" class="btn-icon">✏️ Edit</a>
                        <?php if ($profile_count > 1): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this profile?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                                <button type="submit" class="btn-icon btn-delete">🗑️ Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Add/Edit Profile Form -->
        <?php if ($edit_profile): ?>
            <!-- Edit Form -->
            <div class="add-form">
                <h2>✏️ Edit Profile</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="profile_id" value="<?php echo $edit_profile['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Profile Name</label>
                            <input type="text" name="profile_name" value="<?php echo htmlspecialchars($edit_profile['profile_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Profile Type</label>
                            <select name="profile_type">
                                <option value="adult" <?php echo $edit_profile['profile_type'] == 'adult' ? 'selected' : ''; ?>>Adult (No restrictions)</option>
                                <option value="teen" <?php echo $edit_profile['profile_type'] == 'teen' ? 'selected' : ''; ?>>Teen (PG-13 & below)</option>
                                <option value="kid" <?php echo $edit_profile['profile_type'] == 'kid' ? 'selected' : ''; ?>>Kid (G & PG only)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Profile PIN (Optional)</label>
                            <input type="password" name="pin" placeholder="4+ digits" autocomplete="new-password">
                            <small class="info-text">Leave blank to keep current PIN</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Avatar</label>
                            <?php if (!empty($edit_profile['avatar'])): ?>
                                <div class="avatar-current">
                                    <?php $current_avatar_path = getAvatarImagePath($edit_profile['avatar']); ?>
                                    <?php if ($current_avatar_path): ?>
                                        <img src="<?php echo $current_avatar_path; ?>" alt="Current avatar">
                                    <?php endif; ?>
                                    <span>Current avatar: <?php echo htmlspecialchars($edit_profile['avatar']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="avatar-grid">
                                <?php foreach ($avatar_list as $filename => $label): ?>
                                    <div class="avatar-option <?php echo ($edit_profile['avatar'] == $filename) ? 'selected' : ''; ?>" onclick="selectAvatar('<?php echo $filename; ?>')">
                                        <img src="../uploads/avatars/<?php echo $filename; ?>" alt="<?php echo $label; ?>">
                                        <span><?php echo $label; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="avatar_select" id="avatar_select" value="<?php echo htmlspecialchars($edit_profile['avatar']); ?>">
                        </div>
                    </div>
                    
                    <?php if ($edit_profile['pin']): ?>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="remove_pin" value="1"> Remove PIN
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn-primary">Save Changes</button>
                        <a href="manage_profiles.php" class="btn-icon">Cancel</a>
                    </div>
                </form>
            </div>
        <?php elseif ($can_add_more): ?>
            <!-- Add Form -->
            <div class="add-form">
                <h2>➕ Add New Profile</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Profile Name</label>
                            <input type="text" name="profile_name" required placeholder="e.g., John, Mom, Dad">
                        </div>
                        
                        <div class="form-group">
                            <label>Profile Type</label>
                            <select name="profile_type">
                                <option value="adult">Adult (No restrictions)</option>
                                <option value="teen">Teen (PG-13 & below)</option>
                                <option value="kid">Kid (G & PG only)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Profile PIN (Optional)</label>
                            <input type="password" name="pin" placeholder="4+ digits for extra security">
                        </div>
                        
                        <div class="form-group">
                            <label>Select Avatar</label>
                            <div class="avatar-grid">
                                <?php foreach ($avatar_list as $filename => $label): ?>
                                    <div class="avatar-option" onclick="selectAvatar('<?php echo $filename; ?>')">
                                        <img src="../uploads/avatars/<?php echo $filename; ?>" alt="<?php echo $label; ?>">
                                        <span><?php echo $label; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="avatar_select" id="avatar_select" value="">
                            <small class="info-text">Click on an avatar to select it</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Create Profile</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                Maximum 5 profiles per account. Delete an existing profile to add a new one.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function selectAvatar(filename) {
            document.querySelectorAll('.avatar-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('avatar_select').value = filename;
        }
    </script>
</body>
</html>