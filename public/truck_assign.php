<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['truck_id'])) {
    $truck_id = intval($_POST['truck_id']);
    $driver_id = !empty($_POST['driver_id']) ? intval($_POST['driver_id']) : null;
    $collector1_id = !empty($_POST['collector1_id']) ? intval($_POST['collector1_id']) : null;
    $collector2_id = !empty($_POST['collector2_id']) ? intval($_POST['collector2_id']) : null;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, deactivate all current assignments for this truck
        $deactivate_sql = "UPDATE truck_staff SET status = 'inactive' WHERE truck_id = ?";
        $stmt = $conn->prepare($deactivate_sql);
        $stmt->bind_param('i', $truck_id);
        $stmt->execute();
        
        // Function to assign staff to truck
        $assignStaff = function($user_id, $truck_id, $role) use ($conn) {
            if (!$user_id) return;
            
            // Check if this user is already assigned to another active truck
            $check_sql = "SELECT truck_id FROM truck_staff 
                         WHERE user_id = ? AND status = 'active' AND truck_id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param('ii', $user_id, $truck_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("This staff member is already assigned to another truck.");
            }
            
            // Check if this user already has an inactive assignment to this truck
            $check_inactive = "SELECT id FROM truck_staff 
                             WHERE user_id = ? AND truck_id = ? AND status = 'inactive'";
            $stmt = $conn->prepare($check_inactive);
            $stmt->bind_param('ii', $user_id, $truck_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing assignment
                $update_sql = "UPDATE truck_staff 
                              SET status = 'active', 
                                  role = ?, 
                                  assigned_date = NOW() 
                              WHERE user_id = ? AND truck_id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param('sii', $role, $user_id, $truck_id);
            } else {
                // Create new assignment
                $insert_sql = "INSERT INTO truck_staff 
                              (truck_id, user_id, role, status, assigned_date) 
                              VALUES (?, ?, ?, 'active', NOW())";
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
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: truck_management.php');
    exit();
}

header('HTTP/1.1 400 Bad Request');
echo 'Invalid request';
?>
