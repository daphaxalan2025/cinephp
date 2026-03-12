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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
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
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><path d="M20,20 L80,20 L80,80 L20,80 Z" fill="none" stroke="%23e50914" stroke-width="0.5"/><circle cx="50" cy="50" r="30" fill="none" stroke="%23e50914" stroke-width="0.5"/></svg>') repeat;
            pointer-events: none;
            z-index: -1;
        }
        
        /* Glassmorphism Base */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            transition: all 0.3s;
        }
        
        .logo:hover {
            text-shadow: var(--red-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 10px;
            font-size: 1.5rem;
            filter: drop-shadow(0 0 5px var(--red));
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        
        .nav-links a:hover {
            color: var(--red);
        }
        
        .nav-links a:hover::after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--red);
        }
        
        .nav-links a.active::after {
            width: 60%;
        }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Form Container */
        .form-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            padding: 50px 40px;
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--red), var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .form-container::after {
            content: 'CINEMA';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 5rem;
            font-weight: 900;
            opacity: 0.03;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        
        .form-container h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 30px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 3px;
            position: relative;
        }
        
        .form-container h1::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--red);
            margin: 15px auto 0;
            border-radius: 3px;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-group input::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        /* Button */
        .btn-primary {
            background: var(--red);
            color: #fff;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 1rem;
            padding: 16px 32px;
            border-radius: 40px;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover {
            background: var(--red-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.5);
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            margin-bottom: 25px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            text-align: center;
        }
        
        .alert-error {
            border-left: 4px solid var(--red);
            color: #ff6b6b;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .register-link a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            position: relative;
            padding-bottom: 2px;
            transition: all 0.3s;
        }
        
        .register-link a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        
        .register-link a:hover {
            text-shadow: 0 0 8px var(--red);
        }
        
        .register-link a:hover::after {
            width: 100%;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        /* Features List */
        .features-list {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .feature-item i {
            color: var(--red);
            font-size: 1rem;
        }
        
        /* Demo Credentials */
        .demo-credentials {
            margin-top: 25px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 40px;
            border: 1px dashed rgba(229, 9, 20, 0.3);
            font-size: 0.85rem;
        }
        
        .demo-credentials p {
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .demo-credentials span {
            color: var(--red);
            font-weight: 600;
            font-family: monospace;
            background: rgba(229, 9, 20, 0.1);
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .form-container {
                padding: 40px 25px;
            }
            
            .form-container h1 {
                font-size: 2.2rem;
            }
            
            .features-list {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="../auth/login.php" class="active">Login</a>
                <a href="../auth/register.php">Register</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="form-container">
            <h1>Welcome Back</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username_email" 
                           value="<?php echo htmlspecialchars($username_email); ?>" 
                           required autofocus 
                           placeholder="Enter your username or email">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" 
                           required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
            </form>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
            
            <div class="register-link">
                New to CinemaTicket?
                <a href="register.php">Create an account</a>
            </div>
            
            <div class="features-list">
                <div class="feature-item">
                    <i>🎬</i> Latest Movies
                </div>
                <div class="feature-item">
                    <i>💺</i> Easy Booking
                </div>
                <div class="feature-item">
                    <i>🌟</i> Best Experience
                </div>
            </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>