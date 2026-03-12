<?php
// auth/register.php
require_once '../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'birthdate' => '',
    'gender' => '',
    'country' => 'PH',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'birthdate' => $_POST['birthdate'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'country' => $_POST['country'] ?? 'PH',
        'phone' => trim($_POST['phone'] ?? '')
    ];
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ============ VALIDATION ============
    
    // Username validation
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    } elseif (!isValidUsername($form_data['username'])) {
        $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
    } elseif (isUsernameExists($form_data['username'])) {
        $errors[] = 'Username already taken';
    }
    
    // Email validation
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($form_data['email'])) {
        $errors[] = 'Invalid email format';
    } elseif (isEmailExists($form_data['email'])) {
        $errors[] = 'Email already registered';
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (!isValidPassword($password)) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    // Name validation
    if (empty($form_data['first_name'])) {
        $errors[] = 'First name is required';
    }
    if (empty($form_data['last_name'])) {
        $errors[] = 'Last name is required';
    }
    
    // Birthdate validation
    if (empty($form_data['birthdate'])) {
        $errors[] = 'Birthdate is required';
    } else {
        $age = calculateAge($form_data['birthdate']);
        if ($age < 0 || $age > 120) {
            $errors[] = 'Invalid birthdate';
        }
    }
    
    // Gender validation
    if (empty($form_data['gender'])) {
        $errors[] = 'Gender is required';
    } elseif (!in_array($form_data['gender'], ['male', 'female', 'other'])) {
        $errors[] = 'Invalid gender selection';
    }
    
    // Phone validation (simplified for now)
    if (empty($form_data['phone'])) {
        $errors[] = 'Phone number is required';
    } else {
        $phone = preg_replace('/[^0-9]/', '', $form_data['phone']);
        if (strlen($phone) < 10) {
            $errors[] = 'Invalid phone number';
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        $pdo = getDB();
        
        // Format phone
        $phone = preg_replace('/[^0-9]/', '', $form_data['phone']);
        if (strlen($phone) == 10) {
            $phone = '+63' . $phone;
        }
        
        // Calculate age and determine account type
        $age = calculateAge($form_data['birthdate']);
        $account_type = getAccountTypeByAge($age);
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, 
                              birthdate, account_type, gender, country, phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $form_data['username'],
                $form_data['email'],
                $password_hash,
                $form_data['first_name'],
                $form_data['last_name'],
                $form_data['birthdate'],
                $account_type,
                $form_data['gender'],
                $form_data['country'],
                $phone
            ]);
            
            setFlash('Registration successful! Please login.', 'success');
            header('Location: login.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

// Country list
$countries = [
    'PH' => 'Philippines',
    'US' => 'United States',
    'UK' => 'United Kingdom',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'JP' => 'Japan',
    'KR' => 'South Korea',
    'SG' => 'Singapore',
    'MY' => 'Malaysia',
    'CN' => 'China'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CinemaTicket</title>
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
            padding: 50px;
            max-width: 800px;
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
            content: 'JOIN US';
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            color: var(--red);
            font-weight: 600;
            letter-spacing: 1.5px;
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 40px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--red);
            box-shadow: 0 0 30px rgba(229, 9, 20, 0.3);
            background: rgba(20, 20, 20, 0.8);
        }
        
        .form-group input::placeholder,
        .form-group select::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23e50914' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 16px;
        }
        
        .form-text {
            display: block;
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 6px;
            padding-left: 15px;
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
            margin-top: 20px;
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
            padding: 18px 25px;
            margin-bottom: 25px;
            border-radius: 40px;
            animation: slideIn 0.3s ease;
            font-weight: 400;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
        }
        
        .alert-error {
            border-left: 4px solid var(--red);
            color: #ff6b6b;
        }
        
        .alert-error ul {
            margin-left: 20px;
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
        
        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .login-link a {
            color: var(--red);
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            position: relative;
            padding-bottom: 2px;
            transition: all 0.3s;
        }
        
        .login-link a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        
        .login-link a:hover {
            text-shadow: 0 0 8px var(--red);
        }
        
        .login-link a:hover::after {
            width: 100%;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0;
            opacity: 0.3;
        }
        
        /* Password Strength */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            background: var(--red);
            transition: width 0.3s;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .form-container {
                padding: 30px 20px;
            }
            
            .form-container h1 {
                font-size: 2.2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET</a>
            <div class="nav-links">
                <a href="../auth/login.php">Login</a>
                <a href="../auth/register.php" class="active">Register</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="form-container">
            <h1>Join the Experience</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin-left: 20px; margin-bottom: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" 
                               value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                               required placeholder="Enter first name">
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" 
                               value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                               required placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" 
                           value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                           required placeholder="3-20 characters, letters/numbers/_">
                    <small class="form-text">3-20 characters, letters, numbers, and underscores only</small>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" 
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                           required placeholder="Enter your email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="password" name="password" 
                               required placeholder="Minimum 6 characters">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                        <small class="form-text">At least 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required placeholder="Re-enter password">
                        <small class="form-text" id="passwordMatch"></small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="date" name="birthdate" 
                               value="<?php echo htmlspecialchars($form_data['birthdate']); ?>" 
                               required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Country</label>
                        <select name="country" required>
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?php echo $code; ?>" 
                                    <?php echo $form_data['country'] == $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" 
                               value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                               required placeholder="9123456789">
                        <small class="form-text">Enter 10 digits (will be formatted with +63)</small>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Create Account</button>
            </form>
            
            <!-- Cinema Strip Divider -->
            <div class="cinema-strip"></div>
            
            <div class="login-link">
                Already part of the cinema experience?
                <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/) || password.match(/[$@#&!]+/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.style.background = '#ff4444';
            } else if (strength <= 50) {
                strengthBar.style.background = '#ff8844';
            } else if (strength <= 75) {
                strengthBar.style.background = '#ffff44';
            } else {
                strengthBar.style.background = '#44ff44';
            }
        });
        
        // Password match indicator
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchMsg = document.getElementById('passwordMatch');
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchMsg.innerHTML = '✓ Passwords match';
                    matchMsg.style.color = '#44ff44';
                } else {
                    matchMsg.innerHTML = '✗ Passwords do not match';
                    matchMsg.style.color = '#ff4444';
                }
            } else {
                matchMsg.innerHTML = '';
            }
        });
    </script>
</body>
</html>