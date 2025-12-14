<?php
// admin_approve.php - Admin reviews and approves election proposals
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve'])) {
    $election_id = intval($_POST['election_id']);
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE elections SET approval_status = 'approved', approved_by = ? WHERE election_id = ?");
    $stmt->bind_param("si", $admin_id, $election_id);
    
    if ($stmt->execute()) {
        // Log the approval
        $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'ELECTION_APPROVED', ?)");
        $log_desc = "Approved election ID: " . $election_id;
        $log_stmt->bind_param("ss", $admin_id, $log_desc);
        $log_stmt->execute();
        $log_stmt->close();
        
        $message = "Election approved successfully!";
    } else {
        $error = "Error approving election.";
    }
    $stmt->close();
}

// Handle rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject'])) {
    $election_id = intval($_POST['election_id']);
    $rejection_reason = trim($_POST['rejection_reason']);
    $admin_id = $_SESSION['user_id'];
    
    if (empty($rejection_reason)) {
        $error = "Please provide a reason for rejection.";
    } else {
        $stmt = $conn->prepare("UPDATE elections SET status = 'cancelled', approval_status = 'rejected', approved_by = ?, rejection_reason = ? WHERE election_id = ?");
        $stmt->bind_param("ssi", $admin_id, $rejection_reason, $election_id);
        
        if ($stmt->execute()) {
            // Log the rejection
            $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'ELECTION_REJECTED', ?)");
            $log_desc = "Rejected election ID: " . $election_id . " - Reason: " . $rejection_reason;
            $log_stmt->bind_param("ss", $admin_id, $log_desc);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Election rejected and cancelled.";
        } else {
            $error = "Error rejecting election.";
        }
        $stmt->close();
    }
}

// Get pending elections
$pending_query = "SELECT e.*, u.name as proposer_name, u.class as proposer_class 
                  FROM elections e 
                  JOIN users u ON e.created_by = u.user_id 
                  WHERE e.approval_status = 'pending' 
                  ORDER BY e.created_at DESC";
$pending_result = $conn->query($pending_query);

// Get approved/rejected elections
$reviewed_query = "SELECT e.*, u.name as proposer_name, u.class as proposer_class, a.name as approver_name 
                   FROM elections e 
                   JOIN users u ON e.created_by = u.user_id 
                   LEFT JOIN users a ON e.approved_by = a.user_id 
                   WHERE e.approval_status IN ('approved', 'rejected') 
                   ORDER BY e.updated_at DESC 
                   LIMIT 20";
$reviewed_result = $conn->query($reviewed_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Elections - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Review Election Proposals</h1>
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
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <h2>Pending Proposals</h2>
        
        <?php if ($pending_result->num_rows > 0): ?>
            <?php while ($election = $pending_result->fetch_assoc()): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <p><strong>Proposed by:</strong> <?php echo htmlspecialchars($election['proposer_name']); ?> (<?php echo htmlspecialchars($election['proposer_class']); ?>)</p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($election['description']); ?></p>
                    <p><strong>Start Date:</strong> <?php echo date('F j, Y g:i A', strtotime($election['start_date'])); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    <p><strong>Proposed on:</strong> <?php echo date('F j, Y g:i A', strtotime($election['created_at'])); ?></p>
                    
                    <div style="margin-top: 20px;">
                        <form method="POST" action="" style="display: inline-block; margin-right: 10px;">
                            <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                            <button type="submit" name="approve" style="background-color: #333; border-color: #333;">Approve</button>
                        </form>
                        
                        <button onclick="document.getElementById('reject_form_<?php echo $election['election_id']; ?>').style.display='block'" 
                                class="btn btn-secondary">Reject</button>
                        
                        <div id="reject_form_<?php echo $election['election_id']; ?>" style="display: none; margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                            <form method="POST" action="">
                                <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                <div class="form-group">
                                    <label for="rejection_reason_<?php echo $election['election_id']; ?>">Reason for Rejection:</label>
                                    <textarea id="rejection_reason_<?php echo $election['election_id']; ?>" 
                                              name="rejection_reason" 
                                              rows="3" 
                                              required 
                                              placeholder="Explain why this proposal is being rejected..."></textarea>
                                </div>
                                <button type="submit" name="reject" style="background-color: #666;">Confirm Rejection</button>
                                <button type="button" onclick="document.getElementById('reject_form_<?php echo $election['election_id']; ?>').style.display='none'" class="btn btn-secondary">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No pending election proposals.</p>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-20">Recently Reviewed</h2>
        
        <?php if ($reviewed_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Proposed By</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($election = $reviewed_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($election['title']); ?></td>
                        <td><?php echo htmlspecialchars($election['proposer_name']); ?></td>
                        <td>
                            <?php 
                            $approval_status = $election['approval_status'];
                            $badge_color = $approval_status == 'approved' ? 'background: #e8e8e8; color: #333;' : 'background: #666; color: white;';
                            ?>
                            <span style="padding: 5px 10px; border-radius: 4px; <?php echo $badge_color; ?>">
                                <?php echo ucfirst($approval_status); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($election['approver_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($election['rejection_reason']): ?>
                                <small><?php echo htmlspecialchars($election['rejection_reason']); ?></small>
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
                <p>No reviewed elections yet.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>