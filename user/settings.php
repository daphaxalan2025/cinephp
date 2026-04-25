<?php
// user/settings.php - FIXED: Flash message array to string conversion error
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
        /* Theme Variables - User can change these via theme selection */
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
        }

        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e0e0e0;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.2);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(240, 240, 240, 0.95) 100%);
        }

        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --bg-tertiary: #2a2a4a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255, 0, 255, 0.5);
            --border-color: rgba(255, 0, 255, 0.3);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 58, 0.9) 0%, rgba(20, 20, 50, 0.95) 100%);
        }

        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --bg-tertiary: #0f2a0f;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0, 255, 0, 0.5);
            --border-color: rgba(0, 255, 0, 0.3);
            --card-bg: linear-gradient(135deg, rgba(10, 26, 10, 0.9) 0%, rgba(5, 20, 5, 0.95) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, var(--accent) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, var(--accent) 0%, transparent 50%);
            opacity: 0.03;
            pointer-events: none;
            z-index: -1;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(var(--bg-secondary), 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
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
            color: var(--accent);
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
            text-shadow: var(--accent-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 8px;
            font-size: 1.2rem;
            filter: drop-shadow(0 0 5px var(--accent));
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
            background: var(--accent);
            transition: width 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--accent);
        }
        
        .nav-links a:hover::after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--accent);
        }
        
        .nav-links a.active::after {
            width: 60%;
        }
        
        .profile-switch {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid #e50914;
            border-radius: 40px;
            padding: 6px 15px !important;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .profile-switch:hover {
            background: #e50914;
            color: white !important;
        }
        
        /* Main Container */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
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
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
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
            border-color: var(--accent);
            background: rgba(var(--accent), 0.1);
            color: var(--accent);
            transform: translateX(5px);
        }
        
        /* Content */
        .settings-content {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
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
            color: var(--accent);
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
            border-color: var(--accent);
            transform: scale(1.05);
            box-shadow: 0 0 30px var(--accent-glow);
        }
        
        .preview-dark {
            background: linear-gradient(135deg, #0a0a0a, #1a1a1a);
            border: 2px solid #e50914;
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
            border: 2px solid #e50914;
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
            border: 2px solid #ff00ff;
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
            border: 2px solid #00ff00;
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
            background: rgba(var(--accent), 0.05);
        }
        
        .form-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--accent);
        }
        
        .form-group input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            margin-top: 5px;
        }
        
        .form-group input[type="password"]:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 20px var(--accent-glow);
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
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 14px 30px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px var(--accent-glow);
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
            background: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
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
            color: var(--accent);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Alerts */
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--accent);
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
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
        
        /* Responsive */
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
            
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .settings-menu a:hover {
                transform: none;
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
                <a href="movies.php" class="active">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <div class="profile-badge">
                    <span>👤</span>
                    <span class="profile-name"><?php echo htmlspecialchars($_SESSION['profile_name'] ?? 'Profile'); ?></span>
                    <a href="select_profile.php" class="profile-switch">Switch</a>
                </div>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Settings</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- FIXED: Correct flash message display -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>">
                <?php 
                echo htmlspecialchars($_SESSION['flash']['message']);
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
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
                        <h3 style="color: var(--accent);">Two-Factor Authentication</h3>
                        <p style="color: var(--text-secondary);">Add an extra layer of security to your account</p>
                        <button class="btn-secondary">Enable 2FA</button>
                    </div>
                </div>
                
                <!-- About Section -->
                <div id="about" class="settings-section">
                    <h2>About CinemaTicket</h2>
                    <p style="color: var(--text-secondary);"
                                        </div>
                    
                    <h3 style="color: var(--accent); margin-top: 30px;">Features</h3>
                    <ul class="feature-list">
                        <li>Wide movie selection with age-based filtering</li>
                        <li>Easy ticket purchase with seat selection</li>
                        <li>Digital tickets with QR codes</li>
                        <li>Family accounts with linked profiles</li>
                        <li>Multiple customizable themes</li>
                        <li>Favorites and watch history</li>
                        <li>Online streaming with view limits</li>
                    </ul>
                    
                    <h3 style="color: var(--accent); margin-top: 30px;">Contact</h3>
                    <p style="color: var(--text-primary);">📧 support@cinematicket.com</p>
                    <p style="color: var(--text-primary);">📱 +63 (912) 345-6789</p>
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
            
            // Update URL hash without scrolling
            history.pushState(null, null, '#' + sectionId);
        }

        // Check URL hash on page load
        window.addEventListener('load', function() {
            if (window.location.hash) {
                const sectionId = window.location.hash.substring(1);
                const link = document.querySelector(`.settings-menu a[href="#${sectionId}"]`);
                if (link) {
                    link.click();
                }
            }
        });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>