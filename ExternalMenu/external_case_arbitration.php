<?php
include '../controllers/session_control.php';
include '../server/server.php';

// ✅ Allow both external and normal user sessions
if (!isset($_SESSION['external_id']) && !isset($_SESSION['user_id'])) {
    header('Location: ../bpamis_website/login.php');
    exit;
}

$externalId = (int)($_SESSION['external_id'] ?? $_SESSION['user_id']);
$caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

if ($caseId <= 0) {
    header('Location: case_details.php?error=invalid');
    exit;
}

// ✅ Fetch case info
$stmt = $conn->prepare("
    SELECT 
        ci.Case_Status, 
        co.Complaint_ID, 
        co.external_complainant_id, 
        co.external_complainant_agree
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    WHERE ci.Case_ID = ?
    LIMIT 1
");
$stmt->bind_param("i", $caseId);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
if ($res->num_rows === 0) {
    $stmt->close();
    header('Location: case_details.php?error=notfound');
    exit;
}
$case = $res->fetch_assoc();
$stmt->close();

// ✅ Only proceed if status is "Resolution" or "Conciliation"
$caseStatus = strtolower(trim($case['Case_Status']));
if (!in_array($caseStatus, ['resolution', 'conciliation'], true)) {
    header("Location: case_details.php?case_id=$caseId&error=invalid_status");
    exit;
}

// ✅ Update agreement depending on the role
if ($externalId == $case['external_complainant_id']) {
    // External complainant agrees
    $stmt = $conn->prepare("UPDATE complaint_info SET external_complainant_agree = 1 WHERE Complaint_ID = ?");
    $stmt->bind_param("i", $case['Complaint_ID']);
    $stmt->execute();
    $stmt->close();
} else {
    // External respondent agrees
    $stmt = $conn->prepare("UPDATE complaint_respondents SET respondent_agree = 1 WHERE Complaint_ID = ? AND Respondent_ID = ?");
    $stmt->bind_param("ii", $case['Complaint_ID'], $externalId);
    $stmt->execute();
    $stmt->close();
}

// ✅ Check if everyone agreed
$stmt = $conn->prepare("
    SELECT 
        co.external_complainant_agree, 
        GROUP_CONCAT(cr.respondent_agree) AS respondent_agreements
    FROM complaint_info co
    LEFT JOIN complaint_respondents cr ON co.Complaint_ID = cr.Complaint_ID
    WHERE co.Complaint_ID = ?
    GROUP BY co.Complaint_ID
");
$stmt->bind_param("i", $case['Complaint_ID']);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
$agreeData = $res->fetch_assoc();
$stmt->close();

$respondentAgreements = $agreeData['respondent_agreements'] ? explode(',', $agreeData['respondent_agreements']) : [];
$allRespondentsAgreed = !in_array('0', $respondentAgreements);
$complainantAgreed = $agreeData['external_complainant_agree'] == 1;

// ✅ If everyone agreed, move case to Arbitration
if ($complainantAgreed && $allRespondentsAgreed) {
    $stmt = $conn->prepare("UPDATE case_info SET Case_Status = 'Arbitration' WHERE Case_ID = ?");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $stmt->close();
}

header("Location: case_details.php?case_id=$caseId&success=agreed");
exit;
?>
