<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// includes/functions.php - UPDATED: ₱ currency, profile system, date-based online ticket expiry, flat fee
// FIXED: Removed ltrim() warning, simplified getAvatarImage(), added constant verification
// ADDED: getFlash() function verified, generateSeatMap() existing, all good
require_once __DIR__ . '/../config/database.php';

// Verify database constants are loaded
if (!defined('DB_HOST')) {
    die('Database configuration not found. Please check config/database.php');
}
if (!defined('DB_NAME')) {
    die('Database name not defined. Please check config/database.php');
}

function autoExpireOnlineTickets() {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        UPDATE tickets 
        SET status = 'used' 
        WHERE ticket_type = 'online' 
        AND week_expiry IS NOT NULL 
        AND week_expiry < CURDATE() 
        AND status = 'paid'
    ");
    $stmt->execute();
    return $stmt->rowCount();
}

// Run it immediately when this file is included
autoExpireOnlineTickets();

// ============ CURRENCY AND FEE CONSTANTS ============
define('CURRENCY_SYMBOL', '₱');
define('TICKET_FEE', 50);      // ₱50 flat service fee per ticket

// Format currency function
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
        } catch (PDOException $e) {
            if ($e->getCode() == 1049) {
                die("<h2>Database '" . DB_NAME . "' not found!</h2>
                     <p>Please check your database configuration.</p>");
            } else {
                die("Database error: " . $e->getMessage());
            }
        }
    }
    return $pdo;
}

// ============ AUTO-ARCHIVE EXPIRED SCREENINGS ============
function autoArchiveExpiredScreenings() {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE screenings SET status = 'expired' WHERE CONCAT(show_date, ' ', show_time) < NOW() AND status != 'expired'");
    $stmt->execute();
    $screening_count = $stmt->rowCount();
    
    $stmt = $pdo->prepare("UPDATE online_schedule SET status = 'expired' WHERE show_date < CURDATE() AND status = 'scheduled'");
    $stmt->execute();
    $online_count = $stmt->rowCount();
    
    return ['screenings' => $screening_count, 'online' => $online_count];
}

// ============ TICKET VALIDATION ============
function validateTicketByCode($ticket_code, $staff_cinema_id = null) {
    $pdo = getDB();
    $sql = "
        SELECT t.*, 
               u.first_name, u.last_name, u.email,
               t.ticket_type,
               CASE 
                   WHEN t.ticket_type = 'cinema' THEN s.show_date
                   WHEN t.ticket_type = 'online' THEN os.show_date
               END as show_date,
               CASE 
                   WHEN t.ticket_type = 'cinema' THEN s.show_time
                   WHEN t.ticket_type = 'online' THEN os.show_time
               END as show_time,
               s.screen_number, 
               s.status as screening_status,
               CASE 
                   WHEN t.ticket_type = 'cinema' THEN m.title
                   WHEN t.ticket_type = 'online' THEN om.title
               END as title,
               m.duration,
               c.name as cinema_name, 
               c.id as cinema_id,
               os.max_viewers, 
               os.current_viewers,
               t.week_expiry
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN screenings s ON t.screening_id = s.id
        LEFT JOIN movies m ON s.movie_id = m.id
        LEFT JOIN cinemas c ON s.cinema_id = c.id
        LEFT JOIN online_schedule os ON t.online_schedule_id = os.id
        LEFT JOIN movies om ON os.movie_id = om.id
        WHERE t.ticket_code = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_code]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) return ['valid' => false, 'reason' => 'not_found', 'ticket' => null];
    
    if ($staff_cinema_id && $ticket['ticket_type'] == 'cinema' && $ticket['cinema_id'] && $ticket['cinema_id'] != $staff_cinema_id) {
        return ['valid' => false, 'reason' => 'wrong_cinema', 'ticket' => $ticket];
    }
    
    if ($ticket['ticket_type'] == 'cinema' && $ticket['show_date']) {
        $screening_datetime = strtotime($ticket['show_date'] . ' ' . $ticket['show_time']);
        if ($screening_datetime < time()) {
            return ['valid' => false, 'reason' => 'expired', 'ticket' => $ticket];
        }
    }
    
    if ($ticket['ticket_type'] == 'online') {
        if ($ticket['week_expiry'] && date('Y-m-d') > $ticket['week_expiry']) {
            $updateStmt = $pdo->prepare("UPDATE tickets SET status = 'used' WHERE id = ?");
            $updateStmt->execute([$ticket['id']]);
            return ['valid' => false, 'reason' => 'expired', 'ticket' => $ticket];
        }
    }
    
    if ($ticket['status'] == 'used') {
        return ['valid' => false, 'reason' => 'used', 'used_at' => $ticket['used_at'], 'ticket' => $ticket];
    }
    
    if ($ticket['status'] != 'paid') {
        return ['valid' => false, 'reason' => 'not_paid', 'ticket' => $ticket];
    }
    
    return ['valid' => true, 'ticket' => $ticket];
}

