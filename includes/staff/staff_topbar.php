<?php defined('INCLUDED') or die(); ?>
<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Centered Logo -->
    <div class="topbar-logo-container">
        <img src="../assets/swme logo.png" alt="SWM Logo">
        <span class="logo-text">SWM ENVIRONMENT</span>
    </div>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">

        <!-- Nav Item - Notifications -->
        <?php
        // Count UNREAD notifications for Staff
        $notifCount = 0;
        $currentRole = $_SESSION['role'] ?? 'staff';
        $roleForQuery = ucfirst($currentRole); // 'Staff' for query
        
        // Create unique user ID: STA_workid (e.g., STA_STF005)
        $uniqueUserId = strtoupper(substr($currentRole, 0, 3)) . '_' . ($_SESSION['work_id'] ?? 0);
        
        // Get last_check time from notification_tracking using unique user ID
        $lastCheckTime = '2000-01-01 00:00:00';
        $trackStmt = $conn->prepare("SELECT last_check FROM notification_tracking WHERE user_id = ?");
        if ($trackStmt) {
            $trackStmt->bind_param("s", $uniqueUserId);
            $trackStmt->execute();
            $trackResult = $trackStmt->get_result()->fetch_assoc();
            if ($trackResult) {
                $lastCheckTime = $trackResult['last_check'];
            }
            $trackStmt->close();
        }
        
        // Count notifications newer than last_check for current role
        $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (target_role = ? OR target_role = 'All') AND time_created > ?");
        if ($notifStmt) {
            $notifStmt->bind_param("ss", $roleForQuery, $lastCheckTime);
            $notifStmt->execute();
            $notifResult = $notifStmt->get_result()->fetch_assoc();
            $notifCount = $notifResult['count'] ?? 0;
            $notifStmt->close();
        }
        ?>
        <li class="nav-item no-arrow">
            <a class="nav-link" href="notification_view.php" title="Notifications">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="badge badge-danger badge-counter"><?php echo $notifCount > 3 ? '3+' : $notifCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                    <?php echo htmlspecialchars($_SESSION['name'] ?? 'Staff'); ?>
                </span>
                <i class="fas fa-user-circle fa-fw"></i>
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="userDropdown">
                <a class="dropdown-item" href="staff_profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    Profile
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>
    </ul>
</nav>
<!-- End of Topbar -->
