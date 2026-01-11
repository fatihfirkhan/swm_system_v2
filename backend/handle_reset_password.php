<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

function sendResponse($success, $message, $type = 'info')
{
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message, 'type' => $type]);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if ($password !== $confirm_password) {
        if ($isAjax) {
            sendResponse(false, 'Passwords do not match. Please try again.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=password_mismatch");
        exit();
    }

    if (strlen($password) < 6) {
        if ($isAjax) {
            sendResponse(false, 'Password must be at least 6 characters long.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=weak_password");
        exit();
    }

    // Verify token
    $stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if ($isAjax) {
            sendResponse(false, 'Invalid reset link. Please request a new password reset.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=invalid_token");
        exit();
    }

    $reset = $result->fetch_assoc();
    $stmt->close();

    // Check if token is expired or already used
    if ($reset['used'] == 1) {
        if ($isAjax) {
            sendResponse(false, 'This reset link has already been used.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=token_used");
        exit();
    }

    if (strtotime($reset['expires_at']) < time()) {
        if ($isAjax) {
            sendResponse(false, 'This reset link has expired. Please request a new one.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=token_expired");
        exit();
    }

    $email = $reset['email'];

    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Update user password
    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE email = ? AND role = 'resident'");
    $stmt->bind_param("ss", $hashed_password, $email);

    if (!$stmt->execute()) {
        if ($isAjax) {
            sendResponse(false, 'Failed to update password. Please try again.', 'error');
        }
        header("Location: /reset_password.php?token=" . urlencode($token) . "&error=update_failed");
        exit();
    }
    $stmt->close();

    // Mark token as used
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();

    // Success
    if ($isAjax) {
        sendResponse(true, 'Password has been reset successfully! You can now login with your new password.', 'success');
    }
    header("Location: /reset_password.php?message=success");
    exit();

} else {
    header("Location: /forgot_password.php?role=resident");
    exit();
}
?>