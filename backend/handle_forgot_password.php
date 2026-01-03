<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Use PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is available, if not we'll use mail() function
$usePhpMailer = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
    $usePhpMailer = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../public/forgot_password.php?error=invalid_email&email=" . urlencode($email));
        exit();
    }
    
    // Check if email exists in database (only for residents)
    $stmt = $conn->prepare("SELECT user_id, name, email FROM user WHERE email = ? AND role = 'resident'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: ../public/forgot_password.php?error=email_not_found&email=" . urlencode($email));
        exit();
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
    
    // Store token in database
    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $token, $expires_at);
    
    if (!$stmt->execute()) {
        header("Location: ../public/forgot_password.php?error=send_failed&email=" . urlencode($email));
        exit();
    }
    $stmt->close();
    
    // Create reset link
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $reset_link = $protocol . "://" . $host . "/reset_password.php?token=" . $token;
    
    // Prepare email
    $subject = "Password Reset Request - SWM Environment";
    $message_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4e73df; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f8f9fc; padding: 30px; border: 1px solid #e3e6f0; }
            .button { display: inline-block; padding: 12px 30px; background-color: #4e73df; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #858796; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset Request</h1>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                <p>We received a request to reset your password for your SWM Environment account.</p>
                <p>Click the button below to reset your password:</p>
                <div style='text-align: center;'>
                    <a href='" . $reset_link . "' class='button'>Reset Password</a>
                </div>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background-color: #fff; padding: 10px; border: 1px solid #ddd;'>" . $reset_link . "</p>
                <p><strong>This link will expire in 1 hour.</strong></p>
                <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
            </div>
            <div class='footer'>
                <p>This is an automated email from SWM Environment System. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $sent = false;
    
    // Try to send email using PHPMailer if available
    if ($usePhpMailer) {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Change this to your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your-email@gmail.com'; // Change this to your email
            $mail->Password   = 'your-app-password'; // Change this to your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('noreply@swmenvironment.com', 'SWM Environment');
            $mail->addAddress($email, $user['name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message_body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message_body));
            
            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            // Log error but don't expose details to user
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    }
    
    // Fallback to PHP mail() function if PHPMailer fails or not available
    if (!$sent) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: SWM Environment <noreply@swmenvironment.com>" . "\r\n";
        
        if (mail($email, $subject, $message_body, $headers)) {
            $sent = true;
        }
    }
    
    // Always show success message to prevent email enumeration
    // Even if email send fails, we don't want to reveal if email exists
    header("Location: ../public/forgot_password.php?message=email_sent");
    exit();
    
} else {
    header("Location: ../public/forgot_password.php");
    exit();
}
?>
