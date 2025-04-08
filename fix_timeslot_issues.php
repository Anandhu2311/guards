<?php
// fix_timeslot_issues.php
session_start();
require_once 'DBS.inc.php';

// Handle time slot toggle status request
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status') {
    $scheduleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $newStatus = isset($_GET['status']) ? intval($_GET['status']) : 0;
    
    try {
        // Directly modify status in database
        $sql = "UPDATE schedules SET is_active = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $scheduleId);
        $result = $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'id' => $scheduleId,
            'status' => $newStatus
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Get default start and end times for the form
if (isset($_GET['action']) && $_GET['action'] == 'get_default_times') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'start_time' => '09:00',
        'end_time' => '10:00'
    ]);
    exit;
}

// Default response
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'No action specified']);
