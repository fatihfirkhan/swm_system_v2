<?php defined('INCLUDED') or die(); ?>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-info sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="staff_dashboard.php">
        <div class="sidebar-brand-text mx-3" style="font-family: 'Nunito', sans-serif;">SWME Staff</div>
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
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Account
    </div>

    <!-- Nav Item - Profile -->
    <li class="nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
        <a class="nav-link" href="staff_profile.php">
            <i class="fas fa-fw fa-user"></i>
            <span>My Profile</span>
        </a>
    </li>

    <!-- Nav Item - Logout -->
    <li class="nav-item" style="padding: 0 1.5rem;">
        <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal" 
           style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); 
                  color: white; 
                  border-radius: 8px; 
                  box-shadow: 0 2px 4px rgba(238, 90, 111, 0.3);
                  transition: all 0.3s ease;
                  padding: 0.5rem 0.75rem;
                  font-size: 0.9rem;"
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(238, 90, 111, 0.4)'"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(238, 90, 111, 0.3)'">
            <i class="fas fa-fw fa-sign-out-alt"></i>
            <span>Logout</span>
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
