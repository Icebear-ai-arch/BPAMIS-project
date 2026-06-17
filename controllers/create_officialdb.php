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
    $name    =  "$fname". " " . "$mname"." "."$lname";
    $contact = $_POST['reg_contact'];
    $email   = $_POST['reg_email'];
    // Server-side password confirmation (prevents mismatch if client-side JS is bypassed)
    $raw_password = $_POST['reg_pass'] ?? '';
    $confirm_password = $_POST['reg_pass_confirm'] ?? '';
    if ($raw_password !== $confirm_password) {
        echo '<script>alert("Passwords do not match."); window.location.href="../SecMenu/add_official_account.php";</script>';
        exit;
    }
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    $position = $_POST['reg_type'];

    // Optional birthdate: validate if provided
    $birthdate_raw = trim($_POST['reg_birthdate'] ?? '');
    $birthdate_sql = null;
    if ($birthdate_raw !== '') {
        $bd = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
        if (!$bd) {
            echo '<script>alert("Invalid birthdate format."); window.location.href="../SecMenu/add_official_account.php";</script>';
            exit;
        }
        $today = new DateTime('today');
        if ($bd >= $today) {
            echo '<script>alert("Invalid birthdate."); window.location.href="../SecMenu/add_official_account.php";</script>';
            exit;
        }
        $age = $bd->diff($today)->y;
        if ($age <= 6) {
            echo '<script>alert("Invalid birthdate."); window.location.href="../SecMenu/add_official_account.php";</script>';
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
        echo '<script>alert("Email is already used."); window.location.href="../SecMenu/add_official_account.php";</script>';
        exit;
    }

    // Insert user without username yet
    $sql = "INSERT INTO barangay_officials 
        (Name, Contact_Number, email, password, Position, Birthdate)
        VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $name, $contact, $email, $password, $position, $birthdate_sql);

    if ($stmt->execute()) {
    $officialId = $conn->insert_id;

    // Set official username to the user's position plus Official_ID to make it unique
    // e.g., "Secretary-123"
    $official_username = "{$position}-{$officialId}";

        // Update username and activate account
        $update = $conn->prepare("UPDATE barangay_officials SET isActive = 1, official_username = ? WHERE Official_ID = ?");
        $update->bind_param("si", $official_username, $officialId);
        $update->execute();

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vincentaaronvicente795@gmail.com';
            $mail->Password = 'vwfq cqez mmdf hssm';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Set the sender name per request
            $mail->setFrom('vincentaaronvicente795@gmail.com', 'Barangay Panducot');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            // Simplified email body per request: inform user they can login as their position
            $mail->Subject = 'Your Account Has Been Created';
            $mail->Body = "
                <p>Dear <strong>$name</strong>,</p>
                <p>Your account has been successfully created. You can now login as <strong>$position</strong>.</p>
                <p>Regards,<br>Barangay Panducot</p>
                <p><em>This is an automated message. Please do not reply.</em></p>
            ";

            $mail->send();
            // Email sent — notify and redirect
            echo '<script>alert("'.$position.' account created! Confirmation sent via email."); window.location.href="../SecMenu/add_official_account.php";</script>';
            exit;
        } catch (Exception $e) {
            echo '<script>alert("Account created but email failed to send"); window.location.href="../SecMenu/add_official_account.php";</script>';
            exit;
        }
    } else {
        echo '<script>alert("Account Creation failed."); window.location.href="../SecMenu/add_official_account.php";</script>';
    }

    $stmt->close();
    $conn->close();

} else {
    echo '<script>alert("Invalid access."); window.location.href="../SecMenu/add_official_account.php";</script>';
}
?>