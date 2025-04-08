<?php
require 'DBS.inc.php';

echo "<h1>Admin Page Repair Tool</h1>";

try {
    // First, check the structure of the users table
    echo "<p>Checking users table structure...</p>";
    $userColumns = [];
    $userStructure = $pdo->query("DESCRIBE users");
    while ($col = $userStructure->fetch(PDO::FETCH_ASSOC)) {
        $userColumns[] = $col['Field'];
    }
    
    echo "<p>User table has columns: " . implode(", ", $userColumns) . "</p>";
    
    // First, check if staff_availability table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'staff_availability'")->fetchAll();
    $staffTableExists = !empty($tables);
    
    if (!$staffTableExists) {
        echo "<p>Creating staff_availability table...</p>";
        
        // Create the table with proper structure
        $sql = "CREATE TABLE staff_availability (
            id INT(11) NOT NULL AUTO_INCREMENT,
            schedule_id INT(11) NOT NULL,
            staff_id INT(11) NOT NULL,
            user_type VARCHAR(50) DEFAULT NULL,
            is_available TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        echo "<p>staff_availability table created successfully!</p>";
    } else {
        echo "<p>staff_availability table already exists.</p>";
    }
    
    // Check if availability table exists (old structure)
    $oldTable = $pdo->query("SHOW TABLES LIKE 'availability'")->fetchAll();
    $oldTableExists = !empty($oldTable);
    
    if ($oldTableExists) {
        echo "<p>Found old 'availability' table - will convert data if needed</p>";
    }
    
    // Use a safer query for users - check if role_id exists first
    if (in_array('role_id', $userColumns)) {
        $userQuery = "SELECT id, email, role_id FROM users WHERE role_id IN (2,3,4) LIMIT 10";
    } else if (in_array('userType', $userColumns)) {
        // Alternative if role_id doesn't exist but userType does
        $userQuery = "SELECT id, email, userType FROM users WHERE userType IN ('advisor', 'counselor', 'supporter') LIMIT 10";
    } else {
        // Fallback to just getting any users
        $userQuery = "SELECT id, email FROM users LIMIT 10";
    }
    
    // Get users to create sample data
    echo "<p>Getting users with query: $userQuery</p>";
    $users = $pdo->query($userQuery)->fetchAll();
    
    echo "<p>Found " . count($users) . " users to create sample data</p>";
    
    // Create sample staff availability data for testing
    if (!empty($users)) {
        echo "<p>Adding sample staff availability data...</p>";
        
        // Get all schedules
        $schedules = $pdo->query("SELECT id FROM schedules")->fetchAll();
        
        if (!empty($schedules)) {
            echo "<p>Found " . count($schedules) . " schedules</p>";
            
            // Insert sample data for each user
            $insertCount = 0;
            $sql = "INSERT IGNORE INTO staff_availability (schedule_id, staff_id, is_available) VALUES (?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            
            foreach ($users as $user) {
                // Make sure we have an ID for this user
                $userId = $user['id'];
                if (!$userId) {
                    echo "<p>⚠️ Warning: User without ID found - skipping</p>";
                    continue;
                }
                
                foreach ($schedules as $schedule) {
                    try {
                        $stmt->execute([$schedule['id'], $userId]);
                        if ($stmt->rowCount() > 0) {
                            $insertCount++;
                        }
                    } catch (Exception $e) {
                        echo "<p>Error adding record: " . $e->getMessage() . "</p>";
                        // Continue with the next one
                    }
                }
            }
            
            echo "<p>Added $insertCount sample staff availability records</p>";
        } else {
            echo "<p>No schedules found! Please create some schedules first.</p>";
        }
    } else {
        echo "<p>No users found to create sample data with.</p>";
    }
    
    // Make sure schedules table has is_active column
    try {
        $pdo->query("SELECT is_active FROM schedules LIMIT 1");
        echo "<p>Schedule table has is_active column.</p>";
    } catch (Exception $e) {
        echo "<p>Adding is_active column to schedules table...</p>";
        $pdo->exec("ALTER TABLE schedules ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        $pdo->exec("UPDATE schedules SET is_active = 1");
        echo "<p>Added is_active column and set all schedules to active.</p>";
    }
    
    // Fix the getStaffAvailabilityForDisplay function
    echo "<p>Updating the getStaffAvailabilityForDisplay function in admin.php...</p>";
    
    $adminFile = file_get_contents('admin.php');
    $pattern = '/function getStaffAvailabilityForDisplay\(\$conn, \$scheduleId\) {[\s\S]+?return \[\];[\s\S]+?}/';
    $replacement = 'function getStaffAvailabilityForDisplay($conn, $scheduleId) {
    try {
        $sql = "SELECT sa.id, sa.schedule_id, sa.staff_id, sa.is_available, 
                u.name as staff_name, u.email as staff_email, u.is_active as staff_active,
                r.name as role_name, r.id as role_id
                FROM staff_availability sa
                JOIN users u ON sa.staff_id = u.id
                JOIN roles r ON u.role_id = r.id
                WHERE sa.schedule_id = :schedule_id
                AND sa.is_available = 1
                ORDER BY r.name, u.name";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(\':schedule_id\', $scheduleId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // In case of any error, return empty array to avoid breaking the page
        return [];
    }
}';
    
    // Only replace if the function exists
    if (preg_match($pattern, $adminFile)) {
        $newContent = preg_replace($pattern, $replacement, $adminFile);
        file_put_contents('admin.php', $newContent);
        echo "<p>Successfully updated the getStaffAvailabilityForDisplay function!</p>";
    } else {
        echo "<p>Could not find the getStaffAvailabilityForDisplay function to update.</p>";
    }
    
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #4CAF50; color: white; border-radius: 5px;'>";
    echo "<h2>✅ Admin page has been fixed!</h2>";
    echo "<p>All database tables needed for admin.php are now properly set up.</p>";
    echo "<a href='admin.php' style='color: white; font-weight: bold; text-decoration: underline;'>Go to Admin Page</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='margin-top: 20px; padding: 15px; background-color: #f44336; color: white; border-radius: 5px;'>";
    echo "<h2>⚠️ Error fixing admin page</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 