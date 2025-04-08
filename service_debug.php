<?php
// This file checks the connection between schedules, availability, and users
session_start();
require_once 'DBS.inc.php';

// Only allow admin access to this diagnostic tool
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    die("Access denied. Admin access only.");
}

echo "<h1>Service Provider Debug</h1>";

try {
    // Check raw schedules
    echo "<h2>All Service Providers by Role</h2>";
    
    $query = "SELECT role_id, COUNT(*) as count FROM users 
              WHERE role_id IN (2, 3, 4) 
              GROUP BY role_id
              ORDER BY role_id";
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Role ID</th><th>Role Name</th><th>Count</th></tr>";
    
    foreach ($results as $row) {
        $roleName = 'Unknown';
        switch ($row['role_id']) {
            case 2: $roleName = 'Advisor'; break;
            case 3: $roleName = 'Counselor'; break;
            case 4: $roleName = 'Supporter'; break;
        }
        echo "<tr>";
        echo "<td>{$row['role_id']}</td>";
        echo "<td>{$roleName}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check availability counts by role
    echo "<h2>Availability by Provider Role</h2>";
    
    $query = "SELECT u.role_id, 
                     COUNT(a.avail_id) as total_slots,
                     SUM(CASE WHEN a.is_available = 1 THEN 1 ELSE 0 END) as available_slots
              FROM users u
              LEFT JOIN availability a ON u.email = a.email
              WHERE u.role_id IN (2, 3, 4)
              GROUP BY u.role_id
              ORDER BY u.role_id";
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Role ID</th><th>Role Name</th><th>Total Slots</th><th>Available Slots</th></tr>";
    
    foreach ($results as $row) {
        $roleName = 'Unknown';
        switch ($row['role_id']) {
            case 2: $roleName = 'Advisor'; break;
            case 3: $roleName = 'Counselor'; break;
            case 4: $roleName = 'Supporter'; break;
        }
        echo "<tr>";
        echo "<td>{$row['role_id']}</td>";
        echo "<td>{$roleName}</td>";
        echo "<td>{$row['total_slots']}</td>";
        echo "<td>{$row['available_slots']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show individual providers with their availability
    echo "<h2>Individual Provider Availability</h2>";
    
    $query = "SELECT u.name, u.email, u.role_id,
                     CASE 
                         WHEN u.role_id = 2 THEN 'Advisor'
                         WHEN u.role_id = 3 THEN 'Counselor'
                         WHEN u.role_id = 4 THEN 'Supporter'
                         ELSE 'Unknown'
                     END as role_name,
                     COUNT(a.avail_id) as total_slots,
                     SUM(CASE WHEN a.is_available = 1 THEN 1 ELSE 0 END) as available_slots
              FROM users u
              LEFT JOIN availability a ON u.email = a.email
              WHERE u.role_id IN (2, 3, 4)
              GROUP BY u.email, u.name, u.role_id
              ORDER BY u.role_id, u.name";
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Name</th><th>Email</th><th>Role</th><th>Total Slots</th><th>Available Slots</th></tr>";
    
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>{$row['role_name']} (ID: {$row['role_id']})</td>";
        echo "<td>{$row['total_slots']}</td>";
        echo "<td>{$row['available_slots']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?> 