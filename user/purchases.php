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
        
        /* Tickets Grid */
        .tickets-grid {
            display: grid;
            gap: 25px;
            margin-top: 30px;
        }
        
        .ticket-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        
        .ticket-card::before {
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
        
        .ticket-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .ticket-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .ticket-code {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 1.2rem;
            color: var(--red);
            font-weight: 700;
            letter-spacing: 2px;
        }
        
        .ticket-status {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-paid {
            background: rgba(68, 255, 68, 0.15);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        
        .status-used {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .status-pending {
            background: rgba(255, 255, 68, 0.15);
            color: #ffff44;
            border: 1px solid #ffff44;
        }
        
        .ticket-body {
            display: grid;
            grid-template-columns: 120px 1fr 180px;
            gap: 20px;
            padding: 25px;
        }
        
        .ticket-poster {
            width: 120px;
            height: 170px;
            object-fit: cover;
            border: 2px solid rgba(229, 9, 20, 0.3);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .ticket-poster:hover {
            border-color: var(--red);
            transform: scale(1.05);
        }
        
        .ticket-details {
            color: var(--text-primary);
        }
        
        .ticket-details h3 {
            color: var(--red);
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .owner-badge {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid var(--red);
            color: var(--red);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .detail-label {
            width: 90px;
            color: #888;
        }
        
        .detail-value {
            color: #fff;
            font-weight: 500;
        }
        
        .detail-value.highlight {
            color: var(--red);
            font-weight: 700;
        }
        
        .seats {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 12px;
            padding: 12px;
            margin: 15px 0;
            text-align: center;
        }
        
        .seats-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .seat-numbers {
            color: var(--red);
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 2px;
        }
        
        .ticket-qr {
            text-align: center;
            padding: 15px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .ticket-qr img {
            width: 140px;
            height: 140px;
            border-radius: 8px;
        }
        
        .ticket-qr p {
            color: #333;
            font-size: 0.8rem;
            margin-top: 8px;
            font-weight: 500;
        }
        
        .ticket-actions {
            display: flex;
            gap: 10px;
            padding: 0 25px 25px 25px;
        }
        
        .btn-action {
            flex: 1;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(229, 9, 20, 0.3);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-action:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '🎟️';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 8rem;
            opacity: 0.03;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        
        .empty-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(229, 9, 20, 0.3));
        }
        
        .empty-state h2 {
            font-size: 2rem;
            color: #fff;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 15px 40px;
            border-radius: 40px;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .ticket-body {
                grid-template-columns: 120px 1fr;
            }
            
            .ticket-qr {
                grid-column: span 2;
                margin-top: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .ticket-body {
                grid-template-columns: 1fr;
            }
            
            .ticket-poster {
                margin: 0 auto;
            }
            
            .ticket-qr {
                grid-column: span 1;
            }
            
            .ticket-actions {
                flex-direction: column;
            }
            
            .empty-state {
                padding: 60px 20px;
            }
            
            .empty-state h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
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
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎟️</div>
                <h2>No tickets yet</h2>
                <p>Browse our movies and purchase your first ticket!</p>
                <a href="movies.php" class="btn-primary">Browse Movies</a>
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
                                        <span class="detail-value highlight">Online Streaming</span>
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
                                        <div class="seats-label">Reserved Seats</div>
                                        <div class="seat-numbers"><?php echo $ticket['seat_numbers']; ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-row" style="margin-top:10px;">
                                    <span class="detail-label">Total:</span>
                                    <span class="detail-value highlight">$<?php echo number_format($ticket['total_price'], 2); ?></span>
                                </div>
                                <?php if (!empty($ticket['transaction_id'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Transaction:</span>
                                        <span class="detail-value" style="font-family:monospace; color: var(--red);"><?php echo $ticket['transaction_id']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- QR Code -->
                            <div class="ticket-qr">
                                <img src="<?php echo getQRCodeUrl($ticket['ticket_code']); ?>" alt="QR Code">
                                <p>Scan for entry</p>
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