<?php
session_start();
require_once 'DBS.inc.php';

// Check if admin is logged in
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    header("Location: signin.php");
    exit();
}

// Get schedule ID and status
$scheduleId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

if ($scheduleId) {
    try {
        // Direct SQL update
        $sql = "UPDATE schedules SET is_active = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $scheduleId);
        $stmt->execute();
        
        // Set success message
        $_SESSION['status_message'] = "Schedule " . ($status ? "enabled" : "disabled") . " successfully.";
    } catch (Exception $e) {
        // Set error message
        $_SESSION['status_message'] = "Error: " . $e->getMessage();
    }
}

// Redirect back to admin page
header("Location: admin.php#schedule");
exit();
?>
