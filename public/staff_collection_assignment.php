<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Lane Assignments - SWM Environment';
$currentPage = 'assignments';

// Get staff user_id from session
$staff_user_id = $_SESSION['user_id'];

// Find the truck assigned to this staff member
$truck_query = $conn->prepare("SELECT truck_id FROM truck_staff WHERE user_id = ?");
$truck_query->bind_param("s", $staff_user_id);
$truck_query->execute();
$truck_result = $truck_query->get_result();

$assigned_truck_id = null;
$has_truck = false;

if ($truck_result->num_rows > 0) {
    $truck_row = $truck_result->fetch_assoc();
    $assigned_truck_id = $truck_row['truck_id'];
    $has_truck = true;
}

// Additional styles
$additionalStyles = '
<style>
    /* Weekly Date Strip */
    .weekly-nav {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 1rem;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .weekly-nav::-webkit-scrollbar {
        height: 4px;
    }
    .weekly-nav::-webkit-scrollbar-thumb {
        background: #d1d3e2;
        border-radius: 10px;
    }
    .date-strip {
        display: flex;
        gap: 0.5rem;
        min-width: max-content;
    }
    .date-box {
        flex: 0 0 auto;
        min-width: 70px;
        padding: 0.75rem;
        text-align: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        background: #f8f9fc;
        text-decoration: none;
        color: #5a5c69;
    }
    .date-box:hover {
        background: #e3e6f0;
        transform: translateY(-2px);
    }
    .date-box.active {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        color: white;
        border-color: #4e73df;
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.4);
    }
    .date-box.today {
        border-color: #1cc88a;
    }
    .date-day {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .date-num {
        font-size: 1.25rem;
        font-weight: bold;
    }
    .calendar-jump-btn {
        margin-left: 0.5rem;
        min-width: 70px;
    }
    
    /* Job Summary Card */
    .job-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 1.5rem;
        color: white;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    .truck-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .truck-icon {
        font-size: 2.5rem;
        opacity: 0.9;
    }
    .truck-details h4 {
        margin: 0;
        font-weight: bold;
    }
    .collection-type-badge {
        display: inline-block;
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }
    .badge-recycle {
        background: rgba(28, 200, 138, 0.3);
        border: 2px solid rgba(28, 200, 138, 0.8);
    }
    .badge-domestic {
        background: rgba(54, 185, 204, 0.3);
        border: 2px solid rgba(54, 185, 204, 0.8);
    }
    .area-name {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 1rem 0;
    }
    .progress-section {
        margin-top: 1rem;
    }
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        opacity: 0.95;
    }
    .progress {
        height: 10px;
        border-radius: 10px;
        background: rgba(255,255,255,0.2);
    }
    .progress-bar {
        background: white;
        border-radius: 10px;
    }
    
    /* Lane Cards */
    .lane-card {
        transition: all 0.3s ease;
        border-left: 4px solid #e3e6f0;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .lane-card.completed {
        border-left-color: #1cc88a;
        background: linear-gradient(to right, #f8fff9 0%, white 100%);
    }
    .lane-card.pending {
        border-left-color: #f6c23e;
        background: linear-gradient(to right, #fffef8 0%, white 100%);
    }
    .lane-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2e59d9;
        margin-bottom: 0.5rem;
    }
    .lane-status-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .status-badge {
        display: inline-block;
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .badge-completed {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .badge-pending {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    .timestamp {
        font-size: 0.75rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 64px;
        height: 36px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #f6c23e;
        transition: .4s;
        border-radius: 36px;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 28px;
        width: 28px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    input:checked + .toggle-slider {
        background-color: #1cc88a;
    }
    input:checked + .toggle-slider:before {
        transform: translateX(28px);
    }
    input:disabled + .toggle-slider {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #858796;
    }
    .empty-state-icon {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    .empty-state h4 {
        color: #5a5c69;
        margin-bottom: 0.5rem;
    }
    .empty-state p {
        color: #858796;
    }
    
    /* Mobile Responsive */
    @media (max-width: 576px) {
        .job-summary {
            padding: 1rem;
        }
        .truck-icon {
            font-size: 2rem;
        }
        .area-name {
            font-size: 1.2rem;
        }
        .lane-card .card-body {
            padding: 1rem;
        }
    }
    
    /* Hidden Date Picker */
    #hidden_date_picker {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
</style>
';

// Additional scripts
$additionalScripts = '
<script>
$(document).ready(function() {
    // Check if user has truck assigned
    const hasTruck = ' . ($has_truck ? 'true' : 'false') . ';
    
    if (!hasTruck) {
        // Show "No Truck Assigned" empty state
        $("#job-summary").hide();
        $("#lanes-container").html(`
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-truck-loading"></i>
                </div>
                <h4>No Truck Assigned</h4>
                <p>You are not currently assigned to any truck.<br>Please contact the administrator for assistance.</p>
            </div>
        `);
        return; // Stop further execution
    }
    
    // Get selected date from URL or default to today
    const urlParams = new URLSearchParams(window.location.search);
    const selectedDate = urlParams.get("date") || new Date().toISOString().split("T")[0];
    $("#collection_date").val(selectedDate);
    
    // Load lanes on page load
    loadLanes();
    
    // Handle date change from hidden picker
    $("#hidden_date_picker").on("change", function() {
        const newDate = $(this).val();
        window.location.href = "staff_collection_assignment.php?date=" + newDate;
    });
    
    // Handle calendar jump button
    $("#calendar_jump_btn").on("click", function() {
        $("#hidden_date_picker").trigger("click");
    });
    
    // Handle toggle switch changes
    $(document).on("change", ".lane-toggle", function() {
        const $toggle = $(this);
        const laneId = $toggle.data("lane-id");
        const laneName = $toggle.data("lane-name");
        const scheduleId = $toggle.data("schedule-id");
        const newStatus = $toggle.is(":checked") ? "Collected" : "Pending";
        
        // Disable toggle during update
        $toggle.prop("disabled", true);
        
        $.ajax({
            url: "../backend/handle_lane_status.php",
            method: "POST",
            dataType: "json",
            data: {
                action: "update_status",
                schedule_id: scheduleId,
                lane_name: laneName,
                status: newStatus
            },
            success: function(response) {
                if (response.status === "success") {
                    showToast("Success", response.message, "success");
                    // Reload to update progress bar and card styling
                    loadLanes();
                } else {
                    showToast("Error", response.message, "error");
                    // Revert toggle
                    $toggle.prop("checked", !$toggle.is(":checked"));
                }
            },
            error: function(xhr) {
                showToast("Error", "Failed to update lane status", "error");
                // Revert toggle
                $toggle.prop("checked", !$toggle.is(":checked"));
            },
            complete: function() {
                $toggle.prop("disabled", false);
            }
        });
    });
    
    function loadLanes() {
        const selectedDate = $("#collection_date").val();
        
        if (!selectedDate) {
            $("#job-summary").hide();
            $("#lanes-container").html("<p class=\"text-muted text-center p-4\">Please select a date</p>");
            return;
        }
        
        // Show loading
        $("#job-summary").hide();
        $("#lanes-container").html("<div class=\"text-center p-4\"><i class=\"fas fa-spinner fa-spin fa-2x text-primary\"></i></div>");
        
        $.ajax({
            url: "../backend/handle_lane_status.php",
            method: "POST",
            dataType: "json",
            data: {
                action: "get_lanes",
                collection_date: selectedDate
            },
            success: function(response) {
                if (response.status === "success") {
                    displayLanes(response.data);
                } else {
                    // Friendly "No Tasks" empty state
                    $("#job-summary").hide();
                    $("#lanes-container").html(`
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-mug-hot"></i>
                            </div>
                            <h4>No Collection Tasks</h4>
                            <p>No collection tasks assigned for this date.<br>Enjoy your break!</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $("#job-summary").hide();
                $("#lanes-container").html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Failed to load lane data. Please try again.
                    </div>
                `);
            }
        });
    }
    
    function displayLanes(data) {
        if (!data.lanes || data.lanes.length === 0) {
            $("#job-summary").hide();
            $("#lanes-container").html(`
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h4>No Lanes Found</h4>
                    <p>No lanes configured for this area.</p>
                </div>
            `);
            return;
        }
        
        const isToday = data.is_today;
        const scheduleId = data.schedule_id;
        const areaName = data.area_name || "Unknown Area";
        const truckNumber = data.truck_number || "N/A";
        const collectionType = data.collection_type || "Domestic";
        
        // Calculate progress
        const totalLanes = data.lanes.length;
        const completedLanes = data.lanes.filter(lane => lane.status === "Collected").length;
        const progressPercent = totalLanes > 0 ? Math.round((completedLanes / totalLanes) * 100) : 0;
        
        // Show Job Summary Card
        const typeBadgeClass = collectionType === "Recycle" ? "badge-recycle" : "badge-domestic";
        const summaryHtml = `
            <div class="truck-info">
                <div class="truck-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="truck-details">
                    <h4>${truckNumber}</h4>
                    <span class="collection-type-badge ${typeBadgeClass}">
                        <i class="fas fa-${collectionType === "Recycle" ? "recycle" : "trash"}"></i> ${collectionType}
                    </span>
                </div>
            </div>
            <div class="area-name">
                <i class="fas fa-map-marker-alt me-2"></i>${areaName}
            </div>
            <div class="progress-section">
                <div class="progress-label">
                    <span><i class="fas fa-tasks me-1"></i>Progress</span>
                    <span><strong>${completedLanes}/${totalLanes}</strong> Completed</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: ${progressPercent}%" aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        `;
        $("#job-summary").html(summaryHtml).show();
        
        // Display Read-Only Notice if not today
        let html = "";
        if (!isToday) {
            html += `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Read-Only Mode:</strong> This schedule is not for today. Toggles are disabled.
                </div>
            `;
        }
        
        // Display Lanes
        data.lanes.forEach(function(lane) {
            const isChecked = lane.status === "Collected";
            const statusClass = lane.status === "Collected" ? "completed" : (lane.status === "Pending" ? "pending" : "");
            const statusBadge = lane.status === "Collected" ? 
                `<span class="status-badge badge-completed"><i class="fas fa-check-circle me-1"></i>Completed</span>` : 
                (lane.status === "Pending" ? 
                    `<span class="status-badge badge-pending"><i class="fas fa-clock me-1"></i>Pending</span>` : 
                    `<span class="status-badge badge-pending"><i class="fas fa-clock me-1"></i>Pending</span>`);
            const disabledAttr = isToday ? "" : "disabled";
            const timestampInfo = (lane.status && lane.update_time) ? 
                `<div class="timestamp"><i class="fas fa-history"></i>Updated ${lane.update_time}</div>` : "";
            
            html += `
                <div class="lane-card card ${statusClass}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="lane-status-info flex-grow-1">
                                <div class="lane-name">
                                    <i class="fas fa-road me-2"></i>${lane.lane_name}
                                </div>
                                ${statusBadge}
                                ${timestampInfo}
                            </div>
                            <div class="ms-3">
                                <label class="toggle-switch">
                                    <input type="checkbox" 
                                           class="lane-toggle" 
                                           data-lane-id="${lane.lane_id}" 
                                           data-lane-name="${lane.lane_name}" 
                                           data-schedule-id="${scheduleId}"
                                           ${isChecked ? "checked" : ""} 
                                           ${disabledAttr}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $("#lanes-container").html(html);
    }
    
    function showToast(title, message, type) {
        const bgClass = type === "success" ? "bg-success" : "bg-danger";
        const iconClass = type === "success" ? "fa-check-circle" : "fa-exclamation-circle";
        
        const toastHtml = `
            <div class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${iconClass} me-2"></i><strong>${title}:</strong> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        $("#toast-container").append(toastHtml);
        const $toast = $("#toast-container .toast:last");
        const toast = new bootstrap.Toast($toast[0]);
        toast.show();
        
        // Remove from DOM after hidden
        $toast.on("hidden.bs.toast", function() {
            $(this).remove();
        });
    }
});
</script>
';

// Start output buffering to capture the page content
ob_start();

// Get selected date from URL or default to today
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_timestamp = strtotime($selected_date);

// Format month and year for display
$selected_month_year = date('F Y', $selected_timestamp);

// Generate 7-day strip (3 days before, selected day, 3 days after)
$date_strip = [];
for ($i = -3; $i <= 3; $i++) {
    $date_ts = strtotime("$i days", $selected_timestamp);
    $date_strip[] = [
        'timestamp' => $date_ts,
        'date' => date('Y-m-d', $date_ts),
        'day' => date('D', $date_ts),
        'num' => date('j', $date_ts),
        'is_today' => date('Y-m-d', $date_ts) === date('Y-m-d'),
        'is_selected' => date('Y-m-d', $date_ts) === $selected_date
    ];
}
?>

<!-- Page Content -->
<div class="d-sm-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tasks me-2"></i>Lane Assignments</h1>
</div>

<!-- Weekly Date Navigation Strip (Always visible) -->
<div class="weekly-nav">
    <div class="d-flex align-items-center">
        <div class="date-strip flex-grow-1">
            <?php foreach ($date_strip as $day): ?>
                <a href="staff_collection_assignment.php?date=<?= $day['date'] ?>" 
                   class="date-box <?= $day['is_selected'] ? 'active' : '' ?> <?= $day['is_today'] ? 'today' : '' ?>">
                    <div class="date-day"><?= $day['day'] ?></div>
                    <div class="date-num"><?= $day['num'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        <h5 class="m-0 font-weight-bold text-primary mx-3"><?= $selected_month_year ?></h5>
        <button type="button" class="btn btn-outline-primary calendar-jump-btn" id="calendar_jump_btn" title="Jump to specific date">
            <i class="fas fa-calendar-alt"></i>
        </button>
    </div>
</div>

<!-- Hidden Date Picker for Calendar Jump -->
<input type="date" id="hidden_date_picker" value="<?= htmlspecialchars($selected_date) ?>">

<!-- Hidden field to store selected date for AJAX -->
<input type="hidden" id="collection_date" value="<?= htmlspecialchars($selected_date) ?>">

<!-- Job Summary Card (Hidden by default, shown by JS when data loads) -->
<div id="job-summary" class="job-summary" style="display: none;">
    <!-- Content populated by JavaScript -->
</div>

<!-- Lanes Container -->
<div class="card shadow">
    <div class="card-body">
        <div id="lanes-container">
            <p class="text-muted text-center p-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</p>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<?php
// Get the buffered content
$pageContent = ob_get_clean();

// Render the staff template
require_once '../includes/staff/staff_template.php';
?>
