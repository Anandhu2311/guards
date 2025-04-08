<?php
session_start();
require 'DBS.inc.php';

// Verify user is logged in and is a counselor
if (!isset($_SESSION['email']) || $_SESSION['role_id'] != 3) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Get counselor email from session
$counselor_email = $_SESSION['email'];

// Collect form data
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$location = trim($_POST['location'] ?? '');
$expertise = trim($_POST['expertise'] ?? '');

// Validate data
if (empty($name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Name is required'
    ]);
    exit();
}

try {
    // Update counselor profile with correct column names
    $sql = "UPDATE counselors 
            SET coun_name = :name, 
                phone_number = :phone, 
                coun_location = :location,
                coun_specialization = :expertise
            WHERE coun_email = :email";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
    $stmt->bindParam(':location', $location, PDO::PARAM_STR);
    $stmt->bindParam(':expertise', $expertise, PDO::PARAM_STR);
    $stmt->bindParam(':email', $counselor_email, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        error_log('Profile update failed: ' . implode(", ", $stmt->errorInfo()));
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile'
        ]);
    }
} catch (PDOException $e) {
    error_log('PDO Exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('General Exception: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 