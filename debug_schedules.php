<?php
session_start();
require_once 'DBS.inc.php';

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the content type to JSON
header('Content-Type: application/json');

try {
    // Test database connection
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Database connection failed");
    }
    
    // Log query before execution
    $query = "SELECT id, day, start_time, end_time, is_active FROM schedules";
    error_log("Executing query: " . $query);
    
    // Get all schedules with simple query
    $stmt = $pdo->query($query);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log number of rows
    error_log("Found " . count($schedules) . " schedule rows");
    
    // Return direct, simple response
    echo json_encode([
        'success' => true,
        'count' => count($schedules),
        'schedules' => $schedules,
        'debug' => true,
        'time' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    error_log("Error in debug_schedules.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
