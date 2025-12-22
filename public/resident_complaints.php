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

$pageTitle = 'Report Issue - SWM Environment';
$currentPage = 'complaint';

// Get resident information
$userId = $_SESSION['user_id'];
$residentQuery = "SELECT u.*, ca.taman_name, ca.area_id 
                  FROM user u 
                  LEFT JOIN collection_area ca ON u.area_id = ca.area_id 
                  WHERE u.user_id = ?";
$stmt = $conn->prepare($residentQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resident = $stmt->get_result()->fetch_assoc();

$successMsg = '';
$errorMsg = '';

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $description = trim($_POST['description'] ?? '');
    $imagePath = null;
    
    if (empty($description)) {
        $errorMsg = 'Please provide a description of your issue.';
    } else {
        // Handle image upload
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/complaints/';
            
            // Create directory if not exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['complaint_image']['type'];
            $fileSize = $_FILES['complaint_image']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errorMsg = 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.';
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
                $errorMsg = 'File too large. Maximum size is 5MB.';
            } else {
                $extension = pathinfo($_FILES['complaint_image']['name'], PATHINFO_EXTENSION);
                $filename = 'complaint_' . $userId . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['complaint_image']['tmp_name'], $targetPath)) {
                    $imagePath = $targetPath;
                } else {
                    $errorMsg = 'Failed to upload image. Please try again.';
                }
            }
        }
        
        if (empty($errorMsg)) {
            // Insert complaint into database
            $insertQuery = "INSERT INTO complaints (user_id, area_id, description, image_url, status, submission_time) 
                           VALUES (?, ?, ?, ?, 'Pending', NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            $areaId = $resident['area_id'];
            $insertStmt->bind_param('iiss', $userId, $areaId, $description, $imagePath);
            
            if ($insertStmt->execute()) {
                $successMsg = 'Your complaint has been submitted successfully. We will review it shortly.';
            } else {
                $errorMsg = 'Failed to submit complaint. Please try again.';
            }
        }
    }
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $complaintId = intval($_POST['complaint_id'] ?? 0);
    $feedback = trim($_POST['resident_feedback'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    
    if ($complaintId > 0 && !empty($feedback) && $rating >= 1 && $rating <= 5) {
        // Verify complaint belongs to this user and is resolved
        $verifyQuery = "SELECT complaint_id FROM complaints WHERE complaint_id = ? AND user_id = ? AND status = 'Resolved'";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param('ii', $complaintId, $userId);
        $verifyStmt->execute();
        
        if ($verifyStmt->get_result()->num_rows > 0) {
            $updateQuery = "UPDATE complaints SET resident_feedback = ?, rating = ? WHERE complaint_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('sii', $feedback, $rating, $complaintId);
            
            if ($updateStmt->execute()) {
                $successMsg = 'Thank you for your feedback and rating!';
            } else {
                $errorMsg = 'Failed to submit feedback. Please try again.';
            }
        }
    } else {
        $errorMsg = 'Please provide both feedback text and a rating (1-5 stars).';
    }
}

// Fetch user's complaints
$complaintsQuery = "SELECT c.*, ca.taman_name 
                    FROM complaints c 
                    LEFT JOIN collection_area ca ON c.area_id = ca.area_id 
                    WHERE c.user_id = ? 
                    ORDER BY c.submission_time DESC";
$complaintsStmt = $conn->prepare($complaintsQuery);
$complaintsStmt->bind_param('i', $userId);
$complaintsStmt->execute();
$complaints = $complaintsStmt->get_result();

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
        .nav-tabs .nav-link {
            font-weight: 600;
            color: #5a5c69;
            border: none;
            padding: 12px 24px;
        }
        .nav-tabs .nav-link.active {
            color: #4e73df;
            border-bottom: 3px solid #4e73df;
            background: transparent;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            color: #4e73df;
            border-color: transparent;
        }
        .tab-content {
            padding-top: 20px;
        }
        .complaint-image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-in-progress {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }
        .complaint-card {
            border-left: 4px solid #e3e6f0;
            transition: all 0.2s ease;
        }
        .complaint-card:hover {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .complaint-card.pending {
            border-left-color: #f6c23e;
        }
        .complaint-card.in-progress {
            border-left-color: #36b9cc;
        }
        .complaint-card.resolved {
            border-left-color: #1cc88a;
        }
        /* Star Rating System */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s ease;
            margin: 0;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        .star-rating-display {
            display: inline-flex;
            gap: 3px;
            font-size: 1.2rem;
        }
        .star-rating-display .star {
            color: #ffc107;
        }
        .star-rating-display .star.empty {
            color: #ddd;
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
                        $notifCount = 0;
                        $roleForQuery = 'Resident';
                        $uniqueUserId = 'RES_' . $userId;
                        
                        $lastCheckTime = '2000-01-01 00:00:00';
                        $trackStmt = $conn->prepare("SELECT last_check FROM notification_tracking WHERE user_id = ?");
                        if ($trackStmt) {
                            $trackStmt->bind_param("s", $uniqueUserId);
                            $trackStmt->execute();
                            $trackResult = $trackStmt->get_result()->fetch_assoc();
                            if ($trackResult) {
                                $lastCheckTime = $trackResult['last_check'];
                            }
                            $trackStmt->close();
                        }
                        
                        $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (target_role = ? OR target_role = 'All') AND time_created > ?");
                        if ($notifStmt) {
                            $notifStmt->bind_param("ss", $roleForQuery, $lastCheckTime);
                            $notifStmt->execute();
                            $notifResult = $notifStmt->get_result()->fetch_assoc();
                            $notifCount = $notifResult['count'] ?? 0;
                            $notifStmt->close();
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
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($resident['name'] ?? 'Resident'); ?>
                                </span>
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
                        <h1 class="h3 mb-0 text-gray-800">Report Issue / Complaint</h1>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if ($successMsg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i><?php echo $successMsg; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Nav Tabs -->
                    <ul class="nav nav-tabs mb-0" id="complaintTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="new-tab" data-toggle="tab" href="#newComplaint" 
                               role="tab" aria-controls="newComplaint" aria-selected="true">
                                <i class="fas fa-plus-circle mr-2"></i>New Complaint
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="history-tab" data-toggle="tab" href="#myHistory" 
                               role="tab" aria-controls="myHistory" aria-selected="false">
                                <i class="fas fa-history mr-2"></i>My History
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="complaintTabsContent">

                        <!-- Tab 1: New Complaint -->
                        <div class="tab-pane fade show active" id="newComplaint" role="tabpanel" aria-labelledby="new-tab">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="card shadow mb-4">
                                        <div class="card-header py-3">
                                            <h6 class="m-0 font-weight-bold text-primary">
                                                <i class="fas fa-edit mr-2"></i>Submit New Complaint
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" enctype="multipart/form-data" id="complaintForm">
                                                <div class="form-group">
                                                    <label for="description" class="font-weight-bold">
                                                        <i class="fas fa-align-left mr-1 text-primary"></i>Description of Issue
                                                    </label>
                                                    <textarea class="form-control" id="description" name="description" 
                                                              rows="5" required
                                                              placeholder="Please describe your issue in detail. Include location, time, and any other relevant information..."></textarea>
                                                    <small class="form-text text-muted">Be as specific as possible to help us resolve your issue quickly.</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="complaint_image" class="font-weight-bold">
                                                        <i class="fas fa-camera mr-1 text-primary"></i>Upload Image (Optional)
                                                    </label>
                                                    <div class="custom-file">
                                                        <input type="file" class="custom-file-input" id="complaint_image" 
                                                               name="complaint_image" accept="image/*">
                                                        <label class="custom-file-label" for="complaint_image">Choose file...</label>
                                                    </div>
                                                    <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF, WEBP. Max size: 5MB</small>
                                                    
                                                    <!-- Image Preview -->
                                                    <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                                        <img id="imagePreview" src="" alt="Preview" class="img-thumbnail" style="max-width: 300px;">
                                                        <button type="button" class="btn btn-sm btn-danger ml-2" id="removeImage">
                                                            <i class="fas fa-times"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="form-group mb-0">
                                                    <button type="submit" name="submit_complaint" class="btn btn-primary btn-lg">
                                                        <i class="fas fa-paper-plane mr-2"></i>Submit Complaint
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="card shadow mb-4 border-left-info">
                                        <div class="card-body">
                                            <h6 class="font-weight-bold text-info mb-3">
                                                <i class="fas fa-info-circle mr-2"></i>Tips for Reporting
                                            </h6>
                                            <ul class="small text-muted mb-0 pl-3">
                                                <li class="mb-2">Describe the issue clearly and specifically</li>
                                                <li class="mb-2">Include the exact location if applicable</li>
                                                <li class="mb-2">Upload a photo if it helps illustrate the problem</li>
                                                <li class="mb-2">Mention the date and time when the issue occurred</li>
                                                <li>Check "My History" tab to track your complaint status</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="card shadow mb-4 border-left-warning">
                                        <div class="card-body">
                                            <h6 class="font-weight-bold text-warning mb-3">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>Response Time
                                            </h6>
                                            <p class="small text-muted mb-0">
                                                We aim to respond to all complaints within <strong>24-48 hours</strong>. 
                                                You will receive updates on your complaint status.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: My History -->
                        <div class="tab-pane fade" id="myHistory" role="tabpanel" aria-labelledby="history-tab">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-list mr-2"></i>My Complaint History
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($complaints->num_rows === 0): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-4x text-gray-300 mb-3"></i>
                                            <p class="text-muted">You haven't submitted any complaints yet.</p>
                                            <a href="#newComplaint" class="btn btn-primary" data-toggle="tab" 
                                               onclick="$('#new-tab').tab('show');">
                                                <i class="fas fa-plus mr-2"></i>Submit Your First Complaint
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="complaintsTable">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Issue</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($complaint = $complaints->fetch_assoc()): ?>
                                                        <?php
                                                        $statusClass = 'pending';
                                                        if ($complaint['status'] === 'In Progress') $statusClass = 'in-progress';
                                                        if ($complaint['status'] === 'Resolved') $statusClass = 'resolved';
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?php echo date('d M Y', strtotime($complaint['submission_time'])); ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?php echo date('h:i A', strtotime($complaint['submission_time'])); ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars(substr($complaint['description'], 0, 50)); ?>
                                                                <?php if (strlen($complaint['description']) > 50) echo '...'; ?>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge status-<?php echo $statusClass; ?>">
                                                                    <?php echo $complaint['status']; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-info view-details-btn"
                                                                        data-id="<?php echo $complaint['complaint_id']; ?>"
                                                                        data-description="<?php echo htmlspecialchars($complaint['description']); ?>"
                                                                        data-status="<?php echo $complaint['status']; ?>"
                                                                        data-image="<?php echo htmlspecialchars($complaint['image_url'] ?? ''); ?>"
                                                                        data-response="<?php echo htmlspecialchars($complaint['admin_response'] ?? ''); ?>"
                                                                        data-feedback="<?php echo htmlspecialchars($complaint['resident_feedback'] ?? ''); ?>"
                                                                        data-rating="<?php echo intval($complaint['rating'] ?? 0); ?>"
                                                                        data-created="<?php echo date('d M Y, h:i A', strtotime($complaint['submission_time'])); ?>"
                                                                        data-resolved="<?php echo $complaint['response_time'] ? date('d M Y, h:i A', strtotime($complaint['response_time'])) : ''; ?>">
                                                                    <i class="fas fa-eye"></i> View
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

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

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="detailsModalLabel">
                        <i class="fas fa-file-alt mr-2"></i>Complaint Details
                    </h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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

    <script>
    $(document).ready(function() {
        // Ensure dropdown works
        $('.dropdown-toggle').dropdown();

        // Custom file input label
        $('#complaint_image').on('change', function() {
            const fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName || 'Choose file...');
            
            // Preview image
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').attr('src', e.target.result);
                    $('#imagePreviewContainer').show();
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Remove image
        $('#removeImage').on('click', function() {
            $('#complaint_image').val('');
            $('#complaint_image').next('.custom-file-label').html('Choose file...');
            $('#imagePreviewContainer').hide();
        });

        // View details modal
        $('.view-details-btn').on('click', function() {
            const id = $(this).data('id');
            const description = $(this).data('description');
            const status = $(this).data('status');
            const image = $(this).data('image');
            const response = $(this).data('response');
            const feedback = $(this).data('feedback');
            const rating = parseInt($(this).data('rating')) || 0;
            const created = $(this).data('created');
            const resolved = $(this).data('resolved');

            let statusClass = 'warning';
            if (status === 'In Progress') statusClass = 'info';
            if (status === 'Resolved') statusClass = 'success';

            let html = '<div class="row">';
            
            // Left column - Issue details
            html += '<div class="col-md-' + (image ? '8' : '12') + '">';
            html += '<p class="mb-2"><strong>Status:</strong> <span class="badge badge-' + statusClass + '">' + status + '</span></p>';
            html += '<p class="mb-2"><strong>Submitted:</strong> ' + created + '</p>';
            if (resolved) {
                html += '<p class="mb-2"><strong>Resolved:</strong> ' + resolved + '</p>';
            }
            html += '<hr>';
            html += '<h6 class="font-weight-bold">Your Issue:</h6>';
            html += '<p class="bg-light p-3 rounded">' + description + '</p>';
            html += '</div>';

            // Right column - Image (if exists)
            if (image) {
                html += '<div class="col-md-4 text-center">';
                html += '<h6 class="font-weight-bold mb-2">Attached Image</h6>';
                html += '<a href="' + image + '" target="_blank">';
                html += '<img src="' + image + '" class="img-fluid rounded shadow-sm" style="max-height: 200px;">';
                html += '</a>';
                html += '<small class="d-block text-muted mt-1">Click to view full size</small>';
                html += '</div>';
            }

            html += '</div>';

            // Admin Response (if resolved or in progress)
            if ((status === 'Resolved' || status === 'In Progress') && response) {
                html += '<hr>';
                if (status === 'Resolved') {
                    html += '<div class="bg-success-light p-3 rounded" style="background: #d4edda;">';
                    html += '<h6 class="font-weight-bold text-success"><i class="fas fa-check-circle mr-2"></i>Admin Response:</h6>';
                    html += '<p class="mb-0">' + response + '</p>';
                    html += '</div>';
                } else if (status === 'In Progress') {
                    html += '<div class="bg-info-light p-3 rounded" style="background: #d1ecf1;">';
                    html += '<h6 class="font-weight-bold text-info"><i class="fas fa-comment mr-2"></i>Admin Response (In Progress):</h6>';
                    html += '<p class="mb-0">' + response + '</p>';
                    html += '</div>';
                }
            }

            // Feedback section (if resolved)
            if (status === 'Resolved') {
                html += '<hr>';
                if (feedback) {
                    html += '<div class="bg-info-light p-3 rounded" style="background: #d1ecf1;">';
                    html += '<h6 class="font-weight-bold text-info"><i class="fas fa-comment mr-2"></i>Your Feedback:</h6>';
                    
                    // Display star rating
                    if (rating > 0) {
                        html += '<div class="star-rating-display mb-2">';
                        for (let i = 1; i <= 5; i++) {
                            html += '<span class="star' + (i <= rating ? '' : ' empty') + '">★</span>';
                        }
                        html += '</div>';
                    }
                    
                    html += '<p class="mb-0">' + feedback + '</p>';
                    html += '</div>';
                } else {
                    html += '<div class="bg-light p-3 rounded">';
                    html += '<h6 class="font-weight-bold text-muted"><i class="fas fa-comment-dots mr-2"></i>Submit Your Feedback</h6>';
                    html += '<form method="POST" class="mb-0" id="feedbackForm">';
                    html += '<input type="hidden" name="complaint_id" value="' + id + '">';
                    
                    // Star Rating Input
                    html += '<label class="font-weight-bold small">Rate our service:</label>';
                    html += '<div class="star-rating">';
                    for (let i = 5; i >= 1; i--) {
                        html += '<input type="radio" id="star' + i + '" name="rating" value="' + i + '" required>';
                        html += '<label for="star' + i + '" title="' + i + ' stars">★</label>';
                    }
                    html += '</div>';
                    
                    html += '<textarea name="resident_feedback" class="form-control mb-2" rows="3" placeholder="How was our response? Let us know..." required></textarea>';
                    html += '<button type="submit" name="submit_feedback" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane mr-1"></i>Submit Feedback</button>';
                    html += '</form>';
                    html += '</div>';
                }
            }

            $('#detailsContent').html(html);
            $('#detailsModal').modal('show');
        });
    });
    </script>

</body>
</html>

<?php
// End output buffering and display the page
ob_end_flush();
?>
