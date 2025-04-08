<?php
session_start();
// Simple utility to create files - ADMIN ONLY
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (isset($_POST['create_file']) && isset($_POST['content'])) {
    $filename = $_POST['create_file'];
    $content = $_POST['content'];
    
    // Only allow creating specific files for security
    $allowedFiles = ['save_schedule.php', 'get_schedule.php'];
    
    if (!in_array($filename, $allowedFiles)) {
        echo json_encode(['success' => false, 'message' => 'File creation not allowed']);
        exit();
    }
    
    try {
        file_put_contents($filename, $content);
        echo json_encode(['success' => true, 'message' => 'File created successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
}
?> 