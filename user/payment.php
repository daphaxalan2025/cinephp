<?php
// user/payment.php - WITH PAYMONGO PAYMENT INTENTS API (TEST MODE)
// Supports: GCash, PayMaya, Credit Card, GrabPay via PayMongo
// REFERENCE_NUMBER REMOVED - Use transaction_id instead
// FIXED: Removed all payment_id references from tickets table
// FIXED: Added CSRF protection, proper error handling, seat validation
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Start session for CSRF token if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Custom error function with styling
function showPaymentError($message, $back_url = null) {
    // Use consistent theme source
    $current_theme = $_SESSION['theme_preference'] ?? 'dark';
    $back_url = $back_url ?? $_SERVER['HTTP_REFERER'] ?? 'movies.php';
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="<?php echo $current_theme; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Error - CinemaTicket</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            :root[data-theme="dark"] {
                --bg-primary: #0a0a0a;
                --bg-secondary: #1a1a1a;
                --text-primary: #ffffff;
                --text-secondary: #b3b3b3;
                --accent: #e50914;
                --accent-glow: 0 0 20px rgba(229,9,20,0.3);
                --border-color: rgba(229,9,20,0.2);
                --card-bg: linear-gradient(135deg, rgba(26,26,26,0.9) 0%, rgba(20,20,20,0.95) 100%);
            }
            :root[data-theme="light"] {
                --bg-primary: #f5f5f5;
                --bg-secondary: #ffffff;
                --text-primary: #333333;
                --text-secondary: #666666;
                --accent: #e50914;
                --accent-glow: 0 0 20px rgba(229,9,20,0.2);
                --border-color: rgba(229,9,20,0.2);
                --card-bg: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,240,240,0.95) 100%);
            }
            :root[data-theme="neon"] {
                --bg-primary: #0a0a2a;
                --bg-secondary: #1a1a3a;
                --text-primary: #00ffff;
                --text-secondary: #ff00ff;
                --accent: #ff00ff;
                --accent-glow: 0 0 20px rgba(255,0,255,0.5);
                --border-color: rgba(255,0,255,0.3);
                --card-bg: linear-gradient(135deg, rgba(26,26,58,0.9) 0%, rgba(20,20,50,0.95) 100%);
            }
            :root[data-theme="matrix"] {
                --bg-primary: #000000;
                --bg-secondary: #0a1a0a;
                --text-primary: #00ff00;
                --text-secondary: #00aa00;
                --accent: #00ff00;
                --accent-glow: 0 0 20px rgba(0,255,0,0.5);
                --border-color: rgba(0,255,0,0.3);
                --card-bg: linear-gradient(135deg, rgba(10,26,10,0.9) 0%, rgba(5,20,5,0.95) 100%);
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: var(--bg-primary);
                color: var(--text-primary);
                font-family: 'Inter', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
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
            }
            .error-card {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: 32px;
                padding: 50px 40px;
                max-width: 500px;
                width: 90%;
                text-align: center;
                box-shadow: 0 30px 60px rgba(0,0,0,0.5);
                position: relative;
                overflow: hidden;
            }
            .error-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, transparent, var(--accent), var(--accent), transparent);
                animation: slideBorder 3s infinite;
            }
            @keyframes slideBorder {
                0% { transform: translateX(-100%); }
                50% { transform: translateX(100%); }
                100% { transform: translateX(100%); }
            }
            .error-icon { font-size: 5rem; margin-bottom: 20px; }
            h1 { color: var(--accent); font-size: 2rem; margin-bottom: 15px; }
            .error-message { color: var(--text-secondary); margin-bottom: 30px; line-height: 1.6; }
            .btn {
                display: inline-block;
                background: var(--accent);
                color: var(--bg-primary);
                padding: 14px 35px;
                border-radius: 40px;
                text-decoration: none;
                font-weight: 700;
                transition: all 0.3s;
                margin: 5px;
            }
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px var(--accent-glow);
            }
            .btn-secondary {
                background: transparent;
                border: 1px solid var(--accent);
                color: var(--accent);
            }
            .btn-secondary:hover {
                background: rgba(var(--accent),0.1);
                color: var(--accent);
            }
            .cinema-strip {
                height: 2px;
                background: linear-gradient(90deg, transparent, var(--accent), transparent);
                margin: 20px 0;
                opacity: 0.3;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">⚠️</div>
            <h1>Payment Error</h1>
            <div class="error-message"><?php echo htmlspecialchars($message); ?></div>
            <div class="cinema-strip"></div>
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn">← Go Back</a>
            <a href="movies.php" class="btn btn-secondary">🎬 Browse Movies</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Function to validate seat numbers
function validateSeats($seats, $screening_id, $pdo) {
    if (empty($seats)) {
        return ['valid' => true, 'error' => null];
    }
    
    foreach ($seats as $seat) {
        if (!preg_match('/^[A-Z][0-9]+$/', $seat)) {
            return ['valid' => false, 'error' => 'Invalid seat format: ' . htmlspecialchars($seat)];
        }
    }
    
    // Check for duplicate seat bookings
    $placeholders = implode(',', array_fill(0, count($seats), '?'));
    $stmt = $pdo->prepare("
        SELECT seat_numbers FROM tickets 
        WHERE screening_id = ? AND status IN ('paid', 'pending')
    ");
    $stmt->execute([$screening_id]);
    $existing_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $booked_seats = [];
    foreach ($existing_tickets as $ticket) {
        if ($ticket['seat_numbers'] && $ticket['seat_numbers'] != 'N/A') {
            $booked = explode(',', $ticket['seat_numbers']);
            $booked_seats = array_merge($booked_seats, $booked);
        }
    }
    
    foreach ($seats as $seat) {
        if (in_array($seat, $booked_seats)) {
            return ['valid' => false, 'error' => 'Seat ' . htmlspecialchars($seat) . ' is already booked.'];
        }
    }
    
    return ['valid' => true, 'error' => null];
}

// Get parameters from URL
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$quantity = intval($_GET['quantity'] ?? 1);
$for_user_id = intval($_GET['for_user_id'] ?? $user['id']);
$selected_seats = isset($_GET['seats']) ? explode(',', $_GET['seats']) : [];

// Validate required parameters
if (empty($type) || $id <= 0) {
    showPaymentError("Invalid request. Missing ticket type or ID.", "movies.php");
}

if ($type != 'cinema' && $type != 'online') {
    showPaymentError("Invalid ticket type. Please select a valid ticket.", "movies.php");
}

// FORCE QUANTITY TO 1 FOR ALL TICKETS
$quantity = 1;

// Use TICKET_FEE constant from functions.php
$fee_per_ticket = defined('TICKET_FEE') ? TICKET_FEE : 50; // ₱50 flat fee

// Get item details
if ($type == 'cinema') {
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, m.id as movie_id, m.release_date, c.name as cinema_name
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        JOIN cinemas c ON s.cinema_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        showPaymentError("Screening not found. It may have been removed or expired.", "movies.php");
    }
    
    // Validate seats for cinema tickets
    if (!empty($selected_seats)) {
        $seat_validation = validateSeats($selected_seats, $id, $pdo);
        if (!$seat_validation['valid']) {
            showPaymentError($seat_validation['error'], "movie_detail.php?id=" . $item['movie_id']);
        }
    }
    
    // Check if user already purchased a ticket for this screening (max 1 per person)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets 
        WHERE user_id = ? AND screening_id = ? AND status IN ('paid', 'pending')
    ");
    $stmt->execute([$for_user_id, $id]);
    $existing_tickets = $stmt->fetchColumn();
    
    if ($existing_tickets >= 1) {
        showPaymentError("You have already purchased a ticket for this screening. One ticket per person only.", "movie_detail.php?id=" . $item['movie_id']);
    }
    
    $base_price = $item['price'];
    $cinema_name = $item['cinema_name'];
    $show_info = date('M d, Y h:i A', strtotime($item['show_date'] . ' ' . $item['show_time']));
} else { // online
    $stmt = $pdo->prepare("
        SELECT os.*, m.title, m.id as movie_id, m.release_date
        FROM online_schedule os
        JOIN movies m ON os.movie_id = m.id
        WHERE os.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        showPaymentError("Online schedule not found. It may have been removed or expired.", "movies.php");
    }
    
    // For online tickets, limit to 1 per schedule
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM tickets 
        WHERE user_id = ? AND online_schedule_id = ? AND status IN ('paid', 'pending')
    ");
    $stmt->execute([$for_user_id, $id]);
    $existing_tickets = $stmt->fetchColumn();
    
    if ($existing_tickets > 0) {
        showPaymentError("You have already purchased a ticket for this online schedule. One ticket per person only.", "movie_detail.php?id=" . $item['movie_id']);
    }
    
    $base_price = $item['price'];
    $cinema_name = 'Online Streaming';
    $show_info = date('M d, Y h:i A', strtotime($item['show_date'] . ' ' . $item['show_time']));
}

$subtotal = $base_price * $quantity;
$total_fee = $fee_per_ticket * $quantity;
$total = $subtotal + $total_fee;

$seat_numbers = ($type == 'cinema' && !empty($selected_seats)) ? implode(',', $selected_seats) : 'N/A';
$booking_reference = "BK-" . date('Ymd') . "-" . rand(1000, 9999);

// PayMongo payment methods
$paymongo_method_types = ['gcash', 'paymaya', 'card', 'grab_pay'];
$paymongo_display_names = [
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
    'card' => 'Credit Card',
    'grab_pay' => 'GrabPay'
];

// ========== HANDLE FORM SUBMISSION ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        showPaymentError("Invalid security token. Please refresh the page and try again.", $_SERVER['HTTP_REFERER'] ?? 'movies.php');
    }
    
    $payment_method = $_POST['payment_method'] ?? '';
    $selected_paymongo_method = $_POST['paymongo_method_type'] ?? 'gcash';
    
    if (empty($payment_method)) {
        showPaymentError("Please select a payment method.", $_SERVER['HTTP_REFERER'] ?? 'movies.php');
    }
    
    // Check if it's a PayMongo method
    $is_paymongo = in_array($payment_method, ['gcash', 'paymaya', 'credit_card', 'grab_pay']);
    
    // Re-validate existing tickets to prevent double payment
    if ($type == 'cinema') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tickets 
            WHERE user_id = ? AND screening_id = ? AND status IN ('paid', 'pending')
        ");
        $stmt->execute([$for_user_id, $id]);
        if ($stmt->fetchColumn() >= 1) {
            showPaymentError("You have already purchased a ticket for this screening.", "movie_detail.php?id=" . $item['movie_id']);
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tickets 
            WHERE user_id = ? AND online_schedule_id = ? AND status IN ('paid', 'pending')
        ");
        $stmt->execute([$for_user_id, $id]);
        if ($stmt->fetchColumn() > 0) {
            showPaymentError("You have already purchased a ticket for this online schedule.", "movie_detail.php?id=" . $item['movie_id']);
        }
    }
    
    if ($is_paymongo) {
        // ========== PAYMONGO PAYMENT INTENTS FLOW ==========
        $ticket_code = 'TKT-' . strtoupper(uniqid()) . '-' . date('Ymd');
        
        // Calculate week_expiry for online tickets
        $week_expiry = null;
        if ($type == 'online') {
            $release_date = $item['release_date'];
            $movie_expiry = date('Y-m-d', strtotime($release_date . ' +3 months'));
            $today = date('Y-m-d');
            
            if ($today >= $movie_expiry) {
                $week_expiry = date('Y-m-d', strtotime('+7 days'));
            } else {
                $week_expiry = date('Y-m-d', strtotime($release_date . ' +7 days'));
            }
        }
        
        $pdo->beginTransaction();
        try {
            if ($type == 'cinema') {
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (
                        ticket_code, user_id, screening_id, ticket_type, 
                        quantity, total_price, seat_numbers, 
                        status, purchase_date
                    ) VALUES (?, ?, ?, 'cinema', ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$ticket_code, $for_user_id, $id, $quantity, $total, $seat_numbers]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tickets (
                        ticket_code, user_id, online_schedule_id, ticket_type, 
                        quantity, total_price, 
                        status, week_expiry, purchase_date
                    ) VALUES (?, ?, ?, 'online', ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([$ticket_code, $for_user_id, $id, $quantity, $total, $week_expiry]);
            }
            $ticket_id = $pdo->lastInsertId();
            
            // Create payment record with pending status
            $transaction_id = 'TXN' . time() . rand(100, 999);
            $stmt = $pdo->prepare("
                INSERT INTO payments (
                    user_id, ticket_id, amount, payment_method, payment_status, 
                    transaction_id, payment_date
                ) VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([$user['id'], $ticket_id, $total, $payment_method, $transaction_id]);
            $payment_id = $pdo->lastInsertId();
            
            // DO NOT update tickets table - relationship is maintained via payments.ticket_id
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            showPaymentError("Failed to create ticket: " . $e->getMessage(), "movies.php");
        }
        
        // ========== PAYMONGO CHECKOUT ==========
        try {
            // Map selected method to PayMongo format
            $paymongo_method_type = $selected_paymongo_method;
            if ($payment_method == 'credit_card') {
                $paymongo_method_type = 'card';
            }
            
            $checkout_data = [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => round($total * 100),
                                'name' => $item['title'],
                                'quantity' => 1
                            ]
                        ],
                        'payment_method_types' => [$paymongo_method_type],
                        'success_url' => BASE_URL . "/user/payment-success.php?ticket=$ticket_code&checkout=1&payment_id=$payment_id",
                        'cancel_url' => BASE_URL . "/user/payment.php?cancelled=1&type=$type&id=$id",
                        'description' => "Booking {$booking_reference} - {$item['title']}",
                        'metadata' => [
                            'ticket_code' => $ticket_code,
                            'payment_id' => (string)$payment_id,
                            'user_id' => (string)$user['id']
                        ]
                    ]
                ]
            ];

            $ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
                ],
                CURLOPT_POSTFIELDS => json_encode($checkout_data)
            ]);

            $response = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                // Rollback on API failure
                $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?")->execute([$ticket_id]);
                $pdo->prepare("UPDATE payments SET payment_status = 'failed' WHERE id = ?")->execute([$payment_id]);
                showPaymentError("Connection error: " . $curl_error, "movies.php");
            }

            if ($http !== 200 && $http !== 201) {
                // Rollback on API failure
                $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?")->execute([$ticket_id]);
                $pdo->prepare("UPDATE payments SET payment_status = 'failed' WHERE id = ?")->execute([$payment_id]);
                
                $error = json_decode($response, true);
                $error_msg = $error['errors'][0]['detail'] ?? 'Unknown error occurred';
                showPaymentError("Checkout Error: " . $error_msg, "movies.php");
            }

            $result = json_decode($response, true);
            $checkout_url = $result['data']['attributes']['checkout_url'];
            $checkout_id = $result['data']['id'];

            // Save checkout ID to payment record
            $pdo->prepare("UPDATE payments SET paymongo_checkout_id = ? WHERE id = ?")
                ->execute([$checkout_id, $payment_id]);

            header("Location: " . $checkout_url);
            exit;

        } catch (Exception $e) {
            // Rollback on exception
            $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?")->execute([$ticket_id]);
            $pdo->prepare("UPDATE payments SET payment_status = 'failed' WHERE id = ?")->execute([$payment_id]);
            showPaymentError("Checkout Exception: " . $e->getMessage(), "movies.php");
        }
        
    } else {
        // ========== MANUAL PAYMENT FLOW (Removed - no longer supported) ==========
        showPaymentError("Manual payment methods are currently disabled. Please use GCash, PayMaya, Credit Card, or GrabPay.", "movies.php");
    }
}

