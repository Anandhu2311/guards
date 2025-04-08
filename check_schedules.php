<?php
// This is a utility script to check your schedules and availability
// Place this in your project root and access via browser to debug

session_start();
require_once 'DBS.inc.php';

// Only allow admin access to this diagnostic tool
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    die("Access denied. Admin access only.");
}

echo "<h1>Schedule and Availability Diagnostics</h1>";

try {
    // Check schedules table
    $schedulesStmt = $pdo->query("SELECT * FROM schedules ORDER BY day, start_time");
    $schedules = $schedulesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>All Schedules (" . count($schedules) . ")</h2>";
    if (count($schedules) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Day</th><th>Start Time</th><th>End Time</th><th>Is Active</th></tr>";
        foreach ($schedules as $schedule) {
            echo "<tr>";
            echo "<td>{$schedule['id']}</td>";
            echo "<td>{$schedule['day']}</td>";
            echo "<td>{$schedule['start_time']}</td>";
            echo "<td>{$schedule['end_time']}</td>";
            echo "<td>{$schedule['is_active']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No schedules found in the database.</p>";
    }
    
    // Check availability table
    $availStmt = $pdo->query("
        SELECT a.*, u.name, u.email, u.role_id,
        CASE 
            WHEN u.role_id = 2 THEN 'Advisor'
            WHEN u.role_id = 3 THEN 'Counselor'
            WHEN u.role_id = 4 THEN 'Supporter'
            ELSE 'Unknown'
        END as role_name
        FROM availability a
        INNER JOIN users u ON a.email = u.email
        ORDER BY a.id
    ");
    $availability = $availStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Availability Settings (" . count($availability) . ")</h2>";
    if (count($availability) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Avail ID</th><th>Schedule ID</th><th>Provider</th><th>Role</th><th>Available</th></tr>";
        foreach ($availability as $avail) {
            echo "<tr>";
            echo "<td>{$avail['avail_id']}</td>";
            echo "<td>{$avail['id']}</td>";
            echo "<td>{$avail['name']} ({$avail['email']})</td>";
            echo "<td>{$avail['role_name']} (ID: {$avail['role_id']})</td>";
            echo "<td>" . ($avail['is_available'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No availability settings found. Staff members need to set their availability.</p>";
    }
    
    // Check joined data (what should be displayed in the UI)
    $joinedStmt = $pdo->query("
        SELECT s.*, u.name as provider_name, u.email as provider_email, u.role_id,
        CASE 
            WHEN u.role_id = 2 THEN 'advising'
            WHEN u.role_id = 3 THEN 'counseling'
            WHEN u.role_id = 4 THEN 'support'
            ELSE 'unknown'
        END as provider_type
        FROM schedules s
        INNER JOIN availability a ON s.id = a.id
        INNER JOIN users u ON a.email = u.email
        WHERE a.is_available = 1
        AND s.is_active = 1
        ORDER BY s.day, s.start_time
    ");
    $joinedData = $joinedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Available Services for Display (" . count($joinedData) . ")</h2>";
    if (count($joinedData) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Day</th><th>Time</th><th>Provider</th><th>Role ID</th><th>Service Type</th></tr>";
        foreach ($joinedData as $data) {
            echo "<tr>";
            echo "<td>{$data['id']}</td>";
            echo "<td>{$data['day']}</td>";
            echo "<td>{$data['start_time']} - {$data['end_time']}</td>";
            echo "<td>{$data['provider_name']} ({$data['provider_email']})</td>";
            echo "<td>{$data['role_id']}</td>";
            echo "<td>{$data['provider_type']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No available services found to display. Please check that:</p>";
        echo "<ol>";
        echo "<li>There are schedules in the schedules table</li>";
        echo "<li>Staff members have set their availability</li>";
        echo "<li>The schedules are marked as active</li>";
        echo "</ol>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?> 