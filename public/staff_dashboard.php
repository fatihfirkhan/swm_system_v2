<?php
// Define constant for includes
define('INCLUDED', true);

session_start();
require_once '../includes/db.php';

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'staff') {
    header('Location: login.php?role=staff');
    exit();
}

$pageTitle = 'Staff Dashboard - SWM Environment';
$currentPage = 'dashboard';

// ========== DATA FETCHING FOR STAFF DASHBOARD ==========

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Staff';

// 1. Get staff's assigned truck (ACTIVE only)
$truckQuery = "SELECT t.truck_id, t.truck_number
               FROM truck t
               INNER JOIN truck_staff ts ON t.truck_id = ts.truck_id
               WHERE ts.user_id = ? AND ts.status = 'active'
               LIMIT 1";
$truckStmt = $conn->prepare($truckQuery);
$truckStmt->bind_param('i', $userId);
$truckStmt->execute();
$assignedTruck = $truckStmt->get_result()->fetch_assoc();
$truckStmt->close();

$truckId = $assignedTruck['truck_id'] ?? null;

// 2. Tasks This Month (if truck assigned)
$tasksThisMonth = 0;
if ($truckId) {
    $currentMonth = date('Y-m');
    $tasksQuery = "SELECT COUNT(*) as total FROM schedule 
                   WHERE truck_id = ? AND DATE_FORMAT(collection_date, '%Y-%m') = ?";
    $tasksStmt = $conn->prepare($tasksQuery);
    $tasksStmt->bind_param('is', $truckId, $currentMonth);
    $tasksStmt->execute();
    $tasksThisMonth = $tasksStmt->get_result()->fetch_assoc()['total'];
    $tasksStmt->close();
}

// 3. Completion Rate
$completionRate = 0;
if ($truckId && $tasksThisMonth > 0) {
    $completedQuery = "SELECT COUNT(*) as completed FROM schedule 
                       WHERE truck_id = ? AND DATE_FORMAT(collection_date, '%Y-%m') = ? 
                       AND status = 'Completed'";
    $compStmt = $conn->prepare($completedQuery);
    $compStmt->bind_param('is', $truckId, $currentMonth);
    $compStmt->execute();
    $completedCount = $compStmt->get_result()->fetch_assoc()['completed'];
    $compStmt->close();
    
    $completionRate = round(($completedCount / $tasksThisMonth) * 100);
}

// 4. Today's Assignment
$todayAssignment = null;
if ($truckId) {
    $todayQuery = "SELECT s.*, ca.taman_name as area_name
                   FROM schedule s
                   LEFT JOIN collection_area ca ON s.area_id = ca.area_id
                   WHERE s.truck_id = ? AND s.collection_date = CURDATE()
                   LIMIT 1";
    $todayStmt = $conn->prepare($todayQuery);
    $todayStmt->bind_param('i', $truckId);
    $todayStmt->execute();
    $todayAssignment = $todayStmt->get_result()->fetch_assoc();
    $todayStmt->close();
}

// 5. Recent History (Last 5 completed schedules)
$recentHistory = [];
if ($truckId) {
    $historyQuery = "SELECT s.*, ca.taman_name as area_name
                     FROM schedule s
                     LEFT JOIN collection_area ca ON s.area_id = ca.area_id
                     WHERE s.truck_id = ? AND s.status = 'Completed'
                     ORDER BY s.collection_date DESC
                     LIMIT 5";
    $histStmt = $conn->prepare($historyQuery);
    $histStmt->bind_param('i', $truckId);
    $histStmt->execute();
    $recentHistory = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $histStmt->close();
}

// 6. Upcoming Tasks This Week
$upcomingTasks = [];
if ($truckId) {
    $weekStart = date('Y-m-d');
    $weekEnd = date('Y-m-d', strtotime('+7 days'));
    
    $upcomingQuery = "SELECT s.*, ca.taman_name as area_name,
                      CASE 
                          WHEN s.collection_date = CURDATE() THEN 'Today'
                          WHEN s.collection_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'Tomorrow'
                          ELSE DAYNAME(s.collection_date)
                      END as day_label
                      FROM schedule s
                      LEFT JOIN collection_area ca ON s.area_id = ca.area_id
                      WHERE s.truck_id = ? 
                      AND s.collection_date BETWEEN ? AND ?
                      AND s.status = 'Pending'
                      ORDER BY s.collection_date ASC
                      LIMIT 7";
    $upStmt = $conn->prepare($upcomingQuery);
    $upStmt->bind_param('iss', $truckId, $weekStart, $weekEnd);
    $upStmt->execute();
    $upcomingTasks = $upStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $upStmt->close();
}

// 7. Pending Tasks Count
$pendingCount = 0;
if ($truckId) {
    $pendingQuery = "SELECT COUNT(*) as total FROM schedule 
                     WHERE truck_id = ? AND status = 'Pending' AND collection_date >= CURDATE()";
    $pendStmt = $conn->prepare($pendingQuery);
    $pendStmt->bind_param('i', $truckId);
    $pendStmt->execute();
    $pendingCount = $pendStmt->get_result()->fetch_assoc()['total'];
    $pendStmt->close();
}

