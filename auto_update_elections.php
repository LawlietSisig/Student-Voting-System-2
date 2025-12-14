<?php
// auto_update_elections.php - Automatically update election status based on dates

require_once 'config.php';

/**
 * Update election statuses based on current date/time
 * Call this function whenever loading election data
 */
function autoUpdateElectionStatus() {
    $conn = getDBConnection();
    
    // Update elections to 'active' if start date has passed and status is 'upcoming'
    $activate_query = "UPDATE elections 
                       SET status = 'active' 
                       WHERE status = 'upcoming' 
                       AND approval_status = 'approved' 
                       AND start_date <= NOW()";
    $conn->query($activate_query);
    
    // Update elections to 'completed' if end date has passed and status is 'active'
    $complete_query = "UPDATE elections 
                       SET status = 'completed' 
                       WHERE status = 'active' 
                       AND end_date <= NOW()";
    $conn->query($complete_query);
    
    $conn->close();
}

// Function to get active elections with auto-update
function getActiveElections() {
    autoUpdateElectionStatus();
    
    $conn = getDBConnection();
    $query = "SELECT * FROM elections 
              WHERE status = 'active' 
              AND approval_status = 'approved' 
              AND NOW() BETWEEN start_date AND end_date 
              ORDER BY start_date";
    $result = $conn->query($query);
    $conn->close();
    
    return $result;
}

// Function to get upcoming elections with auto-update
function getUpcomingElections() {
    autoUpdateElectionStatus();
    
    $conn = getDBConnection();
    $query = "SELECT * FROM elections 
              WHERE status = 'upcoming' 
              AND approval_status = 'approved' 
              AND start_date > NOW() 
              ORDER BY start_date";
    $result = $conn->query($query);
    $conn->close();
    
    return $result;
}

// Function to get completed elections
function getCompletedElections() {
    autoUpdateElectionStatus();
    
    $conn = getDBConnection();
    $query = "SELECT * FROM elections 
              WHERE status = 'completed' 
              ORDER BY end_date DESC";
    $result = $conn->query($query);
    $conn->close();
    
    return $result;
}
?>