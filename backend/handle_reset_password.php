<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if ($password !== $confirm_password) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=password_mismatch");
        exit();
    }
    
    if (strlen($password) < 6) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=weak_password");
        exit();
    }
    
    // Verify token
    $stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=invalid_token");
        exit();
    }
    
    $reset = $result->fetch_assoc();
    $stmt->close();
    
    // Check if token is expired or already used
    if ($reset['used'] == 1) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=token_used");
        exit();
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=token_expired");
        exit();
    }
    
    $email = $reset['email'];
    
    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Update user password
    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE email = ? AND role = 'resident'");
    $stmt->bind_param("ss", $hashed_password, $email);
    
    if (!$stmt->execute()) {
        header("Location: ../public/reset_password.php?token=" . urlencode($token) . "&error=update_failed");
        exit();
    }
    $stmt->close();
    
    // Mark token as used
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    
    // Success - redirect to reset page with success message
    header("Location: ../public/reset_password.php?message=success");
    exit();
    
} else {
    header("Location: ../public/forgot_password.php");
    exit();
}
?>
