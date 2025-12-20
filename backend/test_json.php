<?php
// Test JSON response
header('Content-Type: application/json');
echo json_encode(['test' => 'success', 'message' => 'Backend is working']);
?>
