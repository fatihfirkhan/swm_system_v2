<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in as staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['error' => 'Unauthorized', 'schedules' => []]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get the truck assigned to this staff member
$truckQuery = "SELECT ts.truck_id FROM truck_staff ts WHERE ts.staff_id = ? AND ts.is_active = 1";
$truckStmt = $conn->prepare($truckQuery);
$truckStmt->bind_param("i", $userId);
$truckStmt->execute();
$truckResult = $truckStmt->get_result();

if ($truckResult->num_rows === 0) {
    echo json_encode(['schedules' => []]);
    exit;
}

$truckRow = $truckResult->fetch_assoc();
$truckId = $truckRow['truck_id'];

// Fetch all future scheduled dates for this truck (from today onwards) with collection type
$today = date('Y-m-d');
$query = "SELECT collection_date, collection_type FROM schedule WHERE truck_id = ? AND collection_date >= ? ORDER BY collection_date ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $truckId, $today);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[$row['collection_date']] = $row['collection_type'];
}

echo json_encode(['schedules' => $schedules]);
?>