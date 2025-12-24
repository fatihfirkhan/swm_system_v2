<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Staff Management - SWM Environment';
$currentPage = 'staff';

// Handle form actions
$success = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'register';

// REGISTER STAFF
if (isset($_POST['register_staff'])) {
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($role) || empty($name) || empty($password)) {
        $error = 'Please fill in all required fields.';
        $activeTab = 'register';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
        $activeTab = 'register';
    } else {
        // Generate sequential work_id based on last existing ID
        $prefix = ($role === 'admin') ? 'ADM' : 'STF';
        
        // Get the highest existing work_id for this prefix
        $query = $conn->prepare("SELECT work_id FROM user WHERE work_id LIKE ? ORDER BY work_id DESC LIMIT 1");
        $likePattern = $prefix . '%';
        $query->bind_param("s", $likePattern);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $lastWorkId = $result->fetch_assoc()['work_id'];
            $lastNumber = (int) substr($lastWorkId, 3); // Extract number after prefix
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        $query->close();
        
        $work_id = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO user (work_id, name, phone, address_line1, address_line2, postcode, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $work_id, $name, $phone, $address_line1, $address_line2, $postcode, $hashed_password, $role);

        if ($stmt->execute()) {
            $success = "Staff registered successfully! Work ID: <strong>$work_id</strong>";
            $activeTab = 'register';
        } else {
            $error = 'Failed to register staff. Please try again.';
            $activeTab = 'register';
        }
        $stmt->close();
    }
}

