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
$area_query = $conn->query("SELECT area_id, taman_name FROM collection_area");
$truck_query = $conn->query("SELECT truck_id, truck_number FROM truck WHERE status = 'active' ORDER BY truck_number");

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

<!-- Page Content -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Schedule Management</h1>
    <a href="adminschedule.php?new=1" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
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
                            $area_result = $conn->query("SELECT area_id, taman_name FROM collection_area");
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

<!-- Edit Modal -->
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

<?php
// Get the buffered content
$pageContent = ob_get_clean();

// Render the admin template with our variables
require_once '../includes/admin_template.php';
?>