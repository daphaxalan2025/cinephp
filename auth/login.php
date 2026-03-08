<?php
// auth/login.php
require_once '../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$username_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username_email)) {
        $error = 'Please enter your username or email';
    } elseif (empty($password)) {
        $error = 'Please enter your password';
    } else {
        $user = loginUser($username_email, $password);
        
        if ($user) {
            setFlash('Welcome back, ' . $user['first_name'] . '!', 'success');
            
            // Redirect based on account type
            if ($user['account_type'] == 'admin') {
                header('Location: ../admin/dashboard.php');
            } elseif ($user['account_type'] == 'staff') {
                header('Location: ../staff/dashboard.php');
            } else {
                header('Location: ../user/movies.php');
            }
            exit;
        } else {
            $error = 'Invalid username/email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <a href="../auth/login.php">Login</a>
                <a href="../auth/register.php">Register</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="form-container" style="max-width: 400px;">
            <h1>Login</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <input type="text" id="username_email" name="username_email" 
                           value="<?php echo htmlspecialchars($username_email); ?>" 
                           required autofocus placeholder="Enter username or email">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                Don't have an account? <a href="register.php" style="color: #00ffff;">Register here</a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>