<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a supporter
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'DBS.inc.php';

// Define valid status values
$validStatuses = ['pending', 'canceled', 'confirmed', 'follow_up', 'completed', 'follow_up_completed'];

// Get POST data
$bookingId = $_POST['booking_id'] ?? 0;
$notes = $_POST['notes'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$recommendations = $_POST['recommendations'] ?? '';
$followUpRequired = isset($_POST['follow_up_required']) ? (int)$_POST['follow_up_required'] : 0;
$followUpDate = ($followUpRequired && isset($_POST['follow_up_date'])) ? $_POST['follow_up_date'] : null;

// Status will be determined based on follow-up selection
$status = $followUpRequired ? 'follow_up' : 'completed';

// Validate booking ID
if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if this booking belongs to the logged-in supporter
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND provider_email = ?");
    $stmt->execute([$bookingId, $_SESSION['email']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
        exit();
    }
    
    // Check if medical notes already exist for this booking
    $stmt = $pdo->prepare("SELECT * FROM medical_notes WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $existingNotes = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingNotes) {
        // Update existing notes
        $stmt = $pdo->prepare("UPDATE medical_notes SET 
                               notes = ?, 
                               diagnosis = ?, 
                               recommendations = ?, 
                               follow_up_required = ?,
                               follow_up_date = ?,
                               updated_at = NOW() 
                               WHERE booking_id = ?");
        $stmt->execute([$notes, $diagnosis, $recommendations, $followUpRequired, $followUpDate, $bookingId]);
    } else {
        // Insert new notes
        $stmt = $pdo->prepare("INSERT INTO medical_notes 
                              (booking_id, notes, diagnosis, recommendations, follow_up_required, follow_up_date, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$bookingId, $notes, $diagnosis, $recommendations, $followUpRequired, $followUpDate]);
    }
    
    // Update booking status
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $bookingId]);
    
    // If follow-up is required, create a notification
    if ($followUpRequired) {
        // Get patient email
        $patientEmail = $booking['user_email'];
        
        // Get supporter name
        $supporter_email = $_SESSION['email'];
        $stmt = $pdo->prepare("SELECT sup_name FROM supporters WHERE sup_email = ?");
        $stmt->execute([$supporter_email]);
        $supporter = $stmt->fetch(PDO::FETCH_ASSOC);
        $supporterName = $supporter['sup_name'] ?? 'Your supporter';
        
        // Create notification message
        $message = "Follow-up required for your appointment with $supporterName. Please book a new appointment.";
        
        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO notifications 
                              (user_email, message, type, created_at, is_read, booking_id, provider_email) 
                              VALUES (?, ?, 'follow_up', NOW(), 0, ?, ?)");
        $stmt->execute([$patientEmail, $message, $bookingId, $supporter_email]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Medical notes saved successfully',
        'status_updated' => true
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
