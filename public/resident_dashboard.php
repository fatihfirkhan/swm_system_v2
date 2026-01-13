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
$residentQuery = "SELECT u.*, ca.taman_name, cl.lane_name, ca.area_id
                  FROM user u 
                  LEFT JOIN collection_area ca ON u.area_id = ca.area_id 
                  LEFT JOIN collection_lane cl ON u.lane_id = cl.lane_id 
                  WHERE u.user_id = ?";
$stmt = $conn->prepare($residentQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resident = $stmt->get_result()->fetch_assoc();

// ========== DATA FETCHING ==========

// 1. Get NEXT COLLECTION details
$nextCollection = null;
if (isset($resident['area_id']) && $resident['area_id']) {
    $nextCollectionQuery = "SELECT collection_date, collection_type, 
                            CASE 
                                WHEN collection_date = CURDATE() THEN 'Today'
                                WHEN collection_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'Tomorrow'
                                ELSE DAYNAME(collection_date)
                            END as day_label
                            FROM schedule 
                            WHERE area_id = ? AND collection_date >= CURDATE()
                            ORDER BY collection_date ASC 
                            LIMIT 1";
    $nextStmt = $conn->prepare($nextCollectionQuery);
    $nextStmt->bind_param('i', $resident['area_id']);
    $nextStmt->execute();
    $nextCollection = $nextStmt->get_result()->fetch_assoc();
    $nextStmt->close();
}

// 2. Get COMPLAINTS stats
$pendingComplaints = 0;
$totalComplaints = 0;

$complaintQuery = "SELECT 
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
                   FROM complaints 
                   WHERE user_id = ?";
$compStmt = $conn->prepare($complaintQuery);
$compStmt->bind_param('i', $userId);
$compStmt->execute();
$compResult = $compStmt->get_result()->fetch_assoc();
if ($compResult) {
    $totalComplaints = $compResult['total'];
    $pendingComplaints = $compResult['pending'];
}
$compStmt->close();

// 3. Get RECENT COMPLAINTS (last 3)
$recentComplaints = [];
$recentQuery = "SELECT complaint_id, description, status, submission_time
                FROM complaints 
                WHERE user_id = ?
                ORDER BY submission_time DESC
                LIMIT 3";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bind_param('i', $userId);
$recentStmt->execute();
$recentComplaints = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentStmt->close();

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
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/swm-custom.css" rel="stylesheet">

    <style>
        .hero-collection-card {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .hero-collection-card .collection-icon {
            font-size: 4rem;
            opacity: 0.3;
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
        }

        .hero-collection-card .collection-date {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .hero-collection-card .collection-day {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .hero-collection-card .collection-type {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            margin-top: 10px;
        }

        .quick-action-btn {
            padding: 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
                                    <span
                                        class="badge badge-danger badge-counter"><?php echo $notifCount > 3 ? '3+' : $notifCount; ?></span>
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
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-home text-primary mr-2"></i>Resident Dashboard
                        </h1>
                        <small class="text-muted">Welcome back,
                            <?php echo htmlspecialchars($resident['name']); ?>!</small>
                    </div>

                    <!-- ========== HERO SECTION: NEXT COLLECTION ========== -->
                    <div class="row">
                        <div class="col-lg-12">
                            <?php if ($nextCollection): ?>
                                <div class="hero-collection-card shadow position-relative">
                                    <?php
                                    $icon = ($nextCollection['collection_type'] === 'Recycle')
                                        ? 'fa-recycle'
                                        : 'fa-trash-alt';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> collection-icon"></i>
                                    <div class="position-relative" style="z-index: 1;">
                                        <h5 class="text-uppercase mb-3" style="letter-spacing: 2px; font-weight: 600;">
                                            <i class="fas fa-calendar-check mr-2"></i>NEXT COLLECTION
                                        </h5>
                                        <div class="collection-date">
                                            <?php echo date('M d, Y', strtotime($nextCollection['collection_date'])); ?>
                                        </div>
                                        <div class="collection-day">
                                            <?php echo $nextCollection['day_label']; ?>
                                        </div>
                                        <span class="collection-type">
                                            <i class="fas <?php echo $icon; ?> mr-2"></i>
                                            <?php echo htmlspecialchars($nextCollection['collection_type']); ?> Waste
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info shadow">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>No upcoming collections scheduled.</strong> Please check back later or contact
                                    support.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ========== STATS ROW ========== -->
                    <div class="row">
                        <!-- My Area Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                My Area</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo htmlspecialchars($resident['taman_name'] ?? 'Not Assigned'); ?>
                                            </div>
                                            <?php if (isset($resident['lane_name'])): ?>
                                                <small
                                                    class="text-muted"><?php echo htmlspecialchars($resident['lane_name']); ?></small>
                                            <?php endif; ?>
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
                                                My Complaints</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $pendingComplaints; ?> Pending
                                            </div>
                                            <small
                                                class="text-muted"><?php echo $totalComplaints - $pendingComplaints; ?>
                                                Resolved</small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Reports Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Reports Filed</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $totalComplaints; ?>
                                            </div>
                                            <small class="text-muted">All time</small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== QUICK ACTIONS & NOTICE BOARD ========== -->
                    <div class="row">
                        <!-- Quick Actions Card -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-bolt mr-2"></i>Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <a href="resident_complaints.php"
                                                class="btn btn-primary btn-block quick-action-btn">
                                                <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                                                <strong>Lodge a Complaint</strong>
                                                <div class="small">Report waste collection issues</div>
                                            </a>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <a href="resident_schedule.php"
                                                class="btn btn-outline-primary btn-block quick-action-btn">
                                                <i class="fas fa-calendar-alt fa-2x d-block mb-2"></i>
                                                <strong>View Full Schedule</strong>
                                                <div class="small">Check collection calendar</div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notice Board / Community Announcements -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header py-3 bg-primary text-white">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-bullhorn mr-2"></i>Community Announcements
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-2">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Reminder:</strong> Place bins outside by 7:00 AM on collection days.
                                    </div>
                                    <div class="alert alert-success mb-2">
                                        <i class="fas fa-recycle mr-2"></i>
                                        <strong>Recycle Right:</strong> Separate recyclables from general waste.
                                    </div>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-phone mr-2"></i>
                                        <strong>Need Help?</strong> Contact: 1-800-88-7472
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== RECENT ACTIVITY TABLE ========== -->
                    <?php if (count($recentComplaints) > 0): ?>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-history mr-2"></i>Recent Activity
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentComplaints as $comp): ?>
                                                        <tr>
                                                            <td><?php echo date('M d, Y', strtotime($comp['submission_time'])); ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars(substr($comp['description'], 0, 50)) . (strlen($comp['description']) > 50 ? '...' : ''); ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $badgeClass = 'badge-warning';
                                                                if ($comp['status'] === 'Resolved')
                                                                    $badgeClass = 'badge-success';
                                                                elseif ($comp['status'] === 'In Progress')
                                                                    $badgeClass = 'badge-info';
                                                                ?>
                                                                <span class="badge <?php echo $badgeClass; ?>">
                                                                    <?php echo htmlspecialchars($comp['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="resident_complaints.php"
                                                                    class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
        $(document).ready(function () {
            $('.dropdown-toggle').dropdown();
        });
    </script>

</body>

</html>

<?php
// End output buffering and display the page
ob_end_flush();
?>