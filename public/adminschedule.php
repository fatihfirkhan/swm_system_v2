<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Schedule Management - SWM Environment';
$currentPage = 'schedule';

// Get status/error messages
$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch areas and trucks for dropdowns
$area_query = $conn->query("SELECT area_id, taman_name FROM collection_area ORDER BY taman_name");
$truck_query = $conn->query("SELECT truck_id, truck_number FROM truck WHERE status = 'active' ORDER BY truck_number");

// Fetch all areas for the Calendar Monitor tab
$allAreas = [];
$areasQuery = "SELECT area_id, taman_name FROM collection_area ORDER BY taman_name ASC";
$areasResult = $conn->query($areasQuery);
if ($areasResult) {
    while ($area = $areasResult->fetch_assoc()) {
        $allAreas[] = $area;
    }
}

// Additional styles
$additionalStyles = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="css/adminschedule.css?v=' . time() . '" rel="stylesheet">
<style>
    /* Force disable all movement on calendar */
    #calendar td, #calendar td *, #calendar .calendar-day, #calendar .calendar-day * {
        transform: none !important;
        transition: none !important;
        animation: none !important;
        will-change: auto !important;
    }
    /* Remove hover effects that might cause perceived movement */
    #calendar .calendar-day:hover {
        box-shadow: none !important;
        cursor: pointer;
    }
    
    /* Calendar Monitor Styles */
    #monitorCalendar table { 
        width: 100%; 
        border-collapse: collapse; 
    }
    #monitorCalendar th, 
    #monitorCalendar td { 
        padding: 10px; 
        border: 1px solid #eef2f7; 
        min-height: 80px; 
        vertical-align: top; 
    }
    #monitorCalendar th {
        background-color: #f8f9fc;
        font-weight: 600;
        text-align: center;
    }
    .monitor-calendar-day {
        cursor: pointer;
        min-height: 80px;
        transition: all 0.2s ease;
    }
    .monitor-calendar-day:hover {
        box-shadow: 0 0 10px rgba(78, 115, 223, 0.3);
        transform: scale(1.02);
    }
    .monitor-calendar-day.past-date {
        opacity: 0.6;
        background-color: #f8f9fa !important;
    }
    .monitor-calendar-day.today {
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
    
    /* Nav Tabs Styling */
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
</style>
';

// Additional scripts that will be added at the bottom of the page
$additionalScripts = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="js/adminschedule.js?v=20251209092813"></script>
';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Status Messages -->
<div class="mb-4">
    <?php if ($status === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Schedule saved successfully
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error === 'duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Duplicate schedule detected
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($error === 'insert_failed'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Failed to save schedule
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Schedule Management</h1>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-tabs mb-0" id="scheduleTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="manage-tab" data-toggle="tab" href="#manageSchedules" 
           role="tab" aria-controls="manageSchedules" aria-selected="true">
            <i class="fas fa-calendar-plus mr-2"></i>Manage Schedules
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="monitor-tab" data-toggle="tab" href="#calendarMonitor" 
           role="tab" aria-controls="calendarMonitor" aria-selected="false">
            <i class="fas fa-eye mr-2"></i>Calendar Monitor
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="scheduleTabsContent">

    <!-- ======================= TAB 1: MANAGE SCHEDULES ======================= -->
    <div class="tab-pane fade show active" id="manageSchedules" role="tabpanel" aria-labelledby="manage-tab">
        
        <div class="d-flex justify-content-end mb-3">
            <a href="adminschedule.php?new=1" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> New Schedule
            </a>
        </div>

        <div class="row g-4">
            <!-- Schedule Form -->
            <div class="col-lg-5">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Add / Edit Schedule</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="../backend/handle_adminschedule.php" id="scheduleForm">
                            <div class="mb-3">
                                <label for="area_id" class="form-label">Select Area</label>
                                <select name="area_id" id="area_id" class="form-select select2" required>
                                    <option value="">-- Select Area --</option>
                                    <?php while ($row = $area_query->fetch_assoc()): ?>
                                        <option value="<?= $row['area_id'] ?>"><?= htmlspecialchars($row['taman_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-7 mb-3">
                                    <label for="collection_date" class="form-label">Collection Date</label>
                                    <input type="date" name="collection_date" id="collection_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-5 mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="collection_type" class="form-select" required>
                                        <option value="Domestic">Domestic</option>
                                        <option value="Recycle">Recycle</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Assign Truck</label>
                                <select name="truck_id" id="truck_id" class="form-select select2" required>
                                    <option value="">-- Select Truck --</option>
                                    <?php $truck_query->data_seek(0); while ($truck = $truck_query->fetch_assoc()): ?>
                                        <option value="<?= $truck['truck_id'] ?>"><?= htmlspecialchars($truck['truck_number']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <button type="submit" name="submit" class="btn btn-primary w-100">Save Schedule</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="col-lg-7">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Schedule Calendar</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3 gap-3">
                            <div style="flex:1">
                                <label for="calendar_area" class="form-label">Area</label>
                                <select id="calendar_area" class="form-select select2">
                                    <option value="">-- Select Area --</option>
                                    <?php
                                    $area_result = $conn->query("SELECT area_id, taman_name FROM collection_area ORDER BY taman_name");
                                    while ($area = $area_result->fetch_assoc()):
                                    ?>
                                        <option value="<?= $area['area_id'] ?>"><?= htmlspecialchars($area['taman_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div style="width:220px">
                                <label for="calendar_month" class="form-label">Month</label>
                                <input type="month" id="calendar_month" class="form-control" value="<?= date('Y-m') ?>">
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="d-flex gap-3 mb-3 align-items-center small">
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:16px; height:16px; background:#d4edda; border:2px solid #28a745; border-radius:50%;"></span>
                                <span>Recycle</span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span style="display:inline-block; width:16px; height:16px; background:#cfe2ff; border:2px solid #0d6efd; border-radius:50%;"></span>
                                <span>Domestic</span>
                            </div>
                            <small class="text-muted ms-2">Click on scheduled dates to edit</small>
                        </div>

                        <div id="calendar" class="table-responsive mt-2">
                            <p class="text-muted">Please select area and month to view schedule.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- End Tab 1 -->

    <!-- ======================= TAB 2: CALENDAR MONITOR ======================= -->
    <div class="tab-pane fade" id="calendarMonitor" role="tabpanel" aria-labelledby="monitor-tab">
        
        <!-- Area Switcher Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-left-info shadow">
                    <div class="card-body py-3">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Live Collection Monitor - Select Area</div>
                                <select id="monitor_area_selector" class="form-control form-control-lg font-weight-bold text-gray-800" style="max-width: 400px;">
                                    <option value="">-- Select Area --</option>
                                    <?php foreach ($allAreas as $area): ?>
                                        <option value="<?= $area['area_id'] ?>">
                                            <?= htmlspecialchars($area['taman_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-broadcast-tower fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monitor Calendar Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-calendar-alt mr-2"></i>Collection Calendar
                </h6>
                <div>
                    <input type="month" id="monitor_calendar_month" class="form-control form-control-sm" 
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
                    <div class="ml-auto">
                        <small class="text-info"><i class="fas fa-hand-pointer mr-1"></i>Click on any date to view lane status</small>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div id="monitorCalendar" class="table-responsive">
                    <div class="text-center py-5">
                        <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Please select an area to view the collection calendar.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- End Tab 2 -->

</div>
<!-- End Tab Content -->

<!-- Toast Notification Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            <!-- Message will be inserted here -->
        </div>
    </div>
</div>

<!-- Edit Modal (for Tab 1) -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="updateScheduleForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modal-date-info" class="mb-2 fw-bold"></p>
                    <div id="modal-truck-info" class="mb-3 p-2 bg-light rounded small">
                        <i class="fas fa-truck me-1"></i>
                        <span class="text-muted">Assigned Truck:</span>
                        <span id="modal-truck-name" class="ms-1"></span>
                    </div>

                    <div class="mb-3">
                        <label for="modal_collection_type" class="form-label">Collection Type</label>
                        <select id="modal_collection_type" name="collection_type" class="form-select" required>
                            <option value="Domestic">Domestic</option>
                            <option value="Recycle">Recycle</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign Truck</label>
                        <?php
                        $trucks = $conn->query("SELECT truck_id, truck_number FROM truck WHERE status = 'active' ORDER BY truck_number");
                        ?>
                        <select name="truck_id" id="modal_truck_id" class="form-select truck-dropdown select2" required>
                            <option value="">-- Select Truck --</option>
                            <?php $trucks->data_seek(0); while ($t = $trucks->fetch_assoc()): ?>
                                <option value="<?= $t['truck_id'] ?>"><?= htmlspecialchars($t['truck_number']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <input type="hidden" name="schedule_id" id="modal_schedule_id">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-danger" id="deleteScheduleBtn">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lane Status Modal (for Tab 2 - Calendar Monitor) -->
<div class="modal fade" id="laneStatusModal" tabindex="-1" role="dialog" aria-labelledby="laneStatusModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="laneStatusModalLabel">
                    <i class="fas fa-road mr-2"></i>Lane Collection Status
                </h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
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

<!-- Calendar Monitor JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calendar Monitor variables
    let monitorAreaId = null;

    // Area selector change
    document.getElementById('monitor_area_selector').addEventListener('change', function() {
        monitorAreaId = this.value;
        if (monitorAreaId) {
            fetchMonitorCalendarData();
        } else {
            document.getElementById('monitorCalendar').innerHTML = 
                '<div class="text-center py-5"><i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i><p class="text-muted">Please select an area to view the collection calendar.</p></div>';
        }
    });

    // Month change
    document.getElementById('monitor_calendar_month').addEventListener('change', function() {
        if (monitorAreaId) {
            fetchMonitorCalendarData();
        }
    });

    // Delegate click event for monitor calendar days
    document.getElementById('monitorCalendar').addEventListener('click', function(e) {
        const calendarDay = e.target.closest('.monitor-calendar-day');
        if (calendarDay) {
            const dateStr = calendarDay.dataset.date;
            if (dateStr) {
                showLaneStatus(dateStr);
            }
        }
    });

    function fetchMonitorCalendarData() {
        const month = document.getElementById('monitor_calendar_month').value;
        if (!month || !monitorAreaId) return;

        document.getElementById('monitorCalendar').innerHTML = 
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading schedule...</p></div>';

        fetch('../backend/fetch_resident_schedule.php?month=' + month + '&area_id=' + monitorAreaId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('monitorCalendar').innerHTML = 
                        '<p class="text-danger text-center py-4">' + data.error + '</p>';
                    return;
                }
                renderMonitorCalendar(data, month);
            })
            .catch(() => {
                document.getElementById('monitorCalendar').innerHTML = 
                    '<p class="text-danger text-center py-4">Failed to load schedule. Please try again.</p>';
            });
    }

    function showLaneStatus(dateStr) {
        document.getElementById('laneStatusModalLabel').innerHTML = 
            '<i class="fas fa-road mr-2"></i>Lane Status - ' + dateStr;
        document.getElementById('laneStatusContent').innerHTML = 
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading lane status...</p></div>';
        
        $('#laneStatusModal').modal('show');

        fetch('../backend/ajax_get_lane_status.php?date=' + dateStr + '&area_id=' + monitorAreaId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('laneStatusContent').innerHTML = 
                        '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>' + data.error + '</div>';
                    return;
                }
                // Handle no_schedule status
                if (data.status === 'no_schedule') {
                    document.getElementById('laneStatusContent').innerHTML = 
                        '<div class="text-center py-5">' +
                        '<i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>' +
                        '<h5 class="text-muted">No Collection Scheduled</h5>' +
                        '<p class="text-muted mb-0">There is no waste collection scheduled for this date in <strong>' + (data.area_name || 'this area') + '</strong>.</p>' +
                        '</div>';
                    return;
                }
                renderLaneStatus(data);
            })
            .catch(() => {
                document.getElementById('laneStatusContent').innerHTML = 
                    '<div class="alert alert-danger"><i class="fas fa-times-circle mr-2"></i>Failed to load lane status.</div>';
            });
    }

    function renderLaneStatus(data) {
        if (!data.lanes || data.lanes.length === 0) {
            document.getElementById('laneStatusContent').innerHTML = 
                '<div class="text-center py-4"><i class="fas fa-info-circle fa-3x text-muted mb-3"></i><p class="text-muted">No lanes found for this area.</p></div>';
            return;
        }

        let html = '<div class="mb-3">';
        html += '<p class="text-muted small mb-2"><i class="fas fa-map-marker-alt mr-1"></i>' + (data.area_name || 'Selected Area') + '</p>';
        if (data.collection_type) {
            const badgeClass = data.collection_type === 'Recycle' ? 'success' : 'primary';
            html += '<p class="mb-0"><strong>Collection Type:</strong> <span class="badge badge-' + badgeClass + '">' + data.collection_type + '</span></p>';
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

        document.getElementById('laneStatusContent').innerHTML = html;
    }

    function renderMonitorCalendar(schedules, month) {
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

            let classes = 'monitor-calendar-day';
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
        document.getElementById('monitorCalendar').innerHTML = html;
    }
});
</script>

<?php
// Get the buffered content
$pageContent = ob_get_clean();

// Render the admin template with our variables
require_once '../includes/admin_template.php';
?>
