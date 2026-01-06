<?php
// Use environment variable for host (Docker uses 'db', local XAMPP uses 'localhost')
$host = getenv('DB_HOST') ?: 'localhost';  // 'localhost' for XAMPP, 'db' for Docker
$dbname = getenv('DB_NAME') ?: 'swm_system';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';  // Empty for local XAMPP
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
