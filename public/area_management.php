<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define constant for includes
define('INCLUDED', true);

// Start the session at the very beginning
session_start();

// Check if user is logged in as admin
$role = strtolower($_SESSION['role'] ?? '');
$workId = $_SESSION['work_id'] ?? $_SESSION['work_ID'] ?? null;

if (empty($workId) || $role !== 'admin') {
    $_SESSION['error'] = 'You need to be logged in as an admin to access this page.';
    header('Location: login.php');
    exit();
}

// Set page title and current page for navigation
$pageTitle = 'Area Management - SWM Environment';
$currentPage = 'area';

// Try to connect to database
try {
    require_once '../includes/db.php';
    
    // Fetch areas with lane counts
    $areasQuery = "SELECT ca.area_id, ca.taman_name, ca.postcode, 
                   COUNT(cl.lane_id) as lane_count 
                   FROM collection_area ca 
                   LEFT JOIN collection_lane cl ON ca.area_id = cl.area_id 
                   GROUP BY ca.area_id 
                   ORDER BY ca.taman_name";
    
    $areasResult = $conn->query($areasQuery);
    
    if ($areasResult === false) {
        throw new Exception("Error fetching areas: " . $conn->error);
    }
    
    $areas = $areasResult->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Error in area_management.php: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the page. Please try again later.";
    header('Location: admin_dashboard.php');
    exit();
}

