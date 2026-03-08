<?php
// staff/scan.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;

$result = null;
$ticket_info = null;

// Handle form submission (manual fallback)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_code'])) {
    $ticket_code = trim($_POST['ticket_code'] ?? '');
    
    if (!empty($ticket_code)) {
        // Get ticket details
        $stmt = $pdo->prepare("
            SELECT t.*, u.first_name, u.last_name, u.email,
                   s.show_date, s.show_time, s.screen_number,
                   m.title, m.duration,
                   c.name as cinema_name, c.id as cinema_id
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN screenings s ON t.screening_id = s.id
            LEFT JOIN movies m ON s.movie_id = m.id
            LEFT JOIN cinemas c ON s.cinema_id = c.id
            WHERE t.ticket_code = ?
        ");
        $stmt->execute([$ticket_code]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            $result = 'invalid';
        } else {
            // Check if ticket belongs to staff's cinema (if assigned)
            if ($cinema_id && $ticket['cinema_id'] != $cinema_id) {
                $result = 'wrong_cinema';
                $ticket_info = $ticket;
            }
            // Check if expired
            elseif ($ticket['expiry_date'] && strtotime($ticket['expiry_date']) < time()) {
                $result = 'expired';
                $ticket_info = $ticket;
            }
            // Check if already used
            elseif ($ticket['status'] == 'used') {
                $result = 'used';
                $ticket_info = $ticket;
            }
            // Check if paid
            elseif ($ticket['status'] != 'paid') {
                $result = 'not_paid';
                $ticket_info = $ticket;
            }
            // Valid ticket - mark as used
            else {
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'used', used_at = NOW(), verified_by = ? WHERE id = ?");
                $stmt->execute([$user['id'], $ticket['id']]);
                $result = 'valid';
                $ticket_info = $ticket;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .scanner-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .scanner-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        #scanner {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid #00ffff;
            border-radius: 8px;
            overflow: hidden;
        }
        #scanner video {
            width: 100%;
            height: auto;
            display: block;
        }
        .scanner-controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        .scanner-btn {
            padding: 10px 20px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .scanner-btn:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .scanner-btn.danger {
            background: #ff4444;
            color: #fff;
        }
        .manual-input {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #333;
        }
        .input-group {
            display: flex;
            gap: 10px;
        }
        .input-group input {
            flex: 1;
            padding: 12px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 4px;
        }
        .input-group input:focus {
            border-color: #00ffff;
            outline: none;
        }
        .input-group button {
            padding: 12px 20px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .result-card {
            background: #1a1a1a;
            border: 2px solid;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
        }
        .result-valid { border-color: #44ff44; }
        .result-invalid { border-color: #ff4444; }
        .result-warning { border-color: #ffff44; }
        .result-icon { font-size: 4rem; margin-bottom: 20px; }
        .result-title { font-size: 2rem; font-weight: bold; margin-bottom: 20px; }
        .ticket-details {
            margin-top: 30px;
            padding: 20px;
            background: #000;
            border-radius: 8px;
            text-align: left;
        }
        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #333;
        }
        .detail-label { width: 120px; color: #888; }
        .detail-value { flex: 1; color: #fff; }
        .detail-value.highlight { color: #00ffff; font-weight: bold; }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-valid { background: rgba(68,255,68,0.2); color: #44ff44; border: 1px solid #44ff44; }
        .status-used { background: rgba(255,255,68,0.2); color: #ffff44; border: 1px solid #ffff44; }
        .status-expired { background: rgba(255,68,68,0.2); color: #ff4444; border: 1px solid #ff4444; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php" class="active">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="scanner-container">
            <h1>QR Code Scanner</h1>
            
            <div class="scanner-card">
                <h2 style="color:#00ffff; margin-bottom:20px;">Scan Ticket QR Code</h2>
                
                <div id="scanner"></div>
                
                <div class="scanner-controls">
                    <button class="scanner-btn" onclick="startScanner()">▶️ Start Scanner</button>
                    <button class="scanner-btn danger" onclick="stopScanner()">⏹️ Stop Scanner</button>
                </div>
                
                <div class="manual-input">
                    <h3 style="color:#00ffff; margin-bottom:15px;">Manual Entry</h3>
                    <form method="POST" id="manualForm">
                        <div class="input-group">
                            <input type="text" name="ticket_code" placeholder="Enter ticket code manually" required>
                            <button type="submit">Verify</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($result && $ticket_info): ?>
                <div class="result-card result-<?php 
                    echo $result == 'valid' ? 'valid' : ($result == 'used' || $result == 'expired' ? 'warning' : 'invalid'); 
                ?>">
                    <?php if ($result == 'valid'): ?>
                        <div class="result-icon">✅</div>
                        <div class="result-title" style="color:#44ff44;">VALID TICKET</div>
                        <p>Welcome! Ticket has been verified.</p>
                        
                        <div class="ticket-details">
                            <h3 style="color:#00ffff; margin-bottom:15px;">Ticket Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Code:</span>
                                <span class="detail-value highlight"><?php echo $ticket_info['ticket_code']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Customer:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['first_name'] . ' ' . $ticket_info['last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Movie:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['title']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Cinema:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['cinema_name']); ?> (Screen <?php echo $ticket_info['screen_number']; ?>)</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date/Time:</span>
                                <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($ticket_info['show_date'] . ' ' . $ticket_info['show_time'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Seats:</span>
                                <span class="detail-value"><?php echo $ticket_info['seat_numbers'] ?: 'N/A'; ?></span>
                            </div>
                        </div>
                        
                    <?php elseif ($result == 'used'): ?>
                        <div class="result-icon">⚠️</div>
                        <div class="result-title" style="color:#ffff44;">TICKET ALREADY USED</div>
                        <p>Used on: <?php echo date('M d, Y h:i A', strtotime($ticket_info['used_at'])); ?></p>
                        
                    <?php elseif ($result == 'expired'): ?>
                        <div class="result-icon">⌛</div>
                        <div class="result-title" style="color:#ffff44;">TICKET EXPIRED</div>
                        <p>Expired on: <?php echo date('M d, Y h:i A', strtotime($ticket_info['expiry_date'])); ?></p>
                        
                    <?php elseif ($result == 'not_paid'): ?>
                        <div class="result-icon">💰</div>
                        <div class="result-title" style="color:#ff4444;">PAYMENT PENDING</div>
                        <p>This ticket has not been paid for.</p>
                        
                    <?php elseif ($result == 'wrong_cinema'): ?>
                        <div class="result-icon">🏛️</div>
                        <div class="result-title" style="color:#ff4444;">WRONG CINEMA</div>
                        <p>This ticket is for: <?php echo htmlspecialchars($ticket_info['cinema_name']); ?></p>
                        
                    <?php endif; ?>
                </div>
                
            <?php elseif ($result == 'invalid'): ?>
                <div class="result-card result-invalid">
                    <div class="result-icon">❌</div>
                    <div class="result-title" style="color:#ff4444;">INVALID TICKET</div>
                    <p>Ticket code not found in system.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
    <script>
        let html5QrcodeScanner = null;
        
        function startScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            
            html5QrcodeScanner = new Html5QrcodeScanner(
                "scanner", 
                { fps: 10, qrbox: 250 },
                /* verbose= */ false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanError);
        }
        
        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
                document.getElementById('scanner').innerHTML = '';
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            // Handle the scanned code
            console.log('Scanned:', decodedText);
            
            // Stop scanner
            stopScanner();
            
            // Submit the code
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="ticket_code" value="${decodedText}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function onScanError(errorMessage) {
            console.log('Scan error:', errorMessage);
            // Ignore errors, keep scanning
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
        });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>