<?php
session_start();
header('Content-Type: application/json');
include '../server/server.php';

$response = ['success'=>false];

$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
if ($caseId <= 0) {
    echo json_encode($response); exit;
}

// Identify context: if Lupon is logged in, restrict to their assigned cases (Captains/Secretaries are not restricted)
$officialId = isset($_SESSION['official_id']) ? (int)$_SESSION['official_id'] : 0;
$officialName = '';
$officialRole = '';
$isLupon = false;
if ($officialId > 0) {
    if ($st = $conn->prepare('SELECT Name, Position FROM barangay_officials WHERE Official_ID = ?')) {
        $st->bind_param('i', $officialId);
        $st->execute();
        $r = bpamis_stmt_get_result($st);
        if ($r && $row = $r->fetch_assoc()) {
            $officialName = trim((string)($row['Name'] ?? ''));
            $officialRole = strtolower(trim((string)($row['Position'] ?? '')));
            $isLupon = ($officialRole === 'lupon tagapamayapa');
        }
        $st->close();
    }
}

// Schema guard for complaint text: prefer full details when available
$descField = 'co.Complaint_Title';
if ($chk = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'Complaint_Details'")) {
    if ($chk->num_rows > 0) { $descField = 'co.Complaint_Details'; }
    $chk->close();
} else {
    // silently ignore
}
if ($descField === 'co.Complaint_Title') {
    if ($chk2 = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'Complaint_Description'")) {
        if ($chk2->num_rows > 0) { $descField = 'co.Complaint_Description'; }
        $chk2->close();
    }
}

$sql = "
    SELECT 
        ci.Case_ID,
        ci.case_original_id,
        ci.Case_Status,
        COALESCE(NULLIF(TRIM(co.case_type), ''), 'N/A') AS case_type,
        $descField AS complaint,
        (
            SELECT sl.hearingdatetime FROM schedule_list sl
            WHERE sl.case_id = ci.Case_ID
            ORDER BY sl.hearingdatetime ASC
            LIMIT 1
        ) AS next_hearing
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    LEFT JOIN mediation_info mi ON mi.case_id = ci.Case_ID
    LEFT JOIN resolution r ON r.case_id = ci.Case_ID
    LEFT JOIN settlement s ON s.case_id = ci.Case_ID
    WHERE ci.Case_ID = ?
";

// If lupon context, ensure the case is assigned to them
if ($isLupon && $officialName !== '') {
    $sql .= " AND (mi.mediator_name LIKE CONCAT('%', ?, '%') OR r.mediator_name LIKE CONCAT('%', ?, '%') OR s.mediator_name LIKE CONCAT('%', ?, '%') OR ci.lupon_assign LIKE CONCAT('%', ?, '%'))";
}

if ($st = $conn->prepare($sql)) {
    if ($isLupon && $officialName !== '') {
        $st->bind_param('issss', $caseId, $officialName, $officialName, $officialName, $officialName);
    } else {
        $st->bind_param('i', $caseId);
    }
    $st->execute();
    $res = bpamis_stmt_get_result($st);
    if ($res && $row = $res->fetch_assoc()) {
        $response['success'] = true;
        $response['case'] = [
            'case_id' => (int)$row['Case_ID'],
            'case_original_id' => trim((string)($row['case_original_id'] ?? '')),
            'case_status' => $row['Case_Status'] ?? '',
            'case_type' => $row['case_type'] ?? 'N/A',
            'complaint' => $row['complaint'] ?? '',
            'next_hearing' => $row['next_hearing'] ? date('M j, Y g:i A', strtotime($row['next_hearing'])) : null,
        ];
    }
    $st->close();
}

echo json_encode($response);
