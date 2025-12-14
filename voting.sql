CREATE DATABASE IF NOT EXISTS student_voting_system;
USE student_voting_system;

CREATE TABLE users (
    user_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_class (class),
    INDEX idx_role (role)
);

CREATE TABLE elections (
    election_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_by VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (status)
);

CREATE TABLE positions (
    position_id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    max_votes INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id) ON DELETE CASCADE,
    INDEX idx_election (election_id),
    INDEX idx_order (display_order)
);

CREATE TABLE candidates (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    short_bio TEXT,
    campaign_message TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES positions(position_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_candidate (position_id, user_id),
    INDEX idx_position (position_id)
);

CREATE TABLE votes (
    vote_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_id INT NOT NULL,
    candidate_id INT NOT NULL,
    voter_id VARCHAR(20) NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id),
    FOREIGN KEY (position_id) REFERENCES positions(position_id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id),
    FOREIGN KEY (voter_id) REFERENCES users(user_id),
    UNIQUE KEY unique_vote (election_id, position_id, voter_id),
    INDEX idx_election_voter (election_id, voter_id),
    INDEX idx_timestamp (voted_at)
);

CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_election (election_id),
    INDEX idx_rating (rating)
);

CREATE TABLE audit_log (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user_action (user_id, action),
    INDEX idx_timestamp (timestamp)
);

INSERT INTO users (user_id, name, class, password_hash, role) VALUES 
('admin001', 'System Administrator', 'ADMIN', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO users (user_id, name, class, password_hash) VALUES 
('S2025001', 'Ruy Vil', 'BSIT2A', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('S2025002', 'Gril Masuk', 'BSIT2A', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('S2025003', 'Bin Lore', 'BSIT2A', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('S2025004', 'Pat Llor', 'BSIT2A', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO elections (title, description, start_date, end_date, status, created_by) VALUES 
('BSIT2A CLASS ELECTION 2025', 'Annual election for BSIT 2A Class Officers', 
 '2025-10-01 08:00:00', '2025-10-05 17:00:00', 'completed', 'admin001');

INSERT INTO positions (election_id, title, description, display_order, max_votes) VALUES 
(1, 'President', 'BSIT 2A Class President', 1, 1),
(1, 'Vice President', 'Assists the President', 2, 1),
(1, 'Secretary', 'Manages Documentation', 3, 1),
(1, 'Treasurer', 'Manages Finances', 4, 1);

INSERT INTO candidates (position_id, user_id, short_bio, campaign_message) VALUES 
(1, 'S2025001', 'Aspiring Leader', 'I will ensure every voice is heard!'),
(1, 'S2025002', 'Kapogian lang ambag', 'Let''s make our class the best ever!'),
(2, 'S2025003', 'Hackathon Representative', 'Working together for success'),
(2, 'S2025004', 'Has Verified Gcash and Maya Accounts', 'Building bridges between students');

INSERT INTO votes (election_id, position_id, candidate_id, voter_id) VALUES 
(1, 1, 1, 'S2025003'),
(1, 1, 2, 'S2025004'),
(1, 2, 3, 'S2025001'),
(1, 2, 4, 'S2025002');

INSERT INTO feedback (election_id, user_id, rating, comments) VALUES 
(1, 'S2025001', 5, 'Smooth voting process!'),
(1, 'S2025002', 4, 'Good system, would like more candidate info');

CREATE VIEW election_results AS
SELECT 
    e.election_id,
    e.title as election_title,
    p.position_id,
    p.title as position_title,
    c.candidate_id,
    u.name as candidate_name,
    u.class as candidate_class,
    COUNT(v.vote_id) as total_votes
FROM elections e
JOIN positions p ON e.election_id = p.election_id
JOIN candidates c ON p.position_id = c.position_id
LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND p.position_id = v.position_id
JOIN users u ON c.user_id = u.user_id
WHERE e.status = 'completed'
GROUP BY e.election_id, p.position_id, c.candidate_id, u.name, u.class
ORDER BY p.display_order, total_votes DESC;

CREATE VIEW user_voting_status AS
SELECT 
    u.user_id,
    u.name,
    u.class,
    e.election_id,
    e.title as election_title,
    COUNT(DISTINCT v.position_id) as voted_positions,
    COUNT(DISTINCT p.position_id) as total_positions,
    CASE 
        WHEN COUNT(DISTINCT v.position_id) = COUNT(DISTINCT p.position_id) THEN 'Completed'
        WHEN COUNT(DISTINCT v.position_id) > 0 THEN 'Partial'
        ELSE 'Not Voted'
    END as voting_status
FROM users u
CROSS JOIN elections e
LEFT JOIN positions p ON e.election_id = p.election_id
LEFT JOIN votes v ON u.user_id = v.voter_id AND p.position_id = v.position_id AND e.election_id = v.election_id
WHERE u.role = 'student' AND e.status = 'active'
GROUP BY u.user_id, u.name, u.class, e.election_id, e.title;

DELIMITER //
CREATE PROCEDURE CastVote(
    IN p_election_id INT,
    IN p_position_id INT,
    IN p_candidate_id INT,
    IN p_voter_id VARCHAR(20)
)
BEGIN
    DECLARE already_voted INT DEFAULT 0;
    DECLARE election_active INT DEFAULT 0;
    
    SELECT COUNT(*) INTO election_active 
    FROM elections 
    WHERE election_id = p_election_id 
    AND status = 'active' 
    AND NOW() BETWEEN start_date AND end_date;
    
    SELECT COUNT(*) INTO already_voted 
    FROM votes 
    WHERE election_id = p_election_id 
    AND position_id = p_position_id 
    AND voter_id = p_voter_id;
    
    IF election_active = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Election is not active';
    ELSEIF already_voted > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Already voted for this position';
    ELSE
        INSERT INTO votes (election_id, position_id, candidate_id, voter_id)
        VALUES (p_election_id, p_position_id, p_candidate_id, p_voter_id);
        
        INSERT INTO audit_log (user_id, action, description)
        VALUES (p_voter_id, 'VOTE_CAST', CONCAT('Voted for candidate ', p_candidate_id, ' in position ', p_position_id));
    END IF;
END//
DELIMITER ;

CREATE INDEX idx_votes_election_position ON votes(election_id, position_id);
CREATE INDEX idx_candidates_position ON candidates(position_id);
CREATE INDEX idx_elections_dates ON elections(start_date, end_date);
CREATE INDEX idx_users_class_role ON users(class, role);

SELECT 'Database schema created successfully!' as status;

SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'student') as student_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_users,
    (SELECT COUNT(*) FROM elections) as total_elections,
    (SELECT COUNT(*) FROM positions) as total_positions,
    (SELECT COUNT(*) FROM candidates) as total_candidates,
    (SELECT COUNT(*) FROM votes) as total_votes;