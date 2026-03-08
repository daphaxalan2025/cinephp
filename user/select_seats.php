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
    <style>
        .selection-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        .movie-info {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
        }
        .movie-poster {
            width: 100%;
            max-width: 200px;
            border-radius: 8px;
            border: 1px solid #00ffff;
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #333;
        }
        .seat-map-container {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
        }
        .screen {
            background: linear-gradient(90deg, transparent, #00ffff, transparent);
            height: 5px;
            width: 80%;
            margin: 0 auto 40px;
            text-align: center;
            padding-top: 15px;
            color: #888;
        }
        .seat-map {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            max-width: 500px;
            margin: 0 auto;
        }
        .seat {
            aspect-ratio: 1;
            border: 2px solid #333;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s;
            background: #000;
            color: #888;
        }
        .seat.available:hover {
            border-color: #00ffff;
            color: #00ffff;
            transform: scale(1.1);
        }
        .seat.selected {
            background: #00ffff;
            border-color: #00ffff;
            color: #000;
        }
        .seat.booked {
            background: #333;
            border-color: #444;
            color: #666;
            cursor: not-allowed;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #888;
        }
        .legend-box {
            width: 20px;
            height: 20px;
            border: 2px solid;
            border-radius: 4px;
        }
        .legend-box.available { border-color: #333; }
        .legend-box.selected { background: #00ffff; border-color: #00ffff; }
        .legend-box.booked { background: #333; border-color: #444; }
        .selected-info {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 4px;
            color: #00ffff;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-group select {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid #00ffff;
            color: #fff;
            border-radius: 4px;
        }
        .proceed-btn {
            width: 100%;
            padding: 15px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        .proceed-btn:hover:not(:disabled) {
            box-shadow: 0 0 30px #00ffff;
        }
        .proceed-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .price-summary {
            background: #000;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .parent-notice {
            background: rgba(255, 255, 68, 0.1);
            border: 1px solid #ffff44;
            color: #ffff44;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <h1>Select Your Seats</h1>
        
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
                <h2 style="color:#00ffff; margin-bottom:20px;">Movie Details</h2>
                
                <img src="../uploads/posters/<?php echo $screening['poster']; ?>" class="movie-poster">
                
                <h3 style="color:#fff; margin-bottom:10px;"><?php echo htmlspecialchars($screening['title']); ?></h3>
                
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
                    <span><?php echo $screening['screen_number']; ?></span>
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
                    <div style="margin-top: 20px;">
                        <label style="color:#00ffff; display:block; margin-bottom:10px;">Purchase for:</label>
                        <select id="forUserId" style="width:100%; padding:10px; background:#000; color:#fff; border:1px solid #00ffff; border-radius:4px;">
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
                <h2 style="color:#00ffff; margin-bottom:20px;">Choose Your Seats</h2>
                
                <div class="screen">SCREEN</div>
                
                <div class="legend">
                    <div class="legend-item"><div class="legend-box available"></div> Available</div>
                    <div class="legend-item"><div class="legend-box selected"></div> Selected</div>
                    <div class="legend-item"><div class="legend-box booked"></div> Booked</div>
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
                    <label style="color:#00ffff;">Number of Tickets</label>
                    <select id="quantity" onchange="updateQuantity()">
                        <?php for ($i = 1; $i <= min(10, $screening['available_seats']); $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Ticket(s)</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="selected-info" id="selectedSeats">Selected: None</div>
                
                <div class="price-summary">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span>Processing Fee (₱150 each):</span>
                        <span id="fee">$0.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:1.2rem; color:#00ffff; font-weight:bold;">
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
            if (selectedSeats.length === 0) {
                document.getElementById('selectedSeats').textContent = 'Selected: None';
            } else {
                document.getElementById('selectedSeats').textContent = 'Selected: ' + selectedSeats.join(', ');
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