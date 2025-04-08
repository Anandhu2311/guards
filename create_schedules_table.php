<?php
require 'DBS.inc.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // SQL to create table
    $sql = "CREATE TABLE IF NOT EXISTS schedules (
        id INT(11) NOT NULL AUTO_INCREMENT,
        day VARCHAR(10) NOT NULL,
        start_time VARCHAR(5) NOT NULL,
        end_time VARCHAR(5) NOT NULL,
        user_type VARCHAR(20) NOT NULL,
        PRIMARY KEY (id)
    )";

    // Execute query
    $pdo->exec($sql);
    echo "Table 'schedules' created successfully or already exists";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 