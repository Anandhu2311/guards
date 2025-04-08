<?php
session_start();
require 'DBS.inc.php';

// Check admin authentication
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Log function for debugging
function log_debug($message) {
    file_put_contents('staff_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['schedule_id']) || empty($_GET['schedule_id'])) {
    log_debug("No schedule ID provided");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Schedule ID is required']);
    exit();
}

$scheduleId = (int) $_GET['schedule_id'];
log_debug("get_all_staff.php called with schedule_id: " . $scheduleId);

try {
    // Get all staff who can be assigned to schedules (counselors, supporters, advisors)
    $sql = "SELECT u.id, u.name, u.email, u.is_active, r.name as role_name,
            IFNULL(sa.is_available, 0) as is_available
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN staff_availability sa ON sa.staff_id = u.id AND sa.schedule_id = :schedule_id
            WHERE r.name IN ('counselor', 'supporter', 'advisor')
            ORDER BY r.name, u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':schedule_id', $scheduleId);
    $stmt->execute();
    
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_debug("Found " . count($staff) . " staff members");
    
    header('Content-Type: application/json');
    echo json_encode($staff);
    
} catch (PDOException $e) {
    log_debug("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    log_debug("General error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 