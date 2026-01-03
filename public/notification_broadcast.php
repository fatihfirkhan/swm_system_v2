<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['work_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Notification Broadcast - SWM Environment';
$currentPage = 'notification';

// Create notifications table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target_role VARCHAR(20) NOT NULL,
        time_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Add title column if it doesn't exist (for existing tables)
// Check if column exists first since MySQL doesn't support IF NOT EXISTS for columns
$result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'title'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT 'System Notification' AFTER notification_id");
}

// Handle form submission
$successMessage = '';
$errorMessage = '';

// Check for success status from redirect (PRG pattern)
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $successMessage = 'Notification sent successfully!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $targetRole = $_POST['target_role'] ?? '';
    
    // Validate inputs
    if (empty($title)) {
        $errorMessage = 'Please enter a title.';
    } elseif (empty($message)) {
        $errorMessage = 'Please enter a message.';
    } elseif (!in_array($targetRole, ['Resident', 'Staff', 'All'])) {
        $errorMessage = 'Please select a valid audience.';
    } else {
        // Insert notification into database
        $stmt = $conn->prepare("INSERT INTO notifications (title, message, target_role, time_created) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('sss', $title, $message, $targetRole);
        
        if ($stmt->execute()) {
            $stmt->close();
            // PRG Pattern: Redirect to prevent form resubmission
            header("Location: notification_broadcast.php?status=success");
            exit();
        } else {
            $errorMessage = 'Failed to send notification. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch recent notifications for display
$recentNotifications = $conn->query("
    SELECT title, message, target_role, time_created 
    FROM notifications 
    ORDER BY time_created DESC 
    LIMIT 10
");

// Additional styles
$additionalStyles = '';

// Additional scripts
$additionalScripts = '';

// Start output buffering to capture the page content
ob_start();
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Notification Broadcast</h1>
</div>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Broadcast Form -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-bullhorn mr-2"></i>Send Broadcast
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Title Input -->
                    <div class="form-group">
                        <label for="title" class="font-weight-bold">Title</label>
                        <input 
                            type="text" 
                            name="title" 
                            id="title" 
                            class="form-control" 
                            placeholder="Enter notification title..."
                            value="<?php echo (!$successMessage && isset($_POST['title'])) ? htmlspecialchars($_POST['title']) : ''; ?>"
                            required
                        >
                        <small class="form-text text-muted">A short, descriptive title for the notification.</small>
                    </div>

                    <!-- Message Text Area -->
                    <div class="form-group">
                        <label for="message" class="font-weight-bold">Message</label>
                        <textarea 
                            name="message" 
                            id="message" 
                            class="form-control" 
                            rows="6" 
                            placeholder="Enter your notification message here..."
                            required
                        ><?php echo (!$successMessage && isset($_POST['message'])) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        <small class="form-text text-muted">Write a clear and concise message for your audience.</small>
                    </div>

                    <!-- Audience Selector -->
                    <div class="form-group">
                        <label class="font-weight-bold">Send to</label>
                        <div class="mt-2">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="roleResident" name="target_role" value="Resident" 
                                       class="custom-control-input" required
                                       <?php echo (!$successMessage && isset($_POST['target_role']) && $_POST['target_role'] === 'Resident') ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="roleResident">
                                    <i class="fas fa-home mr-1"></i>Residents
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="roleStaff" name="target_role" value="Staff" 
                                       class="custom-control-input"
                                       <?php echo (!$successMessage && isset($_POST['target_role']) && $_POST['target_role'] === 'Staff') ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="roleStaff">
                                    <i class="fas fa-user-tie mr-1"></i>Staff
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="roleAll" name="target_role" value="All" 
                                       class="custom-control-input"
                                       <?php echo (!$successMessage && isset($_POST['target_role']) && $_POST['target_role'] === 'All') ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="roleAll">
                                    <i class="fas fa-users mr-1"></i>All Users
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="send_notification" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane mr-2"></i>Send Notification
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history mr-2"></i>Recent Broadcasts
                </h6>
            </div>
            <div class="card-body">
                <?php if ($recentNotifications && $recentNotifications->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($notification = $recentNotifications->fetch_assoc()): ?>
                            <div class="list-group-item px-0">
                                <h6 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($notification['title'] ?? 'System Notification'); ?></h6>
                                <p class="mb-1 small text-gray-700"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge badge-<?php 
                                        echo $notification['target_role'] === 'Resident' ? 'info' : 
                                            ($notification['target_role'] === 'Staff' ? 'warning' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($notification['target_role']); ?>
                                    </span>
                                    <small class="text-muted">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($notification['time_created'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No notifications sent yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/admin_template.php';
?>
