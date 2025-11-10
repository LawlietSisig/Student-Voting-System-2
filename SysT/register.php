<?php
// register.php - Student registration page
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = trim($_POST['user_id']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $name = $last_name . ', ' . $first_name . ' ' . $middle_name; // Format: Last, First Middle
    $class = trim($_POST['class']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($user_id) || empty($last_name) || empty($first_name) || empty($class) || empty($password)) {
        $error = "User ID, Last Name, First Name, Class, and Password are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        $conn = getDBConnection();
        
        // Check if user ID already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $check_stmt->bind_param("s", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "User ID already exists. Please choose a different one.";
        } else {
            // Hash password and insert new user
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $role = 'student'; // Default role
            
            $stmt = $conn->prepare("INSERT INTO users (user_id, name, class, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $user_id, $name, $class, $password_hash, $role);
            
            if ($stmt->execute()) {
                // Log the registration in audit log
                $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'USER_REGISTERED', 'New user registration')");
                $log_stmt->bind_param("s", $user_id);
                $log_stmt->execute();
                
                $success = "Registration successful! You can now login with your credentials.";
                
                // Optional: Auto-login after registration
                // $_SESSION['user_id'] = $user_id;
                // $_SESSION['name'] = $name;
                // $_SESSION['class'] = $class;
                // $_SESSION['role'] = $role;
                // header("Location: index.php");
                // exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            
            $stmt->close();
        }
        
        $check_stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form-container">
        <h2 class="text-center">Student Voting System</h2>
        <h3 class="text-center mb-20">Register New Account</h3>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
                <br><br>
                <a href="login.php" class="btn" style="width: 100%; text-align: center;">Go to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID (Student Number):</label>
                    <input type="text" id="user_id" name="user_id" required 
                           placeholder="e.g., S2025005" 
                           value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">This will be your login username</small>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name: <span style="color: red;">*</span></label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="e.g., Masuki"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name: <span style="color: red;">*</span></label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="e.g., Grealle Sjeeg"
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name:</label>
                    <input type="text" id="middle_name" name="middle_name" 
                           placeholder="e.g., Pogi (Optional)"
                           value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">Optional</small>
                </div>
                
                <div class="form-group">
                    <label for="class">Class:</label>
                    <input type="text" id="class" name="class" required 
                           placeholder="e.g., 12A"
                           value="<?php echo isset($_POST['class']) ? htmlspecialchars($_POST['class']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required 
                           minlength="6"
                           placeholder="Minimum 6 characters">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           minlength="6"
                           placeholder="Re-enter your password">
                </div>
                
                <button type="submit" style="width: 100%;">Register</button>
            </form>
            
            <div class="mt-20 text-center">
                <p>Already have an account? <a href="login.php" style="color: #333; text-decoration: underline;">Login here</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>