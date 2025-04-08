<?php
session_start();
require 'DBS.inc.php';

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Check if we're receiving form data or JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle form data
        $day = $_POST['day'] ?? null;
        $startTime = $_POST['startTime'] ?? null;
        $endTime = $_POST['endTime'] ?? null;
        $isActive = isset($_POST['isActive']) ? $_POST['isActive'] : '1';
        $id = $_POST['id'] ?? null;
        $userType = $_POST['userType'] ?? null; // This will be mapped to role_id
    } else {
        // Get JSON data (original method)
        $data = json_decode(file_get_contents('php://input'), true);
        $day = $data['day'] ?? null;
        $startTime = $data['startTime'] ?? null;
        $endTime = $data['endTime'] ?? null;
        $isActive = $data['isActive'] ?? '1';
        $id = $data['id'] ?? null;
        $userType = $data['userType'] ?? null; // This will be mapped to role_id
    }

    // Validate input
    if (empty($day) || empty($startTime) || empty($endTime)) {
        throw new Exception('Missing required fields');
    }
    
    // Check if it's an update or insert
    if ($id) {
        // Update existing schedule
        $stmt = $pdo->prepare("UPDATE schedules SET day = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
        $result = $stmt->execute([$day, $startTime, $endTime, $isActive, $id]);
        $message = 'Schedule updated successfully';
    } else {
        // Insert new schedule
        $stmt = $pdo->prepare("INSERT INTO schedules (day, start_time, end_time, is_active) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$day, $startTime, $endTime, $isActive]);
        $id = $pdo->lastInsertId();
        $message = 'Schedule created successfully';
    }
    
    // Add debug output to verify execution
    error_log("Schedule operation: " . ($result ? "Success" : "Failed") . " - " . $message);
    
    if ($result) {
        // Return success with new ID if applicable
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'id' => $id
        ]);
    } else {
        throw new Exception('Database operation failed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 