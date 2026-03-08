<?php
// test_data.php
require_once 'includes/functions.php';

$pdo = getDB();

echo "<h1>DATABASE DIAGNOSTIC</h1>";

// Check movies
$movies = $pdo->query("SELECT * FROM movies")->fetchAll();
echo "<h2>Movies (" . count($movies) . ")</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Title</th></tr>";
foreach ($movies as $movie) {
    echo "<tr><td>{$movie['id']}</td><td>{$movie['title']}</td></tr>";
}
echo "</table>";

// Check cinemas
$cinemas = $pdo->query("SELECT * FROM cinemas")->fetchAll();
echo "<h2>Cinemas (" . count($cinemas) . ")</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Location</th></tr>";
foreach ($cinemas as $cinema) {
    echo "<tr><td>{$cinema['id']}</td><td>{$cinema['name']}</td><td>{$cinema['location']}</td></tr>";
}
echo "</table>";

// Check screenings
$screenings = $pdo->query("
    SELECT s.*, m.title as movie_title, c.name as cinema_name 
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    ORDER BY s.show_date
")->fetchAll();
echo "<h2>Screenings (" . count($screenings) . ")</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Movie</th><th>Cinema</th><th>Date</th><th>Time</th><th>Seats</th></tr>";
foreach ($screenings as $s) {
    echo "<tr>";
    echo "<td>{$s['id']}</td>";
    echo "<td>{$s['movie_title']}</td>";
    echo "<td>{$s['cinema_name']}</td>";
    echo "<td>{$s['show_date']}</td>";
    echo "<td>{$s['show_time']}</td>";
    echo "<td>{$s['available_seats']}</td>";
    echo "</tr>";
}
echo "</table>";

// If no screenings, add some
if (empty($screenings) && !empty($movies) && !empty($cinemas)) {
    echo "<h3 style='color:red'>No screenings found! Add some:</h3>";
    echo '<form method="post">';
    echo '<button type="submit" name="add_screenings">Add Sample Screenings</button>';
    echo '</form>';
    
    if (isset($_POST['add_screenings'])) {
        $movie = $movies[0];
        $cinema = $cinemas[0];
        
        // Add 3 screenings for tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $times = ['10:00:00', '14:00:00', '18:00:00'];
        
        foreach ($times as $i => $time) {
            $stmt = $pdo->prepare("
                INSERT INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats)
                VALUES (?, ?, ?, ?, ?, 12.50, 40)
            ");
            $stmt->execute([$movie['id'], $cinema['id'], $i+1, $tomorrow, $time]);
        }
        
        echo "<p style='color:green'>Screenings added! Refresh page.</p>";
        echo '<meta http-equiv="refresh" content="2">';
    }
}
?>