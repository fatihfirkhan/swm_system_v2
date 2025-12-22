<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin or staff
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Manage Complaints - SWM Environment';
$currentPage = 'complaints';

$successMsg = '';
$errorMsg = '';

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    $complaintId = intval($_POST['complaint_id'] ?? 0);
    $adminResponse = trim($_POST['admin_response'] ?? '');
    
    if ($complaintId > 0 && !empty($adminResponse)) {
        $updateQuery = "UPDATE complaints SET 
                        admin_response = ?, 
                        status = 'Resolved', 
                        response_time = NOW() 
                        WHERE complaint_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('si', $adminResponse, $complaintId);
        
        if ($updateStmt->execute()) {
            $successMsg = 'Response submitted successfully. Complaint marked as Resolved.';
        } else {
            $errorMsg = 'Failed to submit response. Please try again.';
        }
    } else {
        $errorMsg = 'Please provide a response.';
    }
}

// Handle status update (In Progress)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_in_progress'])) {
    $complaintId = intval($_POST['complaint_id'] ?? 0);
    $adminResponse = trim($_POST['admin_response'] ?? '');
    
    if ($complaintId > 0) {
        // Update BOTH status AND admin_response (even if response is empty)
        $updateQuery = "UPDATE complaints SET status = 'In Progress', admin_response = ? WHERE complaint_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('si', $adminResponse, $complaintId);
        
        if ($updateStmt->execute()) {
            if ($updateStmt->affected_rows > 0) {
                $successMsg = 'Status updated to In Progress' . (!empty($adminResponse) ? ' and response saved.' : '.');
            } else {
                $successMsg = 'Response updated successfully.';
            }
        } else {
            $errorMsg = 'Failed to update. Please try again.';
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build dynamic SQL query with filters
$complaintsQuery = "SELECT c.*, u.name as resident_name, u.email as resident_email, 
                           u.phone as resident_phone, 
                           CONCAT_WS(', ', u.house_unit_number, u.address_line1, u.address_line2, u.postcode) as resident_address,
                           ca.taman_name as area_name
                    FROM complaints c
                    LEFT JOIN user u ON c.user_id = u.user_id
                    LEFT JOIN collection_area ca ON c.area_id = ca.area_id
                    WHERE 1=1";

// Status filter
if (!empty($statusFilter) && in_array($statusFilter, ['Pending', 'In Progress', 'Resolved'])) {
    $complaintsQuery .= " AND c.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

// Search filter (by name or complaint ID)
if (!empty($searchQuery)) {
    $escapedSearch = $conn->real_escape_string($searchQuery);
    $complaintsQuery .= " AND (u.name LIKE '%$escapedSearch%' OR c.complaint_id = '$escapedSearch')";
}

// Date range filter
if (!empty($startDate)) {
    $complaintsQuery .= " AND DATE(c.submission_time) >= '" . $conn->real_escape_string($startDate) . "'";
}
if (!empty($endDate)) {
    $complaintsQuery .= " AND DATE(c.submission_time) <= '" . $conn->real_escape_string($endDate) . "'";
}

$complaintsQuery .= " ORDER BY 
                        CASE c.status 
                            WHEN 'Pending' THEN 1 
                            WHEN 'In Progress' THEN 2 
                            WHEN 'Resolved' THEN 3 
                        END,
                        c.submission_time DESC";
$complaints = $conn->query($complaintsQuery);

// Count stats
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved
FROM complaints";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Additional styles
$additionalStyles = '
<style>
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
    }
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .status-in-progress {
        background-color: #cce5ff;
        color: #004085;
    }
    .status-resolved {
        background-color: #d4edda;
        color: #155724;
    }
    .complaint-image {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
        cursor: pointer;
    }
    .stat-card {
        border-left-width: 4px !important;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .stat-card.active {
        background-color: #f8f9fc;
        border-left-width: 6px !important;
    }
    .table td {
        vertical-align: middle;
    }
    .red-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #e74a3b;
        border-radius: 50%;
        margin-right: 5px;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .filter-section {
        background-color: #f8f9fc;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .star-rating-display {
        display: inline-flex;
        gap: 3px;
        font-size: 1.2rem;
        margin-right: 8px;
    }
    .star-rating-display .star {
        color: #ffc107;
    }
    .star-rating-display .star.empty {
        color: #ddd;
    }
</style>
';

// Additional scripts
$additionalScripts = '';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Status Messages -->
<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-2"></i><?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Manage Complaints</h1>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="admin_complaints.php" class="text-decoration-none">
            <div class="card stat-card border-left-primary shadow h-100 py-2 <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Complaints</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <a href="admin_complaints.php?status=Pending" class="text-decoration-none">
            <div class="card stat-card border-left-warning shadow h-100 py-2 <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <a href="admin_complaints.php?status=In Progress" class="text-decoration-none">
            <div class="card stat-card border-left-info shadow h-100 py-2 <?php echo $statusFilter === 'In Progress' ? 'active' : ''; ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['in_progress'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <a href="admin_complaints.php?status=Resolved" class="text-decoration-none">
            <div class="card stat-card border-left-success shadow h-100 py-2 <?php echo $statusFilter === 'Resolved' ? 'active' : ''; ?>">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['resolved'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Complaints Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-exclamation-circle mr-2"></i>All Resident Complaints
            <?php if (!empty($statusFilter)): ?>
                <span class="badge badge-info ml-2">Filtered: <?php echo htmlspecialchars($statusFilter); ?></span>
            <?php endif; ?>
        </h6>
    </div>
    <div class="card-body">
        <!-- Advanced Filter Section -->
        <div class="filter-section">
            <form method="GET" action="admin_complaints.php" class="form-inline">
                <div class="form-group mr-3 mb-2">
                    <label class="mr-2 font-weight-bold"><i class="fas fa-search mr-1"></i> Search:</label>
                    <input type="text" name="search" class="form-control" placeholder="Name or ID" value="<?php echo htmlspecialchars($searchQuery); ?>" style="width: 200px;">
                </div>
                
                <div class="form-group mr-3 mb-2">
                    <label class="mr-2 font-weight-bold"><i class="fas fa-calendar mr-1"></i> Start Date:</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                
                <div class="form-group mr-3 mb-2">
                    <label class="mr-2 font-weight-bold">End Date:</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                
                <?php if (!empty($statusFilter)): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary mb-2 mr-2">
                    <i class="fas fa-filter mr-1"></i> Apply Filters
                </button>
                
                <a href="admin_complaints.php" class="btn btn-secondary mb-2">
                    <i class="fas fa-redo mr-1"></i> Clear All
                </a>
            </form>
        </div>
        <hr>
        <?php if ($complaints->num_rows === 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-gray-300 mb-3"></i>
                <p class="text-muted">No complaints received yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="complaintsTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Area</th>
                            <th>Resident</th>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($complaint = $complaints->fetch_assoc()): ?>
                            <?php
                            $statusClass = 'pending';
                            $statusBadgeClass = 'warning';
                            if ($complaint['status'] === 'In Progress') {
                                $statusClass = 'in-progress';
                                $statusBadgeClass = 'info';
                            }
                            if ($complaint['status'] === 'Resolved') {
                                $statusClass = 'resolved';
                                $statusBadgeClass = 'success';
                            }
                            ?>
                            <tr>
                                <td class="text-center">
                                    <strong>#<?php echo $complaint['complaint_id']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($complaint['status'] === 'Pending'): ?>
                                        <span class="red-dot" title="New - Needs Attention"></span>
                                    <?php endif; ?>
                                    <?php echo date('d M Y', strtotime($complaint['submission_time'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($complaint['submission_time'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($complaint['area_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($complaint['resident_name'] ?? 'Unknown'); ?></strong>
                                    <?php if ($complaint['resident_phone']): ?>
                                        <br><small class="text-muted"><i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($complaint['resident_phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($complaint['description'], 0, 40)); ?>
                                    <?php if (strlen($complaint['description']) > 40) echo '...'; ?>
                                    <?php if ($complaint['image_url']): ?>
                                        <br><small class="text-info"><i class="fas fa-image"></i> Has image</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $statusClass; ?>">
                                        <?php echo $complaint['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary view-complaint-btn"
                                            data-id="<?php echo $complaint['complaint_id']; ?>"
                                            data-description="<?php echo htmlspecialchars($complaint['description']); ?>"
                                            data-status="<?php echo $complaint['status']; ?>"
                                            data-image="<?php echo htmlspecialchars($complaint['image_url'] ?? ''); ?>"
                                            data-response="<?php echo htmlspecialchars($complaint['admin_response'] ?? ''); ?>"
                                            data-feedback="<?php echo htmlspecialchars($complaint['resident_feedback'] ?? ''); ?>"
                                            data-rating="<?php echo intval($complaint['rating'] ?? 0); ?>"
                                            data-resident="<?php echo htmlspecialchars($complaint['resident_name'] ?? 'Unknown'); ?>"
                                            data-email="<?php echo htmlspecialchars($complaint['resident_email'] ?? ''); ?>"
                                            data-phone="<?php echo htmlspecialchars($complaint['resident_phone'] ?? ''); ?>"
                                            data-address="<?php echo htmlspecialchars($complaint['resident_address'] ?? ''); ?>"
                                            data-area="<?php echo htmlspecialchars($complaint['area_name'] ?? 'N/A'); ?>"
                                            data-created="<?php echo date('d M Y, h:i A', strtotime($complaint['submission_time'])); ?>"
                                            data-resolved="<?php echo $complaint['response_time'] ? date('d M Y, h:i A', strtotime($complaint['response_time'])) : ''; ?>">
                                        <i class="fas fa-eye"></i> View / Respond
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View/Respond Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1" role="dialog" aria-labelledby="complaintModalLabel">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="complaintModalLabel">
                    <i class="fas fa-file-alt mr-2"></i>Complaint Details
                </h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="complaintModalContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View complaint modal
    document.querySelectorAll('.view-complaint-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const description = this.dataset.description;
            const status = this.dataset.status;
            const image = this.dataset.image;
            const response = this.dataset.response;
            const feedback = this.dataset.feedback;
            const rating = parseInt(this.dataset.rating) || 0;
            const resident = this.dataset.resident;
            const email = this.dataset.email;
            const phone = this.dataset.phone;
            const address = this.dataset.address;
            const area = this.dataset.area;
            const created = this.dataset.created;
            const resolved = this.dataset.resolved;

            let statusClass = 'warning';
            if (status === 'In Progress') statusClass = 'info';
            if (status === 'Resolved') statusClass = 'success';

            let html = '<div class="row">';
            
            // Left column - Resident info and issue
            html += '<div class="col-lg-' + (image ? '7' : '12') + '">';
            
            // Resident info card
            html += '<div class="card mb-3 border-left-primary">';
            html += '<div class="card-body py-2">';
            html += '<div class="row">';
            html += '<div class="col-md-6">';
            html += '<p class="mb-1"><strong><i class="fas fa-user mr-1"></i> Resident:</strong> ' + resident + '</p>';
            if (phone) html += '<p class="mb-1"><strong><i class="fas fa-phone mr-1"></i> Phone:</strong> ' + phone + '</p>';
            if (email) html += '<p class="mb-1"><strong><i class="fas fa-envelope mr-1"></i> Email:</strong> ' + email + '</p>';
            html += '</div>';
            html += '<div class="col-md-6">';
            html += '<p class="mb-1"><strong><i class="fas fa-map-marker-alt mr-1"></i> Area:</strong> ' + area + '</p>';
            if (address) html += '<p class="mb-1"><strong><i class="fas fa-home mr-1"></i> Address:</strong> ' + address + '</p>';
            html += '</div>';
            html += '</div>';
            html += '</div></div>';

            // Status and dates
            html += '<div class="d-flex justify-content-between align-items-center mb-3">';
            html += '<div><strong>Status:</strong> <span class="badge badge-' + statusClass + ' ml-1">' + status + '</span></div>';
            html += '<div><small class="text-muted">Submitted: ' + created + '</small></div>';
            html += '</div>';
            if (resolved) {
                html += '<p class="text-success mb-3"><i class="fas fa-check mr-1"></i> Resolved on: ' + resolved + '</p>';
            }

            // Issue description
            html += '<h6 class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-1 text-warning"></i> Issue Description:</h6>';
            html += '<div class="bg-light p-3 rounded mb-3">' + description + '</div>';

            html += '</div>';

            // Right column - Image (if exists)
            if (image) {
                html += '<div class="col-lg-5">';
                html += '<h6 class="font-weight-bold mb-2"><i class="fas fa-image mr-1"></i> Attached Image:</h6>';
                html += '<a href="' + image + '" target="_blank">';
                html += '<img src="' + image + '" class="complaint-image shadow-sm" alt="Complaint Image">';
                html += '</a>';
                html += '<small class="d-block text-muted mt-1">Click image to view full size</small>';
                html += '</div>';
            }

            html += '</div>';

            // Admin Response section
            if (response) {
                html += '<hr>';
                if (status === 'Resolved') {
                    html += '<div class="bg-success text-white p-3 rounded">';
                    html += '<h6 class="font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Your Response:</h6>';
                    html += '<p class="mb-0">' + response + '</p>';
                    html += '</div>';
                } else if (status === 'In Progress') {
                    html += '<div class="bg-info text-white p-3 rounded">';
                    html += '<h6 class="font-weight-bold"><i class="fas fa-comment mr-1"></i> Current Response/Notes:</h6>';
                    html += '<p class="mb-0">' + response + '</p>';
                    html += '</div>';
                }
            }

            // Resident Feedback
            if (feedback) {
                html += '<div class="bg-info text-white p-3 rounded mt-3">';
                html += '<h6 class="font-weight-bold"><i class="fas fa-comment mr-1"></i> Resident Feedback:</h6>';
                
                // Display star rating
                if (rating > 0) {
                    html += '<div class="mb-2">';
                    html += '<div class="star-rating-display">';
                    for (let i = 1; i <= 5; i++) {
                        html += '<span class="star' + (i <= rating ? '' : ' empty') + '">★</span>';
                    }
                    html += '</div>';
                    
                    // Rating text
                    const ratingText = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                    html += '<span class="font-weight-bold">' + ratingText[rating] + '</span>';
                    html += '</div>';
                }
                
                html += '<p class="mb-0">' + feedback + '</p>';
                html += '</div>';
            }

            // Response form (if not resolved)
            if (status !== 'Resolved') {
                html += '<hr>';
                html += '<div class="bg-light p-3 rounded">';
                html += '<h6 class="font-weight-bold"><i class="fas fa-reply mr-1 text-primary"></i> ' + (response ? 'Update Response' : 'Submit Response') + '</h6>';
                html += '<form method="POST">';
                html += '<input type="hidden" name="complaint_id" value="' + id + '">';
                
                if (status === 'Pending') {
                    html += '<div class="mb-3">';
                    html += '<button type="submit" name="mark_in_progress" class="btn btn-info btn-sm">';
                    html += '<i class="fas fa-spinner mr-1"></i> Mark as In Progress (saves response below)';
                    html += '</button>';
                    html += '</div>';
                }
                
                html += '<div class="form-group">';
                html += '<label for="admin_response" class="font-weight-bold">Your Response:</label>';
                html += '<textarea name="admin_response" id="admin_response" class="form-control" rows="4" placeholder="Type your response to the resident...">' + (response || '') + '</textarea>';
                html += '<small class="form-text text-muted">Note: Response will be saved when you click either button above or below.</small>';
                html += '</div>';
                html += '<button type="submit" name="submit_response" class="btn btn-success">';
                html += '<i class="fas fa-paper-plane mr-1"></i> Submit Response & Mark Resolved';
                html += '</button>';
                html += '</form>';
                html += '</div>';
            }

            document.getElementById('complaintModalContent').innerHTML = html;
            document.getElementById('complaintModalLabel').innerHTML = '<i class="fas fa-file-alt mr-2"></i>Complaint #' + id;
            
            $('#complaintModal').modal('show');
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once '../includes/admin_template.php';
?>
