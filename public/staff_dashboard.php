<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as staff
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Staff Dashboard - SWM Environment';
$currentPage = 'dashboard';

// Additional styles
$additionalStyles = '';

// Additional scripts
$additionalScripts = '';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Staff Dashboard</h1>
</div>

<!-- Content Row -->
<div class="row">

    <!-- Welcome Card -->
    <div class="col-xl-12 col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-info">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Staff'); ?>!</h6>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <i class="fas fa-tools fa-5x text-info mb-3"></i>
                    <h4 class="text-gray-800 mb-3">Your Staff Dashboard</h4>
                    <p class="text-gray-600">This is your staff dashboard. Content will be added here soon.</p>
                    <hr>
                    <p class="small text-muted">Use the sidebar menu to navigate to your collection assignments and schedule.</p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Placeholder Cards Row -->
<div class="row">

    <!-- Today's Tasks Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Today's Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Coming Soon</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Collections Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Pending Collections</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Coming Soon</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-trash-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Today Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Completed Today</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Coming Soon</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
