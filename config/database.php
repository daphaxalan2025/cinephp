<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinema_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/cinema');

// Add upload paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('POSTER_PATH', UPLOAD_PATH . 'posters/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');
define('QR_PATH', UPLOAD_PATH . 'qrcodes/');
define('TICKET_PATH', UPLOAD_PATH . 'tickets/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>