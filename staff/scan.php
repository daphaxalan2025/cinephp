<?php
// staff/scan.php - PROFESSIONAL DESIGN MATCHING CINEMAS PAGE
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

autoArchiveExpiredScreenings();
autoExpireOnlineTickets();

// Get staff's assigned cinema from staff_cinemas table
$staff_cinema = getStaffCinema($user['id']);
$cinema_id = $staff_cinema ? $staff_cinema['id'] : 0;
$result = null;
$ticket_info = null;
$is_online_ticket = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_code'])) {
    $ticket_code = trim($_POST['ticket_code'] ?? '');
    if (!empty($ticket_code)) {
        $validation = validateTicketByCode($ticket_code, $cinema_id);
        
        if (!$validation['valid']) {
            $result = $validation['reason'];
            $ticket_info = $validation['ticket'] ?? null;
        } else {
            $ticket_info = $validation['ticket'];
            
            if ($ticket_info['ticket_type'] == 'online') {
                $result = 'online_ticket';
                $is_online_ticket = true;
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'used', used_at = NOW(), verified_by = ? WHERE id = ?");
                    $stmt->execute([$user['id'], $ticket_info['id']]);
                    
                    if ($ticket_info['screening_id']) {
                        $stmt2 = $pdo->prepare("UPDATE screenings SET available_seats = available_seats - ? WHERE id = ?");
                        $stmt2->execute([$ticket_info['quantity'], $ticket_info['screening_id']]);
                    }
                    
                    $pdo->commit();
                    $result = 'valid';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $result = 'error';
                    setFlash('Error updating ticket: ' . $e->getMessage(), 'error');
                }
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
        
        /* NAVBAR - MATCHING CINEMAS PAGE */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
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
            color: var(--red);
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
            text-shadow: var(--red-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 8px;
            font-size: 1.2rem;
            filter: drop-shadow(0 0 5px var(--red));
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
        
        /* MAIN CONTAINER - MATCHING CINEMAS PAGE */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* HEADER SECTION - MATCHING CINEMAS PAGE */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
        }
        
        /* CINEMA STRIP - MATCHING CINEMAS PAGE */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 30px 0;
            opacity: 0.5;
        }
        
        .scanner-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .scanner-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
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
        
        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            min-height: 300px;
            border: 2px solid rgba(229,9,20,0.3);
            border-radius: 16px;
            overflow: hidden;
            background: #111;
        }
        
        #reader video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .scanner-loading {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .scanner-controls {
            text-align: center;
            margin: 20px 0;
        }
        
        .start-camera-btn {
            background: var(--red);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .start-camera-btn:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229,9,20,0.3);
        }
        
        .manual-input {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid rgba(229,9,20,0.2);
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
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(229,9,20,0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .input-group input:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229,9,20,0.2);
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
            box-shadow: 0 5px 20px rgba(229,9,20,0.3);
        }
        
        .result-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 2px solid;
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .result-valid { border-color: #44ff44; }
        .result-online { border-color: #00ffff; }
        .result-invalid { border-color: #ff4444; }
        .result-warning { border-color: #ffff44; }
        
        .result-icon { font-size: 4rem; margin-bottom: 20px; }
        .result-title { font-size: 2rem; font-weight: bold; margin-bottom: 20px; }
        
        .ticket-details {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid rgba(229,9,20,0.1);
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
        
        .online-notice {
            background: rgba(0, 255, 255, 0.1);
            border: 1px solid #00ffff;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            color: #00ffff;
            font-weight: 600;
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
            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
        <div class="header-section">
            <h1>QR Scanner</h1>
        </div>
        
        <div class="cinema-strip"></div>
        
        <div class="scanner-container">
            <div class="scanner-card">
                <h2>Scan Ticket QR Code</h2>
                <div id="reader">
                    <div class="scanner-loading">📷 Click "Start Camera" to begin scanning</div>
                </div>
                
                <div class="scanner-controls">
                    <button class="start-camera-btn" onclick="startScanner()">▶️ Start Camera</button>
                </div>
                
                <div class="manual-input">
                    <h3>Manual Entry (Fallback)</h3>
                    <form method="POST" id="manualForm">
                        <div class="input-group">
                            <input type="text" name="ticket_code" id="ticket_code" placeholder="Enter ticket code manually" required>
                            <button type="submit">Verify Ticket</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($result && $ticket_info): ?>
                <?php if ($result == 'online_ticket'): ?>
                    <div class="result-card result-online">
                        <div class="result-icon">💻</div>
                        <div class="result-title" style="color:#00ffff;">ONLINE STREAMING TICKET</div>
                        <p style="color: var(--text-secondary);">This ticket is for online streaming only. No physical entry verification needed.</p>
                        <div class="ticket-details">
                            <h3 style="color: #00ffff; margin-bottom:15px;">Ticket Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Ticket Code:</span>
                                <span class="detail-value highlight"><?php echo htmlspecialchars($ticket_info['ticket_code'] ?? ''); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Customer:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(($ticket_info['first_name'] ?? '') . ' ' . ($ticket_info['last_name'] ?? '')); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Movie:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['title'] ?? ''); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Streaming Date:</span>
                                <span class="detail-value"><?php echo isset($ticket_info['show_date']) ? date('F d, Y', strtotime($ticket_info['show_date'])) : 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Streaming Time:</span>
                                <span class="detail-value"><?php echo isset($ticket_info['show_time']) ? date('h:i A', strtotime($ticket_info['show_time'])) : 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Expires:</span>
                                <span class="detail-value"><?php echo isset($ticket_info['week_expiry']) ? date('F d, Y', strtotime($ticket_info['week_expiry'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                        <div class="online-notice">
                            💻 This ticket is for online streaming only.<br>
                            Customer can watch from home via our website.<br>
                            <strong>No action needed from staff.</strong>
                        </div>
                    </div>
                    
                <?php elseif ($result == 'valid'): ?>
                    <div class="result-card result-valid">
                        <div class="result-icon">✅</div>
                        <div class="result-title" style="color:#44ff44;">VALID TICKET - ENTRY GRANTED</div>
                        <p style="color: var(--text-secondary);">Ticket has been verified and marked as used.</p>
                        <div class="ticket-details">
                            <h3 style="color: var(--red); margin-bottom:15px;">Ticket Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Ticket Code:</span>
                                <span class="detail-value highlight"><?php echo htmlspecialchars($ticket_info['ticket_code'] ?? ''); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Customer:</span>
                                <span class="detail-value"><?php echo htmlspecialchars(($ticket_info['first_name'] ?? '') . ' ' . ($ticket_info['last_name'] ?? '')); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Movie:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['title'] ?? ''); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Cinema:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['cinema_name'] ?? ''); ?> (Screen <?php echo htmlspecialchars($ticket_info['screen_number'] ?? ''); ?>)</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Date/Time:</span>
                                <span class="detail-value"><?php echo isset($ticket_info['show_date']) && isset($ticket_info['show_time']) ? date('M d, Y h:i A', strtotime($ticket_info['show_date'] . ' ' . $ticket_info['show_time'])) : 'N/A'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Seats:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($ticket_info['seat_numbers'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($result == 'used'): ?>
                    <div class="result-card result-warning">
                        <div class="result-icon">⚠️</div>
                        <div class="result-title" style="color:#ffff44;">TICKET ALREADY USED</div>
                        <p>This ticket was already scanned on: <?php echo isset($ticket_info['used_at']) ? date('M d, Y h:i A', strtotime($ticket_info['used_at'])) : 'Unknown date'; ?></p>
                    </div>
                <?php elseif ($result == 'expired'): ?>
                    <div class="result-card result-warning">
                        <div class="result-icon">⌛</div>
                        <div class="result-title" style="color:#ffff44;">TICKET EXPIRED</div>
                        <p>This ticket has expired and is no longer valid.</p>
                    </div>
                <?php elseif ($result == 'not_paid'): ?>
                    <div class="result-card result-invalid">
                        <div class="result-icon">💰</div>
                        <div class="result-title" style="color:#ff4444;">PAYMENT PENDING</div>
                        <p>This ticket has not been paid for yet.</p>
                    </div>
                <?php elseif ($result == 'wrong_cinema'): ?>
                    <div class="result-card result-invalid">
                        <div class="result-icon">🏛️</div>
                        <div class="result-title" style="color:#ff4444;">WRONG CINEMA</div>
                        <p>This ticket is for a different cinema location.</p>
                    </div>
                <?php elseif ($result == 'error'): ?>
                    <div class="result-card result-invalid">
                        <div class="result-icon">❌</div>
                        <div class="result-title" style="color:#ff4444;">SYSTEM ERROR</div>
                        <p>An error occurred while processing this ticket. Please try again or contact support.</p>
                    </div>
                <?php endif; ?>
            <?php elseif ($result == 'not_found'): ?>
                <div class="result-card result-invalid">
                    <div class="result-icon">❌</div>
                    <div class="result-title" style="color:#ff4444;">INVALID TICKET</div>
                    <p>Ticket code not found in system.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        let html5QrCodeScanner = null;
        
        function startScanner() {
            const readerElement = document.getElementById('reader');
            if (!readerElement) {
                console.error("Reader element not found");
                return;
            }
            
            if (typeof Html5Qrcode === 'undefined') {
                console.error("Html5Qrcode library not loaded.");
                document.getElementById('reader').innerHTML = '<div class="scanner-loading" style="color: #ff4444;">❌ QR Library failed to load. Please refresh the page.</div>';
                return;
            }
            
            if (html5QrCodeScanner) {
                html5QrCodeScanner.clear().catch(e => console.log(e));
            }
            
            readerElement.innerHTML = '';
            html5QrCodeScanner = new Html5Qrcode("reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCodeScanner.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanError
            ).then(() => {
                console.log("Scanner started successfully");
                document.getElementById('reader').style.minHeight = 'auto';
            }).catch(err => {
                console.error("Camera error:", err);
                html5QrCodeScanner.start(
                    { facingMode: "user" },
                    config,
                    onScanSuccess,
                    onScanError
                ).catch(err2 => {
                    console.error("All cameras failed:", err2);
                    document.getElementById('reader').innerHTML = '<div class="scanner-loading" style="color: #ff4444;">❌ Could not access camera.<br><br>Use manual entry below.</div>';
                });
            });
        }
        
        function stopScanner() {
            if (html5QrCodeScanner) {
                html5QrCodeScanner.stop().then(() => {
                    html5QrCodeScanner.clear();
                    html5QrCodeScanner = null;
                }).catch(err => console.log("Stop error:", err));
            }
        }
        
        function onScanSuccess(decodedText, decodedResult) {
            console.log("Scanned QR Code:", decodedText);
            stopScanner();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ticket_code';
            input.value = decodedText;
            form.appendChild(input);
            document.body.appendChild(form);
            
            const btn = document.querySelector('.start-camera-btn');
            if (btn) {
                btn.textContent = '⏳ Processing...';
                btn.disabled = true;
            }
            
            form.submit();
        }
        
        function onScanError(errorMessage) {
            // Silent fail
        }
        
        window.startScanner = startScanner;
        
        window.addEventListener('beforeunload', function() {
            if (html5QrCodeScanner) {
                html5QrCodeScanner.stop().catch(e => console.log(e));
            }
        });
        
        const ticketCodeInput = document.getElementById('ticket_code');
        if (ticketCodeInput) {
            ticketCodeInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    document.getElementById('manualForm').submit();
                }, 100);
            });
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>