<?php
session_start();
require_once __DIR__ . '/../server/server.php';

// Add PHPMailer (same as account_verification)
require __DIR__ . '/../phpmailer/PHPMailer.php';
require __DIR__ . '/../phpmailer/SMTP.php';
require __DIR__ . '/../phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Replace send_email with this debug-aware version
function send_email($toEmail, $toName, $subject, $bodyHtml){
    $mail = new PHPMailer(true);
    $smtpDebug = '';
    try {
        // collect debug output into $smtpDebug
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use (&$smtpDebug) {
            $smtpDebug .= "[".$level."] ".$str . "\n";
        };

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'vincentaaronvicente795@gmail.com';
        $mail->Password = 'vwfq cqez mmdf hssm'; // use app password if 2FA
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // local dev TLS loosen (remove in production)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom('vincentaaronvicente795@gmail.com', 'Barangay Secretary');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        return ['success'=>true, 'debug'=>$smtpDebug];
    } catch (Exception $e){
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return ['success'=>false, 'error'=>$err, 'debug'=>$smtpDebug];
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Invalid email']);
    exit;
}

// Look for user across resident_info, barangay_officials, external_complainant
$user = null;
$user_role = null;
$displayName = '';
$isAllowed = true; // if false return message below
$notAllowedMessage = '';

/* resident */
$stmt = $conn->prepare("SELECT Resident_ID AS id, First_Name, Last_Name, isVerify FROM resident_info WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
if ($res && $res->num_rows === 1) {
    $u = $res->fetch_assoc();
    $user = $u;
    $user_role = 'resident';
    $displayName = trim(($u['First_Name'] ?? '') . ' ' . ($u['Last_Name'] ?? ''));
    if ((int)($u['isVerify'] ?? 0) === 0) {
        $isAllowed = false;
        $notAllowedMessage = 'Your account is not verified. Please verify before requesting a password reset.';
    }
}
$stmt->close();

if (!$user) {
    /* officials */
    $stmt = $conn->prepare("SELECT official_id AS id, `Name`, Position, isActive FROM barangay_officials WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    if ($res && $res->num_rows === 1) {
        $u = $res->fetch_assoc();
        $user = $u;
        $user_role = 'official';
        $displayName = $u['Name'] ?? '';
        if ((int)($u['isActive'] ?? 0) === 0) {
            $isAllowed = false;
            $notAllowedMessage = 'Your official account is inactive. Contact administrator.';
        }
    }
    $stmt->close();
}

if (!$user) {
    /* external complainant */
    $stmt = $conn->prepare("SELECT external_complaint_id AS id, external_username, isActive FROM external_complainant WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    if ($res && $res->num_rows === 1) {
        $u = $res->fetch_assoc();
        $user = $u;
        $user_role = 'external';
        $displayName = $u['external_username'] ?? '';
        if ((int)($u['isActive'] ?? 0) === 0) {
            $isAllowed = false;
            $notAllowedMessage = 'Your account is inactive. Contact administrator.';
        }
    }
    $stmt->close();
}

if (!$user) {
    // explicit message per request
    echo json_encode(['success'=>false,'message'=>"No account found with this email."]);
    exit;
}

if (!$isAllowed) {
    echo json_encode(['success'=>false,'message'=>$notAllowedMessage]);
    exit;
}

// Rate limit: max 5 OTPs per hour
$oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
$rstmt = $conn->prepare("SELECT COUNT(*) as c FROM password_resets WHERE email = ? AND created_at >= ?");
$rstmt->bind_param('ss', $email, $oneHourAgo);
$rstmt->execute();
$rc = bpamis_stmt_get_result($rstmt)->fetch_assoc();
$rstmt->close();
if (($rc['c'] ?? 0) >= 5) {
    echo json_encode(['success'=>false,'message'=>'Too many requests. Try again later.']);
    exit;
}

// Generate 6-digit OTP
$otp = random_int(100000, 999999);
$otp_hash = password_hash((string)$otp, PASSWORD_DEFAULT);
$expires_at = date('Y-m-d H:i:s', time() + 15*60); // 15 minutes

$ins = $conn->prepare("INSERT INTO password_resets (email, otp_hash, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('sss', $email, $otp_hash, $expires_at);
$ok = $ins->execute();
$ins->close();

if (!$ok) {
    echo json_encode(['success'=>false,'message'=>'Unable to create reset token.']);
    exit;
}

// Send email (assumes send_email($to, $name, $subject, $body) exists)
$toName = $displayName ?: $email;
$subject = 'BPAMIS Password Reset OTP';
$body = "<p>Dear {$toName},</p>
<p>You requested a password reset. Use the 6-digit code below to reset your password. The code expires in 15 minutes.</p>
<h2 style='letter-spacing:4px'>{$otp}</h2>
<p>If you did not request this, please ignore this message or contact the barangay.</p>
<p>Regards,<br/>BPAMIS</p>";

$mailRes = send_email($email, $toName, $subject, $body);

// Return result
echo json_encode([
    'success'    => true,
    'message'    => 'If this email exists, an OTP has been sent.',
    'email_sent' => (bool)($mailRes['success'] ?? false),
    'mail_error' => $mailRes['error'] ?? null
]);
exit;