// Additional styles
$additionalStyles = '
<style>
    .today-assignment-card {
        background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
        color: white;
        border-radius: 10px;
        padding: 30px;
        margin-bottom: 30px;
    }
    .today-assignment-card h3 {
        color: white;
        font-weight: 700;
    }
    .today-assignment-card .badge {
        font-size: 0.9rem;
        padding: 8px 15px;
    }
</style>
';

// Additional scripts
$additionalScripts = '';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-truck text-info mr-2"></i>Staff Dashboard
    </h1>
    <small class="text-muted">Welcome back, <?php echo htmlspecialchars($userName); ?>!</small>
</div>

<!-- ========== TOP ROW: KPI STATS ========== -->
<div class="row">

    <!-- Tasks This Month Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Tasks This Month</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $tasksThisMonth; ?></div>
                        <small class="text-muted">Assigned collections</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Tasks Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pendingCount; ?></div>
                        <small class="text-muted">Upcoming collections</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completion Rate Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Completion Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completionRate; ?>%</div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo $completionRate; ?>%" 
                                 aria-valuenow="<?php echo $completionRate; ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Truck Status Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Active Truck</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $assignedTruck ? 'Truck #' . htmlspecialchars($assignedTruck['truck_number']) : 'Not Assigned'; ?>
                        </div>
                        <?php if ($assignedTruck): ?>
                            <small class="text-muted">Status: Active</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ========== MIDDLE ROW: TODAY'S PRIORITY TASK ========== -->
<div class="row">
    <div class="col-lg-12">
        <?php if ($todayAssignment): ?>
            <div class="today-assignment-card shadow position-relative">
                <div class="position-relative" style="z-index: 1;">
                    <h5 class="text-uppercase mb-3" style="letter-spacing: 2px; font-weight: 600;">
                        <i class="fas fa-star mr-2"></i>TODAY'S PRIORITY TASK
                    </h5>
                    <h3 class="mb-3"><?php echo htmlspecialchars($todayAssignment['area_name'] ?? 'N/A'); ?></h3>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <strong><i class="fas fa-calendar-day mr-2"></i>Date:</strong><br>
                            <?php echo date('M d, Y', strtotime($todayAssignment['collection_date'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-recycle mr-2"></i>Type:</strong><br>
                            <?php echo htmlspecialchars($todayAssignment['collection_type']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-truck mr-2"></i>Truck:</strong><br>
                            Truck #<?php echo htmlspecialchars($assignedTruck['truck_number'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-info-circle mr-2"></i>Status:</strong><br>
                            <span class="badge badge-light"><?php echo htmlspecialchars($todayAssignment['status']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="staff_schedule_view.php" class="btn btn-light btn-lg">
                            <i class="fas fa-eye mr-2"></i>View Full Details
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info shadow">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>No assignments for today.</strong> Check back tomorrow or view your upcoming schedule.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== UPCOMING TASKS & TIPS ROW ========== -->
<div class="row">
    
    <!-- Upcoming Tasks This Week -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-calendar-week mr-2"></i>Upcoming Tasks (Next 7 Days)
                </h6>
            </div>
            <div class="card-body">
                <?php if (count($upcomingTasks) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Date</th>
                                    <th>Area</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingTasks as $task): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($task['day_label']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($task['collection_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $task['collection_type'] === 'Recycle' ? 'info' : 'secondary'; ?>">
                                            <i class="fas fa-<?php echo $task['collection_type'] === 'Recycle' ? 'recycle' : 'trash-alt'; ?> mr-1"></i>
                                            <?php echo htmlspecialchars($task['collection_type']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-check fa-3x mb-3" style="opacity: 0.3;"></i>
                        <p class="mb-0">No upcoming tasks scheduled for this week.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Performance Tips & Reminders -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-lightbulb mr-2"></i>Tips & Reminders
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3 py-2">
                    <small><i class="fas fa-clock mr-2"></i><strong>Start Time:</strong> Begin collections at 7:00 AM</small>
                </div>
                <div class="alert alert-success mb-3 py-2">
                    <small><i class="fas fa-check-circle mr-2"></i><strong>Safety First:</strong> Wear protective gear at all times</small>
                </div>
                <div class="alert alert-warning mb-3 py-2">
                    <small><i class="fas fa-recycle mr-2"></i><strong>Sorting:</strong> Separate recyclables properly</small>
                </div>
                <div class="alert alert-secondary mb-0 py-2">
                    <small><i class="fas fa-phone mr-2"></i><strong>Issues?</strong> Report to supervisor immediately</small>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- ========== BOTTOM ROW: RECENT HISTORY TABLE ========== -->
<?php if (count($recentHistory) > 0): ?>
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info">
                    <i class="fas fa-history mr-2"></i>Recent Completed Collections
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Area</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentHistory as $history): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($history['collection_date'])); ?></td>
                                <td><?php echo htmlspecialchars($history['area_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $history['collection_type'] === 'Recycle' ? 'info' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($history['collection_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check mr-1"></i><?php echo htmlspecialchars($history['status']); ?>
                                    </span>
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

<?php
$pageContent = ob_get_clean();
require_once '../includes/staff/staff_template.php';
?>
