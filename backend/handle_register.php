<?php
session_start();
require_once '../includes/db.php';

if (isset($_POST['register'])) {
    // Capture form inputs
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $house_unit_number = trim($_POST['house_unit_number'] ?? '');
    $area_id = intval($_POST['area_id'] ?? 0);
    $lane_id = intval($_POST['lane_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($name) || empty($phone) || empty($house_unit_number) || 
        empty($area_id) || empty($lane_id) || empty($email) || empty($password)) {
        header("Location: ../public/register.php?error=incomplete");
        exit;
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        header("Location: ../public/register.php?error=password_mismatch");
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../public/register.php?error=email");
        exit;
    }

    // Check if email already exists
    $check_email = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    $result = $check_email->get_result();

    if ($result->num_rows > 0) {
        header("Location: ../public/register.php?error=email");
        exit;
    }

    // Fetch lane_name and taman_name from database
    $address_query = "SELECT cl.lane_name, ca.taman_name, ca.postcode 
                      FROM collection_lane cl 
                      JOIN collection_area ca ON cl.area_id = ca.area_id 
                      WHERE cl.lane_id = ? AND ca.area_id = ?";
    
    $stmt = $conn->prepare($address_query);
    $stmt->bind_param("ii", $lane_id, $area_id);
    $stmt->execute();
    $address_result = $stmt->get_result();

    if ($address_result->num_rows === 0) {
        header("Location: ../public/register.php?error=incomplete");
        exit;
    }

    $address_data = $address_result->fetch_assoc();
    $lane_name = $address_data['lane_name'];
    $taman_name = $address_data['taman_name'];
    $postcode = $address_data['postcode'];

    // Auto-generate full address: "House Number, Lane Name, Taman Name"
    $address_line1 = $house_unit_number . ', ' . $lane_name . ', ' . $taman_name;
    $address_line2 = ''; // Keep empty for backward compatibility

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'resident';

    // Insert new user with structured address
    $insert_sql = "INSERT INTO user 
                   (name, phone, house_unit_number, lane_id, area_id, address_line1, address_line2, postcode, email, password, role) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param(
        "sssiissssss",
        $name,
        $phone,
        $house_unit_number,
        $lane_id,
        $area_id,
        $address_line1,
        $address_line2,
        $postcode,
        $email,
        $hashed_password,
        $role
    );

    if ($insert_stmt->execute()) {
        // Registration successful
        header("Location: ../public/login.php?role=resident&register=success");
        exit;
    } else {
        // Database error
        error_log("Registration error: " . $conn->error);
        header("Location: ../public/register.php?error=db");
        exit;
    }
}

// If accessed directly without POST
header("Location: ../public/register.php");
exit;
?>
