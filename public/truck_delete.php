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
    
    // Check if truck has any active schedules
    $check_schedule = "SELECT schedule_id FROM schedule WHERE truck_id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($check_schedule);
    $stmt->bind_param('i', $truck_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete truck with pending schedules. Please reassign or complete the schedules first.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First, deactivate all staff assignments
            $deactivate_staff = "UPDATE truck_staff SET status = 'inactive' WHERE truck_id = ?";
            $stmt = $conn->prepare($deactivate_staff);
            $stmt->bind_param('i', $truck_id);
            $stmt->execute();
            
            // Then delete the truck
            $delete_sql = "DELETE FROM truck WHERE truck_id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param('i', $truck_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['success'] = "Truck deleted successfully!";
            } else {
                throw new Exception("Error deleting truck: " . $conn->error);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    }
    
    header('Location: truck_management.php');
    exit();
}

header('HTTP/1.1 400 Bad Request');
echo 'Invalid request';
?>
