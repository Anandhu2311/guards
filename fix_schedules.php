<?php
session_start();
require_once 'DBS.inc.php';

// Set the content type to JSON
header('Content-Type: application/json');

try {
    // Get all scheduled days
    $stmt = $pdo->query("
        SELECT 
            id, 
            day, 
            start_time, 
            end_time, 
            is_active
        FROM schedules 
        ORDER BY 
            CASE 
                WHEN day = 'Monday' THEN 1
                WHEN day = 'Tuesday' THEN 2
                WHEN day = 'Wednesday' THEN 3
                WHEN day = 'Thursday' THEN 4
                WHEN day = 'Friday' THEN 5
                WHEN day = 'Saturday' THEN 6
                WHEN day = 'Sunday' THEN 7
                ELSE 8
            END, 
            start_time
    ");
    
    // Get all schedules as array
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get days with no slots (unscheduled days)
    $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $scheduledDays = array_unique(array_column($schedules, 'day'));
    $unscheduledDays = array_diff($allDays, $scheduledDays);
    
    // Return both schedules and unscheduled days
    echo json_encode([
        'schedules' => $schedules,
        'unscheduledDays' => array_values($unscheduledDays)
    ]);
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>