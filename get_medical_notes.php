<?php
session_start();
require_once 'DBS.inc.php';

try {
    $stmt = $pdo->prepare("
        SELECT m.*, b.booking_id, b.service_type, b.booking_date,
               u.name as user_name, p.name as provider_name,
               DATE_FORMAT(b.booking_date, '%M %d, %Y') as formatted_date
        FROM medical_notes m
        JOIN bookings b ON m.booking_id = b.booking_id
        JOIN users u ON b.user_email = u.email
        JOIN users p ON b.provider_email = p.email
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($notes);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error loading medical notes']);
}
?>