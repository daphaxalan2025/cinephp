<?php 
// user/purchases.php - FIXED: Removed payment_id reference, correct JOIN using ticket_id
// FIXED: Added proper error handling for missing functions
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$current_theme = $user['theme_preference'] ?? 'dark';

// ============================================
// SEARCH FUNCTIONALITY
// ============================================
$search = $_GET['search'] ?? '';

// FIXED SQL: removed payment_id reference, using correct JOIN with ticket_id
$sql = "
    SELECT t.*, 
           u.first_name as owner_first_name, u.last_name as owner_last_name,
           CASE 
               WHEN t.ticket_type = 'cinema' THEN m.title
               WHEN t.ticket_type = 'online' THEN om.title
           END as title,
           CASE 
               WHEN t.ticket_type = 'cinema' THEN m.poster
               WHEN t.ticket_type = 'online' THEN om.poster
           END as poster,
           s.show_date, s.show_time, s.screen_number,
           c.name as cinema_name,
           os.show_date as online_date, 
           os.show_time as online_time,
           t.week_expiry,
           p.transaction_id,
           p.payment_method,
           p.payment_status
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN screenings s ON t.screening_id = s.id
    LEFT JOIN movies m ON s.movie_id = m.id
    LEFT JOIN cinemas c ON s.cinema_id = c.id
    LEFT JOIN online_schedule os ON t.online_schedule_id = os.id
    LEFT JOIN movies om ON os.movie_id = om.id
    LEFT JOIN payments p ON t.id = p.ticket_id
    WHERE t.user_id = ?
";

$params = [$user['id']];

if ($search) {
    $sql .= " AND (
        m.title LIKE ? 
        OR om.title LIKE ? 
        OR c.name LIKE ? 
        OR t.ticket_code LIKE ?
    )";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY t.purchase_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// ============================================
// WATCH BUTTON LOGIC
// ============================================
function getWatchButtonStatus($ticket) {
    if ($ticket['ticket_type'] != 'online') {
        return ['enabled' => false, 'message' => 'Not an online ticket', 'reason' => 'not_online'];
    }

    if ($ticket['status'] != 'paid') {
        return ['enabled' => false, 'message' => 'Payment pending', 'reason' => 'not_paid'];
    }

    $today = date('Y-m-d');
    $show_date = $ticket['online_date'] ?? null;
    $week_expiry = $ticket['week_expiry'] ?? null;

    if ($show_date && $today < $show_date) {
        return ['enabled' => false, 'message' => 'Streaming starts ' . date('M d', strtotime($show_date)), 'reason' => 'not_started'];
    }

    if ($week_expiry && $today > $week_expiry) {
        return ['enabled' => false, 'message' => 'Ticket expired ' . date('M d', strtotime($week_expiry)), 'reason' => 'expired'];
    }

    $days_left = 0;
    if ($week_expiry) {
        $days_left = (strtotime($week_expiry) - strtotime($today)) / 86400;
    }

    return [
        'enabled' => true,
        'message' => '▶️ Watch Now (' . ceil($days_left) . ' days left)',
        'reason' => 'valid'
    ];
}

function getQRCodeUrl($ticket_code) {
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($ticket_code);
}

$profile_name = $_SESSION['profile_name'] ?? 'Default';
$profile_type = $_SESSION['profile_type'] ?? 'adult';

