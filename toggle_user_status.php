<?php
session_start();
require 'DBS.inc.php';

// Check admin authentication
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['userId'] ?? 0;
    $userType = $_POST['userType'] ?? '';
    $status = $_POST['status'] ?? 1;
    
    // Validate inputs
    if (!$userId || empty($userType)) {
        echo json_encode(['success' => false, 'message' => 'Invalid user data']);
        exit();
    }
    
    // Determine which table to update
    $table = '';
    switch ($userType) {
        case 'counselor':
            $table = 'counselors';
            break;
        case 'supporter':
            $table = 'supporters';
            break;
        case 'advisor':
            $table = 'advisors';
            break;
        case 'user':
            $table = 'users';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid user type']);
            exit();
    }
    
    try {
        // Update the user status
        $sql = "UPDATE $table SET is_active = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 