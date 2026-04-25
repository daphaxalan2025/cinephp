<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinema_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/cinephp');

// Add upload paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('POSTER_PATH', UPLOAD_PATH . 'posters/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');
define('QR_PATH', UPLOAD_PATH . 'qrcodes/');
define('TICKET_PATH', UPLOAD_PATH . 'tickets/');

// ============ PAYMONGO API CONFIGURATION (TEST MODE) ============
define('PAYMONGO_SECRET_KEY', 'sk_test_CuXgiJJHcBEX24FTGBE6KxPd');
define('PAYMONGO_PUBLIC_KEY', 'pk_test_PQdr5QWdEXHTJLcs6RXyYjM7');
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>