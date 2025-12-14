<?php
// vote.php - Simplified voting page with abstain
require_once 'config.php';
require_once 'auto_update_elections.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;
$message = '';
$error = '';

// Auto-update election statuses
autoUpdateElectionStatus();

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['position_id'])) {
    $position_id = intval($_POST['position_id']);
    $is_abstain = isset($_POST['abstain']);
    
    // Check if already voted
    $check_vote = $conn->prepare("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND position_id = ? AND voter_id = ?");
    $check_vote->bind_param("iis", $election_id, $position_id, $user_id);
    $check_vote->execute();
    $vote_exists = $check_vote->get_result()->fetch_assoc()['c'] > 0;
    $check_vote->close();
    
    $check_abstain = $conn->prepare("SELECT COUNT(*) as c FROM abstain_votes WHERE election_id = ? AND position_id = ? AND voter_id = ?");
    $check_abstain->bind_param("iis", $election_id, $position_id, $user_id);
    $check_abstain->execute();
    $abstain_exists = $check_abstain->get_result()->fetch_assoc()['c'] > 0;
    $check_abstain->close();
    
    if ($vote_exists || $abstain_exists) {
        $error = "You have already voted for this position.";
    } elseif ($is_abstain) {
        // Record abstain
        $stmt = $conn->prepare("INSERT INTO abstain_votes (election_id, position_id, voter_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $election_id, $position_id, $user_id);
        if ($stmt->execute()) {
            $message = "Abstain recorded successfully!";
        } else {
            $error = "Error recording abstain.";
        }
        $stmt->close();
    } elseif (isset($_POST['candidate_ids']) && is_array($_POST['candidate_ids'])) {
        $candidate_ids = array_map('intval', $_POST['candidate_ids']);
        
        $conn->begin_transaction();
        try {
            foreach ($candidate_ids as $cid) {
                $stmt = $conn->prepare("INSERT INTO votes (election_id, position_id, candidate_id, voter_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $election_id, $position_id, $cid, $user_id);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            $message = "Vote(s) cast successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error casting vote: " . $e->getMessage();
        }
    } else {
        $error = "Please select a candidate or choose to abstain.";
    }
}

// Get election
$election_query = "SELECT * FROM elections WHERE election_id = ? AND status = 'active'";
$election_stmt = $conn->prepare($election_query);
$election_stmt->bind_param("i", $election_id);
$election_stmt->execute();
$election = $election_stmt->get_result()->fetch_assoc();

if (!$election) {
    header("Location: index.php");
    exit();
}

// Get positions
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
                <a href="create_election.php">Propose Election</a>
                <a href="my_votes.php">My Votes</a>
                <a href="results.php">Results</a>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2><?php echo htmlspecialchars($election['title']); ?></h2>
        <p><?php echo htmlspecialchars($election['description']); ?></p>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php while ($position = $positions_result->fetch_assoc()): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($position['title']); ?></h3>
                <p><?php echo htmlspecialchars($position['description']); ?></p>
                
                <?php
                // Check if voted
                $v_check = $conn->prepare("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND position_id = ? AND voter_id = ?");
                $v_check->bind_param("iis", $election_id, $position['position_id'], $user_id);
                $v_check->execute();
                $has_voted = $v_check->get_result()->fetch_assoc()['c'] > 0;
                $v_check->close();
                
                $a_check = $conn->prepare("SELECT COUNT(*) as c FROM abstain_votes WHERE election_id = ? AND position_id = ? AND voter_id = ?");
                $a_check->bind_param("iis", $election_id, $position['position_id'], $user_id);
                $a_check->execute();
                $has_abstained = $a_check->get_result()->fetch_assoc()['c'] > 0;
                $a_check->close();
                
                if ($has_abstained): ?>
                    <p style="color: #718096; font-weight: bold;">✓ You abstained from voting in this position</p>
                <?php elseif ($has_voted): ?>
                    <p style="color: #333; font-weight: bold;">✓ You have voted for this position</p>
                <?php else: ?>
                    <?php
                    $candidates_query = "SELECT c.*, u.name, u.class FROM candidates c JOIN users u ON c.user_id = u.user_id WHERE c.position_id = ? AND c.is_active = 1";
                    $cand_stmt = $conn->prepare($candidates_query);
                    $cand_stmt->bind_param("i", $position['position_id']);
                    $cand_stmt->execute();
                    $candidates = $cand_stmt->get_result();
                    ?>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to submit your vote? This cannot be undone.');">
                        <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                        
                        <div class="candidate-list">
                            <?php while ($cand = $candidates->fetch_assoc()): ?>
                                <div class="candidate-card">
                                    <h4><?php echo htmlspecialchars($cand['name']); ?></h4>
                                    <p><strong>Class:</strong> <?php echo htmlspecialchars($cand['class']); ?></p>
                                    <p><strong>Bio:</strong> <?php echo htmlspecialchars($cand['short_bio']); ?></p>
                                    <p><em>"<?php echo htmlspecialchars($cand['campaign_message']); ?>"</em></p>
                                    <label style="display: block; margin-top: 15px; cursor: pointer;">
                                        <input type="checkbox" name="candidate_ids[]" value="<?php echo $cand['candidate_id']; ?>" style="margin-right: 8px;">
                                        <strong>Select this candidate</strong>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div style="margin: 20px 0; padding: 20px; background: #f7fafc; border: 2px dashed #cbd5e0; border-radius: 8px; text-align: center;">
                            <label style="cursor: pointer; font-size: 16px;">
                                <input type="checkbox" name="abstain" value="1" style="margin-right: 10px;">
                                <strong style="color: #718096;">⊘ Abstain</strong> - I choose not to vote for any candidate in this position
                            </label>
                        </div>
                        
                        <button type="submit" style="width: 100%;">Submit Vote</button>
                    </form>
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