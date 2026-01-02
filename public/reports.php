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

$pageTitle = 'Reports - SWM Environment';
$currentPage = 'reports';

// Get filter parameters with defaults
$currentMonth = date('Y-m');
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$statusFilter = $_GET['status'] ?? 'all';
$areaFilter = $_GET['area_id'] ?? 'all';

// Validate dates
if (!strtotime($startDate)) $startDate = date('Y-m-01');
if (!strtotime($endDate)) $endDate = date('Y-m-t');

// Build the WHERE clause for filtering
$whereConditions = ["s.collection_date BETWEEN ? AND ?"];
$params = [$startDate, $endDate];
$paramTypes = "ss";

if ($statusFilter !== 'all') {
    $whereConditions[] = "s.status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if ($areaFilter !== 'all') {
    $whereConditions[] = "s.area_id = ?";
    $params[] = $areaFilter;
    $paramTypes .= "i";
}

$whereClause = implode(" AND ", $whereConditions);

// ============================================================
// STATISTICS QUERIES
// ============================================================

// 1. Total Collections in period
$totalQuery = "SELECT COUNT(*) as total FROM schedule s WHERE " . $whereClause;
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$totalCollections = $stmt->get_result()->fetch_assoc()['total'];

// 2. Completed Collections
$completedParams = array_merge($params, ['Completed']);
$completedQuery = "SELECT COUNT(*) as total FROM schedule s WHERE " . $whereClause . " AND s.status = ?";
$stmt = $conn->prepare($completedQuery);
$stmt->bind_param($paramTypes . "s", ...$completedParams);
$stmt->execute();
$completedCollections = $stmt->get_result()->fetch_assoc()['total'];

// 3. Pending Collections
$pendingParams = array_merge($params, ['Pending']);
$pendingQuery = "SELECT COUNT(*) as total FROM schedule s WHERE " . $whereClause . " AND s.status = ?";
$stmt = $conn->prepare($pendingQuery);
$stmt->bind_param($paramTypes . "s", ...$pendingParams);
$stmt->execute();
$pendingCollections = $stmt->get_result()->fetch_assoc()['total'];

// 4. Missed Collections
$missedParams = array_merge($params, ['Missed']);
$missedQuery = "SELECT COUNT(*) as total FROM schedule s WHERE " . $whereClause . " AND s.status = ?";
$stmt = $conn->prepare($missedQuery);
$stmt->bind_param($paramTypes . "s", ...$missedParams);
$stmt->execute();
$missedCollections = $stmt->get_result()->fetch_assoc()['total'];

// 5. Completion Rate
$completionRate = $totalCollections > 0 ? round(($completedCollections / $totalCollections) * 100, 1) : 0;

// 6. New Trucks (created within the period)
$newTrucks = 0;
$checkTruckCol = $conn->query("SHOW COLUMNS FROM truck LIKE 'created_at'");
if($checkTruckCol && $checkTruckCol->num_rows > 0) {
    // Use DATE() to compare dates properly, and add 1 day to end date to include full day
    $truckQuery = "SELECT COUNT(*) as total FROM truck WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $stmt = $conn->prepare($truckQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $newTrucks = $stmt->get_result()->fetch_assoc()['total'];
}

// 7. Total Active Trucks
$activeTruckQuery = "SELECT COUNT(*) as total FROM truck WHERE status = 'active'";
$activeTrucks = $conn->query($activeTruckQuery)->fetch_assoc()['total'];

// 8. Total Areas & New Areas Logic
$totalAreasQuery = "SELECT COUNT(*) as total FROM collection_area";
$totalAreas = $conn->query($totalAreasQuery)->fetch_assoc()['total'];

