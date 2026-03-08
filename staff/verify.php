<?php
// staff/verify.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

$result = null;
$ticket_info = null;

// Get staff's cinema (if assigned)
$cinema_id = $user['cinema_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_code = trim($_POST['ticket_code'] ?? '');
    $action = $_POST['action'] ?? 'verify';
    
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
    <title>Verify Ticket - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .verify-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .verify-form {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .verify-form h2 {
            color: #00ffff;
            margin-bottom: 20px;
        }
        .input-group {
            display: flex;
            gap: 10px;
        }
        .input-group input {
            flex: 1;
            padding: 15px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 4px;
            font-size: 1.1rem;
            font-family: monospace;
        }
        .input-group input:focus {
            border-color: #00ffff;
            outline: none;
        }
        .input-group button {
            padding: 15px 30px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        .input-group button:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .result-card {
            background: #1a1a1a;
            border: 2px solid;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
        }
        .result-valid {
            border-color: #44ff44;
        }
        .result-invalid {
            border-color: #ff4444;
        }
        .result-warning {
            border-color: #ffff44;
        }
        .result-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .result-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
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
        .detail-label {
            width: 120px;
            color: #888;
        }
        .detail-value {
            flex: 1;
            color: #fff;
        }
        .detail-value.highlight {
            color: #00ffff;
            font-weight: bold;
        }
        .quick-guide {
            margin-top: 30px;
            padding: 20px;
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 8px;
        }
        .guide-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        .guide-item:last-child {
            border-bottom: none;
        }
        .guide-icon {
            font-size: 1.5rem;
        }
        .manual-entry {
            margin-top: 20px;
            text-align: center;
        }
        .manual-entry a {
            color: #00ffff;
            text-decoration: none;
        }
        .manual-entry a:hover {
            text-decoration: underline;
        }
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
            
            <div class="verify-form">
                <h2>Manual Entry</h2>
                <form method="POST" id="verifyForm">
                    <div class="input-group">
                        <input type="text" name="ticket_code" id="ticket_code" 
                               placeholder="Enter ticket code (e.g., TIX123456789)" 
                               value="<?php echo htmlspecialchars($_POST['ticket_code'] ?? ''); ?>"
                               autofocus required>
                        <button type="submit">Verify</button>
                    </div>
                </form>
                
                <div class="manual-entry">
                    <a href="scan.php">📱 Switch to QR Scanner</a>
                </div>
            </div>
            
            <?php if ($result && $ticket_info): ?>
                <div class="result-card result-<?php 
                    echo $result == 'valid' ? 'valid' : ($result == 'used' || $result == 'expired' ? 'warning' : 'invalid'); 
                ?>">
                    <?php if ($result == 'valid'): ?>
                        <div class="result-icon">✅</div>
                        <div class="result-title" style="color: #44ff44;">VALID TICKET</div>
                        <p>Welcome to the cinema! Ticket has been marked as used.</p>
                        
                        <div class="ticket-details">
                            <h3 style="color: #00ffff; margin-bottom: 15px;">Ticket Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Ticket Code:</span>
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
                            <div class="detail-row">
                                <span class="detail-label">Ticket Type:</span>
                                <span class="detail-value"><?php echo ucfirst($ticket_info['ticket_type']); ?></span>
                            </div>
                        </div>
                        
                    <?php elseif ($result == 'used'): ?>
                        <div class="result-icon">⚠️</div>
                        <div class="result-title" style="color: #ffff44;">TICKET ALREADY USED</div>
                        <p>This ticket was used on <?php echo date('M d, Y h:i A', strtotime($ticket_info['used_at'])); ?></p>
                        
                    <?php elseif ($result == 'expired'): ?>
                        <div class="result-icon">⌛</div>
                        <div class="result-title" style="color: #ffff44;">TICKET EXPIRED</div>
                        <p>This ticket expired on <?php echo date('M d, Y h:i A', strtotime($ticket_info['expiry_date'])); ?></p>
                        
                    <?php elseif ($result == 'not_paid'): ?>
                        <div class="result-icon">💰</div>
                        <div class="result-title" style="color: #ff4444;">PAYMENT PENDING</div>
                        <p>This ticket has not been paid for yet.</p>
                        
                    <?php elseif ($result == 'wrong_cinema'): ?>
                        <div class="result-icon">🏛️</div>
                        <div class="result-title" style="color: #ff4444;">WRONG CINEMA</div>
                        <p>This ticket is for a different cinema location.</p>
                        <p>Ticket cinema: <?php echo htmlspecialchars($ticket_info['cinema_name']); ?></p>
                        
                    <?php endif; ?>
                </div>
                
            <?php elseif ($result == 'invalid'): ?>
                <div class="result-card result-invalid">
                    <div class="result-icon">❌</div>
                    <div class="result-title" style="color: #ff4444;">INVALID TICKET</div>
                    <p>The ticket code you entered was not found in our system.</p>
                </div>
            <?php endif; ?>
            
            <div class="quick-guide">
                <h3 style="color: #00ffff; margin-bottom: 15px;">Quick Guide</h3>
                <div class="guide-item">
                    <span class="guide-icon">✅</span>
                    <span><strong>Valid</strong> - Green screen, allow entry</span>
                </div>
                <div class="guide-item">
                    <span class="guide-icon">⚠️</span>
                    <span><strong>Used/Expired</strong> - Yellow screen, deny entry</span>
                </div>
                <div class="guide-item">
                    <span class="guide-icon">❌</span>
                    <span><strong>Invalid/Not Paid</strong> - Red screen, deny entry</span>
                </div>
                <div class="guide-item">
                    <span class="guide-icon">🏛️</span>
                    <span><strong>Wrong Cinema</strong> - Ticket is for different location</span>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Auto-submit on paste (for barcode scanner)
        document.getElementById('ticket_code').addEventListener('paste', function(e) {
            setTimeout(() => {
                document.getElementById('verifyForm').submit();
            }, 100);
        });
        
        // Keyboard shortcut (Ctrl+F to focus)
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