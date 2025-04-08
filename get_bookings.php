<?php
session_start();
require_once 'DBS.inc.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Simple error handling function
function return_error($message) {
    echo json_encode([
        'error' => true,
        'message' => $message
    ]);
    exit;
}

// Verify admin access
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    return_error('Unauthorized access');
}

try {
    // Updated query to include time slot range (start time - end time)
    $query = "
        SELECT 
            b.booking_id as id,
            b.user_email,
            b.provider_email,
            b.service_type,
            b.booking_date,
            s.start_time,
            s.end_time,
            CONCAT(s.start_time, ' - ', s.end_time) as booking_time, 
            b.status,
            b.notes,
            b.updated_at,
            u.name as user_name,
            CASE 
                WHEN b.service_type = 'Academic Advising' THEN (SELECT adv_name FROM advisors WHERE adv_email = b.provider_email)
                WHEN b.service_type = 'Personal Counseling' THEN (SELECT coun_name FROM counselors WHERE coun_email = b.provider_email)
                WHEN b.service_type = 'Technical Support' THEN (SELECT sup_name FROM supporters WHERE sup_email = b.provider_email)
                ELSE p.name
            END as provider_name,
            CASE WHEN mn.id IS NOT NULL THEN 1 ELSE 0 END as has_medical_report
        FROM bookings b
        LEFT JOIN users u ON b.user_email = u.email
        LEFT JOIN users p ON b.provider_email = p.email
        LEFT JOIN schedules s ON b.schedule_id = s.id
        LEFT JOIN medical_notes mn ON b.booking_id = mn.booking_id
        ORDER BY b.booking_date DESC, s.start_time ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($bookings);
    
} catch (PDOException $e) {
    return_error('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    return_error('Server error: ' . $e->getMessage());
}
?>