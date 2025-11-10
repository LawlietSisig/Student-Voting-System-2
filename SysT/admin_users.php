<?php
// admin_users.php - Manage users
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $class = $_POST['class'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (user_id, name, class, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $user_id, $name, $class, $password_hash, $role);
    
    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $error = "Error creating user. User ID may already exist.";
    }
}

// Handle user status toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    
    if ($stmt->execute()) {
        $message = "User status updated!";
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY role, name";
$users_result = $conn->query($users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Users</h1>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_users.php">Manage Users</a>
                <a href="results.php">View Results</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <h2>Create New User</h2>
        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID:</label>
                    <input type="text" id="user_id" name="user_id" required placeholder="e.g., S2025005">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Class:</label>
                    <input type="text" id="class" name="class" required placeholder="e.g., 12A">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" name="create_user">Create User</button>
            </form>
        </div>
        
        <h2 class="mt-20">All Users</h2>
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['class']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                    <td>
                        <a href="?toggle=1&id=<?php echo urlencode($user['user_id']); ?>" class="btn btn-secondary">
                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>