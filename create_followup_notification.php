<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = $data['booking_id'] ?? 0;
$status = $data['status'] ?? '';

try {
    $pdo->beginTransaction();
    
    // Get booking details
    $stmt = $pdo->prepare("SELECT patient_email FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        // Create notification
        $message = $status === 'follow_up' ? 
            'Please book a follow-up session within one week' :
            'Please book your next session within one week';
            
        $stmt = $pdo->prepare("INSERT INTO notifications 
            (user_email, message, type, created_at, is_read) 
            VALUES (?, ?, 'follow_up', NOW(), 0)");
        $stmt->execute([$booking['patient_email'], $message]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 