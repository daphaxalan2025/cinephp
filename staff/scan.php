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
        
        .scanner-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Scanner Card */
        .scanner-card {
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
        
        .scanner-card::before {
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
        
        .scanner-card h2 {
            color: var(--red);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        #scanner {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 16px;
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
            padding: 12px 25px;
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .scanner-btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .scanner-btn.danger:hover {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .manual-input {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .manual-input h3 {
            color: var(--red);
            margin-bottom: 15px;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
        }
        
        .input-group input {
            flex: 1;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .input-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .input-group button {
            padding: 14px 25px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .input-group button:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        /* Result Card */
        .result-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid;
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .result-valid { border-color: #44ff44; }
        .result-invalid { border-color: #ff4444; }
        .result-warning { border-color: #ffff44; }
        
        .result-icon { font-size: 4rem; margin-bottom: 20px; }
        .result-title { font-size: 2rem; font-weight: bold; margin-bottom: 20px; }
        
        .ticket-details {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-label { 
            width: 120px; 
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .detail-value { 
            flex: 1; 
            color: #fff;
            font-weight: 500;
        }
        
        .detail-value.highlight { 
            color: var(--red); 
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-valid { 
            background: rgba(68,255,68,0.15); 
            color: #44ff44; 
            border: 1px solid #44ff44; 
        }
        
        .status-used { 
            background: rgba(255,255,68,0.15); 
            color: #ffff44; 
            border: 1px solid #ffff44; 
        }
        
        .status-expired { 
            background: rgba(255,68,68,0.15); 
            color: #ff4444; 
            border: 1px solid #ff4444; 
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
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
            
            .input-group {
                flex-direction: column;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label {
                width: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
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
            <h1>QR Scanner</h1>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
            
            <div class="scanner-card">
                <h2>Scan Ticket QR Code</h2>
                
                <div id="scanner"></div>
                
                <div class="scanner-controls">
                    <button class="scanner-btn" onclick="startScanner()">▶️ Start Scanner</button>
                    <button class="scanner-btn danger" onclick="stopScanner()">⏹️ Stop Scanner</button>
                </div>
                
                <div class="manual-input">
                    <h3>Manual Entry</h3>
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
                        <p style="color: var(--text-secondary);">Welcome! Ticket has been verified.</p>
                        
                        <div class="ticket-details">
                            <h3 style="color: var(--red); margin-bottom:15px;">Ticket Details</h3>
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
                        <p style="color: var(--text-secondary);">Used on: <?php echo date('M d, Y h:i A', strtotime($ticket_info['used_at'])); ?></p>
                        
                    <?php elseif ($result == 'expired'): ?>
                        <div class="result-icon">⌛</div>
                        <div class="result-title" style="color:#ffff44;">TICKET EXPIRED</div>
                        <p style="color: var(--text-secondary);">Expired on: <?php echo date('M d, Y h:i A', strtotime($ticket_info['expiry_date'])); ?></p>
                        
                    <?php elseif ($result == 'not_paid'): ?>
                        <div class="result-icon">💰</div>
                        <div class="result-title" style="color:#ff4444;">PAYMENT PENDING</div>
                        <p style="color: var(--text-secondary);">This ticket has not been paid for.</p>
                        
                    <?php elseif ($result == 'wrong_cinema'): ?>
                        <div class="result-icon">🏛️</div>
                        <div class="result-title" style="color:#ff4444;">WRONG CINEMA</div>
                        <p style="color: var(--text-secondary);">This ticket is for: <?php echo htmlspecialchars($ticket_info['cinema_name']); ?></p>
                        
                    <?php endif; ?>
                </div>
                
            <?php elseif ($result == 'invalid'): ?>
                <div class="result-card result-invalid">
                    <div class="result-icon">❌</div>
                    <div class="result-title" style="color:#ff4444;">INVALID TICKET</div>
                    <p style="color: var(--text-secondary);">Ticket code not found in system.</p>
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
            console.log('Scanned:', decodedText);
            stopScanner();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="ticket_code" value="${decodedText}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function onScanError(errorMessage) {
            console.log('Scan error:', errorMessage);
        }
        
        window.addEventListener('beforeunload', function() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
        });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>