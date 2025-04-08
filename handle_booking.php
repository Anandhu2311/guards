<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a counselor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'DBS.inc.php';

// Get POST data
$bookingId = $_POST['booking_id'] ?? 0;
$action = $_POST['action'] ?? '';

// Validate booking ID and action
if (!$bookingId || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit();
}

try {
    // Check if this booking is pending and available to be accepted/rejected
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND status = 'pending'");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or already processed']);
        exit();
    }
    
    // Update booking status based on action
    $newStatus = ($action === 'accept') ? 'confirmed' : 'rejected';
    
    // Simplified update query with only the columns we know exist
    $stmt = $pdo->prepare("UPDATE bookings SET 
                          status = ?, 
                          provider_email = ?
                          WHERE booking_id = ?");
    $stmt->execute([$newStatus, $_SESSION['email'], $bookingId]);
    
    echo json_encode(['success' => true, 'message' => 'Booking ' . $action . 'ed successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 