<?php
session_start();
require_once 'DBS.inc.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_id,
            b.user_email,
            b.provider_email,
            b.service_type,
            DATE_FORMAT(b.booking_date, '%M %d, %Y') as booking_date,
            b.status,
            u.name as user_name,
            p.name as provider_name,
            m.symptoms,
            m.diagnosis,
            m.medication,
            m.further_steps
        FROM bookings b
        LEFT JOIN medical_notes m ON b.booking_id = m.booking_id
        LEFT JOIN users u ON b.user_email = u.email
        LEFT JOIN users p ON b.provider_email = p.email
        ORDER BY b.booking_date DESC
    ");
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($reports);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Error fetching medical reports'
    ]);
}
?>