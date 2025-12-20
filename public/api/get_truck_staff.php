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
    $truck_id = intval($_GET['truck_id']);
    
    $response = [
        'driver_id' => null,
        'collector1_id' => null,
        'collector2_id' => null
    ];
    
    // Get current staff assignments for this truck
    $sql = "SELECT user_id, role 
            FROM truck_staff 
            WHERE truck_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $truck_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (strpos($row['role'], 'Driver') !== false) {
            $response['driver_id'] = $row['user_id'];
        } elseif (strpos($row['role'], 'Collector 1') !== false) {
            $response['collector1_id'] = $row['user_id'];
        } elseif (strpos($row['role'], 'Collector 2') !== false) {
            $response['collector2_id'] = $row['user_id'];
        }
    }
    
    echo json_encode($response);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>
