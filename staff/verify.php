<?php
// staff/verify.php - COMPLETELY FIXED
// Added: Proper ticket status update, online ticket detection, better error handling
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

autoArchiveExpiredScreenings();
autoExpireOnlineTickets(); // <-- ADD THIS: Auto-expire online tickets

// Get staff's assigned cinema from staff_cinemas table
$staff_cinema = getStaffCinema($user['id']);
$cinema_id = $staff_cinema ? $staff_cinema['id'] : 0;
$result = null;
$ticket_info = null;
$is_online_ticket = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_code = trim($_POST['ticket_code'] ?? '');
    if (!empty($ticket_code)) {
        // First validate without updating
        $validation = validateTicketByCode($ticket_code, $cinema_id);
        
        if (!$validation['valid']) {
            $result = $validation['reason'];
            $ticket_info = $validation['ticket'] ?? null;
        } else {
            $ticket_info = $validation['ticket'];
            
            // Check if this is an online ticket
            if ($ticket_info['ticket_type'] == 'online') {
                // ONLINE TICKET: Do NOT mark as used, just show info
                $result = 'online_ticket';
                $is_online_ticket = true;
                // No database update for online tickets
            } else {
                // PHYSICAL TICKET: Mark as used for cinema entry
                try {
                    $pdo->beginTransaction();
                    
                    // Update ticket status
                    $stmt = $pdo->prepare("UPDATE tickets SET status = 'used', used_at = NOW(), verified_by = ? WHERE id = ?");
                    $stmt->execute([$user['id'], $ticket_info['id']]);
                    
                    // Update screening available seats if needed (optional, good for analytics)
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
    <title>Verify Ticket - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Your existing styles remain exactly the same */
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
            transition: all 0.3s;
        }
        .logo:hover { text-shadow: var(--red-glow); }
        .logo::before { content: "🎬"; margin-right: 10px; font-size: 1.5rem; filter: drop-shadow(0 0 5px var(--red)); }
        
        .nav-links { display: flex; gap: 25px; align-items: center; }
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
        .nav-links a:hover { color: var(--red); }
        .nav-links a:hover::after { width: 60%; }
        .nav-links a.active { color: var(--red); }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }
        
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
        
        .verify-container { max-width: 600px; margin: 0 auto; }
        
        .verify-form {
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
        
        .verify-form::before {
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
        
        .verify-form h2 { color: var(--red); margin-bottom: 20px; font-size: 1.5rem; }
        
        .input-group { display: flex; gap: 10px; }
        .input-group input {
            flex: 1;
            padding: 16px 20px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(229,9,20,0.2);
            color: var(--text-primary);
            border-radius: 40px;
            font-size: 1.1rem;
            font-family: 'Monaco', monospace;
            transition: all 0.3s;
        }
        .input-group input:focus { border-color: var(--red); outline: none; box-shadow: 0 0 20px rgba(229,9,20,0.2); }
        .input-group button {
            padding: 16px 35px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .input-group button:hover { background: var(--red-dark); transform: translateY(-2px); box-shadow: 0 5px 20px rgba(229,9,20,0.3); }
        
        .manual-entry { margin-top: 20px; text-align: center; }
        .manual-entry a { color: var(--red); text-decoration: none; transition: all 0.3s; position: relative; padding-bottom: 2px; }
        .manual-entry a::after { content: ''; position: absolute; bottom: 0; left: 0; width: 0; height: 2px; background: var(--red); transition: width 0.3s; }
        .manual-entry a:hover::after { width: 100%; }
        
        .result-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid;
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
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
            padding: 25px;
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            text-align: left;
        }
        .detail-row { display: flex; padding: 12px 0; border-bottom: 1px solid rgba(229,9,20,0.1); }
        .detail-label { width: 120px; color: var(--text-secondary); font-size: 0.85rem; }
        .detail-value { flex: 1; color: #fff; font-weight: 500; }
        .detail-value.highlight { color: var(--red); font-weight: 600; }
        
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
        
        .quick-guide {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229,9,20,0.2);
            border-radius: 16px;
            padding: 20px;
        }
        .quick-guide h3 { color: var(--red); margin-bottom: 15px; }
        .guide-item { display: flex; align-items: center; gap: 15px; padding: 10px; border-bottom: 1px solid rgba(229,9,20,0.1); }
        .guide-item:last-child { border-bottom: none; }
        .guide-icon { font-size: 1.5rem; }
        .guide-item strong { color: var(--red); }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            h1 { font-size: 2rem; }
            .input-group { flex-direction: column; }
            .detail-row { flex-direction: column; gap: 5px; }
            .detail-label { width: auto; }
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
                <a href="verify.php" class="active">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="verify-container">
            <h1>Verify Ticket</h1>
            <div class="cinema-strip"></div>
            
            <div class="verify-form">
                <h2>Manual Entry</h2>
                <form method="POST" id="verifyForm">
                    <div class="input-group">
                        <input type="text" name="ticket_code" id="ticket_code" placeholder="Enter ticket code (e.g., TIX-XXXXX-YYYYMMDD)" value="<?php echo htmlspecialchars($_POST['ticket_code'] ?? ''); ?>" autofocus required>
                        <button type="submit">Verify</button>
                    </div>
                </form>
                <div class="manual-entry"><a href="scan.php">📱 Switch to QR Scanner</a></div>
            </div>
            
            <?php if ($result && $ticket_info): ?>
                <?php if ($result == 'online_ticket'): ?>
                    <!-- ONLINE TICKET - Just display info, DO NOT mark as used -->
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
                    <!-- PHYSICAL TICKET - Valid and marked as used -->
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
                            <div class="detail-row">
                                <span class="detail-label">Amount Paid:</span>
                                <span class="detail-value highlight">₱<?php echo number_format($ticket_info['total_price'] ?? 0, 2); ?></span>
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
            
            <div class="quick-guide">
                <h3>Quick Guide</h3>
                <div class="guide-item"><span class="guide-icon">✅</span><span><strong>Valid (Green)</strong> - Physical ticket, allow entry</span></div>
                <div class="guide-item"><span class="guide-icon">💻</span><span><strong>Online (Cyan)</strong> - Streaming ticket, inform customer</span></div>
                <div class="guide-item"><span class="guide-icon">⚠️</span><span><strong>Used/Expired (Yellow)</strong> - Deny entry</span></div>
                <div class="guide-item"><span class="guide-icon">❌</span><span><strong>Invalid/Not Paid (Red)</strong> - Deny entry</span></div>
            </div>
        </div>
    </main>
    
    <script>
        document.getElementById('ticket_code').addEventListener('paste', function(e) { 
            setTimeout(() => { 
                document.getElementById('verifyForm').submit(); 
            }, 100); 
        });
        document.addEventListener('keydown', function(e) { 
            if (e.ctrlKey && e.key === 'f') { 
                e.preventDefault(); 
                document.getElementById('ticket_code').focus(); 
            } 
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>