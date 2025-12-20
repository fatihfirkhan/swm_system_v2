<?php
require_once 'includes/db.php';

echo "<h2>Database: swm_system</h2>";

// Check schedules
$schedules = $conn->query("SELECT s.*, ca.taman_name 
                          FROM schedule s 
                          LEFT JOIN collection_area ca ON s.area_id = ca.area_id 
                          ORDER BY s.collection_date DESC");

echo "<h3>Schedules Table (" . $schedules->num_rows . " records)</h3>";
if ($schedules->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Area</th><th>Date</th><th>Type</th><th>Status</th><th>Updated</th></tr>";
    while ($row = $schedules->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['schedule_id']}</td>";
        echo "<td>{$row['taman_name']}</td>";
        echo "<td>{$row['collection_date']}</td>";
        echo "<td>{$row['collection_type']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['update_time']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No schedules found in database!</p>";
}

// Check staff assignments
echo "<br><h3>Staff Assignments</h3>";
$staff_assign = $conn->query("SELECT ss.*, u.name, s.collection_date 
                              FROM schedule_staff ss 
                              LEFT JOIN user u ON ss.user_id = u.user_id 
                              LEFT JOIN schedule s ON ss.schedule_id = s.schedule_id
                              ORDER BY s.collection_date DESC");

if ($staff_assign->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Schedule ID</th><th>Date</th><th>Staff Name</th></tr>";
    while ($row = $staff_assign->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['schedule_id']}</td>";
        echo "<td>{$row['collection_date']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No staff assignments found.</p>";
}

echo "<br><br><a href='public/adminschedule.php'>← Back to Schedule Management</a>";
?>
