<?php
session_start();
require_once 'DBS.inc.php';

// Set the content type to JSON
header('Content-Type: application/json');

try {
    // Build query that correctly handles the booking_date timestamp
    $sql = "SELECT 
        b.booking_id as id,
        b.user_email,
        b.provider_email,
        b.schedule_id,
        b.service_type,
        DATE_FORMAT(b.booking_date, '%M %d, %Y') as booking_date,
        DATE_FORMAT(b.booking_date, '%H:%i') as booking_time,
        b.status,
        b.notes,
        b.updated_at as created_at,
        u.name as user_name,
        p.name as provider_name
    FROM bookings b
    LEFT JOIN users u ON b.user_email = u.email
    LEFT JOIN users p ON b.provider_email = p.email
    ORDER BY b.booking_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($bookings);
} catch (PDOException $e) {
    error_log("Database error in fix_bookings.php: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in fix_bookings.php: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>
