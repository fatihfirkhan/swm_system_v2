<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Staff Registration - SWM Environment';
$currentPage = 'staff';

// Get status messages
$success = $_GET['success'] ?? '';
$work_id = $_GET['work_id'] ?? '';
$error = $_GET['error'] ?? '';
$old = $_SESSION['old'] ?? [];

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Content -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Staff Registration</h1>
</div>

<!-- Status Messages -->
<div class="mb-4">
    <?php if ($success == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Staff registered successfully! Assigned Work ID: <strong><?= $work_id ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error == 'incomplete'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Please fill in all required fields.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error == 'duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            This staff already exists (work ID or name conflict).
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error == 'password_mismatch'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Passwords do not match. Please try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Registration Form -->
<div class="row">
    <div class="col-xl-8 col-lg-10">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">New Staff Details</h6>
            </div>
            <div class="card-body">
                <form method="post" action="../backend/handle_staffregister.php">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="staff" <?= ($old['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Waste Collection Staff</option>
                                <option value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= $old['name'] ?? '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= $old['phone'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" name="address_line1" class="form-control" value="<?= $old['address_line1'] ?? '' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" name="address_line2" class="form-control" value="<?= $old['address_line2'] ?? '' ?>">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Postcode</label>
                            <input type="text" name="postcode" class="form-control" value="<?= $old['postcode'] ?? '' ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div id="password-error" class="text-danger small mt-1" style="display: none;">Passwords do not match!</div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="adminschedule.php" class="btn btn-secondary me-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" name="register" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Clear old form data
unset($_SESSION['old']);

// Get the buffered content
$pageContent = ob_get_clean();

// Additional scripts for password validation
$additionalScripts = '
<script>
$(document).ready(function() {
    // Form validation for password matching
    $("form").submit(function(e) {
        var password = $("#password").val();
        var confirmPassword = $("#confirm_password").val();
        
        if (password !== confirmPassword) {
            e.preventDefault();
            $("#password-error").show();
            $("#password").addClass("border-danger");
            $("#confirm_password").addClass("border-danger");
            alert("Passwords do not match! Please check and try again.");
            return false;
        } else {
            $("#password-error").hide();
            $("#password").removeClass("border-danger");
            $("#confirm_password").removeClass("border-danger");
        }
    });

    // Real-time password matching feedback
    $("#confirm_password").on("keyup", function() {
        var password = $("#password").val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword.length > 0) {
            if (password !== confirmPassword) {
                $("#password-error").show();
                $(this).addClass("border-danger");
            } else {
                $("#password-error").hide();
                $(this).removeClass("border-danger");
            }
        }
    });
});
</script>
';

// Render the admin template with our variables
require_once '../includes/admin_template.php';
?>
