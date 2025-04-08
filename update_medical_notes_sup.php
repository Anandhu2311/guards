<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // Validate required fields
    $requiredFields = ['booking_id', 'symptoms', 'diagnosis', 'medication', 'further_steps', 'status'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $booking_id = intval($_POST['booking_id']);
    $symptoms = $_POST['symptoms'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $medication = $_POST['medication'] ?? '';
    $further_steps = $_POST['further_steps'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    // First, check if a record exists
    $checkStmt = $pdo->prepare("SELECT id FROM medical_notes WHERE booking_id = ?");
    $checkStmt->execute([$booking_id]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing record
        $sql = "UPDATE medical_notes 
                SET symptoms = ?, 
                    diagnosis = ?, 
                    medication = ?, 
                    further_steps = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE booking_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $symptoms,
            $diagnosis,
            $medication,
            $further_steps,
            $status,
            $booking_id
        ]);
    } else {
        // Insert new record
        $sql = "INSERT INTO medical_notes 
                (booking_id, symptoms, diagnosis, medication, further_steps, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $booking_id,
            $symptoms,
            $diagnosis,
            $medication,
            $further_steps,
            $status
        ]);
    }

    // Also update the booking status
    $updateBookingStmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $updateBookingStmt->execute([$status, $booking_id]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Medical notes saved successfully']);
    } else {
        throw new Exception('Failed to save medical notes');
    }

} catch (Exception $e) {
    error_log('Error in update_medical_notes_sup.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while saving medical notes: ' . $e->getMessage()
    ]);
}
?>
