<?php
// index.php - Student Dashboard
require_once 'config.php';
require_once 'auto_update_elections.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Auto-update election statuses based on dates
autoUpdateElectionStatus();

// Get active elections - Only show approved elections that are currently active
$elections_query = "SELECT * FROM elections 
                    WHERE status = 'active' 
                    AND approval_status = 'approved' 
                    AND NOW() BETWEEN start_date AND end_date 
                    ORDER BY start_date";
$elections_result = $conn->query($elections_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo htmlspecialchars($_SESSION['class']); ?>)</p>
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
        <h2>Active Elections</h2>
        
        <?php if ($elections_result->num_rows > 0): ?>
            <?php while ($election = $elections_result->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    
                    <?php
                    // Check voting status
                    $election_id = $election['election_id'];
                    $status_query = "SELECT 
                        COUNT(DISTINCT p.position_id) as total_positions,
                        COUNT(DISTINCT v.position_id) as voted_positions
                        FROM positions p
                        LEFT JOIN votes v ON p.position_id = v.position_id 
                            AND v.voter_id = ? 
                            AND v.election_id = ?
                        WHERE p.election_id = ?";
                    $status_stmt = $conn->prepare($status_query);
                    $status_stmt->bind_param("sii", $user_id, $election_id, $election_id);
                    $status_stmt->execute();
                    $status = $status_stmt->get_result()->fetch_assoc();
                    ?>
                    
                    <p><strong>Voting Progress:</strong> <?php echo $status['voted_positions']; ?> / <?php echo $status['total_positions']; ?> positions</p>
                    
                    <a href="vote.php?election_id=<?php echo $election_id; ?>" class="btn">
                        <?php echo ($status['voted_positions'] == $status['total_positions']) ? 'Review Votes' : 'Vote Now'; ?>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No active elections at the moment.</p>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-20">Upcoming Elections</h2>
        <?php
        $upcoming_query = "SELECT * FROM elections 
                          WHERE status = 'upcoming' 
                          AND approval_status = 'approved' 
                          AND start_date > NOW() 
                          ORDER BY start_date";
        $upcoming_result = $conn->query($upcoming_query);
        
        if ($upcoming_result->num_rows > 0):
            while ($election = $upcoming_result->fetch_assoc()):
        ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                <p><?php echo htmlspecialchars($election['description']); ?></p>
                <p><strong>Start Date:</strong> <?php echo date('F j, Y g:i A', strtotime($election['start_date'])); ?></p>
            </div>
        <?php 
            endwhile;
        else:
        ?>
            <div class="card">
                <p>No upcoming elections.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>