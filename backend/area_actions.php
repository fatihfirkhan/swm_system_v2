<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// Get action from query parameter
$action = $_GET['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'add':
        handleAddArea();
        break;
    case 'add_lanes':
        handleAddLanes();
        break;
    case 'delete':
        handleDeleteArea();
        break;
    case 'get_lanes':
        handleGetLanes();
        break;
    case 'delete_lane':
        handleDeleteLane();
        break;
    default:
        $_SESSION['error'] = 'Invalid action';
        header('Location: ../public/area_management.php');
        exit();
}

/**
 * Handle adding a new area
 */
function handleAddArea() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $taman_name = trim($_POST['taman_name'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    
    // Validate required fields
    if (empty($taman_name) || empty($postcode)) {
        sendError('Taman name and postcode are required');
    }
    
    // Validate postcode format
    if (!preg_match('/^[0-9]{5}$/', $postcode)) {
        sendError('Postcode must be 5 digits');
    }
    
    // Check for duplicate taman name
    $check_sql = "SELECT area_id FROM collection_area WHERE taman_name = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('s', $taman_name);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        sendError('An area with this name already exists');
    }
    
    // Insert new area
    $sql = "INSERT INTO collection_area (taman_name, postcode) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $taman_name, $postcode);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Area '$taman_name' added successfully!";
        header('Location: ../public/area_management.php');
        exit();
    } else {
        sendError('Error adding area: ' . $conn->error);
    }
}

/**
 * Handle bulk creating lanes
 */
function handleAddLanes() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $area_id = intval($_POST['area_id'] ?? 0);
    $lane_base_name = trim($_POST['lane_base_name'] ?? '');
    $start_number = intval($_POST['start_number'] ?? 0);
    $end_number = intval($_POST['end_number'] ?? 0);
    
    // Validate required fields
    if (empty($area_id) || empty($lane_base_name)) {
        sendError('Area ID and lane base name are required');
    }
    
    if ($start_number < 1 || $end_number < 1 || $start_number > $end_number) {
        sendError('Invalid number range');
    }
    
    if (($end_number - $start_number) > 100) {
        sendError('Cannot create more than 100 lanes at once');
    }
    
    // Verify area exists
    $check_area = "SELECT taman_name FROM collection_area WHERE area_id = ?";
    $stmt = $conn->prepare($check_area);
    $stmt->bind_param('i', $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Area not found');
    }
    
    $area = $result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $insert_sql = "INSERT INTO collection_lane (area_id, lane_name) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        
        $created_count = 0;
        for ($i = $start_number; $i <= $end_number; $i++) {
            $lane_name = $lane_base_name . ' ' . $i;
            
            $stmt->bind_param('is', $area_id, $lane_name);
            if ($stmt->execute()) {
                $created_count++;
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Successfully created $created_count lanes for {$area['taman_name']}!";
        header('Location: ../public/area_management.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError('Error creating lanes: ' . $e->getMessage());
    }
}

/**
 * Handle deleting an area
 */
function handleDeleteArea() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $area_id = intval($_POST['area_id'] ?? 0);
    
    if ($area_id === 0) {
        sendError('Invalid area ID');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete all lanes for this area first
        $delete_lanes_sql = "DELETE FROM collection_lane WHERE area_id = ?";
        $stmt = $conn->prepare($delete_lanes_sql);
        $stmt->bind_param('i', $area_id);
        $stmt->execute();
        
        // Delete the area
        $delete_area_sql = "DELETE FROM collection_area WHERE area_id = ?";
        $stmt = $conn->prepare($delete_area_sql);
        $stmt->bind_param('i', $area_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['success'] = "Area deleted successfully!";
            header('Location: ../public/area_management.php');
            exit();
        } else {
            throw new Exception("Error deleting area: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}

/**
 * Handle getting lanes for an area (AJAX)
 */
function handleGetLanes() {
    global $conn;
    
    header('Content-Type: application/json');
    
    $area_id = intval($_GET['area_id'] ?? 0);
    
    if ($area_id === 0) {
        echo json_encode(['error' => 'Invalid area ID']);
        exit();
    }
    
    $sql = "SELECT lane_id, lane_name FROM collection_lane WHERE area_id = ? ORDER BY lane_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lanes = [];
    while ($row = $result->fetch_assoc()) {
        $lanes[] = $row;
    }
    
    echo json_encode(['lanes' => $lanes]);
    exit();
}

/**
 * Handle deleting a single lane (AJAX)
 */
function handleDeleteLane() {
    global $conn;
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }
    
    $lane_id = intval($_POST['lane_id'] ?? 0);
    
    if ($lane_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid lane ID']);
        exit();
    }
    
    $sql = "DELETE FROM collection_lane WHERE lane_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $lane_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

/**
 * Helper function to send error response
 */
function sendError($message) {
    $_SESSION['error'] = $message;
    header('Location: ../public/area_management.php');
    exit();
}
?>
