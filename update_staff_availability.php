<?php
session_start();
require 'DBS.inc.php';

// Check admin authentication
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Log function for debugging
function log_debug($message) {
    file_put_contents('staff_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the data from POST
$scheduleId = $_POST['scheduleId'] ?? null;
$staffIds = json_decode($_POST['staffIds'] ?? '[]', true);
$staffTypes = json_decode($_POST['staffTypes'] ?? '[]', true);
$isAvailable = json_decode($_POST['isAvailable'] ?? '[]', true);

log_debug("update_staff_availability.php called with schedule_id: $scheduleId");
log_debug("Staff IDs: " . print_r($staffIds, true));
log_debug("Staff Types: " . print_r($staffTypes, true));
log_debug("Availability: " . print_r($isAvailable, true));

if (!$scheduleId || empty($staffIds) || count($staffIds) !== count($staffTypes) || count($staffIds) !== count($isAvailable)) {
    log_debug("Invalid or missing data");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, remove all existing availability records for this schedule
    $deleteStmt = $pdo->prepare("DELETE FROM staff_availability WHERE schedule_id = :schedule_id");
    $deleteStmt->bindParam(':schedule_id', $scheduleId);
    $deleteStmt->execute();
    
    // Add records for each staff member that is available
    $insertStmt = $pdo->prepare("
        INSERT INTO staff_availability (schedule_id, staff_id, user_type, is_available)
        VALUES (:schedule_id, :staff_id, :user_type, :is_available)
    ");
    
    for ($i = 0; $i < count($staffIds); $i++) {
        // Only add records for staff members marked as available
        if ($isAvailable[$i]) {
            $insertStmt->bindParam(':schedule_id', $scheduleId);
            $insertStmt->bindParam(':staff_id', $staffIds[$i]);
            $insertStmt->bindParam(':user_type', $staffTypes[$i]);
            $insertStmt->bindValue(':is_available', 1);
            $insertStmt->execute();
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    log_debug("Staff availability updated successfully");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Staff availability updated successfully']);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    log_debug("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    log_debug("General error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 