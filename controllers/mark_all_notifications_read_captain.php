<?php
// controllers/mark_all_notifications_read_captain.php
// Marks only captain-relevant notifications as read (captain-specific flag when present)

header('Content-Type: application/json');
// Ensure idle-timeout JS is not injected into JSON responses
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

try {
    // Captain sees these types on notifications-captain.php
    $types = ['Unverified','Hearing','Complaint','Case','Arbitration'];

    // Prefer captain-specific flag if available (create if missing)
    $useCaptainFlag = ensure_column($conn, 'notifications', 'is_read_captain', 'TINYINT(1) NOT NULL DEFAULT 0');
    $targetCol = $useCaptainFlag ? 'is_read_captain' : 'is_read';
    $targetSet = "$targetCol = 1";
    // Treat NULL as unread as well
    $targetWhere = "($targetCol = 0 OR $targetCol IS NULL)";

    // Since the IN list is static and not user input, inline constants
    $inList = "('" . implode("','", array_map([$conn, 'real_escape_string'], $types)) . "')";
    $sql = "UPDATE notifications SET $targetSet WHERE $targetWhere AND type IN $inList";

    if (!$conn->query($sql)) {
        throw new Exception($conn->error);
    }

    echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
