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

$pageTitle = 'Collection Schedule - SWM Environment';
$currentPage = 'schedule';

// Get resident information including their area
$userId = $_SESSION['user_id'];
$residentQuery = "SELECT u.*, ca.taman_name, ca.area_id 
                  FROM user u 
                  LEFT JOIN collection_area ca ON u.area_id = ca.area_id 
                  WHERE u.user_id = ?";
$stmt = $conn->prepare($residentQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$resident = $stmt->get_result()->fetch_assoc();

$residentAreaId = $resident['area_id'] ?? null;
$residentAreaName = $resident['taman_name'] ?? 'Not Assigned';

// Fetch all areas for the area switcher dropdown
$allAreas = [];
$areasQuery = "SELECT area_id, taman_name FROM collection_area ORDER BY taman_name ASC";
$areasResult = $conn->query($areasQuery);
if ($areasResult) {
    while ($area = $areasResult->fetch_assoc()) {
        $allAreas[] = $area;
    }
}

// Determine which area to display (from URL param or default to resident's area)
$selectedAreaId = isset($_GET['area_id']) ? intval($_GET['area_id']) : $residentAreaId;

// Get the selected area name
$selectedAreaName = $residentAreaName;
foreach ($allAreas as $area) {
    if ($area['area_id'] == $selectedAreaId) {
        $selectedAreaName = $area['taman_name'];
        break;
    }
}

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
        /* Calendar Styles */
        #calendar table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        #calendar th, 
        #calendar td { 
            padding: 10px; 
            border: 1px solid #eef2f7; 
            min-height: 80px; 
            vertical-align: top; 
        }
        #calendar th {
            background-color: #f8f9fc;
            font-weight: 600;
            text-align: center;
        }
        .calendar-day {
            cursor: pointer;
            min-height: 80px;
            transition: all 0.2s ease;
        }
        .calendar-day:hover {
            box-shadow: 0 0 10px rgba(78, 115, 223, 0.3);
            transform: scale(1.02);
        }
        .lane-status-item {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-left: 4px solid #e3e6f0;
        }
        .lane-status-item.collected {
            border-left-color: #1cc88a;
            background: #d4edda;
        }
        .lane-status-item.pending {
            border-left-color: #f6c23e;
            background: #fff3cd;
        }
        .calendar-day.past-date {
            opacity: 0.6;
            background-color: #f8f9fa !important;
        }
        .calendar-day.today {
            border: 2px solid #4e73df !important;
        }
        .collection-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            margin-top: 5px;
        }
        .collection-domestic {
            background-color: #cfe2ff;
            color: #0d6efd;
        }
        .collection-recycle {
            background-color: #d4edda;
            color: #28a745;
        }
        .date-number {
            font-size: 1.1em;
            font-weight: bold;
        }
        .today-badge {
            font-size: 0.65em;
            background-color: #4e73df;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
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
                        <h1 class="h3 mb-0 text-gray-800">Collection Schedule</h1>
                    </div>

                    <!-- Area Switcher Card -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-left-primary shadow">
                                <div class="card-body py-3">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                View Collection Area</div>
                                            <select id="area_selector" class="form-control form-control-lg font-weight-bold text-gray-800" style="max-width: 400px;">
                                                <?php foreach ($allAreas as $area): ?>
                                                    <option value="<?= $area['area_id'] ?>" <?= ($area['area_id'] == $selectedAreaId) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($area['taman_name']) ?>
                                                        <?= ($area['area_id'] == $residentAreaId) ? ' (Your Area)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if (empty($allAreas)): ?>
                                                    <option value="">No areas available</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-calendar-alt mr-2"></i>Schedule Calendar
                            </h6>
                            <div>
                                <input type="month" id="calendar_month" class="form-control form-control-sm" 
                                       value="<?= date('Y-m') ?>" style="width: 160px;">
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Legend -->
                            <div class="d-flex flex-wrap mb-3 align-items-center small">
                                <div class="d-flex align-items-center mr-4 mb-2">
                                    <span class="collection-badge collection-domestic mr-2">Domestic</span>
                                    <span class="text-muted">General Waste</span>
                                </div>
                                <div class="d-flex align-items-center mr-4 mb-2">
                                    <span class="collection-badge collection-recycle mr-2">Recycle</span>
                                    <span class="text-muted">Recyclables</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span style="display:inline-block; width:16px; height:16px; background:#fff; border:2px solid #4e73df; border-radius:3px; margin-right: 5px;"></span>
                                    <span class="text-muted">Today</span>
                                </div>
                            </div>

                            <!-- Calendar Container -->
                            <div id="calendar" class="table-responsive">
                                <?php if (!$residentAreaId): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                        <p class="text-muted">You are not assigned to any collection area yet.</p>
                                        <p class="small text-muted">Please contact the administrator to update your profile.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                                        <p class="mt-2 text-muted">Loading schedule...</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Collection Info Cards -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-info shadow h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-info mb-3">
                                        <i class="fas fa-trash mr-2"></i>Domestic Waste Guidelines
                                    </h6>
                                    <ul class="small text-muted mb-0 pl-3">
                                        <li class="mb-2">Place bins outside by 7:00 AM on collection days</li>
                                        <li class="mb-2">Use proper garbage bags for all waste</li>
                                        <li class="mb-2">Do not overfill bins - close lid properly</li>
                                        <li>Keep hazardous materials separate</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 mb-4">
                            <div class="card border-left-success shadow h-100">
                                <div class="card-body">
                                    <h6 class="font-weight-bold text-success mb-3">
                                        <i class="fas fa-recycle mr-2"></i>Recyclable Waste Guidelines
                                    </h6>
                                    <ul class="small text-muted mb-0 pl-3">
                                        <li class="mb-2">Clean and rinse containers before recycling</li>
                                        <li class="mb-2">Flatten cardboard boxes to save space</li>
                                        <li class="mb-2">Separate paper, plastic, glass, and metal</li>
                                        <li>Remove caps from bottles</li>
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

    <!-- Lane Status Modal -->
    <div class="modal fade" id="laneStatusModal" tabindex="-1" role="dialog" aria-labelledby="laneStatusModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="laneStatusModalLabel">
                        <i class="fas fa-road mr-2"></i>Lane Collection Status
                    </h5>
                    <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body" id="laneStatusContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Loading lane status...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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

    <script>
    $(document).ready(function() {
        // Ensure dropdown works
        $('.dropdown-toggle').dropdown();

        // Selected area ID from PHP (from URL or resident's area)
        const selectedAreaId = <?= json_encode($selectedAreaId) ?>;

        // Area selector change - reload page with new area_id
        $('#area_selector').on('change', function() {
            const newAreaId = $(this).val();
            if (newAreaId) {
                window.location.href = 'resident_schedule.php?area_id=' + newAreaId;
            }
        });

        // Only load calendar if an area is selected
        if (selectedAreaId) {
            // Load calendar on page load
            fetchCalendarData();

            // Calendar month change
            $('#calendar_month').on('change', fetchCalendarData);
        }

        // Delegate click event for calendar days
        $(document).on('click', '.calendar-day', function() {
            const dateStr = $(this).data('date');
            if (dateStr) {
                showLaneStatus(dateStr);
            }
        });

        function fetchCalendarData() {
            const month = $('#calendar_month').val();

            if (!month) return;

            // Show loading
            $('#calendar').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading schedule...</p></div>');

            $.ajax({
                url: 'backend/fetch_resident_schedule.php',
                method: 'GET',
                data: { 
                    month: month,
                    area_id: selectedAreaId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#calendar').html('<p class="text-danger text-center py-4">' + response.error + '</p>');
                        return;
                    }
                    renderCalendar(response, month);
                },
                error: function() {
                    $('#calendar').html('<p class="text-danger text-center py-4">Failed to load schedule. Please try again.</p>');
                }
            });
        }

        function showLaneStatus(dateStr) {
            $('#laneStatusModalLabel').html('<i class="fas fa-road mr-2"></i>Lane Status - ' + dateStr);
            $('#laneStatusContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading lane status...</p></div>');
            $('#laneStatusModal').modal('show');

            $.ajax({
                url: 'backend/ajax_get_lane_status.php',
                method: 'GET',
                data: {
                    date: dateStr,
                    area_id: selectedAreaId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        $('#laneStatusContent').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>' + response.error + '</div>');
                        return;
                    }
                    // Handle no_schedule status
                    if (response.status === 'no_schedule') {
                        $('#laneStatusContent').html(
                            '<div class="text-center py-5">' +
                            '<i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>' +
                            '<h5 class="text-muted">No Collection Scheduled</h5>' +
                            '<p class="text-muted mb-0">There is no waste collection scheduled for this date in <strong>' + (response.area_name || 'this area') + '</strong>.</p>' +
                            '</div>'
                        );
                        return;
                    }
                    renderLaneStatus(response);
                },
                error: function() {
                    $('#laneStatusContent').html('<div class="alert alert-danger"><i class="fas fa-times-circle mr-2"></i>Failed to load lane status.</div>');
                }
            });
        }

        function renderLaneStatus(data) {
            if (!data.lanes || data.lanes.length === 0) {
                $('#laneStatusContent').html('<div class="text-center py-4"><i class="fas fa-info-circle fa-3x text-muted mb-3"></i><p class="text-muted">No lanes found for this area.</p></div>');
                return;
            }

            let html = '<div class="mb-3">';
            html += '<p class="text-muted small mb-2"><i class="fas fa-map-marker-alt mr-1"></i>' + (data.area_name || 'Selected Area') + '</p>';
            if (data.collection_type) {
                html += '<p class="mb-0"><strong>Collection Type:</strong> <span class="badge badge-' + (data.collection_type === 'Recycle' ? 'success' : 'primary') + '">' + data.collection_type + '</span></p>';
            }
            html += '</div>';

            html += '<div class="lane-list">';
            data.lanes.forEach(function(lane) {
                const statusClass = lane.status === 'Collected' ? 'collected' : 'pending';
                const statusBadge = lane.status === 'Collected' 
                    ? '<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Collected</span>'
                    : '<span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Pending</span>';

                html += '<div class="lane-status-item ' + statusClass + ' d-flex justify-content-between align-items-center">';
                html += '<div><i class="fas fa-road mr-2 text-muted"></i><strong>' + lane.lane_name + '</strong></div>';
                html += statusBadge;
                html += '</div>';
            });
            html += '</div>';

            // Summary
            const collected = data.lanes.filter(l => l.status === 'Collected').length;
            const pending = data.lanes.filter(l => l.status !== 'Collected').length;
            html += '<div class="mt-3 pt-3 border-top">';
            html += '<div class="row text-center">';
            html += '<div class="col-6"><span class="h4 text-success">' + collected + '</span><br><small class="text-muted">Collected</small></div>';
            html += '<div class="col-6"><span class="h4 text-warning">' + pending + '</span><br><small class="text-muted">Pending</small></div>';
            html += '</div></div>';

            $('#laneStatusContent').html(html);
        }

        function renderCalendar(schedules, month) {
            const parts = month.split('-');
            const year = parseInt(parts[0]);
            const monthNum = parseInt(parts[1]);
            const daysInMonth = new Date(year, monthNum, 0).getDate();
            
            let html = '<table class="table table-bordered mb-0"><thead><tr>';
            
            const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            weekdays.forEach(day => html += '<th class="text-center">' + day + '</th>');
            html += '</tr></thead><tbody><tr>';

            const firstDay = new Date(year, monthNum - 1, 1).getDay();
            for (let i = 0; i < firstDay; i++) {
                html += '<td class="bg-light"></td>';
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = month + '-' + String(day).padStart(2, '0');
                const currentDate = new Date(year, monthNum - 1, day);
                const isPast = currentDate < today;
                const isToday = currentDate.getTime() === today.getTime();
                
                // Find schedule for this date
                const schedule = schedules.find(s => s.date === dateStr);

                let bgColor = '#ffffff';
                let borderStyle = '';
                
                if (schedule) {
                    if (schedule.type === 'Recycle') {
                        bgColor = '#d4edda';
                        borderStyle = 'border-left: 4px solid #28a745;';
                    } else {
                        bgColor = '#cfe2ff';
                        borderStyle = 'border-left: 4px solid #0d6efd;';
                    }
                }

                let classes = 'calendar-day';
                if (isPast) classes += ' past-date';
                if (isToday) classes += ' today';

                // Add data-date attribute for click handling
                html += '<td class="' + classes + '" data-date="' + dateStr + '" style="background-color: ' + bgColor + '; ' + borderStyle + '">';
                html += '<div class="date-number">' + day;
                if (isToday) {
                    html += '<span class="today-badge">Today</span>';
                }
                html += '</div>';
                
                if (schedule) {
                    const badgeClass = schedule.type === 'Recycle' ? 'collection-recycle' : 'collection-domestic';
                    html += '<span class="collection-badge ' + badgeClass + '">' + schedule.type + '</span>';
                }
                
                html += '</td>';

                if ((day + firstDay) % 7 === 0 && day !== daysInMonth) {
                    html += '</tr><tr>';
                }
            }

            // Fill remaining cells
            const totalCells = firstDay + daysInMonth;
            const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let i = 0; i < remainingCells; i++) {
                html += '<td class="bg-light"></td>';
            }

            html += '</tr></tbody></table>';
            $('#calendar').html(html);
        }
    });
    </script>

</body>
</html>

<?php
// End output buffering and display the page
ob_end_flush();
?>
