<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as STAFF only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone']);
    $address_line1 = trim($_POST['address_line1']);
    $address_line2 = trim($_POST['address_line2']);
    $postcode = trim($_POST['postcode']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validation
    $errors = [];
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "Phone number must be 10-11 digits";
    }
    
    if (empty($postcode)) {
        $errors[] = "Postcode is required";
    } elseif (!preg_match('/^[0-9]{5}$/', $postcode)) {
        $errors[] = "Postcode must be 5 digits";
    }
    
    // Password validation (only if user wants to change password)
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    if (empty($errors)) {
        // Update user profile
        if (!empty($new_password)) {
            // Update with password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE user SET phone = ?, address_line1 = ?, address_line2 = ?, postcode = ?, password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssi', $phone, $address_line1, $address_line2, $postcode, $hashed_password, $user_id);
        } else {
            // Update without password
            $sql = "UPDATE user SET phone = ?, address_line1 = ?, address_line2 = ?, postcode = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssi', $phone, $address_line1, $address_line2, $postcode, $user_id);
        }
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $stmt->close();
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Fetch current user data
$sql = "SELECT * FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = 'Staff Profile';
$currentPage = 'profile';

// Start output buffering for page content
ob_start();
?>

<style>
/* Custom teal theme for staff */
.bg-swm-dark {
    background: linear-gradient(180deg, #17a2b8 10%, #138496 100%) !important;
    background-color: #17a2b8 !important;
}

.text-swm-dark {
    color: #17a2b8 !important;
}

.badge-swm-dark {
    background-color: #17a2b8 !important;
    color: white !important;
}

.btn-swm-dark {
    background-color: #17a2b8 !important;
    border-color: #17a2b8 !important;
    color: white !important;
}

.btn-swm-dark:hover,
.btn-swm-dark:focus,
.btn-swm-dark:active {
    background-color: #138496 !important;
    border-color: #117a8b !important;
    color: white !important;
}

.border-left-swm-dark {
    border-left: 0.25rem solid #17a2b8 !important;
}
</style>

<!-- Begin Page Content -->
<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-tie text-swm-dark"></i> Staff Profile
        </h1>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Account Information Card -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-swm-dark">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-id-card"></i> Account Information
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-circle fa-5x text-swm-dark"></i>
                    </div>
                    <h5 class="font-weight-bold"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted mb-1">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    <p class="text-muted mb-3">
                        <i class="fas fa-id-badge"></i> Work ID: <?php echo htmlspecialchars($user['work_id']); ?>
                    </p>
                    <span class="badge badge-swm-dark badge-pill px-3 py-2">
                        <i class="fas fa-user-tie"></i> Staff Member
                    </span>
                    
                    <hr class="my-4">
                    
                    <div class="text-left">
                        <p class="mb-2"><strong><i class="fas fa-phone text-swm-dark"></i> Phone:</strong></p>
                        <p class="text-muted ml-4"><?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></p>
                        
                        <p class="mb-2"><strong><i class="fas fa-map-marker-alt text-swm-dark"></i> Address:</strong></p>
                        <p class="text-muted ml-4">
                            <?php 
                            if ($user['address_line1'] || $user['address_line2']) {
                                echo htmlspecialchars($user['address_line1']) . '<br>' . 
                                     htmlspecialchars($user['address_line2']) . '<br>' .
                                     htmlspecialchars($user['postcode']);
                            } else {
                                echo 'Not set';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-swm-dark">
                    <h6 class="m-0 font-weight-bold text-white">
                        <i class="fas fa-edit"></i> Edit Profile
                    </h6>
                </div>
                <div class="card-body">
                    <form id="profileForm" method="POST" action="">
                        
                        <!-- Name (Read-only) -->
                        <div class="form-group">
                            <label for="name" class="font-weight-bold">
                                <i class="fas fa-user text-swm-dark"></i> Full Name
                            </label>
                            <input type="text" class="form-control bg-light" id="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Name cannot be changed. Contact system administrator if needed.
                            </small>
                        </div>

                        <!-- Work ID (Read-only) -->
                        <div class="form-group">
                            <label for="work_id" class="font-weight-bold">
                                <i class="fas fa-id-badge text-swm-dark"></i> Work ID
                            </label>
                            <input type="text" class="form-control bg-light" id="work_id" 
                                   value="<?php echo htmlspecialchars($user['work_id']); ?>" readonly>
                        </div>

                        <hr class="my-4">
                        <h5 class="text-swm-dark mb-3">
                            <i class="fas fa-edit"></i> Editable Information
                        </h5>

                        <!-- Phone -->
                        <div class="form-group">
                            <label for="phone" class="font-weight-bold">
                                <i class="fas fa-phone text-swm-dark"></i> Phone Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                   placeholder="0123456789" required>
                            <small class="form-text text-muted">10-11 digits only</small>
                        </div>

                        <!-- Address Line 1 -->
                        <div class="form-group">
                            <label for="address_line1" class="font-weight-bold">
                                <i class="fas fa-home text-swm-dark"></i> Address Line 1
                            </label>
                            <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                   value="<?php echo htmlspecialchars($user['address_line1']); ?>" 
                                   placeholder="Street address, building name">
                        </div>

                        <!-- Address Line 2 -->
                        <div class="form-group">
                            <label for="address_line2" class="font-weight-bold">
                                <i class="fas fa-map-marked-alt text-swm-dark"></i> Address Line 2
                            </label>
                            <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                   value="<?php echo htmlspecialchars($user['address_line2']); ?>" 
                                   placeholder="City, State">
                        </div>

                        <!-- Postcode -->
                        <div class="form-group">
                            <label for="postcode" class="font-weight-bold">
                                <i class="fas fa-map-pin text-swm-dark"></i> Postcode <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="postcode" name="postcode" 
                                   value="<?php echo htmlspecialchars($user['postcode']); ?>" 
                                   placeholder="12345" required maxlength="5">
                            <small class="form-text text-muted">5 digits</small>
                        </div>

                        <hr class="my-4">
                        <h5 class="text-swm-dark mb-3">
                            <i class="fas fa-key"></i> Change Password (Optional)
                        </h5>

                        <!-- New Password -->
                        <div class="form-group">
                            <label for="new_password" class="font-weight-bold">
                                <i class="fas fa-lock text-swm-dark"></i> New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Leave blank to keep current password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Minimum 6 characters</small>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label for="confirm_password" class="font-weight-bold">
                                <i class="fas fa-lock text-swm-dark"></i> Confirm New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm your new password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small id="passwordMatchMessage" class="form-text"></small>
                        </div>

                        <hr class="my-4">

                        <!-- Submit Button -->
                        <div class="text-right">
                            <button type="button" class="btn btn-secondary mr-2" onclick="window.location.reload()">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button type="button" class="btn btn-swm-dark" data-toggle="modal" data-target="#confirmModal">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /.container-fluid -->

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-swm-dark text-white">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="fas fa-question-circle"></i> Confirm Changes
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><i class="fas fa-exclamation-triangle text-warning"></i> Are you sure you want to save these changes to your profile?</p>
                <p class="text-muted mb-0">This action will update your contact information and password (if changed).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-swm-dark" onclick="document.getElementById('profileForm').submit()">
                    <i class="fas fa-check"></i> Yes, Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle password visibility
    $('#togglePassword').click(function() {
        const passwordField = $('#new_password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    $('#toggleConfirmPassword').click(function() {
        const passwordField = $('#confirm_password');
        const icon = $(this).find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Check password match
    $('#confirm_password, #new_password').on('keyup', function() {
        const newPass = $('#new_password').val();
        const confirmPass = $('#confirm_password').val();
        const message = $('#passwordMatchMessage');
        
        if (newPass === '' && confirmPass === '') {
            message.text('').removeClass('text-success text-danger');
        } else if (newPass === confirmPass) {
            message.text('✓ Passwords match').removeClass('text-danger').addClass('text-success');
        } else {
            message.text('✗ Passwords do not match').removeClass('text-success').addClass('text-danger');
        }
    });
});
</script>

<?php
// Capture output buffer and load template
$pageContent = ob_get_clean();
require_once '../includes/staff/staff_template.php';
?>
