<?php defined('INCLUDED') or die(); ?>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="admin_dashboard.php">
        <div class="sidebar-brand-text mx-3" style="font-family: 'Nunito', sans-serif;">SWME Admin</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
        <a class="nav-link" href="admin_dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Management
    </div>

    <!-- Nav Item - Schedule -->
    <li class="nav-item <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
        <a class="nav-link" href="adminschedule.php">
            <i class="fas fa-fw fa-calendar-alt"></i>
            <span>Schedule Management</span>
        </a>
    </li>

    <!-- Nav Item - Staff -->
    <li class="nav-item <?php echo $currentPage === 'staff' ? 'active' : ''; ?>">
        <a class="nav-link" href="staff_management.php">
            <i class="fas fa-fw fa-users"></i>
            <span>Staff Management</span>
        </a>
    </li>

    <!-- Nav Item - Area -->
    <li class="nav-item <?php echo $currentPage === 'area' ? 'active' : ''; ?>">
        <a class="nav-link" href="area_management.php">
            <i class="fas fa-fw fa-map-marked-alt"></i>
            <span>Area Management</span>
        </a>
    </li>

    <!-- Nav Item - Truck Management -->
    <li class="nav-item <?php echo $currentPage === 'trucks' ? 'active' : ''; ?>">
        <a class="nav-link" href="truck_management.php">
            <i class="fas fa-fw fa-truck"></i>
            <span>Truck Management</span>
        </a>
    </li>

    <!-- Nav Item - Notification -->
    <li class="nav-item <?php echo $currentPage === 'notification' ? 'active' : ''; ?>">
        <a class="nav-link" href="notification_broadcast.php">
            <i class="fas fa-fw fa-bell"></i>
            <span>Notification Broadcast</span>
        </a>
    </li>

    <!-- Nav Item - Resident Complaints -->
    <li class="nav-item <?php echo $currentPage === 'complaints' ? 'active' : ''; ?>">
        <a class="nav-link" href="admin_complaints.php">
            <i class="fas fa-fw fa-exclamation-circle"></i>
            <span>Resident Complaints</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Reports
    </div>

    <!-- Nav Item - Reports -->
    <li class="nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
        <a class="nav-link" href="reports.php">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Collection Reports</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->