<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a supporter
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'DBS.inc.php';

// Get booking ID from query string
$bookingId = $_GET['booking_id'] ?? 0;

// Validate booking ID
if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

try {
    // Check if this booking belongs to the logged-in supporter
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND provider_email = ?");
    $stmt->execute([$bookingId, $_SESSION['email']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
        exit();
    }
    
    // Get medical notes
    $stmt = $pdo->prepare("SELECT * FROM medical_notes WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $notes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return data
    echo json_encode([
        'success' => true,
        'notes' => $notes ?: null,
        'booking' => [
            'status' => $booking['status'],
            'notes' => $booking['notes'] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
