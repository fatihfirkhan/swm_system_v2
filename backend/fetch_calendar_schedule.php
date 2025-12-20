<?php
include '../includes/db.php';

if (!isset($_GET['area_id']) || !isset($_GET['month'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$area_id = $_GET['area_id'];
$month = $_GET['month']; // Format: YYYY-MM

// Get all schedules for that area in that month
$stmt = $conn->prepare("
    SELECT s.schedule_id, s.collection_date, s.collection_type, 
           s.truck_id, t.truck_number
    FROM schedule s
    LEFT JOIN truck t ON s.truck_id = t.truck_id
    WHERE s.area_id = ? AND DATE_FORMAT(s.collection_date, '%Y-%m') = ?
    ORDER BY s.collection_date
");
$stmt->bind_param("is", $area_id, $month);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];

while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

echo json_encode($schedules);
?>
