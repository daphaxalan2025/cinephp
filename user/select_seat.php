<?php
// user/select_seat.php - UPDATED: Dynamic seat map based on cinema's seats_per_screen
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Get current theme
$current_theme = $user['theme_preference'] ?? 'dark';

// Get screening ID from URL
$screening_id = isset($_GET['screening_id']) ? intval($_GET['screening_id']) : 0;

if (!$screening_id) {
    setFlash('Invalid screening selection', 'error');
    header('Location: movies.php');
    exit;
}

// Get screening details with cinema's seats_per_screen
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.description, m.poster, m.duration, m.rating, m.genre,
           c.name as cinema_name, c.location, c.seats_per_screen
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.id = ? AND s.status != 'expired' AND CONCAT(s.show_date, ' ', s.show_time) > NOW()
");
$stmt->execute([$screening_id]);
$screening = $stmt->fetch();

if (!$screening) {
    setFlash('Screening not found or expired', 'error');
    header('Location: movies.php');
    exit;
}

$base_price = $screening['price'];
$available_seats = $screening['available_seats'];
$total_seats = $screening['seats_per_screen'] ?? 40; // Get from cinema or default to 40
$max_tickets = 1; // Force max tickets to 1 (one person one ticket)

// Generate seat map with booked seats
$stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('paid', 'pending')");
$stmt->execute([$screening_id]);
$booked_seats = [];
while ($row = $stmt->fetch()) {
    if ($row['seat_numbers'] && $row['seat_numbers'] != 'N/A') {
        $booked_seats = array_merge($booked_seats, explode(',', $row['seat_numbers']));
    }
}
$booked_seats = array_unique($booked_seats);

// ========== DYNAMIC SEAT MAP GENERATION ==========
// Calculate rows and columns based on total seats
$cols = 8; // fixed number of columns
$rows_needed = ceil($total_seats / $cols);
$row_letters = range('A', chr(ord('A') + $rows_needed - 1));

$seats = [];
foreach ($row_letters as $row) {
    for ($i = 1; $i <= $cols; $i++) {
        $seat = $row . $i;
        if (count($seats) >= $total_seats) break 2; // Stop once we have enough seats
        $seats[] = [
            'number' => $seat,
            'available' => !in_array($seat, $booked_seats)
        ];
    }
}

// Get user's linked accounts (for adults)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, account_type FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}

