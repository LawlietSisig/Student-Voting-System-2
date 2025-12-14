<?php
// results.php - View election results
require_once 'config.php';
require_once 'auto_update_elections.php';
requireLogin();

$conn = getDBConnection();

// Auto-update election statuses
autoUpdateElectionStatus();

// Get active elections for live results
$active_elections_query = "SELECT * FROM elections WHERE status = 'active' AND approval_status = 'approved' ORDER BY start_date";
$active_elections_result = $conn->query($active_elections_query);

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
                    <a href="admin_approve.php">Approve Elections</a>
                    <a href="admin_users.php">Manage Users</a>
                    <a href="results.php">View Results</a>
                    <a href="edit_profile.php">Edit Profile</a>
                <?php else: ?>
                    <a href="index.php">Dashboard</a>
                    <a href="create_election.php">Propose Election</a>
                    <a href="my_votes.php">My Votes</a>
                    <a href="results.php">Results</a>
                    <a href="edit_profile.php">Edit Profile</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>🔴 Live Results - Active Elections</h2>
        <p style="color: #666; margin-bottom: 20px;">
            <em>Note: These are current vote counts for ongoing elections. Results may change as more votes are cast!</em>
        </p>
        
        <?php if ($active_elections_result->num_rows > 0): ?>
            <?php while ($election = $active_elections_result->fetch_assoc()): ?>
                <div class="card" style="border: 2px solid #333;">
                    <h3>🗳️ <?php echo htmlspecialchars($election['title']); ?> <span style="background: #333; color: white; padding: 5px 10px; border-radius: 4px; font-size: 14px; margin-left: 10px;">LIVE</span></h3>
                    <p><strong>Ends:</strong> <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?></p>
                    
                    <?php
                    // Get live results for this election
                    $live_results_query = "SELECT 
                        p.position_id,
                        p.title as position_title,
                        c.candidate_id,
                        u.name as candidate_name,
                        u.class as candidate_class,
                        COUNT(v.vote_id) as total_votes
                        FROM positions p
                        LEFT JOIN candidates c ON p.position_id = c.position_id
                        LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND p.position_id = v.position_id
                        LEFT JOIN users u ON c.user_id = u.user_id
                        WHERE p.election_id = ?
                        GROUP BY p.position_id, c.candidate_id, u.name, u.class
                        ORDER BY p.display_order, total_votes DESC";
                    $live_stmt = $conn->prepare($live_results_query);
                    $live_stmt->bind_param("i", $election['election_id']);
                    $live_stmt->execute();
                    $live_results = $live_stmt->get_result();
                    
                    // Get abstain counts per position from abstain_votes table
                    $abstain_query = "SELECT position_id, COUNT(*) as abstain_count 
                                     FROM abstain_votes 
                                     WHERE election_id = ? 
                                     GROUP BY position_id";
                    $abstain_stmt = $conn->prepare($abstain_query);
                    $abstain_stmt->bind_param("i", $election['election_id']);
                    $abstain_stmt->execute();
                    $abstain_results = $abstain_stmt->get_result();
                    $abstain_counts = [];
                    while ($abstain_row = $abstain_results->fetch_assoc()) {
                        $abstain_counts[$abstain_row['position_id']] = $abstain_row['abstain_count'];
                    }
                    $abstain_stmt->close();
                    
                    $current_position = null;
                    $max_votes = 0;
                    ?>
                    
                    <?php while ($result = $live_results->fetch_assoc()): ?>
                        <?php if ($current_position != $result['position_id']): ?>
                            <?php if ($current_position !== null): ?>
                                <?php 
                                // Add abstain row for previous position
                                $abstain_count = isset($abstain_counts[$current_position]) ? $abstain_counts[$current_position] : 0;
                                if ($abstain_count > 0):
                                    $abstain_percentage = $max_votes > 0 ? round(($abstain_count / $max_votes) * 100, 1) : 0;
                                ?>
                                    <tr style="background-color: #f7fafc;">
                                        <td><em style="color: #718096;">⊘ Abstain</em></td>
                                        <td>-</td>
                                        <td><strong><?php echo $abstain_count; ?></strong></td>
                                        <td>
                                            <div style="background: #e2e8f0; border-radius: 4px; overflow: hidden; height: 20px; position: relative;">
                                                <div style="background: #718096; height: 100%; width: <?php echo $abstain_percentage; ?>%;"></div>
                                                <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: bold;">
                                                    <?php echo $abstain_percentage; ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    // Check if abstain has majority for previous position
                                    $all_votes_query = "SELECT 
                                        (SELECT COUNT(*) FROM votes WHERE election_id = ? AND position_id = ?) + 
                                        (SELECT COUNT(*) FROM abstain_votes WHERE election_id = ? AND position_id = ?) as total";
                                    $all_votes_stmt = $conn->prepare($all_votes_query);
                                    $all_votes_stmt->bind_param("iiii", $election['election_id'], $current_position, $election['election_id'], $current_position);
                                    $all_votes_stmt->execute();
                                    $total_votes_result = $all_votes_stmt->get_result()->fetch_assoc();
                                    $total_position_votes = $total_votes_result['total'];
                                    $all_votes_stmt->close();
                                    
                                    if ($abstain_count > ($total_position_votes / 2)):
                                    ?>
                                        <tr style="background-color: #fed7d7;">
                                            <td colspan="4" style="text-align: center; font-weight: bold; color: #742a2a;">
                                                ⚠️ NO WINNER AS OF NOW - Majority voted to abstain. Re-election may happen.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                                </tbody></table>
                            <?php endif; ?>
                            <h4 class="mt-20"><?php echo htmlspecialchars($result['position_title']); ?></h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Class</th>
                                        <th>Current Votes</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php 
                            $current_position = $result['position_id'];
                            // Calculate max votes for this position (votes + abstains)
                            $max_query = "SELECT 
                                (SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ? AND position_id = ?) + 
                                (SELECT COUNT(DISTINCT voter_id) FROM abstain_votes WHERE election_id = ? AND position_id = ?) as total_voters";
                            $max_stmt = $conn->prepare($max_query);
                            $max_stmt->bind_param("iiii", $election['election_id'], $result['position_id'], $election['election_id'], $result['position_id']);
                            $max_stmt->execute();
                            $max_result = $max_stmt->get_result()->fetch_assoc();
                            $max_votes = $max_result['total_voters'];
                            $max_stmt->close();
                            ?>
                        <?php endif; ?>
                        
                        <?php 
                        $percentage = $max_votes > 0 ? round(($result['total_votes'] / $max_votes) * 100, 1) : 0;
                        $is_leading = false;
                        
                        // Check if this candidate is leading
                        if ($result['total_votes'] > 0) {
                            $leading_check = "SELECT MAX(vote_count) as max_votes FROM (
                                SELECT COUNT(v.vote_id) as vote_count
                                FROM candidates c
                                LEFT JOIN votes v ON c.candidate_id = v.candidate_id
                                WHERE c.position_id = ?
                                GROUP BY c.candidate_id
                            ) as vote_counts";
                            $leading_stmt = $conn->prepare($leading_check);
                            $leading_stmt->bind_param("i", $result['position_id']);
                            $leading_stmt->execute();
                            $leading_result = $leading_stmt->get_result()->fetch_assoc();
                            $is_leading = ($result['total_votes'] == $leading_result['max_votes']);
                            $leading_stmt->close();
                        }
                        ?>
                        
                        <tr <?php echo $is_leading ? 'style="background-color: #f0f0f0; font-weight: bold;"' : ''; ?>>
                            <td>
                                <?php echo htmlspecialchars($result['candidate_name']); ?>
                                <?php if ($is_leading && $result['total_votes'] > 0): ?>
                                    <span style="color: #333;">🏆</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($result['candidate_class']); ?></td>
                            <td><strong><?php echo $result['total_votes']; ?></strong></td>
                            <td>
                                <div style="background: #e8e8e8; border-radius: 4px; overflow: hidden; height: 20px; position: relative;">
                                    <div style="background: #333; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                                    <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: bold;">
                                        <?php echo $percentage; ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($current_position !== null): ?>
                        <?php 
                        // Add abstain row for the last position
                        $abstain_count = isset($abstain_counts[$current_position]) ? $abstain_counts[$current_position] : 0;
                        if ($abstain_count > 0):
                            $abstain_percentage = $max_votes > 0 ? round(($abstain_count / $max_votes) * 100, 1) : 0;
                        ?>
                            <tr style="background-color: #f7fafc;">
                                <td><em style="color: #718096;">⊘ Abstain</em></td>
                                <td>-</td>
                                <td><strong><?php echo $abstain_count; ?></strong></td>
                                <td>
                                    <div style="background: #e2e8f0; border-radius: 4px; overflow: hidden; height: 20px; position: relative;">
                                        <div style="background: #718096; height: 100%; width: <?php echo $abstain_percentage; ?>%;"></div>
                                        <span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: bold;">
                                            <?php echo $abstain_percentage; ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            // Check if abstain has majority for last position
                            $all_votes_query = "SELECT 
                                (SELECT COUNT(*) FROM votes WHERE election_id = ? AND position_id = ?) + 
                                (SELECT COUNT(*) FROM abstain_votes WHERE election_id = ? AND position_id = ?) as total";
                            $all_votes_stmt = $conn->prepare($all_votes_query);
                            $all_votes_stmt->bind_param("iiii", $election['election_id'], $current_position, $election['election_id'], $current_position);
                            $all_votes_stmt->execute();
                            $total_votes_result = $all_votes_stmt->get_result()->fetch_assoc();
                            $total_position_votes = $total_votes_result['total'];
                            $all_votes_stmt->close();
                            
                            if ($abstain_count > ($total_position_votes / 2)):
                            ?>
                                <tr style="background-color: #fed7d7;">
                                    <td colspan="4" style="text-align: center; font-weight: bold; color: #742a2a;">
                                        ⚠️ NO WINNER - Majority voted to abstain. Re-election may be required.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                        </tbody></table>
                    <?php endif; ?>
                    
                    <?php $live_stmt->close(); ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <p>No active elections at the moment.</p>
            </div>
        <?php endif; ?>
        
        <h2 class="mt-20">Final Results - Completed Elections</h2>
        
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
                    
                    // Get abstain counts for completed election
                    $abstain_query = "SELECT position_id, COUNT(*) as abstain_count 
                                     FROM abstain_votes 
                                     WHERE election_id = ? 
                                     GROUP BY position_id";
                    $abstain_stmt = $conn->prepare($abstain_query);
                    $abstain_stmt->bind_param("i", $election['election_id']);
                    $abstain_stmt->execute();
                    $abstain_results = $abstain_stmt->get_result();
                    $completed_abstain_counts = [];
                    while ($abstain_row = $abstain_results->fetch_assoc()) {
                        $completed_abstain_counts[$abstain_row['position_id']] = $abstain_row['abstain_count'];
                    }
                    $abstain_stmt->close();
                    
                    $current_position = null;
                    ?>
                    
                    <?php while ($result = $results->fetch_assoc()): ?>
                        <?php if ($current_position != $result['position_id']): ?>
                            <?php if ($current_position !== null): ?>
                                <?php 
                                // Add abstain row for previous position
                                $abstain_count = isset($completed_abstain_counts[$current_position]) ? $completed_abstain_counts[$current_position] : 0;
                                if ($abstain_count > 0):
                                ?>
                                    <tr style="background-color: #f7fafc;">
                                        <td><em style="color: #718096;">⊘ Abstain</em></td>
                                        <td>-</td>
                                        <td><strong><?php echo $abstain_count; ?></strong></td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
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
                        <?php 
                        // Add abstain row for last position
                        $abstain_count = isset($completed_abstain_counts[$current_position]) ? $completed_abstain_counts[$current_position] : 0;
                        if ($abstain_count > 0):
                        ?>
                            <tr style="background-color: #f7fafc;">
                                <td><em style="color: #718096;">⊘ Abstain</em></td>
                                <td>-</td>
                                <td><strong><?php echo $abstain_count; ?></strong></td>
                            </tr>
                        <?php endif; ?>
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