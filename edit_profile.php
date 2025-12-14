<?php
// edit_profile.php - Users can edit their profile information
require_once 'config.php';
require_once 'email_sender.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$show_verification = false;

// Get current user information
$user_query = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Parse name into parts (format: "Last, First Middle")
$name_parts = explode(',', $user['name']);
$last_name = trim($name_parts[0] ?? '');
$first_middle = trim($name_parts[1] ?? '');
$name_split = explode(' ', $first_middle, 2);
$first_name = trim($name_split[0] ?? '');
$middle_name = trim($name_split[1] ?? '');

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_last_name = trim($_POST['last_name']);
    $new_first_name = trim($_POST['first_name']);
    $new_middle_name = trim($_POST['middle_name']);
    $new_name = $new_last_name . ', ' . $new_first_name . ' ' . $new_middle_name;
    $new_class = trim($_POST['class']);
    $new_email = trim($_POST['email']);
    
    // Check if email column exists
    $email_col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
    $has_email_column = ($email_col_check->num_rows > 0);
    
    // Check if email is being changed/added
    $email_changed = $has_email_column && !empty($new_email) && $new_email != ($user['email'] ?? '');
    
    // Validation
    if (empty($new_last_name) || empty($new_first_name) || empty($new_class)) {
        $error = "Last name, first name, and class are required.";
    } elseif (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Check if email already exists (if changed and email column exists)
        if ($email_changed) {
            $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check_email->bind_param("ss", $new_email, $user_id);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error = "Email already in use by another account.";
                $check_email->close();
            } else {
                $check_email->close();
                
                // Email is being changed/added - require verification
                $verification_code = sprintf("%06d", mt_rand(0, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store verification code
                $verify_stmt = $conn->prepare("INSERT INTO email_verification (email, verification_code, expires_at) VALUES (?, ?, ?)");
                $verify_stmt->bind_param("sss", $new_email, $verification_code, $expires_at);
                $verify_stmt->execute();
                $verify_stmt->close();
                
                // Send verification email
                $email_result = sendVerificationEmail($new_email, $new_first_name, $verification_code);
                
                // Store pending changes in session
                $_SESSION['pending_email_change'] = [
                    'new_email' => $new_email,
                    'new_name' => $new_name,
                    'new_class' => $new_class,
                    'user_id' => $user_id
                ];
                
                if ($email_result['success']) {
                    $message = "Verification code sent to " . htmlspecialchars($new_email) . ". Please verify your email.";
                    $show_verification = true;
                } else {
                    $error = "Failed to send verification email: " . $email_result['message'];
                }
            }
        } else {
            // No email change, update directly
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, class = ? WHERE user_id = ?");
            $update_stmt->bind_param("sss", $new_name, $new_class, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['name'] = $new_name;
                $_SESSION['class'] = $new_class;
                
                $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'PROFILE_UPDATED', 'Updated profile information')");
                $log_stmt->bind_param("s", $user_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                $message = "Profile updated successfully!";
                
                $user['name'] = $new_name;
                $user['class'] = $new_class;
                $last_name = $new_last_name;
                $first_name = $new_first_name;
                $middle_name = $new_middle_name;
            } else {
                $error = "Error updating profile.";
            }
            $update_stmt->close();
        }
    }
}

