<?php
// user/purchase.php - COMPLETE FIXED VERSION
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Check if user is kid (cannot purchase)
if ($user['account_type'] == 'kid') {
    setFlash('Kid accounts cannot purchase tickets. Please ask a parent/guardian.', 'error');
    header('Location: movies.php');
    exit;
}

$screening_id = isset($_GET['screening_id']) ? intval($_GET['screening_id']) : 0;
$movie_id = isset($_GET['movie_id']) ? intval($_GET['movie_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

$processing_fee = 3.00; // ₱150 = ~$3.00

if ($screening_id) {
    // Get screening details for physical ticket
    $stmt = $pdo->prepare("
        SELECT s.*, m.title, m.description, m.poster, m.duration, m.rating, m.genre,
               c.name as cinema_name, c.location
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        JOIN cinemas c ON s.cinema_id = c.id
        WHERE s.id = ? AND s.show_date >= CURDATE()
    ");
    $stmt->execute([$screening_id]);
    $screening = $stmt->fetch();
    
    if (!$screening) {
        setFlash('Screening not found or expired', 'error');
        header('Location: movies.php');
        exit;
    }
    
    $ticket_type = 'physical';
    $base_price = $screening['price'];
    $item_name = $screening['title'];
    $cinema_name = $screening['cinema_name'];
    $location = $screening['location'];
    $screen_number = $screening['screen_number'];
    $show_date = $screening['show_date'];
    $show_time = $screening['show_time'];
    $duration = $screening['duration'];
    $rating = $screening['rating'];
    $poster = $screening['poster'];
    $available_seats = $screening['available_seats'];
    
    // Generate seat map
    $seats = generateSeatMap($screening_id);
    
} elseif ($movie_id && $type == 'online') {
    // Get movie details for online ticket
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movie_id]);
    $movie = $stmt->fetch();
    
    if (!$movie) {
        setFlash('Movie not found', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Check age restriction
    if ($user['account_type'] == 'teen' && !in_array($movie['rating'], ['G', 'PG', 'PG-13'])) {
        setFlash('This movie is not available for your age group', 'error');
        header('Location: movies.php');
        exit;
    }
    
    // Get available online schedules
    $stmt = $pdo->prepare("
        SELECT * FROM online_schedule 
        WHERE movie_id = ? 
        AND show_date >= CURDATE() 
        AND status = 'scheduled'
        AND current_viewers < max_viewers
        ORDER BY show_date, show_time
    ");
    $stmt->execute([$movie_id]);
    $online_schedules = $stmt->fetchAll();
    
    if (empty($online_schedules)) {
        setFlash('No online streaming schedules available for this movie', 'error');
        header('Location: movie_detail.php?id=' . $movie_id);
        exit;
    }
    
    $ticket_type = 'online';
    $item_name = $movie['title'];
    $poster = $movie['poster'];
    $duration = $movie['duration'];
    $rating = $movie['rating'];
    $genre = $movie['genre'];
    
} else {
    setFlash('Invalid request', 'error');
    header('Location: movies.php');
    exit;
}

// Handle form submission for physical tickets
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $ticket_type == 'physical') {
    $quantity = intval($_POST['quantity'] ?? 1);
    $selected_seats = isset($_POST['seats']) ? explode(',', $_POST['seats']) : [];
    $for_user_id = $_POST['for_user_id'] ?? $user['id'];
    
    // Calculate total with processing fee
    $subtotal = $base_price * $quantity;
    $total_fee = $processing_fee * $quantity;
    $total_price = $subtotal + $total_fee;
    
    // Validate
    $errors = [];
    
    if ($quantity < 1 || $quantity > 10) {
        $errors[] = 'Invalid quantity';
    }
    
    if ($available_seats < $quantity) {
        $errors[] = 'Not enough seats available! Only ' . $available_seats . ' left.';
    }
    
    if (empty($selected_seats)) {
        $errors[] = 'Please select your seats';
    } elseif (count($selected_seats) != $quantity) {
        $errors[] = 'Please select exactly ' . $quantity . ' seat(s)';
    } else {
        // Check if seats are still available
        $stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('paid', 'pending')");
        $stmt->execute([$screening_id]);
        $booked_seats = [];
        while ($row = $stmt->fetch()) {
            if ($row['seat_numbers']) {
                $booked_seats = array_merge($booked_seats, explode(',', $row['seat_numbers']));
            }
        }
        
        $conflicts = array_intersect($selected_seats, $booked_seats);
        if (!empty($conflicts)) {
            $errors[] = 'Some seats are no longer available: ' . implode(', ', $conflicts);
        }
    }
    
    if (empty($errors)) {
        // Redirect to payment
        $seats_param = implode(',', $selected_seats);
        header("Location: payment.php?screening_id={$screening_id}&quantity={$quantity}&seats={$seats_param}" . 
               ($for_user_id != $user['id'] ? "&for_user_id={$for_user_id}" : ""));
        exit;
    }
}

// Get user's linked accounts (for adults)
$linked_accounts = [];
if ($user['account_type'] == 'adult') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, account_type FROM users WHERE parent_id = ?");
    $stmt->execute([$user['id']]);
    $linked_accounts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Tickets - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .purchase-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .movie-summary {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 25px;
        }
        
        .summary-poster {
            width: 120px;
            height: 170px;
            object-fit: cover;
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
        
        .summary-details h1 {
            color: #00ffff;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .meta-info {
            display: flex;
            gap: 20px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .rating-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .rating-G { background: #44ff44; color: #000; }
        .rating-PG { background: #ffff44; color: #000; }
        .rating-PG-13 { background: #ff8844; color: #000; }
        .rating-R { background: #ff4444; color: #fff; }
        
        <?php if ($ticket_type == 'online'): ?>
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .schedule-card {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 255, 255, 0.3);
        }
        
        .schedule-card.selected {
            background: rgba(0, 255, 255, 0.1);
            border-width: 3px;
        }
        
        .schedule-time {
            font-size: 1.3rem;
            color: #00ffff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .availability {
            color: #44ff44;
            margin: 10px 0;
        }
        
        .availability.warning {
            color: #ffff44;
        }
        
        .schedule-price {
            font-size: 1.2rem;
            color: #fff;
            margin: 15px 0;
        }
        <?php else: ?>
        .seat-selection {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
        }
        
        .screen {
            background: linear-gradient(90deg, transparent, #00ffff, transparent);
            height: 5px;
            width: 80%;
            margin: 0 auto 50px;
            text-align: center;
            padding-top: 15px;
            color: #888;
        }
        
        .seat-map {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 10px;
            max-width: 600px;
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
        
        .seat.aisle {
            border-color: #00ffff;
        }
        <?php endif; ?>
        
        .purchase-form {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 8px;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group select:focus {
            border-color: #00ffff;
            outline: none;
        }
        
        .price-breakdown {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            color: #888;
        }
        
        .price-row.total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #00ffff;
            color: #00ffff;
            font-size: 1.3rem;
            font-weight: bold;
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
            transition: all 0.3s;
        }
        
        .proceed-btn:hover:not(:disabled) {
            box-shadow: 0 0 30px #00ffff;
            transform: scale(1.02);
        }
        
        .proceed-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .selected-info {
            margin: 20px 0;
            padding: 15px;
            background: #000;
            border-radius: 4px;
            color: #00ffff;
            text-align: center;
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
            gap: 8px;
            color: #888;
        }
        
        .legend-box {
            width: 20px;
            height: 20px;
            border: 2px solid;
        }
        
        .legend-box.available { border-color: #333; }
        .legend-box.selected { background: #00ffff; border-color: #00ffff; }
        .legend-box.booked { background: #333; border-color: #444; }
        
        .online-benefits {
            background: rgba(0, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .online-benefits h3 {
            color: #00ffff;
            margin-bottom: 15px;
        }
        
        .online-benefits ul {
            list-style: none;
            padding: 0;
        }
        
        .online-benefits li {
            color: #888;
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        
        .online-benefits li:before {
            content: "✓";
            color: #00ffff;
            position: absolute;
            left: 0;
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
    
    <main class="container purchase-container">
        <!-- Movie Summary -->
        <div class="movie-summary">
            <img src="../uploads/posters/<?php echo $poster; ?>" 
                 alt="<?php echo htmlspecialchars($item_name); ?>" 
                 class="summary-poster">
            
            <div class="summary-details">
                <h1><?php echo htmlspecialchars($item_name); ?></h1>
                
                <div class="meta-info">
                    <span class="rating-badge rating-<?php echo $rating; ?>">
                        <?php echo $rating; ?>
                    </span>
                    <span>⏱️ <?php echo $duration; ?> min</span>
                    <?php if (isset($genre)): ?>
                        <span>🎭 <?php echo htmlspecialchars($genre); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($ticket_type == 'physical'): ?>
                    <p style="color: #888;">
                        <strong style="color: #00ffff;"><?php echo htmlspecialchars($cinema_name); ?></strong><br>
                        📍 <?php echo htmlspecialchars($location); ?><br>
                        🎬 Screen <?php echo $screen_number; ?><br>
                        📅 <?php echo date('F d, Y', strtotime($show_date)); ?> at <?php echo date('h:i A', strtotime($show_time)); ?>
                    </p>
                <?php else: ?>
                    <p style="color: #888;">
                        <strong style="color: #00ffff;">Online Streaming</strong><br>
                        Watch anywhere, anytime with 3 views per ticket
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($ticket_type == 'online'): ?>
            <!-- Online Schedule Selection -->
            <div class="purchase-form">
                <h2 style="color: #00ffff; margin-bottom: 20px;">Select Streaming Time</h2>
                
                <div class="online-benefits">
                    <h3>🎥 Online Streaming Benefits</h3>
                    <ul>
                        <li>Watch on any device (phone, tablet, computer)</li>
                        <li>3 views included per ticket</li>
                        <li>Valid for 30 days after purchase</li>
                        <li>HD streaming quality</li>
                        <li>Pause and resume anytime</li>
                        <li><span style="color: #00ffff;">20% discount</span> applied to online tickets</li>
                    </ul>
                </div>
                
                <div class="schedule-grid" id="scheduleGrid">
                    <?php foreach ($online_schedules as $schedule): 
                        $available = $schedule['max_viewers'] - $schedule['current_viewers'];
                        $status_class = $available <= 5 ? 'warning' : '';
                    ?>
                        <div class="schedule-card" onclick="selectSchedule(<?php echo $schedule['id']; ?>, <?php echo $schedule['price']; ?>, this)">
                            <div class="schedule-time">
                                🕐 <?php echo date('h:i A', strtotime($schedule['show_time'])); ?>
                            </div>
                            <div style="color: #888;">
                                <?php echo date('F d, Y', strtotime($schedule['show_date'])); ?>
                            </div>
                            <div class="availability <?php echo $status_class; ?>">
                                👥 <?php echo $available; ?> spots available
                            </div>
                            <div class="schedule-price">
                                $<?php echo number_format($schedule['price'], 2); ?> per ticket
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <form method="GET" action="payment.php" id="onlineForm" style="margin-top: 30px;">
                    <input type="hidden" name="online_id" id="selectedScheduleId">
                    
                    <div class="form-group">
                        <label for="quantity">Number of Tickets</label>
                        <select name="quantity" id="quantity" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="price-breakdown" id="priceBreakdown" style="display: none;">
                        <div class="price-row">
                            <span>Price per ticket:</span>
                            <span id="perTicketPrice">$0.00</span>
                        </div>
                        <div class="price-row">
                            <span>Processing Fee (₱150 each):</span>
                            <span id="feePerTicket">$<?php echo number_format($processing_fee, 2); ?></span>
                        </div>
                        <div class="price-row" id="subtotalRow">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span id="total">$0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                        Proceed to Payment
                    </button>
                </form>
            </div>
            
            <script>
                let selectedSchedulePrice = 0;
                const processingFee = <?php echo $processing_fee; ?>;
                
                function selectSchedule(scheduleId, price, element) {
                    // Remove selected class from all cards
                    document.querySelectorAll('.schedule-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked card
                    element.classList.add('selected');
                    
                    // Update hidden input
                    selectedSchedulePrice = price;
                    document.getElementById('selectedScheduleId').value = scheduleId;
                    
                    // Show price breakdown
                    document.getElementById('priceBreakdown').style.display = 'block';
                    document.getElementById('perTicketPrice').textContent = '$' + price.toFixed(2);
                    
                    // Enable proceed button
                    document.getElementById('proceedBtn').disabled = false;
                    
                    // Update prices
                    updatePrice();
                }
                
                function updatePrice() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    const subtotal = selectedSchedulePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                document.getElementById('quantity').addEventListener('change', updatePrice);
                
                // Form validation
                document.getElementById('onlineForm').addEventListener('submit', function(e) {
                    if (!document.getElementById('selectedScheduleId').value) {
                        e.preventDefault();
                        alert('Please select a streaming time');
                    }
                });
            </script>
            
        <?php else: ?>
            <!-- Physical Ticket Seat Selection -->
            <div class="seat-selection">
                <h2 style="color: #00ffff; margin-bottom: 20px;">Select Your Seats</h2>
                
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
                
                <div class="screen">SCREEN</div>
                
                <div class="seat-map" id="seatMap">
                    <?php foreach ($seats as $seat): ?>
                        <div class="seat <?php echo $seat['available'] ? 'available' : 'booked'; ?>" 
                             data-seat="<?php echo $seat['number']; ?>"
                             onclick="selectSeat(this, <?php echo $available_seats; ?>)">
                            <?php echo $seat['number']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="selected-info" id="selectedInfo">
                    Selected Seats: <span id="selectedSeatsDisplay">None</span>
                </div>
            </div>
            
            <!-- Purchase Form for Physical Tickets -->
            <div class="purchase-form">
                <form method="POST" id="physicalForm">
                    <?php if (!empty($linked_accounts)): ?>
                        <div class="form-group">
                            <label for="for_user_id">Purchase for:</label>
                            <select name="for_user_id" id="for_user_id">
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
                    
                    <div class="form-group">
                        <label for="quantity">Number of Tickets</label>
                        <select name="quantity" id="quantity" onchange="updateQuantity()">
                            <?php for ($i = 1; $i <= min(10, $available_seats); $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <input type="hidden" name="seats" id="selectedSeatsInput">
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Price per ticket:</span>
                            <span>$<?php echo number_format($base_price, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Processing Fee (₱150 each):</span>
                            <span>$<?php echo number_format($processing_fee, 2); ?></span>
                        </div>
                        <div class="price-row" id="subtotalRow">
                            <span>Subtotal:</span>
                            <span id="subtotal">$<?php echo number_format($base_price, 2); ?></span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span id="total">$<?php echo number_format($base_price + $processing_fee, 2); ?></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="proceed-btn" id="proceedBtn" disabled>
                        Proceed to Payment
                    </button>
                </form>
            </div>
            
            <script>
                let selectedSeats = [];
                const basePrice = <?php echo $base_price; ?>;
                const processingFee = <?php echo $processing_fee; ?>;
                
                function selectSeat(seat, maxSeats) {
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
                    updateProceedButton();
                    updateTotal();
                }
                
                function updateSelectedSeats() {
                    const display = document.getElementById('selectedSeatsDisplay');
                    const input = document.getElementById('selectedSeatsInput');
                    
                    if (selectedSeats.length) {
                        display.textContent = selectedSeats.join(', ');
                        input.value = selectedSeats.join(',');
                    } else {
                        display.textContent = 'None';
                        input.value = '';
                    }
                }
                
                function updateProceedButton() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    document.getElementById('proceedBtn').disabled = selectedSeats.length !== quantity;
                }
                
                function updateQuantity() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    
                    // Remove extra selections
                    while (selectedSeats.length > quantity) {
                        const removed = selectedSeats.pop();
                        const seat = document.querySelector(`[data-seat="${removed}"]`);
                        if (seat) seat.classList.remove('selected');
                    }
                    
                    updateSelectedSeats();
                    updateProceedButton();
                    
                    // Update price
                    const subtotal = basePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                function updateTotal() {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    const subtotal = basePrice * quantity;
                    const totalFee = processingFee * quantity;
                    const total = subtotal + totalFee;
                    
                    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
                    document.getElementById('total').textContent = '$' + total.toFixed(2);
                }
                
                // Form validation
                document.getElementById('physicalForm').addEventListener('submit', function(e) {
                    const quantity = parseInt(document.getElementById('quantity').value);
                    if (selectedSeats.length !== quantity) {
                        e.preventDefault();
                        alert('Please select ' + quantity + ' seat(s)');
                    }
                });
            </script>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>