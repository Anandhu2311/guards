<?php
session_start();
require 'DBS.inc.php';

// Check admin authentication
if (!isset($_SESSION['email']) || $_SESSION['email'] !== "admin@gmail.com") {
    echo "Unauthorized access";
    exit();
}

try {
    // Check if user_type column exists in schedules table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM schedules LIKE 'user_type'");
    $stmt->execute();
    $userTypeExists = $stmt->rowCount() > 0;

    if ($userTypeExists) {
        // Remove user_type column from schedules table
        $pdo->exec("ALTER TABLE schedules DROP COLUMN user_type");
        echo "Successfully removed user_type column from schedules table.<br>";
    } else {
        echo "The user_type column doesn't exist in the schedules table.<br>";
    }
    
    echo "Database schema updated successfully!";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 