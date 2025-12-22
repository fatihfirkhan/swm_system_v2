<?php
/**
 * Fetch Schedule for Resident View
 * Returns only public schedule information (date and collection type)
 * No internal IDs are exposed for privacy/security
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

// Get area_id from request or use resident's area
$userId = $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role']);
$areaId = isset($_GET['area_id']) ? intval($_GET['area_id']) : null;

// If no area_id provided and user is a resident, get their area
if (!$areaId && $userRole === 'resident') {
    $areaQuery = $conn->prepare("SELECT area_id FROM user WHERE user_id = ?");
    $areaQuery->bind_param('i', $userId);
    $areaQuery->execute();
    $areaResult = $areaQuery->get_result()->fetch_assoc();

    if (!$areaResult || !$areaResult['area_id']) {
        echo json_encode(['error' => 'You are not assigned to any collection area']);
        exit;
    }
    
    $areaId = $areaResult['area_id'];
}

// Validate month parameter
if (!isset($_GET['month']) || !preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    echo json_encode(['error' => 'Invalid month format']);
    exit;
}

$month = $_GET['month'];

// Fetch schedules for the resident's area
// Only return public information: date and collection type
// No internal IDs (schedule_id, truck_id, area_id) are exposed
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(collection_date, '%Y-%m-%d') as date,
        collection_type as type
    FROM schedule 
    WHERE area_id = ? 
    AND DATE_FORMAT(collection_date, '%Y-%m') = ?
    ORDER BY collection_date ASC
");

$stmt->bind_param('is', $areaId, $month);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = [
        'date' => $row['date'],
        'type' => $row['type']
    ];
}

echo json_encode($schedules);
?>
