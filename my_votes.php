<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user's votes
$votes_query = "SELECT 
    e.title as election_title,
    e.election_id,
    p.title as position_title,
    u.name as candidate_name,
    v.voted_at
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    JOIN positions p ON v.position_id = p.position_id
    JOIN candidates c ON v.candidate_id = c.candidate_id
    JOIN users u ON c.user_id = u.user_id
    WHERE v.voter_id = ?
    ORDER BY v.voted_at DESC";
$votes_stmt = $conn->prepare($votes_query);
$votes_stmt->bind_param("s", $user_id);
$votes_stmt->execute();
$votes_result = $votes_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Votes - Student Voting System</title>
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
        <h2>My Voting History</h2>
        
        <?php if ($votes_result->num_rows > 0): ?>
            <?php
            $current_election = null;
            while ($vote = $votes_result->fetch_assoc()):
                if ($current_election != $vote['election_id']):
                    if ($current_election !== null): ?>
                        </tbody></table></div>
                    <?php endif; ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($vote['election_title']); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Voted For</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                    <?php $current_election = $vote['election_id']; ?>
                <?php endif; ?>
                
                <tr>
                    <td><?php echo htmlspecialchars($vote['position_title']); ?></td>
                    <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                    <td><?php echo date('F j, Y g:i A', strtotime($vote['voted_at'])); ?></td>
                </tr>
            <?php endwhile; ?>
            
            <?php if ($current_election !== null): ?>
                </tbody></table></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <p>You haven't cast any votes yet.</p>
                <br>
                <a href="index.php" class="btn">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; BSIT 2A 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>