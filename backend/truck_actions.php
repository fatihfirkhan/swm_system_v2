<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// Get action from query parameter or form data
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'add':
        handleAddTruck();
        break;
    case 'edit':
        handleEditTruck();
        break;
    case 'delete':
        handleDeleteTruck();
        break;
    case 'assign_staff':
        handleAssignStaff();
        break;
    default:
        header('HTTP/1.1 400 Bad Request');
        $_SESSION['error'] = 'Invalid action';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../public/truck_management.php'));
        exit();
}

/**
 * Handle adding a new truck
 */
function handleAddTruck() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $truck_number = sanitizeInput($_POST['truck_number']);
    $capacity = sanitizeInput($_POST['capacity']);
    $status = sanitizeInput($_POST['status']);
    
    // Validate required fields
    if (empty($truck_number)) {
        sendError('Truck number is required');
    }
    
    // Check for duplicate truck number
    $check_sql = "SELECT truck_id FROM truck WHERE truck_number = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param('s', $truck_number);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        sendError('A truck with this number already exists');
    }
    
    // Insert new truck
    $sql = "INSERT INTO truck (truck_number, capacity, status) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $truck_number, $capacity, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Truck added successfully!";
        header('Location: ../public/truck_management.php');
        exit();
    } else {
        sendError('Error adding truck: ' . $conn->error);
    }
}

/**
 * Handle editing an existing truck
 */
function handleEditTruck() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $truck_id = intval($_POST['truck_id']);
    $truck_number = sanitizeInput($_POST['truck_number'] ?? '');
    $capacity = sanitizeInput($_POST['capacity']);
    $status = sanitizeInput($_POST['status']);
    
    // Validate required fields
    if (empty($truck_number)) {
        // Get existing truck number if not provided
        $get_sql = "SELECT truck_number FROM truck WHERE truck_id = ?";
        $stmt = $conn->prepare($get_sql);
        $stmt->bind_param('i', $truck_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $truck_number = $result->fetch_assoc()['truck_number'];
        } else {
            sendError('Truck not found');
        }
    }
    
    // Update truck (truck_number is not editable, but we keep it for validation)
    $sql = "UPDATE truck SET 
            capacity = ?,
            status = ?
            WHERE truck_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $capacity, $status, $truck_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Truck updated successfully!";
        header('Location: ../public/truck_management.php');
        exit();
    } else {
        sendError('Error updating truck: ' . $conn->error);
    }
}

/**
 * Handle deleting a truck
 */
function handleDeleteTruck() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $truck_id = intval($_POST['truck_id']);
    
    // Check for pending schedules
    $check_schedule = "SELECT schedule_id FROM schedule WHERE truck_id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($check_schedule);
    $stmt->bind_param('i', $truck_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        sendError('Cannot delete truck with pending schedules. Please reassign or complete the schedules first.');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deactivate staff assignments
        $deactivate_staff = "UPDATE truck_staff SET status = 'inactive' WHERE truck_id = ?";
        $stmt = $conn->prepare($deactivate_staff);
        $stmt->bind_param('i', $truck_id);
        $stmt->execute();
        
        // Delete the truck
        $delete_sql = "DELETE FROM truck WHERE truck_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param('i', $truck_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['success'] = "Truck deleted successfully!";
            header('Location: ../public/truck_management.php');
            exit();
        } else {
            throw new Exception("Error deleting truck: " . $conn->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        sendError($e->getMessage());
    }
}

/**
 * Handle assigning staff to a truck
 */
function handleAssignStaff() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Invalid request method');
    }
    
    $truck_id = intval($_POST['truck_id']);
    $driver_id = !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
    $collector1_id = !empty($_POST['collector1_id']) ? intval($_POST['collector1_id']) : null;
    $collector2_id = !empty($_POST['collector2_id']) ? intval($_POST['collector2_id']) : null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deactivate all current assignments for this truck
        $deactivate_sql = "UPDATE truck_staff SET status = 'inactive' WHERE truck_id = ?";
        $stmt = $conn->prepare($deactivate_sql);
        $stmt->bind_param('i', $truck_id);
        $stmt->execute();
        
        // Assign staff with their roles
        $assignStaff = function($user_id, $truck_id, $role) use ($conn) {
            if (!$user_id) return;
            
            // Check if staff is already assigned to another active truck
            $check_sql = "SELECT truck_id FROM truck_staff 
                         WHERE user_id = ? AND status = 'active' AND truck_id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param('ii', $user_id, $truck_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("This staff member is already assigned to another truck.");
            }
            
            // Check for existing inactive assignment
            $check_inactive = "SELECT id FROM truck_staff 
                             WHERE user_id = ? AND truck_id = ? AND status = 'inactive'";
            $stmt = $conn->prepare($check_inactive);
            $stmt->bind_param('ii', $user_id, $truck_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                // Update existing assignment
                $update_sql = "UPDATE truck_staff 
                              SET status = 'active', 
                                  role = ? 
                              WHERE user_id = ? AND truck_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param('sii', $role, $user_id, $truck_id);
            } else {
                // Create new assignment
                $insert_sql = "INSERT INTO truck_staff 
                              (truck_id, user_id, role, status) 
                              VALUES (?, ?, ?, 'active')";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param('iis', $truck_id, $user_id, $role);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error assigning staff: " . $conn->error);
            }
        };
        
        // Assign driver and collectors
        if ($driver_id) {
            $assignStaff($driver_id, $truck_id, 'Driver');
        }
        if ($collector1_id) {
            $assignStaff($collector1_id, $truck_id, 'Collector 1');
        }
        if ($collector2_id) {
            $assignStaff($collector2_id, $truck_id, 'Collector 2');
        }
        
        // Commit transaction
        $conn->commit();
        $_SESSION['success'] = "Staff assignments updated successfully!";
        header('Location: ../public/truck_management.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        sendError($e->getMessage());
    }
}

/**
 * Helper function to sanitize input
 */
function sanitizeInput($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

/**
 * Helper function to send error response
 */
function sendError($message) {
    $_SESSION['error'] = $message;
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../public/truck_management.php'));
    exit();
}
?>
