<?php
include '../includes/db.php';
session_start();

$role = $_GET['role'] ?? '';
$password = $_POST['password'] ?? '';

if ($role === 'resident') {
    $input = $_POST['email'] ?? '';
    $query = "SELECT * FROM user WHERE email = ? AND role = 'resident'";
} else {
    // Staff or Admin login
    $input = $_POST['work_id'] ?? '';
    $query = "SELECT * FROM user WHERE work_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $input);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'] ?? '';
        $_SESSION['work_id'] = $user['work_id'] ?? '';

        // Redirect based on role
        $userRole = strtolower($user['role']);

        if ($userRole === 'resident') {
            header("Location: /resident_dashboard.php");
            exit();
        } elseif ($userRole === 'admin') {
            header("Location: /admin_dashboard.php");
            exit();
        } elseif ($userRole === 'staff') {
            header("Location: /staff_dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = 'Unknown user role: ' . $user['role'];
            header("Location: /login.php?role=$role&error=invalid_role");
            exit();
        }
    } else {
        header("Location: /login.php?role=$role&error=wrong_password");
    }
} else {
    header("Location: /login.php?role=$role&error=user_not_found");
}
?>