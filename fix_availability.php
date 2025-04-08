<?php
// This utility script ensures availability records exist for all staff and schedules
session_start();
require_once 'DBS.inc.php';

// Only allow admin access
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    die("Access denied. Admin access only.");
}

echo "<h1>Availability Data Repair Utility</h1>";

try {
    // Get all service providers
    $providersStmt = $pdo->query("
        SELECT email, name, role_id,
        CASE 
            WHEN role_id = 2 THEN 'Advisor'
            WHEN role_id = 3 THEN 'Counselor'
            WHEN role_id = 4 THEN 'Supporter'
            ELSE 'Unknown'
        END as role_name
        FROM users
        WHERE role_id IN (2, 3, 4)
    ");
    
    $providers = $providersStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Found " . count($providers) . " service providers.</p>";
    
    // Get all schedules
    $schedulesStmt = $pdo->query("SELECT id, day, start_time, end_time FROM schedules WHERE is_active = 1");
    $schedules = $schedulesStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Found " . count($schedules) . " active schedules.</p>";
    
    $fixed = 0;
    
    // Check each provider and schedule combination
    foreach ($providers as $provider) {
        foreach ($schedules as $schedule) {
            // Check if availability record exists
            $checkStmt = $pdo->prepare("
                SELECT count(*) FROM availability 
                WHERE email = :email AND id = :scheduleId
            ");
            $checkStmt->bindParam(':email', $provider['email']);
            $checkStmt->bindParam(':scheduleId', $schedule['id']);
            $checkStmt->execute();
            
            $exists = (int)$checkStmt->fetchColumn();
            
            if ($exists === 0) {
                // Create missing availability record (default to not available)
                $insertStmt = $pdo->prepare("
                    INSERT INTO availability (email, id, is_available)
                    VALUES (:email, :scheduleId, 0)
                ");
                $insertStmt->bindParam(':email', $provider['email']);
                $insertStmt->bindParam(':scheduleId', $schedule['id']);
                $insertStmt->execute();
                
                echo "<p>Created availability record for {$provider['name']} ({$provider['role_name']}) on {$schedule['day']} at {$schedule['start_time']}.</p>";
                $fixed++;
            }
        }
    }
    
    if ($fixed > 0) {
        echo "<h2>Created $fixed missing availability records.</h2>";
    } else {
        echo "<h2>All availability records are in place. No fixes needed.</h2>";
    }
    
    // Option to make all providers available for testing
    if (isset($_GET['set_available']) && $_GET['set_available'] === 'yes') {
        $updateStmt = $pdo->query("UPDATE availability SET is_available = 1");
        $updated = $updateStmt->rowCount();
        echo "<h2>Set $updated availability records to available for testing.</h2>";
    } else {
        echo "<p><a href='fix_availability.php?set_available=yes'>Click here to make all providers available for testing</a></p>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?> 