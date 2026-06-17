<?php
session_start();
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include '../server/server.php';

function send_email($toEmail, $toName, $subject, $bodyHtml){
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
         $mail->Username = 'vincentaaronvicente795@gmail.com';
        $mail->Password = 'vwfq cqez mmdf hssm'; // use app password if 2FA
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('vincentaaronvicente795@gmail.com', 'Barangay Secretary');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->send();
        return ['success'=>true];
    } catch (Exception $e){
        return ['success'=>false, 'error'=>$mail->ErrorInfo];
    }
}

/**
 * Lightweight email validation: format + DNS MX/A presence only.
 * Returns ['ok'=>bool, 'reason'=>string].
 * Avoids SMTP handshakes for speed and hosting compatibility.
 */
function verify_email_dns(string $email){
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok'=>false, 'reason'=>'invalid_format'];
    }
    [$user, $domain] = explode('@', $email, 2);
    $hasMx = function_exists('getmxrr') ? @getmxrr($domain, $mxhosts, $mxweight) && count($mxhosts) > 0 : false;
    $hasA  = checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    if ($hasMx || $hasA) return ['ok'=>true, 'reason'=>'dns_ok'];
    return ['ok'=>false, 'reason'=>'no_mx_or_a'];
}

// Best-effort SMTP RCPT probe similar to account_activation for stronger validation
function verify_email_smtp_rcpt_local($email,$timeout=3){
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) return false;
    [$u,$d]=explode('@',$email,2);
    $mx=[];
    if(function_exists('getmxrr') && @getmxrr($d,$mx,$w) && count($mx)>0){} else{$mx=[$d];}
    foreach($mx as $h){
        $fp=@fsockopen($h,25,$e,$es,$timeout);
        if(!$fp) continue;
        stream_set_timeout($fp,$timeout);
        $res=fgets($fp,512);
        fputs($fp,"HELO local.test\r\n"); $r=fgets($fp,512);
        fputs($fp,"MAIL FROM:<>\r\n"); $r=fgets($fp,512);
        fputs($fp,"RCPT TO:<".$email.">\r\n"); $r=fgets($fp,512);
        fclose($fp);
        if(preg_match('/^2[0-9][0-9]/',$r)) return true;
    }
    return false;
}

$ajax = (isset($_POST['ajax']) && $_POST['ajax']);
// Force JSON header for AJAX calls
if ($ajax) { header('Content-Type: application/json; charset=utf-8'); }

// VERIFY path: set isVerify=1 and send email; return JSON if ajax
if (isset($_POST['verify'])) {
    $residentId = intval($_POST['id']);
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM resident_info WHERE resident_id = ?");
    $stmt->bind_param("i", $residentId);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    $resident = $result->fetch_assoc();
    $stmt->close();

    if ($resident) {
        // Prepare email content
        $toName = trim($resident['first_name'].' '.$resident['last_name']);
        $subject = 'Your Barangay Account Has Been Verified';
        $body = " <html>
                        <body style='font-family: Arial, sans-serif; color: #333;'>
                            <p>Dear <strong>{$toName}</strong>,</p>
                            <p>We are pleased to inform you that your account has been successfully verified.</p>
                            <p>You can now log in and access the Barangay Panducot Adjudication Management Information System (BPAMIS).</p>
                            <p style='margin-top: 20px;'>Regards,<br>
                            <strong>Barangay Secretary</strong><br>
                            Barangay Panducot</p>
                            <hr style='margin-top: 25px;'>
                            <p style='font-size: 12px; color: #777;'>This is an automated message from the <strong>Barangay Panducot Adjudication Management Information System (BPAMIS)</strong>. Please do not reply directly to this email.</p>
                        </body>
                  </html>";

        // Skip DNS/SMTP probes — they were unreliable. Attempt to send verification email but
        // do NOT block verification if sending fails. The account will be verified regardless;
        // sending failure is logged for debugging.
        $mailRes = send_email($resident['email'], $toName, $subject, $body);
        if (!$mailRes['success']) {
            error_log("Verification email send failed for {$resident['email']}: " . ($mailRes['error'] ?? 'unknown'));
            $emailSendOk = false;
        } else {
            $emailSendOk = true;
        }

        // Email sent successfully; mark account verified and active.
        if ($upd = $conn->prepare("UPDATE resident_info SET isVerify = 1, isActive = 1 WHERE resident_id = ?")) {
            $upd->bind_param("i", $residentId);
            $upd->execute();
            $upd->close();
        }

        if ($ajax) {
            if ($emailSendOk) {
                echo json_encode(['success' => true, 'message' => 'Verified and email sent.']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Verified but failed to send email.']);
            }
            exit;
        } else {
            if ($emailSendOk) {
                $_SESSION['message'] = 'Account verified and email sent.';
            } else {
                $_SESSION['message'] = 'Account verified but failed to send verification email.';
            }
            header("Location: ../SecMenu/notifications-secretary.php");
            exit;
        }
    } else {
        if ($ajax) { echo json_encode(['success'=>false,'message'=>'Resident not found']); exit; }
        $_SESSION['message'] = 'Resident not found';
        header("Location: ../SecMenu/notifications-secretary.php");
        exit;
    }
}

// notify_invalid: send email only
if (isset($_POST['notify_invalid'])) {
    $residentId = intval($_POST['id']);
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM resident_info WHERE resident_id = ?");
    $stmt->bind_param("i", $residentId);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    $resident = $res->fetch_assoc();
    $stmt->close();

    if (!$resident) {
        if ($ajax) { echo json_encode(['success'=>false,'message'=>'Resident not found']); exit; }
        $_SESSION['message'] = 'Resident not found';
        header("Location: ../SecMenu/notifications-secretary.php");
        exit;
    }

    $toName = $resident['first_name'] . ' ' . $resident['last_name'];
    $subject = 'Verification Failed - Invalid ID Information';
   $body = "
        <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <p>Dear <strong>{$toName}</strong>,</p>
                <p>We attempted to verify your account, but the provided valid ID did not match the <strong>name and/or address</strong> you supplied during registration.</p>
                <p>Please review your registration details and re-submit a clear valid ID. You may also visit the barangay office for assistance.</p>
                <p style='margin-top: 20px;'>Regards,<br>
                <strong>Barangay Secretary</strong><br>
                Barangay Panducot</p>
                <hr style='margin-top: 25px;'>
                <p style='font-size: 12px; color: #777;'>This is an automated message from the <strong>Barangay Panducot Adjudication Management Information System (BPAMIS)</strong>. Please do not reply directly to this email.</p>
            </body>
        </html>";

    $mailRes = send_email($resident['email'], $toName, $subject, $body);
    if($ajax){
        echo json_encode(['success' => (bool)$mailRes['success'], 'message' => $mailRes['success'] ? 'Email sent' : ('Failed to send: '.$mailRes['error'])]);
        exit;
    } else {
        $_SESSION['message'] = $mailRes['success'] ? 'Invalid ID email sent.' : 'Failed to send invalid email.';
        header("Location: ../SecMenu/notifications-secretary.php");
        exit;
    }
}

// remove: keep as-is
if (isset($_POST['remove'])) {
    $residentId = intval($_POST['id']);
    $delete = $conn->prepare("DELETE FROM resident_info WHERE resident_id = ?");
    $delete->bind_param("i", $residentId);
    $delete->execute();
    if ($ajax) {
        echo json_encode(['success' => true, 'message' => 'Account removed.']);
        exit;
    } else {
        $_SESSION['message'] = 'Account removed.';
        header("Location: ../SecMenu/notifications-secretary.php?user=remove");
        exit;
    }
}
?>
