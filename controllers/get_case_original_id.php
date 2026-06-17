<?php
header('Content-Type: application/json; charset=utf-8');
// Simple endpoint: ?case_id=<id> -> returns { success: true, original_id: '...' }
include __DIR__ . '/../server/server.php';

$caseId = isset($_GET['case_id']) ? trim($_GET['case_id']) : '';
if ($caseId === '') {
    echo json_encode(['success' => false, 'error' => 'missing_case_id']);
    exit;
}

// If case_id is numeric, look up by Case_ID, otherwise attempt to return it as-is
if (!ctype_digit($caseId)) {
    echo json_encode(['success' => false, 'error' => 'invalid_case_id']);
    exit;
}

$orig = null;
try {
    $stmt = $conn->prepare("SELECT case_original_id FROM case_info WHERE Case_ID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $caseId);
        $stmt->execute();
        $res = bpamis_stmt_get_result($stmt);
        if ($res && $row = $res->fetch_assoc()) {
            $orig = $row['case_original_id'];
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // ignore
}

if ($orig !== null && $orig !== '') {
    echo json_encode(['success' => true, 'original_id' => (string)$orig]);
} else {
    echo json_encode(['success' => false, 'error' => 'not_found']);
}

?>