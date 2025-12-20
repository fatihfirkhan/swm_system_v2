<?php
header('Content-Type: application/json');

// Start session and check database connection
require_once '../includes/db.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

// Get area_id from POST
$area_id = intval($_POST['area_id'] ?? 0);

if ($area_id === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid area ID'
    ]);
    exit;
}

try {
    // Fetch lanes for the selected area
    $query = "SELECT lane_id, lane_name 
              FROM collection_lane 
              WHERE area_id = ? 
              ORDER BY lane_name ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lanes = [];
    while ($row = $result->fetch_assoc()) {
        $lanes[] = [
            'lane_id' => $row['lane_id'],
            'lane_name' => htmlspecialchars($row['lane_name'])
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'lanes' => $lanes,
        'count' => count($lanes)
    ]);
    
} catch (Exception $e) {
    error_log("get_lanes.php error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}

exit;
?>
