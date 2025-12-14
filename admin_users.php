<?php
// admin_users.php - Manage users
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $class = $_POST['class'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (user_id, name, class, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $user_id, $name, $class, $password_hash, $role);
    
    if ($stmt->execute()) {
        $message = "User created successfully!";
    } else {
        $error = "Error creating user. User ID may already exist.";
    }
}

// Handle user status toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    
    if ($stmt->execute()) {
        $message = "User status updated!";
    }
    $stmt->close();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $delete_user_id = $_POST['delete_user_id'];
    
    // Prevent self-deletion
    if ($delete_user_id === $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get user email before deletion
            $get_email = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
            $get_email->bind_param("s", $delete_user_id);
            $get_email->execute();
            $email_result = $get_email->get_result();
            $user_email = $email_result->num_rows > 0 ? $email_result->fetch_assoc()['email'] : null;
            $get_email->close();
            
            // 1. Delete user's votes
            $delete_votes = $conn->prepare("DELETE FROM votes WHERE voter_id = ?");
            $delete_votes->bind_param("s", $delete_user_id);
            $delete_votes->execute();
            $delete_votes->close();
            
            // 2. Delete user's feedback
            $delete_feedback = $conn->prepare("DELETE FROM feedback WHERE user_id = ?");
            $delete_feedback->bind_param("s", $delete_user_id);
            $delete_feedback->execute();
            $delete_feedback->close();
            
            // 3. Delete user's candidates entries (and their votes)
            // First get candidate IDs
            $get_candidates = $conn->prepare("SELECT candidate_id FROM candidates WHERE user_id = ?");
            $get_candidates->bind_param("s", $delete_user_id);
            $get_candidates->execute();
            $candidates_result = $get_candidates->get_result();
            
            while ($candidate = $candidates_result->fetch_assoc()) {
                // Delete votes for this candidate
                $delete_candidate_votes = $conn->prepare("DELETE FROM votes WHERE candidate_id = ?");
                $delete_candidate_votes->bind_param("i", $candidate['candidate_id']);
                $delete_candidate_votes->execute();
                $delete_candidate_votes->close();
            }
            $get_candidates->close();
            
            // Now delete the candidates
            $delete_candidates = $conn->prepare("DELETE FROM candidates WHERE user_id = ?");
            $delete_candidates->bind_param("s", $delete_user_id);
            $delete_candidates->execute();
            $delete_candidates->close();
            
            // 4. Delete email verification records
            if ($user_email) {
                $check_verification = $conn->query("SHOW TABLES LIKE 'email_verification'");
                if ($check_verification->num_rows > 0) {
                    $delete_verification = $conn->prepare("DELETE FROM email_verification WHERE email = ?");
                    $delete_verification->bind_param("s", $user_email);
                    $delete_verification->execute();
                    $delete_verification->close();
                }
            }
            
            // 5. Handle audit_log - Check if foreign key exists
            $check_fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = 'student_voting_system' 
                AND TABLE_NAME = 'audit_log' 
                AND CONSTRAINT_NAME = 'audit_log_ibfk_1'");
            
            if ($check_fk->num_rows > 0) {
                // Foreign key exists, need to remove it first
                $error = "Database configuration error: Please run the SQL fix script to remove audit_log foreign key constraint. See admin_users.php instructions.";
                throw new Exception($error);
            } else {
                // No foreign key constraint, safe to update audit_log
                $update_audit = $conn->prepare("UPDATE audit_log SET user_id = 'DELETED_USER' WHERE user_id = ?");
                $update_audit->bind_param("s", $delete_user_id);
                $update_audit->execute();
                $update_audit->close();
            }
            
            // 6. Delete elections created by this user (or reassign to another admin)
            $delete_elections = $conn->prepare("DELETE FROM elections WHERE created_by = ?");
            $delete_elections->bind_param("s", $delete_user_id);
            $delete_elections->execute();
            $delete_elections->close();
            
            // 7. Finally delete the user
            $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_user->bind_param("s", $delete_user_id);
            
            if ($delete_user->execute()) {
                // Log the deletion
                $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description) VALUES (?, 'USER_DELETED', ?)");
                $log_desc = "Deleted user: " . $delete_user_id;
                $log_stmt->bind_param("ss", $_SESSION['user_id'], $log_desc);
                $log_stmt->execute();
                $log_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $message = "User and all associated data deleted successfully!";
            } else {
                throw new Exception("Failed to delete user: " . $delete_user->error);
            }
            
            $delete_user->close();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY role, name";
$users_result = $conn->query($users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Users</h1>
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
        
        <h2>Create New User</h2>
        <div class="card">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">User ID:</label>
                    <input type="text" id="user_id" name="user_id" required placeholder="e.g., S2025005">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="class">Class:</label>
                    <input type="text" id="class" name="class" required placeholder="e.g., 12A">
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" name="create_user">Create User</button>
            </form>
        </div>
        
        <h2 class="mt-20">All Users</h2>
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['class']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                    <td>
                        <a href="?toggle=1&id=<?php echo urlencode($user['user_id']); ?>" class="btn btn-secondary">
                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        
                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" action="" style="display: inline-block; margin-left: 5px;" 
                                  onsubmit="return confirm('⚠️ WARNING: This will permanently delete user <?php echo htmlspecialchars($user['user_id']); ?> and ALL their data including:\n\n• All votes cast\n• All feedback submitted\n• Candidate entries\n• Email verification records\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?');">
                                <input type="hidden" name="delete_user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                <button type="submit" name="delete_user" style="background-color: #666; border-color: #666;">Delete</button>
                            </form>
                        <?php else: ?>
                            <button disabled style="background-color: #ccc; border-color: #ccc; cursor: not-allowed;" title="Cannot delete yourself">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <footer>
        <p>&copy; 2025 Student Voting System</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>