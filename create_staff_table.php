<?php
require 'DBS.inc.php'; // Include your database connection file

try {
    // Create the staff_availability table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS staff_availability (
        id INT(11) NOT NULL AUTO_INCREMENT,
        schedule_id INT(11) NOT NULL,
        staff_id INT(11) NOT NULL,
        user_type VARCHAR(50) DEFAULT NULL,
        is_available TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY schedule_id (schedule_id),
        KEY staff_id (staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'staff_availability' created successfully!";
} catch(PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 