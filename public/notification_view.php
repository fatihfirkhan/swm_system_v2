<?php
// Define constant for includes
define('INCLUDED', true);

session_start();
require_once '../includes/db.php';

// Check if user is logged in (any role)
$userRole = strtolower($_SESSION['role'] ?? '');
$isLoggedIn = false;
$userName = '';
$currentUserId = null;

// Determine user type and validate login
if ($userRole === 'admin' && isset($_SESSION['work_id'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['name'] ?? 'Administrator';
    $templateFile = '../includes/admin_template.php';
    $currentPage = 'notification';
    $currentUserId = $_SESSION['work_id'];
} elseif ($userRole === 'staff' && isset($_SESSION['work_id'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['name'] ?? 'Staff';
    $templateFile = '../includes/staff/staff_template.php';
    $currentPage = 'notifications';
    $currentUserId = $_SESSION['work_id'];
} elseif ($userRole === 'resident' && isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['name'] ?? 'Resident';
    $templateFile = null; // Resident uses custom layout
    $currentPage = 'notifications';
    $currentUserId = $_SESSION['user_id'];
} else {
    // Not logged in - redirect to login
    header('Location: login.php');
    exit();
}

$pageTitle = 'Notifications - SWM Environment';

// Create notification_tracking table if it doesn't exist
// Using unique_user_id which combines role prefix with ID for uniqueness
$conn->query("
    CREATE TABLE IF NOT EXISTS notification_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL UNIQUE,
        last_check TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create a truly unique user identifier by combining role and ID
// This ensures ADM001, STF005, and resident user 1 are all tracked separately
$uniqueUserId = strtoupper(substr($userRole, 0, 3)) . '_' . $currentUserId;

// FIRST: Get the user's last_check timestamp BEFORE updating it
$lastCheckTime = null;
$checkStmt = $conn->prepare("SELECT last_check FROM notification_tracking WHERE user_id = ?");
$checkStmt->bind_param('s', $uniqueUserId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkRow = $checkResult->fetch_assoc()) {
    $lastCheckTime = $checkRow['last_check'];
}
$checkStmt->close();

// NOW: Update or insert the tracking record (marks notifications as read)
if ($lastCheckTime !== null) {
    // Update existing record
    $updateStmt = $conn->prepare("UPDATE notification_tracking SET last_check = NOW() WHERE user_id = ?");
    $updateStmt->bind_param('s', $uniqueUserId);
    $updateStmt->execute();
    $updateStmt->close();
} else {
    // Insert new record (first time viewing)
    $insertStmt = $conn->prepare("INSERT INTO notification_tracking (user_id, last_check) VALUES (?, NOW())");
    $insertStmt->bind_param('s', $uniqueUserId);
    $insertStmt->execute();
    $insertStmt->close();
    // For first-time viewers, all notifications are "new"
    $lastCheckTime = '2000-01-01 00:00:00';
}

// Fetch notifications for user's role
// Get notifications where target_role matches user's role OR target_role is 'All'
$roleForQuery = ucfirst($userRole); // Convert to 'Admin', 'Staff', 'Resident'

$stmt = $conn->prepare("
    SELECT title, message, target_role, time_created 
    FROM notifications 
    WHERE target_role = ? OR target_role = 'All'
    ORDER BY time_created DESC
");
$stmt->bind_param('s', $roleForQuery);
$stmt->execute();
$notifications = $stmt->get_result();

// Additional styles
$additionalStyles = '
<style>
    .notification-card {
        border-left: 4px solid #4e73df;
        transition: transform 0.1s ease-in-out;
    }
    .notification-card:hover {
        transform: translateX(5px);
    }
    .notification-card.notification-all {
        border-left-color: #1cc88a;
    }
    .notification-card.notification-resident {
        border-left-color: #36b9cc;
    }
    .notification-card.notification-staff {
        border-left-color: #f6c23e;
    }
    .notification-card.notification-admin {
        border-left-color: #e74a3b;
    }
    .notification-card.unread {
        background-color: #f8f9fc;
    }
    .notification-time {
        font-size: 0.8rem;
    }
    .notification-title {
        font-weight: 600;
        color: #5a5c69;
    }
    .new-badge {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #e74a3b;
        border-radius: 50%;
        margin-left: 8px;
        animation: pulse 1.5s infinite;
    }
    .badge-new {
        font-size: 0.65rem;
        padding: 3px 6px;
        margin-left: 8px;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
</style>
';

// Additional scripts
$additionalScripts = '';

// For Admin and Staff, use their template system
if ($templateFile) {
    ob_start();
    ?>
    
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
    </div>

    <!-- Notifications List -->
    <div class="row">
        <div class="col-lg-8">
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <?php while ($notification = $notifications->fetch_assoc()): 
                    $targetClass = 'notification-' . strtolower($notification['target_role']);
                    $isUnread = strtotime($notification['time_created']) > strtotime($lastCheckTime);
                    $unreadClass = $isUnread ? ' unread' : '';
                ?>
                    <div class="card shadow-sm mb-3 notification-card <?php echo $targetClass . $unreadClass; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="notification-title mb-0">
                                    <i class="fas fa-bell mr-2 text-primary"></i><?php echo htmlspecialchars($notification['title'] ?? 'System Notification'); ?>
                                    <?php if ($isUnread): ?>
                                        <span class="badge badge-danger badge-new">New</span>
                                    <?php endif; ?>
                                </h6>
                                <span class="badge badge-<?php 
                                    echo $notification['target_role'] === 'All' ? 'success' : 
                                        ($notification['target_role'] === 'Resident' ? 'info' : 
                                        ($notification['target_role'] === 'Staff' ? 'warning' : 'danger')); 
                                ?>">
                                    <?php echo $notification['target_role'] === 'All' ? 'Everyone' : htmlspecialchars($notification['target_role']); ?>
                                </span>
                            </div>
                            <p class="card-text mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                            <small class="notification-time text-muted">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo date('d M Y, h:i A', strtotime($notification['time_created'])); ?>
                            </small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-gray-300 mb-4"></i>
                        <h5 class="text-gray-600">No Notifications</h5>
                        <p class="text-muted mb-0">You don't have any notifications at this time.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle mr-2"></i>About Notifications
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Notifications keep you informed about important updates, announcements, and system changes.
                    </p>
                    <hr>
                    <h6 class="font-weight-bold small">Notification Types:</h6>
                    <ul class="small text-muted mb-0 pl-3">
                        <li class="mb-1"><span class="badge badge-success">Everyone</span> - Sent to all users</li>
                        <li class="mb-1"><span class="badge badge-info">Resident</span> - For residents only</li>
                        <li class="mb-1"><span class="badge badge-warning">Staff</span> - For staff only</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php
    $pageContent = ob_get_clean();
    require_once $templateFile;
} else {
    // Resident uses custom layout (same as resident_dashboard.php structure)
    
    // Get resident info
    $userId = $_SESSION['user_id'];
    $residentQuery = "SELECT name FROM user WHERE user_id = ?";
    $stmtRes = $conn->prepare($residentQuery);
    $stmtRes->bind_param('i', $userId);
    $stmtRes->execute();
    $resident = $stmtRes->get_result()->fetch_assoc();
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
    <?php echo $additionalStyles; ?>
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
                        // Count UNREAD notifications for Resident (user just viewed, so count will be 0)
                        // Since user is on this page, last_check was just updated, so unread count should be 0
                        $notifCountDisplay = 0;
                        ?>
                        <li class="nav-item no-arrow">
                            <a class="nav-link" href="notification_view.php" title="Notifications">
                                <i class="fas fa-bell fa-fw"></i>
                                <?php if ($notifCountDisplay > 0): ?>
                                    <span class="badge badge-danger badge-counter"><?php echo $notifCountDisplay > 3 ? '3+' : $notifCountDisplay; ?></span>
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
                        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
                    </div>

                    <!-- Notifications List -->
                    <div class="row">
                        <div class="col-lg-8">
                            <?php if ($notifications && $notifications->num_rows > 0): ?>
                                <?php while ($notification = $notifications->fetch_assoc()): 
                                    $targetClass = 'notification-' . strtolower($notification['target_role']);
                                    $isUnread = strtotime($notification['time_created']) > strtotime($lastCheckTime);
                                    $unreadClass = $isUnread ? ' unread' : '';
                                ?>
                                    <div class="card shadow-sm mb-3 notification-card <?php echo $targetClass . $unreadClass; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="notification-title mb-0">
                                                    <i class="fas fa-bell mr-2 text-primary"></i><?php echo htmlspecialchars($notification['title'] ?? 'System Notification'); ?>
                                                    <?php if ($isUnread): ?>
                                                        <span class="badge badge-danger badge-new">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <span class="badge badge-<?php 
                                                    echo $notification['target_role'] === 'All' ? 'success' : 'info'; 
                                                ?>">
                                                    <?php echo $notification['target_role'] === 'All' ? 'Everyone' : 'For You'; ?>
                                                </span>
                                            </div>
                                            <p class="card-text mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                            <small class="notification-time text-muted">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($notification['time_created'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="card shadow mb-4">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-bell-slash fa-4x text-gray-300 mb-4"></i>
                                        <h5 class="text-gray-600">No Notifications</h5>
                                        <p class="text-muted mb-0">You don't have any notifications at this time.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info Sidebar -->
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-info-circle mr-2"></i>About Notifications
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-3">
                                        Notifications keep you informed about important updates regarding waste collection schedules, announcements, and system changes.
                                    </p>
                                    <hr>
                                    <h6 class="font-weight-bold small">Stay Updated:</h6>
                                    <ul class="small text-muted mb-0 pl-3">
                                        <li class="mb-1">Check back regularly for new announcements</li>
                                        <li class="mb-1">Important schedule changes will be posted here</li>
                                        <li>Contact support for any questions</li>
                                    </ul>
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

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <script>
    $(document).ready(function() {
        $('.dropdown-toggle').dropdown();
    });
    </script>

</body>
</html>
<?php } ?>
