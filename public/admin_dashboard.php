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
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- SWM Environment custom theme -->
    <link href="css/swm-custom.css" rel="stylesheet">
    <?php echo $additionalStyles; ?>
</head>

<body id="page-top"><?php

// ========== DATA FETCHING FOR ADMIN DASHBOARD ==========
$currentMonth = date('Y-m');

// 1. Total schedules this month
$schedulesQuery = "SELECT COUNT(*) as total FROM schedule WHERE DATE_FORMAT(collection_date, '%Y-%m') = ?";
$stmt = $conn->prepare($schedulesQuery);
$stmt->bind_param('s', $currentMonth);
$stmt->execute();
$scheduleCount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// 2. Active staff count
$staffQuery = "SELECT COUNT(*) as total FROM user WHERE role = 'staff'";
$activeStaff = $conn->query($staffQuery)->fetch_assoc()['total'];

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
    $areaValues[] = (int)$area['collection_count'];
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $scheduleCount; ?> This Month</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Staff Card -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Assigned Staff</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeStaff; ?> Active</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $areaCount; ?></div>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pendingComplaints; ?> Pending</div>
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

                    <!-- Area Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <!-- Card Header -->
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-success">Collection Schedule Overview</h6>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="scheduleChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pie Chart -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <!-- Card Header -->
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
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
                                        <td><?php echo date('M d, Y', strtotime($schedule['collection_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['area_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['staff_names'] ?? 'Not Assigned'); ?></td>
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
// PHP data passed to JavaScript
var chartCollectionData = <?php echo json_encode($chartData); ?>;

// Area Chart
var ctx = document.getElementById("scheduleChart");
var myLineChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ["Week 1", "Week 2", "Week 3", "Week 4"],
        datasets: [{
            label: "Collections",
            lineTension: 0.3,
            backgroundColor: "rgba(28, 200, 138, 0.05)",
            borderColor: "rgba(28, 200, 138, 1)",
            pointRadius: 3,
            pointBackgroundColor: "rgba(28, 200, 138, 1)",
            pointBorderColor: "rgba(28, 200, 138, 1)",
            pointHoverRadius: 3,
            pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
            pointHoverBorderColor: "rgba(28, 200, 138, 1)",
            pointHitRadius: 10,
            pointBorderWidth: 2,
            data: chartCollectionData,
        }],
    },
    options: {
        maintainAspectRatio: false,
        layout: {
            padding: {
                left: 10,
                right: 25,
                top: 25,
                bottom: 0
            }
        },
        scales: {
            xAxes: [{
                gridLines: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    maxTicksLimit: 7
                }
            }],
            yAxes: [{
                ticks: {
                    maxTicksLimit: 5,
                    padding: 10,
                    beginAtZero: true
                },
                gridLines: {
                    color: "rgb(234, 236, 244)",
                    zeroLineColor: "rgb(234, 236, 244)",
                    drawBorder: false,
                    borderDash: [2],
                    zeroLineBorderDash: [2]
                }
            }],
        },
        legend: {
            display: false
        },
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            titleMarginBottom: 10,
            titleFontColor: '#6e707e',
            titleFontSize: 14,
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            intersect: false,
            mode: 'index',
            caretPadding: 10,
        }
    }
});

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