// UPDATE STAFF
if (isset($_POST['update_staff'])) {
    $staff_id = $_POST['staff_id'] ?? '';
    $name = trim($_POST['edit_name'] ?? '');
    $phone = trim($_POST['edit_phone'] ?? '');
    $address_line1 = trim($_POST['edit_address_line1'] ?? '');
    $address_line2 = trim($_POST['edit_address_line2'] ?? '');
    $postcode = trim($_POST['edit_postcode'] ?? '');
    $new_password = $_POST['edit_password'] ?? '';
    $confirm_password = $_POST['edit_confirm_password'] ?? '';

    if (empty($staff_id) || empty($name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if password needs to be updated
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user SET name = ?, phone = ?, address_line1 = ?, address_line2 = ?, postcode = ?, password = ? WHERE user_id = ?");
            $stmt->bind_param("ssssssi", $name, $phone, $address_line1, $address_line2, $postcode, $hashed_password, $staff_id);
        } else {
            $stmt = $conn->prepare("UPDATE user SET name = ?, phone = ?, address_line1 = ?, address_line2 = ?, postcode = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $name, $phone, $address_line1, $address_line2, $postcode, $staff_id);
        }

        if ($stmt->execute()) {
            $success = 'Staff updated successfully!';
        } else {
            $error = 'Failed to update staff.';
        }
        $stmt->close();
    }
    $activeTab = 'manage';
}

// DELETE STAFF
if (isset($_POST['delete_staff'])) {
    $staff_id = $_POST['delete_staff_id'] ?? '';

    if (!empty($staff_id)) {
        $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ? AND role = 'staff'");
        $stmt->bind_param("i", $staff_id);

        if ($stmt->execute()) {
            $success = 'Staff deleted successfully!';
        } else {
            $error = 'Failed to delete staff.';
        }
        $stmt->close();
    }
    $activeTab = 'manage';
}

// Fetch all staff (role = 'staff' only)
$staffList = [];
$result = $conn->query("SELECT user_id, work_id, name, phone, address_line1, address_line2, postcode, created_at FROM user WHERE role = 'staff' ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staffList[] = $row;
    }
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users-cog mr-2"></i>Staff Management</h1>
</div>

<!-- Status Messages -->
<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Bootstrap Tabs -->
<ul class="nav nav-tabs mb-4" id="staffTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= ($activeTab === 'register') ? 'active' : '' ?>" id="register-tab" data-toggle="tab" href="#register" role="tab">
            <i class="fas fa-user-plus mr-1"></i> Register New Staff
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($activeTab === 'manage') ? 'active' : '' ?>" id="manage-tab" data-toggle="tab" href="#manage" role="tab">
            <i class="fas fa-list mr-1"></i> Manage Staff List
        </a>
    </li>
</ul>

<div class="tab-content" id="staffTabsContent">
    <!-- Tab 1: Register New Staff -->
    <div class="tab-pane fade <?= ($activeTab === 'register') ? 'show active' : '' ?>" id="register" role="tabpanel">
        <div class="row">
            <div class="col-xl-8 col-lg-10">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">New Staff Details</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="registerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold">Role <span class="text-danger">*</span></label>
                                    <select name="role" class="form-control" required>
                                        <option value="">Select Role</option>
                                        <option value="staff">Waste Collection Staff</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="Enter full name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" placeholder="e.g. 012-3456789">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="font-weight-bold">Address Line 1</label>
                                <input type="text" name="address_line1" class="form-control" placeholder="Street address">
                            </div>

                            <div class="mb-3">
                                <label class="font-weight-bold">Address Line 2</label>
                                <input type="text" name="address_line2" class="form-control" placeholder="City / State">
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-bold">Postcode</label>
                                    <input type="text" name="postcode" class="form-control" placeholder="e.g. 50000">
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" required>
                                    <small id="password-error" class="text-danger" style="display: none;">Passwords do not match!</small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset" class="btn btn-secondary mr-2">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                                <button type="submit" name="register_staff" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Register Staff
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Manage Staff List -->
    <div class="tab-pane fade <?= ($activeTab === 'manage') ? 'show active' : '' ?>" id="manage" role="tabpanel">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Staff List</h6>
                <div class="input-group" style="width: 300px;">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search by Name or Work ID...">
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="staffTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Work ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Registered</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffList)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No staff found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffList as $staff): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($staff['work_id']) ?></strong></td>
                                        <td><?= htmlspecialchars($staff['name']) ?></td>
                                        <td><?= htmlspecialchars($staff['phone'] ?? '-') ?></td>
                                        <td><?= date('d M Y', strtotime($staff['created_at'])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-warning btn-edit"
                                                data-id="<?= $staff['user_id'] ?>"
                                                data-name="<?= htmlspecialchars($staff['name']) ?>"
                                                data-phone="<?= htmlspecialchars($staff['phone'] ?? '') ?>"
                                                data-address1="<?= htmlspecialchars($staff['address_line1'] ?? '') ?>"
                                                data-address2="<?= htmlspecialchars($staff['address_line2'] ?? '') ?>"
                                                data-postcode="<?= htmlspecialchars($staff['postcode'] ?? '') ?>"
                                                data-toggle="modal" data-target="#editModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete"
                                                data-id="<?= $staff['user_id'] ?>"
                                                data-name="<?= htmlspecialchars($staff['name']) ?>"
                                                data-toggle="modal" data-target="#deleteModal">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit mr-2"></i>Edit Staff</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="staff_id" id="edit_staff_id">
                    <div class="form-group">
                        <label class="font-weight-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Phone Number</label>
                        <input type="text" name="edit_phone" id="edit_phone" class="form-control" placeholder="e.g. 012-3456789">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Address Line 1</label>
                        <input type="text" name="edit_address_line1" id="edit_address_line1" class="form-control" placeholder="Street address">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Address Line 2</label>
                        <input type="text" name="edit_address_line2" id="edit_address_line2" class="form-control" placeholder="City / State">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Postcode</label>
                        <input type="text" name="edit_postcode" id="edit_postcode" class="form-control" placeholder="e.g. 50000">
                    </div>
                    <hr>
                    <p class="text-muted small"><i class="fas fa-info-circle mr-1"></i>Leave password fields empty if you don't want to change it.</p>
                    <div class="form-group">
                        <label class="font-weight-bold">New Password</label>
                        <input type="password" name="edit_password" id="edit_password" class="form-control" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Confirm New Password</label>
                        <input type="password" name="edit_confirm_password" id="edit_confirm_password" class="form-control" placeholder="Re-enter new password">
                        <small id="edit-password-error" class="text-danger" style="display: none;">Passwords do not match!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_staff" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="delete_staff_id" id="delete_staff_id">
                    <p>Are you sure you want to delete <strong id="delete_staff_name"></strong>?</p>
                    <p class="text-muted small mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_staff" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();

// Additional scripts
$additionalScripts = '
<script>
$(document).ready(function() {
    // Password matching validation
    $("#confirm_password").on("keyup", function() {
        var password = $("#password").val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword.length > 0) {
            if (password !== confirmPassword) {
                $("#password-error").show();
                $(this).addClass("is-invalid");
            } else {
                $("#password-error").hide();
                $(this).removeClass("is-invalid");
            }
        }
    });

    // Form submit validation
    $("#registerForm").on("submit", function(e) {
        var password = $("#password").val();
        var confirmPassword = $("#confirm_password").val();
        
        if (password !== confirmPassword) {
            e.preventDefault();
            $("#password-error").show();
            $("#confirm_password").addClass("is-invalid");
            return false;
        }
    });

    // Search filter for staff table
    $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#staffTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Populate Edit Modal
    $(".btn-edit").on("click", function() {
        var id = $(this).data("id");
        var name = $(this).data("name");
        var phone = $(this).data("phone");
        var address1 = $(this).data("address1");
        var address2 = $(this).data("address2");
        var postcode = $(this).data("postcode");
        
        $("#edit_staff_id").val(id);
        $("#edit_name").val(name);
        $("#edit_phone").val(phone);
        $("#edit_address_line1").val(address1);
        $("#edit_address_line2").val(address2);
        $("#edit_postcode").val(postcode);
        // Clear password fields when opening modal
        $("#edit_password").val("");
        $("#edit_confirm_password").val("");
        $("#edit-password-error").hide();
    });

    // Edit modal password validation
    $("#edit_confirm_password").on("keyup", function() {
        var password = $("#edit_password").val();
        var confirmPassword = $(this).val();
        
        if (password.length > 0 && confirmPassword.length > 0) {
            if (password !== confirmPassword) {
                $("#edit-password-error").show();
                $(this).addClass("is-invalid");
            } else {
                $("#edit-password-error").hide();
                $(this).removeClass("is-invalid");
            }
        } else {
            $("#edit-password-error").hide();
            $(this).removeClass("is-invalid");
        }
    });

    // Populate Delete Modal
    $(".btn-delete").on("click", function() {
        var id = $(this).data("id");
        var name = $(this).data("name");
        
        $("#delete_staff_id").val(id);
        $("#delete_staff_name").text(name);
    });
});
</script>
';

require_once '../includes/admin_template.php';
?>
