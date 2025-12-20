<?php
include '../includes/db.php';
session_start();

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

$role = $_GET['role'] ?? '';
$password = $_POST['password'] ?? '';

// Debug log
$debug_file = fopen("../debug_log.txt", "a");
fwrite($debug_file, "Login attempt - Role: " . $role . "\n");
fwrite($debug_file, "POST data: " . print_r($_POST, true) . "\n");

if ($role === 'resident') {
    $input = $_POST['email'] ?? '';
    $query = "SELECT * FROM user WHERE email = ? AND role = 'resident'";
} else {
    // Staff or Admin login
    $input = $_POST['work_id'] ?? '';
    $query = "SELECT * FROM user WHERE work_id = ?";
    
    // Debug
    fwrite($debug_file, "Staff/Admin login attempt with work_id: $input\n");
}

fwrite($debug_file, "Query: " . $query . "\n");
fwrite($debug_file, "Input: " . $input . "\n");

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $input);
$stmt->execute();
$result = $stmt->get_result();

fwrite($debug_file, "Query result rows: " . $result->num_rows . "\n");

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    fwrite($debug_file, "User data: " . print_r($user, true) . "\n");
    
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'] ?? '';
        $_SESSION['work_id'] = $user['work_id'] ?? '';

        fwrite($debug_file, "Login successful. Session data: " . print_r($_SESSION, true) . "\n");
        fclose($debug_file);

        // Redirect based on role - FIXED LOGIC
        $userRole = strtolower($user['role']);
        
        if ($userRole === 'resident') {
            // Redirect residents to their dashboard
            header("Location: ../public/resident_dashboard.php");
            exit();
        } elseif ($userRole === 'admin') {
            // Redirect admins to admin dashboard
            header("Location: ../public/admin_dashboard.php");
            exit();
        } elseif ($userRole === 'staff') {
            // Redirect staff to staff dashboard
            header("Location: ../public/staff_dashboard.php");
            exit();
        } else {
            // Unknown role - show error
            $_SESSION['error'] = 'Unknown user role: ' . $user['role'];
            header("Location: ../public/login.php?role=$role&error=invalid_role");
            exit();
        }
    } else {
        fwrite($debug_file, "Wrong password\n");
        header("Location: ../public/login.php?role=$role&error=wrong_password");
    }
} else {
    fwrite($debug_file, "User not found\n");
    header("Location: ../public/login.php?role=$role&error=user_not_found");
}
fclose($debug_file);
?>
