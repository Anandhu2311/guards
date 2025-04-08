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
log_debug("get_staff_availability.php called with schedule_id: " . $scheduleId);

try {
    // Get all staff by role
    $sql = "SELECT r.name as role_name, 
            u.id, u.name, u.email, u.is_active,
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
    
    // Group staff by role type
    $staffByType = [];
    foreach ($staff as $member) {
        $roleType = $member['role_name'];
        if (!isset($staffByType[$roleType])) {
            $staffByType[$roleType] = [];
        }
        
        $staffByType[$roleType][] = [
            'staff_id' => $member['id'],
            'staff_name' => $member['name'],
            'staff_email' => $member['email'],
            'staff_active' => $member['is_active'],
            'user_type' => $roleType,
            'is_available' => $member['is_available']
        ];
    }
    
    // Convert to flat array for easier processing in JavaScript
    $result = [];
    foreach ($staffByType as $type => $members) {
        foreach ($members as $member) {
            if ($member['is_available'] == 1) { // Only include available staff
                $result[] = $member;
            }
        }
    }
    
    log_debug("Returning " . count($result) . " available staff members");
    header('Content-Type: application/json');
    echo json_encode($result);
    
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