// Function to upload proof of payment (kept for reference but not used)
function uploadProofOfPayment($file) {
    $target_dir = dirname(__DIR__, 2) . '/uploads/proofs/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Add .htaccess protection
    $htaccess = $target_dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
    }
    
    if (!is_writable($target_dir)) {
        return ['success' => false, 'error' => 'Upload directory is not writable'];
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024;
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP, and PDF are allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'proof_' . uniqid() . '_' . time() . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        chmod($target_file, 0644);
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file.'];
}

// Get current theme for the main page
$current_theme = $user['theme_preference'] ?? 'dark';

// Regenerate CSRF token for new form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - CinemaTicket</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.3);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(26,26,26,0.9) 0%, rgba(20,20,20,0.95) 100%);
        }
        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.2);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,240,240,0.95) 100%);
        }
        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255,0,255,0.5);
            --border-color: rgba(255,0,255,0.3);
            --card-bg: linear-gradient(135deg, rgba(26,26,58,0.9) 0%, rgba(20,20,50,0.95) 100%);
        }
        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0,255,0,0.5);
            --border-color: rgba(0,255,0,0.3);
            --card-bg: linear-gradient(135deg, rgba(10,26,10,0.9) 0%, rgba(5,20,5,0.95) 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
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
        .payment-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            margin: 20px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .payment-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent), transparent);
            animation: slideBorder 3s infinite;
        }
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            font-family: 'Montserrat', sans-serif;
        }
        .order-summary {
            background: rgba(0,0,0,0.3);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }
        .order-summary p {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent);
            text-align: right;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--accent);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .payment-option {
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .payment-option:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        .payment-option.selected {
            border-color: var(--accent);
            background: rgba(var(--accent), 0.1);
            box-shadow: 0 0 15px var(--accent-glow);
        }
        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .payment-option .option-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        .payment-option .option-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            border: none;
            border-radius: 40px;
            color: var(--bg-primary);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            font-family: 'Montserrat', sans-serif;
        }
        .btn-submit:hover {
            background: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--accent-glow);
        }
        .back-link {
            display: block;
            text-align: center;
            color: var(--accent);
            margin-top: 25px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .info-note {
            font-size: 0.8rem;
            color: #ff8844;
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: rgba(255,136,68,0.1);
            border-radius: 40px;
        }
        hr {
            border-color: var(--border-color);
            margin: 20px 0;
        }
        @media (max-width: 600px) {
            .payment-container { padding: 25px; margin: 15px; }
            h1 { font-size: 1.5rem; }
            .order-summary p { flex-direction: column; align-items: flex-start; }
            .total { text-align: left; }
            .payment-options { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>💳 Complete Payment</h1>
        
        <div class="order-summary">
            <p><strong>🎬 <?php echo htmlspecialchars($item['title']); ?></strong></p>
            <p><?php echo $type == 'cinema' ? '🏛️ Cinema Ticket' : '💻 Online Streaming Ticket'; ?></p>
            <p>🏢 <?php echo htmlspecialchars($cinema_name); ?></p>
            <p>📅 <?php echo htmlspecialchars($show_info); ?></p>
            <?php if ($type == 'cinema' && !empty($selected_seats)): ?>
                <p>💺 Seats: <?php echo htmlspecialchars(implode(', ', $selected_seats)); ?></p>
            <?php endif; ?>
            <p>📦 Quantity: 1</p>
            <p>🎟️ Price per ticket: ₱<?php echo number_format($base_price, 2); ?></p>
            <p>⚙️ Service Fee: ₱<?php echo number_format($fee_per_ticket, 2); ?></p>
            <div class="total">💰 Total: ₱<?php echo number_format($total, 2); ?></div>
        </div>
        
        <form method="POST" action="" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label>Select Payment Method</label>
                <div class="payment-options">
                    <label class="payment-option" data-method="gcash">
                        <input type="radio" name="payment_method" value="gcash" required>
                        <div class="option-icon">💚</div>
                        <div class="option-name">GCash</div>
                    </label>
                    <label class="payment-option" data-method="paymaya">
                        <input type="radio" name="payment_method" value="paymaya">
                        <div class="option-icon">💙</div>
                        <div class="option-name">PayMaya</div>
                    </label>
                    <label class="payment-option" data-method="credit_card">
                        <input type="radio" name="payment_method" value="credit_card">
                        <div class="option-icon">💳</div>
                        <div class="option-name">Credit Card</div>
                    </label>
                    <label class="payment-option" data-method="grab_pay">
                        <input type="radio" name="payment_method" value="grab_pay">
                        <div class="option-icon">🟢</div>
                        <div class="option-name">GrabPay</div>
                    </label>
                </div>
            </div>
            
            <input type="hidden" name="paymongo_method_type" id="paymongo_method_type" value="gcash">
            
            <button type="submit" class="btn-submit">💳 Proceed to Secure Checkout</button>
        </form>
        
        <div class="info-note">
            🔒 <strong>Secure Payment via PayMongo</strong><br>
            Your payment is processed securely. We accept GCash, PayMaya, Credit Card, and GrabPay.
        </div>
        
        <hr>
        
        <a href="movie_detail.php?id=<?php echo $item['movie_id']; ?>" class="back-link">← Back to Movie</a>
    </div>
    
    <script>
        const paymentOptions = document.querySelectorAll('.payment-option');
        const paymongoMethodType = document.getElementById('paymongo_method_type');
        
        paymentOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            option.addEventListener('click', () => {
                radio.checked = true;
                
                // Update selected styling
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                
                // Update hidden field for PayMongo
                const method = radio.value;
                if (method === 'credit_card') {
                    paymongoMethodType.value = 'card';
                } else {
                    paymongoMethodType.value = method;
                }
            });
            
            // Check if pre-selected
            if (radio.checked) {
                option.classList.add('selected');
                const method = radio.value;
                if (method === 'credit_card') {
                    paymongoMethodType.value = 'card';
                } else {
                    paymongoMethodType.value = method;
                }
            }
        });
        
        // Select first option by default
        if (!document.querySelector('input[name="payment_method"]:checked')) {
            const firstOption = document.querySelector('.payment-option');
            if (firstOption) {
                firstOption.click();
            }
        }
    </script>
</body>
</html>