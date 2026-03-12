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
        
        /* Settings Container */
        .settings-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        /* Sidebar */
        .settings-sidebar {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 25px;
            height: fit-content;
            position: relative;
            overflow: hidden;
        }
        
        .settings-sidebar::before {
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
        
        .settings-menu {
            list-style: none;
        }
        
        .settings-menu li {
            margin-bottom: 10px;
        }
        
        .settings-menu a {
            display: block;
            padding: 14px 18px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 40px;
            transition: all 0.3s;
            border: 1px solid transparent;
            font-weight: 500;
        }
        
        .settings-menu a:hover,
        .settings-menu a.active {
            border-color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            color: var(--red);
            transform: translateX(5px);
        }
        
        /* Content */
        .settings-content {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 35px;
            position: relative;
            overflow: hidden;
        }
        
        .settings-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .settings-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-section h2 {
            color: var(--red);
            margin-bottom: 15px;
            font-size: 1.8rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .settings-section p {
            color: var(--text-secondary);
            margin-bottom: 25px;
        }
        
        /* Theme Options */
        .theme-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            height: 120px;
            border-radius: 16px;
            margin-bottom: 12px;
            border: 3px solid transparent;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .theme-option input[type="radio"]:checked + .theme-preview {
            border-color: var(--red);
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
        }
        
        .preview-dark {
            background: linear-gradient(135deg, #0a0a0a, #1a1a1a);
            border: 2px solid var(--red);
            position: relative;
            overflow: hidden;
        }
        
        .preview-dark::after {
            content: '🌙';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .preview-light {
            background: linear-gradient(135deg, #fff, #e0e0e0);
            border: 2px solid var(--red);
            position: relative;
            overflow: hidden;
        }
        
        .preview-light::after {
            content: '☀️';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            opacity: 0.5;
        }
        
        .preview-neon {
            background: linear-gradient(135deg, #ff00ff, #00ffff);
            border: 2px solid var(--red);
            position: relative;
            overflow: hidden;
        }
        
        .preview-neon::after {
            content: '⚡';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            color: #fff;
            text-shadow: 0 0 10px #fff;
        }
        
        .preview-matrix {
            background: linear-gradient(135deg, #00ff00, #003300);
            border: 2px solid var(--red);
            position: relative;
            overflow: hidden;
        }
        
        .preview-matrix::after {
            content: '0101';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            color: #00ff00;
            font-family: monospace;
        }
        
        .theme-option span {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            cursor: pointer;
            padding: 10px;
            border-radius: 40px;
            transition: all 0.3s;
        }
        
        .form-group label:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .form-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--red);
        }
        
        .form-group input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            margin-top: 5px;
        }
        
        .form-group input[type="password"]:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-group small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
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
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
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
        
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            border-color: var(--red);
            color: var(--red);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: transparent;
            border: 1px solid #ff4444;
            color: #ff4444;
            padding: 12px 25px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-danger:hover {
            background: #ff4444;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 68, 68, 0.3);
        }
        
        /* Danger Zone */
        .danger-zone {
            margin-top: 40px;
            padding: 25px;
            border: 1px solid #ff4444;
            border-radius: 16px;
            background: rgba(255, 68, 68, 0.05);
        }
        
        .danger-zone h3 {
            color: #ff4444;
            margin-bottom: 10px;
        }
        
        .danger-zone p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        /* Feature List */
        .feature-list {
            list-style: none;
            margin: 20px 0;
        }
        
        .feature-list li {
            color: var(--text-secondary);
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .feature-list li::before {
            content: '🎬';
            position: absolute;
            left: 0;
            color: var(--red);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-menu a:hover {
                transform: none;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .theme-options {
                grid-template-columns: 1fr 1fr;
            }
            
            .btn-secondary, .btn-danger {
                display: block;
                margin: 10px 0;
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
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <div class="settings-container">
            <!-- Sidebar -->
            <div class="settings-sidebar">
                <ul class="settings-menu">
                    <li><a href="#appearance" class="active" onclick="showSection('appearance', event)">Appearance</a></li>
                    <li><a href="#notifications" onclick="showSection('notifications', event)">Notifications</a></li>
                    <li><a href="#privacy" onclick="showSection('privacy', event)">Privacy</a></li>
                    <li><a href="#security" onclick="showSection('security', event)">Security</a></li>
                    <li><a href="#about" onclick="showSection('about', event)">About</a></li>
                </ul>
            </div>
            
            <!-- Content -->
            <div class="settings-content">
                <!-- Appearance Section -->
                <div id="appearance" class="settings-section active">
                    <h2>Theme Preferences</h2>
                    <p>Choose your favorite theme for the website</p>
                    
                    <form method="POST">
                        <div class="theme-options">
                            <label class="theme-option">
                                <input type="radio" name="theme" value="dark" 
                                       <?php echo $current_theme == 'dark' ? 'checked' : ''; ?>>
                                <div class="theme-preview preview-dark"></div>
                                <span>Dark Mode</span>
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
                        
                        <button type="submit" class="btn-primary">Save Theme</button>
                    </form>
                </div>
                
                <!-- Notifications Section -->
                <div id="notifications" class="settings-section">
                    <h2>Notification Preferences</h2>
                    <p>Choose how you want to be notified</p>
                    
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
                        
                        <button type="submit" name="notifications" class="btn-primary">Save Preferences</button>
                    </form>
                </div>
                
                <!-- Privacy Section -->
                <div id="privacy" class="settings-section">
                    <h2>Privacy Settings</h2>
                    
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
                        
                        <button type="submit" class="btn-primary">Save Privacy Settings</button>
                    </form>
                    
                    <div class="danger-zone">
                        <h3>Danger Zone</h3>
                        <p>Download your data or delete your account</p>
                        <a href="export_data.php" class="btn-secondary">Download My Data</a>
                        <a href="delete_account.php" class="btn-danger" 
                           onclick="return confirm('Are you sure? This cannot be undone!')">Delete Account</a>
                    </div>
                </div>
                
                <!-- Security Section -->
                <div id="security" class="settings-section">
                    <h2>Security Settings</h2>
                    
                    <form action="profile.php" method="POST">
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
                    
                    <div style="margin-top:30px;">
                        <h3 style="color: var(--red);">Two-Factor Authentication</h3>
                        <p style="color: var(--text-secondary);">Add an extra layer of security to your account</p>
                        <button class="btn-secondary">Enable 2FA</button>
                    </div>
                </div>
                
                <!-- About Section -->
                <div id="about" class="settings-section">
                    <h2>About CinemaTicket</h2>
                    <p style="color: var(--text-secondary);">Version 2.0.0</p>
                    <p style="color: #fff; margin-bottom: 20px;">Your premier destination for movie tickets - Watch in cinema or online</p>
                    
                    <h3 style="color: var(--red); margin-top: 30px;">Features</h3>
                    <ul class="feature-list">
                        <li>Wide movie selection with age-based filtering</li>
                        <li>Easy ticket purchase with seat selection</li>
                        <li>Digital tickets with QR codes</li>
                        <li>Family accounts with linked profiles</li>
                        <li>Multiple customizable themes</li>
                        <li>Favorites and watch history</li>
                        <li>Online streaming with view limits</li>
                    </ul>
                    
                    <h3 style="color: var(--red); margin-top: 30px;">Contact</h3>
                    <p style="color: #fff;">📧 support@cinematicket.com</p>
                    <p style="color: #fff;">📱 +63 (912) 345-6789</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showSection(sectionId, event) {
            event.preventDefault();
            
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