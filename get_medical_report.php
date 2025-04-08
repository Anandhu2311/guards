<?php
session_start();
require_once 'DBS.inc.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Verify admin access
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo json_encode([
        'error' => true,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Validate booking_id
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

$booking_id = intval($_GET['booking_id']);

try {
    $stmt = $pdo->prepare("
        SELECT 
            mn.id,
            mn.booking_id,
            mn.symptoms,
            mn.diagnosis,
            mn.medication,
            mn.further_steps,
            mn.created_at,
            mn.updated_at,
            mn.status as note_status,
            b.booking_date,
            s.start_time,
            s.end_time,
            CONCAT(s.start_time, ' - ', s.end_time) as booking_time,
            b.service_type,
            b.status as booking_status,
            u.name as user_name,
            u.email as user_email,
            CASE 
                WHEN b.service_type = 'Academic Advising' THEN (SELECT adv_name FROM advisors WHERE adv_email = b.provider_email)
                WHEN b.service_type = 'Personal Counseling' THEN (SELECT coun_name FROM counselors WHERE coun_email = b.provider_email)
                WHEN b.service_type = 'Technical Support' THEN (SELECT sup_name FROM supporters WHERE sup_email = b.provider_email)
                ELSE p.name
            END as provider_name
        FROM medical_notes mn
        JOIN bookings b ON mn.booking_id = b.booking_id
        JOIN users u ON b.user_email = u.email
        LEFT JOIN users p ON b.provider_email = p.email
        LEFT JOIN schedules s ON b.schedule_id = s.id
        WHERE mn.booking_id = :booking_id
        LIMIT 1
    ");
    
    $stmt->bindParam(':booking_id', $booking_id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        // Format dates for display
        if (isset($report['created_at'])) {
            $created = new DateTime($report['created_at']);
            $report['submission_date'] = $created->format('Y-m-d H:i');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $report
        ]);
    } else {
        echo json_encode([
            'error' => true,
            'message' => 'No medical report found for this booking'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in get_medical_report.php: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Error fetching medical report: ' . $e->getMessage()
    ]);
}
?> 