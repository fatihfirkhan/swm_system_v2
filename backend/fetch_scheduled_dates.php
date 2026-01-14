<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['area_id']) || empty($_GET['area_id'])) {
    echo json_encode(['error' => 'Area ID required', 'dates' => []]);
    exit;
}

$areaId = intval($_GET['area_id']);

// Fetch all future scheduled dates for this area (from today onwards) with collection type
$today = date('Y-m-d');
$query = "SELECT collection_date, collection_type FROM schedule WHERE area_id = ? AND collection_date >= ? ORDER BY collection_date ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $areaId, $today);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[$row['collection_date']] = $row['collection_type'];
}

echo json_encode(['schedules' => $schedules]);
?>