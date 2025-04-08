<?php
require_once 'DBS.inc.php';

// Enable error logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session to access email
session_start();

// Connect to database
try {
    $host = 'localhost';
    $dbname = 'guardsphere';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user email from request
    $email = $_POST['email'] ?? $_SESSION['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode(['error' => 'No email provided']);
        exit;
    }
    
    // Check database structure
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $result = [
        'email' => $email,
        'tables' => $tables,
        'bookings_table_exists' => in_array('bookings', $tables),
        'bookings' => []
    ];
    
    // If bookings table exists, get its structure
    if (in_array('bookings', $tables)) {
        $columns = $pdo->query("DESCRIBE bookings")->fetchAll(PDO::FETCH_COLUMN);
        $result['bookings_columns'] = $columns;
        
        // Check if user_email column exists
        if (in_array('user_email', $columns)) {
            // Query bookings as client
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $clientBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['client_bookings'] = $clientBookings;
        }
        
        // Check if provider_email column exists
        if (in_array('provider_email', $columns)) {
            // Query bookings as provider
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE provider_email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $providerBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['provider_bookings'] = $providerBookings;
        }
        
        // Check if advisor_email column exists
        if (in_array('advisor_email', $columns)) {
            // Query bookings as advisor
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE advisor_email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $advisorBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['advisor_bookings'] = $advisorBookings;
        }
        
        // Try a direct query to get all bookings for this user in any role
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE 
                              (user_email = :email) OR 
                              (provider_email = :email AND provider_email IS NOT NULL) OR
                              (advisor_email = :email AND advisor_email IS NOT NULL)");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['all_bookings'] = $allBookings;
    }
    
    // Return results
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 