<?php
// register.php - Student registration page with email verification (PRODUCTION)
require_once 'config.php';
require_once 'email_sender.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Step 1: Handle registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $user_id = trim($_POST['user_id']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $name = $last_name . ', ' . $first_name . ' ' . $middle_name;
    $email = trim($_POST['email']);
    $class = trim($_POST['class']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($user_id) || empty($last_name) || empty($first_name) || empty($email) || empty($class) || empty($password)) {
        $error = "User ID, Last Name, First Name, Email, Class, and Password are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        $conn = getDBConnection();
        
        // Check if user ID already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        if (!$check_stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $check_stmt->bind_param("s", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            // Check if email exists
            $email_exists = false;
            $check_email = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
            if ($check_email->num_rows > 0) {
                $check_email_stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
                $check_email_stmt->bind_param("s", $email);
                $check_email_stmt->execute();
                $email_result = $check_email_stmt->get_result();
                $email_exists = ($email_result->num_rows > 0);
                $check_email_stmt->close();
            }
            
            if ($result->num_rows > 0) {
                $error = "User ID already exists. Please choose a different one.";
            } elseif ($email_exists) {
                $error = "Email already registered. Please use a different email.";
            } else {
                // Generate 6-digit verification code
                $verification_code = sprintf("%06d", mt_rand(0, 999999));
                
                // Check if email_verification table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'email_verification'");
                if ($table_check->num_rows > 0) {
                    // Store verification code
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $verify_stmt = $conn->prepare("INSERT INTO email_verification (email, verification_code, expires_at) VALUES (?, ?, ?)");
                    $verify_stmt->bind_param("sss", $email, $verification_code, $expires_at);
                    $verify_stmt->execute();
                    $verify_stmt->close();
                    
                    // Send verification email
                    $email_result = sendVerificationEmail($email, $first_name, $verification_code);
                    
                    if ($email_result['success']) {
                        // Store registration data in session
                        $_SESSION['pending_registration'] = [
                            'user_id' => $user_id,
                            'name' => $name,
                            'email' => $email,
                            'class' => $class,
                            'password_hash' => password_hash($password, PASSWORD_BCRYPT)
                        ];
                        
                        $success = "Verification code sent! Please check your email inbox (and spam folder).";
                        $step = 2;
                    } else {
                        $error = "Failed to send verification email. " . $email_result['message'];
                    }
                } else {
                    $error = "Email verification table not found. Please run the SQL setup script first.";
                }
            }
            
            $check_stmt->close();
        }
        $conn->close();
    }
}

