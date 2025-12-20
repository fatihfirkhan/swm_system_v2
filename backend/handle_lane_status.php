<?php
session_start();
require_once '../includes/db.php';

// Output buffer and JSON headers
ob_start();
header('Content-Type: application/json');

// Check if user is logged in as staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$staff_user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Find the truck assigned to this staff member
$truck_query = $conn->prepare("SELECT truck_id FROM truck_staff WHERE user_id = ?");
$truck_query->bind_param("s", $staff_user_id);
$truck_query->execute();
$truck_result = $truck_query->get_result();

if ($truck_result->num_rows === 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'No truck assigned to your account']);
    exit();
}

$assigned_truck_id = $truck_result->fetch_assoc()['truck_id'];

// ========== GET LANES FOR SELECTED DATE ==========
if ($action === 'get_lanes') {
    $collection_date = $_POST['collection_date'] ?? '';
    
    if (empty($collection_date)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Collection date is required']);
        exit();
    }
    
    // Find schedule for this truck and date
    $schedule_query = $conn->prepare("
        SELECT 
            s.schedule_id, 
            s.area_id, 
            s.collection_date, 
            s.collection_type,
            ca.taman_name,
            t.truck_number
        FROM schedule s
        LEFT JOIN collection_area ca ON s.area_id = ca.area_id
        LEFT JOIN truck t ON s.truck_id = t.truck_id
        WHERE s.truck_id = ? AND s.collection_date = ?
    ");
    $schedule_query->bind_param("ss", $assigned_truck_id, $collection_date);
    $schedule_query->execute();
    $schedule_result = $schedule_query->get_result();
    
    if ($schedule_result->num_rows === 0) {
        ob_clean();
        echo json_encode([
            'status' => 'error', 
            'message' => 'No schedule found for your truck on this date'
        ]);
        exit();
    }
    
    $schedule = $schedule_result->fetch_assoc();
    $schedule_id = $schedule['schedule_id'];
    $area_id = $schedule['area_id'];
    $area_name = $schedule['taman_name'];
    $truck_number = $schedule['truck_number'];
    $collection_type = $schedule['collection_type'];
    
    // Check if selected date is today
    $is_today = ($collection_date === date('Y-m-d'));
    
    // Get all lanes for this area with their status
    $lanes_query = $conn->prepare("
        SELECT 
            cl.lane_id,
            cl.lane_name,
            ls.status,
            ls.updated_by,
            DATE_FORMAT(ls.update_time, '%h:%i %p') as update_time
        FROM collection_lane cl
        LEFT JOIN lane_status ls ON ls.schedule_id = ? AND ls.lane_name = cl.lane_name
        WHERE cl.area_id = ?
        ORDER BY cl.lane_name
    ");
    $lanes_query->bind_param("ss", $schedule_id, $area_id);
    $lanes_query->execute();
    $lanes_result = $lanes_query->get_result();
    
    $lanes = [];
    while ($lane = $lanes_result->fetch_assoc()) {
        $lanes[] = [
            'lane_id' => $lane['lane_id'],
            'lane_name' => $lane['lane_name'],
            'status' => $lane['status'] ?? null,
            'updated_by' => $lane['updated_by'] ?? null,
            'update_time' => $lane['update_time'] ?? null
        ];
    }
    
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'data' => [
            'schedule_id' => $schedule_id,
            'area_id' => $area_id,
            'area_name' => $area_name,
            'truck_number' => $truck_number,
            'collection_type' => $collection_type,
            'is_today' => $is_today,
            'lanes' => $lanes
        ]
    ]);
    exit();
}

// ========== UPDATE LANE STATUS ==========
if ($action === 'update_status') {
    $schedule_id = $_POST['schedule_id'] ?? '';
    $lane_name = $_POST['lane_name'] ?? '';
    $status = $_POST['status'] ?? '';
    
    // Validation
    if (empty($schedule_id) || empty($lane_name) || empty($status)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit();
    }
    
    if (!in_array($status, ['Pending', 'Collected'])) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
        exit();
    }
    
    // Verify this schedule belongs to the staff's truck
    $verify_query = $conn->prepare("SELECT schedule_id FROM schedule WHERE schedule_id = ? AND truck_id = ?");
    $verify_query->bind_param("ss", $schedule_id, $assigned_truck_id);
    $verify_query->execute();
    
    if ($verify_query->get_result()->num_rows === 0) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: This schedule does not belong to your truck']);
        exit();
    }
    
    // Check if this schedule is for today (24-hour rule)
    $date_check = $conn->prepare("SELECT collection_date FROM schedule WHERE schedule_id = ?");
    $date_check->bind_param("s", $schedule_id);
    $date_check->execute();
    $date_result = $date_check->get_result();
    $schedule_date = $date_result->fetch_assoc()['collection_date'];
    
    if ($schedule_date !== date('Y-m-d')) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Cannot update: Only today\'s schedules can be modified']);
        exit();
    }
    
    // Check if lane_status row exists
    $check_query = $conn->prepare("SELECT status FROM lane_status WHERE schedule_id = ? AND lane_name = ?");
    $check_query->bind_param("ss", $schedule_id, $lane_name);
    $check_query->execute();
    $check_result = $check_query->get_result();
    
    $update_time = date('Y-m-d H:i:s');
    
    if ($check_result->num_rows > 0) {
        // UPDATE existing row
        $update_query = $conn->prepare("
            UPDATE lane_status 
            SET status = ?, updated_by = ?, update_time = ?
            WHERE schedule_id = ? AND lane_name = ?
        ");
        $update_query->bind_param("sssss", $status, $staff_user_id, $update_time, $schedule_id, $lane_name);
        
        if ($update_query->execute()) {
            ob_clean();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Lane status updated to ' . $status
            ]);
        } else {
            ob_clean();
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to update lane status: ' . $conn->error
            ]);
        }
    } else {
        // INSERT new row
        $insert_query = $conn->prepare("
            INSERT INTO lane_status (schedule_id, lane_name, status, updated_by, update_time)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_query->bind_param("sssss", $schedule_id, $lane_name, $status, $staff_user_id, $update_time);
        
        if ($insert_query->execute()) {
            ob_clean();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Lane status set to ' . $status
            ]);
        } else {
            ob_clean();
            echo json_encode([
                'status' => 'error', 
                'message' => 'Failed to insert lane status: ' . $conn->error
            ]);
        }
    }
    exit();
}

// Unknown action
ob_clean();
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit();
?>
