<?php
// Start session and include database connection
session_start();
require_once 'DBS.inc.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated and is a supporter
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['booking_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Get parameters
    $bookingId = intval($_POST['booking_id']);
    $action = $_POST['action'];
    $supporterEmail = $_SESSION['email'];

    // Validate action
    if (!in_array($action, ['accept', 'reject'])) {
        throw new Exception('Invalid action');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check if booking exists and belongs to this supporter
    $stmt = $pdo->prepare("
        SELECT * FROM bookings 
        WHERE booking_id = ? 
        AND provider_email = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$bookingId, $supporterEmail]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception('Invalid booking or not authorized to modify');
    }

    // Update booking status
    $newStatus = ($action === 'accept') ? 'confirmed' : 'rejected';
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = ?
        WHERE booking_id = ?
    ");
    $success = $stmt->execute([$newStatus, $bookingId]);

    if (!$success) {
        throw new Exception('Failed to update booking status');
    }

    // If accepting, create initial medical notes record
    if ($action === 'accept') {
        $stmt = $pdo->prepare("
            INSERT INTO medical_notes 
            (booking_id, symptoms, diagnosis, medication, further_steps, created_at, created_by) 
            VALUES (?, '', '', '', '', CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([$bookingId, $supporterEmail]);
    }

    // Commit transaction
    $pdo->commit();

    // Send notification to user
    $stmt = $pdo->prepare("
        SELECT u.email, u.name, b.booking_date, s.start_time, s.end_time 
        FROM bookings b
        JOIN users u ON b.user_email = u.email
        JOIN schedules s ON b.schedule_id = s.id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    $bookingDetails = $stmt->fetch();

    // You can implement email notification here if needed
    // sendBookingNotification($bookingDetails, $newStatus);

    echo json_encode([
        'success' => true,
        'message' => 'Booking ' . ($action === 'accept' ? 'accepted' : 'rejected') . ' successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in handle_booking_sup.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process booking: ' . $e->getMessage()
    ]);
}
?>
