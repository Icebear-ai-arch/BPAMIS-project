<?php
// controllers/mark_all_notifications_read_captain_redirect.php
// Direct (non-AJAX) endpoint to mark captain notifications as read, then redirect back with a status.

// Prevent idle-timeout script injection for this flow
if (!defined('SC_SUPPRESS_SCRIPT')) {
    define('SC_SUPPRESS_SCRIPT', true);
}

require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/session_control.php';

function ensure_column(mysqli $conn, string $table, string $column, string $definition): bool {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows > 0) return true;
    return (bool)$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

$affected = 0;
try {
    $types = ['Unverified','Hearing','Complaint','Case','Arbitration'];
    $useCaptainFlag = ensure_column($conn, 'notifications', 'is_read_captain', 'TINYINT(1) NOT NULL DEFAULT 0');
    $targetCol = $useCaptainFlag ? 'is_read_captain' : 'is_read';
    $inList = "('" . implode("','", array_map([$conn, 'real_escape_string'], $types)) . "')";
    $sql = "UPDATE notifications SET $targetCol = 1 WHERE ($targetCol = 0 OR $targetCol IS NULL) AND type IN $inList";
    if (!$conn->query($sql)) {
        throw new Exception($conn->error);
    }
    $affected = $conn->affected_rows;
} catch (Throwable $e) {
    // On error, redirect with error message (trim to avoid header issues)
    $msg = substr(urlencode($e->getMessage()), 0, 200);
    header('Location: ../OfficialMenu/notifications-captain.php?mark=0&error=' . $msg);
    exit;
}

header('Location: ../OfficialMenu/notifications-captain.php?mark=1&affected=' . (int)$affected);
exit;
