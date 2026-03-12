<?php
// user/select_seats.php
require_once '../includes/functions.php';
requireLogin();

$screening_id = $_GET['screening_id'] ?? 0;
$pdo = getDB();
$user = getCurrentUser();

// Get screening details
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.poster, m.price as movie_price, c.name as cinema_name, c.location
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$screening_id]);
$screening = $stmt->fetch();

if (!$screening) {
    setFlash('Screening not found', 'error');
    header('Location: movies.php');
    exit;
}

// Get booked seats
$booked = [];
$stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('paid', 'pending')");
$stmt->execute([$screening_id]);
while ($row = $stmt->fetch()) {
    if ($row['seat_numbers']) {
        $booked = array_merge($booked, explode(',', $row['seat_numbers']));
    }
}

// Get user's linked accounts (for adults)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE parent_id = ?");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}

// Get parent info (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Seats - <?php echo htmlspecialchars($screening['title']); ?></title>
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
        
        /* Selection Container */
        .selection-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        /* Movie Info Card */
        .movie-info {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .movie-info::before {
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
        
        .movie-info h2 {
            color: var(--red);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .movie-poster {
            width: 100%;
            max-width: 200px;
            border-radius: 12px;
            border: 2px solid rgba(229, 9, 20, 0.3);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .movie-poster:hover {
            border-color: var(--red);
            transform: scale(1.05);
        }
        
        .movie-info h3 {
            color: #fff;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            padding: 8px 0;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
        }
        
        .detail-row span:last-child {
            color: var(--red);
            font-weight: 600;
        }
        
        /* Family Select */
        .family-select {
            margin-top: 20px;
        }
        
        .family-select label {
            color: var(--red);
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .family-select select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .family-select select:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        /* Seat Map Container */
        .seat-map-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .seat-map-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        .seat-map-container h2 {
            color: var(--red);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .screen {
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            height: 5px;
            width: 80%;
            margin: 0 auto 40px;
            text-align: center;
            padding-top: 15px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 5px;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0 30px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border: 2px solid;
            border-radius: 4px;
        }
        
        .legend-box.available { border-color: rgba(255, 255, 255, 0.2); }
        .legend-box.selected { background: var(--red); border-color: var(--red); }
        .legend-box.booked { background: rgba(255, 255, 255, 0.1); border-color: transparent; }
        
        .seat-map {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .seat {
            aspect-ratio: 1;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
        }
        
        .seat.available:hover {
            border-color: var(--red);
            color: var(--red);
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.3);
        }
        
        .seat.selected {
            background: var(--red);
            border-color: var(--red);
            color: #fff;
            transform: scale(1.05);
        }
        
        .seat.booked {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.2);
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        /* Form Elements */
        .form-group {
            margin: 25px 0;
        }
        
        .form-group label {
            color: var(--red);
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            color: var(--text-primary);
            border-radius: 40px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .form-group select:focus {
            border-color: var(--red);
            outline: none;
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .selected-info {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--red);
            font-weight: 600;
        }
        
        .selected-info span {
            color: #fff;
        }
        
        .price-summary {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.2);
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
        
        .price-row span:last-child {
            color: var(--red);
            font-weight: 600;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--red);
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .total-row span:last-child {
            color: var(--red);
        }
        
        .proceed-btn {
            width: 100%;
            padding: 16px;
            background: var(--red);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .proceed-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .proceed-btn:hover:not(:disabled) {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
        }
        
        .proceed-btn:hover:not(:disabled)::before {
            left: 100%;
        }
        
        .proceed-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Parent Notice */
        .parent-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 20px;
            border-radius: 40px;
            margin-bottom: 20px;
            border-left: 4px solid var(--red);
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
            .selection-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .seat-map {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
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
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Select Your Seats</h1>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
        <!-- Parent notice for kids/teens -->
        <?php if ($user['account_type'] == 'kid' || $user['account_type'] == 'teen'): ?>
            <?php if ($parent): ?>
                <div class="parent-notice">
                    👤 This purchase will be sent to your parent (<?php echo htmlspecialchars($parent['first_name']); ?>) for approval.
                    They will complete the payment.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="selection-container">
            <!-- Left: Movie Info -->
            <div class="movie-info">
                <h2>Movie Details</h2>
                
                <?php if ($screening['poster']): ?>
                    <img src="../uploads/posters/<?php echo $screening['poster']; ?>" class="movie-poster">
                <?php else: ?>
                    <div style="width:200px; height:300px; background:var(--deep-gray); border:2px solid rgba(229,9,20,0.3); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); margin-bottom:20px;">
                        No Poster
                    </div>
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($screening['title']); ?></h3>
                
                <div class="detail-row">
                    <span>Cinema:</span>
                    <span><?php echo htmlspecialchars($screening['cinema_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Location:</span>
                    <span><?php echo htmlspecialchars($screening['location']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Screen:</span>
                    <span>Screen <?php echo $screening['screen_number']; ?></span>
                </div>
                <div class="detail-row">
                    <span>Date:</span>
                    <span><?php echo date('F d, Y', strtotime($screening['show_date'])); ?></span>
                </div>
                <div class="detail-row">
                    <span>Time:</span>
                    <span><?php echo date('h:i A', strtotime($screening['show_time'])); ?></span>
                </div>
                <div class="detail-row">
                    <span>Price per ticket:</span>
                    <span>$<?php echo number_format($screening['price'], 2); ?></span>
                </div>
                
                <!-- Family purchase option (for adults) -->
                <?php if (!empty($linked_accounts) && $user['account_type'] == 'adult'): ?>
                    <div class="family-select">
                        <label>Purchase for:</label>
                        <select id="forUserId">
                            <option value="<?php echo $user['id']; ?>">Myself</option>
                            <?php foreach ($linked_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Seat Selection -->
            <div class="seat-map-container">
                <h2>Choose Your Seats</h2>
                
                <div class="screen">SCREEN</div>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-box available"></div>
                        <span>Available</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box selected"></div>
                        <span>Selected</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-box booked"></div>
                        <span>Booked</span>
                    </div>
                </div>
                
                <div class="seat-map" id="seatMap">
                    <?php
                    $rows = ['A', 'B', 'C', 'D', 'E'];
                    foreach ($rows as $row) {
                        for ($i = 1; $i <= 8; $i++) {
                            $seat = $row . $i;
                            $class = in_array($seat, $booked) ? 'booked' : 'available';
                            echo "<div class='seat $class' data-seat='$seat' onclick='selectSeat(this)'>$seat</div>";
                        }
                    }
                    ?>
                </div>
                
                <div class="form-group">
                    <label>Number of Tickets</label>
                    <select id="quantity" onchange="updateQuantity()">
                        <?php for ($i = 1; $i <= min(10, $screening['available_seats']); $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="selected-info" id="selectedSeats">
                    Selected: <span>None</span>
                </div>
                
                <div class="price-summary">
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                    <div class="price-row">
                        <span>Processing Fee (₱150 each):</span>
                        <span id="fee">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span>TOTAL:</span>
                        <span id="total">$0.00</span>
                    </div>
                </div>
                
                <input type="hidden" id="selectedSeatsInput">
                <button class="proceed-btn" id="proceedBtn" onclick="proceedToPayment()" disabled>
                    Proceed to Payment
                </button>
            </div>
        </div>
    </main>
    
    <script>
        const price = <?php echo $screening['price']; ?>;
        const processingFee = 3.00;
        let selectedSeats = [];
        
        function selectSeat(seat) {
            if (seat.classList.contains('booked')) return;
            
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (seat.classList.contains('selected')) {
                seat.classList.remove('selected');
                selectedSeats = selectedSeats.filter(s => s !== seat.dataset.seat);
            } else {
                if (selectedSeats.length < quantity) {
                    seat.classList.add('selected');
                    selectedSeats.push(seat.dataset.seat);
                } else {
                    alert('You can only select ' + quantity + ' seat(s)');
                }
            }
            
            updateSelectedSeats();
            updatePrice();
            document.getElementById('proceedBtn').disabled = selectedSeats.length !== quantity;
        }
        
        function updateSelectedSeats() {
            const display = document.getElementById('selectedSeats');
            if (selectedSeats.length === 0) {
                display.innerHTML = 'Selected: <span>None</span>';
            } else {
                display.innerHTML = 'Selected: <span>' + selectedSeats.join(', ') + '</span>';
            }
            document.getElementById('selectedSeatsInput').value = selectedSeats.join(',');
        }
        
        function updateQuantity() {
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));
            selectedSeats = [];
            updateSelectedSeats();
            updatePrice();
            document.getElementById('proceedBtn').disabled = true;
        }
        
        function updatePrice() {
            const qty = selectedSeats.length;
            const subtotal = price * qty;
            const fee = processingFee * qty;
            const total = subtotal + fee;
            
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('fee').textContent = '$' + fee.toFixed(2);
            document.getElementById('total').textContent = '$' + total.toFixed(2);
        }
        
        function proceedToPayment() {
            const quantity = document.getElementById('quantity').value;
            const seats = selectedSeats.join(',');
            const forUserId = document.getElementById('forUserId')?.value || <?php echo $user['id']; ?>;
            
            window.location.href = `payment.php?type=cinema&id=<?php echo $screening_id; ?>&quantity=${quantity}&seats=${seats}&for_user_id=${forUserId}`;
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>