<?php
session_start();
require 'DBS.inc.php';

// Check if user is admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate input
    if (empty($data['id']) || empty($data['day']) || empty($data['startTime']) || 
        empty($data['endTime']) || empty($data['userType'])) {
        throw new Exception('Missing required fields');
    }
    
    // Update schedule
    $stmt = $pdo->prepare("UPDATE schedules SET day = ?, start_time = ?, end_time = ?, user_type = ? WHERE id = ?");
    $result = $stmt->execute([
        $data['day'],
        $data['startTime'],
        $data['endTime'],
        $data['userType'],
        $data['id']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update schedule');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 