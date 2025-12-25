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
$pageTitle = 'Truck Management - SWM Environment';
$currentPage = 'trucks';

// Try to connect to database
try {
    require_once '../includes/db.php';
    
    // Set page title and current page for navigation
    $pageTitle = 'Truck Management - SWM Environment';
    $currentPage = 'trucks';
    
    // Debug: Check if tables exist
    $tables = $conn->query("SHOW TABLES LIKE 'truck'");
    if ($tables->num_rows == 0) {
        throw new Exception("Truck table does not exist");
    }
    
    // Get all trucks with staff count (only count ACTIVE staff)
    $trucksQuery = "SELECT t.*, 
                   (SELECT COUNT(*) FROM truck_staff WHERE truck_id = t.truck_id AND status = 'active') as staff_count
                   FROM truck t
                   ORDER BY t.truck_number";
    
    $trucksResult = $conn->query($trucksQuery);
    
    if ($trucksResult === false) {
        throw new Exception("Error fetching trucks: " . $conn->error);
    }
    
    $trucks = $trucksResult->fetch_all(MYSQLI_ASSOC);
    
    // Get all staff for assignment
    $staffQuery = "SELECT user_id, name, work_id FROM user WHERE role = 'staff' ORDER BY name";
    $staffResult = $conn->query($staffQuery);
    
    if ($staffResult === false) {
        throw new Exception("Error fetching staff: " . $conn->error);
    }
    
    $staff = $staffResult->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message
    error_log("Error in truck_management.php: " . $e->getMessage());
    
    // For debugging - show error details
    if (isset($_GET['debug'])) {
        die("<pre>Error: " . $e->getMessage() . "\n" . 
            "Query: " . ($trucksQuery ?? 'N/A') . "\n" . 
            "MySQL Error: " . ($conn->error ?? 'N/A') . "</pre>");
    }
    
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
                        <h1 class="h3 mb-0 text-gray-800">Truck Management</h1>
                        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addTruckModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Truck
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

                    <!-- Trucks Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Truck List</h6>
                        </div>
                        <div class="card-body">
                            <!-- Filter Row -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="small text-muted mb-1">Filter by Status</label>
                                    <select class="form-control form-control-sm" id="filterStatus">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted mb-1">Filter by Staff Count</label>
                                    <select class="form-control form-control-sm" id="filterStaffCount">
                                        <option value="">All</option>
                                        <option value="0">0 Staff (Empty)</option>
                                        <option value="1">1 Staff</option>
                                        <option value="2">2 Staff</option>
                                        <option value="3">3 Staff (Full)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small text-muted mb-1">Search Truck</label>
                                    <input type="text" class="form-control form-control-sm" id="searchTruck" placeholder="Search by truck number...">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-secondary btn-block" id="resetFilters">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="trucksTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Truck Number</th>
                                            <th>Assigned Staff</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trucks as $truck): 
                                            $statusClass = [
                                                'active' => 'success',
                                                'maintenance' => 'warning',
                                                'inactive' => 'secondary'
                                            ][$truck['status']] ?? 'secondary';
                                        ?>
                                        <tr data-status="<?php echo $truck['status']; ?>" data-staff-count="<?php echo $truck['staff_count']; ?>">
                                            <td><?php echo htmlspecialchars($truck['truck_number']); ?></td>
                                            <td>
                                                <?php 
                                                $count = $truck['staff_count'];
                                                $badgeClass = $count == 3 ? 'success' : ($count > 0 ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge badge-<?php echo $badgeClass; ?>" id="staff-count-<?php echo $truck['truck_id']; ?>">
                                                    <?php echo $count; ?>/3
                                                </span>
                                                <button type="button" class="btn btn-sm btn-info view-staff ml-2" 
                                                        data-truck-id="<?php echo $truck['truck_id']; ?>"
                                                        data-truck-number="<?php echo htmlspecialchars($truck['truck_number']); ?>"
                                                        title="Manage assigned staff">
                                                    <i class="fas fa-users"></i> Manage
                                                </button>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($truck['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-truck" 
                                                        data-id="<?php echo $truck['truck_id']; ?>"
                                                        data-number="<?php echo htmlspecialchars($truck['truck_number']); ?>"
                                                        data-status="<?php echo $truck['status']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-truck" 
                                                        data-id="<?php echo $truck['truck_id']; ?>">
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

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SWM Environment <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Add Truck Modal -->
    <div class="modal fade" id="addTruckModal" tabindex="-1" role="dialog" aria-labelledby="addTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTruckModalLabel">Add New Truck</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="../backend/truck_actions.php?action=add" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="truck_number">Truck Number *</label>
                            <input type="text" class="form-control" id="truck_number" name="truck_number" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_truck" class="btn btn-primary">Add Truck</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Staff Modal -->
    <div class="modal fade" id="viewStaffModal" tabindex="-1" role="dialog" aria-labelledby="viewStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewStaffModalLabel">Assigned Staff - Truck: <span id="viewTruckNumberLabel"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="viewStaffContent">
                        <p class="text-center">Loading staff information...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Staff Modal -->
    <div class="modal fade" id="assignStaffModal" tabindex="-1" role="dialog" aria-labelledby="assignStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignStaffModalLabel">Assign Staff to Truck: <span id="truckNumberLabel"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="assignStaffForm" action="../backend/truck_actions.php?action=assign_staff" method="POST">
                    <input type="hidden" name="truck_id" id="assignTruckId">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Driver</label>
                            <select class="form-control" name="driver_id" id="driverSelect" required>
                                <option value="">Select Driver</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Collector 1</label>
                            <select class="form-control" name="collector1_id" id="collector1Select">
                                <option value="">Select Collector 1</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Collector 2</label>
                            <select class="form-control" name="collector2_id" id="collector2Select">
                                <option value="">Select Collector 2</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Assignments</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Truck Modal -->
    <div class="modal fade" id="editTruckModal" tabindex="-1" role="dialog" aria-labelledby="editTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTruckModalLabel">Edit Truck</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editTruckForm" action="../backend/truck_actions.php?action=edit" method="POST">
                    <input type="hidden" name="truck_id" id="editTruckId">
                    <input type="hidden" name="truck_number" id="edit_truck_number">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_truck_number_display">Truck Number</label>
                            <input type="text" class="form-control" id="edit_truck_number_display" readonly disabled>
                            <small class="form-text text-muted">Truck number cannot be changed.</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteTruckModal" tabindex="-1" role="dialog" aria-labelledby="deleteTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTruckModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this truck? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="deleteTruckForm" action="../backend/truck_actions.php?action=delete" method="POST" style="display: inline;">
                        <input type="hidden" name="truck_id" id="deleteTruckId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Staff Confirmation Modal -->
    <div class="modal fade" id="removeStaffModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-user-minus mr-2"></i>Remove Staff from Truck</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove <strong id="removeStaffName"></strong> from <strong id="removeStaffTruck"></strong>?</p>
                    <p class="text-muted small mb-0"><i class="fas fa-info-circle mr-1"></i>This staff will be unassigned and available for other trucks.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRemoveStaff">
                        <i class="fas fa-user-minus mr-1"></i>Remove Staff
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Swap Staff Modal -->
    <div class="modal fade" id="swapStaffModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-exchange-alt mr-2"></i>Swap Staff</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Swap <strong id="swapStaffName"></strong> (<span id="swapStaffRole"></span>) with:</p>
                    <div class="form-group">
                        <label class="font-weight-bold">Select Staff to Swap With</label>
                        <select class="form-control" id="swapTargetSelect">
                            <option value="">-- Select Staff to Swap With --</option>
                        </select>
                    </div>
                    <div id="swapPreview" class="alert alert-info" style="display: none;">
                        <small>
                            <i class="fas fa-exchange-alt mr-1"></i>
                            <span id="swapPreviewText"></span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="confirmSwapStaff" disabled>
                        <i class="fas fa-exchange-alt mr-1"></i>Confirm Swap
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Add Staff to Truck</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Add staff as <strong id="addStaffRole"></strong> to <strong id="addStaffTruck"></strong>:</p>
                    <div class="form-group">
                        <label class="font-weight-bold">Select Available Staff</label>
                        <select class="form-control" id="addStaffSelect">
                            <option value="">-- Select Staff --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmAddStaff" disabled>
                        <i class="fas fa-user-plus mr-1"></i>Add Staff
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
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

    <!-- Bootstrap core JavaScript (load before DataTables) -->
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
    // Wait for all scripts to be fully loaded
    $(document).ready(function() {
        // Initialize DataTable with custom filtering disabled (we'll use our own)
        var table = $('#trucksTable').DataTable({
            "pageLength": 10,
            "order": [[0, 'asc']],
            "dom": 'lrtip' // Remove default search box since we have our own
        });

        // Custom filter function
        function applyFilters() {
            var statusFilter = $('#filterStatus').val().toLowerCase();
            var staffCountFilter = $('#filterStaffCount').val();
            var searchFilter = $('#searchTruck').val().toLowerCase();
            
            $('#trucksTable tbody tr').each(function() {
                var $row = $(this);
                var rowStatus = $row.data('status') ? $row.data('status').toLowerCase() : '';
                var rowStaffCount = String($row.data('staff-count'));
                var rowTruckNumber = $row.find('td:first').text().toLowerCase();
                
                var statusMatch = !statusFilter || rowStatus === statusFilter;
                var staffMatch = !staffCountFilter || rowStaffCount === staffCountFilter;
                var searchMatch = !searchFilter || rowTruckNumber.indexOf(searchFilter) > -1;
                
                if (statusMatch && staffMatch && searchMatch) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
            
            // Update "no data" message
            var visibleRows = $('#trucksTable tbody tr:visible').length;
            if (visibleRows === 0) {
                if ($('#noDataRow').length === 0) {
                    $('#trucksTable tbody').append('<tr id="noDataRow"><td colspan="4" class="text-center text-muted py-4">No trucks found matching the filters</td></tr>');
                }
            } else {
                $('#noDataRow').remove();
            }
        }
        
        // Filter event handlers
        $('#filterStatus, #filterStaffCount').on('change', applyFilters);
        $('#searchTruck').on('keyup', applyFilters);
        
        // Reset filters
        $('#resetFilters').on('click', function() {
            $('#filterStatus').val('');
            $('#filterStaffCount').val('');
            $('#searchTruck').val('');
            applyFilters();
        });

        // Handle edit truck button click using document-level event delegation
        // This works even after DataTables recreates the DOM
        $(document).off('click', '.edit-truck').on('click', '.edit-truck', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var truckId = $btn.data('id');
            var truckNumber = $btn.data('number');
            var status = $btn.data('status');
            
            if (!truckId) {
                console.error('Truck ID not found');
                return;
            }
            
            $('#editTruckId').val(truckId);
            $('#edit_truck_number').val(truckNumber);
            $('#edit_truck_number_display').val(truckNumber);
            $('#edit_status').val(status);
            
            $('#editTruckModal').modal('show');
        });

        // Handle delete truck button click using document-level event delegation
        $(document).off('click', '.delete-truck').on('click', '.delete-truck', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var truckId = $btn.data('id');
            
            if (!truckId) {
                console.error('Truck ID not found');
                return;
            }
            
            $('#deleteTruckId').val(truckId);
            $('#deleteTruckModal').modal('show');
        });

        // Store all staff data for filtering
        var allStaff = <?php echo json_encode($staff); ?>;
        var currentViewTruckId = null;
        var currentViewTruckNumber = null;
        var isReloading = false;
        
        // Handle view staff button click
        $(document).off('click', '.view-staff').on('click', '.view-staff', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var truckId = $btn.data('truck-id');
            var truckNumber = $btn.data('truck-number');
            
            currentViewTruckId = truckId;
            currentViewTruckNumber = truckNumber;
            
            $('#viewTruckNumberLabel').text(truckNumber);
            $('#viewStaffContent').html('<p class="text-center">Loading staff information...</p>');
            $('#viewStaffModal').modal('show');
            
            loadStaffForView(truckId, truckNumber);
        });
        
        // Function to load staff for view modal
        function loadStaffForView(truckId, truckNumber) {
            $.get('../api/get_truck_staff_details.php?truck_id=' + truckId, function(data) {
                if (data.error) {
                    $('#viewStaffContent').html('<p class="text-center text-danger">Error: ' + data.error + '</p>');
                    return;
                }
                
                // Define all roles
                var roles = ['Driver', 'Collector 1', 'Collector 2'];
                var staffByRole = {};
                
                // Map current staff by role
                if (data.staff && data.staff.length > 0) {
                    data.staff.forEach(function(staff) {
                        staffByRole[staff.role] = staff;
                    });
                }
                
                var html = '<table class="table table-bordered">';
                html += '<thead class="thead-light"><tr><th style="width: 120px;">Role</th><th>Name</th><th style="width: 100px;">Work ID</th><th class="text-center" style="width: 120px;">Actions</th></tr></thead>';
                html += '<tbody>';
                
                roles.forEach(function(role) {
                    html += '<tr>';
                    html += '<td><strong>' + role + '</strong></td>';
                    
                    if (staffByRole[role]) {
                        var staff = staffByRole[role];
                        html += '<td>' + staff.name + '</td>';
                        html += '<td>' + staff.work_id + '</td>';
                        html += '<td class="text-center">';
                        html += '<button type="button" class="btn btn-sm btn-outline-info btn-swap-staff mr-1" ';
                        html += 'data-user-id="' + staff.user_id + '" ';
                        html += 'data-name="' + staff.name + '" ';
                        html += 'data-role="' + staff.role + '" ';
                        html += 'data-truck-id="' + truckId + '" ';
                        html += 'data-truck-number="' + truckNumber + '" ';
                        html += 'title="Swap with another staff">';
                        html += '<i class="fas fa-exchange-alt"></i>';
                        html += '</button>';
                        html += '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-staff" ';
                        html += 'data-user-id="' + staff.user_id + '" ';
                        html += 'data-name="' + staff.name + '" ';
                        html += 'data-role="' + staff.role + '" ';
                        html += 'data-truck-id="' + truckId + '" ';
                        html += 'data-truck-number="' + truckNumber + '" ';
                        html += 'title="Remove from truck">';
                        html += '<i class="fas fa-user-minus"></i>';
                        html += '</button>';
                        html += '</td>';
                    } else {
                        // Empty slot - show Add button
                        html += '<td class="text-muted"><em>Not assigned</em></td>';
                        html += '<td>-</td>';
                        html += '<td class="text-center">';
                        html += '<button type="button" class="btn btn-sm btn-outline-success btn-add-staff" ';
                        html += 'data-role="' + role + '" ';
                        html += 'data-truck-id="' + truckId + '" ';
                        html += 'data-truck-number="' + truckNumber + '" ';
                        html += 'title="Add staff to this position">';
                        html += '<i class="fas fa-user-plus"></i>';
                        html += '</button>';
                        html += '</td>';
                    }
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#viewStaffContent').html(html);
                
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                $('#viewStaffContent').html('<p class="text-center text-danger">Error loading staff information. Please check the console for details.</p>');
            });
        }
        
        // Handle Remove Staff button click
        $(document).on('click', '.btn-remove-staff', function() {
            var userId = $(this).data('user-id');
            var name = $(this).data('name');
            var truckId = $(this).data('truck-id');
            var truckNumber = $(this).data('truck-number');
            
            $('#removeStaffName').text(name);
            $('#removeStaffTruck').text('Truck ' + truckNumber);
            $('#confirmRemoveStaff').data('user-id', userId).data('truck-id', truckId);
            
            $('#viewStaffModal').modal('hide');
            $('#removeStaffModal').modal('show');
        });
        
        // Confirm Remove Staff
        $('#confirmRemoveStaff').on('click', function() {
            var userId = $(this).data('user-id');
            var truckId = $(this).data('truck-id');
            var $btn = $(this);
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Removing...');
            
            $.post('../backend/truck_actions.php?action=remove_staff', {
                user_id: userId,
                truck_id: truckId
            }, function(response) {
                if (response.success) {
                    isReloading = true;
                    $('#removeStaffModal').modal('hide');
                    // Refresh page to update staff count badge
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to remove staff'));
                    $btn.prop('disabled', false).html('<i class="fas fa-user-minus mr-1"></i>Remove Staff');
                }
            }, 'json').fail(function() {
                alert('Error removing staff. Please try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-user-minus mr-1"></i>Remove Staff');
            });
        });
        
        // Handle Swap Staff button click
        $(document).on('click', '.btn-swap-staff', function() {
            var userId = $(this).data('user-id');
            var name = $(this).data('name');
            var role = $(this).data('role');
            var truckId = $(this).data('truck-id');
            var truckNumber = $(this).data('truck-number');
            
            $('#swapStaffName').text(name);
            $('#swapStaffRole').text(role);
            $('#swapTargetSelect').html('<option value="">-- Loading... --</option>');
            $('#swapPreview').hide();
            $('#confirmSwapStaff').prop('disabled', true)
                .data('user-id', userId)
                .data('truck-id', truckId)
                .data('role', role);
            
            $('#viewStaffModal').modal('hide');
            $('#swapStaffModal').modal('show');
            
            // Load staff from other trucks AND unassigned staff
            $.get('../api/get_truck_staff_details.php?action=swap_options&exclude_truck_id=' + truckId, function(data) {
                var html = '<option value="">-- Select Staff to Swap With --</option>';
                
                // Add unassigned staff first
                if (data.unassigned && data.unassigned.length > 0) {
                    html += '<optgroup label="Available Staff (Not Assigned)">';
                    data.unassigned.forEach(function(staff) {
                        html += '<option value="' + staff.user_id + '" ';
                        html += 'data-name="' + staff.name + '" ';
                        html += 'data-role="" ';
                        html += 'data-truck-id="0" ';
                        html += 'data-truck-number="None" ';
                        html += 'data-is-unassigned="true">';
                        html += staff.name + ' (' + staff.work_id + ') - Not Assigned';
                        html += '</option>';
                    });
                    html += '</optgroup>';
                }
                
                // Add staff from other trucks
                if (data.assigned && data.assigned.length > 0) {
                    html += '<optgroup label="Staff from Other Trucks">';
                    data.assigned.forEach(function(staff) {
                        html += '<option value="' + staff.user_id + '" ';
                        html += 'data-name="' + staff.name + '" ';
                        html += 'data-role="' + staff.role + '" ';
                        html += 'data-truck-id="' + staff.truck_id + '" ';
                        html += 'data-truck-number="' + staff.truck_number + '" ';
                        html += 'data-is-unassigned="false">';
                        html += staff.name + ' (' + staff.role + ') - Truck ' + staff.truck_number;
                        html += '</option>';
                    });
                    html += '</optgroup>';
                }
                
                $('#swapTargetSelect').html(html);
            }).fail(function() {
                $('#swapTargetSelect').html('<option value="">-- Error loading staff --</option>');
            });
        });
        
        // Swap target selection change
        $('#swapTargetSelect').on('change', function() {
            var $selected = $(this).find(':selected');
            var targetUserId = $(this).val();
            
            if (targetUserId) {
                var targetName = $selected.data('name');
                var targetRole = $selected.data('role');
                var targetTruckNumber = $selected.data('truck-number');
                var isUnassigned = $selected.data('is-unassigned');
                var sourceName = $('#swapStaffName').text();
                var sourceRole = $('#swapStaffRole').text();
                
                var previewText;
                if (isUnassigned === true || isUnassigned === 'true') {
                    previewText = sourceName + ' (' + sourceRole + ') will be replaced by ' + targetName + ' (currently not assigned)';
                } else {
                    previewText = sourceName + ' (' + sourceRole + ') from Truck ' + currentViewTruckNumber;
                    previewText += ' ⟷ ';
                    previewText += targetName + ' (' + targetRole + ') from Truck ' + targetTruckNumber;
                }
                
                $('#swapPreviewText').text(previewText);
                $('#swapPreview').show();
                $('#confirmSwapStaff').prop('disabled', false)
                    .data('target-user-id', targetUserId)
                    .data('target-truck-id', $selected.data('truck-id'))
                    .data('target-role', targetRole)
                    .data('is-unassigned', isUnassigned);
            } else {
                $('#swapPreview').hide();
                $('#confirmSwapStaff').prop('disabled', true);
            }
        });
        
        // Confirm Swap Staff
        $('#confirmSwapStaff').on('click', function() {
            var $btn = $(this);
            var sourceUserId = $btn.data('user-id');
            var sourceTruckId = $btn.data('truck-id');
            var sourceRole = $btn.data('role');
            var targetUserId = $btn.data('target-user-id');
            var targetTruckId = $btn.data('target-truck-id');
            var targetRole = $btn.data('target-role');
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Swapping...');
            
            $.post('../backend/truck_actions.php?action=swap_staff', {
                source_user_id: sourceUserId,
                source_truck_id: sourceTruckId,
                source_role: sourceRole,
                target_user_id: targetUserId,
                target_truck_id: targetTruckId,
                target_role: targetRole
            }, function(response) {
                if (response.success) {
                    isReloading = true;
                    $('#swapStaffModal').modal('hide');
                    // Refresh page to update all data
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to swap staff'));
                    $btn.prop('disabled', false).html('<i class="fas fa-exchange-alt mr-1"></i>Confirm Swap');
                }
            }, 'json').fail(function() {
                alert('Error swapping staff. Please try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-exchange-alt mr-1"></i>Confirm Swap');
            });
        });
        
        // Return to view modal when remove/swap modal is closed (only if not reloading)
        $('#removeStaffModal, #swapStaffModal, #addStaffModal').on('hidden.bs.modal', function() {
            if (currentViewTruckId && !isReloading) {
                // Small delay to prevent modal stacking issues
                setTimeout(function() {
                    $('#viewStaffModal').modal('show');
                }, 300);
            }
        });
        
        // Handle Add Staff button click
        $(document).on('click', '.btn-add-staff', function() {
            var role = $(this).data('role');
            var truckId = $(this).data('truck-id');
            var truckNumber = $(this).data('truck-number');
            
            $('#addStaffRole').text(role);
            $('#addStaffTruck').text('Truck ' + truckNumber);
            $('#addStaffSelect').html('<option value="">-- Loading... --</option>');
            $('#confirmAddStaff').prop('disabled', true)
                .data('role', role)
                .data('truck-id', truckId);
            
            $('#viewStaffModal').modal('hide');
            $('#addStaffModal').modal('show');
            
            // Load unassigned staff
            $.get('../api/get_truck_staff_details.php?action=unassigned', function(data) {
                var html = '<option value="">-- Select Staff --</option>';
                if (data.staff && data.staff.length > 0) {
                    data.staff.forEach(function(staff) {
                        html += '<option value="' + staff.user_id + '">';
                        html += staff.name + ' (' + staff.work_id + ')';
                        html += '</option>';
                    });
                } else {
                    html = '<option value="">-- No available staff --</option>';
                }
                $('#addStaffSelect').html(html);
            }).fail(function() {
                $('#addStaffSelect').html('<option value="">-- Error loading staff --</option>');
            });
        });
        
        // Add staff select change
        $('#addStaffSelect').on('change', function() {
            $('#confirmAddStaff').prop('disabled', !$(this).val());
        });
        
        // Confirm Add Staff
        $('#confirmAddStaff').on('click', function() {
            var $btn = $(this);
            var userId = $('#addStaffSelect').val();
            var role = $btn.data('role');
            var truckId = $btn.data('truck-id');
            
            if (!userId) return;
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Adding...');
            
            $.post('../backend/truck_actions.php?action=add_single_staff', {
                user_id: userId,
                truck_id: truckId,
                role: role
            }, function(response) {
                if (response.success) {
                    isReloading = true;
                    $('#addStaffModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Failed to add staff'));
                    $btn.prop('disabled', false).html('<i class="fas fa-user-plus mr-1"></i>Add Staff');
                }
            }, 'json').fail(function() {
                alert('Error adding staff. Please try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-user-plus mr-1"></i>Add Staff');
            });
        });

        // Handle assign staff modal show
        $(document).on('show.bs.modal', '#assignStaffModal', function (event) {
            var button = $(event.relatedTarget);
            var truckId = button.data('truck-id');
            var truckNumber = button.data('truck-number');
            
            var modal = $(this);
            modal.find('#truckNumberLabel').text(truckNumber);
            modal.find('#assignTruckId').val(truckId);
            
            // Clear all selects first
            $('#driverSelect, #collector1Select, #collector2Select').html('<option value="">Select...</option>');
            
            // Get assigned staff IDs (excluding current truck - these are staff assigned to OTHER trucks)
            $.get('../api/get_truck_staff_details.php?action=assigned_ids&exclude_truck_id=' + truckId, function(assignedData) {
                if (assignedData.error) {
                    console.error('Error getting assigned staff:', assignedData.error);
                    // Fallback: show all staff if API fails
                    allStaff.forEach(function(staff) {
                        var option = '<option value="' + staff.user_id + '">' + 
                                    staff.name + ' (' + staff.work_id + ')' + 
                                    '</option>';
                        $('#driverSelect, #collector1Select, #collector2Select').append(option);
                    });
                } else {
                    var assignedIds = assignedData.assigned_ids || [];
                    // Convert to numbers for proper comparison
                    assignedIds = assignedIds.map(function(id) { return parseInt(id); });
                    
                    // Populate dropdowns with available staff (excluding already assigned to OTHER trucks)
                    allStaff.forEach(function(staff) {
                        var staffId = parseInt(staff.user_id);
                        // Only show staff that are not assigned to other trucks
                        if (assignedIds.indexOf(staffId) === -1) {
                            var option = '<option value="' + staff.user_id + '">' + 
                                        staff.name + ' (' + staff.work_id + ')' + 
                                        '</option>';
                            $('#driverSelect, #collector1Select, #collector2Select').append(option);
                        }
                    });
                }
                
                // Load current staff assignments for this truck and set selected values
                // This ensures current assignments are visible even if they're in the filtered list
                $.get('../api/get_truck_staff.php?truck_id=' + truckId, function(data) {
                    if (data.driver_id) {
                        // Add current driver to dropdown if not already there
                        var driverStaff = allStaff.find(function(s) { return parseInt(s.user_id) == parseInt(data.driver_id); });
                        if (driverStaff) {
                            // Check if option already exists
                            if ($('#driverSelect option[value="' + data.driver_id + '"]').length === 0) {
                                var driverOption = '<option value="' + driverStaff.user_id + '" selected>' + 
                                                 driverStaff.name + ' (' + driverStaff.work_id + ')' + 
                                                 '</option>';
                                $('#driverSelect').prepend(driverOption);
                            } else {
                                $('#driverSelect').val(data.driver_id);
                            }
                        }
                    }
                    if (data.collector1_id) {
                        var collector1Staff = allStaff.find(function(s) { return parseInt(s.user_id) == parseInt(data.collector1_id); });
                        if (collector1Staff) {
                            if ($('#collector1Select option[value="' + data.collector1_id + '"]').length === 0) {
                                var collector1Option = '<option value="' + collector1Staff.user_id + '" selected>' + 
                                                     collector1Staff.name + ' (' + collector1Staff.work_id + ')' + 
                                                     '</option>';
                                $('#collector1Select').prepend(collector1Option);
                            } else {
                                $('#collector1Select').val(data.collector1_id);
                            }
                        }
                    }
                    if (data.collector2_id) {
                        var collector2Staff = allStaff.find(function(s) { return parseInt(s.user_id) == parseInt(data.collector2_id); });
                        if (collector2Staff) {
                            if ($('#collector2Select option[value="' + data.collector2_id + '"]').length === 0) {
                                var collector2Option = '<option value="' + collector2Staff.user_id + '" selected>' + 
                                                     collector2Staff.name + ' (' + collector2Staff.work_id + ')' + 
                                                     '</option>';
                                $('#collector2Select').prepend(collector2Option);
                            } else {
                                $('#collector2Select').val(data.collector2_id);
                            }
                        }
                    }
                }).fail(function() {
                    console.error('Error loading current staff assignments');
                });
            }).fail(function(xhr, status, error) {
                console.error('Error getting assigned staff IDs:', status, error);
                // Fallback: show all staff if API fails
                allStaff.forEach(function(staff) {
                    var option = '<option value="' + staff.user_id + '">' + 
                                staff.name + ' (' + staff.work_id + ')' + 
                                '</option>';
                    $('#driverSelect, #collector1Select, #collector2Select').append(option);
                });
            });
        });
    });
    </script>
</body>
</html>

<?php 
// End output buffering and display the page
ob_end_flush();
?>
