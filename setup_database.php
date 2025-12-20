<?php
$host = "localhost";
$username = "root";
$password = "";

// Create connection without database
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS swm_system";
if ($conn->query($sql)) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db("swm_system");

// Create users table
$users_table = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    work_id VARCHAR(10) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    role ENUM('admin', 'staff', 'resident') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($users_table)) {
    echo "Users table created successfully<br>";

    // Check if admin user exists
    $check_admin = $conn->query("SELECT * FROM users WHERE work_id = 'ADM001'");
    if ($check_admin->num_rows == 0) {
        // Create default admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (work_id, name, password, role) 
                        VALUES ('ADM001', 'Admin User', '$admin_password', 'admin')";
        
        if ($conn->query($insert_admin)) {
            echo "Default admin user created successfully<br>";
            echo "Login credentials:<br>";
            echo "Work ID: ADM001<br>";
            echo "Password: admin123<br>";
        } else {
            echo "Error creating admin user: " . $conn->error . "<br>";
        }
    } else {
        echo "Admin user already exists<br>";
    }
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create collection_areas table
$areas_table = "CREATE TABLE IF NOT EXISTS collection_areas (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($areas_table)) {
    echo "Collection areas table created successfully<br>";
} else {
    echo "Error creating collection areas table: " . $conn->error . "<br>";
}

// Create collection_schedule table
$schedule_table = "CREATE TABLE IF NOT EXISTS collection_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT,
    staff_id INT,
    collection_date DATE NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES collection_areas(area_id),
    FOREIGN KEY (staff_id) REFERENCES users(user_id)
)";

if ($conn->query($schedule_table)) {
    echo "Collection schedule table created successfully<br>";
} else {
    echo "Error creating collection schedule table: " . $conn->error . "<br>";
}

// Create staff table if it doesn't exist
$staff_table = "CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($staff_table)) {
    echo "Staff table created successfully<br>";
} else {
    echo "Error creating staff table: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Database setup completed. You can now <a href='public/login.php?role=staff_auto'>login</a> with the admin credentials shown above.";
?>