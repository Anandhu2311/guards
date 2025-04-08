<?php
session_start();
require 'DBS.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function log_debug($message) {
    file_put_contents('bookings_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Check if user is logged in and is an advisor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 2) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$advisorEmail = $_SESSION['email'];

try {
    // First check if we have the advisor column in the table
    $columnCheckStmt = $pdo->prepare("DESCRIBE bookings");
    $columnCheckStmt->execute();
    $columns = $columnCheckStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log('Bookings table columns: ' . implode(', ', $columns));
    
    // We need to add an advisor_email column to the bookings table
    if (!in_array('advisor_email', $columns)) {
        error_log('Adding advisor_email column to bookings table');
        $alterStmt = $pdo->prepare("ALTER TABLE bookings ADD COLUMN advisor_email VARCHAR(255) NULL");
        $alterStmt->execute();
        echo json_encode(['error' => 'System is being updated. Please refresh the page in a few moments.']);
        exit();
    }
    
    // Now get all bookings for this advisor
    $stmt = $pdo->prepare("
        SELECT id as booking_id, user_email, booking_date, booking_time, status
        FROM bookings
        WHERE advisor_email = :advisor_email
        ORDER BY booking_date ASC, booking_time ASC
    ");
    
    $stmt->bindParam(':advisor_email', $advisorEmail, PDO::PARAM_STR);
    $stmt->execute();
    
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('Found ' . count($bookings) . ' bookings');
    
    // Return the bookings as JSON
    echo json_encode($bookings);
    
} catch (PDOException $e) {
    error_log('Database error in get_user_bookings.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 