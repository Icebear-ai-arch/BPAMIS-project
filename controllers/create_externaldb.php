
<?php
session_start();
include '../server/server.php';
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['Signup'])) {
    $fname   = $_POST['reg_fname'];
    $lname   = $_POST['reg_lname'];
    $mname   = $_POST['reg_mname'];
    $address = $_POST['reg_address'];
    $email   = $_POST['reg_email'];
    $contact = $_POST['reg_contact'];
    $password = password_hash($_POST['reg_pass'], PASSWORD_DEFAULT);

    // Optional birthdate: validate if provided
    $birthdate_raw = trim($_POST['reg_birthdate'] ?? '');
    $birthdate_sql = null;
    if ($birthdate_raw !== '') {
        $bd = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
        if (!$bd) {
            echo '<script>alert("Invalid birthdate format."); window.location.href="../SecMenu/add_external_user.php";</script>';
            exit;
        }
        $today = new DateTime('today');
        if ($bd >= $today) {
            echo '<script>alert("Please input your real birthdate."); window.location.href="../SecMenu/add_external_user.php";</script>';
            exit;
        }
        $age = $bd->diff($today)->y;
        if ($age <= 6) {
            echo '<script>alert("Please input your real birthdate."); window.location.href="../SecMenu/add_external_user.php";</script>';
            exit;
        }
        $birthdate_sql = $bd->format('Y-m-d');
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
        echo '<script>alert("Email is already used."); window.location.href="../SecMenu/add_external_user.php";</script>';
        exit;
    }

    // 1. Insert user without username yet
    $sql = "INSERT INTO external_complainant 
        (first_name, last_name, middle_name, address, email, contact_number, password, Birthdate)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $fname, $lname, $mname, $address, $email, $contact, $password, $birthdate_sql);

    if ($stmt->execute()) {
    $externalId = $conn->insert_id;

    // Build username as E{externalId}-fname and sanitize the first name
    // e.g. E123-Juan (remove non-alphanumeric characters from first name)
    $clean_fname = preg_replace('/[^A-Za-z0-9]/', '', $fname);
    $external_username = "E{$externalId}-{$clean_fname}";

        // 3. Update the record with the generated username
        $update = $conn->prepare("UPDATE external_complainant SET isActive = 1, external_username = ? WHERE external_complaint_id = ?");
        $update->bind_param("si", $external_username, $externalId);
        $update->execute();

    // 4. Send email (no username included)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vincentaaronvicente795@gmail.com';
            $mail->Password = 'vwfq cqez mmdf hssm';  // App password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Use Barangay Panducot as the sender name and update the email body per request
            $mail->setFrom('vincentaaronvicente795@gmail.com', 'Barangay Panducot');
            $mail->addAddress($email, "$fname $lname");

            $mail->isHTML(true);
            $mail->Subject = 'Your Account Has Been Created';
            $mail->Body = "
                <p>Dear <strong>$fname $lname</strong>,</p>
                <p>Your account has been created. You may now login on Barangay Panducot Adjudication Management Information System.</p>
                <p>Regards,<br>Barangay Panducot</p>
                <p><em>This is an automated message. Please do not reply.</em></p>
            ";

            $mail->send();

            echo '<script>alert("Account created! Confirmation sent via email."); window.location.href="../SecMenu/add_external_user.php";</script>';
            exit;
        } catch (Exception $e) {
            echo '<script>alert("Account created but email failed to send"); window.location.href="../SecMenu/add_external_user.php";</script>';
            exit;
        }
    } else {
        echo '<script>alert("Account Creation failed."); window.location.href="../SecMenu/add_external_user.php";</script>';
    }

    $stmt->close();
    $conn->close();

} else {
    echo '<script>alert("Invalid access."); window.location.href="../SecMenu/add_external_user.php";</script>';
}
?>