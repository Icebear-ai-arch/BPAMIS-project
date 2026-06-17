<?php
include '../controllers/session_control.php';
include '../server/server.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$residentId = (int)$_SESSION['user_id'];
$caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;

if ($caseId <= 0) {
    header('Location: view_cases.php');
    exit;
}

// 🟢 Fetch case + complainant info
$stmt = $conn->prepare("
    SELECT ci.Case_Status, co.Complaint_ID, co.Resident_ID AS complainant_id, 
           co.complainant_agree, co.respondent_id AS main_respondent_id, co.respondent_agree AS main_respondent_agree
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
    header('Location: view_cases.php?error=notfound');
    exit;
}
$case = $res->fetch_assoc();
$stmt->close();

// 🟢 Only proceed if status is "Resolution"
if (strtolower($case['Case_Status']) !== 'resolution') {
    header('Location: view_cases.php?error=invalid');
    exit;
}

// 🟢 Update agreement depending on user type
if ($residentId == $case['complainant_id']) {
    // Current user is complainant
    $stmt = $conn->prepare("UPDATE complaint_info SET complainant_agree = 1 WHERE Complaint_ID = ?");
    $stmt->bind_param("i", $case['Complaint_ID']);
    $stmt->execute();
    $stmt->close();
} elseif ($residentId == $case['main_respondent_id']) {
    // Current user is the main respondent
    $stmt = $conn->prepare("UPDATE complaint_info SET respondent_agree = 1 WHERE Complaint_ID = ?");
    $stmt->bind_param("i", $case['Complaint_ID']);
    $stmt->execute();
    $stmt->close();
} else {
    // Current user is a listed respondent
    $stmt = $conn->prepare("UPDATE complaint_respondents SET respondent_agree = 1 WHERE Complaint_ID = ? AND Respondent_ID = ?");
    $stmt->bind_param("ii", $case['Complaint_ID'], $residentId);
    $stmt->execute();
    $stmt->close();
}

// 🟢 Check if all respondents and complainant have agreed
$stmt = $conn->prepare("
    SELECT 
        co.complainant_agree,
        co.respondent_agree AS main_respondent_agree,
        GROUP_CONCAT(cr.respondent_agree) AS other_respondents
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

// 🟢 Analyze agreement status
$complainantAgreed = (int)$agreeData['complainant_agree'] === 1;
$mainRespondentAgreed = (int)$agreeData['main_respondent_agree'] === 1;

$otherRespondents = $agreeData['other_respondents'] ? explode(',', $agreeData['other_respondents']) : [];
$allOtherRespondentsAgreed = empty($otherRespondents) || !in_array('0', $otherRespondents, true);

// ✅ Everyone agreed if all conditions are true
$everyoneAgreed = $complainantAgreed && $mainRespondentAgreed && $allOtherRespondentsAgreed;

if ($everyoneAgreed) {
    // 🟢 Move case to Arbitration
    $stmt = $conn->prepare("UPDATE case_info SET Case_Status = 'Arbitration' WHERE Case_ID = ?");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $stmt->close();
}

header("Location: case_details.php?case_id=$caseId&success=agreed");
exit;
