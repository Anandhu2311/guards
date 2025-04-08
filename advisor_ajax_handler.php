<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is an advisor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'DBS.inc.php';

$advisor_email = $_SESSION['email'];

// Check if action is set
if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

switch ($_POST['action']) {
    case 'get_medical_notes':
        // Check if booking_id is set
        if (!isset($_POST['booking_id'])) {
            echo json_encode(['success' => false, 'message' => 'No booking ID provided']);
            exit();
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        // Verify this booking belongs to the logged-in advisor
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND provider_email = ?");
        $stmt->execute([$booking_id, $advisor_email]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
            exit();
        }
        
        // Get medical notes
        $stmt = $pdo->prepare("SELECT * FROM medical_notes WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $notes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return booking status along with notes (if any)
        $data = [
            'status' => $booking['status'],
            'booking_date' => $booking['booking_date'],
            'user_email' => $booking['user_email'],
            'notes' => $booking['notes']
        ];
        
        // Add medical notes data if exists
        if ($notes) {
            $data['symptoms'] = $notes['symptoms'];
            $data['diagnosis'] = $notes['diagnosis'];
            $data['medication'] = $notes['medication'];
            $data['further_procedure'] = $notes['further_procedure'];
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'save_medical_notes':
        // Check if booking_id is set
        if (!isset($_POST['booking_id'])) {
            echo json_encode(['success' => false, 'message' => 'No booking ID provided']);
            exit();
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        // Verify this booking belongs to the logged-in advisor
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND provider_email = ?");
        $stmt->execute([$booking_id, $advisor_email]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get form data
            $symptoms = $_POST['symptoms'] ?? '';
            $diagnosis = $_POST['diagnosis'] ?? '';
            $medication = $_POST['medication'] ?? '';
            $further_procedure = $_POST['further_procedure'] ?? '';
            $status = $_POST['status'] ?? 'pending';
            
            // Update booking status
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $stmt->execute([$status, $booking_id]);
            
            // Check if medical notes already exist
            $stmt = $pdo->prepare("SELECT id FROM medical_notes WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing medical notes
                $stmt = $pdo->prepare("
                    UPDATE medical_notes 
                    SET symptoms = ?, diagnosis = ?, medication = ?, further_procedure = ?, updated_at = NOW()
                    WHERE booking_id = ?
                ");
                $stmt->execute([$symptoms, $diagnosis, $medication, $further_procedure, $booking_id]);
            } else {
                // Insert new medical notes
                $stmt = $pdo->prepare("
                    INSERT INTO medical_notes (booking_id, symptoms, diagnosis, medication, further_procedure)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$booking_id, $symptoms, $diagnosis, $medication, $further_procedure]);
            }
            
            $pdo->commit();
            
            // Send notification to user about booking status update
            sendBookingUpdateNotification($pdo, $booking_id, $status);
            
            echo json_encode(['success' => true, 'message' => 'Medical notes saved successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'handle_booking':
        if (!isset($_POST['booking_id']) || !isset($_POST['booking_action'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit();
        }
        
        $booking_id = intval($_POST['booking_id']);
        $action = $_POST['booking_action'];
        
        // Verify this booking belongs to the logged-in advisor
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND provider_email = ?");
        $stmt->execute([$booking_id, $advisor_email]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or not authorized']);
            exit();
        }
        
        // Determine new status based on action
        $new_status = '';
        switch ($action) {
            case 'accept':
                $new_status = 'confirmed';
                break;
            case 'reject':
                $new_status = 'cancelled';
                break;
            case 'complete':
                $new_status = 'complete';
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
        
        try {
            // Update booking status
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $stmt->execute([$new_status, $booking_id]);
            
            // Send notification to user
            sendBookingUpdateNotification($pdo, $booking_id, $new_status);
            
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// Function to send notification to user about booking status update
function sendBookingUpdateNotification($pdo, $booking_id, $status) {
    try {
        // Get booking and user details
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as user_name 
            FROM bookings b
            JOIN users u ON b.user_email = u.email
            WHERE b.booking_id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return false;
        }
        
        // Format status for display
        $status_text = ucfirst(str_replace('_', ' ', $status));
        
        // Prepare notification message
        $message = "Your booking (ID: {$booking_id}) status has been updated to {$status_text}.";
        
        // Insert notification into database if you have a notifications table
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_email, message, related_id, type, created_at)
            VALUES (?, ?, ?, 'booking_update', NOW())
        ");
        $stmt->execute([$booking['user_email'], $message, $booking_id]);
        
        return true;
    } catch (Exception $e) {
        error_log('Error sending notification: ' . $e->getMessage());
        return false;
    }
}
?> 