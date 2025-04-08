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
$symptoms = $_POST['symptoms'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$medication = $_POST['medication'] ?? '';
$furtherSteps = $_POST['further_steps'] ?? '';
$status = $_POST['status'] ?? 'pending'; // Default to pending

// Validate booking ID
if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Validate status
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
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
                              symptoms = ?, 
                              diagnosis = ?, 
                              medication = ?, 
                              further_steps = ?,
                              status = ?, 
                              updated_at = NOW() 
                              WHERE booking_id = ?");
        $stmt->execute([$symptoms, $diagnosis, $medication, $furtherSteps, $status, $bookingId]);
    } else {
        // Insert new notes
        $stmt = $pdo->prepare("INSERT INTO medical_notes 
                             (booking_id, symptoms, diagnosis, medication, further_steps, status, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$bookingId, $symptoms, $diagnosis, $medication, $furtherSteps, $status]);
    }
    
    // Update booking status
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $bookingId]);
    
    // If status is follow_up, create a notification for the user
    if ($status === 'follow_up') {
        // Get user email from booking
        $userEmail = $booking['user_email'];
        
        // Create notification
        $stmt = $pdo->prepare("INSERT INTO notifications 
                             (user_email, message, type, created_at, is_read, booking_id, provider_email) 
                             VALUES (?, ?, 'follow_up', NOW(), 0, ?, ?)");
        $message = "Follow-up required for your appointment. Please book a new appointment.";
        $stmt->execute([$userEmail, $message, $bookingId, $_SESSION['email']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Medical notes saved successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 