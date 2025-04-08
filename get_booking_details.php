<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Authentication check - only allow admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    http_response_code(403); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

// Verify booking ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid booking ID']);
    exit();
}

$bookingId = intval($_GET['id']);

try {
    // Updated SQL query to show time slot range (start time - end time)
    $sql = "SELECT b.booking_id as id, 
                  b.service_type,
                  b.booking_date,
                  s.start_time,
                  s.end_time,
                  CONCAT(s.start_time, ' - ', s.end_time) as booking_time,
                  b.notes,
                  b.status,
                  b.user_email,
                  b.provider_email,
                  b.updated_at,
                  u.name as user_name,
                  CASE 
                      WHEN b.service_type = 'Academic Advising' THEN (SELECT adv_name FROM advisors WHERE adv_email = b.provider_email)
                      WHEN b.service_type = 'Personal Counseling' THEN (SELECT coun_name FROM counselors WHERE coun_email = b.provider_email)
                      WHEN b.service_type = 'Technical Support' THEN (SELECT sup_name FROM supporters WHERE sup_email = b.provider_email)
                      ELSE p.name
                  END as provider_name
            FROM bookings b
            LEFT JOIN users u ON b.user_email = u.email
            LEFT JOIN users p ON b.provider_email = p.email
            LEFT JOIN schedules s ON b.schedule_id = s.id
            WHERE b.booking_id = :bookingId";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':bookingId', $bookingId, PDO::PARAM_INT);
    $stmt->execute();
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Output as JSON
    echo json_encode($booking);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?> 