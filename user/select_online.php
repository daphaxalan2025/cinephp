<?php
// user/select_online.php
require_once '../includes/functions.php';
requireLogin();

$schedule_id = $_GET['schedule_id'] ?? 0;
$pdo = getDB();
$user = getCurrentUser();

// Get schedule details
$stmt = $pdo->prepare("
    SELECT os.*, m.title, m.poster, m.duration
    FROM online_schedule os
    JOIN movies m ON os.movie_id = m.id
    WHERE os.id = ?
");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    setFlash('Schedule not found', 'error');
    header('Location: movies.php');
    exit;
}

// Get user's linked accounts (for adults)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE parent_id = ?");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}

// Get parent info (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Streaming - <?php echo htmlspecialchars($schedule['title']); ?></title>
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
            max-width: 800px;
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
        
        /* Movie Card */
        .movie-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .movie-card::before {
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
        
        .movie-header {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .poster {
            width: 150px;
            height: 220px;
            object-fit: cover;
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .poster:hover {
            border-color: var(--red);
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
        }
        
        .details h2 {
            color: var(--red);
            margin-bottom: 15px;
            font-size: 1.8rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .details p {
            color: var(--text-secondary);
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .details p i {
            color: var(--red);
            width: 20px;
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
        
        /* Form Elements */
        .form-group {
            margin: 20px 0;
        }
        
        .form-group label {
            display: block;
            color: var(--red);
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .form-group select:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        /* Price Summary */
        .price-summary {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            color: var(--text-secondary);
        }
        
        .price-row span:last-child {
            color: var(--red);
            font-weight: 600;
        }
        
        .total-row {
            font-size: 1.3rem;
            font-weight: 700;
            border-top: 2px solid var(--red);
            padding-top: 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        .total-row span:last-child {
            color: var(--red);
        }
        
        /* Proceed Button */
        .proceed-btn {
            width: 100%;
            padding: 16px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 20px;
        }
        
        .proceed-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .proceed-btn:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
        }
        
        .proceed-btn:hover::before {
            left: 100%;
        }
        
        /* Info Box */
        .info-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .info-box h3 {
            color: var(--red);
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box ul {
            color: var(--text-secondary);
            margin-left: 20px;
        }
        
        .info-box li {
            margin: 10px 0;
            padding-left: 5px;
        }
        
        .info-box li::marker {
            color: var(--red);
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Availability Badge */
        .availability {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(68, 255, 68, 0.15);
            border: 1px solid #44ff44;
            border-radius: 30px;
            color: #44ff44;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .movie-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .poster {
                margin: 0 auto;
            }
            
            .details p {
                justify-content: center;
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
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Online Streaming</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Parent notice for kids/teens -->
        <?php if ($user['account_type'] == 'kid' || $user['account_type'] == 'teen'): ?>
            <?php if ($parent): ?>
                <div class="parent-notice">
                    👤 This purchase will be sent to your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) for approval.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="movie-card">
            <div class="movie-header">
                <?php if ($schedule['poster']): ?>
                    <img src="../uploads/posters/<?php echo $schedule['poster']; ?>" class="poster">
                <?php else: ?>
                    <div style="width:150px; height:220px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
                        No Poster
                    </div>
                <?php endif; ?>
                
                <div class="details">
                    <h2><?php echo htmlspecialchars($schedule['title']); ?></h2>
                    <p><i>📅</i> <?php echo date('F d, Y', strtotime($schedule['show_date'])); ?></p>
                    <p><i>⏰</i> <?php echo date('h:i A', strtotime($schedule['show_time'])); ?></p>
                    <p><i>⏱️</i> Duration: <?php echo $schedule['duration']; ?> minutes</p>
                    <span class="availability">
                        👥 <?php echo $schedule['max_viewers'] - $schedule['current_viewers']; ?> spots available
                    </span>
                </div>
            </div>
            
            <!-- Family purchase option (for adults) -->
            <?php if (!empty($linked_accounts) && $user['account_type'] == 'adult'): ?>
                <div class="form-group">
                    <label>Purchase for:</label>
                    <select id="forUserId">
                        <option value="<?php echo $user['id']; ?>">Myself</option>
                        <?php foreach ($linked_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Number of Tickets</label>
                <select id="quantity" onchange="updatePrice()">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="price-summary">
                <div class="price-row">
                    <span>Price per ticket:</span>
                    <span>$<?php echo number_format($schedule['price'], 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">$<?php echo number_format($schedule['price'], 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Processing Fee (₱150 each):</span>
                    <span id="fee">$3.00</span>
                </div>
                <div class="total-row">
                    <span>TOTAL:</span>
                    <span id="total">$<?php echo number_format($schedule['price'] + 3, 2); ?></span>
                </div>
            </div>
            
            <button class="proceed-btn" onclick="proceedToPayment()">
                Proceed to Payment
            </button>
        </div>
        
        <div class="info-box">
            <h3><i>🎥</i> Online Streaming Information</h3>
            <ul>
                <li>Watch from any device (computer, tablet, phone)</li>
                <li>3 views included per ticket</li>
                <li>Valid for 30 days after purchase</li>
                <li>HD streaming quality</li>
                <li>No seat selection needed</li>
                <li>Start watching at your selected time</li>
            </ul>
        </div>
    </main>
    
    <script>
        const price = <?php echo $schedule['price']; ?>;
        const processingFee = 3.00;
        
        function updatePrice() {
            const quantity = parseInt(document.getElementById('quantity').value);
            const subtotal = price * quantity;
            const fee = processingFee * quantity;
            const total = subtotal + fee;
            
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('fee').textContent = '$' + fee.toFixed(2);
            document.getElementById('total').textContent = '$' + total.toFixed(2);
        }
        
        function proceedToPayment() {
            const quantity = document.getElementById('quantity').value;
            const forUserId = document.getElementById('forUserId')?.value || <?php echo $user['id']; ?>;
            
            window.location.href = `payment.php?type=online&id=<?php echo $schedule_id; ?>&quantity=${quantity}&for_user_id=${forUserId}`;
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>