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

// Get all assigned staff IDs (for filtering)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'assigned_ids') {
    try {
        $exclude_truck_id = isset($_GET['exclude_truck_id']) ? intval($_GET['exclude_truck_id']) : 0;
        
        $sql = "SELECT DISTINCT user_id 
                FROM truck_staff 
                WHERE status = 'active'";
        
        if ($exclude_truck_id > 0) {
            $sql .= " AND truck_id != ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('i', $exclude_truck_id);
        } else {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $assigned_ids = [];
        while ($row = $result->fetch_assoc()) {
            $assigned_ids[] = $row['user_id'];
        }
        
        echo json_encode(['assigned_ids' => $assigned_ids]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Get all assigned staff with their truck info (for swap feature)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'all_assigned') {
    try {
        $sql = "SELECT ts.user_id, ts.truck_id, ts.role, u.name, u.work_id, t.truck_number
                FROM truck_staff ts
                INNER JOIN user u ON ts.user_id = u.user_id
                INNER JOIN truck t ON ts.truck_id = t.truck_id
                WHERE ts.status = 'active'
                ORDER BY t.truck_number, ts.role";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $staff = [];
        while ($row = $result->fetch_assoc()) {
            $staff[] = [
                'user_id' => $row['user_id'],
                'truck_id' => $row['truck_id'],
                'truck_number' => $row['truck_number'],
                'name' => $row['name'],
                'work_id' => $row['work_id'],
                'role' => $row['role']
            ];
        }
        
        echo json_encode(['staff' => $staff]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Get swap options - both assigned (other trucks) and unassigned staff
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'swap_options') {
    try {
        $exclude_truck_id = isset($_GET['exclude_truck_id']) ? intval($_GET['exclude_truck_id']) : 0;
        
        // Get assigned staff from other trucks
        $sql1 = "SELECT ts.user_id, ts.truck_id, ts.role, u.name, u.work_id, t.truck_number
                FROM truck_staff ts
                INNER JOIN user u ON ts.user_id = u.user_id
                INNER JOIN truck t ON ts.truck_id = t.truck_id
                WHERE ts.status = 'active' AND ts.truck_id != ?
                ORDER BY t.truck_number, ts.role";
        
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param('i', $exclude_truck_id);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        
        $assigned = [];
        while ($row = $result1->fetch_assoc()) {
            $assigned[] = [
                'user_id' => $row['user_id'],
                'truck_id' => $row['truck_id'],
                'truck_number' => $row['truck_number'],
                'name' => $row['name'],
                'work_id' => $row['work_id'],
                'role' => $row['role']
            ];
        }
        
        // Get unassigned staff (staff role users not in any active truck assignment)
        $sql2 = "SELECT u.user_id, u.name, u.work_id
                FROM user u
                WHERE u.role = 'staff'
                AND u.user_id NOT IN (
                    SELECT user_id FROM truck_staff WHERE status = 'active'
                )
                ORDER BY u.name";
        
        $result2 = $conn->query($sql2);
        
        $unassigned = [];
        while ($row = $result2->fetch_assoc()) {
            $unassigned[] = [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'work_id' => $row['work_id']
            ];
        }
        
        echo json_encode(['assigned' => $assigned, 'unassigned' => $unassigned]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Get unassigned staff only
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'unassigned') {
    try {
        $sql = "SELECT u.user_id, u.name, u.work_id
                FROM user u
                WHERE u.role = 'staff'
                AND u.user_id NOT IN (
                    SELECT user_id FROM truck_staff WHERE status = 'active'
                )
                ORDER BY u.name";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
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
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>