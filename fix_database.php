<?php
require 'DBS.inc.php';

// Start a transaction for safety
$pdo->beginTransaction();

try {
    // 1. Check if staff_availability table exists, if not create it
    $result = $pdo->query("SHOW TABLES LIKE 'staff_availability'");
    if ($result->rowCount() == 0) {
        $sql = "CREATE TABLE staff_availability (
            id INT PRIMARY KEY AUTO_INCREMENT,
            schedule_id INT NOT NULL,
            staff_id INT NOT NULL,
            user_type VARCHAR(50),
            is_available TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        echo "Created staff_availability table.<br>";
    }
    
    $pdo->commit();
    echo "<h2>Database updated successfully!</h2>";
    echo "<p>Your admin page should now work correctly. <a href='admin.php'>Go to admin page</a></p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 