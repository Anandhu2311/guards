<?php
// Verifies and updates the database schema if needed
session_start();
require_once 'DBS.inc.php';

// Security check
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    die("Access denied. Admin access only.");
}

echo "<h1>Database Schema Verification</h1>";

try {
    // Check availability table structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS availability (
            avail_id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            id INT NOT NULL,
            is_available TINYINT(1) DEFAULT 0,
            INDEX (email),
            INDEX (id),
            UNIQUE KEY unique_availability (email, id),
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE,
            FOREIGN KEY (id) REFERENCES schedules(id) ON DELETE CASCADE
        )
    ");
    
    echo "<p>✅ Availability table structure verified!</p>";
    
    // Display sample data
    $sampleData = $pdo->query("
        SELECT a.email, a.id, a.is_available, u.name, u.role_id, 
               s.day, s.start_time, s.end_time
        FROM availability a
        JOIN users u ON a.email = u.email
        JOIN schedules s ON a.id = s.id
        WHERE a.is_available = 1
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Sample Available Slots:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Email</th><th>Name</th><th>Role ID</th><th>Day</th><th>Time</th></tr>";
    
    foreach ($sampleData as $row) {
        echo "<tr>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['role_id']}</td>";
        echo "<td>{$row['day']}</td>";
        echo "<td>{$row['start_time']} - {$row['end_time']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 