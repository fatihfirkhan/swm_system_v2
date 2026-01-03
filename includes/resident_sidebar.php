<?php defined('INCLUDED') or die(); ?>
<!-- Sidebar -->
<ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar" style="background: linear-gradient(180deg, #4e73df 10%, #2e59d9 100%);">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="resident_dashboard.php">
        <div class="sidebar-brand-text mx-3" style="font-family: 'Nunito', sans-serif;">SWME Resident</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
        <a class="nav-link" href="resident_dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        My Services
    </div>

    <!-- Nav Item - My Collection Schedule -->
    <li class="nav-item <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
        <a class="nav-link" href="resident_schedule.php">
            <i class="fas fa-fw fa-calendar-alt"></i>
            <span>My Collection Schedule</span>
        </a>
    </li>

    <!-- Nav Item - Report Issue / Complaint -->
    <li class="nav-item <?php echo $currentPage === 'complaint' ? 'active' : ''; ?>">
        <a class="nav-link" href="resident_complaints.php">
            <i class="fas fa-fw fa-exclamation-circle"></i>
            <span>Report Issue / Complaint</span>
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
        <a class="nav-link" href="resident_profile.php">
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