// Start output buffering
ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- SWM Environment custom theme -->
    <link href="css/swm-custom.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <?php include '../includes/admin_topbar.php'; ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Area Management</h1>
                        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addAreaModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Area
                        </button>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Areas Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Collection Areas</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="areasTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Area Name</th>
                                            <th>Postcode</th>
                                            <th>Total Lanes</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($areas as $area): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($area['taman_name']); ?></td>
                                            <td><?php echo htmlspecialchars($area['postcode']); ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $area['lane_count']; ?> Lanes</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary add-lanes" 
                                                        data-area-id="<?php echo $area['area_id']; ?>"
                                                        data-area-name="<?php echo htmlspecialchars($area['taman_name']); ?>">
                                                    <i class="fas fa-road"></i> Add Lanes
                                                </button>
                                                <button class="btn btn-sm btn-info view-lanes" 
                                                        data-area-id="<?php echo $area['area_id']; ?>"
                                                        data-area-name="<?php echo htmlspecialchars($area['taman_name']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-area" 
                                                        data-area-id="<?php echo $area['area_id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <?php include '../includes/template_footer.php'; ?>

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Add Area Modal -->
    <div class="modal fade" id="addAreaModal" tabindex="-1" role="dialog" aria-labelledby="addAreaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAreaModalLabel">Add New Area</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="backend/area_actions.php?action=add" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="taman_name">Taman Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="taman_name" name="taman_name" required placeholder="e.g., Taman Seri Indah">
                        </div>
                        <div class="form-group">
                            <label for="postcode">Postcode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="postcode" name="postcode" required placeholder="e.g., 43000" pattern="[0-9]{5}" maxlength="5">
                            <small class="form-text text-muted">5-digit postcode</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Area</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Lanes Modal -->
    <div class="modal fade" id="addLanesModal" tabindex="-1" role="dialog" aria-labelledby="addLanesModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLanesModalLabel">Bulk Create Lanes for <span id="areaNameLabel"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="backend/area_actions.php?action=add_lanes" method="POST">
                    <input type="hidden" name="area_id" id="laneAreaId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="lane_base_name">Lane Base Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lane_base_name" name="lane_base_name" required placeholder="e.g., Jalan Kencana">
                            <small class="form-text text-muted">Base name for lanes. Numbers will be appended automatically.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_number">Start Number <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="start_number" name="start_number" required min="1" value="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_number">End Number <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="end_number" name="end_number" required min="1" value="10">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>Preview:</strong> 
                            <span id="previewText">Enter values to see preview</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Lanes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Lanes Modal -->
    <div class="modal fade" id="viewLanesModal" tabindex="-1" role="dialog" aria-labelledby="viewLanesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLanesModalLabel">Lanes in <span id="viewAreaNameLabel"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="lanesContent">
                        <p class="text-center">Loading lanes...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Area Modal -->
    <div class="modal fade" id="deleteAreaModal" tabindex="-1" role="dialog" aria-labelledby="deleteAreaModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAreaModalLabel">Delete Area</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this area? This will also delete all associated lanes.</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="deleteAreaForm" action="backend/area_actions.php?action=delete" method="POST" style="display: inline;">
                        <input type="hidden" name="area_id" id="deleteAreaId">
                        <button type="submit" class="btn btn-danger">Delete Area</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>
    
    <!-- Page level plugins -->
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#areasTable').DataTable({
            "pageLength": 10,
            "order": [[0, 'asc']]
        });

        // Handle Add Lanes button click
        $('.add-lanes').click(function() {
            var areaId = $(this).data('area-id');
            var areaName = $(this).data('area-name');
            
            $('#laneAreaId').val(areaId);
            $('#areaNameLabel').text(areaName);
            $('#addLanesModal').modal('show');
        });

        // Handle View Lanes button click
        $('.view-lanes').click(function() {
            var areaId = $(this).data('area-id');
            var areaName = $(this).data('area-name');
            
            $('#viewAreaNameLabel').text(areaName);
            $('#lanesContent').html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading lanes...</p>');
            $('#viewLanesModal').modal('show');
            
            // Load lanes via AJAX
            $.get('backend/area_actions.php?action=get_lanes&area_id=' + areaId, function(data) {
                if (data.error) {
                    $('#lanesContent').html('<p class="text-center text-danger">Error: ' + data.error + '</p>');
                    return;
                }
                
                if (data.lanes && data.lanes.length > 0) {
                    var html = '<div class="table-responsive"><table class="table table-bordered table-sm">';
                    html += '<thead><tr><th>#</th><th>Lane Name</th><th>Action</th></tr></thead>';
                    html += '<tbody>';
                    data.lanes.forEach(function(lane, index) {
                        html += '<tr>';
                        html += '<td>' + (index + 1) + '</td>';
                        html += '<td>' + lane.lane_name + '</td>';
                        html += '<td><button class="btn btn-sm btn-danger delete-lane" data-lane-id="' + lane.lane_id + '"><i class="fas fa-trash"></i></button></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    $('#lanesContent').html(html);
                } else {
                    $('#lanesContent').html('<p class="text-center text-muted">No lanes found for this area.</p>');
                }
            }).fail(function() {
                $('#lanesContent').html('<p class="text-center text-danger">Failed to load lanes. Please try again.</p>');
            });
        });

        // Handle Delete Area button click
        $('.delete-area').click(function() {
            var areaId = $(this).data('area-id');
            $('#deleteAreaId').val(areaId);
            $('#deleteAreaModal').modal('show');
        });

        // Handle Delete Lane button click (delegated event)
        $(document).on('click', '.delete-lane', function() {
            var laneId = $(this).data('lane-id');
            if (confirm('Are you sure you want to delete this lane?')) {
                $.post('backend/area_actions.php?action=delete_lane', { lane_id: laneId }, function(response) {
                    if (response.success) {
                        // Reload the lanes view
                        $('.view-lanes').first().click();
                        location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'Failed to delete lane'));
                    }
                }).fail(function() {
                    alert('Failed to delete lane. Please try again.');
                });
            }
        });

        // Preview lane names
        function updatePreview() {
            var baseName = $('#lane_base_name').val();
            var startNum = parseInt($('#start_number').val());
            var endNum = parseInt($('#end_number').val());
            
            if (baseName && startNum && endNum && startNum <= endNum) {
                var count = endNum - startNum + 1;
                var preview = baseName + ' ' + startNum;
                if (count > 1) {
                    preview += ', ' + baseName + ' ' + (startNum + 1);
                }
                if (count > 2) {
                    preview += ', ... , ' + baseName + ' ' + endNum;
                }
                preview += ' (' + count + ' lanes)';
                $('#previewText').text(preview);
            } else {
                $('#previewText').text('Enter values to see preview');
            }
        }

        $('#lane_base_name, #start_number, #end_number').on('input change', updatePreview);
    });
    </script>
</body>
</html>

<?php
// End output buffering and display the page
ob_end_flush();
?>
