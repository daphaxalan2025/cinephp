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
    <style>
        .container { max-width: 800px; margin: 0 auto; }
        .movie-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
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
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
        .details h2 {
            color: #00ffff;
            margin-bottom: 15px;
        }
        .details p {
            color: #888;
            margin: 10px 0;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 10px;
        }
        .form-group select {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid #00ffff;
            color: #fff;
            border-radius: 4px;
        }
        .price-summary {
            background: #000;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        .total-row {
            font-size: 1.3rem;
            color: #00ffff;
            font-weight: bold;
            border-top: 2px solid #00ffff;
            padding-top: 15px;
            margin-top: 15px;
        }
        .proceed-btn {
            width: 100%;
            padding: 15px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        .proceed-btn:hover {
            box-shadow: 0 0 30px #00ffff;
        }
        .parent-notice {
            background: rgba(255, 255, 68, 0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .info-box h3 {
            color: #00ffff;
            margin-bottom: 10px;
        }
        .info-box ul {
            color: #888;
            margin-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
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
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Online Streaming</h1>
        
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
                <img src="../uploads/posters/<?php echo $schedule['poster']; ?>" class="poster">
                <div class="details">
                    <h2><?php echo htmlspecialchars($schedule['title']); ?></h2>
                    <p>📅 <?php echo date('F d, Y', strtotime($schedule['show_date'])); ?></p>
                    <p>⏰ <?php echo date('h:i A', strtotime($schedule['show_time'])); ?></p>
                    <p>⏱️ Duration: <?php echo $schedule['duration']; ?> minutes</p>
                    <p>👥 <?php echo $schedule['max_viewers'] - $schedule['current_viewers']; ?> spots available</p>
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
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> Ticket(s)</option>
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
            <h3>🎥 Online Streaming Information</h3>
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