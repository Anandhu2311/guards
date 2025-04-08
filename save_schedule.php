<?php
// Start by turning off output buffering and clearing any previous output
ob_end_clean();
ob_start();

// Start session if not already started
session_start();
require 'DBS.inc.php';

// Force JSON content type from the beginning
header('Content-Type: application/json');

// Essential error handling
try {
    // Enable error reporting for logging but not displaying
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Turn off error display
    
    // Log function for debugging
    function debug_log($data, $label = '') {
        error_log(date('[Y-m-d H:i:s] ') . $label . ': ' . print_r($data, true));
    }
    
    // Log all incoming data
    debug_log("Request received - Method: " . $_SERVER['REQUEST_METHOD']);
    debug_log("POST data: " . print_r($_POST, true));
    
    // Check database connection
    try {
        $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        debug_log("Database connection successful");
    } catch (PDOException $e) {
        debug_log("Database connection failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
        exit();
    }
    
    // Check if user is admin
    if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    // Fixed function to check for exact duplicate time slots
    function checkForDuplicates($conn, $day, $startTime, $endTime, $excludeId = null) {
        debug_log(compact('day', 'startTime', 'endTime', 'excludeId'), 'Checking duplicates with params');
        
        try {
            // Normalize inputs to prevent whitespace/case issues
            $day = trim($day);
            $startTime = trim($startTime);
            $endTime = trim($endTime);
            
            // Direct SQL query to find any exact matches
            $sql = "SELECT COUNT(*) FROM schedules 
                    WHERE day = :day 
                    AND start_time = :startTime 
                    AND end_time = :endTime";
            
            if ($excludeId) {
                $sql .= " AND id != :excludeId";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':day', $day);
            $stmt->bindParam(':startTime', $startTime);
            $stmt->bindParam(':endTime', $endTime);
            
            if ($excludeId) {
                $stmt->bindParam(':excludeId', $excludeId);
            }
            
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            debug_log($count, 'Found duplicates count');
            return $count > 0;
        } catch (PDOException $e) {
            debug_log($e->getMessage(), 'Database error in checkForDuplicates');
            return false; // Changed to false - don't assume duplication on error
        }
    }

    // NEW: Function to validate if the time slot is in the past
    function isPastTimeSlot($day, $startTime) {
        $today = strtolower(date('l')); // Get current day name in lowercase
        $currentTime = date('H:i'); // Get current time in 24h format
        $dayIndex = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 
            'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0
        ];
        
        $todayIndex = $dayIndex[strtolower($today)];
        $slotDayIndex = $dayIndex[strtolower($day)];
        
        // Calculate days difference (considering the week wraps around)
        $daysDiff = ($slotDayIndex - $todayIndex + 7) % 7;
        
        // If it's a past day this week
        if ($daysDiff > 0) {
            return false; // It's a future day, so not in the past
        } 
        // If it's today, check the time
        else if ($daysDiff === 0) {
            return $startTime <= $currentTime; // True if start time has passed
        }
        // It's a past day in the current week
        else {
            return true;
        }
    }

    // Handle different actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        debug_log($_POST, 'POST data received');
        
        $action = $_POST['action'] ?? '';
        debug_log($action, 'Action');

        // Special case for updateStatus action
        if ($action === 'updateStatus') {
            // Validate schedule ID
            if (!isset($_POST['scheduleId']) || !is_numeric($_POST['scheduleId'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
                exit();
            }

            $scheduleId = (int)$_POST['scheduleId'];
            $isActive = isset($_POST['isActive']) ? (int)$_POST['isActive'] : 1;

            // Update status
            $sql = "UPDATE schedules SET is_active = :isActive WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $scheduleId);
            $stmt->bindParam(':isActive', $isActive);
            $success = $stmt->execute();

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating status']);
            }
            exit(); // Exit here, don't continue with other validations
        }

        // Validate required fields for other actions
        if (!isset($_POST['day']) || !isset($_POST['startTime']) || !isset($_POST['endTime'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }

        $day = trim($_POST['day']);
        $startTime = trim($_POST['startTime']);
        $endTime = trim($_POST['endTime']);
        $isActive = isset($_POST['isActive']) ? (int)$_POST['isActive'] : 1;
        $scheduleId = $_POST['scheduleId'] ?? '';
        // Set a default userType if not provided
        $userType = isset($_POST['userType']) ? trim($_POST['userType']) : 'general';

        debug_log(compact('day', 'startTime', 'endTime', 'isActive', 'scheduleId', 'userType'), 'Validated input data');

        // Validate time format
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $startTime) || 
            !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $endTime)) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit();
        }

        // Validate day
        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        if (!in_array($day, $validDays)) {
            echo json_encode(['success' => false, 'message' => 'Invalid day']);
            exit();
        }

        // Ensure end time is after start time
        if ($startTime >= $endTime) {
            echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
            exit();
        }
        
        // NEW: Validate if the time slot is not in the past
        if (isPastTimeSlot($day, $startTime)) {
            $today = date('l');
            if (strtolower($day) === strtolower($today)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot create a time slot for today that has already passed',
                    'isPastTime' => true
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot create a time slot for past days',
                    'isPastDay' => true
                ]);
            }
            exit();
        }

        try {
            // Switch based on the action
            switch ($action) {
                case 'create':
                    // COMPLETELY REWRITTEN to fix parameter binding issue
                    try {
                        // First check for exact duplicates with explicit array parameters
                        $exactDuplicateParams = [
                            ':day' => $day,
                            ':startTime' => $startTime,
                            ':endTime' => $endTime
                        ];
                        
                        $sql = "SELECT COUNT(*) FROM schedules 
                                WHERE day = :day 
                                AND start_time = :startTime 
                                AND end_time = :endTime";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($exactDuplicateParams);
                        $exactCount = $stmt->fetchColumn();
                        
                        if ($exactCount > 0) {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'An identical time slot already exists for this day and time',
                                'hasConflict' => true
                            ]);
                            exit();
                        }
                        
                        // Then check for overlaps with explicit array parameters
                        $overlapParams = [
                            ':day' => $day,
                            ':startTime' => $startTime,
                            ':endTime' => $endTime
                        ];
                        
                        $sql = "SELECT COUNT(*) FROM schedules 
                                WHERE day = :day 
                                AND (
                                    (start_time < :endTime AND end_time > :startTime)
                                )";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($overlapParams);
                        $overlapCount = $stmt->fetchColumn();
                        
                        if ($overlapCount > 0) {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'This time slot conflicts with existing scheduled slot(s)',
                                'hasConflict' => true
                            ]);
                            exit();
                        }
                        
                        // Create new schedule with explicit array parameters
                        $insertParams = [
                            ':day' => $day,
                            ':startTime' => $startTime,
                            ':endTime' => $endTime,
                            ':isActive' => $isActive
                        ];
                        
                        $sql = "INSERT INTO schedules (day, start_time, end_time, is_active) 
                                VALUES (:day, :startTime, :endTime, :isActive)";
                        $stmt = $pdo->prepare($sql);
                        $success = $stmt->execute($insertParams);
                        
                        if ($success) {
                            echo json_encode(['success' => true, 'message' => 'Schedule created successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error creating schedule']);
                        }
                    } catch (PDOException $e) {
                        // Catch and report errors specifically for create operation
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Database error in create operation: ' . $e->getMessage()
                        ]);
                    }
                    break;

                case 'update':
                    // Validate schedule ID
                    if (!isset($_POST['scheduleId']) || !is_numeric($_POST['scheduleId'])) {
                        echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
                        exit();
                    }

                    $scheduleId = (int)$_POST['scheduleId'];
                    debug_log($scheduleId, 'Update schedule ID');
                    
                    // Check for overlap with existing slots
                    $sql = "SELECT COUNT(*) FROM schedules 
                            WHERE day = :day 
                            AND (
                                (start_time < :endTime AND end_time > :startTime) OR
                                (start_time = :startTime AND end_time = :endTime)
                            )
                            AND id != :scheduleId";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':day', $day);
                    $stmt->bindParam(':startTime', $startTime);
                    $stmt->bindParam(':endTime', $endTime);
                    $stmt->bindParam(':scheduleId', $scheduleId);
                    $stmt->execute();
                    $overlapCount = $stmt->fetchColumn();
                    
                    if ($overlapCount > 0) {
                        echo json_encode([
                            'success' => false, 
                            'message' => 'This time slot conflicts with existing scheduled slot(s)',
                            'hasConflict' => true
                        ]);
                        exit();
                    }
                    
                    // Update existing time slot
                    $sql = "UPDATE schedules 
                            SET day = :day, start_time = :startTime, end_time = :endTime, is_active = :isActive 
                            WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $scheduleId);
                    $stmt->bindParam(':day', $day);
                    $stmt->bindParam(':startTime', $startTime);
                    $stmt->bindParam(':endTime', $endTime);
                    $stmt->bindParam(':isActive', $isActive);
                    $success = $stmt->execute();

                    if ($success) {
                        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error updating schedule']);
                    }
                    break;

                case 'updateStatus': 
                    // This case is now handled above for better flow control
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } catch (PDOException $e) {
            // Log error and return to user
            debug_log($e->getMessage(), 'PDO Exception');
            
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'unique_') !== false) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Duplicate time slot detected by database: ' . $e->getMessage(),
                    'hasConflict' => true
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    // Catch any unexpected errors and return JSON instead of HTML
    debug_log("Uncaught Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// Flush the output buffer and end it
ob_end_flush();
?> 