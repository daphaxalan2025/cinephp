<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

// Database connection
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
                die("<h2>Database not found!</h2>
                     <p><a href='/cinema/database/setup.php' style='background: #00ffff; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Click here to run setup</a></p>");
            } else {
                die("Database error: " . $e->getMessage());
            }
        }
    }
    return $pdo;
}

// User functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function loginUser($username_email, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username_email, $username_email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['account_type'] = $user['account_type'];
        
        // Update last login
        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        
        return $user;
    }
    return false;
}

function logoutUser() {
    $_SESSION = array();
    session_destroy();
    return true;
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Access control
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('Please login first', 'error');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['account_type'] != 'admin') {
        setFlash('Access denied', 'error');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireStaff() {
    requireLogin();
    if (!in_array($_SESSION['account_type'], ['staff', 'admin'])) {
        setFlash('Access denied', 'error');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Flash messages
function setFlash($message, $type = 'info') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Validation functions
function isUsernameExists($username, $exclude_id = null) {
    $pdo = getDB();
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? true : false;
}

function isEmailExists($email, $exclude_id = null) {
    $pdo = getDB();
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ? true : false;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function isValidPassword($password) {
    return strlen($password) >= 6;
}

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

// ============ TICKET VALIDATION ============
function validateSeatSelection($screening_id, $selected_seats, $quantity) {
    $pdo = getDB();
    
    // Check if seats are available
    $booked_seats = [];
    foreach ($selected_seats as $seat) {
        $stmt = $pdo->prepare("
            SELECT id FROM tickets 
            WHERE screening_id = ? AND status IN ('pending', 'paid')
            AND FIND_IN_SET(?, REPLACE(seat_numbers, ',', ',')) > 0
        ");
        $stmt->execute([$screening_id, $seat]);
        if ($stmt->fetch()) {
            $booked_seats[] = $seat;
        }
    }
    
    if (!empty($booked_seats)) {
        return ['valid' => false, 'message' => 'Seats already booked: ' . implode(', ', $booked_seats)];
    }
    
    // Check screening availability
    $stmt = $pdo->prepare("SELECT available_seats FROM screenings WHERE id = ?");
    $stmt->execute([$screening_id]);
    $screening = $stmt->fetch();
    
    if ($screening['available_seats'] < $quantity) {
        return ['valid' => false, 'message' => 'Not enough seats available'];
    }
    
    return ['valid' => true];
}

function generateTicketCode() {
    return 'TIX-' . strtoupper(uniqid()) . '-' . date('Ymd');
}

// ============ SEAT MAP GENERATOR ============
function generateSeatMap($screening_id) {
    $pdo = getDB();
    
    try {
        // Get all booked seats
        $stmt = $pdo->prepare("
            SELECT seat_numbers FROM tickets 
            WHERE screening_id = ? AND status IN ('pending', 'paid')
        ");
        $stmt->execute([$screening_id]);
        
        $booked = [];
        while ($row = $stmt->fetch()) {
            if (!empty($row['seat_numbers'])) {
                $seats = explode(',', $row['seat_numbers']);
                $booked = array_merge($booked, $seats);
            }
        }
    } catch (PDOException $e) {
        $booked = [];
    }
    
    // Generate 40 seats (8 columns x 5 rows)
    $rows = ['A', 'B', 'C', 'D', 'E'];
    $seats = [];
    
    foreach ($rows as $row) {
        for ($i = 1; $i <= 8; $i++) {
            $seat = $row . $i;
            $seats[] = [
                'number' => $seat,
                'available' => !in_array($seat, $booked)
            ];
        }
    }
    
    return $seats;
}

// ============ MOVIE FILTERING BY AGE ============
function getMoviesByAgeGroup($account_type) {
    $pdo = getDB();
    
    $rating_filter = '';
    if ($account_type == 'kid') {
        $rating_filter = "WHERE rating IN ('G', 'PG')";
    } elseif ($account_type == 'teen') {
        $rating_filter = "WHERE rating IN ('G', 'PG', 'PG-13')";
    }
    
    $sql = "SELECT * FROM movies $rating_filter ORDER BY created_at DESC";
    return $pdo->query($sql)->fetchAll();
}

// ============ PROFILE PICTURE UPLOAD ============
function uploadProfilePicture($file, $user_id) {
    // Check if UPLOAD_PATH is defined
    if (!defined('UPLOAD_PATH')) {
        error_log("UPLOAD_PATH constant is not defined");
        return ['success' => false, 'error' => 'Server configuration error'];
    }
    
    $target_dir = UPLOAD_PATH . 'profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            error_log("Failed to create directory: " . $target_dir);
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($target_dir)) {
        error_log("Directory not writable: " . $target_dir);
        return ['success' => false, 'error' => 'Upload directory is not writable'];
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 2MB.'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error_message = $upload_errors[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'error' => $error_message];
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Set proper permissions
        chmod($target_file, 0644);
        return ['success' => true, 'filename' => $filename];
    }
    
    error_log("Failed to move uploaded file. Source: " . $file['tmp_name'] . " Destination: " . $target_file);
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// ============ POSTER UPLOAD ============
function uploadPoster($file) {
    $target_dir = UPLOAD_PATH . 'posters/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'poster_' . time() . '_' . uniqid() . '.' . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        chmod($target_file, 0644);
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}
?>