<?php
require_once 'config.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get all days of the week
    $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    // Get days that have schedules
    $stmt = $pdo->prepare("SELECT DISTINCT day FROM schedules WHERE is_active = 1");
    $stmt->execute();
    $scheduledDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find days without schedules
    $unscheduledDays = array_diff($allDays, $scheduledDays);
    
    // Return the result
    echo json_encode([
        'success' => true,
        'unscheduledDays' => array_values($unscheduledDays)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 