function isTicketValid($ticket_id) {
    $pdo = getDB();
    $query = "SELECT t.*, s.show_date, s.show_time, t.week_expiry, t.ticket_type FROM tickets t LEFT JOIN screenings s ON t.screening_id = s.id WHERE t.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    if (!$ticket) return false;
    
    if ($ticket['ticket_type'] == 'cinema' && $ticket['show_date']) {
        if (strtotime($ticket['show_date'] . ' ' . $ticket['show_time']) < time()) return false;
    }
    
    if ($ticket['ticket_type'] == 'online' && $ticket['week_expiry']) {
        if (date('Y-m-d') > $ticket['week_expiry']) return false;
    }
    
    if ($ticket['status'] == 'used') return false;
    if ($ticket['status'] != 'paid') return false;
    return true;
}

function isLoggedIn() { return isset($_SESSION['user_id']); }

function loginUser($username_email, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username_email, $username_email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['account_type'] = $user['account_type'];
        
        unset($_SESSION['profile_id']);
        unset($_SESSION['profile_type']);
        unset($_SESSION['profile_name']);
        
        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        return $user;
    }
    return false;
}

function logoutUser() { $_SESSION = array(); session_destroy(); return true; }

// ============ PROFILE MANAGEMENT FUNCTIONS ============
function getCurrentProfile() {
    if (!isset($_SESSION['profile_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ?");
    $stmt->execute([$_SESSION['profile_id']]);
    return $stmt->fetch();
}

function setActiveProfile($profile_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE id = ? AND user_id = ?");
    $stmt->execute([$profile_id, $_SESSION['user_id']]);
    $profile = $stmt->fetch();
    if ($profile) {
        $_SESSION['profile_id'] = $profile['id'];
        $_SESSION['profile_type'] = $profile['profile_type'];
        $_SESSION['profile_name'] = $profile['profile_name'];
        return $profile;
    }
    return false;
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        return null;
    }
    return $user;
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('Please login first', 'error');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    
    if (!isset($_SESSION['profile_id']) 
        && !in_array($_SESSION['account_type'], ['admin', 'staff'])
        && basename($_SERVER['PHP_SELF']) != 'select_profile.php'
        && basename($_SERVER['PHP_SELF']) != 'manage_profiles.php') {
        header('Location: ' . BASE_URL . '/user/select_profile.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['account_type'] != 'admin') {
        setFlash('Access denied. Admin only.', 'error');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireStaff() {
    requireLogin();
    if (!in_array($_SESSION['account_type'], ['staff', 'admin'])) {
        setFlash('Access denied. Staff only.', 'error');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function setFlash($message, $type = 'info') { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; }

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isUsernameExists($username, $exclude_id = null) {
    $pdo = getDB();
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? true : false;
}

function isEmailExists($email, $exclude_id = null) {
    $pdo = getDB();
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? true : false;
}

function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
function isValidUsername($username) { return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username); }
function isValidPassword($password) { return strlen($password) >= 6; }

function calculateAge($birthdate) {
    $today = new DateTime();
    $diff = $today->diff(new DateTime($birthdate));
    return $diff->y;
}

function getAccountTypeByAge($age) {
    if ($age < 13) return 'kid';
    if ($age < 18) return 'teen';
    return 'adult';
}

// ============ PROFILE PIN VERIFICATION ============
function verifyProfilePin($profile_id, $entered_pin) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT pin FROM user_profiles WHERE id = ?");
    $stmt->execute([$profile_id]);
    $profile = $stmt->fetch();
    
    if (!$profile || empty($profile['pin'])) {
        return true;
    }
    
    return password_verify($entered_pin, $profile['pin']);
}

// ============ GET AVATAR IMAGE - SIMPLIFIED FIXED VERSION ============
function getAvatarImage($avatar_filename) {
    // Return null if no filename provided
    if (empty($avatar_filename)) {
        return null;
    }
    
    // Build the full file path (relative to web root)
    $avatar_path = '../uploads/avatars/' . $avatar_filename;
    
    // Check if file exists using absolute path
    $full_path = __DIR__ . '/../uploads/avatars/' . $avatar_filename;
    
    if (file_exists($full_path)) {
        return $avatar_path;
    }
    
    // Try alternative path
    $alt_full_path = __DIR__ . '/uploads/avatars/' . $avatar_filename;
    if (file_exists($alt_full_path)) {
        return 'uploads/avatars/' . $avatar_filename;
    }
    
    // If all fail, return null
    return null;
}

function validateSeatSelection($screening_id, $selected_seats, $quantity) {
    $pdo = getDB();
    $booked_seats = [];
    foreach ($selected_seats as $seat) {
        $stmt = $pdo->prepare("SELECT id FROM tickets WHERE screening_id = ? AND status IN ('pending', 'paid') AND FIND_IN_SET(?, REPLACE(seat_numbers, ',', ',')) > 0");
        $stmt->execute([$screening_id, $seat]);
        if ($stmt->fetch()) $booked_seats[] = $seat;
    }
    if (!empty($booked_seats)) return ['valid' => false, 'message' => 'Seats already booked: ' . implode(', ', $booked_seats)];
    $stmt = $pdo->prepare("SELECT available_seats FROM screenings WHERE id = ? AND status != 'expired' AND CONCAT(show_date, ' ', show_time) >= NOW()");
    $stmt->execute([$screening_id]);
    $screening = $stmt->fetch();
    if (!$screening) return ['valid' => false, 'message' => 'Screening not found or expired'];
    if ($screening['available_seats'] < $quantity) return ['valid' => false, 'message' => 'Not enough seats available'];
    return ['valid' => true];
}

function generateTicketCode() { return 'TIX-' . strtoupper(uniqid()) . '-' . date('Ymd'); }

function generateSeatMap($screening_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT seat_numbers FROM tickets WHERE screening_id = ? AND status IN ('pending', 'paid')");
    $stmt->execute([$screening_id]);
    $booked = [];
    while ($row = $stmt->fetch()) {
        if (!empty($row['seat_numbers']) && $row['seat_numbers'] != 'N/A') {
            $booked = array_merge($booked, explode(',', $row['seat_numbers']));
        }
    }
    $rows = ['A', 'B', 'C', 'D', 'E'];
    $seats = [];
    foreach ($rows as $row) {
        for ($i = 1; $i <= 8; $i++) {
            $seat = $row . $i;
            $seats[] = ['number' => $seat, 'available' => !in_array($seat, $booked)];
        }
    }
    return $seats;
}

// ============ GET MOVIES BY AGE GROUP ============
function getMoviesByAgeGroup($account_type) {
    $type = isset($_SESSION['profile_type']) ? $_SESSION['profile_type'] : $account_type;
    $pdo = getDB();
    $rating_filter = '';
    if ($type == 'kid') {
        $rating_filter = "WHERE rating IN ('G', 'PG')";
    } elseif ($type == 'teen') {
        $rating_filter = "WHERE rating IN ('G', 'PG', 'PG-13')";
    }
    return $pdo->query("SELECT * FROM movies $rating_filter ORDER BY created_at DESC")->fetchAll();
}

// ============ CINEMA SEARCH FUNCTIONS ============
function searchCinemas($search_term) {
    $pdo = getDB();
    $search = "%$search_term%";
    $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM screenings WHERE cinema_id = c.id AND CONCAT(show_date, ' ', show_time) >= NOW() AND status != 'expired') as upcoming_screenings FROM cinemas c WHERE c.name LIKE ? OR c.location LIKE ? ORDER BY c.name");
    $stmt->execute([$search, $search]);
    return $stmt->fetchAll();
}

function getScreeningsByCinemaAndMovie($cinema_id, $movie_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT s.*, c.name as cinema_name, c.location FROM screenings s JOIN cinemas c ON s.cinema_id = c.id WHERE s.cinema_id = ? AND s.movie_id = ? AND CONCAT(s.show_date, ' ', s.show_time) >= NOW() AND s.status != 'expired' ORDER BY s.show_date, s.show_time");
    $stmt->execute([$cinema_id, $movie_id]);
    return $stmt->fetchAll();
}

function getUpcomingScreenings($movie_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT s.*, c.name as cinema_name, c.location FROM screenings s JOIN cinemas c ON s.cinema_id = c.id WHERE s.movie_id = ? AND CONCAT(s.show_date, ' ', s.show_time) >= NOW() AND s.status != 'expired' ORDER BY s.show_date, s.show_time");
    $stmt->execute([$movie_id]);
    return $stmt->fetchAll();
}

function getCinemasForMovie($movie_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.location 
        FROM cinemas c
        JOIN screenings s ON c.id = s.cinema_id
        WHERE s.movie_id = ? AND CONCAT(s.show_date, ' ', s.show_time) >= NOW() AND s.status != 'expired'
        ORDER BY c.name
    ");
    $stmt->execute([$movie_id]);
    return $stmt->fetchAll();
}

// ============ GET STAFF ASSIGNED CINEMA ============
function getStaffCinema($staff_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.location, c.total_screens, c.seats_per_screen
        FROM staff_cinemas sc
        JOIN cinemas c ON sc.cinema_id = c.id
        WHERE sc.staff_id = ?
        LIMIT 1
    ");
    $stmt->execute([$staff_id]);
    return $stmt->fetch();
}

function uploadProfilePicture($file, $user_id) {
    if (!defined('UPLOAD_PATH')) return ['success' => false, 'error' => 'Server configuration error'];
    $target_dir = UPLOAD_PATH . 'profiles/';
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    if (!is_writable($target_dir)) return ['success' => false, 'error' => 'Upload directory is not writable'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024;
    if (!in_array($file['type'], $allowed_types)) return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.'];
    if ($file['size'] > $max_size) return ['success' => false, 'error' => 'File too large. Maximum size is 2MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload failed.'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $target_file = $target_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_file)) { chmod($target_file, 0644); return ['success' => true, 'filename' => $filename]; }
    return ['success' => false, 'error' => 'Failed to move uploaded file.'];
}

function uploadPoster($file) {
    $target_dir = UPLOAD_PATH . 'posters/';
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    if (!in_array($file['type'], $allowed_types)) return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.'];
    if ($file['size'] > $max_size) return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'poster_' . time() . '_' . uniqid() . '.' . $ext;
    $target_file = $target_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_file)) { chmod($target_file, 0644); return ['success' => true, 'filename' => $filename]; }
    return ['success' => false, 'error' => 'Failed to move uploaded file.'];
}

// ============ HELPER FUNCTION FOR TICKET FEE ============
function getTicketFee() {
    return TICKET_FEE;
}
?>