<?php
// Define constant for includes
define('INCLUDED', true);

session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Admin Dashboard - SWM Environment';
$currentPage = 'dashboard';

// Additional styles for charts
$additionalStyles = '';

// Additional scripts for charts
$additionalScripts = '
<script src="vendor/chart.js/Chart.min.js"></script>
<script src="js/dashboard_charts.js?v=' . time() . '"></script>
';

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

    <!-- SWM Environment custom theme -->
    <link href="css/swm-custom.css" rel="stylesheet">
    <?php echo $additionalStyles; ?>
</head>

<body id="page-top">
    <?php

    // ========== DATA FETCHING FOR ADMIN DASHBOARD ==========
    $currentMonth = date('Y-m');

    // 1. Total schedules this month
    $schedulesQuery = "SELECT COUNT(*) as total FROM schedule WHERE DATE_FORMAT(collection_date, '%Y-%m') = ?";
    $stmt = $conn->prepare($schedulesQuery);
    $stmt->bind_param('s', $currentMonth);
    $stmt->execute();
    $scheduleCount = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // 2. Active trucks count
    $truckQuery = "SELECT COUNT(*) as total FROM truck WHERE status = 'active'";
    $activeTrucks = $conn->query($truckQuery)->fetch_assoc()['total'];

    // 3. Collection areas count
    $areasQuery = "SELECT COUNT(DISTINCT area_id) as total FROM collection_area";
    $areaCount = $conn->query($areasQuery)->fetch_assoc()['total'];

    // 4. **FIX: Get REAL Pending Complaints count**
    $pendingComplaintsQuery = "SELECT COUNT(*) as total FROM complaints WHERE status = 'Pending'";
    $pendingComplaints = $conn->query($pendingComplaintsQuery)->fetch_assoc()['total'];

    // 5. Chart Data: Last 4 weeks collection counts
    $chartData = [];
    for ($i = 3; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-$i weeks monday"));
        $weekEnd = date('Y-m-d', strtotime("-$i weeks sunday"));

        $weekQuery = "SELECT COUNT(*) as count FROM schedule WHERE collection_date BETWEEN ? AND ?";
        $weekStmt = $conn->prepare($weekQuery);
        $weekStmt->bind_param('ss', $weekStart, $weekEnd);
        $weekStmt->execute();
        $chartData[] = $weekStmt->get_result()->fetch_assoc()['count'];
        $weekStmt->close();
    }

    // 7. Completion Rate for this month
    $completedQuery = "SELECT COUNT(*) as total FROM schedule WHERE DATE_FORMAT(collection_date, '%Y-%m') = ? AND status = 'Completed'";
    $compStmt = $conn->prepare($completedQuery);
    $compStmt->bind_param('s', $currentMonth);
    $compStmt->execute();
    $completedCount = $compStmt->get_result()->fetch_assoc()['total'];
    $compStmt->close();
    $completionRate = $scheduleCount > 0 ? round(($completedCount / $scheduleCount) * 100) : 0;

    // 6. Recent schedules
    $recentQuery = "SELECT s.*, GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as staff_names, 
                ca.taman_name as area_name, t.truck_number
                FROM schedule s
                LEFT JOIN truck t ON s.truck_id = t.truck_id
                LEFT JOIN truck_staff ts ON t.truck_id = ts.truck_id
                LEFT JOIN user u ON ts.user_id = u.user_id
                LEFT JOIN collection_area ca ON s.area_id = ca.area_id
                GROUP BY s.schedule_id
                ORDER BY s.collection_date DESC LIMIT 5";
    $recentSchedules = $conn->query($recentQuery)->fetch_all(MYSQLI_ASSOC);

    // 7. Area Collection Data for Pie Chart (This Month)
    $areaChartQuery = "SELECT ca.taman_name, COUNT(s.schedule_id) as collection_count
                   FROM collection_area ca
                   LEFT JOIN schedule s ON ca.area_id = s.area_id 
                   AND DATE_FORMAT(s.collection_date, '%Y-%m') = ?
                   GROUP BY ca.area_id, ca.taman_name
                   ORDER BY collection_count DESC
                   LIMIT 6";
    $areaStmt = $conn->prepare($areaChartQuery);
    $areaStmt->bind_param('s', $currentMonth);
    $areaStmt->execute();
    $areaChartData = $areaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $areaStmt->close();

    // Prepare data for JavaScript
    $areaLabels = [];
    $areaValues = [];
    foreach ($areaChartData as $area) {
        $areaLabels[] = $area['taman_name'];
        $areaValues[] = (int) $area['collection_count'];
    }
    ?>

    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <?php include '../includes/admin_topbar.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <a href="reports.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                        </a>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Total Schedules Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Schedules</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $scheduleCount; ?> This Month</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Trucks Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Trucks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeTrucks; ?> Available</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Areas Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Areas Covered</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $areaCount; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Complaints Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Complaints</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $pendingComplaints; ?> Pending</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Monthly Completion Progress Ring -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <!-- Card Header -->
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-success">Monthly Completion Rate</h6>
                                <span class="small text-muted"><?php echo date('F Y'); ?></span>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Progress Ring -->
                                    <div class="col-md-6 text-center">
                                        <div class="position-relative d-inline-block" style="width: 200px; height: 200px;">
                                            <canvas id="completionRing" width="200" height="200"></canvas>
                                            <div class="position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                                                <span class="h1 font-weight-bold text-success mb-0"><?php echo $completionRate; ?>%</span>
                                                <br>
                                                <span class="small text-muted">Completed</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Stats Summary -->
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="font-weight-bold text-success"><i class="fas fa-check-circle mr-1"></i>Completed</span>
                                                <span><?php echo $completedCount; ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $completionRate; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="font-weight-bold text-warning"><i class="fas fa-clock mr-1"></i>Pending</span>
                                                <span><?php echo $scheduleCount - $completedCount; ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-warning" style="width: <?php echo 100 - $completionRate; ?>%"></div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="text-center">
                                            <span class="h4 font-weight-bold text-gray-800"><?php echo $scheduleCount; ?></span>
                                            <span class="text-muted">Total Schedules</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Pie Chart -->
                        <div class="col-xl-4 col-lg-5">
                            <div class="card shadow mb-4">
                                <!-- Card Header -->
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-success">Collection by Area</h6>
                                </div>
                                <!-- Card Body -->
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="areaChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Schedules Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-success">Recent Collection Schedules</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Area</th>
                                            <th>Staff Assigned</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentSchedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($schedule['collection_date'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($schedule['area_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($schedule['staff_names'] ?? 'Not Assigned'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $schedule['status'];
                                                    $statusClass = [
                                                        'Pending' => 'badge-warning',
                                                        'Completed' => 'badge-success'
                                                    ][$status] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="adminschedule.php?schedule_id=<?php echo $schedule['schedule_id']; ?>"
                                                        class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
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
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SWM Environment <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

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

    <?php require_once '../includes/template_footer.php'; ?>

    <!-- Page level plugins -->
    <script src="vendor/chart.js/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script>
        // Completion Rate data
        var completionRate = <?php echo $completionRate; ?>;
        
        // Completion Ring Chart
        var ctxRing = document.getElementById("completionRing");
        if (ctxRing) {
            var completionRingChart = new Chart(ctxRing, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [completionRate, 100 - completionRate],
                        backgroundColor: ['#1cc88a', '#e9ecef'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    cutoutPercentage: 75,
                    maintainAspectRatio: true,
                    tooltips: { enabled: false },
                    legend: { display: false },
                    animation: {
                        animateRotate: true,
                        duration: 1500
                    }
                }
            });
        }

        // Pie Chart
        var ctx2 = document.getElementById("areaChart");

        // PHP data passed to JavaScript
        var areaLabels = <?php echo json_encode($areaLabels); ?>;
        var areaData = <?php echo json_encode($areaValues); ?>;

        // Generate colors dynamically
        var colors = ['#1cc88a', '#4e73df', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];
        var hoverColors = ['#17a673', '#2e59d9', '#2c9faf', '#dda20a', '#be2617', '#60616f'];

        var myPieChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: areaLabels,
                datasets: [{
                    data: areaData,
                    backgroundColor: colors.slice(0, areaLabels.length),
                    hoverBackgroundColor: hoverColors.slice(0, areaLabels.length),
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true,
                    position: 'bottom'
                },
                cutoutPercentage: 80,
            },
        });
    </script>