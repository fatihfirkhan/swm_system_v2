<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as staff
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Collection Schedule - SWM Environment';
$currentPage = 'schedule';

// Fetch areas for dropdown
$area_query = $conn->query("SELECT area_id, taman_name FROM collection_area ORDER BY taman_name");

// Additional styles
$additionalStyles = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="css/adminschedule.css?v=' . time() . '" rel="stylesheet">
<style>
    /* View-only calendar styles */
    #calendar td, #calendar td *, #calendar .calendar-day, #calendar .calendar-day * {
        transform: none !important;
        transition: none !important;
        animation: none !important;
        will-change: auto !important;
    }
    /* Remove hover effects - view only */
    #calendar .calendar-day:hover {
        box-shadow: none !important;
        cursor: default !important;
    }
    .calendar-day {
        cursor: default !important;
    }
</style>
';

// Additional scripts
$additionalScripts = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $(".select2").select2({ width: "100%" });

    // Calendar controls
    $("#calendar_area, #calendar_month").on("change", fetchCalendarData);

    function fetchCalendarData() {
        const areaId = $("#calendar_area").val();
        const month = $("#calendar_month").val();

        if (!areaId || !month) {
            $("#calendar").html("<p class=\"text-muted\">Please select area and month to view schedule.</p>");
            return;
        }

        // Show loading indicator
        $("#calendar").html("<div class=\"text-center p-4\"><i class=\"fas fa-spinner fa-spin fa-2x text-info\"></i><p class=\"mt-2 text-muted\">Loading schedule...</p></div>");

        $.ajax({
            url: "../backend/fetch_calendar_schedule.php",
            method: "GET",
            data: { area_id: areaId, month: month },
            success: function(response) {
                let data = [];
                try { 
                    data = JSON.parse(response); 
                } catch (e) { 
                    $("#calendar").html("<p class=\"text-danger\">Invalid schedule data.</p>"); 
                    return; 
                }
                renderCalendar(data, month);
            },
            error: function() {
                $("#calendar").html("<p class=\"text-danger\">Failed to load schedule data.</p>");
            }
        });
    }

    function renderCalendar(schedules, month) {
        const parts = month.split("-");
        const daysInMonth = new Date(parts[0], parts[1], 0).getDate();
        let calendarHTML = "<table class=\"table table-bordered\"><thead class=\"table-light\"><tr>";

        const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        weekdays.forEach(day => calendarHTML += "<th class=\"text-center\">" + day + "</th>");
        calendarHTML += "</tr></thead><tbody><tr>";

        const firstDay = new Date(month + "-01").getDay();
        for (let i = 0; i < firstDay; i++) calendarHTML += "<td></td>";

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = month + "-" + String(day).padStart(2, "0");
            const schedule = schedules.find(s => s.collection_date === dateStr);
            const currentDate = new Date(dateStr);
            const isPast = currentDate < today;
            const isToday = currentDate.getTime() === today.getTime();

            let bgColor = "#ffffff";
            let borderColor = "";
            if (schedule) {
                if (schedule.collection_type === "Recycle") {
                    bgColor = "#d4edda";
                    borderColor = "border-left: 4px solid #28a745;";
                } else {
                    bgColor = "#cfe2ff";
                    borderColor = "border-left: 4px solid #0d6efd;";
                }
            }

            let classes = "calendar-day";
            if (isPast) classes += " past-date";
            if (isToday) classes += " today";

            let todayBorder = isToday ? "border: 2px solid #17a2b8 !important;" : "";

            calendarHTML += "<td style=\"vertical-align: top; background-color: " + bgColor + "; " + borderColor + " padding: 8px; " + todayBorder + "\" class=\"" + classes + "\" id=\"cell-" + dateStr + "\">";
            calendarHTML += "<div class=\"date-content\"><strong style=\"font-size: 1.1em;\">" + day + "</strong>";
            
            if (isToday) {
                calendarHTML += " <span class=\"badge badge-info\" style=\"font-size: 0.7em;\">Today</span>";
            }
            
            calendarHTML += "<br>";
            
            if (schedule) {
                calendarHTML += "<small class=\"text-dark fw-bold\">" + schedule.collection_type + "</small>";
                if (schedule.truck_number) {
                    calendarHTML += "<br><small class=\"text-muted\"><i class=\"fas fa-truck\"></i> " + schedule.truck_number + "</small>";
                }
            }
            calendarHTML += "</div></td>";

            if ((day + firstDay) % 7 === 0) calendarHTML += "</tr><tr>";
        }

        calendarHTML += "</tr></tbody></table>";
        $("#calendar").html(calendarHTML);
    }
});
</script>
';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Collection Schedule</h1>
</div>

<!-- Calendar View Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-info">Schedule Calendar</h6>
    </div>
    <div class="card-body">
        <!-- Filter Controls -->
        <div class="row mb-3">
            <div class="col-md-6 mb-2 mb-md-0">
                <label for="calendar_area" class="form-label">Select Area</label>
                <select id="calendar_area" class="form-select select2">
                    <option value="">-- Select Area --</option>
                    <?php while ($area = $area_query->fetch_assoc()): ?>
                        <option value="<?= $area['area_id'] ?>"><?= htmlspecialchars($area['taman_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="calendar_month" class="form-label">Select Month</label>
                <input type="month" id="calendar_month" class="form-control" value="<?= date('Y-m') ?>">
            </div>
        </div>

        <!-- Legend -->
        <div class="d-flex flex-wrap gap-3 mb-3 align-items-center small">
            <div class="d-flex align-items-center mr-3">
                <span style="display:inline-block; width:16px; height:16px; background:#d4edda; border:2px solid #28a745; border-radius:50%; margin-right: 5px;"></span>
                <span>Recycle</span>
            </div>
            <div class="d-flex align-items-center mr-3">
                <span style="display:inline-block; width:16px; height:16px; background:#cfe2ff; border:2px solid #0d6efd; border-radius:50%; margin-right: 5px;"></span>
                <span>Domestic</span>
            </div>
            <div class="d-flex align-items-center">
                <span style="display:inline-block; width:16px; height:16px; background:#fff; border:2px solid #17a2b8; border-radius:50%; margin-right: 5px;"></span>
                <span>Today</span>
            </div>
        </div>

        <!-- Calendar Container -->
        <div id="calendar" class="table-responsive mt-2">
            <p class="text-muted">Please select an area and month to view the collection schedule.</p>
        </div>
    </div>
</div>

<!-- Schedule Info Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-info">Collection Types Information</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card border-left-primary h-100">
                    <div class="card-body">
                        <h6 class="font-weight-bold text-primary">Domestic Waste</h6>
                        <p class="small text-muted mb-0">Regular household waste collection. Please ensure all waste is properly bagged and placed at the designated collection point.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-left-success h-100">
                    <div class="card-body">
                        <h6 class="font-weight-bold text-success">Recyclable Waste</h6>
                        <p class="small text-muted mb-0">Recyclable materials collection including paper, plastic, glass, and metal. Please separate recyclables from general waste.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/staff/staff_template.php';
?>
