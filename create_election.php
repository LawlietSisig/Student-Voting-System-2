<?php
// create_election.php - Students can propose elections
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle election creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_election'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Validation
    if (empty($title) || empty($start_date) || empty($end_date)) {
        $error = "Title, start date, and end date are required.";
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error = "End date must be after start date.";
    } elseif (strtotime($start_date) < time()) {
        $error = "Start date cannot be in the past.";
    } else {
        // Create election with pending approval status
        $approval_status = 'pending';
        $stmt = $conn->prepare("INSERT INTO elections (title, description, start_date, end_date, status, approval_status, created_by) VALUES (?, ?, ?, ?, 'upcoming', ?, ?)");
        $stmt->bind_param("ssssss", $title, $description, $start_date, $end_date, $approval_status, $user_id);
        
        if ($stmt->execute()) {
            // Log the action
            $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'ELECTION_PROPOSED', ?)");
            $log_desc = "Proposed election: " . $title;
            $log_stmt->bind_param("ss", $user_id, $log_desc);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Election proposal submitted successfully! It will be visible once approved by an administrator.";
        } else {
            $error = "Error creating election proposal: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get user's proposed elections
$my_proposals_query = "SELECT * FROM elections WHERE created_by = ? ORDER BY created_at DESC";
$my_proposals_stmt = $conn->prepare($my_proposals_query);
$my_proposals_stmt->bind_param("s", $user_id);
$my_proposals_stmt->execute();
$my_proposals = $my_proposals_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propose Election - Student Voting System</title>
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
        <h2>Propose New Election</h2>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <p style="color: #666; margin-bottom: 20px;">
                <strong>Note:</strong> Your election proposal will be reviewed by administrators before it becomes active.
            </p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="title">Election Title: <span style="color: red;">*</span></label>
                    <input type="text" id="title" name="title" required 
                           placeholder="e.g., Class President Election 2025">
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" 
                              placeholder="Describe the purpose and details of this election..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date & Time: <span style="color: red;">*</span></label>
                    <input type="datetime-local" id="start_date" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date & Time: <span style="color: red;">*</span></label>
                    <input type="datetime-local" id="end_date" name="end_date" required>
                </div>
                
                <button type="submit" name="create_election">Submit Proposal</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        
        <h2 class="mt-20">My Election Proposals</h2>
        
        <?php if ($my_proposals->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Approval Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($proposal = $my_proposals->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($proposal['title']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($proposal['start_date'])); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($proposal['end_date'])); ?></td>
                        <td>
                            <?php 
                            $status = $proposal['status'];
                            $status_color = '';
                            if ($status == 'upcoming') {
                                $status_color = 'background: #f0f0f0; color: #333;';
                            } elseif ($status == 'active') {
                                $status_color = 'background: #e8e8e8; color: #333;';
                            } elseif ($status == 'completed') {
                                $status_color = 'background: #ddd; color: #333;';
                            } elseif ($status == 'cancelled') {
                                $status_color = 'background: #666; color: white;';
                            }
                            ?>
                            <span style="padding: 5px 10px; border-radius: 4px; <?php echo $status_color; ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $approval_status = $proposal['approval_status'];
                            $badge_color = '';
                            if ($approval_status == 'pending') {
                                $badge_color = 'background: #f0f0f0; color: #666;';
                            } elseif ($approval_status == 'approved') {
                                $badge_color = 'background: #e8e8e8; color: #333;';
                            } elseif ($approval_status == 'rejected') {
                                $badge_color = 'background: #666; color: white;';
                            }
                            ?>
                            <span style="padding: 5px 10px; border-radius: 4px; <?php echo $badge_color; ?>">
                                <?php echo ucfirst($approval_status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($proposal['rejection_reason']): ?>
                                <small style="color: #666;">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($proposal['rejection_reason']); ?>
                                </small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="card">
                <p>You haven't proposed any elections yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>