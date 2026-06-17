<?php
include '../server/server.php';

// Always respond JSON
header('Content-Type: application/json');

// server.php starts the session with the proper session_name based on path/referrer.
// Do NOT call session_start() here before including server.php otherwise session_name()
// in server.php will not take effect and AJAX requests may start a different session.

if (!isset($_SESSION['official_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Accept either a numeric case_id or a human-friendly case_original_id
$case_id = 0;
$case_id_raw = $_GET['case_id'] ?? '';
$status = $_GET['status'] ?? '';

if ($case_id_raw !== '') {
    // If the caller supplied the numeric id (or dataset.caseId), use it
    if (ctype_digit((string)$case_id_raw)) {
        $case_id = (int)$case_id_raw;
    } else {
        // attempt lookup by case_original_id (fallback)
        $lookup = $conn->prepare("SELECT Case_ID FROM case_info WHERE case_original_id = ? LIMIT 1");
        if ($lookup) {
            $lookup->bind_param('s', $case_id_raw);
            $lookup->execute();
            $lr = bpamis_stmt_get_result($lookup);
            if ($lr && $lr->num_rows > 0) {
                $case_id = (int)$lr->fetch_assoc()['Case_ID'];
            }
            $lookup->close();
        }
    }
}

// Map multiple friendly status names to the correct DB table.
$table_map = [
    'Mediation' => 'mediation_info',
    'Resolution' => 'resolution',
    // 'Conciliation' is the new UI name that maps to the conciliation table (new schema)
    'Conciliation' => 'conciliation',
    // Arbitration uses the arbitration table
    'Arbitration' => 'arbitration',
    'Settlement' => 'settlement'
];

if (!$case_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid case_id']);
    exit();
}

if (!isset($table_map[$status])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

$table = $table_map[$status];

// Fetch mediator_name from the corresponding table for the given case_id
$stmt = $conn->prepare("SELECT mediator_name FROM $table WHERE case_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Server prepare failed']);
    exit();
}
$stmt->bind_param("i", $case_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$mediators = [];

if ($row = $result->fetch_assoc()) {
    if (!empty($row['mediator_name'])) {
        // Assuming mediator_name is stored as a comma separated string
        $parts = array_map('trim', explode(',', $row['mediator_name']));
        // remove empty entries (caused by extra commas or trailing commas)
        $parts = array_values(array_filter($parts, function($v){ return $v !== null && $v !== '' && $v !== false; }));
        $mediators = $parts;
    }
}

$stmt->close();

echo json_encode(['mediators' => $mediators]);
