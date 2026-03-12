<?php
// user/payment.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;
$quantity = intval($_GET['quantity'] ?? 1);
$selected_seats = isset($_GET['seats']) ? explode(',', $_GET['seats']) : [];
$for_user_id = $_GET['for_user_id'] ?? $user['id'];

$processing_fee = 3.00; // ₱150

// Map the type to database values
$db_ticket_type = ($type == 'cinema') ? 'cinema' : 'online';

// Validate for_user_id
$purchaser_id = $user['id'];
if ($for_user_id != $user['id']) {
    if ($user['account_type'] == 'adult') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND parent_id = ?");
        $stmt->execute([$for_user_id, $user['id']]);
        if (!$stmt->fetch()) {
            setFlash('Invalid linked account', 'error');
            header('Location: movies.php');
            exit;
        }
    } else {
        setFlash('You cannot purchase tickets for others', 'error');
        header('Location: movies.php');
        exit;
    }
}

// Get item details based on type
if ($type == 'cinema') {
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, m.poster, c.name as cinema_name
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        JOIN cinemas c ON s.cinema_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        setFlash('Screening not found', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Calculate expiry date (screening date + time)
    $expiry_date = date('Y-m-d H:i:s', strtotime($item['show_date'] . ' ' . $item['show_time']));
    
    // Check seat availability
    if (count($selected_seats) != $quantity) {
        setFlash('Invalid seat selection', 'error');
        header('Location: select_seats.php?screening_id=' . $id);
        exit;
    }
    
    // Verify seats are still available
    $stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('paid', 'pending')");
    $stmt->execute([$id]);
    $booked = [];
    while ($row = $stmt->fetch()) {
        if ($row['seat_numbers']) {
            $booked = array_merge($booked, explode(',', $row['seat_numbers']));
        }
    }
    
    foreach ($selected_seats as $seat) {
        if (in_array($seat, $booked)) {
            setFlash('Some seats were just booked. Please select again.', 'error');
            header('Location: select_seats.php?screening_id=' . $id);
            exit;
        }
    }
    
} elseif ($type == 'online') {
    $stmt = $pdo->prepare("
        SELECT os.*, m.title, m.poster
        FROM online_schedule os
        JOIN movies m ON os.movie_id = m.id
        WHERE os.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        setFlash('Schedule not found', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Calculate expiry date (online schedule time)
    $expiry_date = date('Y-m-d H:i:s', strtotime($item['show_date'] . ' ' . $item['show_time']));
    
    // Check viewer capacity
    if ($item['current_viewers'] + $quantity > $item['max_viewers']) {
        setFlash('Not enough viewer spots available', 'error');
        header('Location: select_online.php?schedule_id=' . $id);
        exit;
    }
    
} else {
    setFlash('Invalid request', 'error');
    header('Location: movies.php');
    exit;
}

$subtotal = $item['price'] * $quantity;
$total_fee = $processing_fee * $quantity;
$total = $subtotal + $total_fee;

// Get parent info if purchaser is a kid/teen
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_number = $_POST['transaction_number'] ?? '';
    $proof_file = $_FILES['proof'] ?? null;
    
    if (empty($payment_method)) {
        $error = 'Please select a payment method';
    } elseif (empty($transaction_number)) {
        $error = 'Please enter transaction number';
    } else {
        $pdo->beginTransaction();
        
        try {
            // Handle proof upload
            $proof_filename = null;
            if ($proof_file && $proof_file['error'] == 0) {
                $target_dir = UPLOAD_PATH . 'proofs/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                if (in_array($proof_file['type'], $allowed_types)) {
                    $ext = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
                    $proof_filename = 'proof_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($proof_file['tmp_name'], $target_dir . $proof_filename);
                }
            }
            
            // FIRST: Create payment record (without ticket_id)
            $transaction_id = 'TXN' . time() . rand(100, 999);
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, payment_method, payment_status, transaction_id, proof_of_transaction)
                VALUES (?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$user['id'], $total, $payment_method, $transaction_id, $proof_filename]);
            $payment_id = $pdo->lastInsertId();
            
            // SECOND: Generate ticket code
            $ticket_code = 'TIX' . time() . rand(100, 999) . strtoupper(substr(md5(uniqid()), 0, 4));
            
            // THIRD: Create ticket with payment_id - use correct ticket_type values
            $seat_string = ($type == 'cinema') ? implode(',', $selected_seats) : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO tickets (
                    ticket_code, user_id, screening_id, online_schedule_id, ticket_type,
                    quantity, total_price, seat_numbers, payment_id, payment_status, status, expiry_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'paid', ?)
            ");
            
            $screening_id_val = ($type == 'cinema') ? $id : null;
            $online_id_val = ($type == 'online') ? $id : null;
            
            $stmt->execute([
                $ticket_code,
                $for_user_id,
                $screening_id_val,
                $online_id_val,
                $db_ticket_type,  // Use mapped value
                $quantity,
                $total,
                $seat_string,
                $payment_id,
                $expiry_date
            ]);
            
            $ticket_id = $pdo->lastInsertId();
            
            // FOURTH: Update payment with ticket_id
            $stmt = $pdo->prepare("UPDATE payments SET ticket_id = ? WHERE id = ?");
            $stmt->execute([$ticket_id, $payment_id]);
            
            // Update available seats for cinema
            if ($type == 'cinema') {
                $stmt = $pdo->prepare("UPDATE screenings SET available_seats = available_seats - ? WHERE id = ?");
                $stmt->execute([$quantity, $id]);
            }
            
            // Update current viewers for online
            if ($type == 'online') {
                $stmt = $pdo->prepare("UPDATE online_schedule SET current_viewers = current_viewers + ? WHERE id = ?");
                $stmt->execute([$quantity, $id]);
            }
            
            $pdo->commit();
            
            setFlash('Payment successful! Your ticket has been generated.', 'success');
            header('Location: purchases.php?ticket=' . $ticket_code);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Payment failed: ' . $e->getMessage();
            error_log("Payment error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - CinemaTicket</title>
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
        
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Cards */
        .card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
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
        
        .card h2 {
            color: var(--red);
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 600;
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
        
        /* Movie Summary */
        .movie-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .poster {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .poster:hover {
            border-color: var(--red);
            transform: scale(1.05);
        }
        
        .movie-details h3 {
            color: #fff;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .movie-details p {
            color: var(--text-secondary);
            margin: 2px 0;
        }
        
        .movie-details .highlight {
            color: var(--red);
            font-weight: 600;
        }
        
        .movie-details .for-user {
            color: #ffff44;
            margin-top: 5px;
        }
        
        /* Expiry Info */
        .expiry-info {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 15px;
            margin: 20px 0;
            color: var(--text-secondary);
        }
        
        .expiry-info strong {
            color: var(--red);
        }
        
        .expiry-info small {
            display: block;
            margin-top: 5px;
            color: #666;
        }
        
        /* Order Rows */
        .row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
        }
        
        .total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--red);
            border-top: 2px solid var(--red);
            margin-top: 20px;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: var(--red);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-group small {
            display: block;
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 5px;
            padding-left: 15px;
        }
        
        /* File Upload */
        .file-upload {
            border: 2px dashed rgba(229, 9, 20, 0.3);
            padding: 25px;
            text-align: center;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: var(--red);
            background: rgba(229, 9, 20, 0.05);
        }
        
        .file-upload input {
            display: none;
        }
        
        .file-upload span {
            color: var(--text-secondary);
        }
        
        .file-name {
            margin-top: 10px;
            color: var(--red);
            font-size: 0.9rem;
        }
        
        /* Pay Button */
        .pay-button {
            width: 100%;
            padding: 16px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .pay-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .pay-button:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
        }
        
        .pay-button:hover::before {
            left: 100%;
        }
        
        /* Info Box */
        .info-box {
            margin-top: 20px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            border: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .info-box p {
            color: var(--text-secondary);
            margin: 5px 0;
        }
        
        .info-box strong {
            color: var(--red);
        }
        
        /* Error */
        .error {
            color: #ff4444;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid #ff4444;
            border-radius: 40px;
            border-left: 4px solid #ff4444;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .movie-summary {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .card {
                padding: 20px;
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
        <h1>Complete Payment</h1>
        
        <!-- Parent notice for kids/teens -->
        <?php if ($user['parent_id'] && $parent): ?>
            <div class="parent-notice">
                👤 This purchase will be sent to your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) for approval.
            </div>
        <?php endif; ?>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="payment-container">
            <!-- Order Summary -->
            <div class="card">
                <h2>Order Summary</h2>
                
                <div class="movie-summary">
                    <?php if ($item['poster']): ?>
                        <img src="../uploads/posters/<?php echo $item['poster']; ?>" class="poster">
                    <?php else: ?>
                        <div style="width:80px; height:120px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
                            No Poster
                        </div>
                    <?php endif; ?>
                    
                    <div class="movie-details">
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p><?php echo ucfirst($type); ?> Ticket</p>
                        <?php if ($type == 'cinema' && !empty($selected_seats)): ?>
                            <p class="highlight">Seats: <?php echo implode(', ', $selected_seats); ?></p>
                        <?php endif; ?>
                        <?php if ($for_user_id != $user['id']): ?>
                            <?php
                            $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                            $stmt->execute([$for_user_id]);
                            $for_user = $stmt->fetch();
                            ?>
                            <p class="for-user">For: <?php echo htmlspecialchars($for_user['first_name'] . ' ' . $for_user['last_name']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="expiry-info">
                    <strong>⏰ Ticket Expiry:</strong> 
                    <?php echo date('F d, Y h:i A', strtotime($expiry_date)); ?>
                    <br>
                    <small>Ticket will expire after this time and cannot be used</small>
                </div>
                
                <div class="row">
                    <span>Price per ticket:</span>
                    <span>$<?php echo number_format($item['price'], 2); ?></span>
                </div>
                <div class="row">
                    <span>Quantity:</span>
                    <span><?php echo $quantity; ?> ticket(s)</span>
                </div>
                <div class="row">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="row">
                    <span>Processing Fee (₱150 each):</span>
                    <span>$<?php echo number_format($total_fee, 2); ?></span>
                </div>
                <div class="total">
                    <span>TOTAL:</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="card">
                <h2>Payment Details</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" required>
                            <option value="">Select payment method</option>
                            <option value="gcash">📱 GCash</option>
                            <option value="paypal">🅿️ PayPal</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                            <option value="credit_card">💳 Credit Card</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Transaction / Reference Number</label>
                        <input type="text" name="transaction_number" placeholder="Enter transaction/reference number" required>
                        <small>Your payment reference/transaction number from GCash, PayPal, or bank transfer</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Proof of Payment</label>
                        <div class="file-upload" onclick="document.getElementById('proof').click()">
                            <input type="file" id="proof" name="proof" accept="image/*,application/pdf" onchange="updateFileName(this)">
                            <span>📎 Click to upload screenshot or receipt</span>
                            <div id="fileName" class="file-name"></div>
                        </div>
                        <small>Upload screenshot of your payment confirmation or receipt (JPG, PNG, PDF)</small>
                    </div>
                    
                    <button type="submit" class="pay-button">
                        Confirm Payment - $<?php echo number_format($total, 2); ?>
                    </button>
                </form>
                
                <div class="info-box">
                    <p>📝 <strong>What is CVV?</strong> The 3-digit security code on the back of your credit card.</p>
                    <p>For GCash/PayPal/Bank Transfer, use the transaction/reference number from your payment app.</p>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function updateFileName(input) {
            const fileName = input.files[0]?.name;
            document.getElementById('fileName').textContent = fileName ? 'Selected: ' + fileName : '';
        }
    </script>
</body>
</html>