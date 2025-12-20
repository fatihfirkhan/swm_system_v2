<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Resident Complaints - SWM Environment';
$currentPage = 'complaints';

// Additional styles
$additionalStyles = '';

// Additional scripts
$additionalScripts = '';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Resident Complaints</h1>
</div>

<!-- Placeholder Content -->
<div class="row">
    <div class="col-xl-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Manage Resident Complaints</h6>
            </div>
            <div class="card-body text-center py-5">
                <i class="fas fa-exclamation-circle fa-5x text-gray-300 mb-4"></i>
                <h4 class="text-gray-600">Resident Complaints Page</h4>
                <p class="text-gray-500">This page will allow you to view and manage resident complaints.</p>
                <p class="small text-muted">Content coming soon...</p>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/admin_template.php';
?>
