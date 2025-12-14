<?php
// admin_elections.php - Create and manage elections
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_election'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $created_by = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO elections (title, description, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $title, $description, $start_date, $end_date, $created_by);
        
        if ($stmt->execute()) {
            $message = "Election created successfully!";
            $election_id = $conn->insert_id;
            $action = 'manage';
        } else {
            $error = "Error creating election.";
        }
    }
    
    if (isset($_POST['add_position'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $display_order = $_POST['display_order'];
        $max_votes = $_POST['max_votes'];
        
        // Verify election exists
        $check_election = $conn->prepare("SELECT election_id FROM elections WHERE election_id = ?");
        $check_election->bind_param("i", $election_id);
        $check_election->execute();
        $election_exists = $check_election->get_result()->num_rows > 0;
        $check_election->close();
        
        if (!$election_exists) {
            $error = "Invalid election ID. Please select a valid election.";
        } else {
            $stmt = $conn->prepare("INSERT INTO positions (election_id, title, description, display_order, max_votes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $election_id, $title, $description, $display_order, $max_votes);
            
            if ($stmt->execute()) {
                $message = "Position added successfully!";
            } else {
                $error = "Error adding position: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if (isset($_POST['add_candidate'])) {
        $position_id = $_POST['position_id'];
        $user_id = $_POST['user_id'];
        $short_bio = $_POST['short_bio'];
        $campaign_message = $_POST['campaign_message'];
        
        $stmt = $conn->prepare("INSERT INTO candidates (position_id, user_id, short_bio, campaign_message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $position_id, $user_id, $short_bio, $campaign_message);
        
        if ($stmt->execute()) {
            $message = "Candidate added successfully!";
        } else {
            $error = "Error adding candidate. User may already be a candidate for this position.";
        }
    }
    
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE elections SET status = ? WHERE election_id = ?");
        $stmt->bind_param("si", $new_status, $election_id);
        
        if ($stmt->execute()) {
            $message = "Election status updated successfully!";
        } else {
            $error = "Error updating status.";
        }
    }
    
    if (isset($_POST['delete_election'])) {
        $delete_id = intval($_POST['election_id']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete in correct order to avoid foreign key constraint errors
            
            // 1. Delete all votes for this election
            $delete_votes = $conn->prepare("DELETE FROM votes WHERE election_id = ?");
            $delete_votes->bind_param("i", $delete_id);
            $delete_votes->execute();
            $delete_votes->close();
            
            // 2. Delete all feedback for this election
            $delete_feedback = $conn->prepare("DELETE FROM feedback WHERE election_id = ?");
            $delete_feedback->bind_param("i", $delete_id);
            $delete_feedback->execute();
            $delete_feedback->close();
            
            // 3. Delete all candidates (positions cascade will handle this, but we'll be explicit)
            $delete_candidates = $conn->prepare("DELETE c FROM candidates c 
                INNER JOIN positions p ON c.position_id = p.position_id 
                WHERE p.election_id = ?");
            $delete_candidates->bind_param("i", $delete_id);
            $delete_candidates->execute();
            $delete_candidates->close();
            
            // 4. Delete all positions for this election (CASCADE will work here)
            $delete_positions = $conn->prepare("DELETE FROM positions WHERE election_id = ?");
            $delete_positions->bind_param("i", $delete_id);
            $delete_positions->execute();
            $delete_positions->close();
            
            // 5. Finally, delete the election
            $delete_election = $conn->prepare("DELETE FROM elections WHERE election_id = ?");
            $delete_election->bind_param("i", $delete_id);
            $delete_election->execute();
            $delete_election->close();
            
            // Log the deletion
            $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'ELECTION_DELETED', ?)");
            $log_desc = "Deleted election ID: " . $delete_id;
            $log_stmt->bind_param("ss", $_SESSION['user_id'], $log_desc);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $message = "Election and all related data deleted successfully!";
            $action = 'list';
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Error deleting election: " . $e->getMessage();
        }
    }
}

// Get election details if managing
$election = null;
if (($action == 'manage' || $action == 'edit') && $election_id > 0) {
    $election_query = "SELECT * FROM elections WHERE election_id = ?";
    $election_stmt = $conn->prepare($election_query);
    $election_stmt->bind_param("i", $election_id);
    $election_stmt->execute();
    $result = $election_stmt->get_result();
    if ($result->num_rows > 0) {
        $election = $result->fetch_assoc();
    }
    $election_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Elections</h1>
            <nav>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_approve.php">Approve Elections</a>
                <a href="admin_users.php">Manage Users</a>
                <a href="results.php">View Results</a>
                <a href="edit_profile.php">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($action == 'create'): ?>
            <h2>Create New Election</h2>
            <div class="card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="title">Election Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date & Time:</label>
                        <input type="datetime-local" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date & Time:</label>
                        <input type="datetime-local" id="end_date" name="end_date" required>
                    </div>
                    
                    <button type="submit" name="create_election">Create Election</button>
                    <a href="admin_elections.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        
        <?php elseif ($action == 'manage' && $election): ?>
            <h2><?php echo htmlspecialchars($election['title']); ?></h2>
            
            <div class="card">
                <h3>Election Details</h3>
                <p><strong>Election ID:</strong> <?php echo $election['election_id']; ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($election['status']); ?></p>
                <p><strong>Start:</strong> <?php echo date('F j, Y g:i A', strtotime($election['start_date'])); ?></p>
                <p><strong>End:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                
                <form method="POST" action="" style="display: inline;">
                    <label for="status">Change Status:</label>
                    <select name="status" id="status">
                        <option value="upcoming" <?php echo $election['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="active" <?php echo $election['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $election['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $election['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" name="update_status">Update</button>
                </form>
                
                <form method="POST" action="" style="display: inline; margin-left: 20px;" onsubmit="return confirm('Are you sure you want to delete this election? This will also delete all positions, candidates, and votes associated with it. This action cannot be undone!');">
                    <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                    <button type="submit" name="delete_election" style="background-color: #666; border-color: #666;">Delete Election</button>
                </form>
            </div>
            
            <div class="card">
                <h3>Add Position</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="pos_title">Position Title:</label>
                        <input type="text" id="pos_title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pos_description">Description:</label>
                        <input type="text" id="pos_description" name="description">
                    </div>
                    
                    <div class="form-group">
                        <label for="display_order">Display Order:</label>
                        <input type="number" id="display_order" name="display_order" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_votes">Max Votes:</label>
                        <input type="number" id="max_votes" name="max_votes" value="1">
                    </div>
                    
                    <button type="submit" name="add_position">Add Position</button>
                </form>
            </div>
            
            <?php
            // Get positions for this election
            $positions_query = "SELECT * FROM positions WHERE election_id = ? ORDER BY display_order";
            $positions_stmt = $conn->prepare($positions_query);
            $positions_stmt->bind_param("i", $election_id);
            $positions_stmt->execute();
            $positions_result = $positions_stmt->get_result();
            ?>
            
            <h3>Positions and Candidates</h3>
            <?php while ($position = $positions_result->fetch_assoc()): ?>
                <div class="card">
                    <h4><?php echo htmlspecialchars($position['title']); ?></h4>
                    
                    <?php
                    // Get candidates for this position
                    $candidates_query = "SELECT c.*, u.name, u.class FROM candidates c 
                        JOIN users u ON c.user_id = u.user_id 
                        WHERE c.position_id = ?";
                    $candidates_stmt = $conn->prepare($candidates_query);
                    $candidates_stmt->bind_param("i", $position['position_id']);
                    $candidates_stmt->execute();
                    $candidates_result = $candidates_stmt->get_result();
                    ?>
                    
                    <?php if ($candidates_result->num_rows > 0): ?>
                        <h5>Candidates:</h5>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Bio</th>
                                    <th>Campaign Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($candidate = $candidates_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['class']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['short_bio']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['campaign_message']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No candidates added yet.</p>
                    <?php endif; ?>
                    
                    <h5>Add Candidate to this Position</h5>
                    <form method="POST" action="">
                        <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                        
                        <div class="form-group">
                            <label for="user_id_<?php echo $position['position_id']; ?>">Student ID:</label>
                            <input type="text" id="user_id_<?php echo $position['position_id']; ?>" name="user_id" required placeholder="e.g., S2025001">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio_<?php echo $position['position_id']; ?>">Short Bio:</label>
                            <input type="text" id="bio_<?php echo $position['position_id']; ?>" name="short_bio" placeholder="e.g., Class representative for 2 years">
                        </div>
                        
                        <div class="form-group">
                            <label for="campaign_<?php echo $position['position_id']; ?>">Campaign Message:</label>
                            <input type="text" id="campaign_<?php echo $position['position_id']; ?>" name="campaign_message" placeholder="e.g., Vote for change!">
                        </div>
                        
                        <button type="submit" name="add_candidate">Add Candidate</button>
                    </form>
                </div>
            <?php endwhile; ?>
            
            <div class="text-center mt-20">
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        
        <?php else: ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            <p>Invalid election ID or election not found.</p>
            <p><strong>Debug Info:</strong> Action: <?php echo htmlspecialchars($action); ?>, Election ID: <?php echo $election_id; ?></p>
            <a href="admin_dashboard.php" class="btn">Back to Dashboard</a>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>