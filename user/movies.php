<?php
// user/movies.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Get movies based on account type
$rating_filter = '';
if ($user['account_type'] == 'kid') {
    $rating_filter = "WHERE rating IN ('G', 'PG')";
} elseif ($user['account_type'] == 'teen') {
    $rating_filter = "WHERE rating IN ('G', 'PG', 'PG-13')";
}

$sql = "SELECT * FROM movies $rating_filter ORDER BY created_at DESC";
$movies = $pdo->query($sql)->fetchAll();

// Get screening counts
$screening_counts = [];
foreach ($movies as $movie) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ? AND show_date >= CURDATE()");
    $stmt->execute([$movie['id']]);
    $screening_counts[$movie['id']] = $stmt->fetchColumn();
}

// Check if user has parent (for kids/teens)
$parent = null;
if ($user['parent_id']) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user['parent_id']]);
    $parent = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <a href="movies.php" class="active">Movies</a>
                <a href="favorites.php">Favorites</a>
                <a href="history.php">History</a>
                <a href="purchases.php">My Tickets</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>
                <?php 
                if ($user['account_type'] == 'kid') echo "Movies for Kids";
                elseif ($user['account_type'] == 'teen') echo "Movies for Teens";
                else echo "Now Showing";
                ?>
            </h1>
            <div>
                <span style="color: #888;">Account:</span> 
                <span style="color: #00ffff; text-transform: uppercase;"><?php echo $user['account_type']; ?></span>
            </div>
        </div>
        
        <!-- Parent notification for kids/teens -->
        <?php if ($user['account_type'] == 'kid' || $user['account_type'] == 'teen'): ?>
            <?php if ($parent): ?>
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    👤 Linked to parent: <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                    - Your ticket purchases will be sent to them for approval.
                </div>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-bottom: 20px;">
                    ⚠️ No parent linked. Please ask an adult to create a linked account for you.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (empty($movies)): ?>
            <div class="alert alert-info">No movies available for your age group.</div>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($movies as $movie): ?>
                    <div class="movie-card">
                        <?php if ($movie['poster']): ?>
                            <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                 style="width: 100%; height: 300px; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 300px; background: #000; display: flex; align-items: center; justify-content: center; color: #666;">
                                No Poster
                            </div>
                        <?php endif; ?>
                        
                        <div class="movie-info">
                            <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                            
                            <div class="movie-meta">
                                <span class="rating rating-<?php echo strtolower($movie['rating']); ?>">
                                    <?php echo $movie['rating']; ?>
                                </span>
                                <span>⏱️ <?php echo $movie['duration']; ?> min</span>
                                <span>🎭 <?php echo htmlspecialchars($movie['genre']); ?></span>
                            </div>
                            
                            <p><?php echo htmlspecialchars(substr($movie['description'], 0, 100)) . '...'; ?></p>
                            
                            <div class="price">From $<?php echo number_format($movie['price'], 2); ?></div>
                            
                            <?php if ($screening_counts[$movie['id']] > 0): ?>
                                <div style="margin: 10px 0; color: #44ff44;">
                                    🎬 <?php echo $screening_counts[$movie['id']]; ?> cinema screenings
                                </div>
                            <?php endif; ?>
                            
                            <a href="movie_detail.php?id=<?php echo $movie['id']; ?>" class="btn btn-primary btn-block">View Details</a>
                            
                            <!-- Add to favorites button -->
                            <a href="favorites.php?add=<?php echo $movie['id']; ?>" class="btn-favorite" style="margin-top: 10px;">❤️ Add to Favorites</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>