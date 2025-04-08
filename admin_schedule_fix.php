<?php
// Create a new file: admin_schedule_fix.php

session_start();
require_once 'DBS.inc.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get all schedules
    $schedules = getSchedules($pdo);
    
    // Get unscheduled days
    $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $scheduledDays = array_unique(array_column($schedules, 'day'));
    $unscheduledDays = array_diff($allDays, $scheduledDays);
    
    // Return both data sets
    echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'unscheduledDays' => array_values($unscheduledDays)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get all schedules from database
 */
function getSchedules($conn) {
    $sql = "SELECT * FROM schedules ORDER BY 
        CASE 
            WHEN day = 'Monday' THEN 1
            WHEN day = 'Tuesday' THEN 2
            WHEN day = 'Wednesday' THEN 3
            WHEN day = 'Thursday' THEN 4
            WHEN day = 'Friday' THEN 5
            WHEN day = 'Saturday' THEN 6
            WHEN day = 'Sunday' THEN 7
        END, 
        start_time";
    
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
