<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/db.php';

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