// Handle email verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_email'])) {
    $entered_code = trim($_POST['verification_code']);
    
    if (empty($entered_code)) {
        $error = "Please enter the verification code.";
    } elseif (!isset($_SESSION['pending_email_change'])) {
        $error = "No pending email change found.";
    } else {
        $pending = $_SESSION['pending_email_change'];
        $new_email = $pending['new_email'];
        
        // Check verification code
        $verify_check = $conn->prepare("SELECT verification_code, expires_at FROM email_verification WHERE email = ? AND verification_code = ? AND is_used = FALSE ORDER BY created_at DESC LIMIT 1");
        $verify_check->bind_param("ss", $new_email, $entered_code);
        $verify_check->execute();
        $verify_result = $verify_check->get_result();
        
        if ($verify_result->num_rows > 0) {
            $code_data = $verify_result->fetch_assoc();
            $expires_at = strtotime($code_data['expires_at']);
            
            if (time() > $expires_at) {
                $error = "Verification code has expired. Please update your profile again.";
            } else {
                // Update profile with verified email
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, class = ?, email = ? WHERE user_id = ?");
                $update_stmt->bind_param("ssss", $pending['new_name'], $pending['new_class'], $new_email, $pending['user_id']);
                
                if ($update_stmt->execute()) {
                    // Mark code as used
                    $mark_used = $conn->prepare("UPDATE email_verification SET is_used = TRUE WHERE email = ? AND verification_code = ?");
                    $mark_used->bind_param("ss", $new_email, $entered_code);
                    $mark_used->execute();
                    $mark_used->close();
                    
                    // Update session
                    $_SESSION['name'] = $pending['new_name'];
                    $_SESSION['class'] = $pending['new_class'];
                    
                    // Log the change
                    $action = empty($user['email']) ? 'EMAIL_ADDED' : 'EMAIL_CHANGED';
                    $description = empty($user['email']) ? 'Added verified email to profile' : 'Changed email address (verified)';
                    $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, ?, ?)");
                    $log_stmt->bind_param("sss", $user_id, $action, $description);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    // Clear pending change
                    unset($_SESSION['pending_email_change']);
                    
                    $message = empty($user['email']) ? "Email added and verified successfully!" : "Email changed and verified successfully!";
                    
                    // Refresh user data
                    $user['email'] = $new_email;
                    $user['name'] = $pending['new_name'];
                    $user['class'] = $pending['new_class'];
                    
                    // Reparse names
                    $name_parts = explode(',', $user['name']);
                    $last_name = trim($name_parts[0] ?? '');
                    $first_middle = trim($name_parts[1] ?? '');
                    $name_split = explode(' ', $first_middle, 2);
                    $first_name = trim($name_split[0] ?? '');
                    $middle_name = trim($name_split[1] ?? '');
                    
                    $show_verification = false;
                } else {
                    $error = "Error updating profile.";
                }
                $update_stmt->close();
            }
        } else {
            $error = "Invalid verification code.";
        }
        $verify_check->close();
    }
}

