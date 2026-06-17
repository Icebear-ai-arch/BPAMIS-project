<?php
session_start();
include '../server/server.php';

// Determine notification id and external/resident identifier
if (!isset($_POST['notif_id'])) {
    header("Location: notifications.php");
    exit();
}

$notif_id = (int)$_POST['notif_id'];

// Prefer external session id if present, otherwise use resident user id
$externalId = isset($_SESSION['external_id']) ? (int)$_SESSION['external_id'] : null;
$residentId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($externalId === null && $residentId === null) {
    // Not authenticated for either resident or external
    header("Location: notifications.php");
    exit();
}

// Build a robust WHERE clause that matches the logged-in user's id against
// resident_id and any external_* columns that exist. This covers cases where
// external users use `$_SESSION['user_id']` (as seen in `notifications.php`).
$sessionId = $externalId ?? $residentId;

// Discover available columns
$columns = [];
if ($res = $conn->query("SHOW COLUMNS FROM notifications")) {
    while ($c = $res->fetch_assoc()) { $columns[] = $c['Field']; }
}

$candidateCols = [];
// Prefer resident match when residentId is present
if ($residentId !== null && in_array('resident_id', $columns, true)) {
    $candidateCols[] = 'resident_id';
}

// Common external columns
$externalCandidates = ['external_user_id','external_complainant_id','external_complaint_id'];
foreach ($externalCandidates as $ec) {
    if (in_array($ec, $columns, true)) $candidateCols[] = $ec;
}

// If no candidate user columns found, fall back to updating by notification_id only
if (empty($candidateCols)) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $notif_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: notifications.php");
    exit();
}

// Build SQL with placeholders for each candidate column
$whereParts = [];
$params = [];
foreach ($candidateCols as $col) {
    $whereParts[] = "$col = ?";
    // Use sessionId for external/resident matching; resident_id will use residentId specifically
    if ($col === 'resident_id') $params[] = $residentId;
    else $params[] = $sessionId;
}

$sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (" . implode(' OR ', $whereParts) . ")";
if ($stmt = $conn->prepare($sql)) {
    // bind params: first notification id, then the user id values
    $bindParams = array_merge([$notif_id], $params);
    $types = str_repeat('i', count($bindParams));
    // build references for call_user_func_array
    $refs = [];
    $refs[] = &$types;
    foreach ($bindParams as $k => $v) { $refs[] = &$bindParams[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();
}

header("Location: notifications.php");
exit();
?>