// Get flash message safely - function exists in functions.php
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.3);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(26,26,26,0.9) 0%, rgba(20,20,20,0.95) 100%);
            --success-color: #44ff44;
            --pending-color: #ffff44;
        }
        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e0e0e0;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229,9,20,0.2);
            --border-color: rgba(229,9,20,0.2);
            --card-bg: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(240,240,240,0.95) 100%);
            --success-color: #00aa00;
            --pending-color: #cc8800;
        }
        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --bg-tertiary: #2a2a4a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255,0,255,0.5);
            --border-color: rgba(255,0,255,0.3);
            --card-bg: linear-gradient(135deg, rgba(26,26,58,0.9) 0%, rgba(20,20,50,0.95) 100%);
            --success-color: #00ffff;
            --pending-color: #ffff00;
        }
        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --bg-tertiary: #0f2a0f;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0,255,0,0.5);
            --border-color: rgba(0,255,0,0.3);
            --card-bg: linear-gradient(135deg, rgba(10,26,10,0.9) 0%, rgba(5,20,5,0.95) 100%);
            --success-color: #00ff00;
            --pending-color: #ffff00;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, var(--accent) 0%, transparent 50%);
            opacity: 0.03;
            pointer-events: none;
            z-index: -1;
        }
        .navbar {
            background: rgba(var(--bg-secondary), 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
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
            color: var(--accent);
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
        }
        .logo::before { content: "🎬"; margin-right: 8px; }
        .nav-links { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .profile-switch {
            background: rgba(229, 9, 20, 0.15);
            border: 1px solid #e50914;
            border-radius: 40px;
            padding: 6px 15px !important;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .profile-switch:hover {
            background: #e50914;
            color: white !important;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px 0;
        }
        .profile-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .profile-info {
            background: rgba(var(--accent), 0.1);
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 0.9rem;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .back-button:hover { border-color: var(--accent); color: var(--accent); transform: translateX(-5px); }
        .search-section { margin-bottom: 30px; }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .search-form input {
            flex: 1;
            padding: 14px 20px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .search-form input:focus { border-color: var(--accent); outline: none; }
        .search-form button {
            padding: 14px 30px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .search-form .clear-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .tickets-grid { display: grid; gap: 25px; margin-top: 30px; }
        .ticket-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
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
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            animation: slideBorder 3s infinite;
        }
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        .ticket-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .ticket-header {
            background: rgba(0,0,0,0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .ticket-code { font-family: monospace; font-size: 1.2rem; color: var(--accent); font-weight: 700; }
        .ticket-status {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-paid { background: rgba(68,255,68,0.15); color: var(--success-color); border: 1px solid var(--success-color); }
        .status-used { background: rgba(229,9,20,0.15); color: var(--accent); border: 1px solid var(--accent); }
        .status-pending { background: rgba(255,255,68,0.15); color: var(--pending-color); border: 1px solid var(--pending-color); }
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
            border: 2px solid var(--border-color);
            border-radius: 12px;
        }
        .ticket-details h3 { color: var(--accent); margin-bottom: 15px; font-size: 1.4rem; }
        .owner-badge {
            background: rgba(var(--accent),0.15);
            border: 1px solid var(--accent);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 15px;
        }
        .detail-row { display: flex; margin-bottom: 10px; flex-wrap: wrap; }
        .detail-label { width: 100px; color: var(--text-secondary); opacity: 0.7; }
        .detail-value { color: var(--text-primary); font-weight: 500; flex: 1; }
        .detail-value.highlight { color: var(--accent); font-weight: 700; }
        .expiry-warning { color: #ff8844; font-size: 0.8rem; margin-top: 5px; }
        
        .seat-row {
            display: flex;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .seat-label {
            width: 100px;
            color: var(--text-secondary);
            opacity: 0.7;
        }
        .seat-value {
            color: var(--text-primary);
            font-weight: 500;
            flex: 1;
        }
        
        .ticket-qr {
            text-align: center;
            padding: 15px;
            background: #fff;
            border-radius: 16px;
        }
        .ticket-qr img { width: 140px; height: 140px; border-radius: 8px; }
        .ticket-qr p { color: #333; font-size: 0.8rem; margin-top: 8px; }
        .ticket-actions { display: flex; gap: 10px; padding: 0 25px 25px 25px; }
        .btn-action {
            flex: 1;
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 40px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            background: transparent;
            cursor: pointer;
        }
        .btn-action:hover:not(.disabled) { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); }
        .btn-action.disabled { opacity: 0.5; cursor: not-allowed; }
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 32px;
        }
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        .alert {
            padding: 18px 25px;
            margin-bottom: 20px;
            border-radius: 16px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent);
        }
        @media (max-width: 1024px) {
            .ticket-body { grid-template-columns: 120px 1fr; }
            .ticket-qr { grid-column: span 2; margin-top: 20px; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            h1 { font-size: 2rem; }
            .ticket-body { grid-template-columns: 1fr; }
            .ticket-poster { margin: 0 auto; }
            .ticket-actions { flex-direction: column; }
            .search-form { flex-direction: column; }
            .profile-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="movies.php" class="active">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <div class="profile-badge">
                    <span>👤</span>
                    <span class="profile-name"><?php echo htmlspecialchars($_SESSION['profile_name'] ?? 'Profile'); ?></span>
                    <a href="select_profile.php" class="profile-switch">Switch</a>
                </div>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <a href="javascript:history.back()" class="back-button">← Back</a>
        
        <div class="profile-header">
            <h1>My Tickets</h1>
            <div class="profile-info">
                👤 Viewing as: <strong><?php echo htmlspecialchars($profile_name); ?></strong> (<?php echo ucfirst($profile_type); ?> profile)
            </div>
        </div>
        
        <div class="cinema-strip"></div>
        
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        
        <!-- SEARCH BAR -->
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by movie, cinema, or ticket code..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Search</button>
                <?php if ($search): ?>
                    <a href="purchases.php" class="clear-btn" style="padding: 14px 30px; background: transparent; border: 1px solid var(--border-color); border-radius: 40px; text-decoration: none; color: var(--text-primary);">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <div style="font-size: 5rem; margin-bottom: 20px;">🎟️</div>
                <h2><?php echo $search ? 'No tickets match your search' : 'No tickets yet'; ?></h2>
                <p><?php echo $search ? 'Try a different search term' : 'Browse movies and purchase your first ticket!'; ?></p>
                <a href="movies.php" class="btn-action" style="display: inline-block; width: auto; padding: 12px 30px; background: var(--accent); color: var(--bg-primary);">Browse Movies</a>
            </div>
        <?php else: ?>
            <div class="tickets-grid">
                <?php foreach ($tickets as $ticket): 
                    $watch_status = getWatchButtonStatus($ticket);
                ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <span class="ticket-code"><?php echo htmlspecialchars($ticket['ticket_code']); ?></span>
                            <span class="ticket-status status-<?php echo $ticket['status']; ?>"><?php echo strtoupper($ticket['status']); ?></span>
                        </div>
                        
                        <div class="ticket-body">
                            <?php 
                            $poster_path = !empty($ticket['poster']) ? '../uploads/posters/' . $ticket['poster'] : '../uploads/posters/default.jpg';
                            ?>
                            <img src="<?php echo $poster_path; ?>" class="ticket-poster" onerror="this.src='../uploads/posters/default.jpg'">
                            
                            <div class="ticket-details">
                                <?php if ($ticket['user_id'] != $user['id']): ?>
                                    <div class="owner-badge">For: <?php echo htmlspecialchars($ticket['owner_first_name'] . ' ' . $ticket['owner_last_name']); ?></div>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($ticket['title'] ?? 'Movie Ticket'); ?></h3>
                                
                                <?php if ($ticket['ticket_type'] == 'cinema'): ?>
                                    <div class="detail-row"><span class="detail-label">Cinema:</span><span class="detail-value"><?php echo htmlspecialchars($ticket['cinema_name'] ?? 'N/A'); ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Screen:</span><span class="detail-value"><?php echo $ticket['screen_number'] ?? 'N/A'; ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Date:</span><span class="detail-value"><?php echo $ticket['show_date'] ? date('M d, Y', strtotime($ticket['show_date'])) : 'N/A'; ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Time:</span><span class="detail-value"><?php echo $ticket['show_time'] ? date('h:i A', strtotime($ticket['show_time'])) : 'N/A'; ?></span></div>
                                    
                                    <?php if ($ticket['seat_numbers'] && $ticket['seat_numbers'] != 'N/A'): ?>
                                        <div class="seat-row">
                                            <span class="seat-label">Seats:</span>
                                            <span class="seat-value"><?php echo htmlspecialchars($ticket['seat_numbers']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-row"><span class="detail-label">Total:</span><span class="detail-value highlight">₱<?php echo number_format($ticket['total_price'], 2); ?></span></div>
                                    
                                <?php else: ?>
                                    <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value highlight">Online Streaming</span></div>
                                    <div class="detail-row"><span class="detail-label">Date:</span><span class="detail-value"><?php echo $ticket['online_date'] ? date('M d, Y', strtotime($ticket['online_date'])) : 'N/A'; ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Time:</span><span class="detail-value"><?php echo $ticket['online_time'] ? date('h:i A', strtotime($ticket['online_time'])) : 'N/A'; ?></span></div>
                                    <?php if ($ticket['week_expiry']): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Valid Until:</span>
                                            <span class="detail-value"><?php echo date('M d, Y', strtotime($ticket['week_expiry'])); ?></span>
                                        </div>
                                        <?php if (strtotime($ticket['week_expiry']) < strtotime('+3 days')): ?>
                                            <div class="expiry-warning">⚠️ Expiring soon!</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="detail-row"><span class="detail-label">Total:</span><span class="detail-value highlight">₱<?php echo number_format($ticket['total_price'], 2); ?></span></div>
                                <?php endif; ?>
                                
                                <?php if ($ticket['payment_method']): ?>
                                    <div class="detail-row"><span class="detail-label">Payment:</span><span class="detail-value"><?php echo ucfirst($ticket['payment_method']); ?></span></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ticket-qr">
                                <img src="<?php echo getQRCodeUrl($ticket['ticket_code']); ?>" alt="QR Code">
                                <p><?php echo htmlspecialchars($ticket['ticket_code']); ?></p>
                            </div>
                        </div>
                        
                        <div class="ticket-actions">
                            <?php if ($ticket['ticket_type'] == 'online'): ?>
                                <?php if ($watch_status['enabled']): ?>
                                    <a href="watch.php?ticket_code=<?php echo urlencode($ticket['ticket_code']); ?>" class="btn-action"><?php echo htmlspecialchars($watch_status['message']); ?></a>
                                <?php else: ?>
                                    <span class="btn-action disabled" style="background:rgba(255,68,68,0.1); border-color:#ff4444; color:#ff4444;">⛔ <?php echo htmlspecialchars($watch_status['message']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="download_ticket.php?code=<?php echo urlencode($ticket['ticket_code']); ?>" class="btn-action">📥 Download PDF</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="../assets/js/script.js"></script>
</body>
</html>