// Handle AJAX request for checking seat availability
if (isset($_GET['check_seats']) && isset($_GET['seats'])) {
    header('Content-Type: application/json');
    $check_seats = explode(',', $_GET['seats']);
    $conflicts = array_intersect($check_seats, $booked_seats);
    echo json_encode([
        'available' => empty($conflicts),
        'conflicts' => $conflicts
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Seats - <?php echo htmlspecialchars($screening['title']); ?></title>
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
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
            --success-color: #44ff44;
        }
        :root[data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e0e0e0;
            --text-primary: #333333;
            --text-secondary: #666666;
            --accent: #e50914;
            --accent-dark: #b2070f;
            --accent-glow: 0 0 20px rgba(229, 9, 20, 0.2);
            --border-color: rgba(229, 9, 20, 0.2);
            --card-bg: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(240, 240, 240, 0.95) 100%);
            --success-color: #00aa00;
        }
        :root[data-theme="neon"] {
            --bg-primary: #0a0a2a;
            --bg-secondary: #1a1a3a;
            --bg-tertiary: #2a2a4a;
            --text-primary: #00ffff;
            --text-secondary: #ff00ff;
            --accent: #ff00ff;
            --accent-dark: #cc00cc;
            --accent-glow: 0 0 20px rgba(255, 0, 255, 0.5);
            --border-color: rgba(255, 0, 255, 0.3);
            --card-bg: linear-gradient(135deg, rgba(26, 26, 58, 0.9) 0%, rgba(20, 20, 50, 0.95) 100%);
            --success-color: #00ffff;
        }
        :root[data-theme="matrix"] {
            --bg-primary: #000000;
            --bg-secondary: #0a1a0a;
            --bg-tertiary: #0f2a0f;
            --text-primary: #00ff00;
            --text-secondary: #00aa00;
            --accent: #00ff00;
            --accent-dark: #00aa00;
            --accent-glow: 0 0 20px rgba(0, 255, 0, 0.5);
            --border-color: rgba(0, 255, 0, 0.3);
            --card-bg: linear-gradient(135deg, rgba(10, 26, 10, 0.9) 0%, rgba(5, 20, 5, 0.95) 100%);
            --success-color: #00ff00;
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
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        
        .movie-summary {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .summary-poster {
            width: 120px;
            height: 170px;
            object-fit: cover;
            border: 2px solid var(--border-color);
            border-radius: 12px;
        }
        .summary-details h1 {
            color: var(--accent);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .meta-info {
            display: flex;
            gap: 15px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .location-info {
            color: var(--text-secondary);
            margin-top: 10px;
        }
        .location-info strong { color: var(--accent); }
        
        .seat-selection {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin: 30px 0;
        }
        .seat-selection h2 { color: var(--accent); margin-bottom: 20px; }
        .screen {
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            height: 5px;
            width: 80%;
            margin: 0 auto 50px;
            text-align: center;
            padding-top: 15px;
            color: var(--text-secondary);
        }
        .seat-map {
            display: grid;
            grid-template-columns: repeat(<?php echo $cols; ?>, 1fr);
            gap: 10px;
            max-width: 800px;
            margin: 0 auto;
        }
        .seat {
            aspect-ratio: 1;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--text-secondary);
        }
        .seat.available:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: scale(1.1);
            box-shadow: 0 0 20px var(--accent-glow);
        }
        .seat.selected {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg-primary);
            transform: scale(1.05);
        }
        .seat.booked {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.2);
            cursor: not-allowed;
            text-decoration: line-through;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0 30px;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; color: var(--text-secondary); }
        .legend-box { width: 20px; height: 20px; border: 2px solid; border-radius: 4px; }
        .legend-box.available { border-color: var(--border-color); }
        .legend-box.selected { background: var(--accent); border-color: var(--accent); }
        .legend-box.booked { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        
        .selected-info {
            margin: 20px 0;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 40px;
            text-align: center;
        }
        .selected-info span { color: var(--accent); font-weight: 700; }
        
        .purchase-form {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 30px;
            margin-top: 30px;
        }
        .purchase-form h2 { color: var(--accent); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            color: var(--accent);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
        }
        .price-breakdown {
            background: rgba(0,0,0,0.3);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            color: var(--text-secondary);
        }
        .price-row.total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--accent);
            color: var(--accent);
            font-size: 1.2rem;
            font-weight: 700;
        }
        .proceed-btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 40px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
        }
        .proceed-btn:hover:not(:disabled) {
            background: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px var(--accent-glow);
        }
        .proceed-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--accent);
            text-decoration: none;
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
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .seat-map { grid-template-columns: repeat(4, 1fr); }
            .movie-summary { flex-direction: column; text-align: center; }
            .meta-info { justify-content: center; }
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
        <!-- Movie Summary -->
        <div class="movie-summary">
            <?php if ($screening['poster']): ?>
                <img src="../uploads/posters/<?php echo $screening['poster']; ?>" class="summary-poster">
            <?php else: ?>
                <div style="width:120px; height:170px; background:var(--bg-tertiary); border-radius:12px; display:flex; align-items:center; justify-content:center;">No Poster</div>
            <?php endif; ?>
            <div class="summary-details">
                <h1><?php echo htmlspecialchars($screening['title']); ?></h1>
                <div class="meta-info">
                    <span>⭐ <?php echo $screening['rating']; ?></span>
                    <span>⏱️ <?php echo $screening['duration']; ?> min</span>
                    <span>🎭 <?php echo htmlspecialchars($screening['genre']); ?></span>
                </div>
                <div class="location-info">
                    <strong><?php echo htmlspecialchars($screening['cinema_name']); ?></strong><br>
                    📍 <?php echo htmlspecialchars($screening['location']); ?><br>
                    🎬 Screen <?php echo $screening['screen_number']; ?><br>
                    📅 <?php echo date('F d, Y', strtotime($screening['show_date'])); ?> at <?php echo date('h:i A', strtotime($screening['show_time'])); ?>
                </div>
            </div>
        </div>
        
        <div class="cinema-strip"></div>
        
        <!-- Seat Selection -->
        <div class="seat-selection">
            <h2>Select Your Seats</h2>
            
            <div class="legend">
                <div class="legend-item"><div class="legend-box available"></div><span>Available</span></div>
                <div class="legend-item"><div class="legend-box selected"></div><span>Selected</span></div>
                <div class="legend-item"><div class="legend-box booked"></div><span>Booked</span></div>
            </div>
            
            <div class="screen">SCREEN</div>
            
            <div class="seat-map" id="seatMap">
                <?php foreach ($seats as $seat): ?>
                    <div class="seat <?php echo $seat['available'] ? 'available' : 'booked'; ?>" 
                         data-seat="<?php echo $seat['number']; ?>"
                         onclick="selectSeat(this)">
                        <?php echo $seat['number']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="selected-info" id="selectedInfo">
                Selected Seat: <span id="selectedSeatsDisplay">None</span>
            </div>
        </div>
        
        <!-- Purchase Form - NO QUANTITY DROPDOWN -->
        <div class="purchase-form">
            <h2>Complete Booking</h2>
            
            <form id="purchaseForm" method="GET" action="payment.php">
                <input type="hidden" name="type" value="cinema">
                <input type="hidden" name="id" value="<?php echo $screening_id; ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="seats" id="selectedSeatsInput">
                
                <?php if (!empty($linked_accounts)): ?>
                    <div class="form-group">
                        <label>Purchase for:</label>
                        <select name="for_user_id" id="forUserId">
                            <option value="<?php echo $user['id']; ?>">
                                Myself (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                            </option>
                            <?php foreach ($linked_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?> 
                                    (<?php echo ucfirst($account['account_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Price per ticket:</span>
                        <span>₱<?php echo number_format($base_price, 2); ?></span>
                    </div>
                    <div class="price-row">
                        <span>Service Fee:</span>
                        <span>₱<?php echo number_format(TICKET_FEE, 2); ?></span>
                    </div>
                    <div class="price-row total">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($base_price + TICKET_FEE, 2); ?></span>
                    </div>
                </div>
                
                <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                    Proceed to Payment
                </button>
            </form>
        </div>
        
        <a href="movie_detail.php?id=<?php echo $screening['movie_id']; ?>" class="back-link">← Back to Movie</a>
    </main>
    
    <script>
        let selectedSeats = [];
        const basePrice = <?php echo $base_price; ?>;
        const processingFee = <?php echo TICKET_FEE; ?>;
        const screeningId = <?php echo $screening_id; ?>;
        const quantity = 1;
        
        function selectSeat(seatElement) {
            if (seatElement.classList.contains('booked')) return;
            
            const seatNumber = seatElement.dataset.seat;
            
            if (seatElement.classList.contains('selected')) {
                // Deselect
                seatElement.classList.remove('selected');
                selectedSeats = selectedSeats.filter(s => s !== seatNumber);
            } else {
                // Select - only allow 1 seat
                if (selectedSeats.length < 1) {
                    seatElement.classList.add('selected');
                    selectedSeats.push(seatNumber);
                } else {
                    alert('You can only select 1 seat');
                }
            }
            
            updateSelectedSeats();
            updateProceedButton();
        }
        
        function updateSelectedSeats() {
            const display = document.getElementById('selectedSeatsDisplay');
            const input = document.getElementById('selectedSeatsInput');
            
            if (selectedSeats.length > 0) {
                display.textContent = selectedSeats.join(', ');
                input.value = selectedSeats.join(',');
            } else {
                display.textContent = 'None';
                input.value = '';
            }
        }
        
        function updateProceedButton() {
            const proceedBtn = document.getElementById('proceedBtn');
            proceedBtn.disabled = selectedSeats.length !== 1;
        }
        
        // Form validation before submit
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            if (selectedSeats.length !== 1) {
                e.preventDefault();
                alert('Please select 1 seat');
            }
        });
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>