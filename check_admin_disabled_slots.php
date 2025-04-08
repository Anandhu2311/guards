<?php
// Turn off error reporting to prevent PHP errors from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Ensure session is started
session_start();

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once 'DBS.inc.php';

try {
    // Get user email from session
    $userEmail = $_SESSION['email'];
    $roleId = $_SESSION['role_id'];
    
    // Query to get all disabled schedules
    $sql = "SELECT s.* FROM schedules s 
            WHERE s.is_active = 0
            ORDER BY 
            CASE 
                WHEN day = 'Monday' THEN 1 
                WHEN day = 'Tuesday' THEN 2 
                WHEN day = 'Wednesday' THEN 3 
                WHEN day = 'Thursday' THEN 4 
                WHEN day = 'Friday' THEN 5 
                WHEN day = 'Saturday' THEN 6 
                WHEN day = 'Sunday' THEN 7 
            END, start_time";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $disabledSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($disabledSlots) > 0) {
        echo json_encode([
            'success' => true,
            'hasDisabledSlots' => true,
            'count' => count($disabledSlots),
            'disabledSlots' => $disabledSlots
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasDisabledSlots' => false
        ]);
    }
} catch (Exception $e) {
    error_log('Error checking admin disabled slots: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
