<?php
// user/settings.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Handle theme change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['theme'])) {
    $theme = $_POST['theme'];
    $valid_themes = ['dark', 'light', 'neon', 'matrix'];
    
    if (in_array($theme, $valid_themes)) {
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        $stmt->execute([$theme, $user['id']]);
        setFlash('Theme updated successfully', 'success');
        header('Location: settings.php');
        exit;
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notifications'])) {
    // In a real app, you'd save these to a user_settings table
    setFlash('Notification preferences saved', 'success');
    header('Location: settings.php');
    exit;
}

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        .settings-sidebar {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            height: fit-content;
        }
        .settings-menu {
            list-style: none;
        }
        .settings-menu li {
            margin-bottom: 10px;
        }
        .settings-menu a {
            display: block;
            padding: 12px 15px;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        .settings-menu a:hover,
        .settings-menu a.active {
            border-color: #00ffff;
            background: rgba(0,255,255,0.1);
            color: #00ffff;
        }
        .settings-content {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
        }
        .settings-section {
            display: none;
        }
        .settings-section.active {
            display: block;
        }
        .theme-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .theme-option {
            cursor: pointer;
            text-align: center;
        }
        .theme-option input[type="radio"] {
            display: none;
        }
        .theme-preview {
            height: 100px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 3px solid transparent;
            transition: all 0.3s;
        }
        .theme-option input[type="radio"]:checked + .theme-preview {
            border-color: #00ffff;
            transform: scale(1.05);
        }
        .preview-dark {
            background: linear-gradient(135deg, #1a1a1a, #000);
            border: 2px solid #00ffff;
        }
        .preview-light {
            background: linear-gradient(135deg, #fff, #ccc);
            border: 2px solid #00ffff;
        }
        .preview-neon {
            background: linear-gradient(135deg, #00ffff, #ff00ff);
        }
        .preview-matrix {
            background: linear-gradient(135deg, #00ff00, #003300);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #00ffff;
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
        }
        .danger-zone {
            margin-top: 40px;
            padding: 20px;
            border: 2px solid #ff4444;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <a href="movies.php">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php" class="active">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Settings</h1>
        
        <div class="settings-container">
            <!-- Sidebar -->
            <div class="settings-sidebar">
                <ul class="settings-menu">
                    <li><a href="#appearance" class="active" onclick="showSection('appearance')">Appearance</a></li>
                    <li><a href="#notifications" onclick="showSection('notifications')">Notifications</a></li>
                    <li><a href="#privacy" onclick="showSection('privacy')">Privacy</a></li>
                    <li><a href="#security" onclick="showSection('security')">Security</a></li>
                    <li><a href="#about" onclick="showSection('about')">About</a></li>
                </ul>
            </div>
            
            <!-- Content -->
            <div class="settings-content">
                <!-- Appearance Section -->
                <div id="appearance" class="settings-section active">
                    <h2 style="color:#00ffff;">Theme Preferences</h2>
                    <p style="color:#888;">Choose your favorite theme for the website</p>
                    
                    <form method="POST">
                        <div class="theme-options">
                            <label class="theme-option">
                                <input type="radio" name="theme" value="dark" 
                                       <?php echo $current_theme == 'dark' ? 'checked' : ''; ?>>
                                <div class="theme-preview preview-dark"></div>
                                <span>Dark Neon</span>
                            </label>
                            
                            <label class="theme-option">
                                <input type="radio" name="theme" value="light" 
                                       <?php echo $current_theme == 'light' ? 'checked' : ''; ?>>
                                <div class="theme-preview preview-light"></div>
                                <span>Light Mode</span>
                            </label>
                            
                            <label class="theme-option">
                                <input type="radio" name="theme" value="neon" 
                                       <?php echo $current_theme == 'neon' ? 'checked' : ''; ?>>
                                <div class="theme-preview preview-neon"></div>
                                <span>Neon Vibes</span>
                            </label>
                            
                            <label class="theme-option">
                                <input type="radio" name="theme" value="matrix" 
                                       <?php echo $current_theme == 'matrix' ? 'checked' : ''; ?>>
                                <div class="theme-preview preview-matrix"></div>
                                <span>Matrix</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Theme</button>
                    </form>
                </div>
                
                <!-- Notifications Section -->
                <div id="notifications" class="settings-section">
                    <h2 style="color:#00ffff;">Notification Preferences</h2>
                    <p style="color:#888;">Choose how you want to be notified</p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="email_movies" checked> Email me about new movies
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="email_screenings" checked> Email me about upcoming screenings
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="sms_tickets" checked> SMS for ticket confirmations
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="promotions"> Promotional offers and discounts
                            </label>
                        </div>
                        
                        <button type="submit" name="notifications" class="btn btn-primary">Save Preferences</button>
                    </form>
                </div>
                
                <!-- Privacy Section -->
                <div id="privacy" class="settings-section">
                    <h2 style="color:#00ffff;">Privacy Settings</h2>
                    
                    <form>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_history" checked> Show my watch history to family members
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_favorites"> Show my favorites publicly
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="allow_linked" checked> Allow linked accounts to see my activity
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Privacy Settings</button>
                    </form>
                    
                    <div class="danger-zone">
                        <h3 style="color:#ff4444;">Danger Zone</h3>
                        <p style="color:#888;">Download your data or delete your account</p>
                        <a href="export_data.php" class="btn btn-secondary">Download My Data</a>
                        <a href="delete_account.php" class="btn btn-danger" 
                           onclick="return confirm('Are you sure? This cannot be undone!')">Delete Account</a>
                    </div>
                </div>
                
                <!-- Security Section -->
                <div id="security" class="settings-section">
                    <h2 style="color:#00ffff;">Security Settings</h2>
                    
                    <form action="profile.php" method="POST">
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
                    
                    <div style="margin-top:30px;">
                        <h3 style="color:#00ffff;">Two-Factor Authentication</h3>
                        <p style="color:#888;">Add an extra layer of security to your account</p>
                        <button class="btn btn-secondary">Enable 2FA</button>
                    </div>
                </div>
                
                <!-- About Section -->
                <div id="about" class="settings-section">
                    <h2 style="color:#00ffff;">About CinemaTicket</h2>
                    <p style="color:#888;">Version 2.0.0</p>
                    <p style="color:#fff;">Your premier destination for movie tickets - Watch in cinema or online</p>
                    
                    <h3 style="color:#00ffff; margin-top:30px;">Features</h3>
                    <ul style="color:#888; margin-left:20px;">
                        <li>🎬 Wide movie selection with age-based filtering</li>
                        <li>🎟️ Easy ticket purchase with seat selection</li>
                        <li>📱 Digital tickets with QR codes</li>
                        <li>👨‍👩‍👧‍👦 Family accounts with linked profiles</li>
                        <li>🎨 Multiple customizable themes</li>
                        <li>❤️ Favorites and watch history</li>
                        <li>📺 Online streaming with view limits</li>
                    </ul>
                    
                    <h3 style="color:#00ffff; margin-top:30px;">Contact</h3>
                    <p style="color:#fff;">Email: support@cinematicket.com</p>
                    <p style="color:#fff;">Phone: +63 (912) 345-6789</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            
            document.querySelectorAll('.settings-menu a').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>