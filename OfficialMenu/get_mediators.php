<?php
session_start();
include '../server/server.php';

if (!isset($_SESSION['official_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$status = $_GET['status'] ?? '';

$table_map = [
    'Mediation' => 'mediation_info',
    'Resolution' => 'resolution',
    'Settlement' => 'settlement'
];

if (!$case_id || !isset($table_map[$status])) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

$table = $table_map[$status];

// Fetch mediator_name from the corresponding table for the given case_id
$stmt = $conn->prepare("SELECT mediator_name FROM $table WHERE case_id = ?");
$stmt->bind_param("i", $case_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$mediators = [];

if ($row = $result->fetch_assoc()) {
    if (!empty($row['mediator_name'])) {
        // Assuming mediator_name is stored as a comma separated string
        $mediators = array_map('trim', explode(',', $row['mediator_name']));
    }
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode(['mediators' => $mediators]);
