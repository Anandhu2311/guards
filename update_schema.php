<?php
// This script updates the database schema to better handle multiple user availability
session_start();
require_once 'DBS.inc.php';

// Only allow admin access
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@gmail.com') {
    die("Access denied. Admin access only.");
}

echo "<h1>Database Schema Update Utility</h1>";

try {
    // Check current tables
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Verify schedules table structure
    echo "<h2>Checking schedules table...</h2>";
    $tableInfo = $pdo->query("DESCRIBE schedules")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($tableInfo);
    echo "</pre>";
    
    // 2. Verify availability table structure
    echo "<h2>Checking availability table...</h2>";
    $availTableInfo = $pdo->query("DESCRIBE availability")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($availTableInfo);
    echo "</pre>";
    
    // 3. Check for primary key on availability table
    $indexInfo = $pdo->query("SHOW INDEX FROM availability")->fetchAll(PDO::FETCH_ASSOC);
    $hasPrimaryKey = false;
    
    foreach ($indexInfo as $index) {
        if ($index['Key_name'] === 'PRIMARY') {
            $hasPrimaryKey = true;
            break;
        }
    }
    
    if (!$hasPrimaryKey) {
        echo "<p>Adding primary key to availability table...</p>";
        
        // First check if there are any duplicate entries
        $duplicates = $pdo->query("
            SELECT email, id, COUNT(*) as count 
            FROM availability 
            GROUP BY email, id 
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($duplicates) > 0) {
            echo "<p>Warning: Found duplicate entries in availability table:</p>";
            echo "<pre>";
            print_r($duplicates);
            echo "</pre>";
            
            echo "<p>Removing duplicates...</p>";
            
            foreach ($duplicates as $dup) {
                // Keep one record and delete duplicates
                $pdo->exec("
                    DELETE FROM availability 
                    WHERE email = '{$dup['email']}' AND id = {$dup['id']}
                    LIMIT " . ($dup['count'] - 1) . "
                ");
            }
        }
        
        // Add primary key
        $pdo->exec("ALTER TABLE availability ADD PRIMARY KEY (email, id)");
        echo "<p>Primary key added successfully!</p>";
    } else {
        echo "<p>Availability table already has a primary key.</p>";
    }
    
    // 4. Check for appropriate indexes
    $hasEmailIndex = false;
    $hasScheduleIndex = false;
    
    foreach ($indexInfo as $index) {
        if ($index['Column_name'] === 'email' && $index['Key_name'] !== 'PRIMARY') {
            $hasEmailIndex = true;
        }
        if ($index['Column_name'] === 'id' && $index['Key_name'] !== 'PRIMARY') {
            $hasScheduleIndex = true;
        }
    }
    
    if (!$hasEmailIndex) {
        echo "<p>Adding index on email column...</p>";
        $pdo->exec("CREATE INDEX idx_availability_email ON availability (email)");
    }
    
    if (!$hasScheduleIndex) {
        echo "<p>Adding index on id column...</p>";
        $pdo->exec("CREATE INDEX idx_availability_id ON availability (id)");
    }
    
    // 5. Add a unique schedule name field if it doesn't exist
    $hasNameColumn = false;
    foreach ($tableInfo as $column) {
        if ($column['Field'] === 'name') {
            $hasNameColumn = true;
            break;
        }
    }
    
    if (!$hasNameColumn) {
        echo "<p>Adding name column to schedules table...</p>";
        $pdo->exec("ALTER TABLE schedules ADD COLUMN name VARCHAR(255) DEFAULT NULL AFTER id");
        
        // Generate names for existing schedules
        $pdo->exec("
            UPDATE schedules 
            SET name = CONCAT(day, ' ', start_time, '-', end_time)
            WHERE name IS NULL
        ");
        
        echo "<p>Names generated for existing schedules.</p>";
    }
    
    // 6. Check for foreign key constraints
    $foreignKeys = $pdo->query("
        SELECT * FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME = 'availability'
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($foreignKeys) === 0) {
        echo "<p>No foreign key constraints found. Checking if they can be added...</p>";
        
        // Check for orphaned records first
        $orphanedRecords = $pdo->query("
            SELECT a.email, a.id 
            FROM availability a
            LEFT JOIN schedules s ON a.id = s.id
            WHERE s.id IS NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($orphanedRecords) > 0) {
            echo "<p>Warning: Found orphaned records in availability table. These need to be fixed first:</p>";
            echo "<pre>";
            print_r($orphanedRecords);
            echo "</pre>";
            
            if (isset($_GET['fix_orphans']) && $_GET['fix_orphans'] === 'yes') {
                echo "<p>Removing orphaned records...</p>";
                $pdo->exec("
                    DELETE a FROM availability a
                    LEFT JOIN schedules s ON a.id = s.id
                    WHERE s.id IS NULL
                ");
                echo "<p>Orphaned records removed.</p>";
            } else {
                echo "<p><a href='update_schema.php?fix_orphans=yes'>Click here to remove orphaned records</a></p>";
                echo "<p>(This is needed before adding foreign key constraints)</p>";
                exit;
            }
        }
        
        // Add foreign key constraints if no orphans or they've been fixed
        if (count($orphanedRecords) === 0 || (isset($_GET['fix_orphans']) && $_GET['fix_orphans'] === 'yes')) {
            try {
                $pdo->exec("
                    ALTER TABLE availability
                    ADD CONSTRAINT fk_availability_schedule
                    FOREIGN KEY (id) REFERENCES schedules(id)
                    ON DELETE CASCADE
                ");
                echo "<p>Foreign key constraint added for schedules reference.</p>";
            } catch (PDOException $e) {
                echo "<p>Error adding foreign key: " . $e->getMessage() . "</p>";
            }
            
            try {
                $pdo->exec("
                    ALTER TABLE availability
                    ADD CONSTRAINT fk_availability_user
                    FOREIGN KEY (email) REFERENCES users(email)
                    ON DELETE CASCADE
                ");
                echo "<p>Foreign key constraint added for users reference.</p>";
            } catch (PDOException $e) {
                echo "<p>Error adding foreign key: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p>Foreign key constraints already exist.</p>";
    }
    
    // 7. Status summary
    echo "<h2>Database Schema Status</h2>";
    echo "<p>✅ The database schema is now optimized for handling multiple users per time slot.</p>";
    
    // Show sample of availability data
    echo "<h2>Sample Availability Data</h2>";
    $sample = $pdo->query("
        SELECT a.email, u.name as user_name, u.role_id, 
               s.day, s.start_time, s.end_time, a.is_available,
               CASE 
                   WHEN u.role_id = 2 THEN 'Advisor'
                   WHEN u.role_id = 3 THEN 'Counselor' 
                   WHEN u.role_id = 4 THEN 'Supporter'
                   ELSE 'Unknown'
               END as role_name
        FROM availability a
        JOIN users u ON a.email = u.email
        JOIN schedules s ON a.id = s.id
        WHERE a.is_available = 1
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User</th><th>Role</th><th>Day</th><th>Time</th></tr>";
    
    foreach ($sample as $row) {
        echo "<tr>";
        echo "<td>{$row['user_name']} ({$row['email']})</td>";
        echo "<td>{$row['role_name']} (ID: {$row['role_id']})</td>";
        echo "<td>{$row['day']}</td>";
        echo "<td>{$row['start_time']} - {$row['end_time']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>