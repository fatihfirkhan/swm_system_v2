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
    $truck_number = $conn->real_escape_string(trim($_POST['truck_number']));
    $model = $conn->real_escape_string(trim($_POST['model']));
    $capacity = $conn->real_escape_string(trim($_POST['capacity']));
    $status = $conn->real_escape_string(trim($_POST['status']));
    
    // Check if truck number already exists (excluding current truck)
    $check_sql = "SELECT truck_id FROM truck WHERE truck_number = '$truck_number' AND truck_id != $truck_id";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "A truck with this number already exists.";
    } else {
        $sql = "UPDATE truck SET 
                truck_number = '$truck_number',
                model = " . ($model ? "'$model'" : "NULL") . ",
                capacity = " . ($capacity ? "'$capacity'" : "NULL") . ",
                status = '$status'
                WHERE truck_id = $truck_id";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Truck updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating truck: " . $conn->error;
        }
    }
    
    header('Location: truck_management.php');
    exit();
}

header('HTTP/1.1 400 Bad Request');
echo 'Invalid request';
?>
