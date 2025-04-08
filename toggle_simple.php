<?php
session_start();
require_once 'DBS.inc.php';

// Disable all error reporting/output
error_reporting(0);
ini_set('display_errors', 0);

// Set content type to plain text, not JSON
header('Content-Type: text/plain');

// Get parameters
$scheduleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? intval($_GET['status']) : 0;

try {
    // Direct SQL update - no JSON involved
    $sql = "UPDATE schedules SET is_active = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $scheduleId]);
    
    // Return simple success text
    echo "SUCCESS";
} catch (Exception $e) {
    // Return simple error text
    echo "ERROR";
}
exit;
?>
