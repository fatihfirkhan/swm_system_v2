<?php
// Set timezone to Malaysia Time (MYT = UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Use environment variable for host (Docker uses 'db', local XAMPP uses 'localhost')
$host = getenv('DB_HOST') ?: 'localhost';  // 'localhost' for XAMPP, 'db' for Docker
$dbname = getenv('DB_NAME') ?: 'swm_system';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';  // Empty for local XAMPP
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to MYT
$conn->query("SET time_zone = '+08:00'");

// Auto-mark missed schedules (runs once per request, efficient query)
// Mark schedules as 'Missed' if collection_date is before today and status is still 'Pending'
$conn->query("UPDATE schedule SET status = 'Missed', update_time = NOW() 
              WHERE collection_date < CURDATE() AND status = 'Pending'");
