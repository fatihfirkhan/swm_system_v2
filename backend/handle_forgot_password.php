<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_config.php';

// Use PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is available, if not we'll use mail() function
$usePhpMailer = false;
$phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    $usePhpMailer = true;
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
    $usePhpMailer = true;
}

// Log function for debugging (errors suppressed for production)
function logDebug($message)
{
    if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
        $logFile = __DIR__ . '/../debug_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

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
    $email = trim($_POST['email']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isAjax) {
            sendResponse(false, 'Please enter a valid email address.', 'error');
        }
        header("Location: ../public/forgot_password.php?error=invalid_email&email=" . urlencode($email));
        exit();
    }

    // Check if email exists in database (only for residents)
    $stmt = $conn->prepare("SELECT user_id, name, email FROM user WHERE email = ? AND role = 'resident'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        if ($isAjax) {
            sendResponse(false, 'No account found with this email address.', 'error');
        }
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
        if ($isAjax) {
            sendResponse(false, 'Failed to process request. Please try again.', 'error');
        }
        header("Location: ../public/forgot_password.php?error=send_failed&email=" . urlencode($email));
        exit();
    }
    $stmt->close();

    // Create reset link for wastetrack.me
    $reset_link = SITE_URL . "/reset_password.php?token=" . $token;

    // Prepare email
    $subject = "Password Reset Request - WasteTrack";
    $message_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f8f9fc; padding: 30px; border: 1px solid #e3e6f0; }
            .button { display: inline-block; padding: 12px 30px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #858796; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Password Reset Request</h1>
            </div>
            <div class='content'>
                <p>Hai " . htmlspecialchars($user['name']) . ",</p>
                <p>Kami terima permintaan untuk reset password akaun WasteTrack anda.</p>
                <p>Klik butang di bawah untuk reset password:</p>
                <div style='text-align: center;'>
                    <a href='" . $reset_link . "' class='button' style='color: white;'>Reset Password</a>
                </div>
                <p>Atau copy dan paste link ini dalam browser:</p>
                <p style='word-break: break-all; background-color: #fff; padding: 10px; border: 1px solid #ddd;'>" . $reset_link . "</p>
                <p><strong>⏰ Link ini akan tamat dalam 1 jam.</strong></p>
                <p>Jika anda tidak meminta reset password, sila abaikan email ini.</p>
            </div>
            <div class='footer'>
                <p>Email automatik dari WasteTrack System. Jangan reply email ini.</p>
                <p>© " . date('Y') . " WasteTrack - wastetrack.me</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $sent = false;
    logDebug("Attempting to send password reset email to: $email");
    logDebug("Reset link: $reset_link");

    // Try to send email using PHPMailer if available
    if ($usePhpMailer) {
        try {
            $mail = new PHPMailer(true);

            // Server settings from config
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            // Recipients
            $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
            $mail->addAddress($email, $user['name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message_body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message_body));

            $mail->send();
            $sent = true;
            logDebug("Email sent successfully using PHPMailer");
        } catch (Exception $e) {
            // Log error but don't expose details to user
            logDebug("PHPMailer failed: " . $mail->ErrorInfo);
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    }

    // Fallback to PHP mail() function if PHPMailer fails or not available
    if (!$sent) {
        logDebug("Trying PHP mail() function as fallback");
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM_ADDRESS . ">" . "\r\n";

        if (@mail($email, $subject, $message_body, $headers)) {
            $sent = true;
            logDebug("Email sent successfully using PHP mail()");
        } else {
            logDebug("PHP mail() function failed");
        }
    }

    // Log the result
    if ($sent) {
        logDebug("Password reset email sent successfully to: $email");
    } else {
        logDebug("Failed to send password reset email to: $email");
        // For testing - show the reset link in console/log
        logDebug("TEST MODE - Reset Link: $reset_link");
    }

    // Always show success message to prevent email enumeration
    // Even if email send fails, we don't want to reveal if email exists
    if ($isAjax) {
        sendResponse(true, 'Password reset link has been sent to your email. Please check your inbox.', 'success');
    }
    header("Location: ../public/forgot_password.php?message=email_sent");
    exit();

} else {
    header("Location: ../public/forgot_password.php");
    exit();
}
?>