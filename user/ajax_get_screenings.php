<?php
// user/ajax_get_screenings.php
require_once '../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$movie_id = $_GET['movie_id'] ?? 0;
$cinema_id = $_GET['cinema_id'] ?? 0;

if (!$movie_id || !$cinema_id) {
    echo json_encode([]);
    exit;
}

$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT s.*, c.name as cinema_name, c.location 
    FROM screenings s
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.movie_id = ? AND s.cinema_id = ? AND s.show_date >= CURDATE() AND s.status != 'expired'
    ORDER BY s.show_date, s.show_time
");
$stmt->execute([$movie_id, $cinema_id]);
$screenings = $stmt->fetchAll();

echo json_encode($screenings);
?>