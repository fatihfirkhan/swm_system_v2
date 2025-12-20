<?php
// Use the shared template includes
$page_title = 'Select Role - SWM Environment';
require __DIR__ . '/../includes/template_head.php';
require __DIR__ . '/../includes/template_navbar.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8">
            <div class="card o-hidden border-0 shadow-lg my-5">
                <div class="card-body p-0">
                    <div class="p-5">
                        <div class="text-center">
                            <h1 class="h4 text-gray-900 mb-4">Select Your Role</h1>
                        </div>
                        <a href="login.php?role=resident" class="btn btn-primary btn-user btn-block mb-3">
                            <i class="fas fa-home fa-fw"></i> Resident
                        </a>
                        <a href="login.php?role=staff_auto" class="btn btn-info btn-user btn-block">
                            <i class="fas fa-user-tie fa-fw"></i> Staff
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php require __DIR__ . '/../includes/template_footer.php'; ?>
