<?php
session_start();
require 'DBS.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Log function
function log_debug($message) {
    file_put_contents('schedule_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_debug("update_schedule.php called with POST data: " . print_r($_POST, true));

// Check if form data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = $_POST['scheduleId'] ?? 0;
    $day = $_POST['day'] ?? '';
    $startTime = $_POST['startTime'] ?? '';
    $endTime = $_POST['endTime'] ?? '';
    $userType = $_POST['userType'] ?? '';
    $isActive = isset($_POST['isActive']) ? (int)$_POST['isActive'] : 1;
    
    // Validate inputs
    if (!$scheduleId || empty($day) || empty($startTime) || empty($endTime) || empty($userType)) {
        log_debug("Missing required fields");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    // Check for time validity (start time must be before end time)
    if ($startTime >= $endTime) {
        log_debug("Start time must be before end time");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
        exit();
    }
    
    try {
        // First check if the schedule exists
        $checkSql = "SELECT * FROM schedules WHERE id = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':id', $scheduleId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            log_debug("Schedule not found");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Schedule not found']);
            exit();
        }
        
        // Check for conflicts with other user types
        $checkConflictSql = "SELECT * FROM schedules 
                            WHERE day = :day 
                            AND ((start_time <= :startTime AND end_time > :startTime) 
                                OR (start_time < :endTime AND end_time >= :endTime)
                                OR (start_time >= :startTime AND end_time <= :endTime))
                            AND user_type != :userType
                            AND id != :id";
        
        $checkStmt = $pdo->prepare($checkConflictSql);
        $checkStmt->bindParam(':day', $day);
        $checkStmt->bindParam(':startTime', $startTime);
        $checkStmt->bindParam(':endTime', $endTime);
        $checkStmt->bindParam(':userType', $userType);
        $checkStmt->bindParam(':id', $scheduleId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $conflict = $checkStmt->fetch(PDO::FETCH_ASSOC);
            log_debug("This time slot conflicts with an existing {$conflict['user_type']} slot");
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "This time slot conflicts with an existing {$conflict['user_type']} slot"
            ]);
            exit();
        }
        
        // Update the schedule
        $sql = "UPDATE schedules 
                SET day = :day, 
                    start_time = :startTime, 
                    end_time = :endTime, 
                    user_type = :userType, 
                    is_active = :isActive 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':day', $day);
        $stmt->bindParam(':startTime', $startTime);
        $stmt->bindParam(':endTime', $endTime);
        $stmt->bindParam(':userType', $userType);
        $stmt->bindParam(':isActive', $isActive);
        $stmt->bindParam(':id', $scheduleId);
        
        log_debug("Executing SQL: " . $sql . " with params: id=$scheduleId, day=$day, startTime=$startTime, endTime=$endTime, userType=$userType, isActive=$isActive");
        
        if ($stmt->execute()) {
            log_debug("Update successful");
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            $errorInfo = $stmt->errorInfo();
            log_debug("Update failed: " . print_r($errorInfo, true));
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule', 'error' => $errorInfo]);
        }
    } catch (PDOException $e) {
        log_debug("PDO Exception: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        log_debug("General Exception: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
    }
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 