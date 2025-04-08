<?php
session_start();
require 'DBS.inc.php';

// Set content type header
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo json_encode(['hasConflict' => true, 'message' => 'Unauthorized access']);
    exit();
}

// Debug function
function debug_log($data, $label = '') {
    error_log(date('[Y-m-d H:i:s] ') . $label . ': ' . print_r($data, true));
}

// Validate required fields
if (!isset($_POST['day']) || !isset($_POST['startTime']) || !isset($_POST['endTime'])) {
    echo json_encode(['hasConflict' => true, 'message' => 'Missing required fields']);
    exit();
}

// Normalize inputs to prevent false positives
$day = trim($_POST['day']);
$startTime = trim($_POST['startTime']);
$endTime = trim($_POST['endTime']);
$excludeId = isset($_POST['excludeId']) && !empty($_POST['excludeId']) ? $_POST['excludeId'] : null;

debug_log(compact('day', 'startTime', 'endTime', 'excludeId'), 'Checking conflicts with params');

try {
    // Check for overlapping times with improved query
    $sql = "SELECT id, day, start_time, end_time FROM schedules 
            WHERE day = :day 
            AND (
                (start_time < :endTime AND end_time > :startTime) OR
                (start_time = :startTime AND end_time = :endTime)
            )";
    
    if ($excludeId) {
        $sql .= " AND id != :excludeId";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':day', $day);
    $stmt->bindParam(':startTime', $startTime);
    $stmt->bindParam(':endTime', $endTime);
    
    if ($excludeId) {
        $stmt->bindParam(':excludeId', $excludeId);
    }
    
    $stmt->execute();
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debug_log(count($conflicts), 'Total conflicts found');
    
    if (count($conflicts) > 0) {
        // Check if it's an exact duplicate or just an overlap
        $exactDuplicate = false;
        foreach ($conflicts as $conflict) {
            if ($conflict['start_time'] == $startTime && $conflict['end_time'] == $endTime) {
                $exactDuplicate = true;
                break;
            }
        }
        
        $message = $exactDuplicate ? 
            'An identical time slot already exists for this day and time' : 
            'This time slot overlaps with existing scheduled slot(s)';
        
        echo json_encode([
            'hasConflict' => true, 
            'message' => $message,
            'conflicts' => $conflicts,
            'type' => $exactDuplicate ? 'exact_duplicate' : 'overlap'
        ]);
    } else {
        echo json_encode(['hasConflict' => false, 'message' => 'No conflicts found']);
    }
} catch (PDOException $e) {
    debug_log($e->getMessage(), 'Database error in conflict check');
    echo json_encode(['hasConflict' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 