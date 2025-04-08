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

log_debug("delete_schedule.php called with ID: " . (isset($_GET['id']) ? $_GET['id'] : 'none'));

// Get ID from query parameter
$id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($id)) {
    log_debug("No schedule ID provided");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No schedule ID provided']);
    exit();
}

try {
    // Delete from database using PDO
    $sql = "DELETE FROM schedules WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    log_debug("Executing SQL: " . $sql . " with ID: " . $id);
    
    if ($stmt->execute()) {
        log_debug("Delete successful");
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        $errorInfo = $stmt->errorInfo();
        log_debug("Delete failed: " . print_r($errorInfo, true));
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to delete record', 'error' => $errorInfo]);
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
?> 