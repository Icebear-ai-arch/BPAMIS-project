<?php
require_once(__DIR__ . '/../server/server.php');

// For API endpoints included via fetch/XHR, suppress session_control's inline scripts
if (!defined('SC_SUPPRESS_SCRIPT')) define('SC_SUPPRESS_SCRIPT', true);

// Send JSON header early to avoid content-type mismatch if session_control emits output
header('Content-Type: application/json; charset=utf-8');
include_once __DIR__ . '/session_control.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$official_id = $_SESSION['official_id'] ?? null;
if (!$official_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
$reason = trim((string)($_POST['reason'] ?? ''));

if ($case_id <= 0 || $reason === '') {
    echo json_encode(['success' => false, 'error' => 'Missing case id or reason']);
    exit;
}

// Ensure lupon_declines table exists (safe to run repeatedly)
$createSql = "CREATE TABLE IF NOT EXISTS lupon_declines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    lupon_id INT NOT NULL,
    reason TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reassigned TINYINT(1) NOT NULL DEFAULT 0,
    reassigned_at DATETIME DEFAULT NULL,
    reassigned_to_lupon_id INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Insert decline record
// Prevent duplicate open declines for same case by same lupon
$check = $conn->prepare("SELECT id FROM lupon_declines WHERE case_id = ? AND lupon_id = ? AND reassigned = 0 LIMIT 1");
if ($check) {
    $check->bind_param('ii', $case_id, $official_id);
    $check->execute();
    $cres = bpamis_stmt_get_result($check);
    if ($cres && $cres->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'You have already declined this case']);
        exit;
    }
    $check->close();
}

$ins = $conn->prepare("INSERT INTO lupon_declines (case_id, lupon_id, reason, created_at) VALUES (?, ?, ?, NOW())");
if (!$ins) {
    echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
    exit;
}
$ins->bind_param('iis', $case_id, $official_id, $reason);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'error' => 'DB execute failed: ' . $ins->error]);
    exit;
}
$ins->close();

// Notify Lupon Head(s)
$headId = null;
$posCandidates = ["Lupon Tagapamayapa Head", "Lupon Head", "LuponHead"];
foreach ($posCandidates as $pos) {
    $st = $conn->prepare("SELECT Official_ID FROM barangay_officials WHERE Position = ? ORDER BY Official_ID LIMIT 1");
    if ($st) {
        $st->bind_param('s', $pos);
        $st->execute();
        $r = bpamis_stmt_get_result($st);
        if ($r && $row = $r->fetch_assoc()) { $headId = (int)$row['Official_ID']; $st->close(); break; }
        $st->close();
    }
}

$title = "Lupon Declined: Case #" . $case_id;
$message = "A Lupon assigned to Case #{$case_id} has declined to attend. Reason: " . $reason;

if ($headId) {
    // Insert notification addressed to the Lupon Head. Fill both lupon_id and official_id
    // so role-specific readers can pick it up regardless of which column they check.
    $n = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, official_id, is_read) VALUES (?, ?, 'Case', NOW(), ?, ?, 0)");
    if ($n) {
        $n->bind_param('ssii', $title, $message, $headId, $headId);
        $n->execute();
        $n->close();
    }
}

// Broadcast update to other tabs so nav badges refresh
try { if (function_exists('apcu_store')) { /* no-op server side */ } } catch(Throwable $e) {}
// Also send a small JSON response
echo json_encode(['success' => true, 'message' => 'Decline recorded']);
exit;

?>
