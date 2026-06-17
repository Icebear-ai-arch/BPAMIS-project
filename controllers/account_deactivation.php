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

// Deactivate an account (generalized for resident/official/external)
if (isset($_POST['deactivate'])) {
    $ajax = isset($_POST['ajax']) && $_POST['ajax'];
    $id = intval($_POST['id']);
    $type = isset($_POST['type']) ? trim($_POST['type']) : 'resident';
    $reason = trim($_POST['reason'] ?? '');

    // fetch user by type
    if ($type === 'resident') {
        $stmt = $conn->prepare("SELECT resident_id AS id, first_name, last_name, email FROM resident_info WHERE resident_id = ?");
        $stmt->bind_param('i', $id);
    } elseif ($type === 'official') {
        $stmt = $conn->prepare("SELECT Official_ID AS id, Name AS first_name, '' AS last_name, email FROM barangay_officials WHERE Official_ID = ?");
        $stmt->bind_param('i', $id);
    } else { // external
        $stmt = $conn->prepare("SELECT External_Complaint_ID AS id, First_name AS first_name, Middle_name AS last_name, email FROM external_complainant WHERE External_Complaint_ID = ?");
        $stmt->bind_param('i', $id);
    }
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        if ($ajax) { echo json_encode(['success'=>false,'message'=>'User not found']); exit; }
        $_SESSION['message'] = 'User not found'; header('Location: ../SecMenu/home-secretary.php'); exit;
    }

    $toEmail = $user['email'];
    $toName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

    // Quick DNS check
    if(!verify_email_dns_simple_local($toEmail)){
        if ($ajax) { echo json_encode(['success'=>false,'message'=>'Recipient appears invalid']); exit; }
        $_SESSION['message'] = 'Recipient appears invalid; deactivation email not sent.'; header('Location: ../SecMenu/home-secretary.php'); exit;
    }

    // send deactivation email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
        $mail->Username='vincentaaronvicente795@gmail.com'; $mail->Password='vwfq cqez mmdf hssm'; $mail->SMTPSecure='tls'; $mail->Port=587;
        $mail->setFrom('vincentaaronvicente795@gmail.com','Barangay Secretary'); $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Has Been Deactivated';
        $mail->Body = "<p>Dear <strong>".htmlspecialchars($toName)."</strong>,</p><p>Your account has been deactivated for the following reason:</p><p><em>".htmlspecialchars($reason)."</em></p><p>If you believe this is an error, please contact the barangay office.</p><br><p>Regards,<br>Barangay Admin</p>";
        $mail->send();

        // update DB: set isActive = 0 only after email is sent
        if ($type === 'resident') { $upd = $conn->prepare("UPDATE resident_info SET isActive = 0 WHERE resident_id = ?"); $upd->bind_param('i', $id); $upd->execute(); }
        elseif ($type === 'official') { $upd = $conn->prepare("UPDATE barangay_officials SET isActive = 0 WHERE Official_ID = ?"); $upd->bind_param('i', $id); $upd->execute(); }
        else { $upd = $conn->prepare("UPDATE external_complainant SET isActive = 0 WHERE External_Complaint_ID = ?"); $upd->bind_param('i', $id); $upd->execute(); }

        if ($ajax) { echo json_encode(['success'=>true,'message'=>'Deactivation email sent and account disabled']); exit; }
        $_SESSION['message'] = 'Deactivation email sent and account disabled.'; header('Location: ../SecMenu/home-secretary.php'); exit;
    } catch (Exception $e) {
        if ($ajax) { echo json_encode(['success'=>false,'message'=>'Failed to send email','error'=>$e->getMessage()]); exit; }
        $_SESSION['message'] = 'Failed to send deactivation email. Account not changed.'; header('Location: ../SecMenu/home-secretary.php'); exit;
    }
}

?>
