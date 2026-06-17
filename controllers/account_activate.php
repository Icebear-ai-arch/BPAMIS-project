<?php
session_start();
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';
require '../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include '../server/server.php';

function verify_email_dns_simple_local($email){
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) return false;
    [$u,$d] = explode('@',$email,2);
    $hasMx = function_exists('getmxrr') ? @getmxrr($d,$mxhosts) && count($mxhosts) > 0 : false;
    $hasA = checkdnsrr($d,'A') || checkdnsrr($d,'AAAA');
    return ($hasMx || $hasA);
}

// Activation handler: activate an account by email (resident/official/external)
if (isset($_POST['submitActivation']) || !empty($_POST['activation_email'])) {
    $email = trim($_POST['activation_email'] ?? '');
    if (!$email) {
        $_SESSION['message'] = 'No email provided for activation.';
        header('Location: ../SecMenu/home-secretary.php');
        exit;
    }

    // locate user across tables
    $user = null; $userType = null;
    $stmt = $conn->prepare("SELECT resident_id AS id, CONCAT(First_name,' ',Middle_name,' ',Last_name) AS name, email, isActive FROM resident_info WHERE email = ? LIMIT 1");
    $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $user = $res->fetch_assoc(); $userType='resident'; } $stmt->close();

    if(!$user){ $stmt = $conn->prepare("SELECT Official_ID AS id, Name AS name, email, COALESCE(isActive,0) AS isActive FROM barangay_officials WHERE email = ? LIMIT 1"); $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $user = $res->fetch_assoc(); $userType='official'; } $stmt->close(); }

    if(!$user){ $stmt = $conn->prepare("SELECT External_Complaint_ID AS id, CONCAT(First_name,' ',Middle_name,' ',Last_name) AS name, email, COALESCE(isActive,0) AS isActive FROM external_complainant WHERE email = ? LIMIT 1"); $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $user = $res->fetch_assoc(); $userType='external'; } $stmt->close(); }

    if(!$user){ $_SESSION['message'] = 'No account found with that email address.'; header('Location: ../SecMenu/home-secretary.php'); exit; }

    // logging
    $logDir = __DIR__ . '/../logs'; if(!is_dir($logDir)) @mkdir($logDir,0755,true); $logFile = $logDir . '/activation_debug.log';
    @file_put_contents($logFile, sprintf("[%s] account_activate: email=%s type=%s id=%s isActive=%s\n", date('Y-m-d H:i:s'), $email, $userType, $user['id'] ?? 'n/a', var_export($user['isActive'] ?? null,true)), FILE_APPEND);

    if(isset($user['isActive']) && intval($user['isActive']) === 1){
        // If this matched record is active, attempt to locate an inactive record for the same email in any table
        @file_put_contents($logFile, sprintf("[%s] account_activate: matched active record first for %s, searching for inactive alternative\n", date('Y-m-d H:i:s'), $email), FILE_APPEND);
        // look for inactive resident
        $alt = null; $altType = null;
        $stmt = $conn->prepare("SELECT resident_id AS id, CONCAT(First_name,' ',Middle_name,' ',Last_name) AS name, email, isActive AS isActive FROM resident_info WHERE email = ? AND isActive = 0 LIMIT 1");
        $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $alt = $res->fetch_assoc(); $altType='resident'; } $stmt->close();
        if(!$alt){ $stmt = $conn->prepare("SELECT Official_ID AS id, Name AS name, email, COALESCE(isActive,0) AS isActive FROM barangay_officials WHERE email = ? AND COALESCE(isActive,0) = 0 LIMIT 1"); $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $alt = $res->fetch_assoc(); $altType='official'; } $stmt->close(); }
        if(!$alt){ $stmt = $conn->prepare("SELECT External_Complaint_ID AS id, CONCAT(First_name,' ',Middle_name,' ',Last_name) AS name, email, COALESCE(isActive,0) AS isActive FROM external_complainant WHERE email = ? AND COALESCE(isActive,0) = 0 LIMIT 1"); $stmt->bind_param('s',$email); $stmt->execute(); $res = bpamis_stmt_get_result($stmt); if($res && $res->num_rows>0){ $alt = $res->fetch_assoc(); $altType='external'; } $stmt->close(); }
        if($alt){ @file_put_contents($logFile, sprintf("[%s] account_activate: found inactive alternative in %s for %s (id=%s)\n", date('Y-m-d H:i:s'), $altType, $email, $alt['id'] ?? 'n/a'), FILE_APPEND); $user = $alt; $userType = $altType; }
        else { @file_put_contents($logFile, sprintf("[%s] account_activate: no inactive alternative found for %s\n", date('Y-m-d H:i:s'), $email), FILE_APPEND); $_SESSION['message'] = 'This account is already active.'; header('Location: ../SecMenu/home-secretary.php'); exit; }
    }

    // require DNS check only
    if(!verify_email_dns_simple_local($email)){
        @file_put_contents($logFile, sprintf("[%s] account_activate: dns failed %s\n", date('Y-m-d H:i:s'), $email), FILE_APPEND);
        $_SESSION['message'] = 'Email appears undeliverable (DNS/MX check failed). Account was NOT activated.'; header('Location: ../SecMenu/home-secretary.php'); exit;
    }

    $toName = trim($user['name'] ?? '') ?: $email;
    $subject = 'Your Barangay Account Has Been Activated';
    $body = "<html><body style='font-family:Arial,sans-serif;color:#333;'><p>Dear <strong>".htmlspecialchars($toName)."</strong>,</p><p>Your account has been <strong>activated</strong>. You may now access the BPAMIS system using your registered email.</p><p>Regards,<br><strong>Barangay Secretary</strong></p><hr><p style='font-size:12px;color:#777;'>This is an automated message from BPAMIS. Do not reply.</p></body></html>";

    $mail = new PHPMailer(true);
    try{
        $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
        $mail->Username='vincentaaronvicente795@gmail.com'; $mail->Password='vwfq cqez mmdf hssm'; $mail->SMTPSecure='tls'; $mail->Port=587;
        $mail->setFrom('vincentaaronvicente795@gmail.com','Barangay Secretary'); $mail->addAddress($email,$toName);
        $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$body; $mail->send();

        if($userType==='resident'){ $upd=$conn->prepare("UPDATE resident_info SET isActive = 1 WHERE resident_id = ?"); $upd->bind_param('i',$user['id']); $upd->execute(); }
        elseif($userType==='official'){ $upd=$conn->prepare("UPDATE barangay_officials SET isActive = 1 WHERE Official_ID = ?"); $upd->bind_param('i',$user['id']); $upd->execute(); }
        else{ $upd=$conn->prepare("UPDATE external_complainant SET isActive = 1 WHERE External_Complaint_ID = ?"); $upd->bind_param('i',$user['id']); $upd->execute(); }

        @file_put_contents($logFile, sprintf("[%s] account_activate: sent and updated %s\n", date('Y-m-d H:i:s'), $email), FILE_APPEND);
        $_SESSION['message'] = 'Activation email sent and account activated.'; header('Location: ../SecMenu/home-secretary.php'); exit;
    }catch(Exception $e){ $err = $e->getMessage(); $phErr = isset($mail) && property_exists($mail,'ErrorInfo') ? $mail->ErrorInfo : ''; @file_put_contents($logFile, sprintf("[%s] account_activate: mail failed %s: %s | %s\n", date('Y-m-d H:i:s'), $email, $err, $phErr), FILE_APPEND); $_SESSION['message'] = 'Failed to send activation email. Account was NOT activated. Check logs for details.'; header('Location: ../SecMenu/home-secretary.php'); exit; }
}

?>
