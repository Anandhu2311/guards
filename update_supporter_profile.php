<?php
// Ensure session is started first thing
session_start();

// Check authentication and role
if (!isset($_SESSION['email']) || 
    (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 4) && 
    (!isset($_SESSION['role']) || strtolower($_SESSION['role']) != 'supporter')) {
    header("Location: signin.php");
    exit();
}

// Include database connection
require_once 'DBS.inc.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the supporter's email from session
    $supporter_email = $_SESSION['email'];
    
    // Get supporter data from database
    $stmt = $pdo->prepare("SELECT * FROM supporters WHERE sup_email = :email");
    $stmt->bindParam(':email', $supporter_email, PDO::PARAM_STR);
    $stmt->execute();
    $supporter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supporter) {
        // If supporter not found in database, redirect to login
        session_destroy();
        echo json_encode(['success' => false, 'message' => 'Supporter account not found']);
        exit();
    }

    // Process POST data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        
        // Validate data
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit();
        }
        
        if (empty($phone) || !preg_match('/^\d{10}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Valid 10-digit phone number is required']);
            exit();
        }
        
        // Update supporter profile
        $stmt = $pdo->prepare("
            UPDATE supporters
            SET sup_name = :name,
                phone_number = :phone,
                sup_location = :location,
                sup_specialization = :specialization
            WHERE sup_email = :email
        ");
        
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->bindParam(':location', $location, PDO::PARAM_STR);
        $stmt->bindParam(':specialization', $specialization, PDO::PARAM_STR);
        $stmt->bindParam(':email', $supporter_email, PDO::PARAM_STR);
        
        $success = $stmt->execute();
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        
    } else {
        // Not a POST request
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }

} catch (PDOException $e) {
    error_log('Database error in update_supporter_profile.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 