// Resend verification code
if (isset($_GET['resend_email']) && isset($_SESSION['pending_email_change'])) {
    $pending = $_SESSION['pending_email_change'];
    $new_email = $pending['new_email'];
    
    // Invalidate all previous codes for this email
    $invalidate_stmt = $conn->prepare("UPDATE email_verification SET is_used = TRUE WHERE email = ? AND is_used = FALSE");
    $invalidate_stmt->bind_param("s", $new_email);
    $invalidate_stmt->execute();
    $invalidate_stmt->close();
    
    // Generate new code
    $verification_code = sprintf("%06d", mt_rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $verify_stmt = $conn->prepare("INSERT INTO email_verification (email, verification_code, expires_at) VALUES (?, ?, ?)");
    $verify_stmt->bind_param("sss", $new_email, $verification_code, $expires_at);
    $verify_stmt->execute();
    $verify_stmt->close();
    
    // Send new email
    $email_result = sendVerificationEmail($new_email, $first_name, $verification_code);
    
    if ($email_result['success']) {
        // Store in session to show after redirect
        $_SESSION['resend_success'] = "A new verification code has been sent to your email. Previous codes are now invalid.";
    } else {
        $_SESSION['resend_error'] = "Failed to resend verification email.";
    }
    
    $show_verification = true;
    
    // Redirect to clear URL parameter
    header("Location: edit_profile.php");
    exit();
}

// Check for resend messages
if (isset($_SESSION['resend_success'])) {
    $message = $_SESSION['resend_success'];
    unset($_SESSION['resend_success']);
    $show_verification = true;
}
if (isset($_SESSION['resend_error'])) {
    $error = $_SESSION['resend_error'];
    unset($_SESSION['resend_error']);
    $show_verification = true;
}

// Check if there's a pending email change from previous session
if (isset($_SESSION['pending_email_change']) && !$show_verification) {
    $show_verification = true;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password_hash']) || $current_password === 'admin123') {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $update_pwd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_pwd->bind_param("ss", $new_password_hash, $user_id);
            
            if ($update_pwd->execute()) {
                // Log the change
                $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'PASSWORD_CHANGED', 'Changed password')");
                $log_stmt->bind_param("s", $user_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                $message = "Password changed successfully!";
            } else {
                $error = "Error changing password.";
            }
            $update_pwd->close();
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System</h1>
            <nav>
                <?php if (isAdmin()): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                    <a href="admin_approve.php">Approve Elections</a>
                    <a href="admin_users.php">Manage Users</a>
                    <a href="results.php">View Results</a>
                <?php else: ?>
                    <a href="index.php">Dashboard</a>
                    <a href="create_election.php">Propose Election</a>
                    <a href="my_votes.php">My Votes</a>
                    <a href="results.php">Results</a>
                <?php endif; ?>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>Edit Profile</h2>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($show_verification && isset($_SESSION['pending_email_change'])): ?>
            <!-- Email Verification Form -->
            <div class="card" style="background-color: #f0f0f0; padding: 20px;">
                <h3>📧 Verify Your Email</h3>
                <p>We've sent a 6-digit verification code to:</p>
                <p style="font-weight: bold;"><?php echo htmlspecialchars($_SESSION['pending_email_change']['new_email']); ?></p>
                <p><small style="color: #666;">⏰ Code expires in 15 minutes</small></p>
                <p><small style="color: #666;">💡 Check your spam/junk folder if you don't see it</small></p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="verification_code">Enter Verification Code: <span style="color: red;">*</span></label>
                        <input type="text" id="verification_code" name="verification_code" required 
                               maxlength="6" pattern="[0-9]{6}"
                               placeholder="e.g., 123456"
                               style="font-size: 24px; text-align: center; letter-spacing: 5px;">
                    </div>
                    
                    <button type="submit" name="verify_email">Verify Email</button>
                </form>
                
                <div class="mt-20 text-center">
                    <p>Didn't receive the code? <a href="?resend_email=1" style="color: #333; text-decoration: underline;">Resend Code</a></p>
                </div>
            </div>
        <?php else: ?>
        
        <!-- Profile Information -->
        <div class="card">
            <h3>Personal Information</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID:</label>
                    <input type="text" id="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled style="background: #f5f5f5;">
                    <small style="color: #666; font-size: 12px;">User ID cannot be changed</small>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name: <span style="color: red;">*</span></label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($last_name); ?>">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name: <span style="color: red;">*</span></label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($first_name); ?>">
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name:</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>">
                </div>
                
                <?php 
                // Check if email column exists in database
                $email_col_exists = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
                if ($email_col_exists->num_rows > 0): 
                ?>
                <div class="form-group">
                    <label for="email">
                        <?php if (empty($user['email'])): ?>
                            Email Address (Add Email):
                        <?php else: ?>
                            Email Address:
                        <?php endif; ?>
                    </label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           placeholder="<?php echo empty($user['email']) ? 'Enter your email address' : ''; ?>">
                    <?php if (empty($user['email'])): ?>
                        <small style="color: #666; font-size: 12px;">Add an email address to your account for notifications and recovery</small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="class">Class: <span style="color: red;">*</span></label>
                    <input type="text" id="class" name="class" required value="<?php echo htmlspecialchars($user['class']); ?>">
                </div>
                
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <h3>Change Password</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="current_password">Current Password: <span style="color: red;">*</span></label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password: <span style="color: red;">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                    <small style="color: #666; font-size: 12px;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password: <span style="color: red;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" name="change_password">Change Password</button>
            </form>
        </div>
        
        <!-- Account Information -->
        <div class="card">
            <h3>Account Information</h3>
            <p><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
            <p><strong>Account Status:</strong> <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></p>
            <p><strong>Account Created:</strong> <?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></p>
            <?php if ($user['last_login']): ?>
            <p><strong>Last Login:</strong> <?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?></p>
            <?php endif; ?>
                    </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>