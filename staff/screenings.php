<?php
// staff/screenings.php - PROFESSIONAL DESIGN FIXED - ALL FUNCTIONS WORKING
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();
$errors = [];

// Get staff's assigned cinema from staff_cinemas table
$staff_cinema = getStaffCinema($user['id']);
$cinema_id = $staff_cinema ? $staff_cinema['id'] : 0;
$is_assigned = ($cinema_id > 0);

// Get assigned cinema details for pre-population
$assigned_cinema_details = null;
if ($is_assigned) {
    $stmt = $pdo->prepare("SELECT id, name, total_screens, seats_per_screen FROM cinemas WHERE id = ?");
    $stmt->execute([$cinema_id]);
    $assigned_cinema_details = $stmt->fetch();
}

// Auto-expire past screenings
$stmt = $pdo->prepare("UPDATE screenings SET status = 'expired' WHERE CONCAT(show_date, ' ', show_time) < NOW() AND status != 'expired'");
$stmt->execute();

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE screening_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlash('Cannot delete screening with existing tickets', 'error');
        } else {
            $verify = $pdo->prepare("SELECT cinema_id FROM screenings WHERE id = ?");
            $verify->execute([$id]);
            $screening_cinema = $verify->fetchColumn();
            
            if ($is_assigned && $screening_cinema != $cinema_id) {
                setFlash('You can only delete screenings from your assigned cinema', 'error');
            } else {
                $stmt = $pdo->prepare("DELETE FROM screenings WHERE id = ?");
                if ($stmt->execute([$id])) {
                    setFlash('Screening deleted successfully', 'success');
                }
            }
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: screenings.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $movie_id = $_POST['movie_id'] ?? '';
    $cinema_id_post = $_POST['cinema_id'] ?? $cinema_id;
    $screen_number = intval($_POST['screen_number'] ?? 0);
    $show_date = $_POST['show_date'] ?? '';
    $show_time = $_POST['show_time'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $available_seats = intval($_POST['available_seats'] ?? 40);
    $screening_id = $_POST['screening_id'] ?? '';
    
    if (empty($movie_id)) $errors[] = 'Movie is required';
    if (empty($cinema_id_post)) $errors[] = 'Cinema is required';
    if ($screen_number < 1) $errors[] = 'Valid screen number is required';
    if (empty($show_date)) $errors[] = 'Date is required';
    if (empty($show_time)) $errors[] = 'Time is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($available_seats < 1 || $available_seats > 500) $errors[] = 'Available seats must be between 1 and 500';
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT release_date, title FROM movies WHERE id = ?");
        $stmt->execute([$movie_id]);
        $movie_data = $stmt->fetch();
        
        if ($movie_data && $movie_data['release_date']) {
            $cutoff = date('Y-m-d', strtotime($movie_data['release_date'] . ' +3 months'));
            if ($show_date > $cutoff) {
                $errors[] = 'Cannot schedule a screening more than 3 months after the release date. Release date: ' . $movie_data['release_date'] . ' | Cutoff: ' . $cutoff;
            }
            if ($show_date < $movie_data['release_date']) {
                $errors[] = 'Screening date cannot be before the movie release date (' . $movie_data['release_date'] . ')';
            }
        }
    }
    
    if (empty($errors)) {
        if ($screening_id) {
            $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ? AND id != ?");
            $check->execute([$cinema_id_post, $screen_number, $show_date, $show_time, $screening_id]);
        } else {
            $check = $pdo->prepare("SELECT id FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ? AND show_time = ?");
            $check->execute([$cinema_id_post, $screen_number, $show_date, $show_time]);
        }
        
        if ($check->fetch()) {
            $errors[] = 'A screening already exists for this cinema, screen, date and time';
        }
        
        if (!$screening_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM screenings WHERE cinema_id = ? AND screen_number = ? AND show_date = ?");
            $stmt->execute([$cinema_id_post, $screen_number, $show_date]);
            $count = $stmt->fetchColumn();
            
            if ($count >= 5) {
                $errors[] = 'Maximum 5 screenings allowed per screen per day';
            }
        }
    }
    
    if (empty($errors)) {
        $screening_datetime = strtotime("$show_date $show_time");
        $status = ($screening_datetime < time()) ? 'expired' : 'scheduled';
        
        try {
            if ($screening_id) {
                $sql = "UPDATE screenings SET movie_id = ?, cinema_id = ?, screen_number = ?, show_date = ?, show_time = ?, price = ?, available_seats = ?, status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$movie_id, $cinema_id_post, $screen_number, $show_date, $show_time, $price, $available_seats, $status, $screening_id]);
                
                if ($result) {
                    setFlash('Screening updated successfully', 'success');
                } else {
                    setFlash('Failed to update screening', 'error');
                }
            } else {
                $sql = "INSERT INTO screenings (movie_id, cinema_id, screen_number, show_date, show_time, price, available_seats, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$movie_id, $cinema_id_post, $screen_number, $show_date, $show_time, $price, $available_seats, $status]);
                
                if ($result) {
                    setFlash("Screening added successfully", 'success');
                } else {
                    setFlash('Failed to add screening', 'error');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
    } else {
        setFlash(implode('<br>', $errors), 'error');
    }
    
    header('Location: screenings.php');
    exit;
}

// Get data for dropdowns
$movies = $pdo->query("SELECT id, title, price, release_date FROM movies ORDER BY title")->fetchAll();

// Get cinemas - if staff assigned, only that cinema
if ($is_assigned) {
    $cinemas = [$assigned_cinema_details];
} else {
    $cinemas = $pdo->query("SELECT id, name, total_screens, seats_per_screen FROM cinemas ORDER BY name")->fetchAll();
}

// Get all screenings
$sql = "
    SELECT s.*, m.title as movie_title, m.price as movie_price, m.release_date, c.name as cinema_name, c.seats_per_screen,
           (SELECT COUNT(*) FROM tickets WHERE screening_id = s.id) as tickets_sold
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
";

$where = [];
$params = [];

if ($is_assigned) {
    $where[] = "s.cinema_id = ?";
    $params[] = $cinema_id;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY c.name, s.show_date DESC, s.show_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$screenings_by_cinema_raw = $stmt->fetchAll();

$grouped_screenings = [];
foreach ($screenings_by_cinema_raw as $s) {
    $grouped_screenings[$s['cinema_name']][] = $s;
}

$edit_screening = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM screenings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_screening = $stmt->fetch();
}

$show_form = (isset($_GET['action']) && $_GET['action'] == 'add') || isset($_GET['edit']);

// Pre-generate screen options for assigned staff
$pre_generated_screen_options = '';
if ($is_assigned && $assigned_cinema_details) {
    $total_screens = $assigned_cinema_details['total_screens'];
    $seats_per_screen = $assigned_cinema_details['seats_per_screen'] ?? 40;
    $options = '<option value="">-- Select screen --</option>';
    for ($i = 1; $i <= $total_screens; $i++) {
        $selected = ($edit_screening && $edit_screening['screen_number'] == $i) ? 'selected' : '';
        $options .= "<option value=\"{$i}\" {$selected}>Screen {$i}</option>";
    }
    $pre_generated_screen_options = $options;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Screenings - Staff Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #0a0a0a;
            --deep-gray: #1a1a1a;
            --medium-gray: #2a2a2a;
            --light-gray: #333333;
            --red: #e50914;
            --red-dark: #b2070f;
            --red-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --glass-bg: rgba(26, 26, 26, 0.7);
            --glass-border: rgba(255, 255, 255, 0.05);
            --card-gradient: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(229, 9, 20, 0.03) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(229, 9, 20, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }
        
        .logo {
            color: var(--red);
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.3s;
        }
        .logo:hover { text-shadow: var(--red-glow); }
        .logo::before { content: "🎬"; margin-right: 8px; font-size: 1.2rem; filter: drop-shadow(0 0 5px var(--red)); }
        
        .nav-links { display: flex; gap: 20px; align-items: center; }
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        .nav-links a:hover { color: var(--red); }
        .nav-links a:hover::after { width: 60%; }
        .nav-links a.active { color: var(--red); }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        /* Buttons - Fixed Sizes */
        .btn-add {
            background: var(--red);
            color: white;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            min-width: 180px;
            justify-content: center;
        }
        
        .btn-add:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 12px 28px;
            border-radius: 40px;
            transition: all 0.3s;
            cursor: pointer;
            min-width: 180px;
            text-align: center;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 12px 28px;
            border-radius: 40px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 180px;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-search, .btn-clear {
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            min-width: 100px;
        }
        
        .btn-search {
            background: var(--red);
            color: white;
        }
        
        .btn-search:hover {
            background: var(--red-dark);
            transform: translateY(-2px);
        }
        
        .btn-clear {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
        }
        
        .btn-clear:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        /* Action Buttons on Cards - Fixed Sizes & Alignment */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-start;
        }
        
        .btn-action {
            min-width: 100px;
            padding: 8px 12px;
            font-size: 0.7rem;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .btn-action:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-action.delete {
            border-color: #ff4444;
            color: #ff4444;
        }
        
        .btn-action.delete:hover {
            background: #ff4444;
            color: #fff;
            border-color: #ff4444;
        }
        
        .btn-action.view {
            border-color: #44ff44;
            color: #44ff44;
        }
        
        .btn-action.view:hover {
            background: #44ff44;
            color: #000;
            border-color: #44ff44;
        }
        
        /* Staff Notice */
        .staff-notice {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--red);
            color: var(--text-primary);
            padding: 15px 25px;
            border-radius: 40px;
            margin-bottom: 25px;
            border-left: 4px solid var(--red);
            font-size: 0.9rem;
        }
        
        /* Search Section */
        .search-section {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .search-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-group select,
        .search-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .search-group select:focus,
        .search-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .search-actions {
            display: flex;
            gap: 10px;
        }
        
        .results-summary {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        /* Form Container */
        .form-wrapper {
            margin: 30px 0;
        }
        
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .form-container h2 {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            padding-bottom: 15px;
        }
        
        .form-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--red);
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(10, 10, 10, 0.6);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 20px rgba(229, 9, 20, 0.2);
        }
        
        .form-group input:read-only {
            background: rgba(10, 10, 10, 0.3);
            cursor: not-allowed;
            color: var(--text-secondary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-text {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 5px;
            padding-left: 15px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .button-group .btn-primary,
        .button-group .btn-secondary {
            flex: 1;
        }
        
        .date-info {
            background: rgba(229, 9, 20, 0.1);
            border-radius: 40px;
            padding: 8px 15px;
            margin-top: 10px;
            font-size: 0.75rem;
            color: #ff8844;
            text-align: center;
        }
        
        /* Screening Cards */
        .screening-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .screening-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s;
        }
        
        .screening-card:hover {
            border-color: rgba(229, 9, 20, 0.3);
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.15);
        }
        
        .screening-movie {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--red);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .screening-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid rgba(229, 9, 20, 0.1);
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
        }
        
        .detail-item {
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .detail-value.highlight {
            color: var(--red);
            font-weight: 700;
        }
        
        .screening-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-scheduled {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .status-expired {
            background: rgba(136, 136, 136, 0.15);
            color: #888;
            border: 1px solid #888;
        }
        
        .seat-progress {
            margin-top: 5px;
        }
        
        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--red);
            border-radius: 3px;
            transition: width 0.3s;
        }
        
        .no-screenings {
            text-align: center;
            padding: 60px 40px;
            background: var(--card-gradient);
            border-radius: 20px;
            color: var(--text-secondary);
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 40px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(229, 9, 20, 0.2);
            font-size: 0.9rem;
        }
        
        .alert-error {
            border-left: 4px solid #ff4444;
            color: #ff6b6b;
        }
        
        .alert-success {
            border-left: 4px solid #44ff44;
            color: #44ff44;
        }
        
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 25px 0;
            opacity: 0.3;
        }
        
        @media (max-width: 1024px) {
            .screening-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            h1 { font-size: 1.8rem; }
            .form-row { grid-template-columns: 1fr; }
            .form-container { padding: 25px; margin: 20px; }
            .button-group { flex-direction: column; }
            .search-row { flex-direction: column; }
            .search-group { width: 100%; }
            .search-actions { width: 100%; }
            .btn-search, .btn-clear { flex: 1; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-add { width: 100%; justify-content: center; }
            .screening-details { grid-template-columns: 1fr; }
            .action-buttons { justify-content: center; }
            .btn-action { min-width: 90px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php" class="active">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="page-header">
            <h1>Manage Screenings</h1>
            <a href="?action=add" class="btn-add">➕ Add New Screening</a>
        </div>
        
        <div class="cinema-strip"></div>
        
        <?php if ($is_assigned): ?>
            <div class="staff-notice">
                ℹ️ You are assigned to <strong><?php echo htmlspecialchars($assigned_cinema_details['name'] ?? 'your cinema'); ?></strong>. 
                You can only manage screenings for this cinema. Price is auto-set from movie price.
            </div>
        <?php endif; ?>
        
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($show_form): ?>
            <div class="form-wrapper">
                <div class="form-container">
                    <h2><?php echo $edit_screening ? '✏️ Edit Screening' : '➕ Add New Screening'; ?></h2>
                    
                    <form method="POST" action="" id="screeningForm">
                        <?php if ($edit_screening): ?>
                            <input type="hidden" name="screening_id" value="<?php echo $edit_screening['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>🎬 Select Movie</label>
                                <select name="movie_id" id="movieSelect" required>
                                    <option value="">-- Select a movie --</option>
                                    <?php foreach ($movies as $movie): ?>
                                        <option value="<?php echo $movie['id']; ?>" 
                                            data-price="<?php echo $movie['price']; ?>"
                                            data-release="<?php echo $movie['release_date']; ?>"
                                            <?php echo (($edit_screening['movie_id'] ?? '') == $movie['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($movie['title']); ?> (₱<?php echo number_format($movie['price'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>🏛️ Cinema</label>
                                <select name="cinema_id" id="cinemaSelect" required <?php echo $is_assigned ? 'disabled' : ''; ?> onchange="updateScreenOptions()">
                                    <option value="">-- Select a cinema --</option>
                                    <?php foreach ($cinemas as $cinema): ?>
                                        <?php if ($cinema): ?>
                                            <option value="<?php echo $cinema['id']; ?>" 
                                                data-screens="<?php echo $cinema['total_screens']; ?>"
                                                data-seats="<?php echo $cinema['seats_per_screen'] ?? 40; ?>"
                                                <?php echo (($edit_screening['cinema_id'] ?? '') == $cinema['id']) ? 'selected' : ''; ?>
                                                <?php echo ($is_assigned && $cinema['id'] == $cinema_id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cinema['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($is_assigned): ?>
                                    <input type="hidden" name="cinema_id" value="<?php echo $cinema_id; ?>">
                                    <small class="form-text">You are assigned to this cinema</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📺 Screen Number</label>
                                <?php if ($is_assigned && $assigned_cinema_details): ?>
                                    <select name="screen_number" id="screenSelect" required>
                                        <?php echo $pre_generated_screen_options; ?>
                                    </select>
                                <?php else: ?>
                                    <select name="screen_number" id="screenSelect" required>
                                        <option value="">-- Select screen --</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>💰 Price (₱)</label>
                                <input type="number" name="price" id="priceInput" step="0.01" 
                                       value="<?php echo $edit_screening['price'] ?? ''; ?>" 
                                       required placeholder="Auto-fills from movie"
                                       <?php echo $is_assigned ? 'readonly' : ''; ?>>
                                <small class="form-text">
                                    <?php echo $is_assigned ? 'Auto-set from selected movie (read-only)' : 'Auto-fills from movie (can be overridden)'; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Show Date</label>
                                <input type="date" name="show_date" id="showDate" value="<?php echo $edit_screening['show_date'] ?? date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                <div id="dateInfo" class="date-info"></div>
                            </div>
                            
                            <div class="form-group">
                                <label>⏰ Show Time</label>
                                <input type="time" name="show_time" value="<?php echo $edit_screening['show_time'] ?? '19:00'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>🪑 Available Seats</label>
                            <input type="number" name="available_seats" id="availableSeats" value="<?php 
                                if ($edit_screening) {
                                    echo $edit_screening['available_seats'];
                                } elseif ($is_assigned && $assigned_cinema_details) {
                                    echo $assigned_cinema_details['seats_per_screen'] ?? 40;
                                } else {
                                    echo '40';
                                }
                            ?>" min="1" max="500" required readonly>
                            <small class="form-text">Auto-filled from cinema's seats per screen</small>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn-primary">
                                <?php echo $edit_screening ? '💾 Update Screening' : '✨ Create Screening'; ?>
                            </button>
                            <a href="screenings.php" class="btn-secondary">❌ Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="cinema-strip"></div>
        <?php endif; ?>
        
        <!-- Search Section - No Labels -->
        <div class="search-section">
            <div class="search-row">
                <div class="search-group">
                    <select id="cinemaFilter" onchange="filterScreenings()" <?php echo $is_assigned ? 'disabled' : ''; ?>>
                        <option value="">-- Select a cinema --</option>
                        <?php foreach ($cinemas as $cinema): ?>
                            <?php if ($cinema): ?>
                                <option value="<?php echo htmlspecialchars($cinema['name']); ?>">
                                    <?php echo htmlspecialchars($cinema['name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_assigned && !empty($cinemas[0])): ?>
                        <input type="hidden" id="forcedCinema" value="<?php echo htmlspecialchars($cinemas[0]['name']); ?>">
                    <?php endif; ?>
                </div>
                <div class="search-group">
                    <input type="text" id="movieSearchInput" placeholder="Search by movie name..." autocomplete="off">
                </div>
                <div class="search-actions">
                    <button class="btn-search" onclick="applyMovieSearch()">🔍 Search</button>
                    <button class="btn-clear" onclick="clearSearch()">Clear</button>
                </div>
            </div>
            <div class="results-summary" id="resultsSummary"></div>
        </div>
        
        <div id="screeningsContainer" class="screening-cards">
            <div class="no-screenings">
                🎬 Select a cinema from the dropdown above to view its screenings
            </div>
        </div>
    </main>
    
    <script>
        const allScreenings = <?php echo json_encode($grouped_screenings); ?>;
        let currentCinema = '';
        let currentSearchTerm = '';
        
        <?php if ($is_assigned && !empty($cinemas[0])): ?>
        currentCinema = '<?php echo htmlspecialchars($cinemas[0]['name']); ?>';
        const cinemaFilter = document.getElementById('cinemaFilter');
        if (cinemaFilter) {
            cinemaFilter.value = currentCinema;
        }
        filterScreenings();
        <?php endif; ?>
        
        function filterScreenings() {
            const cinemaFilterEl = document.getElementById('cinemaFilter');
            if (cinemaFilterEl && !cinemaFilterEl.disabled) {
                currentCinema = cinemaFilterEl.value;
            } else if (cinemaFilterEl && cinemaFilterEl.disabled) {
                const forcedCinema = document.getElementById('forcedCinema');
                if (forcedCinema) {
                    currentCinema = forcedCinema.value;
                }
            }
            currentSearchTerm = document.getElementById('movieSearchInput').value;
            renderScreenings();
        }
        
        function applyMovieSearch() {
            currentSearchTerm = document.getElementById('movieSearchInput').value;
            renderScreenings();
        }
        
        function clearSearch() {
            document.getElementById('movieSearchInput').value = '';
            currentSearchTerm = '';
            renderScreenings();
        }
        
        function renderScreenings() {
            const container = document.getElementById('screeningsContainer');
            const resultsSummary = document.getElementById('resultsSummary');
            
            if (!currentCinema) {
                container.innerHTML = '<div class="no-screenings">🎬 Select a cinema from the dropdown above to view its screenings</div>';
                resultsSummary.innerHTML = '';
                return;
            }
            
            let screenings = allScreenings[currentCinema] || [];
            
            if (currentSearchTerm) {
                screenings = screenings.filter(s => 
                    s.movie_title.toLowerCase().includes(currentSearchTerm.toLowerCase())
                );
            }
            
            if (screenings.length === 0) {
                if (currentSearchTerm) {
                    container.innerHTML = '<div class="no-screenings">📭 No screenings found for "' + escapeHtml(currentSearchTerm) + '" in this cinema</div>';
                    resultsSummary.innerHTML = 'No results found for "' + escapeHtml(currentSearchTerm) + '"';
                } else {
                    container.innerHTML = '<div class="no-screenings">📭 No screenings found for this cinema</div>';
                    resultsSummary.innerHTML = '';
                }
                return;
            }
            
            if (currentSearchTerm) {
                resultsSummary.innerHTML = `Found ${screenings.length} screening(s) matching "${escapeHtml(currentSearchTerm)}" in ${escapeHtml(currentCinema)}`;
            } else {
                resultsSummary.innerHTML = `Showing all ${screenings.length} screening(s) for ${escapeHtml(currentCinema)}`;
            }
            
            let html = '';
            for (const s of screenings) {
                const now = new Date();
                const showDateTime = new Date(s.show_date + 'T' + s.show_time);
                const isExpired = showDateTime < now || s.status === 'expired';
                const sold = parseInt(s.tickets_sold) || 0;
                const available = parseInt(s.available_seats);
                const percentBooked = (sold / available) * 100;
                const showDate = new Date(s.show_date);
                const formattedDate = showDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                const formattedTime = showDateTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const priceDiff = parseFloat(s.price) !== parseFloat(s.movie_price);
                
                let movieTitle = escapeHtml(s.movie_title);
                if (currentSearchTerm) {
                    const regex = new RegExp(`(${escapeRegex(currentSearchTerm)})`, 'gi');
                    movieTitle = movieTitle.replace(regex, '<mark style="background: var(--red); color: white; padding: 0 4px; border-radius: 4px;">$1</mark>');
                }
                
                html += `
                    <div class="screening-card">
                        <div class="screening-movie">
                            🎬 ${movieTitle}
                        </div>
                        <div class="screening-details">
                            <div class="detail-item">
                                <span class="detail-label">📅 DATE</span>
                                <span class="detail-value">${formattedDate}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">⏰ TIME</span>
                                <span class="detail-value">${formattedTime}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📺 SCREEN</span>
                                <span class="detail-value">Screen ${s.screen_number}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">💰 PRICE</span>
                                <span class="detail-value highlight">₱${parseFloat(s.price).toFixed(2)}</span>
                                ${priceDiff ? `<span style="color:#ff8844; font-size:0.7rem; margin-left:5px;">(Movie: ₱${parseFloat(s.movie_price).toFixed(2)})</span>` : ''}
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">🪑 SEATS</span>
                                <span class="detail-value">${sold} / ${available} sold</span>
                                <div class="seat-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${percentBooked}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">📊 STATUS</span>
                                <span class="screening-status ${isExpired ? 'status-expired' : 'status-scheduled'}">
                                    ${isExpired ? 'Expired' : 'Scheduled'}
                                </span>
                            </div>
                        </div>
                        <div class="action-buttons">
                            ${!isExpired ? `<a href="?edit=${s.id}" class="btn-action">✏️ Edit</a>` : ''}
                            <a href="?delete=${s.id}" class="btn-action delete" onclick="return confirm('Delete this screening? This action cannot be undone.')">🗑️ Delete</a>
                            <a href="tickets_list.php?screening_id=${s.id}" class="btn-action view">🎟️ View Tickets</a>
                        </div>
                    </div>
                `;
            }
            container.innerHTML = html;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        const searchInput = document.getElementById('movieSearchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyMovieSearch();
            });
        }
        
        const movieSelect = document.getElementById('movieSelect');
        const priceInput = document.getElementById('priceInput');
        const showDateInput = document.getElementById('showDate');
        const dateInfoDiv = document.getElementById('dateInfo');
        
        function updatePriceFromMovie() {
            if (movieSelect && priceInput && movieSelect.value) {
                const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                const moviePrice = selectedOption.getAttribute('data-price');
                if (moviePrice) {
                    priceInput.value = parseFloat(moviePrice).toFixed(2);
                }
            }
        }
        
        function updateDateInfo() {
            if (movieSelect && movieSelect.value && showDateInput) {
                const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                const releaseDate = selectedOption.getAttribute('data-release');
                
                if (releaseDate) {
                    const release = new Date(releaseDate);
                    const cutoff = new Date(release);
                    cutoff.setMonth(cutoff.getMonth() + 3);
                    
                    const releaseStr = release.toISOString().split('T')[0];
                    const cutoffStr = cutoff.toISOString().split('T')[0];
                    
                    dateInfoDiv.innerHTML = `📅 Release: ${releaseStr} | ✅ Valid until: ${cutoffStr}`;
                    showDateInput.min = releaseStr;
                    showDateInput.max = cutoffStr;
                } else {
                    dateInfoDiv.innerHTML = '';
                    showDateInput.min = '<?php echo date('Y-m-d'); ?>';
                    showDateInput.max = '';
                }
            }
        }
        
        function checkReleaseDate() {
            if (movieSelect && movieSelect.value && showDateInput && showDateInput.value) {
                const selectedOption = movieSelect.options[movieSelect.selectedIndex];
                const releaseDate = selectedOption.getAttribute('data-release');
                const showDate = showDateInput.value;
                
                if (releaseDate && showDate) {
                    if (showDate < releaseDate) {
                        dateInfoDiv.innerHTML = '<span style="color: #ff4444;">⚠️ Warning: Screening date is BEFORE movie release date!</span>';
                    } else {
                        const release = new Date(releaseDate);
                        const cutoff = new Date(release);
                        cutoff.setMonth(cutoff.getMonth() + 3);
                        const cutoffStr = cutoff.toISOString().split('T')[0];
                        
                        if (showDate > cutoffStr) {
                            dateInfoDiv.innerHTML = `<span style="color: #ff8844;">⚠️ Warning: Screening date is more than 3 months after release date (${releaseDate}). Cutoff: ${cutoffStr}</span>`;
                        }
                    }
                }
            }
        }
        
        if (movieSelect) {
            movieSelect.addEventListener('change', function() {
                updatePriceFromMovie();
                updateDateInfo();
            });
            if (movieSelect.value) {
                updatePriceFromMovie();
                updateDateInfo();
            }
        }
        
        if (showDateInput) {
            showDateInput.addEventListener('change', checkReleaseDate);
        }
        
        function updateScreenOptions() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            const screenSelect = document.getElementById('screenSelect');
            const availableSeatsInput = document.getElementById('availableSeats');
            
            <?php if (!$is_assigned): ?>
            if (cinemaSelect && screenSelect && cinemaSelect.value) {
                const selected = cinemaSelect.options[cinemaSelect.selectedIndex];
                let totalScreens = selected.dataset.screens ? parseInt(selected.dataset.screens) : 0;
                let seatsPerScreen = selected.dataset.seats ? parseInt(selected.dataset.seats) : 40;
                
                if (availableSeatsInput && seatsPerScreen) {
                    availableSeatsInput.value = seatsPerScreen;
                }
                
                if (totalScreens > 0) {
                    let options = '<option value="">-- Select screen --</option>';
                    for (let i = 1; i <= totalScreens; i++) {
                        options += `<option value="${i}">Screen ${i}</option>`;
                    }
                    screenSelect.innerHTML = options;
                    
                    <?php if ($edit_screening): ?>
                    if (<?php echo json_encode($edit_screening['screen_number']); ?>) {
                        screenSelect.value = <?php echo json_encode($edit_screening['screen_number']); ?>;
                    }
                    <?php endif; ?>
                }
            }
            <?php endif; ?>
        }
        
        <?php if (!$is_assigned): ?>
        if (document.getElementById('cinemaSelect')) {
            document.getElementById('cinemaSelect').addEventListener('change', updateScreenOptions);
            if (document.getElementById('cinemaSelect').value) {
                updateScreenOptions();
            }
        }
        <?php endif; ?>
        
        <?php if ($edit_screening): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const cinemaSelect = document.getElementById('cinemaSelect');
            if (cinemaSelect && cinemaSelect.value) {
                updateScreenOptions();
            }
            if (movieSelect && movieSelect.value) {
                updatePriceFromMovie();
                updateDateInfo();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>