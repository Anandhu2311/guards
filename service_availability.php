<?php
// Required includes
require_once 'DBS.inc.php'; // Database connection

session_start();
header('Content-Type: application/json');

// Function to check availability for a specific schedule
function checkAvailability($pdo, $scheduleId) {
    try {
        // First check if schedule exists and is active
        $scheduleStmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND is_active = 1");
        $scheduleStmt->execute([$scheduleId]);
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$schedule) {
            return [
                'success' => false,
                'message' => 'Schedule not found or inactive'
            ];
        }
        
        // Then get all available providers for this schedule
        $providersSql = "SELECT u.id, u.name, u.email, r.name as provider_type
                         FROM staff_availability sa
                         JOIN users u ON sa.staff_id = u.id
                         JOIN roles r ON u.role_id = r.id
                         WHERE sa.schedule_id = ?
                         AND sa.is_available = 1
                         AND u.is_active = 1
                         ORDER BY r.name, u.name";
        
        $providersStmt = $pdo->prepare($providersSql);
        $providersStmt->execute([$scheduleId]);
        $providers = $providersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'is_available' => count($providers) > 0,
            'providers' => $providers,
            'schedule' => $schedule
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error checking availability: ' . $e->getMessage()
        ];
    }
}

// Handle the request
if (isset($_POST['action']) && $_POST['action'] === 'check_availability') {
    $scheduleId = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    
    if ($scheduleId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid schedule ID'
        ]);
        exit;
    }
    
    // Get database connection
    try {
        $host = 'localhost';
        $dbname = 'guardsphere';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $result = checkAvailability($pdo, $scheduleId);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid request'
]);