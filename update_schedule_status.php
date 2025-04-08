<?php
session_start();
require 'DBS.inc.php'; // Database connection file

// Check if admin is logged in
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    header("Location: signin.php");
    exit();
}

// Get parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? intval($_GET['status']) : 0;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin.php';

if ($id > 0) {
    // Use the existing function to toggle status
    $sql = "UPDATE schedules SET is_active = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $id);
    $result = $stmt->execute();
    
    // Set a success message
    if ($result) {
        $_SESSION['status_message'] = "Schedule status updated successfully";
    } else {
        $_SESSION['status_message'] = "Error updating schedule status";
    }
}

// Redirect back to admin page
header("Location: $redirect");
exit();
?>
