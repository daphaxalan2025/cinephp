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
    <style>
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #333;
        }
        .total {
            font-size: 1.3rem;
            color: #00ffff;
            font-weight: bold;
            border-top: 2px solid #00ffff;
            margin-top: 20px;
            padding-top: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 4px;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #00ffff;
            outline: none;
        }
        .form-group small {
            display: block;
            color: #888;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .pay-button {
            width: 100%;
            padding: 15px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        .pay-button:hover {
            box-shadow: 0 0 30px #00ffff;
        }
        .error {
            color: #ff4444;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(255,68,68,0.1);
            border: 1px solid #ff4444;
            border-radius: 4px;
        }
        .movie-summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #333;
        }
        .poster {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #00ffff;
            border-radius: 4px;
        }
        .parent-notice {
            background: rgba(255, 255, 68, 0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .expiry-info {
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #888;
        }
        .expiry-info strong {
            color: #00ffff;
        }
        .cvv-info {
            background: #333;
            color: #888;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        .file-upload {
            border: 2px dashed #00ffff;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload:hover {
            background: rgba(0,255,255,0.1);
        }
        .file-upload input {
            display: none;
        }
        .file-name {
            margin-top: 10px;
            color: #00ffff;
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
        <h1>Payment</h1>
        
        <!-- Parent notice for kids/teens -->
        <?php if ($user['parent_id'] && $parent): ?>
            <div class="parent-notice">
                👤 This purchase will be sent to your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) for approval.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="payment-container">
            <!-- Order Summary -->
            <div class="card">
                <h2 style="color:#00ffff; margin-bottom:20px;">Order Summary</h2>
                
                <div class="movie-summary">
                    <img src="../uploads/posters/<?php echo $item['poster']; ?>" class="poster">
                    <div>
                        <h3 style="color:#fff;"><?php echo htmlspecialchars($item['title']); ?></h3>
                        <p style="color:#888;"><?php echo ucfirst($type); ?> Ticket</p>
                        <?php if ($type == 'cinema' && !empty($selected_seats)): ?>
                            <p style="color:#00ffff;">Seats: <?php echo implode(', ', $selected_seats); ?></p>
                        <?php endif; ?>
                        <?php if ($for_user_id != $user['id']): ?>
                            <?php
                            $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                            $stmt->execute([$for_user_id]);
                            $for_user = $stmt->fetch();
                            ?>
                            <p style="color:#ffff44;">For: <?php echo htmlspecialchars($for_user['first_name'] . ' ' . $for_user['last_name']); ?></p>
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
                <h2 style="color:#00ffff; margin-bottom:20px;">Payment Details</h2>
                
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
                        <label>Proof of Payment (Screenshot/Photo)</label>
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
                
                <div style="margin-top: 20px; padding: 15px; background: #000; border-radius: 4px;">
                    <p style="color:#888;">📝 <strong>What is CVV?</strong> The 3-digit security code on the back of your credit card.</p>
                    <p style="color:#888; margin-top:5px;">For GCash/PayPal/Bank Transfer, use the transaction/reference number from your payment app.</p>
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