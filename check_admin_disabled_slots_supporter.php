<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

// Verify supporter authentication
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Get the supporter's email from session
    $supporter_email = $_SESSION['email'];
    
    // Query to check for admin-disabled slots where supporter was available
    $sql = "SELECT s.id, s.day, s.start_time, s.end_time, s.is_active
            FROM schedules s
            JOIN staff_availability sa ON s.id = sa.schedule_id 
            WHERE sa.staff_email = :email 
            AND sa.role_id = 4
            AND sa.is_available = 1 
            AND s.is_active = 0";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $supporter_email, PDO::PARAM_STR);
    $stmt->execute();
    
    $disabledSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If there are disabled slots, update supporter's availability
    if (!empty($disabledSlots)) {
        $updateStmt = $pdo->prepare("
            UPDATE staff_availability 
            SET is_available = 0 
            WHERE staff_email = :email 
            AND schedule_id = :schedule_id
            AND role_id = 4"
        );
        
        foreach ($disabledSlots as $slot) {
            $updateStmt->execute([
                ':email' => $supporter_email,
                ':schedule_id' => $slot['id']
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'disabled_slots' => $disabledSlots
    ]);
    
} catch (PDOException $e) {
    error_log('Error checking admin disabled slots: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 