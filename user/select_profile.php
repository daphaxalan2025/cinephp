<?php
// user/select_profile.php - Netflix-style profile selection screen with PIN protection
// FIXED: Added local fallback for getAvatarImage(), improved PIN flow, better error handling
require_once '../includes/functions.php';

// FALLBACK FUNCTION - in case functions.php doesn't have it
if (!function_exists('getAvatarImage')) {
    function getAvatarImage($avatar_filename) {
        if (empty($avatar_filename)) return null;
        $paths = [
            '../uploads/avatars/' . $avatar_filename,
            'uploads/avatars/' . $avatar_filename
        ];
        foreach ($paths as $path) {
            if (file_exists(dirname(__DIR__) . '/' . $path)) {
                return $path;
            }
        }
        return null;
    }
}

// Require login but NOT profile selection (avoid infinite loop)
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get current user
$user = getCurrentUser();
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDB();

// Handle PIN verification AJAX request
if (isset($_POST['verify_pin']) && isset($_POST['profile_id']) && isset($_POST['pin'])) {
    header('Content-Type: application/json');
    $profile_id = (int)$_POST['profile_id'];
    $entered_pin = $_POST['pin'];
    
    // Make sure verifyProfilePin function exists
    if (!function_exists('verifyProfilePin')) {
        echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
        exit;
    }
    
    if (verifyProfilePin($profile_id, $entered_pin)) {
        // PIN is correct, now select the profile
        $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profile_id, $_SESSION['user_id']]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            $_SESSION['profile_id'] = $profile['id'];
            $_SESSION['profile_type'] = $profile['profile_type'];
            $_SESSION['profile_name'] = $profile['profile_name'];
            echo json_encode(['success' => true, 'redirect' => 'movies.php']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect PIN. Please try again.']);
        exit;
    }
}

// Handle direct profile selection (POST from the hidden form - for profiles without PIN)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['profile_id']) && !isset($_POST['verify_pin'])) {
    $profile_id = (int)$_POST['profile_id'];
    
    // Get profile details
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$profile_id, $_SESSION['user_id']]);
    $profile = $stmt->fetch();
    
    if ($profile) {
        $_SESSION['profile_id'] = $profile['id'];
        $_SESSION['profile_type'] = $profile['profile_type'];
        $_SESSION['profile_name'] = $profile['profile_name'];
        header('Location: movies.php');
        exit;
    } else {
        setFlash('Profile not found', 'error');
        header('Location: select_profile.php');
        exit;
    }
}

// Get all profiles for this user
$stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ? ORDER BY profile_type, profile_name");
$stmt->execute([$_SESSION['user_id']]);
$profiles = $stmt->fetchAll();

$profile_count = count($profiles);
$can_add_more = $profile_count < 5;

// Check if current session is adult (can manage profiles)
$is_adult = ($_SESSION['account_type'] == 'adult');

