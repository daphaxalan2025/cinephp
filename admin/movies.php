<?php
// admin/movies.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];
$success = '';

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Get poster filename to delete
        $stmt = $pdo->prepare("SELECT poster FROM movies WHERE id = ?");
        $stmt->execute([$id]);
        $movie = $stmt->fetch();
        
        if ($movie && $movie['poster']) {
            $poster_path = '../uploads/posters/' . $movie['poster'];
            if (file_exists($poster_path)) {
                unlink($poster_path); // Delete the poster file
            }
        }
        
        // Check if movie has screenings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete movie with existing screenings', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
            if ($stmt->execute([$id])) {
                setFlash('Movie deleted successfully', 'success');
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: movies.php');
    exit;
}

// ============ HANDLE ADD/EDIT ============
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = intval($_POST['duration'] ?? 120);
    $rating = $_POST['rating'] ?? 'PG';
    $genre = trim($_POST['genre'] ?? '');
    $price = floatval($_POST['price'] ?? 12.50);
    $trailer_url = trim($_POST['trailer_url'] ?? '');
    $streaming_url = trim($_POST['streaming_url'] ?? '');
    $release_date = $_POST['release_date'] ?? date('Y-m-d');
    $movie_id = $_POST['movie_id'] ?? '';
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required';
    if (strlen($title) < 2) $errors[] = 'Title must be at least 2 characters';
    if (empty($description)) $errors[] = 'Description is required';
    if ($duration < 1 || $duration > 300) $errors[] = 'Duration must be between 1 and 300 minutes';
    if (empty($rating)) $errors[] = 'Rating is required';
    if (empty($genre)) $errors[] = 'Genre is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    // Handle poster upload
    $poster_filename = $_POST['current_poster'] ?? '';
    
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['poster']['type'], $allowed_types)) {
            $errors[] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        } elseif ($_FILES['poster']['size'] > $max_size) {
            $errors[] = 'File too large. Maximum size is 5MB.';
        } else {
            // Generate unique filename
            $ext = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $poster_filename = uniqid() . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/posters/' . $poster_filename;
            
            if (!move_uploaded_file($_FILES['poster']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload poster.';
            }
        }
    } elseif (empty($poster_filename) && !$movie_id) {
        $errors[] = 'Poster is required for new movies';
    }
    
    // Format YouTube URL to embed format
    if (!empty($trailer_url)) {
        if (strpos($trailer_url, 'youtube.com/watch?v=') !== false) {
            parse_str(parse_url($trailer_url, PHP_URL_QUERY), $params);
            $video_id = $params['v'] ?? '';
            if ($video_id) {
                $trailer_url = 'https://www.youtube.com/embed/' . $video_id;
            }
        } elseif (strpos($trailer_url, 'youtu.be/') !== false) {
            $video_id = substr($trailer_url, strrpos($trailer_url, '/') + 1);
            $trailer_url = 'https://www.youtube.com/embed/' . $video_id;
        }
    }
    
    // Check for duplicate title
    try {
        $check = $pdo->prepare("SELECT id FROM movies WHERE title = ? AND id != ?");
        $check->execute([$title, $movie_id ?: 0]);
        if ($check->fetch()) {
            $errors[] = 'Movie with this title already exists';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            if ($movie_id) {
                // Update
                $sql = "UPDATE movies SET title=?, description=?, duration=?, rating=?, genre=?, price=?, poster=?, trailer_url=?, streaming_url=?, release_date=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$title, $description, $duration, $rating, $genre, $price, $poster_filename, $trailer_url, $streaming_url, $release_date, $movie_id]);
                
                // Delete old poster if new one was uploaded
                if ($result && isset($_FILES['poster']) && $_FILES['poster']['error'] == 0 && $_POST['current_poster']) {
                    $old_poster = '../uploads/posters/' . $_POST['current_poster'];
                    if (file_exists($old_poster)) {
                        unlink($old_poster);
                    }
                }
                
                if ($result) {
                    setFlash('Movie updated successfully', 'success');
                }
            } else {
                // Insert
                $sql = "INSERT INTO movies (title, description, duration, rating, genre, price, poster, trailer_url, streaming_url, release_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$title, $description, $duration, $rating, $genre, $price, $poster_filename, $trailer_url, $streaming_url, $release_date]);
                
                if ($result) {
                    setFlash('Movie added successfully', 'success');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: movies.php');
        exit;
    }
}

// Get all movies
try {
    $movies = $pdo->query("SELECT * FROM movies ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $movies = [];
    setFlash('Error loading movies: ' . $e->getMessage(), 'error');
}

// Get movie for editing
$edit_movie = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_movie = $stmt->fetch();
        if (!$edit_movie) {
            header('Location: movies.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
}

// Ratings options
$ratings = ['G', 'PG', 'PG-13', 'R'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Movies - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .poster-preview {
            max-width: 150px;
            max-height: 200px;
            margin-top: 10px;
            border: 2px solid #00ffff;
            border-radius: 4px;
        }
        .current-poster {
            margin: 10px 0;
            padding: 10px;
            background: #000;
            border: 1px solid #00ffff;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php" class="active">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online Schedule</a>
                <a href="users.php">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>Manage Movies</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add New Movie</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <?php if (isset($_GET['action']) || isset($_GET['edit'])): ?>
            <div style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 2px solid #00ffff; margin-bottom: 40px;">
                <h2 style="color: #00ffff; margin-bottom: 20px;">
                    <?php echo $edit_movie ? 'Edit Movie' : 'Add New Movie'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_movie): ?>
                        <input type="hidden" name="movie_id" value="<?php echo $edit_movie['id']; ?>">
                        <input type="hidden" name="current_poster" value="<?php echo $edit_movie['poster']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title">Movie Title</label>
                        <input type="text" id="title" name="title" 
                               value="<?php echo htmlspecialchars($edit_movie['title'] ?? ''); ?>" 
                               required placeholder="Enter movie title">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="5" 
                                  required placeholder="Enter movie description"><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration">Duration (minutes)</label>
                            <input type="number" id="duration" name="duration" 
                                   value="<?php echo $edit_movie['duration'] ?? '120'; ?>" 
                                   min="1" max="300" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="rating">Rating</label>
                            <select id="rating" name="rating" required>
                                <option value="">Select Rating</option>
                                <?php foreach ($ratings as $r): ?>
                                    <option value="<?php echo $r; ?>" 
                                        <?php echo ($edit_movie['rating'] ?? '') == $r ? 'selected' : ''; ?>>
                                        <?php echo $r; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="genre">Genre</label>
                            <input type="text" id="genre" name="genre" 
                                   value="<?php echo htmlspecialchars($edit_movie['genre'] ?? ''); ?>" 
                                   required placeholder="e.g., Action, Comedy, Drama">
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price ($)</label>
                            <input type="number" id="price" name="price" step="0.01" 
                                   value="<?php echo $edit_movie['price'] ?? '12.50'; ?>" 
                                   min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="release_date">Release Date</label>
                        <input type="date" id="release_date" name="release_date" 
                               value="<?php echo $edit_movie['release_date'] ?? date('Y-m-d'); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="poster">Movie Poster</label>
                        <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/gif">
                        <small class="form-text">Allowed: JPG, PNG, GIF (Max: 5MB)</small>
                        
                        <?php if ($edit_movie && $edit_movie['poster']): ?>
                            <div class="current-poster">
                                <p>Current Poster:</p>
                                <img src="../uploads/posters/<?php echo $edit_movie['poster']; ?>" 
                                     alt="Current poster" class="poster-preview">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="trailer_url">YouTube Trailer URL</label>
                        <input type="url" id="trailer_url" name="trailer_url" 
                               value="<?php echo htmlspecialchars($edit_movie['trailer_url'] ?? ''); ?>" 
                               placeholder="https://www.youtube.com/watch?v=...">
                        <small class="form-text">Supports youtube.com or youtu.be links</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="streaming_url">Streaming URL (Optional)</label>
                        <input type="url" id="streaming_url" name="streaming_url" 
                               value="<?php echo htmlspecialchars($edit_movie['streaming_url'] ?? ''); ?>" 
                               placeholder="https://...">
                        <small class="form-text">Link for online streaming (if available)</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_movie ? 'Update Movie' : 'Add Movie'; ?>
                        </button>
                        <a href="movies.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Movies List -->
        <?php if (empty($movies)): ?>
            <div class="alert alert-info">No movies found. Add your first movie!</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Poster</th>
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Rating</th>
                        <th>Genre</th>
                        <th>Price</th>
                        <th>Trailer</th>
                        <th>Screenings</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): 
                        // Get screening count
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE movie_id = ?");
                            $stmt->execute([$movie['id']]);
                            $screening_count = $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            $screening_count = 0;
                        }
                    ?>
                        <tr>
                            <td><?php echo $movie['id']; ?></td>
                            <td>
                                <?php if ($movie['poster']): ?>
                                    <img src="../uploads/posters/<?php echo $movie['poster']; ?>" 
                                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                         style="width: 50px; height: 70px; object-fit: cover; border: 1px solid #00ffff; border-radius: 4px;">
                                <?php else: ?>
                                    <span style="color: #666;">No poster</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: #00ffff;"><?php echo htmlspecialchars($movie['title']); ?></strong></td>
                            <td><?php echo $movie['duration']; ?> min</td>
                            <td>
                                <span style="padding: 3px 8px; background: #000; border: 1px solid #00ffff; border-radius: 3px;">
                                    <?php echo $movie['rating']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                            <td>$<?php echo number_format($movie['price'], 2); ?></td>
                            <td>
                                <?php if ($movie['trailer_url']): ?>
                                    <a href="<?php echo htmlspecialchars($movie['trailer_url']); ?>" target="_blank" class="btn-small">▶️ Watch</a>
                                <?php else: ?>
                                    <span style="color: #666;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $screening_count; ?></td>
                            <td>
                                <a href="?edit=<?php echo $movie['id']; ?>" class="btn-small">Edit</a>
                                <a href="?delete=<?php echo $movie['id']; ?>" class="btn-small" 
                                   onclick="return confirm('Are you sure you want to delete this movie?')" 
                                   style="border-color: #ff4444; color: #ff4444;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>