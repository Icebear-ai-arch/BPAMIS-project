
<?php
session_start();
include '../server/server.php';

if (isset($_POST['Signup'])) {
    $fname = trim($_POST['reg_fname'] ?? '');
    $mname = trim($_POST['reg_mname'] ?? '');
    $lname = trim($_POST['reg_lname'] ?? '');

    $house_no = trim($_POST['reg_house_no'] ?? '');
    $purok    = trim($_POST['reg_purok'] ?? '');
    // Same style as register: fixed barangay/city is appended
    $address  = "$house_no, $purok, Barangay Panducot, Calumpit, Bulacan";

    $email = trim($_POST['reg_email'] ?? '');
    $raw_pass = $_POST['reg_pass'] ?? '';
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);

    // Optional birthdate handling
    $birthdate_raw = trim($_POST['reg_birthdate'] ?? '');
    $birthdate_sql = null;
    $age_str = '';
    if ($birthdate_raw !== '') {
        $bd = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
        if (!$bd) {
            echo '<script>alert("Invalid birthdate format."); window.location.href="../SecMenu/add_resident.php";</script>';
            exit;
        }
        $today = new DateTime('today');
        if ($bd >= $today) {
            echo '<script>alert("Invalid birthdate"); window.location.href="../SecMenu/add_resident.php";</script>';
            exit;
        }
        $age = $bd->diff($today)->y;
        if ($age <= 6) {
            echo '<script>alert("Invalid birthdate"); window.location.href="../SecMenu/add_resident.php";</script>';
            exit;
        }
        $birthdate_sql = $bd->format('Y-m-d');
        $age_str = (string)$age;
    }

    // Enforce cross-table email uniqueness
    $checkSql = "SELECT (SELECT COUNT(*) FROM resident_info WHERE email = ?) 
               + (SELECT COUNT(*) FROM external_complainant WHERE email = ?) 
               + (SELECT COUNT(*) FROM barangay_officials WHERE email = ?) AS total";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("sss", $email, $email, $email);
    $checkStmt->execute();
    $checkStmt->bind_result($total);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($total > 0) {
        echo '<script>alert("Email is already used."); window.location.href="../SecMenu/add_resident.php";</script>';
        exit;
    }

    // Insert resident (no valid ID upload required)
    // Keep column list consistent with public register
    $valid_id_db = '';
    $stmt = $conn->prepare("INSERT INTO resident_info (first_name, last_name, middle_name, address, email, password, valid_id_path, Birthdate, age, isActive, isVerify) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    if ($stmt === false) {
        echo '<script>alert("Registration failed: DB prepare error."); window.location.href="../SecMenu/add_resident.php";</script>';
        exit;
    }
    $stmt->bind_param("sssssssss", $fname, $lname, $mname, $address, $email, $password, $valid_id_db, $birthdate_sql, $age_str);

    if ($stmt->execute()) {
        echo '<script>alert("Resident created successfully."); window.location.href="../SecMenu/add_resident.php";</script>';
        exit();
    } else {
        echo '<script>alert("Registration failed: ' . htmlspecialchars($stmt->error) . '"); window.location.href="../SecMenu/add_resident.php";</script>';
    }

    $stmt->close();
    $conn->close();
} else {
    echo '<script>alert("Invalid access."); window.location.href="../SecMenu/add_resident.php";</script>';
}
?>