<?php
// Script to mark all 'Pending' lane_status as 'Missed' if more than 24 hours past their schedule date
require_once '../includes/db.php';

// Get all schedules where collection_date < today
$today = date('Y-m-d');
$schedules = $conn->query("SELECT schedule_id, collection_date FROM schedule WHERE collection_date < '$today'");

while ($schedule = $schedules->fetch_assoc()) {
    $schedule_id = $schedule['schedule_id'];
    // Update all lanes with 'Pending' status to 'Missed'
    $update = $conn->prepare("UPDATE lane_status SET status = 'Missed' WHERE schedule_id = ? AND status = 'Pending'");
    $update->bind_param('s', $schedule_id);
    $update->execute();
    $update->close();
}

$conn->close();
?>
