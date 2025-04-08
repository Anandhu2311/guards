<?php
// Start output buffering to capture all errors
ob_start();

session_start();
require 'DBS.inc.php';

// Debug info - write to log file
$logFile = 'toggle_debug.log';
file_put_contents($logFile, "Request received: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($logFile, "GET params: " . print_r($_GET, true) . "\n", FILE_APPEND);
file_put_contents($logFile, "POST params: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Check admin login
$admin_email = "admin@gmail.com"; 
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
    if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Authentication failed']);
        exit;
    }
    header("Location: signin.php");
    exit();
}

// Get parameters from URL/GET
$schedule_id = isset($_GET['schedule_id']) ? $_GET['schedule_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

file_put_contents($logFile, "Parsed params: schedule_id=$schedule_id, status=$status\n", FILE_APPEND);

// Validate input
if (empty($schedule_id) || $status === '') {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    $_SESSION['error_message'] = "Missing required fields";
    header("Location: admin.php");
    exit();
}

// Update the schedule status
try {
    $sql = "UPDATE schedules SET is_active = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $schedule_id);
    
    file_put_contents($logFile, "Executing query: $sql with id=$schedule_id, status=$status\n", FILE_APPEND);
    
    if ($stmt->execute()) {
        if ($is_ajax) {
            echo json_encode(['success' => true, 'message' => 'Schedule status updated successfully']);
            exit;
        }
        file_put_contents($logFile, "Query executed successfully\n", FILE_APPEND);
        $_SESSION['success_message'] = "Schedule status updated successfully";
    } else {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule status']);
            exit;
        }
        file_put_contents($logFile, "Query execution failed\n", FILE_APPEND);
        $_SESSION['error_message'] = "Failed to update schedule status";
    }
} catch (PDOException $e) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    file_put_contents($logFile, "Database exception: " . $e->getMessage() . "\n", FILE_APPEND);
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

// Capture any output and errors
$output = ob_get_clean();
if (!empty($output)) {
    file_put_contents($logFile, "Captured output: $output\n", FILE_APPEND);
}

// This will only run for non-Ajax requests
file_put_contents($logFile, "Redirecting to admin.php\n", FILE_APPEND);
header("Location: admin.php");
exit();
?> 