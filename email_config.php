<?php
// email_config.php - Email configuration for PHPMailer

// Set to TRUE to enable email sending, FALSE for demo mode
define('EMAIL_ENABLED', true);  // Change to TRUE when ready

// SMTP Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);  // 587 for TLS, 465 for SSL
define('SMTP_SECURE', 'tls');  // 'tls' or 'ssl'
define('SMTP_USERNAME', 'grealle.sjeeg.d.masuki@gmail.com');  // Your email
define('SMTP_PASSWORD', 'hhuvhabzsmctjgby');  // Gmail App Password
define('SMTP_FROM_EMAIL', 'grealle.sjeeg.d.masuki@gmail.com');
define('SMTP_FROM_NAME', 'Student Voting System');

// Email templates
function getVerificationEmailHTML($code, $name) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #333; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 30px; }
            .code { font-size: 32px; font-weight: bold; color: #333; text-align: center; 
                    padding: 20px; background: white; border: 2px dashed #333; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Student Voting System</h1>
            </div>
            <div class='content'>
                <h2>Hello $name,</h2>
                <p>Thank you for registering with the Student Voting System!</p>
                <p>Your verification code is:</p>
                <div class='code'>$code</div>
                <p>This code will expire in <strong>15 minutes</strong>.</p>
                <p>If you didn't request this code, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Student Voting System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>