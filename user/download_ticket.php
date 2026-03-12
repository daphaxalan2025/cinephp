<?php
// user/download_ticket.php
require_once '../includes/functions.php';
requireLogin();

$ticket_code = $_GET['code'] ?? '';
$pdo = getDB();
$user = getCurrentUser();

// Get ticket details
$stmt = $pdo->prepare("
    SELECT t.*, 
           m.title, m.duration, m.rating,
           s.show_date, s.show_time, s.screen_number,
           c.name as cinema_name, c.location,
           os.show_date as online_date, os.show_time as online_time,
           u.first_name, u.last_name, u.email
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN screenings s ON t.screening_id = s.id
    LEFT JOIN movies m ON (s.movie_id = m.id OR os.movie_id = m.id)
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    LEFT JOIN online_schedule os ON t.online_schedule_id = os.id
    WHERE t.ticket_code = ? AND (t.user_id = ? OR t.user_id IN (SELECT id FROM users WHERE parent_id = ?))
");
$stmt->execute([$ticket_code, $user['id'], $user['id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('Ticket not found', 'error');
    header('Location: purchases.php');
    exit;
}

// In a real application, you would generate a PDF here
// For now, we'll create a simple HTML ticket that can be printed

// Set headers for download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="ticket_' . $ticket_code . '.html"');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ticket <?php echo $ticket_code; ?> - CinemaTicket</title>
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
            --card-gradient: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(20, 20, 20, 0.98) 100%);
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(229, 9, 20, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(229, 9, 20, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        .ticket {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .ticket::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--red), var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .ticket::after {
            content: 'CINEMA TICKET';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 5rem;
            font-weight: 900;
            opacity: 0.03;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid rgba(229, 9, 20, 0.2);
            padding-bottom: 25px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        
        .header h1 {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-family: 'Montserrat', sans-serif;
        }
        
        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-top: 10px;
            letter-spacing: 2px;
        }
        
        .qr-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .qr-code {
            background: white;
            padding: 15px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
        }
        
        .qr-code img {
            width: 150px;
            height: 150px;
            display: block;
        }
        
        .ticket-code {
            text-align: center;
        }
        
        .ticket-code-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        
        .ticket-code-value {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 2rem;
            font-weight: 700;
            color: var(--red);
            letter-spacing: 3px;
            background: rgba(229, 9, 20, 0.1);
            padding: 10px 20px;
            border-radius: 40px;
            border: 1px solid rgba(229, 9, 20, 0.3);
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
            position: relative;
            z-index: 1;
        }
        
        .detail-group {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-row {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }
        
        .detail-value {
            color: var(--red);
            font-size: 1.2rem;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
        }
        
        .detail-value.large {
            font-size: 1.5rem;
        }
        
        .seats-section {
            text-align: center;
            margin: 30px 0;
            padding: 25px;
            background: rgba(229, 9, 20, 0.05);
            border: 2px dashed rgba(229, 9, 20, 0.3);
            border-radius: 24px;
            position: relative;
            z-index: 1;
        }
        
        .seats-label {
            color: var(--text-secondary);
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        
        .seats-numbers {
            color: var(--red);
            font-size: 2.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
        }
        
        .streaming-info {
            margin: 30px 0;
            padding: 25px;
            background: rgba(68, 255, 68, 0.05);
            border: 2px dashed rgba(68, 255, 68, 0.3);
            border-radius: 24px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .streaming-info .label {
            color: #44ff44;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        
        .streaming-info .value {
            color: #44ff44;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .validity {
            color: var(--text-secondary);
            margin-top: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid rgba(229, 9, 20, 0.2);
            color: var(--text-secondary);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        
        .footer p {
            margin-bottom: 5px;
        }
        
        .footer .copyright {
            margin-top: 15px;
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.8rem;
        }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            body::before {
                display: none;
            }
            
            .ticket {
                background: white;
                border: 2px solid #000;
                box-shadow: none;
            }
            
            .ticket::before,
            .ticket::after {
                display: none;
            }
            
            .header h1 {
                background: none;
                -webkit-text-fill-color: #000;
                color: #000;
            }
            
            .detail-value {
                color: #000;
            }
            
            .seats-numbers {
                color: #000;
                text-shadow: none;
            }
            
            .qr-code {
                background: #fff;
                border: 1px solid #000;
            }
        }
        
        @media (max-width: 768px) {
            .ticket {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .qr-section {
                flex-direction: column;
                gap: 15px;
            }
            
            .ticket-code-value {
                font-size: 1.5rem;
            }
            
            .seats-numbers {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>CINEMA TICKET</h1>
            <p>Official Movie Ticket</p>
        </div>
        
        <div class="qr-section">
            <div class="qr-code">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($ticket_code); ?>" alt="QR Code">
            </div>
            
            <div class="ticket-code">
                <div class="ticket-code-label">Ticket Code</div>
                <div class="ticket-code-value"><?php echo $ticket_code; ?></div>
            </div>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <div class="details-grid">
            <div class="detail-group">
                <div class="detail-row">
                    <div class="detail-label">Movie</div>
                    <div class="detail-value large"><?php echo htmlspecialchars($ticket['title']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Customer</div>
                    <div class="detail-value"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($ticket['email']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Ticket Type</div>
                    <div class="detail-value"><?php echo ucfirst($ticket['ticket_type']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Quantity</div>
                    <div class="detail-value"><?php echo $ticket['quantity']; ?> ticket(s)</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Total Paid</div>
                    <div class="detail-value large">$<?php echo number_format($ticket['total_price'], 2); ?></div>
                </div>
            </div>
            
            <div class="detail-group">
                <?php if ($ticket['ticket_type'] == 'cinema'): ?>
                    <div class="detail-row">
                        <div class="detail-label">Cinema</div>
                        <div class="detail-value"><?php echo htmlspecialchars($ticket['cinema_name']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Location</div>
                        <div class="detail-value"><?php echo htmlspecialchars($ticket['location']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Screen</div>
                        <div class="detail-value">Screen <?php echo $ticket['screen_number']; ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($ticket['show_date'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Time</div>
                        <div class="detail-value"><?php echo date('h:i A', strtotime($ticket['show_time'])); ?></div>
                    </div>
                <?php else: ?>
                    <div class="detail-row">
                        <div class="detail-label">Streaming Date</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($ticket['online_date'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Streaming Time</div>
                        <div class="detail-value"><?php echo date('h:i A', strtotime($ticket['online_time'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo $ticket['duration']; ?> minutes</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Rating</div>
                        <div class="detail-value"><?php echo $ticket['rating']; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($ticket['seat_numbers']): ?>
            <div class="seats-section">
                <div class="seats-label">Your Reserved Seats</div>
                <div class="seats-numbers"><?php echo $ticket['seat_numbers']; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['ticket_type'] == 'online'): ?>
            <div class="streaming-info">
                <div class="label">Streaming Details</div>
                <div class="value">Views: <?php echo $ticket['streaming_views']; ?>/<?php echo $ticket['max_streaming_views']; ?></div>
                <div class="validity">Valid until <?php echo date('F d, Y', strtotime('+30 days', strtotime($ticket['purchase_date']))); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <div class="footer">
            <p>🎬 Present this ticket (printed or on mobile) at the entrance</p>
            <p>💻 For online streaming, visit our website and use your ticket code</p>
            <p class="copyright">© <?php echo date('Y'); ?> CinemaTicket. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php exit; ?>