// Step 2: Handle verification code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    $entered_code = trim($_POST['verification_code']);
    
    if (empty($entered_code)) {
        $error = "Please enter the verification code";
        $step = 2; // Stay on verification page
    } elseif (!isset($_SESSION['pending_registration'])) {
        $error = "No pending registration found. Please register again.";
        $step = 1;
    } else {
        $conn = getDBConnection();
        $email = $_SESSION['pending_registration']['email'];
        
        // First check if this code exists and was used/invalidated
        $check_old_code = $conn->prepare("SELECT is_used, expires_at FROM email_verification WHERE email = ? AND verification_code = ? ORDER BY created_at DESC LIMIT 1");
        $check_old_code->bind_param("ss", $email, $entered_code);
        $check_old_code->execute();
        $old_code_result = $check_old_code->get_result();
        
        if ($old_code_result->num_rows > 0) {
            $old_code = $old_code_result->fetch_assoc();
            if ($old_code['is_used'] == 1) {
                $error = "This verification code has been invalidated. Please use the new code sent to your email.";
                $step = 2;
                $check_old_code->close();
                $conn->close();
            } else {
                // Code exists and not used, check if expired
                $expires_at = strtotime($old_code['expires_at']);
                if (time() > $expires_at) {
                    $error = "Verification code has expired. Please request a new code.";
                    $step = 2;
                    $check_old_code->close();
                    $conn->close();
                } else {
                    // Code is valid, proceed with verification
                    $check_old_code->close();
                    
                    // Get registration data
                    $reg_data = $_SESSION['pending_registration'];
                    $role = 'student';
                    $is_verified = 1;
                    
                    // Check if columns exist
                    $email_col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
                    $verified_col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
                    
                    if ($email_col_check->num_rows > 0 && $verified_col_check->num_rows > 0) {
                        $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, class, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssi", $reg_data['user_id'], $reg_data['name'], $reg_data['email'], $reg_data['class'], $reg_data['password_hash'], $role, $is_verified);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO users (user_id, name, class, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $reg_data['user_id'], $reg_data['name'], $reg_data['class'], $reg_data['password_hash'], $role);
                    }
                    
                    if ($stmt->execute()) {
                        // Mark verification code as used
                        $mark_used = $conn->prepare("UPDATE email_verification SET is_used = TRUE WHERE email = ? AND verification_code = ?");
                        $mark_used->bind_param("ss", $email, $entered_code);
                        $mark_used->execute();
                        $mark_used->close();
                        
                        // Log registration
                        $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'USER_REGISTERED', 'New user registration with email verification')");
                        $log_stmt->bind_param("s", $reg_data['user_id']);
                        $log_stmt->execute();
                        $log_stmt->close();
                        
                        // Send welcome email
                        sendWelcomeEmail($reg_data['email'], $reg_data['name'], $reg_data['user_id']);
                        
                        // Clear session data
                        unset($_SESSION['pending_registration']);
                        
                        $success = "Registration successful! Your email has been verified. You can now login.";
                        $step = 3;
                    } else {
                        $error = "Registration failed: " . $stmt->error;
                        $step = 2;
                    }
                    
                    $stmt->close();
                    $conn->close();
                }
            }
        } else {
            $error = "Invalid verification code. Please check and try again.";
            $step = 2;
            $check_old_code->close();
            $conn->close();
        }
    }
}

