<?php
require_once 'DBS.inc.php';

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() > 0) {
        echo "Notifications table already exists.<br>";
    } else {
        // Create the notifications table
        $sql = "CREATE TABLE notifications (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_email),
            INDEX (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "Notifications table created successfully!<br>";
    }
    
    echo "Database setup complete!";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 