<?php
include 'db-connect.php';

if (!isset($_GET['Case_ID'])) {
    echo json_encode(['error' => 'No Case_ID provided']);
    exit;
}

$caseId = (int)$_GET['Case_ID'];

// Initialize
$complainantName = '';
$respondents = [];
$caseStatus = '';
$phaseLabel = '';

/* Fetch Case Status and Complainant (resident or external) */
$infoQuery = "
    SELECT 
        ci.Case_Status,
        COALESCE(
            NULLIF(CONCAT_WS(' ', r.First_Name, r.Middle_Name, r.Last_Name), ''),
            NULLIF(CONCAT_WS(' ', e.First_Name, e.Middle_Name, e.Last_Name), ''),
            ''
        ) AS Complainant_Name
    FROM case_info ci
    JOIN complaint_info ci2 ON ci.Complaint_ID = ci2.Complaint_ID
    LEFT JOIN resident_info r ON ci2.Resident_ID = r.Resident_ID
    LEFT JOIN external_complainant e ON ci2.External_Complainant_ID = e.External_Complaint_ID
    WHERE ci.Case_ID = ?
    LIMIT 1
";

if ($stmt = $conn->prepare($infoQuery)) {
    $stmt->bind_param('i', $caseId);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    if ($res && $row = $res->fetch_assoc()) {
        $caseStatus = trim((string)($row['Case_Status'] ?? ''));
        $complainantName = trim((string)($row['Complainant_Name'] ?? ''));
    }
    $stmt->close();
}

// Map to phase label expected by UI (Resolution => Conciliation)
switch (strtolower($caseStatus)) {
    case 'resolution':
        $phaseLabel = 'Conciliation';
        break;
    case 'mediation':
        $phaseLabel = 'Mediation';
        break;
    case 'arbitration':
        $phaseLabel = 'Arbitration';
        break;
    case 'settlement':
        $phaseLabel = 'Settlement';
        break;
    default:
        $phaseLabel = $caseStatus !== '' ? $caseStatus : 'Open';
        break;
}

/*  GET RESPONDENTS (Main + Sub Respondents)*/
$respondentsQuery = "
    SELECT 
        CONCAT(r_main.First_Name, ' ', r_main.Middle_Name, ' ', r_main.Last_Name) AS Main_Respondent,
        GROUP_CONCAT(CONCAT(r_sub.First_Name, ' ', r_sub.Middle_Name, ' ', r_sub.Last_Name) SEPARATOR ', ') AS Sub_Respondents
    FROM case_info ci
    JOIN complaint_info ci2 ON ci.Complaint_ID = ci2.Complaint_ID
    LEFT JOIN resident_info r_main 
        ON ci2.Respondent_ID = r_main.Resident_ID
    LEFT JOIN complaint_respondents cr 
        ON ci2.Complaint_ID = cr.Complaint_ID
    LEFT JOIN resident_info r_sub 
        ON cr.Respondent_ID = r_sub.Resident_ID
    WHERE ci.Case_ID = ?
    GROUP BY ci.Case_ID
";

if ($respondentsStmt = $conn->prepare($respondentsQuery)) {
    $respondentsStmt->bind_param("i", $caseId);
    $respondentsStmt->execute();
    $respondentsResult = bpamis_stmt_get_result($respondentsStmt);
    if ($respondentsRow = $respondentsResult->fetch_assoc()) {
        if (!empty($respondentsRow['Main_Respondent'])) {
            $respondents[] = trim($respondentsRow['Main_Respondent']);
        }
        if (!empty($respondentsRow['Sub_Respondents'])) {
            $subList = explode(',', $respondentsRow['Sub_Respondents']);
            foreach ($subList as $sub) {
                $respondents[] = trim($sub);
            }
        }
    }
    $respondentsStmt->close();
}

/* RETURN JSON RESPONSE */
echo json_encode([
    'complainant' => $complainantName,
    'respondents' => $respondents,
    'case_status' => $caseStatus,
    'phase' => $phaseLabel
]);

?>
