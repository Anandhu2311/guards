<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Check if user is logged in as admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate schedule_id parameter
if (!isset($_GET['schedule_id']) || !is_numeric($_GET['schedule_id'])) {
    echo json_encode(['error' => 'Invalid schedule ID']);
    exit();
}

$scheduleId = (int)$_GET['schedule_id'];

try {
    // Query to get available staff for the schedule
    $sql = "SELECT u.id, u.name, u.email, r.name as role_name, u.is_active 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN staff_availability sa ON u.id = sa.staff_id
            WHERE sa.schedule_id = :schedule_id
            AND sa.is_available = 1
            ORDER BY r.name, u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->execute();
    
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($staff);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?> 