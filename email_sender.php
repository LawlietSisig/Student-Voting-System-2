<?php
// email_sender.php - Functions for sending emails

require_once 'email_config.php';

// Check if PHPMailer is installed
function isPhpMailerAvailable() {
    // Check composer autoload
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    // Check manual installation
    if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        return true;
    }
    
    return false;
}

/**
 * Send verification email
 * @param string $to_email Recipient email
 * @param string $to_name Recipient name
 * @param string $code Verification code
 * @return array ['success' => bool, 'message' => string]
 */
function sendVerificationEmail($to_email, $to_name, $code) {
    // Demo mode - don't send actual emails
    if (!EMAIL_ENABLED) {
        return [
            'success' => true,
            'message' => 'Demo mode: Email not sent',
            'demo_code' => $code
        ];
    }
    
    // Check if PHPMailer is available
    if (!isPhpMailerAvailable()) {
        return [
            'success' => false,
            'message' => 'PHPMailer not installed. Please install it first.'
        ];
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification Code - Student Voting System';
        $mail->Body = getVerificationEmailHTML($code, $to_name);
        $mail->AltBody = "Your verification code is: $code\n\nThis code will expire in 15 minutes.";
        
        $mail->send();
        
        return [
            'success' => true,
            'message' => 'Verification email sent successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Send welcome email after successful registration
 */
function sendWelcomeEmail($to_email, $to_name, $user_id) {
    if (!EMAIL_ENABLED || !isPhpMailerAvailable()) {
        return ['success' => true, 'message' => 'Demo mode'];
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Student Voting System';
        $mail->Body = "
        <h2>Welcome $to_name!</h2>
        <p>Your account has been successfully created and verified.</p>
        <p><strong>User ID:</strong> $user_id</p>
        <p>You can now login and participate in elections.</p>
        <p>Thank you for joining!</p>
        ";
        
        $mail->send();
        return ['success' => true, 'message' => 'Welcome email sent'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>