<?php
session_start();
require_once 'DBS.inc.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['email']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit();
}

try {
    $booking_id = intval($_GET['booking_id']);
    
    $stmt = $pdo->prepare("
        SELECT symptoms, diagnosis, medication, further_steps, status 
        FROM medical_notes 
        WHERE booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $notes = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($notes) {
        echo json_encode([
            'success' => true,
            'notes' => $notes
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'notes' => [
                'symptoms' => '',
                'diagnosis' => '',
                'medication' => '',
                'further_steps' => '',
                'status' => 'pending'
            ]
        ]);
    }

} catch (Exception $e) {
    error_log('Error in get_medical_notes_adv.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving medical notes'
    ]);
}
?>
