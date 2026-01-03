<?php
require_once __DIR__ . '/../includes/db.php';

$token = $_GET['token'] ?? '';
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Verify token
$valid_token = false;
$email = '';

if ($token) {
    $stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reset = $result->fetch_assoc();
        $email = $reset['email'];
        
        // Check if token is expired or already used
        if ($reset['used'] == 1) {
            $error = 'token_used';
        } elseif (strtotime($reset['expires_at']) < time()) {
            $error = 'token_expired';
        } else {
            $valid_token = true;
        }
    } else {
        $error = 'invalid_token';
    }
    $stmt->close();
}

$page_title = 'Reset Password - SWM Environment';
require __DIR__ . '/../includes/template_head.php';
require __DIR__ . '/../includes/template_navbar.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="p-5">
                        <div class="text-center">
                            <h1 class="h4 text-gray-900 mb-4">Reset Your Password</h1>
                        </div>

                        <?php if ($message === 'success'): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> Password has been reset successfully! You can now login with your new password.
                            </div>
                            <div class="text-center">
                                <a href="login.php?role=resident" class="btn btn-primary btn-user btn-block">
                                    Go to Login
                                </a>
                            </div>
                        <?php elseif ($error === 'invalid_token'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Invalid reset link. Please request a new password reset.
                            </div>
                            <div class="text-center">
                                <a href="forgot_password.php" class="btn btn-primary btn-user btn-block">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php elseif ($error === 'token_expired'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> This reset link has expired. Please request a new one.
                            </div>
                            <div class="text-center">
                                <a href="forgot_password.php" class="btn btn-primary btn-user btn-block">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php elseif ($error === 'token_used'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> This reset link has already been used.
                            </div>
                            <div class="text-center">
                                <a href="forgot_password.php" class="btn btn-primary btn-user btn-block">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php elseif ($error === 'password_mismatch'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Passwords do not match. Please try again.
                            </div>
                        <?php elseif ($error === 'weak_password'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Password must be at least 6 characters long.
                            </div>
                        <?php endif; ?>

                        <?php if ($valid_token && !$message): ?>
                        <p class="mb-4">Enter your new password below.</p>
                        <form class="user" method="post" action="../backend/handle_reset_password.php" id="resetPasswordForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="form-group">
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control form-control-user" 
                                        placeholder="New Password" id="password" required minlength="6">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" id="togglePassword">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                            </div>

                            <div class="form-group">
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control form-control-user" 
                                        placeholder="Confirm New Password" id="confirm_password" required minlength="6">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" id="toggleConfirmPassword">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="reset_password" class="btn btn-primary btn-user btn-block">
                                Reset Password
                            </button>
                        </form>
                        <?php endif; ?>

                        <hr>
                        <div class="text-center">
                            <a class="small" href="login.php?role=resident">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back button -->
            <div class="text-center">
                <a href="index.php" class="btn btn-light btn-icon-split mb-4">
                    <span class="icon text-gray-600">
                        <i class="fas fa-arrow-left"></i>
                    </span>
                    <span class="text">Back to Home</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', () => {
            const type = password.type === 'password' ? 'text' : 'password';
            password.type = type;
            togglePassword.querySelector('i').classList.toggle('fa-eye');
            togglePassword.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
    const confirmPassword = document.getElementById('confirm_password');
    if (toggleConfirmPassword && confirmPassword) {
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPassword.type === 'password' ? 'text' : 'password';
            confirmPassword.type = type;
            toggleConfirmPassword.querySelector('i').classList.toggle('fa-eye');
            toggleConfirmPassword.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    // Form validation
    const form = document.getElementById('resetPasswordForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirm_password').value;

            if (pwd !== confirmPwd) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (pwd.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }

            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});
</script>

<?php require __DIR__ . '/../includes/template_footer.php'; ?>
