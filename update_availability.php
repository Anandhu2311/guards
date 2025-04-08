<?php
session_start();
require_once 'DBS.inc.php';

// Check if user is logged in and is a counselor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 3) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Validate input parameters
if (!isset($_POST['schedule_id']) || !isset($_POST['is_available'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit();
}

$schedule_id = intval($_POST['schedule_id']);
$is_available = intval($_POST['is_available']);
$staff_email = $_SESSION['email'];

try {
    // First get counselor name and schedule details in a single transaction
    $pdo->beginTransaction();
    
    // Get counselor name
    $counselorStmt = $pdo->prepare("SELECT coun_name FROM counselors WHERE coun_email = ?");
    $counselorStmt->execute([$staff_email]);
    $counselor = $counselorStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$counselor) {
        throw new Exception('Counselor not found');
    }
    
    // Get schedule details
    $scheduleStmt = $pdo->prepare("SELECT day, start_time, end_time FROM schedules WHERE id = ?");
    $scheduleStmt->execute([$schedule_id]);
    $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    // Check if record exists
    $checkStmt = $pdo->prepare("SELECT id FROM staff_availability WHERE schedule_id = ? AND staff_email = ?");
    $checkStmt->execute([$schedule_id, $staff_email]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing record
        $sql = "UPDATE staff_availability 
                SET is_available = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE schedule_id = ? AND staff_email = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$is_available, $schedule_id, $staff_email]);
    } else {
        // Insert new record with all required fields
        $sql = "INSERT INTO staff_availability 
                (schedule_id, staff_email, staff_name, role_id, is_available, day, start_time, end_time, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $schedule_id,
            $staff_email,
            $counselor['coun_name'],
            $_SESSION['role_id'],
            $is_available,
            $schedule['day'],
            $schedule['start_time'],
            $schedule['end_time']
        ]);
    }

    if ($success) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Availability updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update availability');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error updating availability: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>