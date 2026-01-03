<?php
/**
 * Handle Admin Schedule Operations
 * - AJAX: update/delete actions (returns JSON)
 * - Form: submit button (redirects to page)
 */

// Start output buffering FIRST to catch any whitespace
ob_start();

// Suppress error output to screen, log them instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../includes/db.php';

// Helper function to send JSON response properly
function send_json_response($status, $message) {
    ob_clean(); // Wipe buffer
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Check admin authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (isset($_POST['action'])) {
        send_json_response('error', 'Unauthorized access');
    }
    header('Location: /login.php');
    exit;
}

/**
 * =============================================
 * AJAX OPERATIONS - Return JSON only
 * =============================================
 */
if (isset($_POST['action'])) {
    
    $action = $_POST['action'];
    
    // ---------- DELETE SCHEDULE ----------
    if ($action === 'delete') {
        $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        
        if ($schedule_id <= 0) {
            send_json_response('error', 'Invalid schedule ID');
        }
        
        // Get schedule date
        $stmt = $conn->prepare("SELECT collection_date FROM schedule WHERE schedule_id = ?");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            send_json_response('error', 'Schedule not found');
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // Block past dates
        if (strtotime($row['collection_date']) < strtotime(date('Y-m-d'))) {
            send_json_response('error', 'Cannot delete past schedules');
        }
        
        // Execute Delete
        $delStmt = $conn->prepare("DELETE FROM schedule WHERE schedule_id = ?");
        $delStmt->bind_param("i", $schedule_id);
        
        if ($delStmt->execute()) {
            $delStmt->close();
            send_json_response('success', 'Schedule deleted successfully');
        } else {
            $err = $delStmt->error;
            $delStmt->close();
            send_json_response('error', 'Delete failed: ' . $err);
        }
    }

    // ---------- UPDATE SCHEDULE ----------
    if ($action === 'update') {
        $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        $collection_type = isset($_POST['collection_type']) ? trim($_POST['collection_type']) : '';
        $truck_id = (isset($_POST['truck_id']) && $_POST['truck_id'] !== '') ? intval($_POST['truck_id']) : null;
        
        if ($schedule_id <= 0) {
            send_json_response('error', 'Invalid schedule ID');
        }
        
        if (!in_array($collection_type, ['Domestic', 'Recycle'])) {
            send_json_response('error', 'Invalid collection type');
        }

        // Get current schedule details to check date
        $stmt = $conn->prepare("SELECT collection_date FROM schedule WHERE schedule_id = ?");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            send_json_response('error', 'Schedule not found');
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // Block past dates
        if (strtotime($row['collection_date']) < strtotime(date('Y-m-d'))) {
            send_json_response('error', 'Cannot edit past schedules');
        }
        
        // Execute Update
        if ($truck_id !== null && $truck_id > 0) {
            $upStmt = $conn->prepare("UPDATE schedule SET collection_type = ?, truck_id = ?, update_time = NOW() WHERE schedule_id = ?");
            $upStmt->bind_param("sii", $collection_type, $truck_id, $schedule_id);
        } else {
            $upStmt = $conn->prepare("UPDATE schedule SET collection_type = ?, truck_id = NULL, update_time = NOW() WHERE schedule_id = ?");
            $upStmt->bind_param("si", $collection_type, $schedule_id);
        }
        
        if ($upStmt->execute()) {
            $upStmt->close();
            send_json_response('success', 'Schedule updated successfully');
        } else {
            $err = $upStmt->error;
            $upStmt->close();
            send_json_response('error', 'Update failed: ' . $err);
        }
    }
    
    // Unknown action
    send_json_response('error', 'Unknown action');
}

/**
 * =============================================
 * FORM SUBMISSION - Redirect only
 * =============================================
 */
if (isset($_POST['submit'])) {
    $area_id = intval($_POST['area_id'] ?? 0);
    $collection_dates_raw = $_POST['collection_dates'] ?? '';
    $collection_type = $_POST['collection_type'] ?? '';
    $truck_id = (isset($_POST['truck_id']) && $_POST['truck_id'] !== '') ? intval($_POST['truck_id']) : null;

    // Validate basic fields
    if ($area_id <= 0 || empty($collection_dates_raw) || empty($collection_type)) {
        header("Location: /adminschedule.php?error=missing_fields");
        exit;
    }

    // Parse multiple dates (comma-separated from Flatpickr)
    $dates = array_map('trim', explode(',', $collection_dates_raw));
    $dates = array_filter($dates); // Remove empty values
    
    if (empty($dates)) {
        header("Location: /adminschedule.php?error=missing_fields");
        exit;
    }

    $today = date('Y-m-d');
    $successCount = 0;
    $duplicateCount = 0;
    $pastDateCount = 0;
    $errorCount = 0;

    foreach ($dates as $collection_date) {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $collection_date)) {
            $errorCount++;
            continue;
        }

        // Block past dates
        if (strtotime($collection_date) < strtotime($today)) {
            $pastDateCount++;
            continue;
        }

        // Check duplicate
        $chk = $conn->prepare("SELECT schedule_id FROM schedule WHERE area_id = ? AND collection_date = ?");
        $chk->bind_param("is", $area_id, $collection_date);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            $duplicateCount++;
            continue;
        }
        $chk->close();

        // Insert
        if ($truck_id !== null) {
            $ins = $conn->prepare("INSERT INTO schedule (area_id, collection_date, collection_type, truck_id, status, update_time) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $ins->bind_param("issi", $area_id, $collection_date, $collection_type, $truck_id);
        } else {
            $ins = $conn->prepare("INSERT INTO schedule (area_id, collection_date, collection_type, truck_id, status, update_time) VALUES (?, ?, ?, NULL, 'Pending', NOW())");
            $ins->bind_param("iss", $area_id, $collection_date, $collection_type);
        }
        
        if ($ins->execute()) {
            $successCount++;
        } else {
            $errorCount++;
        }
        $ins->close();
    }

    // Build result message
    $totalDates = count($dates);
    if ($successCount == $totalDates) {
        header("Location: /adminschedule.php?status=success&count=" . $successCount);
    } elseif ($successCount > 0) {
        // Partial success
        $msg = "added={$successCount}";
        if ($duplicateCount > 0) $msg .= "&skipped_dup={$duplicateCount}";
        if ($pastDateCount > 0) $msg .= "&skipped_past={$pastDateCount}";
        header("Location: /adminschedule.php?status=partial&{$msg}");
    } elseif ($duplicateCount > 0) {
        header("Location: /adminschedule.php?error=duplicate");
    } elseif ($pastDateCount > 0) {
        header("Location: /adminschedule.php?error=past_date");
    } else {
        header("Location: /adminschedule.php?error=insert_failed");
    }
    exit;
}

// No valid request - redirect back
header("Location: /adminschedule.php");
exit;