// Resend verification code
if (isset($_GET['resend']) && isset($_SESSION['pending_registration'])) {
    $conn = getDBConnection();
    $email = $_SESSION['pending_registration']['email'];
    $name = $_SESSION['pending_registration']['name'];
    
    // Invalidate all previous codes for this email
    $invalidate_stmt = $conn->prepare("UPDATE email_verification SET is_used = TRUE WHERE email = ? AND is_used = FALSE");
    $invalidate_stmt->bind_param("s", $email);
    $invalidate_stmt->execute();
    $invalidate_stmt->close();
    
    // Generate new code
    $verification_code = sprintf("%06d", mt_rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $verify_stmt = $conn->prepare("INSERT INTO email_verification (email, verification_code, expires_at) VALUES (?, ?, ?)");
    $verify_stmt->bind_param("sss", $email, $verification_code, $expires_at);
    $verify_stmt->execute();
    $verify_stmt->close();
    
    // Send new verification email
    $first_name = explode(',', $name)[1] ?? 'User';
    $first_name = trim($first_name);
    $email_result = sendVerificationEmail($email, $first_name, $verification_code);
    
    if ($email_result['success']) {
        // Store success message in session to show after redirect
        $_SESSION['resend_success'] = "A new verification code has been sent to your email. Previous codes are now invalid.";
    } else {
        $_SESSION['resend_error'] = "Failed to resend verification email: " . $email_result['message'];
    }
    
    $step = 2;
    $conn->close();
    
    // Redirect to remove the resend parameter from URL
    header("Location: register.php?step=2");
    exit();
}

// Check for resend messages from session
if (isset($_SESSION['resend_success'])) {
    $success = $_SESSION['resend_success'];
    unset($_SESSION['resend_success']);
}
if (isset($_SESSION['resend_error'])) {
    $error = $_SESSION['resend_error'];
    unset($_SESSION['resend_error']);
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
        
        <?php
        // Check if database is set up correctly
        $conn_check = getDBConnection();
        $email_col = $conn_check->query("SHOW COLUMNS FROM users LIKE 'email'");
        $verify_table = $conn_check->query("SHOW TABLES LIKE 'email_verification'");
        
        if ($email_col->num_rows == 0 || $verify_table->num_rows == 0):
        ?>
            <div class="message error">
                <strong>⚠️ Database Setup Required!</strong><br>
                Please run the SQL script to add email verification support.
            </div>
        <?php 
        endif;
        $conn_check->close();
        ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <!-- Step 1: Registration Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID (Student Number): <span style="color: red;">*</span></label>
                    <input type="text" id="user_id" name="user_id" required 
                           placeholder="e.g., S2025005" 
                           value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">This will be your login username</small>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name: <span style="color: red;">*</span></label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="e.g., Dela Cruz"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name: <span style="color: red;">*</span></label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="e.g., Juan"
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name:</label>
                    <input type="text" id="middle_name" name="middle_name" 
                           placeholder="e.g., Santos (optional)"
                           value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">Optional</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address: <span style="color: red;">*</span></label>
                    <input type="email" id="email" name="email" required 
                           placeholder="e.g., juan.delacruz@example.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <small style="color: #666; font-size: 12px;">A verification code will be sent to this email</small>
                </div>
                
                <div class="form-group">
                    <label for="class">Class: <span style="color: red;">*</span></label>
                    <input type="text" id="class" name="class" required 
                           placeholder="e.g., BSIT2A"
                           value="<?php echo isset($_POST['class']) ? htmlspecialchars($_POST['class']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password: <span style="color: red;">*</span></label>
                    <input type="password" id="password" name="password" required 
                           minlength="6"
                           placeholder="Minimum 6 characters">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password: <span style="color: red;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           minlength="6"
                           placeholder="Re-enter your password">
                </div>
                
                <button type="submit" name="register" style="width: 100%;">Register</button>
            </form>
            
            <div class="mt-20 text-center">
                <p>Already have an account? <a href="login.php" style="color: #333; text-decoration: underline;">Login here</a></p>
            </div>
            
        <?php elseif ($step == 2): ?>
            <!-- Step 2: Email Verification -->
            <div class="card" style="background-color: #f0f0f0; padding: 15px; margin-bottom: 20px;">
                <h4>📧 Verify Your Email</h4>
                <p>We've sent a 6-digit verification code to:</p>
                <p style="font-weight: bold;"><?php echo htmlspecialchars($_SESSION['pending_registration']['email']); ?></p>
                <p><small style="color: #666;">⏰ Code expires in 15 minutes</small></p>
                <p><small style="color: #666;">💡 Check your spam/junk folder if you don't see it</small></p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="verification_code">Enter Verification Code: <span style="color: red;">*</span></label>
                    <input type="text" id="verification_code" name="verification_code" required 
                           maxlength="6" pattern="[0-9]{6}"
                           placeholder="e.g., 123456"
                           style="font-size: 24px; text-align: center; letter-spacing: 5px;">
                </div>
                
                <button type="submit" name="verify_code" style="width: 100%;">Verify Email</button>
            </form>
            
            <div class="mt-20 text-center">
                <p>Didn't receive the code? <a href="?step=2&resend=1" style="color: #333; text-decoration: underline;">Resend Code</a></p>
                <p><a href="register.php" style="color: #666; text-decoration: underline;">Start Over</a></p>
            </div>
            
        <?php elseif ($step == 3): ?>
            <!-- Step 3: Success -->
            <div class="card" style="text-align: center; padding: 30px;">
                <h2 style="color: #333; margin-bottom: 20px;">✓ Registration Complete!</h2>
                <p>Your account has been successfully created and verified.</p>
                <p>A welcome email has been sent to your inbox.</p>
                <p style="margin-top: 20px;">
                    <a href="login.php" class="btn" style="width: 100%; display: block; text-align: center;">Go to Login</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>