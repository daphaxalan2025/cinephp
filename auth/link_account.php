<?php
// auth/link_account.php
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();

// Only adults can link accounts
if ($user['account_type'] != 'adult') {
    setFlash('Only adult accounts can create linked accounts', 'error');
    header('Location: ../user/profile.php');
    exit;
}

$errors = [];
$form_data = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'birthdate' => '',
    'gender' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'birthdate' => $_POST['birthdate'] ?? '',
        'gender' => $_POST['gender'] ?? ''
    ];
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $relationship = $_POST['relationship'] ?? 'child';
    
    // Validation
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $form_data['username'])) {
        $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
    } elseif (isUsernameExists($form_data['username'])) {
        $errors[] = 'Username already taken';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } elseif (isEmailExists($form_data['email'])) {
        $errors[] = 'Email already registered';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($form_data['first_name'])) {
        $errors[] = 'First name is required';
    }
    
    if (empty($form_data['last_name'])) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($form_data['birthdate'])) {
        $errors[] = 'Birthdate is required';
    } else {
        $age = calculateAge($form_data['birthdate']);
        if ($age < 0) {
            $errors[] = 'Invalid birthdate';
        } elseif ($age >= 18) {
            $errors[] = 'Linked account must be under 18 years old';
        }
    }
    
    if (empty($form_data['gender'])) {
        $errors[] = 'Gender is required';
    } elseif (!in_array($form_data['gender'], ['male', 'female', 'other'])) {
        $errors[] = 'Invalid gender selection';
    }
    
    if (empty($errors)) {
        $pdo->beginTransaction();
        
        try {
            $age = calculateAge($form_data['birthdate']);
            $account_type = ($age < 13) ? 'kid' : 'teen';
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, 
                                  birthdate, account_type, gender, country, phone, parent_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PH', '0000000000', ?)
            ");
            
            $stmt->execute([
                $form_data['username'],
                $form_data['email'],
                $hash,
                $form_data['first_name'],
                $form_data['last_name'],
                $form_data['birthdate'],
                $account_type,
                $form_data['gender'],
                $user['id']
            ]);
            
            $child_id = $pdo->lastInsertId();
            
            // Insert into link_accounts table
            $stmt = $pdo->prepare("
                INSERT INTO link_accounts (parent_id, child_id, relationship) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $child_id, $relationship]);
            
            $pdo->commit();
            
            setFlash('Linked account created successfully!', 'success');
            header('Location: ../user/profile.php#linked-accounts');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to create account: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Linked Account - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket</a>
            <div class="nav-links">
                <a href="../user/movies.php">Movies</a>
                <a href="../user/purchases.php">My Tickets</a>
                <a href="../user/profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="form-container" style="max-width:600px;">
            <h1>Create Linked Account</h1>
            <p style="color:#888; margin-bottom:30px; text-align:center;">
                Create an account for your child or teen (under 18)
            </p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthdate">Birthdate *</label>
                        <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($form_data['birthdate']); ?>" required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select</option>
                            <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="relationship">Relationship *</label>
                    <select id="relationship" name="relationship" required>
                        <option value="child">Child</option>
                        <option value="sibling">Sibling</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="info-box" style="background:#000; border:1px solid #00ffff; padding:20px; border-radius:8px; margin:30px 0;">
                    <h3 style="color:#00ffff; margin-bottom:15px;">About Linked Accounts:</h3>
                    <ul style="color:#888; margin-left:20px;">
                        <li>✅ Kids (under 13) can only view G and PG movies</li>
                        <li>✅ Teens (13-17) can view G, PG, and PG-13 movies</li>
                        <li>✅ You can manage all linked accounts from your profile</li>
                        <li>✅ Purchase tickets for your family members</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Linked Account</button>
                <a href="../user/profile.php" class="btn btn-block" style="margin-top:10px;">Cancel</a>
            </form>
        </div>
    </main>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>