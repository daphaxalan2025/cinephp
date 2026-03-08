<?php
// index.php
require_once 'includes/functions.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CinemaTicket - Movie Ticket System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <?php if (isLoggedIn()): ?>
                    <?php if ($_SESSION['account_type'] == 'admin'): ?>
                        <a href="admin/dashboard.php">Admin</a>
                    <?php elseif ($_SESSION['account_type'] == 'staff'): ?>
                        <a href="staff/dashboard.php">Staff</a>
                    <?php else: ?>
                        <a href="user/movies.php">Movies</a>
                    <?php endif; ?>
                    <a href="user/purchases.php">My Tickets</a>
                    <a href="user/profile.php">Profile</a>
                    <a href="auth/logout.php">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
                <?php else: ?>
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; padding: 100px 20px;">
            <h1 style="font-size: 3rem; color: #00ffff; text-shadow: 0 0 20px #00ffff; margin-bottom: 20px;">
                Welcome to CinemaTicket
            </h1>
            <p style="font-size: 1.2rem; color: #ccc; margin-bottom: 40px;">
                Book your movie tickets online with interactive seat selection
            </p>
            
            <?php if (!isLoggedIn()): ?>
                <div style="display: flex; gap: 20px; justify-content: center;">
                    <a href="auth/register.php" class="btn btn-primary" style="padding: 15px 40px;">Get Started</a>
                    <a href="auth/login.php" class="btn" style="padding: 15px 40px;">Login</a>
                </div>
            <?php else: ?>
                <a href="user/movies.php" class="btn btn-primary" style="padding: 15px 40px;">Browse Movies</a>
            <?php endif; ?>
        </div>
        
        <!-- Features -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 60px;">
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 20px;">🎬</div>
                <h3 style="color: #00ffff;">Wide Selection</h3>
                <p style="color: #ccc;">Choose from the latest movies</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 20px;">🪑</div>
                <h3 style="color: #00ffff;">Choose Your Seat</h3>
                <p style="color: #ccc;">Interactive seat selection</p>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 20px;">🎟️</div>
                <h3 style="color: #00ffff;">Digital Tickets</h3>
                <p style="color: #ccc;">Easy verification with QR codes</p>
            </div>
        </div>
    </main>
    
    <script src="assets/js/script.js"></script>
</body>
</html>