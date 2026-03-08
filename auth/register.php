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
        <div class="form-container">
            <h1>Create Account</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($form_data['first_name']); ?>" 
                               required placeholder="Enter first name">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($form_data['last_name']); ?>" 
                               required placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                           required placeholder="3-20 characters, letters/numbers/_">
                    <small class="form-text">3-20 characters, letters, numbers, and underscores only</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                           required placeholder="Enter your email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" 
                               required placeholder="Minimum 6 characters">
                        <small class="form-text">At least 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required placeholder="Re-enter password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthdate">Birthdate</label>
                        <input type="date" id="birthdate" name="birthdate" 
                               value="<?php echo htmlspecialchars($form_data['birthdate']); ?>" 
                               required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select gender</option>
                            <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Country</label>
                        <select id="country" name="country" required>
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?php echo $code; ?>" 
                                    <?php echo $form_data['country'] == $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                               required placeholder="9123456789">
                        <small class="form-text">Enter 10 digits (will be formatted with +63)</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                Already have an account? <a href="login.php" style="color: #00ffff;">Login here</a>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>