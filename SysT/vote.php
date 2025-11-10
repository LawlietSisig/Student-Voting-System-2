<?php
// vote.php - Voting page
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;
$message = '';
$error = '';

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['position_id']) && isset($_POST['candidate_ids']) && is_array($_POST['candidate_ids'])) {
        $position_id = intval($_POST['position_id']);
        $candidate_ids = array_map('intval', $_POST['candidate_ids']); // Convert all to integers
        
        // Get max_votes for this position
        $max_query = $conn->prepare("SELECT max_votes FROM positions WHERE position_id = ?");
        $max_query->bind_param("i", $position_id);
        $max_query->execute();
        $max_result = $max_query->get_result()->fetch_assoc();
        $max_votes = $max_result['max_votes'];
        $max_query->close();
        
        // Check if user already voted for this position
        $vote_check = $conn->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE election_id = ? AND position_id = ? AND voter_id = ?");
        $vote_check->bind_param("iis", $election_id, $position_id, $user_id);
        $vote_check->execute();
        $check_result = $vote_check->get_result()->fetch_assoc();
        $already_voted_count = $check_result['vote_count'];
        $vote_check->close();
        
        if ($already_voted_count > 0) {
            $error = "You have already voted for this position.";
        } elseif (count($candidate_ids) > $max_votes) {
            $error = "You can only vote for up to " . $max_votes . " candidate(s) for this position.";
        } elseif (count($candidate_ids) == 0) {
            $error = "Please select at least one candidate.";
        } else {
            // Check if election is active
            $election_check = $conn->prepare("SELECT status FROM elections WHERE election_id = ? AND status = 'active'");
            $election_check->bind_param("i", $election_id);
            $election_check->execute();
            $is_active = $election_check->get_result()->num_rows > 0;
            $election_check->close();
            
            if (!$is_active) {
                $error = "Election is not active.";
            } else {
                // Insert votes for each selected candidate
                $all_success = true;
                $conn->begin_transaction();
                
                try {
                    foreach ($candidate_ids as $candidate_id) {
                        $vote_stmt = $conn->prepare("INSERT INTO votes (election_id, position_id, candidate_id, voter_id) VALUES (?, ?, ?, ?)");
                        $vote_stmt->bind_param("iiis", $election_id, $position_id, $candidate_id, $user_id);
                        
                        if (!$vote_stmt->execute()) {
                            throw new Exception("Error casting vote");
                        }
                        $vote_stmt->close();
                    }
                    
                    // Log the vote
                    $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'VOTE_CAST', ?)");
                    $log_desc = "Voted for " . count($candidate_ids) . " candidate(s) in position $position_id";
                    $log_stmt->bind_param("ss", $user_id, $log_desc);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $conn->commit();
                    $message = "Vote(s) cast successfully!";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error casting vote: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Please select at least one candidate.";
    }
}

// Get election details
$election_query = "SELECT * FROM elections WHERE election_id = ? AND status = 'active'";
$election_stmt = $conn->prepare($election_query);
$election_stmt->bind_param("i", $election_id);
$election_stmt->execute();
$election = $election_stmt->get_result()->fetch_assoc();

if (!$election) {
    header("Location: index.php");
    exit();
}

// Get positions and candidates
$positions_query = "SELECT * FROM positions WHERE election_id = ? ORDER BY display_order";
$positions_stmt = $conn->prepare($positions_query);
$positions_stmt->bind_param("i", $election_id);
$positions_stmt->execute();
$positions_result = $positions_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System</h1>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="my_votes.php">My Votes</a>
                <a href="results.php">Results</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2><?php echo htmlspecialchars($election['title']); ?></h2>
        <p><?php echo htmlspecialchars($election['description']); ?></p>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php while ($position = $positions_result->fetch_assoc()): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($position['title']); ?></h3>
                <p><?php echo htmlspecialchars($position['description']); ?></p>
                <p><strong>You can vote for up to <?php echo $position['max_votes']; ?> candidate(s)</strong></p>
                
                <?php
                // Check if already voted for this position
                $vote_check_query = "SELECT v.vote_id, c.candidate_id, u.name 
                    FROM votes v 
                    JOIN candidates c ON v.candidate_id = c.candidate_id 
                    JOIN users u ON c.user_id = u.user_id 
                    WHERE v.election_id = ? AND v.position_id = ? AND v.voter_id = ?";
                $vote_check_stmt = $conn->prepare($vote_check_query);
                $vote_check_stmt->bind_param("iis", $election_id, $position['position_id'], $user_id);
                $vote_check_stmt->execute();
                $existing_votes = $vote_check_stmt->get_result();
                
                if ($existing_votes->num_rows > 0):
                ?>
                    <p style="color: #333; font-weight: bold;">✓ You voted for:</p>
                    <ul>
                        <?php while ($voted = $existing_votes->fetch_assoc()): ?>
                            <li><?php echo htmlspecialchars($voted['name']); ?></li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <?php
                    // Get candidates for this position
                    $candidates_query = "SELECT c.*, u.name, u.class 
                        FROM candidates c 
                        JOIN users u ON c.user_id = u.user_id 
                        WHERE c.position_id = ? AND c.is_active = 1";
                    $candidates_stmt = $conn->prepare($candidates_query);
                    $candidates_stmt->bind_param("i", $position['position_id']);
                    $candidates_stmt->execute();
                    $candidates_result = $candidates_stmt->get_result();
                    ?>
                    
                    <form method="POST" action="" onsubmit="return validateVotes(this, <?php echo $position['max_votes']; ?>)">
                        <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                        
                        <div class="candidate-list">
                            <?php while ($candidate = $candidates_result->fetch_assoc()): ?>
                                <div class="candidate-card">
                                    <input type="checkbox" 
                                           name="candidate_ids[]" 
                                           value="<?php echo $candidate['candidate_id']; ?>" 
                                           id="candidate_<?php echo $candidate['candidate_id']; ?>"
                                           style="display: none;">
                                    <label for="candidate_<?php echo $candidate['candidate_id']; ?>" 
                                           style="cursor: pointer; display: block;"
                                           onclick="toggleCheckbox(this, <?php echo $candidate['candidate_id']; ?>)">
                                        <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                        <p><strong>Class:</strong> <?php echo htmlspecialchars($candidate['class']); ?></p>
                                        <p><strong>Bio:</strong> <?php echo htmlspecialchars($candidate['short_bio']); ?></p>
                                        <p><em>"<?php echo htmlspecialchars($candidate['campaign_message']); ?>"</em></p>
                                        <div class="checkbox-indicator" id="indicator_<?php echo $candidate['candidate_id']; ?>" 
                                             style="margin-top: 10px; padding: 8px; border: 2px solid #ddd; border-radius: 4px; text-align: center; background: white;">
                                            Select
                                        </div>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <button type="submit" style="width: 100%; margin-top: 20px;">Submit Vote(s)</button>
                    </form>
                    
                    <script>
                    function toggleCheckbox(label, candidateId) {
                        event.preventDefault(); // Prevent default behavior
                        event.stopPropagation(); // Stop event bubbling
                        
                        const checkbox = document.getElementById('candidate_' + candidateId);
                        const indicator = document.getElementById('indicator_' + candidateId);
                        
                        checkbox.checked = !checkbox.checked;
                        
                        if (checkbox.checked) {
                            indicator.style.background = '#333';
                            indicator.style.color = 'white';
                            indicator.style.border = '2px solid #333';
                            indicator.textContent = '✓ Selected';
                        } else {
                            indicator.style.background = 'white';
                            indicator.style.color = '#333';
                            indicator.style.border = '2px solid #ddd';
                            indicator.textContent = 'Select';
                        }
                    }
                    
                    function validateVotes(form, maxVotes) {
                        const checked = form.querySelectorAll('input[name="candidate_ids[]"]:checked');
                        const positionTitle = form.closest('.card').querySelector('h3').textContent;
                        
                        if (checked.length === 0) {
                            alert('Please select at least one candidate.');
                            return false;
                        }
                        
                        if (checked.length > maxVotes) {
                            alert('You can only vote for up to ' + maxVotes + ' candidate(s) for this position.');
                            return false;
                        }
                        
                        // Get names of selected candidates
                        let candidateNames = [];
                        checked.forEach(function(checkbox) {
                            const card = checkbox.closest('.candidate-card');
                            const name = card.querySelector('h4').textContent;
                            candidateNames.push(name);
                        });
                        
                        // Create confirmation message
                        let confirmMessage = 'Position: ' + positionTitle + '\n\n';
                        confirmMessage += 'You are about to vote for:\n';
                        candidateNames.forEach(function(name, index) {
                            confirmMessage += (index + 1) + '. ' + name + '\n';
                        });
                        confirmMessage += '\n⚠️ Are you sure? This action cannot be undone!';
                        
                        return confirm(confirmMessage);
                    }
                    </script>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        
        <div class="text-center mt-20">
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>