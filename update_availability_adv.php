<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    error_log("Unauthorized access attempt - Session: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check required parameters
if (!isset($_POST['schedule_id']) || !isset($_POST['is_available'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    $scheduleId = intval($_POST['schedule_id']);
    $isAvailable = intval($_POST['is_available']);
    $advisor_email = $_SESSION['email'];
    
    // Get advisor name
    $stmt = $pdo->prepare("SELECT adv_name as staff_name FROM advisors WHERE adv_email = ? AND is_active = 1");
    $stmt->execute([$advisor_email]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        throw new Exception('Advisor not found or inactive');
    }

    // Get schedule details
    $scheduleStmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND is_active = 1");
    $scheduleStmt->execute([$scheduleId]);
    $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        throw new Exception('Schedule not found or inactive');
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Check if record exists
        $checkStmt = $pdo->prepare("SELECT id FROM staff_availability WHERE schedule_id = ? AND staff_email = ?");
        $checkStmt->execute([$scheduleId, $advisor_email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $sql = "UPDATE staff_availability SET is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE schedule_id = ? AND staff_email = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$isAvailable, $scheduleId, $advisor_email]);
        } else {
            $sql = "INSERT INTO staff_availability (schedule_id, staff_email, staff_name, role_id, is_available, day, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                $scheduleId,
                $advisor_email,
                $staff['staff_name'],
                $_SESSION['role_id'],
                $isAvailable,
                $schedule['day'],
                $schedule['start_time'],
                $schedule['end_time']
            ]);
        }

        if ($success) {
            $pdo->commit();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to update availability');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log('Error in availability update: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
