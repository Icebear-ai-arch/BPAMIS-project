<?php
session_start();
include '../server/server.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: notifications.php');
    exit();
}

$extId = (int)$_SESSION['user_id'];

// Try to detect which column references external user in notifications table
$columns = [];
if ($res = $conn->query("SHOW COLUMNS FROM notifications")) {
    while ($c = $res->fetch_assoc()) { $columns[] = $c['Field']; }
}
$candidates = ['external_user_id','external_complainant_id','external_complaint_id'];
$targetCol = '';
foreach ($candidates as $cand) { if (in_array($cand, $columns, true)) { $targetCol = $cand; break; } }

if ($targetCol !== '') {
    $sql = "UPDATE notifications SET is_read = 1 WHERE $targetCol = ?";
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('i', $extId);
        $st->execute();
        $st->close();
    }
}

header('Location: notifications.php');
exit();
