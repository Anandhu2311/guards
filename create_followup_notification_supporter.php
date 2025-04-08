<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

// Verify supporter authentication
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $booking_id = intval($data['booking_id']);
    $supporter_email = $_SESSION['email'];
    
    // First verify this booking belongs to this supporter
    $checkStmt = $pdo->prepare("
        SELECT b.*, u.name as user_name, u.email as user_email 
        FROM bookings b
        JOIN users u ON b.user_email = u.email
        WHERE b.id = ? AND b.provider_email = ?
    ");
    $checkStmt->execute([$booking_id, $supporter_email]);
    $booking = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or unauthorized']);
        exit();
    }
    
    // Create the notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_email,
            provider_email,
            booking_id,
            message,
            type,
            created_at
        ) VALUES (?, ?, ?, ?, 'followup', CURRENT_TIMESTAMP)
    ");
    
    $message = "Follow-up requested for your session with supporter " . 
               ($booking['provider_name'] ?? 'your supporter') . 
               ". Please book a new appointment.";
    
    $success = $stmt->execute([
        $booking['user_email'],
        $supporter_email,
        $booking_id,
        $message
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => 'Follow-up notification created successfully'
        ]);
    } else {
        throw new Exception('Failed to create notification');
    }
    
} catch (Exception $e) {
    error_log('Error creating follow-up notification: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
} 