<?php
// admin/users.php
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();
$errors = [];

// ============ HANDLE DELETE ============
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Don't allow deleting own account
    if ($id == $_SESSION['user_id']) {
        setFlash('You cannot delete your own account', 'error');
    } else {
        try {
            // Check if user has tickets
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
            $stmt->execute([$id]);
            $ticket_count = $stmt->fetchColumn();
            
            // Check if user has payments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
            $stmt->execute([$id]);
            $payment_count = $stmt->fetchColumn();
            
            if ($ticket_count > 0 || $payment_count > 0) {
                setFlash('Cannot delete user with existing tickets or payments. Deactivate instead.', 'error');
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$id])) {
                    setFlash('User deleted successfully', 'success');
                }
            }
        } catch (PDOException $e) {
            setFlash('Error: ' . $e->getMessage(), 'error');
        }
    }
    header('Location: users.php');
    exit;
}

// ============ HANDLE TOGGLE STATUS ============
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('User status updated', 'success');
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: users.php');
    exit;
}

// ============ HANDLE ADD/EDIT ============
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $account_type = $_POST['account_type'] ?? 'user';
    $user_id = $_POST['user_id'] ?? '';
    
    // Validation
    if (empty($username)) $errors[] = 'Username is required';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) $errors[] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
    
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    if (!$user_id && empty($password)) $errors[] = 'Password is required for new users';
    elseif ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    
    // Check unique username
    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $user_id ?: 0]);
        if ($check->fetch()) $errors[] = 'Username already exists';
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    // Check unique email
    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $user_id ?: 0]);
        if ($check->fetch()) $errors[] = 'Email already exists';
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            if ($user_id) {
                // Update - don't change password if not provided
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password_hash=?, first_name=?, last_name=?, account_type=? WHERE id=?");
                    $result = $stmt->execute([$username, $email, $hash, $first_name, $last_name, $account_type, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, first_name=?, last_name=?, account_type=? WHERE id=?");
                    $result = $stmt->execute([$username, $email, $first_name, $last_name, $account_type, $user_id]);
                }
                if ($result) {
                    setFlash('User updated successfully', 'success');
                }
            } else {
                // Insert
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, account_type, birthdate) VALUES (?, ?, ?, ?, ?, ?, '2000-01-01')");
                $result = $stmt->execute([$username, $email, $hash, $first_name, $last_name, $account_type]);
                if ($result) {
                    setFlash('User added successfully', 'success');
                }
            }
        } catch (PDOException $e) {
            setFlash('Database error: ' . $e->getMessage(), 'error');
        }
        header('Location: users.php');
        exit;
    }
}

// Get all users with payment stats
try {
    $users = $pdo->query("
        SELECT u.*, 
               (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count,
               (SELECT COUNT(*) FROM payments WHERE user_id = u.id) as payment_count,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = u.id AND payment_status = 'completed') as total_spent
        FROM users u
        ORDER BY u.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $users = [];
    setFlash('Error loading users: ' . $e->getMessage(), 'error');
}

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_user = $stmt->fetch();
        if (!$edit_user) {
            setFlash('User not found', 'error');
            header('Location: users.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlash('Error: ' . $e->getMessage(), 'error');
        header('Location: users.php');
        exit;
    }
}

// Account types
$account_types = ['user', 'staff', 'admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CinemaTicket</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Admin</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="movies.php">Movies</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="online_schedule.php">Online Schedule</a>
                <a href="users.php" class="active">Users</a>
                <a href="tickets.php">Tickets</a>
                <a href="payments.php">Payments</a>
                <a href="reports.php">Reports</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h1>User Management</h1>
            <a href="?action=add" class="btn btn-primary">➕ Add New User</a>
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
                    <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
                </h2>
                
                <form method="POST">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($edit_user['first_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($edit_user['last_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>" 
                                   required <?php echo $edit_user ? 'readonly' : ''; ?>>
                            <?php if ($edit_user): ?>
                                <small class="form-text">Username cannot be changed</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">
                                <?php echo $edit_user ? 'New Password (leave blank to keep current)' : 'Password'; ?>
                            </label>
                            <input type="password" id="password" name="password" 
                                   <?php echo !$edit_user ? 'required' : ''; ?>
                                   placeholder="<?php echo $edit_user ? 'Leave blank to keep current' : 'Enter password'; ?>">
                            <?php if ($edit_user): ?>
                                <small class="form-text">Minimum 6 characters if changing</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="account_type">Account Type</label>
                            <select id="account_type" name="account_type" required>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo $type; ?>"
                                        <?php echo (($edit_user['account_type'] ?? 'user') == $type) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                        </button>
                        <a href="users.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Users List -->
        <?php if (empty($users)): ?>
            <div class="alert alert-info">No users found.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Tickets</th>
                        <th>Payments</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><span style="color: #00ffff;"><?php echo htmlspecialchars($user['username']); ?></span></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span style="padding: 3px 10px; background: #000; border: 1px solid #00ffff; border-radius: 15px;">
                                    <?php echo strtoupper($user['account_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: <?php echo $user['is_active'] ? '#44ff44' : '#ff4444'; ?>;">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['ticket_count']; ?></td>
                            <td><?php echo $user['payment_count']; ?></td>
                            <td>$<?php echo number_format($user['total_spent'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="?edit=<?php echo $user['id']; ?>" class="btn-small">Edit</a>
                                <a href="?toggle=<?php echo $user['id']; ?>" class="btn-small">
                                    <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                </a>
                                <a href="payments.php?user_id=<?php echo $user['id']; ?>" class="btn-small">Payments</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn-small" 
                                       onclick="return confirm('Delete this user?')" 
                                       style="border-color: #ff4444; color: #ff4444;">Delete</a>
                                <?php endif; ?>
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