$newAreas = 0;
// Safety check: only run query if column exists
$checkAreaCol = $conn->query("SHOW COLUMNS FROM collection_area LIKE 'created_at'");
if($checkAreaCol && $checkAreaCol->num_rows > 0) {
    // Use DATE() to compare dates properly
    $newAreasQuery = "SELECT COUNT(*) as total FROM collection_area WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
    $stmt = $conn->prepare($newAreasQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $newAreas = $stmt->get_result()->fetch_assoc()['total'];
}

// ============================================================
// CHART DATA QUERIES
// ============================================================

// Chart 1: Collections by Status (for Bar Chart)
$statusChartData = [
    'Completed' => $completedCollections,
    'Pending' => $pendingCollections,
    'Missed' => $missedCollections
];

// Chart 2: Domestic vs Recycle Collection Types (Pie Chart)
$domesticQuery = "SELECT COUNT(*) as total FROM schedule s WHERE s.collection_date BETWEEN ? AND ? AND s.collection_type = 'Domestic'";
$stmt = $conn->prepare($domesticQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$domesticCount = $stmt->get_result()->fetch_assoc()['total'];

$recycleQuery = "SELECT COUNT(*) as total FROM schedule s WHERE s.collection_date BETWEEN ? AND ? AND s.collection_type = 'Recycle'";
$stmt = $conn->prepare($recycleQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$recycleCount = $stmt->get_result()->fetch_assoc()['total'];

$collectionTypeData = [
    'Domestic' => $domesticCount,
    'Recycle' => $recycleCount
];

// Chart 3: Collections Trend Over Time
$trendQuery = "SELECT DATE(collection_date) as date, COUNT(*) as count
               FROM schedule
               WHERE collection_date BETWEEN ? AND ?
               GROUP BY DATE(collection_date)
               ORDER BY date ASC";
$stmt = $conn->prepare($trendQuery);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$trendResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$trendLabels = [];
$trendData = [];
foreach ($trendResult as $row) {
    $trendLabels[] = date('M d', strtotime($row['date']));
    $trendData[] = (int)$row['count'];
}

// ============================================================
// TABLE DATA QUERY
// ============================================================

$dataQuery = "SELECT s.schedule_id, s.collection_date, s.collection_type, s.status,
                     ca.taman_name as area_name, t.truck_number,
                     GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as staff_names
              FROM schedule s
              LEFT JOIN collection_area ca ON s.area_id = ca.area_id
              LEFT JOIN truck t ON s.truck_id = t.truck_id
              LEFT JOIN truck_staff ts ON t.truck_id = ts.truck_id AND ts.status = 'active'
              LEFT JOIN user u ON ts.user_id = u.user_id
              WHERE " . $whereClause . "
              GROUP BY s.schedule_id
              ORDER BY s.collection_date DESC, s.schedule_id DESC";

$stmt = $conn->prepare($dataQuery);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all areas for filter dropdown
$areasQuery = $conn->query("SELECT area_id, taman_name FROM collection_area ORDER BY taman_name");

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">

    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/swm-custom.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <style>
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .filter-card {
            background: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
        }
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }
        .stat-card .card-body {
            padding: 1.25rem;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .chart-card {
            border-radius: 10px;
            overflow: hidden;
        }
        .table-card {
            border-radius: 10px;
            overflow: hidden;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }
        .export-btn-group .btn {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        .metric-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .metric-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #858796;
        }
        .trend-up { color: #1cc88a; }
        .trend-down { color: #e74a3b; }
    </style>
</head>

<body id="page-top">

<div id="wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            <?php include '../includes/admin_topbar.php'; ?>

            <div class="container-fluid">

                <div class="analytics-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1"><i class="fas fa-file-alt mr-2"></i>Reports</h2>
                            <p class="mb-0 opacity-75">
                                Comprehensive waste collection statistics and insights
                            </p>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <div class="export-btn-group">
                                <button class="btn btn-light btn-sm mr-2" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel mr-1 text-success"></i>Excel
                                </button>
                                <button class="btn btn-light btn-sm" onclick="exportToPDF()">
                                    <i class="fas fa-file-pdf mr-1 text-danger"></i>PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card filter-card shadow-sm mb-4">
                    <div class="card-body py-3">
                        <form method="GET" action="" class="row align-items-end">
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label class="small font-weight-bold text-gray-600">Start Date</label>
                                <input type="date" class="form-control form-control-sm" name="start_date" 
                                       value="<?php echo htmlspecialchars($startDate); ?>">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label class="small font-weight-bold text-gray-600">End Date</label>
                                <input type="date" class="form-control form-control-sm" name="end_date" 
                                       value="<?php echo htmlspecialchars($endDate); ?>">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label class="small font-weight-bold text-gray-600">Status</label>
                                <select class="form-control form-control-sm" name="status">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Missed" <?php echo $statusFilter === 'Missed' ? 'selected' : ''; ?>>Missed</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label class="small font-weight-bold text-gray-600">Area</label>
                                <select class="form-control form-control-sm" name="area_id">
                                    <option value="all">All Areas</option>
                                    <?php while ($area = $areasQuery->fetch_assoc()): ?>
                                        <option value="<?php echo $area['area_id']; ?>" 
                                            <?php echo $areaFilter == $area['area_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($area['taman_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-filter mr-1"></i>Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-4">
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col">
                                        <div class="metric-label mb-1">Total Collections</div>
                                        <div class="metric-value text-primary"><?php echo number_format($totalCollections); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('M d', strtotime($startDate)) . ' - ' . date('M d', strtotime($endDate)); ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon bg-primary text-white">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col">
                                        <div class="metric-label mb-1">Completion Rate</div>
                                        <div class="metric-value text-success"><?php echo $completionRate; ?>%</div>
                                        <div class="progress progress-sm mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $completionRate; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon bg-success text-white">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col">
                                        <div class="metric-label mb-1">Active Trucks</div>
                                        <div class="metric-value text-info"><?php echo number_format($activeTrucks); ?></div>
                                        <small class="text-muted">
                                            <?php if ($newTrucks > 0): ?>
                                                <span class="trend-up"><i class="fas fa-arrow-up"></i> <?php echo $newTrucks; ?> new</span>
                                            <?php else: ?>
                                                Fleet operational
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon bg-info text-white">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col">
                                        <div class="metric-label mb-1">Service Areas</div>
                                        <div class="metric-value text-warning"><?php echo number_format($totalAreas); ?></div>
                                        <small class="text-muted">
                                            <?php if ($newAreas > 0): ?>
                                                <span class="trend-up font-weight-bold">
                                                    <i class="fas fa-plus-circle"></i> <?php echo $newAreas; ?> new zones
                                                </span>
                                            <?php else: ?>
                                                Active coverage zones
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon bg-warning text-white">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Data Table Card - MOVED TO TOP -->
                <div class="card table-card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table mr-2"></i>Collection Records
                        </h6>
                        <span class="badge badge-primary badge-pill"><?php echo count($schedules); ?> records</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedules)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-gray-300 mb-3"></i>
                                <h5 class="text-gray-500">No Data Found</h5>
                                <p class="text-muted">No collection records match your filter criteria.</p>
                                <a href="reports.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-sync mr-1"></i>Reset Filters
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="reportsTable" width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="12%">Date</th>
                                            <th width="18%">Zone/Area</th>
                                            <th width="10%">Type</th>
                                            <th width="12%">Truck</th>
                                            <th width="23%">Collector(s)</th>
                                            <th width="10%">Status</th>
                                            <th width="10%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($schedules as $schedule): 
                                            $statusClass = 'secondary';
                                            switch(strtolower($schedule['status'])) {
                                                case 'completed': $statusClass = 'success'; break;
                                                case 'pending': $statusClass = 'warning'; break;
                                                case 'missed': $statusClass = 'danger'; break;
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo date('d M Y', strtotime($schedule['collection_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['area_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($schedule['collection_type'] === 'Domestic'): ?>
                                                    <span class="badge badge-info"><i class="fas fa-trash-alt mr-1"></i>Domestic</span>
                                                <?php elseif ($schedule['collection_type'] === 'Recycle'): ?>
                                                    <span class="badge badge-success"><i class="fas fa-recycle mr-1"></i>Recycle</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($schedule['collection_type'] ?? 'N/A'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($schedule['truck_number']): ?>
                                                    <i class="fas fa-truck text-muted mr-1"></i><?php echo htmlspecialchars($schedule['truck_number']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($schedule['staff_names'] ?? 'No staff assigned'); ?></small></td>
                                            <td>
                                                <span class="badge badge-<?php echo $statusClass; ?> status-badge">
                                                    <?php echo htmlspecialchars($schedule['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $schedule['schedule_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Charts Section - MOVED TO BOTTOM -->
                <div class="row mb-4">
                    
                    <div class="col-xl-6 col-lg-6 mb-4">
                        <div class="card chart-card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-bar mr-2"></i>Collections by Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-6 mb-4">
                        <div class="card chart-card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie mr-2"></i>Domestic vs Recycle
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card chart-card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line mr-2"></i>Collection Trend Over Time
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 250px;">
                                    <canvas id="trendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            </div>
        <?php include '../includes/template_footer.php'; ?>

    </div>
</div>

<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Collection Details</h5>
                <button class="close text-white" type="button" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
// Chart Data from PHP
const statusData = <?php echo json_encode($statusChartData); ?>;
const collectionTypeData = <?php echo json_encode($collectionTypeData); ?>;
const trendLabels = <?php echo json_encode($trendLabels); ?>;
const trendData = <?php echo json_encode($trendData); ?>;

// Summary Stats for Export
const summaryStats = {
    totalCollections: <?php echo $totalCollections; ?>,
    completedCollections: <?php echo $completedCollections; ?>,
    pendingCollections: <?php echo $pendingCollections; ?>,
    missedCollections: <?php echo $missedCollections; ?>,
    completionRate: <?php echo $completionRate; ?>,
    activeTrucks: <?php echo $activeTrucks; ?>,
    newTrucks: <?php echo $newTrucks; ?>,
    totalAreas: <?php echo $totalAreas; ?>,
    newAreas: <?php echo $newAreas; ?>,
    domesticCount: <?php echo $domesticCount; ?>,
    recycleCount: <?php echo $recycleCount; ?>
};

$(document).ready(function() {
    // Initialize DataTable
    <?php if (!empty($schedules)): ?>
    $('#reportsTable').DataTable({
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "order": [[1, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [7] }]
    });
    <?php endif; ?>

    // Bar Chart - Collections by Status (with percentage)
    const totalStatus = statusData.Completed + statusData.Pending + statusData.Missed;
    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: {
            labels: ['Completed', 'Pending', 'Missed'],
            datasets: [{
                label: 'Collections',
                data: [statusData.Completed, statusData.Pending, statusData.Missed],
                backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'],
                borderColor: ['#17a673', '#dda20a', '#c0392b'],
                borderWidth: 1,
                borderRadius: 5,
                barThickness: 50
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const percentage = totalStatus > 0 ? ((value / totalStatus) * 100).toFixed(1) : 0;
                            return `${value} (${percentage}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });

    // Pie Chart - Domestic vs Recycle (with percentage)
    const totalType = (collectionTypeData.Domestic || 0) + (collectionTypeData.Recycle || 0);
    new Chart(document.getElementById('typeChart'), {
        type: 'pie',
        data: {
            labels: [
                `Domestic: ${collectionTypeData.Domestic || 0} (${totalType > 0 ? ((collectionTypeData.Domestic / totalType) * 100).toFixed(1) : 0}%)`,
                `Recycle: ${collectionTypeData.Recycle || 0} (${totalType > 0 ? ((collectionTypeData.Recycle / totalType) * 100).toFixed(1) : 0}%)`
            ],
            datasets: [{
                data: [collectionTypeData.Domestic || 0, collectionTypeData.Recycle || 0],
                backgroundColor: ['#36b9cc', '#1cc88a'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { padding: 15, usePointStyle: true }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const percentage = totalType > 0 ? ((value / totalType) * 100).toFixed(1) : 0;
                            return `${value} collections (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Line Chart - Collection Trend
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Collections',
                data: trendData,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4e73df',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
});

// View Details Function
function viewDetails(scheduleId) {
    $('#detailsContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Loading...</p></div>');
    $('#detailsModal').modal('show');
    
    $.ajax({
        url: '../backend/get_schedule_details.php',
        method: 'GET',
        data: { schedule_id: scheduleId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let data = response.data;
                let statusClass = data.status.toLowerCase() === 'completed' ? 'success' : 
                                  data.status.toLowerCase() === 'pending' ? 'warning' : 'danger';
                $('#detailsContent').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-primary mb-3">Schedule Info</h6>
                            <table class="table table-sm">
                                <tr><th>ID:</th><td>#${data.schedule_id}</td></tr>
                                <tr><th>Date:</th><td>${data.collection_date}</td></tr>
                                <tr><th>Type:</th><td>${data.collection_type || 'N/A'}</td></tr>
                                <tr><th>Status:</th><td><span class="badge badge-${statusClass}">${data.status}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="font-weight-bold text-primary mb-3">Assignment</h6>
                            <table class="table table-sm">
                                <tr><th>Area:</th><td>${data.area_name || 'N/A'}</td></tr>
                                <tr><th>Truck:</th><td>${data.truck_number || 'Not Assigned'}</td></tr>
                                <tr><th>Staff:</th><td>${data.staff_names || 'Not assigned'}</td></tr>
                            </table>
                        </div>
                    </div>
                `);
            } else {
                $('#detailsContent').html('<div class="alert alert-danger">Failed to load details</div>');
            }
        },
        error: function() {
            $('#detailsContent').html(`<div class="alert alert-warning">Unable to load details. Schedule ID: ${scheduleId}</div>`);
        }
    });
}

// Export to Excel (with Summary Stats & Chart Data)
function exportToExcel() {
    const wb = XLSX.utils.book_new();
    
    // Calculate percentages
    const total = summaryStats.totalCollections || 1;
    const completedPct = ((summaryStats.completedCollections / total) * 100).toFixed(1);
    const pendingPct = ((summaryStats.pendingCollections / total) * 100).toFixed(1);
    const missedPct = ((summaryStats.missedCollections / total) * 100).toFixed(1);
    
    const totalType = summaryStats.domesticCount + summaryStats.recycleCount || 1;
    const domesticPct = ((summaryStats.domesticCount / totalType) * 100).toFixed(1);
    const recyclePct = ((summaryStats.recycleCount / totalType) * 100).toFixed(1);
    
    // Sheet 1: Summary Statistics
    const summaryData = [
        ['WASTE COLLECTION REPORT'],
        ['Period: <?php echo $startDate; ?> to <?php echo $endDate; ?>'],
        ['Generated: ' + new Date().toLocaleString()],
        [''],
        ['════════════════════════════════════════'],
        ['COLLECTION STATUS BREAKDOWN (Chart 1)'],
        ['════════════════════════════════════════'],
        ['Status', 'Count', 'Percentage'],
        ['Completed', summaryStats.completedCollections, completedPct + '%'],
        ['Pending', summaryStats.pendingCollections, pendingPct + '%'],
        ['Missed', summaryStats.missedCollections, missedPct + '%'],
        ['TOTAL', summaryStats.totalCollections, '100%'],
        [''],
        ['Completion Rate:', summaryStats.completionRate + '%'],
        [''],
        ['════════════════════════════════════════'],
        ['COLLECTION TYPES (Chart 2 - Pie)'],
        ['════════════════════════════════════════'],
        ['Type', 'Count', 'Percentage'],
        ['Domestic', summaryStats.domesticCount, domesticPct + '%'],
        ['Recycle', summaryStats.recycleCount, recyclePct + '%'],
        ['TOTAL', summaryStats.domesticCount + summaryStats.recycleCount, '100%'],
        [''],
        ['════════════════════════════════════════'],
        ['FLEET & AREA REGISTRATION'],
        ['════════════════════════════════════════'],
        ['Item', 'Total', 'New (This Period)'],
        ['Trucks', summaryStats.activeTrucks, summaryStats.newTrucks],
        ['Service Areas', summaryStats.totalAreas, summaryStats.newAreas],
        [''],
        ['════════════════════════════════════════'],
        ['COLLECTION TREND (Chart 3 - Daily)'],
        ['════════════════════════════════════════'],
        ['Date', 'Collections']
    ];
    
    // Add trend data (daily collections)
    for (let i = 0; i < trendLabels.length; i++) {
        summaryData.push([trendLabels[i], trendData[i]]);
    }
    
    const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
    wsSummary['!cols'] = [{ wch: 30 }, { wch: 15 }, { wch: 15 }];
    XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary & Charts');
    
    // Sheet 2: Collection Records
    const table = document.getElementById('reportsTable');
    if (table) {
        const wsTable = XLSX.utils.table_to_sheet(table);
        wsTable['!cols'] = [{ wch: 5 }, { wch: 15 }, { wch: 25 }, { wch: 12 }, { wch: 15 }, { wch: 30 }, { wch: 12 }];
        
        // Remove Actions column
        const range = XLSX.utils.decode_range(wsTable['!ref']);
        for (let R = range.s.r; R <= range.e.r; R++) {
            delete wsTable[XLSX.utils.encode_cell({ r: R, c: 7 })];
        }
        range.e.c = 6;
        wsTable['!ref'] = XLSX.utils.encode_range(range);
        wsTable['!cols'].pop();
        
        XLSX.utils.book_append_sheet(wb, wsTable, 'Collection Records');
    }
    
    XLSX.writeFile(wb, `report_<?php echo $startDate; ?>_to_<?php echo $endDate; ?>.xlsx`);
}

// Export to PDF (with Summary, Table, and Charts)
async function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4'); // Landscape
    const pageWidth = doc.internal.pageSize.width;
    const pageHeight = doc.internal.pageSize.height;

    // ===== PAGE 1: HEADER & SUMMARY =====
    doc.setFontSize(20);
    doc.setTextColor(78, 115, 223);
    doc.text('WASTE COLLECTION REPORT', 14, 20);
    
    doc.setFontSize(11);
    doc.setTextColor(100);
    doc.text(`Period: <?php echo $startDate; ?> to <?php echo $endDate; ?>`, 14, 28);
    doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 34);
    
    // Summary Statistics Box
    doc.setFillColor(248, 249, 252);
    doc.roundedRect(14, 42, 260, 45, 3, 3, 'F');
    
    doc.setFontSize(12);
    doc.setTextColor(40);
    doc.text('SUMMARY STATISTICS', 20, 52);
    
    doc.setFontSize(10);
    doc.setTextColor(80);
    
    // Column 1
    doc.text(`Total Collections: ${summaryStats.totalCollections}`, 20, 62);
    doc.text(`Completed: ${summaryStats.completedCollections} (${summaryStats.completionRate}%)`, 20, 69);
    doc.text(`Pending: ${summaryStats.pendingCollections}`, 20, 76);
    doc.text(`Missed: ${summaryStats.missedCollections}`, 20, 83);
    
    // Column 2
    doc.text(`Active Trucks: ${summaryStats.activeTrucks}`, 100, 62);
    doc.text(`New Trucks (This Period): ${summaryStats.newTrucks}`, 100, 69);
    doc.text(`Total Areas: ${summaryStats.totalAreas}`, 100, 76);
    doc.text(`New Areas (This Period): ${summaryStats.newAreas}`, 100, 83);
    
    // Column 3
    const totalType = summaryStats.domesticCount + summaryStats.recycleCount;
    const domesticPct = totalType > 0 ? ((summaryStats.domesticCount / totalType) * 100).toFixed(1) : 0;
    const recyclePct = totalType > 0 ? ((summaryStats.recycleCount / totalType) * 100).toFixed(1) : 0;
    doc.text(`Domestic: ${summaryStats.domesticCount} (${domesticPct}%)`, 190, 62);
    doc.text(`Recycle: ${summaryStats.recycleCount} (${recyclePct}%)`, 190, 69);

    // ===== TABLE =====
    const table = document.getElementById('reportsTable');
    if (table) {
        const headers = [];
        const data = [];
        table.querySelectorAll('thead th').forEach((cell, i) => { if (i < 7) headers.push(cell.innerText.trim()); });
        table.querySelectorAll('tbody tr').forEach(row => {
            const rowData = [];
            row.querySelectorAll('td').forEach((cell, i) => { if (i < 7) rowData.push(cell.innerText.trim()); });
            if (rowData.length > 0) data.push(rowData);
        });

        doc.autoTable({
            head: [headers],
            body: data,
            startY: 95,
            theme: 'grid',
            headStyles: { fillColor: [78, 115, 223], textColor: 255, fontStyle: 'bold', fontSize: 8 },
            bodyStyles: { fontSize: 7 },
            alternateRowStyles: { fillColor: [248, 249, 252] },
            margin: { left: 14, right: 14 },
            didDrawPage: function(data) {
                // Footer on each page
                doc.setFontSize(8);
                doc.setTextColor(150);
                doc.text('SWM Environment - Waste Management System', 14, pageHeight - 10);
                doc.text(`Page ${doc.internal.getCurrentPageInfo().pageNumber}`, pageWidth - 25, pageHeight - 10);
            }
        });
    }
    
    // ===== NEW PAGE FOR CHARTS =====
    doc.addPage();
    
    doc.setFontSize(14);
    doc.setTextColor(78, 115, 223);
    doc.text('CHARTS & VISUALIZATIONS', 14, 20);
    
    // Capture and add all 3 charts
    const charts = [
        { id: 'statusChart', title: 'Collections by Status', x: 14, y: 30, w: 130, h: 75 },
        { id: 'typeChart', title: 'Domestic vs Recycle', x: 150, y: 30, w: 130, h: 75 },
        { id: 'trendChart', title: 'Collection Trend Over Time', x: 14, y: 115, w: 266, h: 70 }
    ];
    
    for (const chart of charts) {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            try {
                const canvasImg = await html2canvas(canvas, { backgroundColor: '#ffffff' });
                const imgData = canvasImg.toDataURL('image/png');
                
                doc.setFontSize(10);
                doc.setTextColor(60);
                doc.text(chart.title, chart.x, chart.y - 2);
                doc.addImage(imgData, 'PNG', chart.x, chart.y, chart.w, chart.h);
            } catch (err) {
                console.error(`Error capturing ${chart.id}`, err);
            }
        }
    }
    
    // Add chart data as text summary below
    doc.setFontSize(9);
    doc.setTextColor(80);
    let summaryY = 195;
    
    doc.text('Data Summary:', 14, summaryY);
    doc.text(`• Completed: ${summaryStats.completedCollections} | Pending: ${summaryStats.pendingCollections} | Missed: ${summaryStats.missedCollections}`, 20, summaryY + 6);
    doc.text(`• Domestic: ${summaryStats.domesticCount} (${domesticPct}%) | Recycle: ${summaryStats.recycleCount} (${recyclePct}%)`, 20, summaryY + 12);
    
    // Footer
    doc.setFontSize(8);
    doc.setTextColor(150);
    doc.text('SWM Environment - Waste Management System', 14, pageHeight - 10);
    doc.text(`Page ${doc.internal.getCurrentPageInfo().pageNumber}`, pageWidth - 25, pageHeight - 10);
    
    doc.save(`report_<?php echo $startDate; ?>_to_<?php echo $endDate; ?>.pdf`);
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>