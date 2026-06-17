<?php
session_start();
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include '../server/server.php'; 

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
    return substr(str_shuffle($chars), 0, $length);
}

if (isset($_POST['submitActivation'])) {

    $email = $_POST['activation_email'];

    // 1. Get user details by email
    $stmt = $conn->prepare("SELECT resident_id, first_name, last_name, email, isverify 
                            FROM resident_info WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    $resident = $result->fetch_assoc();

    if ($resident) {
        // Check if already verified
        if ($resident['isverify'] == 1) {
            echo "<script>alert('This account is already activated. Please log in.'); 
                  window.location.href='../bpamis_website/login.php';</script>";
            exit;
        }

        // 2. Generate random password
        $plainPassword = generateRandomPassword(8);
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 3. Update verification + password
        $update = $conn->prepare("UPDATE resident_info 
                                  SET isVerify = 1, password = ? 
                                  WHERE resident_id = ?");
        $update->bind_param("si", $hashedPassword, $resident['resident_id']);
        $update->execute();

        // 4. Send email to the user
        $mail = new PHPMailer(true);
        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vincentaaronvicente795@gmail.com';        
            $mail->Password = 'vwfq cqez mmdf hssm'; // App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Email content
            $mail->setFrom('vincentaaronvicente795@gmail.com', 'Barangay Secretary');
            $mail->addAddress($resident['email'], $resident['first_name']." ".$resident['last_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Your Barangay Account is Verified';
            $mail->Body = "
                <p>Dear <strong>{$resident['first_name']} {$resident['last_name']}</strong>,</p>
                <p>Your account has been successfully <strong>verified</strong> by the Barangay system.</p>
                <p>You may now fully access the system using your email and the password below:</p><br>
                <h2>Your Temporary Password</h2>
                <p><strong>{$plainPassword}</strong></p>
                <p><em>Please change your password immediately after logging in for security.</em></p>
                <br><p>Regards,<br>Barangay Admin</p>
            ";

            $mail->send();

            // Success alert then redirect
            echo "<script>alert('Activation successful! A temporary password has been sent to your email.'); 
                  window.location.href='../bpamis_website/login.php';</script>";
            exit;

        } catch (Exception $e) {
            // Failure alert then redirect
            echo "<script>alert('Failed to send activation email. Please try again.'); 
                  window.location.href='../bpamis_website/register.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('No account found with that email address.'); 
              window.location.href='../bpamis_website/register.php';</script>";
        exit;
    }
}
?>
