<?php
// database/setup.php - RUN THIS FIRST
$host = 'localhost';
$dbname = 'cinema_db';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { background: #000; color: #fff; font-family: monospace; padding: 20px; }
        .success { color: #00ffff; text-shadow: 0 0 5px #00ffff; }
        .error { color: #ff0000; }
        pre { background: #111; padding: 10px; border: 1px solid #00ffff; }
    </style>
</head>
<body>
<h1>🎬 CinemaTicket Database Setup</h1>";

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "<p class='success'>✅ Database '$dbname' created</p>";
    
    // Select database
    $pdo->exec("USE `$dbname`");
    
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
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
            parent_id INT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Users table created</p>";
    
    // Create cinemas table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cinemas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            location VARCHAR(500) NOT NULL,
            total_screens INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Cinemas table created</p>";
    
    // Create movies table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movies (
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
    
    // Create screenings table (for cinema)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS screenings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            movie_id INT NOT NULL,
            cinema_id INT NOT NULL,
            screen_number INT DEFAULT 1,
            show_date DATE NOT NULL,
            show_time TIME NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            available_seats INT DEFAULT 40,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
            FOREIGN KEY (cinema_id) REFERENCES cinemas(id) ON DELETE CASCADE,
            INDEX idx_datetime (show_date, show_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Screenings table created</p>";
    
    // Create online_schedule table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS online_schedule (
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
            INDEX idx_online_date (show_date, show_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Online Schedule table created</p>";
    
    // Create tickets table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_code VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            screening_id INT NULL,
            online_schedule_id INT NULL,
            ticket_type VARCHAR(20) NOT NULL,
            quantity INT DEFAULT 1,
            total_price DECIMAL(10,2) NOT NULL,
            seat_numbers TEXT,
            payment_id INT NULL,
            payment_status VARCHAR(20) DEFAULT 'pending',
            status VARCHAR(20) DEFAULT 'pending',
            purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL,
            streaming_views INT DEFAULT 0,
            max_streaming_views INT DEFAULT 3,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (screening_id) REFERENCES screenings(id) ON DELETE SET NULL,
            FOREIGN KEY (online_schedule_id) REFERENCES online_schedule(id) ON DELETE SET NULL,
            INDEX idx_code (ticket_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Tickets table created</p>";
    
    // Create payments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            ticket_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_status VARCHAR(20) DEFAULT 'pending',
            transaction_id VARCHAR(200) UNIQUE,
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Payments table created</p>";
    
    // Create favorites table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favorites (
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
    
    // Create watch_history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS watch_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed BOOLEAN DEFAULT FALSE,
            watch_duration INT DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Watch History table created</p>";
    
    // Create link_accounts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS link_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            child_id INT NOT NULL,
            relationship VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (child_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_link (parent_id, child_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p class='success'>✅ Link Accounts table created</p>";
    
    // Insert sample cinema
    $pdo->exec("
        INSERT IGNORE INTO cinemas (name, location, total_screens) VALUES 
        ('SM North EDSA', 'Quezon City', 4),
        ('SM Mall of Asia', 'Pasay City', 6),
        ('Ayala Malls Cinemas', 'Makati City', 3)
    ");
    echo "<p class='success'>✅ Sample cinemas added</p>";
    
    // Insert sample movies
    $movies = [
        ['Dune: Part Two', 'Paul Atreides unites with Chani and the Fremen while seeking revenge.', 166, 'PG-13', 'Sci-Fi', 'dune.jpg', 'https://www.youtube.com/embed/Way9Dexny3w', 'https://example.com/stream/dune', '2024-03-01', 15.50],
        ['Kung Fu Panda 4', 'Po must train a new warrior to take his place as Dragon Warrior.', 94, 'PG', 'Animation', 'kfp4.jpg', 'https://www.youtube.com/embed/_inKs4eeHiI', 'https://example.com/stream/kfp4', '2024-03-08', 12.50],
        ['Godzilla x Kong', 'The Titans clash in an epic battle for supremacy.', 115, 'PG-13', 'Action', 'godzilla.jpg', 'https://www.youtube.com/embed/qqrpMRDuPfc', 'https://example.com/stream/gxk', '2024-03-15', 14.00]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO movies (title, description, duration, rating, genre, poster, trailer_url, streaming_url, release_date, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($movies as $movie) {
        $stmt->execute($movie);
    }
    echo "<p class='success'>✅ Sample movies added</p>";
    
    // Insert sample screenings (multiple times per screen)
    $cinemas = $pdo->query("SELECT id FROM cinemas")->fetchAll();
    $movies = $pdo->query("SELECT id FROM movies")->fetchAll();
    
    if (!empty($cinemas) && !empty($movies)) {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $dayAfter = date('Y-m-d', strtotime('+2 days'));
        
        // For each cinema, add screenings for first movie
        $times = ['10:00:00', '13:00:00', '16:00:00', '19:00:00', '22:00:00'];
        
        foreach ($cinemas as $cinema) {
            for ($screen = 1; $screen <= 2; $screen++) {
                foreach ($times as $i => $time) {
                    if ($i < 3) { // Add 3 screenings per screen
                        $stmt = $pdo->prepare("INSERT IGNORE INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$movies[0]['id'], $cinema['id'], $screen, $tomorrow, $time, 15.50, 40]);
                    }
                }
            }
        }
        echo "<p class='success'>✅ Sample screenings added (multiple per screen)</p>";
    }
    
    // Insert sample online schedule
    if (!empty($movies)) {
        $times = ['10:00:00', '14:00:00', '18:00:00', '22:00:00'];
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        foreach ($times as $time) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO online_schedule (movie_id, show_date, show_time, price, max_viewers) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$movies[0]['id'], $tomorrow, $time, 12.00, 100]);
        }
        echo "<p class='success'>✅ Sample online schedule added</p>";
    }
    
    // Create admin user (admin/admin123) - check if exists first
    $check = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
    if (!$check) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (username, email, password_hash, first_name, last_name, birthdate, account_type, gender, country, phone) VALUES 
            ('admin', 'admin@cinema.com', '$admin_pass', 'Admin', 'User', '1990-01-01', 'admin', 'male', 'PH', '+639123456789')
        ");
        echo "<p class='success'>✅ Admin user created (admin/admin123)</p>";
    }
    
    // Create test user (user/password123) - check if exists first
    $check = $pdo->query("SELECT id FROM users WHERE username = 'john'")->fetch();
    if (!$check) {
        $user_pass = password_hash('password123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (username, email, password_hash, first_name, last_name, birthdate, account_type, gender, country, phone) VALUES 
            ('john', 'john@email.com', '$user_pass', 'John', 'Doe', '2000-01-01', 'adult', 'male', 'PH', '+639123456788')
        ");
        echo "<p class='success'>✅ Test user created (john/password123)</p>";
    }
    
    echo "<h2 class='success'>✅ DATABASE SETUP COMPLETE!</h2>";
    echo "<pre>
╔════════════════════════════════════════════════════════════╗
║                    Login Credentials                       ║
╠════════════════════════════════════════════════════════════╣
║  Admin:  admin / admin123                                   ║
║  User:   john / password123                                 ║
║                                                            ║
║  Cinemas: SM North EDSA, SM MOA, Ayala Malls              ║
║  Movies: Dune 2, Kung Fu Panda 4, Godzilla x Kong         ║
║  Screenings: Multiple times per screen (max 5 per day)    ║
║  Online Schedule: 4 time slots available                   ║
╚════════════════════════════════════════════════════════════╝
    </pre>";
    echo "<p><a href='/cinema/auth/login.php' style='color: #00ffff;'>Go to Login →</a></p>";
    
} catch(PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your MySQL connection settings.</p>";
}
echo "</body></html>";
?>