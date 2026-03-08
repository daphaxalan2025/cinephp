<?php
// user/purchases.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Get user's tickets (including those purchased for linked accounts)
$stmt = $pdo->prepare("
    SELECT t.*, 
           m.title, m.poster,
           s.show_date, s.show_time, s.screen_number,
           c.name as cinema_name,
           os.show_date as online_date, os.show_time as online_time,
           p.transaction_id, p.payment_method,
           u.first_name as owner_first_name, u.last_name as owner_last_name
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN screenings s ON t.screening_id = s.id
    LEFT JOIN movies m ON s.movie_id = m.id
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    LEFT JOIN online_schedule os ON t.online_schedule_id = os.id
    LEFT JOIN movies om ON os.movie_id = om.id
    LEFT JOIN payments p ON t.payment_id = p.id
    WHERE t.user_id = ? OR t.user_id IN (SELECT id FROM users WHERE parent_id = ?)
    ORDER BY t.purchase_date DESC
");
$stmt->execute([$user['id'], $user['id']]);
$tickets = $stmt->fetchAll();

// If no tickets found with the above query, try a simpler query
if (empty($tickets)) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name as owner_first_name, u.last_name as owner_last_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.user_id = ? OR t.user_id IN (SELECT id FROM users WHERE parent_id = ?)
        ORDER BY t.purchase_date DESC
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $tickets = $stmt->fetchAll();
}

// Function to generate QR code URL
function getQRCodeUrl($ticket_code) {
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($ticket_code);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .tickets-grid {
            display: grid;
            gap: 25px;
            margin-top: 30px;
        }
        .ticket-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .ticket-card:hover {
            box-shadow: 0 0 20px rgba(0,255,255,0.3);
        }
        .ticket-header {
            background: #000;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #00ffff;
        }
        .ticket-code {
            font-family: monospace;
            font-size: 1.2rem;
            color: #00ffff;
            font-weight: bold;
        }
        .ticket-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .status-paid {
            background: rgba(68,255,68,0.2);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        .status-used {
            background: rgba(0,255,255,0.2);
            color: #00ffff;
            border: 1px solid #00ffff;
        }
        .status-pending {
            background: rgba(255,255,68,0.2);
            color: #ffff44;
            border: 1px solid #ffff44;
        }
        .ticket-body {
            display: grid;
            grid-template-columns: 100px 1fr 150px;
            gap: 20px;
            padding: 20px;
        }
        .ticket-poster {
            width: 100px;
            height: 140px;
            object-fit: cover;
            border: 1px solid #00ffff;
            border-radius: 4px;
        }
        .ticket-details {
            color: #fff;
        }
        .ticket-details h3 {
            color: #00ffff;
            margin-bottom: 10px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 8px;
            color: #888;
        }
        .detail-label {
            width: 80px;
        }
        .detail-value {
            color: #fff;
        }
        .seats {
            background: #000;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            text-align: center;
        }
        .seat-numbers {
            color: #00ffff;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .ticket-qr {
            text-align: center;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
        }
        .ticket-qr img {
            width: 120px;
            height: 120px;
        }
        .ticket-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding: 0 20px 20px;
        }
        .btn-action {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: 1px solid #00ffff;
            border-radius: 4px;
            color: #00ffff;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-action:hover {
            background: #00ffff;
            color: #000;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .owner-badge {
            background: #00ffff;
            color: #000;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .ticket-body {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .ticket-poster {
                margin: 0 auto;
            }
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
                <a href="purchases.php" class="active">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>My Tickets</h1>
        
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎟️</div>
                <h2>No tickets yet</h2>
                <p style="color:#888; margin-bottom:20px;">Browse movies and purchase your first ticket!</p>
                <a href="movies.php" class="btn btn-primary">Browse Movies</a>
            </div>
        <?php else: ?>
            <div class="tickets-grid">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <span class="ticket-code"><?php echo $ticket['ticket_code']; ?></span>
                            <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                <?php echo strtoupper($ticket['status']); ?>
                            </span>
                        </div>
                        
                        <div class="ticket-body">
                            <!-- Poster - try to get from various sources -->
                            <?php
                            $poster = '../uploads/posters/default.jpg';
                            if (!empty($ticket['poster'])) {
                                $poster = '../uploads/posters/' . $ticket['poster'];
                            }
                            ?>
                            <img src="<?php echo $poster; ?>" class="ticket-poster" onerror="this.src='../uploads/posters/default.jpg'">
                            
                            <!-- Details -->
                            <div class="ticket-details">
                                <?php if ($ticket['user_id'] != $user['id']): ?>
                                    <div class="owner-badge">
                                        For: <?php echo htmlspecialchars($ticket['owner_first_name'] . ' ' . $ticket['owner_last_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h3><?php echo htmlspecialchars($ticket['title'] ?? 'Movie Ticket'); ?></h3>
                                
                                <?php if ($ticket['ticket_type'] == 'cinema'): ?>
                                    <?php if (!empty($ticket['cinema_name'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Cinema:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($ticket['cinema_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['screen_number'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Screen:</span>
                                            <span class="detail-value"><?php echo $ticket['screen_number']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['show_date'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Date:</span>
                                            <span class="detail-value"><?php echo date('M d, Y', strtotime($ticket['show_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['show_time'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Time:</span>
                                            <span class="detail-value"><?php echo date('h:i A', strtotime($ticket['show_time'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value">Online Streaming</span>
                                    </div>
                                    <?php if (!empty($ticket['online_date'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Date:</span>
                                            <span class="detail-value"><?php echo date('M d, Y', strtotime($ticket['online_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['online_time'])): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Time:</span>
                                            <span class="detail-value"><?php echo date('h:i A', strtotime($ticket['online_time'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Views:</span>
                                        <span class="detail-value"><?php echo $ticket['streaming_views']; ?>/<?php echo $ticket['max_streaming_views']; ?> used</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($ticket['seat_numbers']): ?>
                                    <div class="seats">
                                        <div style="color:#888; margin-bottom:5px;">Seats</div>
                                        <div class="seat-numbers"><?php echo $ticket['seat_numbers']; ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-row" style="margin-top:10px;">
                                    <span class="detail-label">Total:</span>
                                    <span class="detail-value" style="color:#00ffff;">$<?php echo number_format($ticket['total_price'], 2); ?></span>
                                </div>
                                <?php if (!empty($ticket['transaction_id'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Transaction:</span>
                                        <span class="detail-value" style="font-family:monospace;"><?php echo $ticket['transaction_id']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- QR Code -->
                            <div class="ticket-qr">
                                <img src="<?php echo getQRCodeUrl($ticket['ticket_code']); ?>" alt="QR Code">
                                <p style="color:#000; font-size:0.8rem; margin-top:5px;">Scan for entry</p>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="ticket-actions">
                            <?php if ($ticket['ticket_type'] == 'online' && $ticket['status'] == 'paid' && $ticket['streaming_views'] < $ticket['max_streaming_views']): ?>
                                <a href="watch.php?ticket_code=<?php echo $ticket['ticket_code']; ?>" class="btn-action">▶️ Watch Now</a>
                            <?php endif; ?>
                            <a href="download_ticket.php?code=<?php echo $ticket['ticket_code']; ?>" class="btn-action">📥 Download PDF</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>