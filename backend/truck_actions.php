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
    case 'remove_staff':
        handleRemoveStaff();
        break;
    case 'swap_staff':
        handleSwapStaff();
        break;
    case 'add_single_staff':
        handleAddSingleStaff();
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
    
    // Insert new truck (capacity uses default value from database)
    $sql = "INSERT INTO truck (truck_number, status) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $truck_number, $status);
    
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
    
    // Update truck (only status is editable now)
    $sql = "UPDATE truck SET 
            status = ?
            WHERE truck_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $truck_id);
    
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
 * Handle removing a staff member from a truck
 */
function handleRemoveStaff() {
    global $conn;
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit();
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $truck_id = intval($_POST['truck_id'] ?? 0);
    
    if (empty($user_id) || empty($truck_id)) {
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    try {
        // Set staff assignment to inactive
        $sql = "UPDATE truck_staff SET status = 'inactive' WHERE user_id = ? AND truck_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $user_id, $truck_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Staff removed successfully']);
        } else {
            throw new Exception('Failed to remove staff');
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

/**
 * Handle swapping two staff members between trucks
 * Also handles replacing with unassigned staff (target_truck_id = 0)
 */
function handleSwapStaff() {
    global $conn;
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit();
    }
    
    $source_user_id = intval($_POST['source_user_id'] ?? 0);
    $source_truck_id = intval($_POST['source_truck_id'] ?? 0);
    $source_role = sanitizeInput($_POST['source_role'] ?? '');
    $target_user_id = intval($_POST['target_user_id'] ?? 0);
    $target_truck_id = intval($_POST['target_truck_id'] ?? 0);
    $target_role = sanitizeInput($_POST['target_role'] ?? '');
    
    if (empty($source_user_id) || empty($source_truck_id) || empty($target_user_id)) {
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        // Deactivate source staff's current assignment
        $sql1 = "UPDATE truck_staff SET status = 'inactive' WHERE user_id = ? AND truck_id = ? AND status = 'active'";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param('ii', $source_user_id, $source_truck_id);
        $stmt1->execute();
        
        // Check if target is from another truck or unassigned
        if ($target_truck_id > 0) {
            // Target is from another truck - do a real swap
            // Deactivate target staff's current assignment
            $sql2 = "UPDATE truck_staff SET status = 'inactive' WHERE user_id = ? AND truck_id = ? AND status = 'active'";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param('ii', $target_user_id, $target_truck_id);
            $stmt2->execute();
            
            // Assign source staff to target truck with target's role
            $sql3 = "INSERT INTO truck_staff (truck_id, user_id, role, status) VALUES (?, ?, ?, 'active')";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param('iis', $target_truck_id, $source_user_id, $target_role);
            $stmt3->execute();
            
            // Assign target staff to source truck with source's role
            $sql4 = "INSERT INTO truck_staff (truck_id, user_id, role, status) VALUES (?, ?, ?, 'active')";
            $stmt4 = $conn->prepare($sql4);
            $stmt4->bind_param('iis', $source_truck_id, $target_user_id, $source_role);
            $stmt4->execute();
        } else {
            // Target is unassigned - just replace source with target
            // Assign target (unassigned) staff to source truck with source's role
            $sql3 = "INSERT INTO truck_staff (truck_id, user_id, role, status) VALUES (?, ?, ?, 'active')";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param('iis', $source_truck_id, $target_user_id, $source_role);
            $stmt3->execute();
            // Source staff is now unassigned (already deactivated above)
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Staff swapped successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

/**
 * Handle adding a single staff to a truck position
 */
function handleAddSingleStaff() {
    global $conn;
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit();
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $truck_id = intval($_POST['truck_id'] ?? 0);
    $role = sanitizeInput($_POST['role'] ?? '');
    
    if (empty($user_id) || empty($truck_id) || empty($role)) {
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }
    
    try {
        // Check if staff is already assigned to another truck
        $check_sql = "SELECT truck_id FROM truck_staff WHERE user_id = ? AND status = 'active'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['error' => 'This staff is already assigned to another truck']);
            exit();
        }
        
        // Check if role is already filled for this truck
        $role_check = "SELECT user_id FROM truck_staff WHERE truck_id = ? AND role = ? AND status = 'active'";
        $role_stmt = $conn->prepare($role_check);
        $role_stmt->bind_param('is', $truck_id, $role);
        $role_stmt->execute();
        
        if ($role_stmt->get_result()->num_rows > 0) {
            echo json_encode(['error' => 'This position is already filled']);
            exit();
        }
        
        // Check if there's an existing inactive record for this truck-user combination
        $existing_check = "SELECT id FROM truck_staff WHERE truck_id = ? AND user_id = ?";
        $existing_stmt = $conn->prepare($existing_check);
        $existing_stmt->bind_param('ii', $truck_id, $user_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE truck_staff SET role = ?, status = 'active' WHERE truck_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sii', $role, $truck_id, $user_id);
        } else {
            // Insert new assignment
            $sql = "INSERT INTO truck_staff (truck_id, user_id, role, status) VALUES (?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iis', $truck_id, $user_id, $role);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Staff added successfully']);
        } else {
            throw new Exception('Failed to add staff');
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
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
