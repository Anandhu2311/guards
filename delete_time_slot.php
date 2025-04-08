<?php
session_start();
require 'DBS.inc.php';

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Log function for debugging
function log_debug($message) {
    file_put_contents('schedule_delete.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Accept both JSON and form data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // If not JSON, check POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
    
    $scheduleId = $data['id'] ?? 0;
    
    log_debug("delete_time_slot.php called with ID: $scheduleId");
    
    if (!$scheduleId) {
        log_debug("Missing schedule ID");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
        exit();
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First delete any staff availability records for this schedule
        $stmt1 = $pdo->prepare("DELETE FROM staff_availability WHERE schedule_id = :id");
        $stmt1->bindParam(':id', $scheduleId);
        $stmt1->execute();
        
        // Then delete the schedule itself
        $stmt2 = $pdo->prepare("DELETE FROM schedules WHERE id = :id");
        $stmt2->bindParam(':id', $scheduleId);
        $result = $stmt2->execute();
        
        if ($result) {
            $pdo->commit();
            log_debug("Schedule $scheduleId deleted successfully");
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            $pdo->rollBack();
            log_debug("Failed to delete schedule $scheduleId");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete schedule']);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        log_debug("PDO Exception: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        $pdo->rollBack();
        log_debug("General Exception: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 