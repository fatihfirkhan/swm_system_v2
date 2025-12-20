<?php defined('INCLUDED') or die(); ?>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-info sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="staff_dashboard.php">
        <div class="sidebar-brand-text mx-3" style="font-family: 'Nunito', sans-serif;">SWM Staff</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
        <a class="nav-link" href="staff_dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        My Work
    </div>

    <!-- Nav Item - Collection Assignment -->
    <li class="nav-item <?php echo $currentPage === 'assignment' ? 'active' : ''; ?>">
        <a class="nav-link" href="staff_collection_assignment.php">
            <i class="fas fa-fw fa-tasks"></i>
            <span>Collection Assignment</span>
        </a>
    </li>

    <!-- Nav Item - Schedule View -->
    <li class="nav-item <?php echo $currentPage === 'schedule-view' ? 'active' : ''; ?>">
        <a class="nav-link" href="staff_schedule_view.php">
            <i class="fas fa-fw fa-calendar"></i>
            <span>Schedule View</span>
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
