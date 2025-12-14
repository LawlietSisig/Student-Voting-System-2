ALTER TABLE users ADD COLUMN email VARCHAR(100) UNIQUE AFTER class;
ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE AFTER is_active;

CREATE TABLE email_verification (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    verification_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_used BOOLEAN DEFAULT FALSE,
    INDEX idx_email (email),
    INDEX idx_code (verification_code)
);

UPDATE users SET is_verified = TRUE WHERE role = 'admin';