// Handle profile deletion (from query string)
if (isset($_GET['delete']) && isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    $delete_id = (int)$_GET['delete'];
    
    // Check if this is the last profile
    if ($profile_count <= 1) {
        setFlash('Cannot delete the last profile', 'error');
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $_SESSION['user_id']]);
        setFlash('Profile deleted successfully', 'success');
    }
    
    header('Location: select_profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Who's Watching? - CinemaTicket</title>
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
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 40%, rgba(229, 9, 20, 0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        
        /* Navbar */
        .navbar {
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding: 1.5rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            color: #e50914;
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s;
        }
        
        .logo:hover {
            text-shadow: 0 0 20px rgba(229, 9, 20, 0.5);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            color: #e50914;
        }
        
        /* Back Button */
        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #fff !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff !important;
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        /* Logout Button */
        .logout-btn {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
            border: 1px solid #e50914;
        }
        
        .logout-btn:hover {
            background: #e50914;
            color: white;
        }
        
        /* Family Info Box */
        .family-info {
            background: rgba(229,9,20,0.1);
            border: 1px solid #e50914;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .family-info-icon {
            font-size: 3rem;
        }
        
        .family-info-content {
            flex: 1;
        }
        
        .family-info-content h3 {
            color: #e50914;
            margin-bottom: 8px;
        }
        
        .family-info-content p {
            color: #b3b3b3;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .family-info-content small {
            color: #b3b3b3;
            font-size: 0.8rem;
        }
        
        .manage-profiles-link {
            background: #e50914;
            color: white;
            padding: 10px 25px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .manage-profiles-link:hover {
            background: #b2070f;
            transform: translateY(-2px);
        }
        
        /* First Profile Hint */
        .first-profile-hint {
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
            text-align: center;
            border: 1px dashed rgba(229,9,20,0.3);
        }
        
        .first-profile-hint-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .first-profile-hint h3 {
            color: #e50914;
            margin-bottom: 10px;
        }
        
        .first-profile-hint p {
            color: #b3b3b3;
            margin-bottom: 15px;
        }
        
        .first-profile-btn {
            display: inline-block;
            background: #e50914;
            color: white;
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .first-profile-btn:hover {
            background: #b2070f;
            transform: translateY(-2px);
        }
        
        /* Main Container */
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 30px;
        }
        
        .profiles-container {
            text-align: center;
            max-width: 1200px;
            width: 100%;
        }
        
        .profiles-container h1 {
            font-size: 3.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #fff 0%, #e50914 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            letter-spacing: 2px;
        }
        
        .subtitle {
            color: #b3b3b3;
            font-size: 1.1rem;
            margin-bottom: 50px;
        }
        
        /* Profiles Grid */
        .profiles-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .profile-card {
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }
        
        .profile-card:hover {
            transform: translateY(-10px);
        }
        
        .profile-card:hover .avatar {
            border-color: #e50914;
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.3);
        }
        
        .avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
            border: 3px solid rgba(229, 9, 20, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s;
            overflow: hidden;
            position: relative;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-emoji {
            font-size: 5rem;
        }
        
        .profile-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .badge-adult {
            background: rgba(229, 9, 20, 0.2);
            color: #e50914;
            border: 1px solid #e50914;
        }
        
        .badge-teen {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }
        
        .badge-kid {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }
        
        /* Add Profile Card */
        .add-profile {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        
        .add-profile .avatar {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(229, 9, 20, 0.3);
        }
        
        .add-profile .avatar-emoji {
            font-size: 4rem;
            opacity: 0.7;
        }
        
        .add-profile:hover .avatar-emoji {
            opacity: 1;
        }
        
        .add-profile .profile-name {
            color: #b3b3b3;
        }
        
        /* PIN Modal */
        .pin-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .pin-modal.active {
            display: flex;
        }
        
        .pin-modal-content {
            background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
            border: 1px solid #e50914;
            border-radius: 32px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 0 50px rgba(229,9,20,0.3);
        }
        
        .pin-modal-content h2 {
            color: #e50914;
            margin-bottom: 10px;
        }
        
        .pin-modal-content p {
            color: #b3b3b3;
            margin-bottom: 25px;
        }
        
        .pin-input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(229,9,20,0.3);
            border-radius: 40px;
            color: white;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 5px;
            margin-bottom: 20px;
        }
        
        .pin-input:focus {
            outline: none;
            border-color: #e50914;
        }
        
        .pin-error {
            color: #ff4444;
            font-size: 0.85rem;
            margin-bottom: 15px;
            display: none;
        }
        
        .pin-error.show {
            display: block;
        }
        
        .pin-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .pin-btn {
            padding: 12px 25px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pin-btn-submit {
            background: #e50914;
            color: white;
        }
        
        .pin-btn-submit:hover {
            background: #b2070f;
            transform: translateY(-2px);
        }
        
        .pin-btn-cancel {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .pin-btn-cancel:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Manage Link - Hide for non-adults */
        .manage-link {
            display: inline-block;
            margin-top: 30px;
            color: #b3b3b3;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s;
            padding: 12px 24px;
            border-radius: 40px;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .manage-link:hover {
            color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }
        
        /* Back Link Below Profiles */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            margin-left: 15px;
            color: #b3b3b3;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
            padding: 10px 20px;
            border-radius: 40px;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .back-link:hover {
            color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }
        
        /* Flash Messages */
        .flash-message {
            position: fixed;
            top: 100px;
            right: 30px;
            padding: 15px 25px;
            border-radius: 40px;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-left: 4px solid #e50914;
            color: #fff;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        
        .flash-success {
            border-left-color: #28a745;
        }
        
        .flash-error {
            border-left-color: #e50914;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profiles-container h1 {
                font-size: 2rem;
            }
            
            .avatar {
                width: 120px;
                height: 120px;
            }
            
            .avatar-emoji {
                font-size: 3rem;
            }
            
            .avatar img {
                width: 120px;
                height: 120px;
            }
            
            .profile-name {
                font-size: 1rem;
            }
            
            .profiles-grid {
                gap: 25px;
            }
            
            .family-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="javascript:history.back()" class="back-btn">← Back</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="profiles-container">
            <h1>Who's watching?</h1>
            <p class="subtitle">Select a profile to continue</p>
            
            <!-- Family Info Box -->
            <div class="family-info">
                <div class="family-info-icon">👨‍👩‍👧‍👦</div>
                <div class="family-info-content">
                    <h3>Family-Friendly Experience</h3>
                    <p>Each profile has its own watch history and age-appropriate content.</p>
                    <small>🔒 Kid profiles cannot purchase tickets (parent must buy). 🎮 Teen profiles see PG-13 and below only. 🎬 Adult profiles have full access.</small>
                </div>
                <?php if ($is_adult): ?>
                    <a href="manage_profiles.php" class="manage-profiles-link">Manage Profiles →</a>
                <?php endif; ?>
            </div>
            
            <div class="profiles-grid">
                <?php foreach ($profiles as $profile): ?>
                    <div class="profile-card" data-profile-id="<?php echo $profile['id']; ?>" data-profile-name="<?php echo htmlspecialchars($profile['profile_name']); ?>" data-has-pin="<?php echo $profile['pin'] ? 'true' : 'false'; ?>">
                        <div class="avatar">
                            <?php 
                            $avatar_path = getAvatarImage($profile['avatar']);
                            if ($avatar_path && file_exists($avatar_path)): 
                            ?>
                                <img src="<?php echo $avatar_path; ?>" alt="<?php echo htmlspecialchars($profile['profile_name']); ?>">
                            <?php else: ?>
                                <div class="avatar-emoji">
                                    <?php 
                                    $emoji = '👤';
                                    if ($profile['profile_type'] == 'adult') $emoji = '🎬';
                                    elseif ($profile['profile_type'] == 'teen') $emoji = '🎮';
                                    elseif ($profile['profile_type'] == 'kid') $emoji = '🧸';
                                    echo $emoji;
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($profile['profile_name']); ?></div>
                        <div class="profile-badge badge-<?php echo $profile['profile_type']; ?>">
                            <?php echo ucfirst($profile['profile_type']); ?>
                        </div>
                        <?php if ($profile['pin']): ?>
                            <div style="font-size: 0.7rem; color: #ff8844; margin-top: 5px;">🔒 PIN protected</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($can_add_more && $is_adult): ?>
                    <a href="manage_profiles.php" class="profile-card add-profile">
                        <div class="avatar">
                            <div class="avatar-emoji">+</div>
                        </div>
                        <div class="profile-name">Add Profile</div>
                        <div class="profile-badge" style="opacity: 0.5;">New</div>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- First Profile Hint (if no profiles exist) -->
            <?php if (empty($profiles) && $is_adult): ?>
                <div class="first-profile-hint">
                    <div class="first-profile-hint-icon">🎉</div>
                    <h3>Welcome to CinemaTicket!</h3>
                    <p>Create your first profile to start watching. You can add up to 5 profiles for your family.</p>
                    <a href="manage_profiles.php" class="first-profile-btn">Create Your First Profile →</a>
                </div>
            <?php endif; ?>
            
            <div>
                <?php if ($is_adult): ?>
                    <a href="manage_profiles.php" class="manage-link">⚙️ Manage Profiles</a>
                <?php endif; ?>
                <a href="javascript:history.back()" class="back-link">← Go Back</a>
            </div>
        </div>
    </div>
    
    <!-- PIN Modal -->
    <div id="pinModal" class="pin-modal">
        <div class="pin-modal-content">
            <h2>🔒 Profile Locked</h2>
            <p id="pinProfileName">This profile is PIN protected. Enter PIN to continue.</p>
            <input type="password" id="pinInput" class="pin-input" placeholder="Enter PIN" maxlength="6" autocomplete="off">
            <div id="pinError" class="pin-error">Incorrect PIN. Please try again.</div>
            <div class="pin-buttons">
                <button class="pin-btn pin-btn-submit" id="submitPinBtn">Unlock</button>
                <button class="pin-btn pin-btn-cancel" id="cancelPinBtn">Cancel</button>
            </div>
        </div>
    </div>
    
    <form id="profileSelectForm" method="POST" style="display: none;">
        <input type="hidden" name="profile_id" id="hiddenProfileId">
    </form>
    
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.flash-message')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>
    
    <script>
        let currentProfileId = null;
        
        // Add click handlers to all profile cards
        document.querySelectorAll('.profile-card[data-profile-id]').forEach(card => {
            card.addEventListener('click', function(e) {
                // Prevent if clicking on a link inside (like add-profile)
                if (e.target.closest('a')) return;
                
                const profileId = this.dataset.profileId;
                const profileName = this.dataset.profileName;
                const hasPin = this.dataset.hasPin === 'true';
                
                if (hasPin) {
                    // Show PIN modal
                    currentProfileId = profileId;
                    document.getElementById('pinProfileName').innerHTML = `<strong>${profileName}</strong> is PIN protected. Enter PIN to continue.`;
                    document.getElementById('pinModal').classList.add('active');
                    document.getElementById('pinInput').value = '';
                    document.getElementById('pinInput').focus();
                    document.getElementById('pinError').classList.remove('show');
                } else {
                    // No PIN, direct submit
                    document.getElementById('hiddenProfileId').value = profileId;
                    document.getElementById('profileSelectForm').submit();
                }
            });
        });
        
        function submitPin() {
            const pin = document.getElementById('pinInput').value;
            if (!pin || pin.length < 4) {
                document.getElementById('pinError').textContent = 'Please enter a valid PIN (4+ digits)';
                document.getElementById('pinError').classList.add('show');
                return;
            }
            
            // Disable button to prevent double submission
            const submitBtn = document.getElementById('submitPinBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying...';
            
            // Send AJAX request to verify PIN
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'verify_pin=1&profile_id=' + currentProfileId + '&pin=' + encodeURIComponent(pin)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Unlock';
                
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    document.getElementById('pinError').textContent = data.message;
                    document.getElementById('pinError').classList.add('show');
                    document.getElementById('pinInput').value = '';
                    document.getElementById('pinInput').focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Unlock';
                document.getElementById('pinError').textContent = 'An error occurred. Please try again.';
                document.getElementById('pinError').classList.add('show');
            });
        }
        
        function closePinModal() {
            document.getElementById('pinModal').classList.remove('active');
            currentProfileId = null;
        }
        
        // Add event listeners
        document.getElementById('submitPinBtn').addEventListener('click', submitPin);
        document.getElementById('cancelPinBtn').addEventListener('click', closePinModal);
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('pinModal').classList.contains('active')) {
                closePinModal();
            }
        });
        
        // Submit on Enter key
        document.getElementById('pinInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitPin();
            }
        });
    </script>
</body>
</html>