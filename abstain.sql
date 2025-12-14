CREATE TABLE IF NOT EXISTS abstain_votes (
    abstain_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_id INT NOT NULL,
    voter_id VARCHAR(20) NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(election_id),
    FOREIGN KEY (position_id) REFERENCES positions(position_id),
    FOREIGN KEY (voter_id) REFERENCES users(user_id),
    UNIQUE KEY unique_abstain (election_id, position_id, voter_id),
    INDEX idx_election_voter (election_id, voter_id)
);

SELECT 'Abstain votes table created successfully!' as status;