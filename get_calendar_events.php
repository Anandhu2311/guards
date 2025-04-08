<?php
session_start();
require 'DBS.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Log function
function log_debug($message) {
    file_put_contents('calendar_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_debug("get_calendar_events.php called");

try {
    // Get all schedules with date information
    $sql = "SELECT * FROM schedules";
    $stmt = $pdo->query($sql);
    $events = [];

    if ($stmt) {
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        log_debug("Retrieved " . count($schedules) . " schedules");
        
        foreach ($schedules as $row) {
            // For recurring weekly schedules, we need to create multiple events
            // This is a simplified version - in a real app, you would handle date ranges properly
            $year = date('Y');
            $month = date('m');
            
            // Get all dates for this day of the week in the current month
            $dates = [];
            $startDate = new DateTime("first " . $row['day'] . " of $year-$month");
            $endDate = new DateTime("last day of $year-$month");
            
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dates[] = $currentDate->format('Y-m-d');
                $currentDate->modify('+1 week');
            }
            
            // Create an event for each date
            foreach ($dates as $date) {
                $events[] = [
                    'id' => $row['id'],
                    'date' => $date,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'user_type' => $row['user_type']
                ];
            }
        }
        
        log_debug("Generated " . count($events) . " calendar events");
    } else {
        log_debug("Query failed: " . print_r($pdo->errorInfo(), true));
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($events);
} catch (PDOException $e) {
    log_debug("PDO Exception: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    log_debug("General Exception: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'General error: ' . $e->getMessage()]);
}
?> 