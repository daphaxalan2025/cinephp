<?php
// database/setup.php - SINGLE SOURCE OF TRUTH
// WARNING: This will DELETE all existing data!
// Run this ONLY for fresh installations or when resetting database
// REMOVED: payment_status from tickets, proof_of_payment from payments
// REMOVED: reference_number, paymongo_payment_id
// REMOVED: parent_id and cinema_id from users table

$host = 'localhost';
$dbname = 'cinema_db';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup - CinemaTicket</title>
    <style>
        body { background: #0a0a0a; color: #fff; font-family: monospace; padding: 20px; }
        .success { color: #44ff44; }
        .error { color: #ff4444; }
        .info { color: #44ffff; }
        .warning { color: #ffff44; }
        .box { border: 1px solid #333; padding: 20px; margin: 20px 0; border-radius: 8px; }
        pre { background: #111; padding: 15px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🎬 CinemaTicket Database Setup</h1>
<div class='box'>
<p class='warning'>⚠️ WARNING: This will DELETE all existing data!</p>
<p>Type 'RESET' to confirm, or close this page to cancel.</p>
<form method='post'>
    <input type='text' name='confirm' placeholder='Type RESET to continue' style='padding: 10px; width: 200px;'>
    <button type='submit' style='padding: 10px 20px; background: #e50914; color: white; border: none; cursor: pointer;'>Reset Database</button>
</form>
";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'RESET') {
    
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "<p class='success'>✅ Database '$dbname' ready</p>";
    
    $pdo->exec("USE `$dbname`");
    
    // Disable foreign key checks for clean drop
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Get and drop all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($tables)) {
        $pdo->exec("DROP TABLE IF EXISTS " . implode(',', $tables));
        echo "<p class='info'>🗑️ Dropped " . count($tables) . " existing tables</p>";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // ============ CREATE TABLES ============
    
    // 1. USERS table - NO parent_id, NO cinema_id
    $pdo->exec("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            birthdate DATE NOT NULL,
            account_type VARCHAR(20) DEFAULT 'user',
            gender VARCHAR(20),
            country VARCHAR(50),
            phone VARCHAR(20),
            profile_pic VARCHAR(255),
            theme_preference VARCHAR(20) DEFAULT 'dark',
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Users table created (no parent_id, no cinema_id)</p>";
    
    // 2. CINEMAS table
    $pdo->exec("
        CREATE TABLE cinemas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            location VARCHAR(500) NOT NULL,
            total_screens INT DEFAULT 1,
            seats_per_screen INT DEFAULT 40,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Cinemas table created</p>";
    
    // 3. MOVIES table
    $pdo->exec("
        CREATE TABLE movies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            duration INT DEFAULT 120,
            rating VARCHAR(10) DEFAULT 'PG',
            genre VARCHAR(100),
            poster VARCHAR(255),
            trailer_url VARCHAR(500),
            streaming_url VARCHAR(500),
            release_date DATE,
            price DECIMAL(10,2) DEFAULT 12.50,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Movies table created</p>";
    
    // 4. SCREENINGS table
    $pdo->exec("
        CREATE TABLE screenings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            movie_id INT NOT NULL,
            cinema_id INT NOT NULL,
            screen_number INT DEFAULT 1,
            show_date DATE NOT NULL,
            show_time TIME NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            available_seats INT DEFAULT 40,
            status VARCHAR(20) DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            FOREIGN KEY (cinema_id) REFERENCES cinemas(id) ON DELETE CASCADE,
            INDEX idx_show_date (show_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Screenings table created</p>";
    
    // 5. ONLINE_SCHEDULE table
    $pdo->exec("
        CREATE TABLE online_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            movie_id INT NOT NULL,
            show_date DATE NOT NULL,
            show_time TIME NOT NULL,
            max_viewers INT DEFAULT 100,
            current_viewers INT DEFAULT 0,
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            UNIQUE KEY unique_online_show (movie_id, show_date, show_time),
            INDEX idx_online_date (show_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Online Schedule table created</p>";
    
    // 6. TICKETS table - CLEAN
    $pdo->exec("
        CREATE TABLE tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_code VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            screening_id INT NULL,
            online_schedule_id INT NULL,
            ticket_type VARCHAR(20) NOT NULL,
            quantity INT DEFAULT 1,
            total_price DECIMAL(10,2) NOT NULL,
            seat_numbers TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            week_expiry DATE NULL,
            used_at TIMESTAMP NULL,
            verified_by INT NULL,
            cancelled_at TIMESTAMP NULL,
            cancelled_by INT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (screening_id) REFERENCES screenings(id) ON DELETE SET NULL,
            FOREIGN KEY (online_schedule_id) REFERENCES online_schedule(id) ON DELETE SET NULL,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_user_screening (user_id, screening_id),
            INDEX idx_code (ticket_code),
            INDEX idx_status (status),
            INDEX idx_week_expiry (week_expiry)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Tickets table created</p>";
    
    // 7. PAYMENTS table - CLEAN
    $pdo->exec("
        CREATE TABLE payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ticket_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            transaction_id VARCHAR(200) UNIQUE,
            paymongo_checkout_id VARCHAR(100) NULL,
            description VARCHAR(255) NULL,
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
            INDEX idx_transaction (transaction_id),
            INDEX idx_checkout (paymongo_checkout_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Payments table created</p>";
    
    // 8. USER_PROFILES table
    $pdo->exec("
        CREATE TABLE user_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            profile_name VARCHAR(100) NOT NULL,
            profile_type ENUM('adult', 'teen', 'kid') DEFAULT 'adult',
            avatar VARCHAR(255) NULL,
            pin VARCHAR(255) NULL,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_profiles (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ User Profiles table created</p>";
    
    // 9. FAVORITES table
    $pdo->exec("
        CREATE TABLE favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            UNIQUE KEY unique_favorite (user_id, movie_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Favorites table created</p>";
    
    // 10. WATCH_HISTORY table
    $pdo->exec("
        CREATE TABLE watch_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed TINYINT DEFAULT 0,
            watch_duration INT DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            INDEX idx_user_watched (user_id, watched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Watch History table created</p>";
    
    // 11. PAYMONGO_EVENTS table
    $pdo->exec("
        CREATE TABLE paymongo_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(100) UNIQUE NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50),
            resource_id VARCHAR(100),
            data JSON,
            received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_id (event_id),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ PayMongo Events table created</p>";
    
    // ============ SAMPLE DATA ============
    echo "<h3>📝 Inserting sample data...</h3>";
    
    // Sample cinemas
    $pdo->exec("
        INSERT INTO cinemas (name, location, total_screens, seats_per_screen) VALUES 
        ('SM North EDSA', 'Quezon City', 4, 40),
        ('SM Mall of Asia', 'Pasay City', 6, 50),
        ('Ayala Malls Cinemas', 'Makati City', 3, 35),
        ('Gateway Cinema', 'Quezon City', 5, 45),
        ('Robinsons Galleria', 'Pasig City', 4, 40)
    ");
    echo "<p class='success'>✅ Sample cinemas added</p>";
    
    // Sample movies
    $movies = [
        ['Dune: Part Two', 'Paul Atreides unites with Chani and the Fremen while seeking revenge.', 166, 'PG-13', 'Sci-Fi', '', 'https://www.youtube.com/embed/Way9Dexny3w', '', '2024-03-01', 15.50],
        ['Kung Fu Panda 4', 'Po must train a new warrior to take his place as Dragon Warrior.', 94, 'PG', 'Animation', '', 'https://www.youtube.com/embed/_inKs4eeHiI', '', '2024-03-08', 12.50],
        ['Godzilla x Kong', 'The Titans clash in an epic battle for supremacy.', 115, 'PG-13', 'Action', '', 'https://www.youtube.com/embed/qqrpMRDuPfc', '', '2024-03-15', 14.00],
        ['Inside Out 2', 'Return to Riley\'s mind for new emotional adventures.', 100, 'PG', 'Animation', '', 'https://www.youtube.com/embed/LEjhY15eCx0', '', '2024-06-14', 12.00],
        ['Deadpool 3', 'The Merc with a Mouth returns for another wild adventure.', 120, 'R', 'Action', '', 'https://www.youtube.com/embed/XYZ123', '', '2024-07-26', 16.00]
    ];
    $stmt = $pdo->prepare("INSERT INTO movies (title, description, duration, rating, genre, poster, trailer_url, streaming_url, release_date, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($movies as $movie) { $stmt->execute($movie); }
    echo "<p class='success'>✅ Sample movies added</p>";
    
    // Create users - NO cinema_id for staff
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, email, password_hash, first_name, last_name, birthdate, account_type, gender, country, phone, is_active) VALUES ('admin', 'admin@cinema.com', '$admin_pass', 'Admin', 'User', '1990-01-01', 'admin', 'male', 'PH', '+639123456789', 1)");
    echo "<p class='success'>✅ Admin user: admin/admin123</p>";
    
    $staff_pass = password_hash('staff123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, email, password_hash, first_name, last_name, birthdate, account_type, gender, country, phone, is_active) VALUES ('staff', 'staff@cinema.com', '$staff_pass', 'Staff', 'User', '1995-01-01', 'staff', 'male', 'PH', '+639123456788', 1)");
    echo "<p class='success'>✅ Staff user: staff/staff123</p>";
    
    $adult_pass = password_hash('password123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, email, password_hash, first_name, last_name, birthdate, account_type, gender, country, phone, is_active) VALUES ('adult', 'adult@email.com', '$adult_pass', 'John', 'Doe', '1990-01-01', 'adult', 'male', 'PH', '+639123456787', 1)");
    echo "<p class='success'>✅ Adult user: adult/password123</p>";
    
    echo "<h2 class='success'>✅ DATABASE SETUP COMPLETE!</h2>";
    echo "<pre>
╔═══════════════════════════════════════════════════════════════════════════════╗
║                              LOGIN CREDENTIALS                                ║
╠═══════════════════════════════════════════════════════════════════════════════╣
║  👑 Admin:   admin / admin123                                                 ║
║  👔 Staff:   staff / staff123                                                 ║
║  👨 Adult:   adult / password123                                              ║
╚═══════════════════════════════════════════════════════════════════════════════╝
    </pre>";
    echo "<p><a href='/cinephp/auth/login.php' style='color: #44ff44; font-size: 1.2rem;'>🎬 Go to Login →</a></p>";
    
} catch(PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
} else {
    echo "<p class='info'>💡 Type 'RESET' and click the button to reset the database.</p>";
}

echo "</div></body></html>";
?>