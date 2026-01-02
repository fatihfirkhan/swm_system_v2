<?php
// Define constant for includes
define('INCLUDED', true);

session_start();
require_once '../includes/db.php';

// Check if user is logged in as resident
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'resident') {
    header('Location: login.php?role=resident');
    exit();
}

$pageTitle = 'My Profile - SWM Environment';
$currentPage = 'profile';

// Get user_id from session
$user_id = $_SESSION['user_id'];

// Fetch current user data
$query = "SELECT u.user_id, u.name, u.email, u.phone, u.house_unit_number, 
                 u.area_id, u.lane_id, u.address_line1, u.postcode,
                 ca.taman_name, cl.lane_name
          FROM user u
          LEFT JOIN collection_area ca ON u.area_id = ca.area_id
          LEFT JOIN collection_lane cl ON u.lane_id = cl.lane_id
          WHERE u.user_id = ? AND u.role = 'resident'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found or access denied.");
}

$user = $result->fetch_assoc();

// Fetch resident info for topbar
$residentQuery = "SELECT u.*, ca.taman_name 
                  FROM user u 
                  LEFT JOIN collection_area ca ON u.area_id = ca.area_id 
                  WHERE u.user_id = ?";
$stmt2 = $conn->prepare($residentQuery);
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$resident = $stmt2->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $house_unit_number = trim($_POST['house_unit_number'] ?? '');
    $area_id = intval($_POST['area_id'] ?? 0);
    $lane_id = intval($_POST['lane_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($house_unit_number)) {
        $errors[] = 'Please fill in all required fields.';
    }
    
    // Validate area and lane
    if ($area_id <= 0) {
        $errors[] = 'Please select a valid area.';
    }
    if ($lane_id <= 0) {
        $errors[] = 'Please select a valid lane.';
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Check if email is already taken by another user
    $check_email = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
    $check_email->bind_param("si", $email, $user_id);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        $errors[] = 'This email is already registered to another account.';
    }
    
    // Validate password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        // Fetch area and lane names for address construction
        $addr_query = $conn->prepare("SELECT ca.taman_name, cl.lane_name 
                                       FROM collection_area ca, collection_lane cl 
                                       WHERE ca.area_id = ? AND cl.lane_id = ?");
        $addr_query->bind_param('ii', $area_id, $lane_id);
        $addr_query->execute();
        $addr_result = $addr_query->get_result()->fetch_assoc();
        
        if ($addr_result) {
            // Update address_line1 with formatted address
            $address_line1 = $house_unit_number . ', ' . $addr_result['lane_name'] . ', ' . $addr_result['taman_name'];
            
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE user SET name = ?, email = ?, phone = ?, 
                                house_unit_number = ?, area_id = ?, lane_id = ?, 
                                address_line1 = ?, password = ? 
                                WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssiissi", $name, $email, $phone, $house_unit_number, 
                                         $area_id, $lane_id, $address_line1, $hashed_password, $user_id);
            } else {
                // Update without changing password
                $update_query = "UPDATE user SET name = ?, email = ?, phone = ?, 
                                house_unit_number = ?, area_id = ?, lane_id = ?, address_line1 = ? 
                                WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssiisi", $name, $email, $phone, $house_unit_number, 
                                         $area_id, $lane_id, $address_line1, $user_id);
            }
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = 'Profile updated successfully!';
                // Refresh user data
                header("Location: resident_profile.php");
                exit();
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        } else {
            $errors[] = 'Invalid area or lane selection.';
        }
    }
    
    // Store errors in session for display
    if (!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
    }
}


// Fetch all areas for dropdown
$areas_query = $conn->query("SELECT area_id, taman_name FROM collection_area ORDER BY taman_name");

