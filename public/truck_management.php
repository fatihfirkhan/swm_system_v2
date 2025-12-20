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
    
    // Get all trucks with staff count
    $trucksQuery = "SELECT t.*, 
                   (SELECT COUNT(*) FROM truck_staff WHERE truck_id = t.truck_id) as staff_count
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
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Truck List</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="trucksTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Truck Number</th>
                                            <th>Capacity</th>
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
                                        <tr>
                                            <td><?php echo htmlspecialchars($truck['truck_number']); ?></td>
                                            <td><?php echo htmlspecialchars($truck['capacity']); ?></td>
                                            <td>
                                                <span class="badge badge-info view-staff" style="cursor: pointer;" 
                                                      data-truck-id="<?php echo $truck['truck_id']; ?>"
                                                      data-truck-number="<?php echo htmlspecialchars($truck['truck_number']); ?>"
                                                      title="Click to view assigned staff">
                                                    <?php echo $truck['staff_count']; ?> Staff
                                                </span>
                                                <button type="button" class="btn btn-sm btn-link" data-toggle="modal" data-target="#assignStaffModal" 
                                                        data-truck-id="<?php echo $truck['truck_id']; ?>"
                                                        data-truck-number="<?php echo htmlspecialchars($truck['truck_number']); ?>">
                                                    Manage Staff
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
                                                        data-capacity="<?php echo htmlspecialchars($truck['capacity']); ?>"
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
                            <label for="capacity">Capacity</label>
                            <select class="form-control" id="capacity" name="capacity">
                                <option value="3-ton">3-ton</option>
                                <option value="5-ton">5-ton</option>
                                <option value="10-ton">10-ton</option>
                                <option value="15-ton">15-ton</option>
                            </select>
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
        <div class="modal-dialog" role="document">
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
                            <label for="edit_capacity">Capacity</label>
                            <select class="form-control" id="edit_capacity" name="capacity">
                                <option value="3-ton">3-ton</option>
                                <option value="5-ton">5-ton</option>
                                <option value="10-ton">10-ton</option>
                                <option value="15-ton">15-ton</option>
                            </select>
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
        // Initialize DataTable
        var table = $('#trucksTable').DataTable({
            "pageLength": 10,
            "order": [[0, 'asc']]
        });

        // Handle edit truck button click using document-level event delegation
        // This works even after DataTables recreates the DOM
        $(document).off('click', '.edit-truck').on('click', '.edit-truck', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var truckId = $btn.data('id');
            var truckNumber = $btn.data('number');
            var capacity = $btn.data('capacity');
            var status = $btn.data('status');
            
            if (!truckId) {
                console.error('Truck ID not found');
                return;
            }
            
            $('#editTruckId').val(truckId);
            $('#edit_truck_number').val(truckNumber);
            $('#edit_truck_number_display').val(truckNumber);
            $('#edit_capacity').val(capacity);
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
        
        // Handle view staff button click
        $(document).off('click', '.view-staff').on('click', '.view-staff', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var truckId = $btn.data('truck-id');
            var truckNumber = $btn.data('truck-number');
            
            $('#viewTruckNumberLabel').text(truckNumber);
            $('#viewStaffContent').html('<p class="text-center">Loading staff information...</p>');
            $('#viewStaffModal').modal('show');
            
            // Load staff details
            $.get('../api/get_truck_staff_details.php?truck_id=' + truckId, function(data) {
                if (data.error) {
                    $('#viewStaffContent').html('<p class="text-center text-danger">Error: ' + data.error + '</p>');
                    return;
                }
                if (data.staff && data.staff.length > 0) {
                    var html = '<table class="table table-bordered">';
                    html += '<thead><tr><th>Role</th><th>Name</th><th>Work ID</th></tr></thead>';
                    html += '<tbody>';
                    data.staff.forEach(function(staff) {
                        html += '<tr>';
                        html += '<td><strong>' + staff.role + '</strong></td>';
                        html += '<td>' + staff.name + '</td>';
                        html += '<td>' + staff.work_id + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    $('#viewStaffContent').html(html);
                } else {
                    $('#viewStaffContent').html('<p class="text-center text-muted">No staff assigned to this truck.</p>');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                $('#viewStaffContent').html('<p class="text-center text-danger">Error loading staff information. Please check the console for details.</p>');
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
