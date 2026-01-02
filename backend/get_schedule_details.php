<?php
/**
 * Get Schedule Details API
 * Returns detailed information about a specific schedule
 */

header('Content-Type: application/json');

require_once '../includes/db.php';

// Check if schedule_id is provided
if (!isset($_GET['schedule_id']) || !is_numeric($_GET['schedule_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing schedule ID'
    ]);
    exit;
}

$schedule_id = intval($_GET['schedule_id']);

// Query to get schedule details with area, truck, and staff info
$query = "SELECT s.schedule_id, s.collection_date, s.collection_type, s.status, s.update_time,
                 ca.taman_name as area_name, ca.area_id,
                 t.truck_number, t.truck_id,
                 GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as staff_names,
                 GROUP_CONCAT(DISTINCT u.user_id SEPARATOR ', ') as staff_ids
          FROM schedule s
          LEFT JOIN collection_area ca ON s.area_id = ca.area_id
          LEFT JOIN truck t ON s.truck_id = t.truck_id
          LEFT JOIN truck_staff ts ON t.truck_id = ts.truck_id AND ts.status = 'active'
          LEFT JOIN user u ON ts.user_id = u.user_id
          WHERE s.schedule_id = ?
          GROUP BY s.schedule_id";

$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Schedule not found'
    ]);
    exit;
}

$schedule = $result->fetch_assoc();

// Format the date for display
$schedule['collection_date'] = date('d M Y (l)', strtotime($schedule['collection_date']));

// Get lane collection status if available
$laneQuery = "SELECT cl.lane_name, lcs.status as lane_status
              FROM lane_collection_status lcs
              JOIN collection_lane cl ON lcs.lane_id = cl.lane_id
              WHERE lcs.schedule_id = ?
              ORDER BY cl.lane_name";

$laneStmt = $conn->prepare($laneQuery);
if ($laneStmt) {
    $laneStmt->bind_param("i", $schedule_id);
    $laneStmt->execute();
    $laneResult = $laneStmt->get_result();
    
    $lanes = [];
    while ($lane = $laneResult->fetch_assoc()) {
        $lanes[] = $lane;
    }
    $schedule['lanes'] = $lanes;
}

echo json_encode([
    'success' => true,
    'data' => $schedule
]);

$stmt->close();
$conn->close();
?>
