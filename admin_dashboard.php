<?php
// admin_dashboard.php - Admin control panel
require_once 'config.php';
require_once 'auto_update_elections.php';
requireAdmin();

$conn = getDBConnection();

// Auto-update election statuses
autoUpdateElectionStatus();

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM elections) as total_elections,
    (SELECT COUNT(*) FROM elections WHERE status = 'active') as active_elections,
    (SELECT COUNT(*) FROM votes) as total_votes";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get all elections
$elections_query = "SELECT * FROM elections ORDER BY created_at DESC";
$elections_result = $conn->query($elections_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Voting System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Voting System - Admin Panel</h1>
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
        <h2>Dashboard Statistics</h2>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $stats['total_students']; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_elections']; ?></h3>
                <p>Total Elections</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['active_elections']; ?></h3>
                <p>Active Elections</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['total_votes']; ?></h3>
                <p>Total Votes Cast</p>
            </div>
        </div>
        
        <h2 class="mt-20">All Elections</h2>
        <a href="admin_elections.php?action=create" class="btn mb-20">Create New Election</a>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($election = $elections_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $election['election_id']; ?></td>
                    <td><?php echo htmlspecialchars($election['title']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($election['start_date'])); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($election['end_date'])); ?></td>
                    <td><?php echo ucfirst($election['status']); ?></td>
                    <td>
                        <a href="admin_elections.php?action=manage&id=<?php echo $election['election_id']; ?>" class="btn">Manage</a>
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