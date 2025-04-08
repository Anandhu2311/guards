<?php
// This file handles fetching schedule data for editing
session_start();
require 'DBS.inc.php'; // Database connection file

// Set headers for JSON response
header('Content-Type: application/json');

// Check if ID parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing schedule ID']);
    exit();
}

$scheduleId = intval($_GET['id']);

try {
    // Get schedule data
    $sql = "SELECT * FROM schedules WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $scheduleId);
    $stmt->execute();
    
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($schedule) {
        echo json_encode(['success' => true, 'schedule' => $schedule]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 