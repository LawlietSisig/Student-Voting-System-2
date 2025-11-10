<?php
// results.php - View election results
require_once 'config.php';
requireLogin();

$conn = getDBConnection();

// Get completed elections
$elections_query = "SELECT * FROM elections WHERE status = 'completed' ORDER BY end_date DESC";
$elections_result = $conn->query($elections_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System</h1>
            <nav>
                <?php if (isAdmin()): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                    <a href="admin_users.php">Manage Users</a>
                    <a href="results.php">View Results</a>
                <?php else: ?>
                    <a href="index.php">Dashboard</a>
                    <a href="my_votes.php">My Votes</a>
                    <a href="results.php">Results</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>Election Results</h2>
        
        <?php if ($elections_result->num_rows > 0): ?>
            <?php while ($election = $elections_result->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <p><strong>Ended:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    
                    <?php
                    // Get results for this election
                    $results_query = "SELECT * FROM election_results WHERE election_id = ? ORDER BY position_id, total_votes DESC";
                    $results_stmt = $conn->prepare($results_query);
                    $results_stmt->bind_param("i", $election['election_id']);
                    $results_stmt->execute();
                    $results = $results_stmt->get_result();
                    
                    $current_position = null;
                    ?>
                    
                    <?php while ($result = $results->fetch_assoc()): ?>
                        <?php if ($current_position != $result['position_id']): ?>
                            <?php if ($current_position !== null): ?>
                                </table>
                            <?php endif; ?>
                            <h4 class="mt-20"><?php echo htmlspecialchars($result['position_title']); ?></h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Class</th>
                                        <th>Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php $current_position = $result['position_id']; ?>
                        <?php endif; ?>
                        
                        <tr>
                            <td><?php echo htmlspecialchars($result['candidate_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['candidate_class']); ?></td>
                            <td><strong><?php echo $result['total_votes']; ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($current_position !== null): ?>
                        </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No completed elections yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>