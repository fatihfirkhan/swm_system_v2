<?php
$role = $_GET['role'] ?? ''; // role select
$error = $_GET['error'] ?? ''; // login error

// Display-friendly role name (show "staff" instead of "staff_auto" in the UI)
$displayRole = ($role === 'staff_auto') ? 'staff' : $role;
?>
<?php
// Use shared templates for head/navbar/footer
$page_title = ucfirst($displayRole) . ' Login - SWM Environment';
require __DIR__ . '/../includes/template_head.php';
require __DIR__ . '/../includes/template_navbar.php';
?>

<!-- kotak Resident Login -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="p-5">
                        <div class="text-center">
                            <h1 class="h4 text-gray-900 mb-4"><?php echo ucfirst($displayRole); ?> Login</h1>
                        </div>
                        <form class="user" method="post" action="backend/handle_login.php?role=<?php echo $role; ?>" id="loginForm">
                <?php if ($role === 'resident'): ?>
                    <div class="form-group">
                        <input type="email" name="email" class="form-control form-control-user" placeholder="Enter Email Address" required>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <input type="text" name="work_id" class="form-control form-control-user" placeholder="Enter Work ID" required>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <div class="input-group">
                        <input type="password" name="password" class="form-control form-control-user <?php echo ($error === 'wrong_password') ? 'is-invalid' : ''; ?>" placeholder="Password" id="password" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-primary" id="togglePassword">
                                <i class="fa fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php if ($error === 'wrong_password'): ?>
                <div class="text-danger small mt-1">Wrong password</div>
                <?php elseif ($error === 'user_not_found'): ?>
                <div class="text-danger small mt-1">User not found</div>
                 <?php endif; ?>
                <button type="submit" name="login" class="btn btn-primary btn-user btn-block">
                    Login
                </button>
            </form>
            <hr>
            <div class="text-center">
                <?php if ($role === 'resident'): ?>
                    <a class="small" href="register.php">Create an Account!</a>
                    <div class="mb-2"></div>
                <?php endif; ?>
                <a class="small" href="forgot_password.php?role=<?php echo $role; ?>">Forgot Password?</a>
            </div>
        </div>
    </div>
</div>
<!-- Back button with proper spacing -->
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('togglePassword');
  const pwd = document.getElementById('password');
  if (toggle && pwd) {
    toggle.addEventListener('click', () => {
      const type = pwd.type === 'password' ? 'text' : 'password';
      pwd.type = type;
      toggle.querySelector('i').classList.toggle('fa-eye');
      toggle.querySelector('i').classList.toggle('fa-eye-slash');
    });
  }

  // simple client-side validation feedback
  const form = document.getElementById('loginForm');
  form && form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  }, false);
});
</script>

<?php require __DIR__ . '/../includes/template_footer.php'; ?>
