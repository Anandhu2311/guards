<?php
require 'DBS.inc.php'; // Include your database connection file

function listTables($pdo) {
    $tables = [];
    try {
        $result = $pdo->query('SHOW TABLES');
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    } catch (PDOException $e) {
        return ['Error: ' . $e->getMessage()];
    }
}

function describeTable($pdo, $table) {
    $columns = [];
    try {
        $result = $pdo->query("DESCRIBE $table");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row;
        }
        return $columns;
    } catch (PDOException $e) {
        return ['Error: ' . $e->getMessage()];
    }
}

// List all tables
echo "<h2>Database Tables</h2>";
$tables = listTables($pdo);
echo "<pre>";
print_r($tables);
echo "</pre>";

// Check if staff_availability exists and describe it
if (in_array('staff_availability', $tables)) {
    echo "<h2>staff_availability Table Structure</h2>";
    $columns = describeTable($pdo, 'staff_availability');
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
}

// Look at schedules table too
if (in_array('schedules', $tables)) {
    echo "<h2>schedules Table Structure</h2>";
    $columns = describeTable($pdo, 'schedules');
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
}
?> 