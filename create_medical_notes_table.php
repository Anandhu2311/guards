<?php
// Script to check and create the medical_notes table if it doesn't exist

// Include database connection
require_once 'DBS.inc.php';

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'medical_notes'");
    if ($stmt->rowCount() > 0) {
        echo "Medical notes table already exists.<br>";
    } else {
        // Create the medical_notes table
        $sql = "CREATE TABLE medical_notes (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT(11) NOT NULL,
            symptoms TEXT,
            diagnosis TEXT,
            medication TEXT,
            further_procedure TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "Medical notes table created successfully!<br>";
    }
    
    // Check if bookings table has a 'notes' column
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'notes'");
    if ($stmt->rowCount() == 0) {
        // Add notes column to bookings table
        $sql = "ALTER TABLE bookings ADD COLUMN notes TEXT AFTER status";
        $pdo->exec($sql);
        echo "Added notes column to bookings table.<br>";
    } else {
        echo "Notes column already exists in bookings table.<br>";
    }
    
    echo "Database setup complete!";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
