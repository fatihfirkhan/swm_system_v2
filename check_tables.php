<?php
include 'includes/db.php';

// Get all tables in the database
$tables = $conn->query("SHOW TABLES");

echo "<h2>Tables in your database:</h2>";
while ($table = $tables->fetch_array()) {
    $tableName = $table[0];
    echo "<h3>Table: $tableName</h3>";
    
    // Get the structure of each table
    $columns = $conn->query("SHOW COLUMNS FROM $tableName");
    echo "<ul>";
    while ($column = $columns->fetch_assoc()) {
        echo "<li>" . $column['Field'] . " - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
}
?>