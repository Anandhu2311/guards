<?php
session_start();
require 'DBS.inc.php';

// Enable error reporting for logging but not displaying
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is admin
$admin_email = "admin@gmail.com"; // Same as in admin.php
if (!isset($_SESSION['email']) || $_SESSION['email'] !== $admin_email) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Log function
function log_debug($message) {
    file_put_contents('schedule_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_debug("get_schedules.php called");

// Function to get all schedules including staff availability
function getSchedulesWithAvailability($conn) {
    $sql = "SELECT s.*, sa.staff_id, sa.user_type, 
            CONCAT(COALESCE(u.first_name, c.first_name, a.first_name, sp.first_name), ' ', 
                  COALESCE(u.last_name, c.last_name, a.last_name, sp.last_name)) as staff_name  
            FROM schedules s
            LEFT JOIN staff_availability sa ON s.id = sa.schedule_id
            LEFT JOIN users u ON sa.staff_id = u.id AND sa.user_type = 'user'
            LEFT JOIN counselors c ON sa.staff_id = c.id AND sa.user_type = 'counselor'
            LEFT JOIN advisors a ON sa.staff_id = a.id AND sa.user_type = 'advisor'
            LEFT JOIN supporters sp ON sa.staff_id = sp.id AND sa.user_type = 'supporter'
            ORDER BY s.day, s.start_time";
    
    $stmt = $conn->query($sql);
    $schedules = [];
    
    if ($stmt) {
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $schedules;
}

try {
    // Get all schedules with staff count
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM staff_availability WHERE schedule_id = s.id AND is_available = 1) as staff_count
            FROM schedules s
            ORDER BY 
                CASE 
                    WHEN s.day = 'Monday' THEN 1
                    WHEN s.day = 'Tuesday' THEN 2
                    WHEN s.day = 'Wednesday' THEN 3
                    WHEN s.day = 'Thursday' THEN 4
                    WHEN s.day = 'Friday' THEN 5
                    WHEN s.day = 'Saturday' THEN 6
                    WHEN s.day = 'Sunday' THEN 7
                END,
                s.start_time";
    
    $stmt = $pdo->query($sql);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($schedules);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 