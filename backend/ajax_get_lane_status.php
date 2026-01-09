<?php
/**
 * AJAX Get Lane Status
 * Returns lane collection status for a specific date and area
 * Used by resident_schedule.php for the Lane Status Modal
 */

session_start();
require_once '../includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in (allow resident, admin, or staff)
$allowedRoles = ['resident', 'admin', 'staff'];
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), $allowedRoles)) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Validate required parameters
if (!isset($_GET['date']) || !isset($_GET['area_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$date = $_GET['date'];
$areaId = intval($_GET['area_id']);

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Get area name
$areaQuery = $conn->prepare("SELECT taman_name FROM collection_area WHERE area_id = ?");
$areaQuery->bind_param('i', $areaId);
$areaQuery->execute();
$areaResult = $areaQuery->get_result()->fetch_assoc();

if (!$areaResult) {
    echo json_encode(['error' => 'Area not found']);
    exit;
}

$areaName = $areaResult['taman_name'];

// Check if there's a schedule for this date and area
$scheduleQuery = $conn->prepare("
    SELECT schedule_id, collection_type 
    FROM schedule 
    WHERE area_id = ? AND collection_date = ?
");
$scheduleQuery->bind_param('is', $areaId, $date);
$scheduleQuery->execute();
$scheduleResult = $scheduleQuery->get_result()->fetch_assoc();

// If no schedule exists for this date/area, return no_schedule status
if (!$scheduleResult) {
    echo json_encode([
        'status' => 'no_schedule',
        'date' => $date,
        'area_id' => $areaId,
        'area_name' => $areaName
    ]);
    exit;
}

$scheduleId = $scheduleResult['schedule_id'];
$collectionType = $scheduleResult['collection_type'];

// Get all lanes for this area with their status (only if schedule exists)
$lanesQuery = $conn->prepare("
    SELECT 
        cl.lane_id,
        cl.lane_name,
        ls.status
    FROM collection_lane cl
    LEFT JOIN lane_status ls ON ls.lane_name = cl.lane_name AND ls.schedule_id = ?
    WHERE cl.area_id = ?
    ORDER BY cl.lane_name ASC
");

$lanesQuery->bind_param('ii', $scheduleId, $areaId);
$lanesQuery->execute();
$lanesResult = $lanesQuery->get_result();

$lanes = [];
while ($lane = $lanesResult->fetch_assoc()) {
    // Determine if missed
    $lane_status = $lane['status'] ?? 'Pending';
    $is_missed = false;
    if ($lane_status === 'Pending') {
        $schedule_date = $date;
        $now = date('Y-m-d');
        if (strtotime($schedule_date) < strtotime($now)) {
            $lane_status = 'Missed';
            $is_missed = true;
        }
    }
    $lanes[] = [
        'lane_id' => $lane['lane_id'],
        'lane_name' => $lane['lane_name'],
        'status' => $lane_status
    ];
}

// Return JSON response
echo json_encode([
    'status' => 'success',
    'date' => $date,
    'area_id' => $areaId,
    'area_name' => $areaName,
    'schedule_id' => $scheduleId,
    'collection_type' => $collectionType,
    'lanes' => $lanes
]);
?>
