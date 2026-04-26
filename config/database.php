<?php
// Load Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env from ROOT folder
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Database config
define('DB_HOST', 'localhost');
define('DB_NAME', 'cinema_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/cinephp');

// Upload paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('POSTER_PATH', UPLOAD_PATH . 'posters/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');
define('QR_PATH', UPLOAD_PATH . 'qrcodes/');
define('TICKET_PATH', UPLOAD_PATH . 'tickets/');

// PayMongo (from .env)
define('PAYMONGO_SECRET_KEY', $_ENV['PAYMONGO_SECRET_KEY']);
define('PAYMONGO_PUBLIC_KEY', $_ENV['PAYMONGO_PUBLIC_KEY']);
define('PAYMONGO_API_URL', 'https://api.paymongo.com/v1');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug (optional remove later)
error_reporting(E_ALL);
ini_set('display_errors', 1);