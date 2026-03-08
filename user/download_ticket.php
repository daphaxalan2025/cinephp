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
    <title>Ticket <?php echo $ticket_code; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #000;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        .ticket {
            max-width: 800px;
            margin: 0 auto;
            background: #1a1a1a;
            border: 3px solid #00ffff;
            border-radius: 10px;
            padding: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #00ffff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #00ffff;
            margin: 0;
            font-size: 2rem;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            width: 150px;
            height: 150px;
        }
        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            color: #888;
            font-size: 0.9rem;
        }
        .detail-value {
            color: #00ffff;
            font-size: 1.1rem;
            font-weight: bold;
        }
        .seats {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 5px;
        }
        .seats .label {
            color: #888;
            margin-bottom: 5px;
        }
        .seats .numbers {
            color: #00ffff;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #333;
            color: #888;
            font-size: 0.9rem;
        }
        .barcode {
            text-align: center;
            margin: 20px 0;
            font-family: monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        @media print {
            body { background: #fff; }
            .ticket { border: 2px solid #000; }
            .header h1 { color: #000; }
            .detail-value { color: #000; }
            .seats { border: 1px solid #000; }
            .seats .numbers { color: #000; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>🎬 CinemaTicket</h1>
            <p style="color:#fff;">Official Movie Ticket</p>
        </div>
        
        <div class="qr-code">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($ticket_code); ?>" alt="QR Code">
        </div>
        
        <div class="barcode">
            <?php echo $ticket_code; ?>
        </div>
        
        <div class="details">
            <div>
                <div class="detail-row">
                    <div class="detail-label">Movie</div>
                    <div class="detail-value"><?php echo htmlspecialchars($ticket['title']); ?></div>
                </div>
                
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
                        <div class="detail-value"><?php echo $ticket['screen_number']; ?></div>
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
                <?php endif; ?>
            </div>
            
            <div>
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
                    <div class="detail-value">$<?php echo number_format($ticket['total_price'], 2); ?></div>
                </div>
            </div>
        </div>
        
        <?php if ($ticket['seat_numbers']): ?>
            <div class="seats">
                <div class="label">Your Seats</div>
                <div class="numbers"><?php echo $ticket['seat_numbers']; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['ticket_type'] == 'online'): ?>
            <div class="seats" style="border-color:#44ff44;">
                <div class="label">Streaming Details</div>
                <div class="numbers">Views: <?php echo $ticket['streaming_views']; ?>/<?php echo $ticket['max_streaming_views']; ?></div>
                <div style="color:#888; margin-top:10px;">Valid until <?php echo date('F d, Y', strtotime('+30 days', strtotime($ticket['purchase_date']))); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Present this ticket (printed or on mobile) at the entrance</p>
            <p>For online streaming, visit our website and use your ticket code</p>
            <p style="margin-top:10px;">© <?php echo date('Y'); ?> CinemaTicket. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
<?php exit; ?>