// Fetch all lanes for dropdown
$lanes_query = $conn->query("SELECT lane_id, lane_name, area_id FROM collection_lane ORDER BY lane_name");

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/swm-custom.css" rel="stylesheet">
    
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .profile-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .form-section {
            background: #f8f9fc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e3e6f0;
        }
        .info-text {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body id="page-top" class="resident-page">

    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include '../includes/resident_sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Centered Logo -->
                    <div class="topbar-logo-container">
                        <img src="../assets/swme logo.png" alt="SWM Logo">
                        <span class="logo-text">SWM ENVIRONMENT</span>
                    </div>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - Notifications -->
                        <?php
                        // Count UNREAD notifications for Resident using tracking system
                        $notifCount = 0;
                        
                        try {
                            // Get user's last check time from notification_tracking
                            $uniqueUserId = 'RES_' . $user_id;
                            $lastCheckTime = '2000-01-01 00:00:00'; // Default for new users
                            
                            $trackingCheck = $conn->prepare("SELECT last_check FROM notification_tracking WHERE user_id = ?");
                            if ($trackingCheck) {
                                $trackingCheck->bind_param('s', $uniqueUserId);
                                $trackingCheck->execute();
                                $trackingResult = $trackingCheck->get_result();
                                if ($trackingRow = $trackingResult->fetch_assoc()) {
                                    $lastCheckTime = $trackingRow['last_check'];
                                }
                                $trackingCheck->close();
                            }
                            
                            // Count notifications newer than last check
                            $notifSql = "SELECT COUNT(*) as unread_count 
                                         FROM notifications 
                                         WHERE target_role IN ('Resident', 'All')
                                         AND time_created > ?";
                            
                            $notifStmt = $conn->prepare($notifSql);
                            if ($notifStmt) {
                                $notifStmt->bind_param('s', $lastCheckTime);
                                $notifStmt->execute();
                                $notifResult = $notifStmt->get_result();
                                
                                if ($notifRow = $notifResult->fetch_assoc()) {
                                    $notifCount = $notifRow['unread_count'];
                                }
                                $notifStmt->close();
                            }
                        } catch (Exception $e) {
                            // Silently fail - just show 0 notifications
                            $notifCount = 0;
                        }
                        ?>

                        <li class="nav-item no-arrow">
                            <a class="nav-link" href="notification_view.php" title="Notifications">
                                <i class="fas fa-bell fa-fw"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span class="badge badge-danger badge-counter"><?php echo $notifCount > 3 ? '3+' : $notifCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($resident['name']); ?></span>
                                <i class="fas fa-user-circle fa-fw"></i>
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="resident_profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Manage My Profile</h1>
                    </div>

                    <!-- Success Message -->
                    <?php
                    // Get success/error messages
                    $success_message = $_SESSION['success_message'] ?? '';
                    $error_messages = $_SESSION['error_messages'] ?? [];
                    unset($_SESSION['success_message'], $_SESSION['error_messages']);
                    
                    if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Error Messages -->
                    <?php if (!empty($error_messages)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <ul class="mb-0">
                                <?php foreach ($error_messages as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="profile-icon">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="col">
                <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                <p class="mb-0" style="opacity: 0.85;">
                    <i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($user['email']) ?>
                </p>
                <p class="mb-0" style="opacity: 0.85;">
                    <i class="fas fa-phone mr-2"></i><?= htmlspecialchars($user['phone']) ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Profile Edit Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit mr-2"></i>Edit Profile Information
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="profileForm">
                        <!-- Hidden field to ensure form submission is detected -->
                        <input type="hidden" name="update_profile" value="1">
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-user-circle mr-2"></i>Personal Information
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-map-marker-alt mr-2"></i>Address Information
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">House/Unit Number <span class="text-danger">*</span></label>
                                <input type="text" name="house_unit_number" class="form-control" 
                                       value="<?= htmlspecialchars($user['house_unit_number']) ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Area (Taman) <span class="text-danger">*</span></label>
                                    <select name="area_id" id="area_id" class="form-control" required>
                                        <option value="">-- Select Area --</option>
                                        <?php 
                                        $areas_query->data_seek(0); // Reset pointer
                                        while ($area = $areas_query->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $area['area_id'] ?>" <?= $area['area_id'] == $user['area_id'] ? 'selected' : '' ?>><?= htmlspecialchars($area['taman_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <small class="text-muted">Select your residential area</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lane (Jalan) <span class="text-danger">*</span></label>
                                    <select name="lane_id" id="lane_id" class="form-control" required>
                                        <option value="">-- Select Lane --</option>
                                        <!-- Options loaded dynamically based on area -->
                                    </select>
                                    <small class="text-muted">Select your street/lane</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Address (Auto-generated)</label>
                                <textarea id="full_address" class="form-control" rows="2" readonly><?= htmlspecialchars(trim($user['address_line1'])) ?></textarea>
                                <small class="text-muted">This will update automatically when you change house number, area, or lane</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Postcode</label>
                                <input type="text" class="form-control" 
                                       value="<?= htmlspecialchars($user['postcode']) ?>" readonly>
                            </div>
                        </div>

                        <!-- Password Change Section -->
                        <div class="form-section">
                            <div class="section-title">
                                <i class="fas fa-lock mr-2"></i>Change Password (Optional)
                            </div>
                            
                            <div class="info-text mb-3">
                                <i class="fas fa-info-circle mr-2"></i>
                                Leave the password fields blank if you don't want to change your password.
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="new_password" 
                                       class="form-control" minlength="6" 
                                       placeholder="Enter new password (min. 6 characters)">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                       class="form-control" minlength="6" 
                                       placeholder="Confirm new password">
                                <div id="password-error" class="text-danger small mt-1" style="display: none;">
                                    Passwords do not match!
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-end">
                            <a href="resident_dashboard.php" class="btn btn-secondary mr-2">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile Info Card -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Account Type</small>
                        <span class="badge badge-success">Resident</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">User ID</small>
                        <strong>#<?= $user['user_id'] ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Area</small>
                        <strong><?= htmlspecialchars($user['taman_name']) ?></strong>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        You can change your area and lane assignment if you move to a different location within the service area.
                    </small>
                </div>
            </div>

            <!-- Quick Links Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                </div>
                <div class="card-body">
                    <a href="resident_dashboard.php" class="btn btn-outline-primary btn-sm btn-block mb-2">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>
                    <a href="resident_schedule.php" class="btn btn-outline-primary btn-sm btn-block mb-2">
                        <i class="fas fa-calendar mr-2"></i>My Schedule
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm btn-block">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php include '../includes/template_footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Confirm Save Modal-->
    <div class="modal fade" id="confirmSaveModal" tabindex="-1" role="dialog" aria-labelledby="confirmSaveLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="confirmSaveLabel">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Profile Update
                    </h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">Are you sure you want to save these changes to your profile?</p>
                    <div id="passwordWarning" class="alert alert-info" style="display:none;">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Note:</strong> Your password will also be updated.
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button class="btn btn-primary" type="button" id="confirmSaveBtn">
                        <i class="fas fa-check mr-2"></i>Yes, Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- JavaScript for password validation -->
    <script>
    // Prepare lanes data for JavaScript
    const lanesData = <?php 
        $lanes_array = [];
        $lanes_query->data_seek(0);
        while ($lane = $lanes_query->fetch_assoc()) {
            $lanes_array[] = [
                'lane_id' => $lane['lane_id'],
                'lane_name' => $lane['lane_name'],
                'area_id' => $lane['area_id']
            ];
        }
        echo json_encode($lanes_array);
    ?>;
    
    const currentLaneId = <?= $user['lane_id'] ?>;
    
    $(document).ready(function() {
        // Function to load lanes based on selected area
        function loadLanes(areaId, selectLaneId = null) {
            const laneSelect = $('#lane_id');
            laneSelect.empty();
            laneSelect.append('<option value="">-- Select Lane --</option>');
            
            // Filter lanes by area
            const filteredLanes = lanesData.filter(lane => lane.area_id == areaId);
            
            filteredLanes.forEach(lane => {
                const selected = selectLaneId && lane.lane_id == selectLaneId ? 'selected' : '';
                laneSelect.append(`<option value="${lane.lane_id}" ${selected}>${lane.lane_name}</option>`);
            });
            
            updateFullAddress();
        }
        
        // Function to update full address preview
        function updateFullAddress() {
            const houseNumber = $('input[name="house_unit_number"]').val().trim();
            const areaId = $('#area_id').val();
            const laneId = $('#lane_id').val();
            
            if (!houseNumber || !areaId || !laneId) {
                return;
            }
            
            const areaName = $('#area_id option:selected').text().trim();
            const laneName = $('#lane_id option:selected').text().trim();
            
            const fullAddress = `${houseNumber}, ${laneName}, ${areaName}`;
            $('#full_address').val(fullAddress);
        }
        
        // Load lanes for current area on page load
        const currentAreaId = $('#area_id').val();
        if (currentAreaId) {
            loadLanes(currentAreaId, currentLaneId);
        }
        
        // Area change event
        $('#area_id').on('change', function() {
            const selectedAreaId = $(this).val();
            if (selectedAreaId) {
                loadLanes(selectedAreaId);
            } else {
                $('#lane_id').empty().append('<option value="">-- Select Lane --</option>');
                $('#full_address').val('');
            }
        });
        
        // Update address when house number, area, or lane changes
        $('input[name="house_unit_number"], #area_id, #lane_id').on('change keyup', function() {
            updateFullAddress();
        });
        
        // Real-time password matching validation
        $('#confirm_password').on('keyup', function() {
            var newPassword = $('#new_password').val();
            var confirmPassword = $(this).val();
            
            // Only validate if user has entered something in new password field
            if (newPassword.length > 0 && confirmPassword.length > 0) {
                if (newPassword !== confirmPassword) {
                    $('#password-error').show();
                    $(this).addClass('border-danger');
                } else {
                    $('#password-error').hide();
                    $(this).removeClass('border-danger');
                }
            } else {
                $('#password-error').hide();
                $(this).removeClass('border-danger');
            }
        });

        // Form submission validation with confirmation
        var formSubmitting = false;
        
        $('#profileForm').submit(function(e) {
            // If already confirmed, allow submission
            if (formSubmitting) {
                return true;
            }
            
            e.preventDefault();
            
            var newPassword = $('#new_password').val();
            var confirmPassword = $('#confirm_password').val();
            
            // Only validate if user entered a new password
            if (newPassword.length > 0) {
                if (newPassword !== confirmPassword) {
                    $('#password-error').show();
                    $('#new_password').addClass('border-danger');
                    $('#confirm_password').addClass('border-danger');
                    alert('Passwords do not match! Please check and try again.');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    alert('Password must be at least 6 characters long.');
                    return false;
                }
            }
            
            // Show confirmation modal
            if (newPassword.length > 0) {
                $('#passwordWarning').show();
            } else {
                $('#passwordWarning').hide();
            }
            
            $('#confirmSaveModal').modal('show');
            return false;
        });
        
        // Handle modal confirmation - use native DOM submit to bypass jQuery event handler
        $('#confirmSaveBtn').click(function() {
            formSubmitting = true;
            $('#confirmSaveModal').modal('hide');
            // Use native DOM submit method to bypass the jQuery submit handler
            document.getElementById('profileForm').submit();
        });

        // Clear error styling when user starts typing in new password
        $('#new_password').on('keyup', function() {
            if ($(this).val().length > 0) {
                $(this).removeClass('border-danger');
            }
        });
    });
    </script>

</body>
</html>
<?php
ob_end_flush();
?>
