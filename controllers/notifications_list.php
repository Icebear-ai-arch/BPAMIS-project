<?php
// Returns latest notifications for secretary view (JSON list)
// Shape: { notifications: [ { notification_id, type, title, message, created_at, is_read, isPriority } ] }
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../server/server.php';

try {
    $rows = [];
    $excludeTrash = isset($_GET['exclude_trash']) && $_GET['exclude_trash'] == '1';
    
    if ($excludeTrash) {
        // Exclude notifications that are in trash
        $sql = "SELECT n.notification_id, n.type, n.title, n.message, n.created_at, n.is_read, COALESCE(n.isPriority, 0) AS isPriority
                FROM notifications n
                LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                WHERE t.notification_id IS NULL
                ORDER BY n.created_at DESC
                LIMIT 100";
    } else {
        // Original query (for backward compatibility)
        $sql = "SELECT notification_id, type, title, message, created_at, is_read, COALESCE(isPriority, 0) AS isPriority
                FROM notifications
                ORDER BY created_at DESC
                LIMIT 100";
    }
    
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            // Normalize types
            $r['is_read'] = (int)($r['is_read'] ?? 0);
            $r['isPriority'] = (int)($r['isPriority'] ?? 0);
            $rows[] = $r;
        }
        $res->free();
    }
    echo json_encode([ 'notifications' => $rows ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'notifications' => [], 'error' => true ]);
}
