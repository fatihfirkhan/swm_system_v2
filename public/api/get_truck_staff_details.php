<?php
header('Content-Type: application/json');
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['truck_id'])) {
    try {
        $truck_id = intval($_GET['truck_id']);
        
        $response = [
            'staff' => []
        ];
        
        // Get current staff assignments for this truck with names
        $sql = "SELECT ts.user_id, ts.role, u.name, u.work_id
                FROM truck_staff ts
                INNER JOIN user u ON ts.user_id = u.user_id
                WHERE ts.truck_id = ? AND ts.status = 'active'
                ORDER BY ts.role";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('i', $truck_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['staff'][] = [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'work_id' => $row['work_id'],
                'role' => $row['role']
            ];
        }
        
        echo json_encode($response);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Get unassigned staff (not assigned to any truck)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'unassigned') {
    $sql = "SELECT u.user_id, u.name, u.work_id 
            FROM user u
            WHERE u.role = 'staff' 
            AND u.user_id NOT IN (
                SELECT DISTINCT user_id FROM truck_staff WHERE status = 'active'
            )
            ORDER BY u.name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'work_id' => $row['work_id']
        ];
    }
    
    echo json_encode(['staff' => $staff]);
    exit();
}

// Get swap options (staff from other trucks AND unassigned)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'swap_options') {
    $exclude_truck_id = isset($_GET['exclude_truck_id']) ? intval($_GET['exclude_truck_id']) : 0;
    
    // Get unassigned staff
    $sql_unassigned = "SELECT u.user_id, u.name, u.work_id 
            FROM user u
            WHERE u.role = 'staff' 
            AND u.user_id NOT IN (
                SELECT DISTINCT user_id FROM truck_staff WHERE status = 'active'
            )
            ORDER BY u.name";
    
    $result_unassigned = $conn->query($sql_unassigned);
    $unassigned = [];
    while ($row = $result_unassigned->fetch_assoc()) {
        $unassigned[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'work_id' => $row['work_id']
        ];
    }
    
    // Get staff from other trucks
    $sql_assigned = "SELECT ts.user_id, ts.role, ts.truck_id, u.name, u.work_id, t.truck_number
            FROM truck_staff ts
            INNER JOIN user u ON ts.user_id = u.user_id
            INNER JOIN truck t ON ts.truck_id = t.truck_id
            WHERE ts.status = 'active' AND ts.truck_id != ?
            ORDER BY t.truck_number, ts.role";
    
    $stmt = $conn->prepare($sql_assigned);
    $stmt->bind_param('i', $exclude_truck_id);
    $stmt->execute();
    $result_assigned = $stmt->get_result();
    
    $assigned = [];
    while ($row = $result_assigned->fetch_assoc()) {
        $assigned[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'work_id' => $row['work_id'],
            'role' => $row['role'],
            'truck_id' => $row['truck_id'],
            'truck_number' => $row['truck_number']
        ];
    }
    
    echo json_encode(['unassigned' => $unassigned, 'assigned' => $assigned]);
    exit();
}

// Get all assigned staff IDs (for filtering)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'assigned_ids') {
    $exclude_truck_id = isset($_GET['exclude_truck_id']) ? intval($_GET['exclude_truck_id']) : 0;
    
    $sql = "SELECT DISTINCT user_id 
            FROM truck_staff 
            WHERE status = 'active'";
    
    if ($exclude_truck_id > 0) {
        $sql .= " AND truck_id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $exclude_truck_id);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assigned_ids = [];
    while ($row = $result->fetch_assoc()) {
        $assigned_ids[] = $row['user_id'];
    }
    
    echo json_encode(['assigned_ids' => $assigned_ids]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>

