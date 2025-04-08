<?php
session_start();
require 'DBS.inc.php';

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get all schedules
    $stmt = $pdo->query("SELECT id, day, start_time, end_time, user_type FROM schedules");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transform schedules into FullCalendar events format
    $events = [];
    foreach ($schedules as $schedule) {
        // Map days to dates for the current week
        $dayToNumber = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 0
        ];
        
        // Set colors based on user type
        $colors = [
            'supporter' => '#4CAF50',
            'advisor' => '#2196F3',
            'counselor' => '#FF9800'
        ];
        
        // Get date for the day of the week
        $dayNumber = $dayToNumber[$schedule['day']];
        $date = date('Y-m-d', strtotime("this week Sunday +{$dayNumber} days"));
        
        // Create event
        $events[] = [
            'id' => $schedule['id'],
            'title' => ucfirst($schedule['user_type']) . ' Shift',
            'start' => $date . 'T' . $schedule['start_time'],
            'end' => $date . 'T' . $schedule['end_time'],
            'backgroundColor' => $colors[$schedule['user_type']] ?? '#663399',
            'borderColor' => $colors[$schedule['user_type']] ?? '#663399'
        ];
    }
    
    // Return as JSON
    header('Content-Type: application/json');
    echo json_encode($events);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 