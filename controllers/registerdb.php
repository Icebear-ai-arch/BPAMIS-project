
<?php
include '../server/server.php';

if (isset($_POST['Signup'])) {
    $fname = trim($_POST['reg_fname'] ?? '');
    $lname = trim($_POST['reg_lname'] ?? '');
    $mname = trim($_POST['reg_mname'] ?? '');

    // Address: support desktop (house_no+purok) and mobile (reg_address)
    if (!empty($_POST['reg_house_no']) || !empty($_POST['reg_purok'])) {
        $house_no = trim($_POST['reg_house_no'] ?? '');
        $purok = trim($_POST['reg_purok'] ?? '');
        $address = "$house_no, $purok, Barangay Panducot, Calumpit, Bulacan";
    } else {
        $address = trim($_POST['reg_address'] ?? '');
        if ($address === '') {
            $address = "Barangay Panducot, Calumpit, Bulacan";
        }
    }

    $email = trim($_POST['reg_email'] ?? '');

    // Password: support both desktop and mobile field names
    $raw_pass = $_POST['reg_pass'] ?? $_POST['reg_pass_mobile'] ?? '';
    $password = password_hash($raw_pass, PASSWORD_DEFAULT);

    // Birthdate handling and validation
    $birthdate_raw = trim($_POST['reg_birthdate'] ?? '');
    if ($birthdate_raw === '') {
        echo '<script>alert("Please provide your birthdate."); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }
    $birthdate_dt = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
    if (!$birthdate_dt) {
        echo '<script>alert("Invalid birthdate format."); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }
    $today = new DateTime('today');
    // disallow today or future dates
    if ($birthdate_dt >= $today) {
        echo '<script>alert("Invalid birthdate"); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }
    $age = $birthdate_dt->diff($today)->y;
    if ($age <= 7) {
        echo '<script>alert("Invalid birthdate"); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }
    $age_str = (string)$age;

    // Handle valid_id upload (single file) -> store in uploads_id/, save path into DB valid_id_path
    $valid_id_path = null;

    // Enforce presence of uploaded valid ID
    if (!isset($_FILES['valid_id']) || !isset($_FILES['valid_id']['error']) || $_FILES['valid_id']['error'] === UPLOAD_ERR_NO_FILE) {
        echo '<script>alert("Please upload a valid ID (JPG or PNG, max 1MB)."); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }

    if (isset($_FILES['valid_id']) && isset($_FILES['valid_id']['error']) && $_FILES['valid_id']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['valid_id'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            // 1MB hard limit
            $MAX_ID_BYTES = 1 * 1024 * 1024;

            // Validate size first
            if ($file['size'] > $MAX_ID_BYTES) {
                echo '<script>alert("File size is too large. Maximum allowed is 1MB."); window.location.href="../bpamis_website/register.php";</script>';
                exit;
            }

            // Validate MIME-type using reliable PHP functions and also check extension
            $finfo = @mime_content_type($file['tmp_name']) ?: $file['type'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Allowed extensions and image types
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $allowed_mime = ['image/jpeg', 'image/png'];

            // Use exif_imagetype where available to determine actual image type
            $detected_type = false;
            if (function_exists('exif_imagetype')) {
                $detected_type = @exif_imagetype($file['tmp_name']);
            }

            $is_valid_ext = in_array($ext, $allowed_ext, true);
            $is_valid_mime = in_array($finfo, $allowed_mime, true);
            $is_valid_imagetype = in_array($detected_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);

            if (!($is_valid_ext && $is_valid_mime && ($detected_type === false ? true : $is_valid_imagetype))) {
                // If any check fails, reject the upload
                echo '<script>alert("Invalid Valid ID. Only JPG, JPEG or PNG images under 1MB are allowed."); window.location.href="../bpamis_website/register.php";</script>';
                exit;
            }

            $uploadDir = __DIR__ . '/../uploads_id/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $origName = basename($file['name']);
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
            try {
                $uniq = time() . '_' . bin2hex(random_bytes(6));
            } catch (Exception $e) {
                $uniq = time() . '_' . mt_rand(1000,9999);
            }
            $targetName = $uniq . '_' . $safeName;
            $targetPath = $uploadDir . $targetName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $valid_id_path = 'uploads_id/' . $targetName;
            } else {
                echo '<script>alert("Failed to save Valid ID file. Please try again."); window.location.href="../bpamis_website/register.php";</script>';
                exit;
            }
        } else {
            echo '<script>alert("Error uploading Valid ID file."); window.location.href="../bpamis_website/register.php";</script>';
            exit;
        }
    }

    // Cross-table email uniqueness check (Resident, External, Official)
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
        echo '<script>alert("Email is already used."); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }

    // Insert into DB (includes valid_id_path, Birthdate and age columns).
    $valid_id_db = $valid_id_path ?? '';
    $stmt = $conn->prepare("INSERT INTO resident_info (first_name, last_name, middle_name, address, email, password, valid_id_path, Birthdate, age) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        echo '<script>alert("Registration failed: DB prepare error."); window.location.href="../bpamis_website/register.php";</script>';
        exit;
    }
    $stmt->bind_param("sssssssss", $fname, $lname, $mname, $address, $email, $password, $valid_id_db, $birthdate_raw, $age_str);

    if ($stmt->execute()) {
        echo '<script>alert("Sign up successful! Welcome, ' . htmlspecialchars($fname) . ' ' . htmlspecialchars($lname) . '"); window.location.href="../bpamis_website/login.php";</script>';
        exit();
    } else {
        // If file was saved but DB failed, optionally remove uploaded file to avoid orphaned files
        if ($valid_id_path && file_exists(__DIR__ . '/../' . $valid_id_path)) {
            @unlink(__DIR__ . '/../' . $valid_id_path);
        }
        echo '<script>alert("Registration failed: ' . htmlspecialchars($stmt->error) . '"); window.location.href="../bpamis_website/register.php";</script>';
    }

    $stmt->close();
    $conn->close();
} else {
    echo '<script>alert("SIGNUP Failed"); window.location.href="../bpamis_website/register.php";</script>';
}
?>