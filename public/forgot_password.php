<?php
$role = $_GET['role'] ?? 'resident';
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<?php
$page_title = 'Forgot Password - SWM Environment';
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
                            <h1 class="h4 text-gray-900 mb-2">Forgot Your Password?</h1>
                            <p class="mb-4">Enter your email address and we'll send you a link to reset your password.</p>
                        </div>

                        <?php if ($message === 'email_sent'): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle"></i> Password reset link has been sent to your email. Please check your inbox.
                            </div>
                        <?php elseif ($error === 'email_not_found'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> No account found with this email address.
                            </div>
                        <?php elseif ($error === 'send_failed'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Failed to send email. Please try again later.
                            </div>
                        <?php elseif ($error === 'invalid_email'): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle"></i> Please enter a valid email address.
                            </div>
                        <?php endif; ?>

                        <form class="user" method="post" action="../backend/handle_forgot_password.php" id="forgotPasswordForm">
                            <div class="form-group">
                                <input type="email" name="email" class="form-control form-control-user" 
                                    placeholder="Enter Email Address" required 
                                    value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                            </div>
                            <button type="submit" name="reset_password" class="btn btn-primary btn-user btn-block">
                                Reset Password
                            </button>
                        </form>
                        <hr>
                        <div class="text-center">
                            <a class="small" href="login.php?role=<?php echo $role; ?>">Back to Login</a>
                        </div>
                        <div class="text-center">
                            <a class="small" href="register.php">Create an Account!</a>
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

<?php require __DIR__ . '/../includes/template_footer.php'; ?>
