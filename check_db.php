<?php
include 'includes/db.php';

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows == 0) {
    // Create users table if it doesn't exist
    $create_table = "CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        work_id VARCHAR(10) UNIQUE,
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        name VARCHAR(100),
        role ENUM('admin', 'staff', 'resident'),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table)) {
        echo "Users table created successfully\n";
        
        // Create an admin user for testing
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (work_id, name, password, role) 
                        VALUES ('ADM001', 'Admin User', '$admin_password', 'admin')";
        
        if ($conn->query($insert_admin)) {
            echo "Admin user created successfully (work_id: ADM001, password: admin123)\n";
        } else {
            echo "Error creating admin user: " . $conn->error . "\n";
        }
    } else {
        echo "Error creating users table: " . $conn->error . "\n";
    }
} else {
    echo "Users table exists. Current users:\n";
    $result = $conn->query("SELECT work_id, email, name, role FROM users");
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
}
?>