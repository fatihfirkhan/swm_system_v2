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

$pageTitle = 'Resident Dashboard - SWM Environment';
$currentPage = 'dashboard';

// Get resident information
$userId = $_SESSION['user_id'];
$residentQuery = "SELECT u.*, ca.taman_name, cl.lane_name 
                  FROM user u 
                  LEFT JOIN collection_area ca ON u.area_id = ca.area_id 
                  LEFT JOIN collection_lane cl ON u.lane_id = cl.lane_id 
                  WHERE u.user_id = ?";
$stmt = $conn->prepare($residentQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resident = $stmt->get_result()->fetch_assoc();

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
                        // Count UNREAD notifications for Resident
                        $notifCount = 0;
                        $roleForQuery = 'Resident';
                        
                        // Create unique user ID: RES_userid (e.g., RES_5)
                        $uniqueUserId = 'RES_' . ($_SESSION['user_id'] ?? 0);
                        
                        // Get last_check time from notification_tracking using unique user ID
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
                        
                        // Count notifications newer than last_check for Resident
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
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    </div>

                    <!-- Welcome Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Welcome, <?php echo htmlspecialchars($resident['name']); ?>!</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><strong>Your Address:</strong> <?php echo htmlspecialchars($resident['address_line1'] ?? 'Not set'); ?></p>
                                    <p class="mb-2"><strong>Area:</strong> <?php echo htmlspecialchars($resident['taman_name'] ?? 'Not set'); ?></p>
                                    <p class="mb-0"><strong>Lane:</strong> <?php echo htmlspecialchars($resident['lane_name'] ?? 'Not set'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Next Collection Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Next Collection</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">Coming Soon</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- My Area Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                My Area</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">Coming Soon</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-map-marked-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Complaints Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Active Complaints</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">0 Pending</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <a href="resident_schedule.php" class="btn btn-primary btn-block">
                                                <i class="fas fa-calendar-alt"></i> View Collection Schedule
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <a href="resident_complaint.php" class="btn btn-primary btn-block">
                                                <i class="fas fa-exclamation-circle"></i> Report Issue
                                            </a>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <a href="resident_notifications.php" class="btn btn-primary btn-block">
                                                <i class="fas fa-bell"></i> View Notifications
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Information Section -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Important Information</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-check-circle text-primary"></i> Please ensure waste bins are placed outside by 7:00 AM on collection days.</li>
                                        <li class="mb-2"><i class="fas fa-check-circle text-primary"></i> Separate recyclables from general waste for proper disposal.</li>
                                        <li class="mb-2"><i class="fas fa-check-circle text-primary"></i> Report any missed collections within 24 hours.</li>
                                        <li class="mb-0"><i class="fas fa-check-circle text-primary"></i> For urgent issues, contact our hotline: 1-800-SWM-HELP</li>
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
    // Ensure dropdown works
    $(document).ready(function() {
        $('.dropdown-toggle').dropdown();
    });
    </script>

</body>
</html>

<?php
// End output buffering and display the page
ob_end_flush();
?>
