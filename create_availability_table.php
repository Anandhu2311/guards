<?php
require 'DBS.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // SQL to create table
    $sql = "CREATE TABLE IF NOT EXISTS availability (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_email VARCHAR(100) NOT NULL,
        schedule_id INT(11) NOT NULL,
        is_available TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_schedule (user_email, schedule_id)
    )";

    // Execute query
    $pdo->exec($sql);
    echo "Table 'availability' created successfully or already exists";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 