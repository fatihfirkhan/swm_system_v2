<?php
include '../includes/db.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (isset($_POST['register'])) {
    $role = $_POST['role'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address1 = $_POST['address_line1'];
    $address2 = $_POST['address_line2'];
    $postcode = $_POST['postcode'];
    $raw_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate all required fields
    if (
        !isset($role) || trim($role) === '' ||
        !isset($name) || trim($name) === '' ||
        !isset($phone) || trim($phone) === '' ||
        !isset($address1) || trim($address1) === '' ||
        !isset($postcode) || trim($postcode) === '' ||
        !isset($raw_password) || trim($raw_password) === ''
    ) {
        $_SESSION['old'] = $_POST;
        header("Location: /staffregister.php?error=incomplete");
        exit;
    }

    // Check if passwords match
    if ($raw_password !== $confirm_password) {
        $_SESSION['old'] = $_POST;
        header("Location: /staffregister.php?error=password_mismatch");
        exit;
    }

    $password = password_hash($raw_password, PASSWORD_DEFAULT);

    // Auto-generate work_id
    $prefix = ($role === 'admin') ? 'ADM' : 'STF';
    $sql = "SELECT work_id FROM user WHERE role = ? ORDER BY user_id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['work_id'];
        $number = intval(substr($last_id, 3)) + 1;
    } else {
        $number = 1;
    }

    $work_id = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);

    // Optional: check for duplicate work_id
    $check = $conn->prepare("SELECT * FROM user WHERE work_id = ?");
    $check->bind_param("s", $work_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['old'] = $_POST;
        header("Location: /staffregister.php?error=duplicate");
        exit;
    }

    // Insert new user
    $sql = "INSERT INTO user (name, phone, address_line1, address_line2, postcode, work_id, password, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $name, $phone, $address1, $address2, $postcode, $work_id, $password, $role);
    $stmt->execute();

    header("Location: /staffregister.php?success=1&work_id=$work_id");
    exit;
}
?>
