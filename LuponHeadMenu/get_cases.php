<?php
include '../server/server.php';

// Always respond JSON
header('Content-Type: application/json');

// server.php sets the session_name() based on path/referrer and starts the session.
// Do not call session_start() before including server.php or the session namespace will not be applied.
if (!isset($_SESSION['official_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$status = $_GET['status'] ?? '';
if (!$status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing status']);
    exit();
}

// Map UI statuses to DB values
$table_status_map = [
    'Conciliation' => [ 'Resolution', 'Conciliation' ],
    'Arbitration' => [ 'Arbitration' ]
];

if (!isset($table_status_map[$status])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($table_status_map[$status]), '?'));
$types = str_repeat('s', count($table_status_map[$status]));
$params = $table_status_map[$status];

// Select complainant and main respondent names for display label
$sql = "SELECT ci.Case_ID,
    ci.case_original_id,
        ci.Date_Opened AS case_date,
        COALESCE(CONCAT(r.First_Name, ' ', r.Last_Name), CONCAT(e.First_Name, ' ', e.Last_Name), 'Unknown') AS complainant_name,
        COALESCE(CONCAT(r2.First_Name, ' ', r2.Last_Name), 'N/A') AS main_respondent_name
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    LEFT JOIN RESIDENT_INFO r ON co.Resident_ID = r.Resident_ID
    LEFT JOIN EXTERNAL_COMPLAINANT e ON co.External_Complainant_ID = e.External_Complaint_ID
    LEFT JOIN RESIDENT_INFO r2 ON co.Respondent_ID = r2.Resident_ID
    WHERE ci.Case_Status IN ($placeholders)
    ORDER BY ci.Case_ID ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query prepare failed']);
    exit();
}

// bind params dynamically
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
$cases = [];
while ($row = $res->fetch_assoc()) {
    // Build label: CASE {ID} - {MM} - {YYYY} / COMPLAINANT VS RESPONDENT_MAIN
    $month = '';
    $year = '';
    if (!empty($row['case_date'])) {
        $ts = strtotime($row['case_date']);
        if ($ts !== false) {
            // numeric month without leading zero
            $month = date('n', $ts);
            $year = date('Y', $ts);
        }
    }

    $complainant = $row['complainant_name'] ?: 'Unknown';
    $respondent = $row['main_respondent_name'] ?: 'N/A';
    // uppercase the names to match requested style
    $complainantU = mb_strtoupper($complainant);
    $respondentU = mb_strtoupper($respondent);

    $datePart = $month !== '' && $year !== '' ? " - {$month} - {$year}" : '';
    // Prefer human-friendly case_original_id when available. If missing, fall back to a CASE-{ID} token
    $caseOriginal = isset($row['case_original_id']) && $row['case_original_id'] !== null && $row['case_original_id'] !== '' ? $row['case_original_id'] : 'CASE ' . $row['Case_ID'];
    $label = $caseOriginal .  ' / ' . $complainantU . ' VS ' . $respondentU;
    // Always include case_original_id (never null) so the frontend can use it as the <option> value
    $cases[] = [
        'case_id' => (int)$row['Case_ID'],
        'case_original_id' => $caseOriginal,
        'label' => $label,
        'complainant' => $complainant,
        'respondent' => $respondent
    ];
}
$stmt->close();

echo json_encode(['cases' => $cases]);
