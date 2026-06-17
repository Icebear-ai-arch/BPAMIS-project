<?php
// filepath: c:\xampp\htdocs\BPAMIS_01\controllers\push_notifications.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../server/server.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// helper: dynamic bind_param without variadics
function stmt_bind_params($stmt, $types, array &$params){
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $k => $v) { $refs[] = &$params[$k]; }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    $hasUser     = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    $hasOfficial = isset($_SESSION['official_id']) && is_numeric($_SESSION['official_id']);

    if (!$hasUser && !$hasOfficial) {
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Not authenticated']);
        exit;
    }

    $sinceId = (int)($_GET['since_id'] ?? 0);

    $clauses = [];
    $params  = [];
    $types   = '';

    // Optional role hint from client to broaden scope for certain official roles (e.g., secretary sees broadcast 'Unverified')
    $roleHint = isset($_GET['role_hint']) ? strtolower(trim($_GET['role_hint'])) : '';

    $isLupon = false;
    $isCaptain = false;
    $isSecretary = false;

    if ($hasOfficial) {
        $oid = (int)$_SESSION['official_id'];
        $position = strtolower(trim($_SESSION['official_position'] ?? ''));
        $isCaptain = (strpos($position, 'barangay captain') !== false);
        $isSecretary = (strpos($position, 'secretary') !== false);
        $isLupon = (strpos($position, 'lupon tagapamayapa') !== false);
        
        // Base targeting: explicit official or lupon assignment.
        // Only match lupon_id when the notification does NOT have an explicit official_id set.
        // For lupon members, exclude notifications where official_id points to a captain.
        if ($isLupon) {
            // Lupon members should NOT see notifications explicitly targeted at the captain
            // Match when:
            // 1. official_id matches this lupon member's ID, OR
            // 2. lupon_id matches AND official_id is NULL (general lupon notifications)
            // BUT exclude if official_id points to ANY captain (not just this user)
            $officialClause = '(
                (notifications.official_id = ?
                    AND notifications.resident_id IS NULL
                    AND notifications.external_complaint_id IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM barangay_officials bo 
                        WHERE bo.Official_ID = notifications.official_id 
                        AND (
                            LOWER(bo.Position) LIKE "%barangay captain%"
                            OR LOWER(bo.Position) LIKE "%secretary%"
                        )
                    )
                )
                OR (
                    notifications.lupon_id = ?
                    AND notifications.official_id IS NULL
                    AND notifications.resident_id IS NULL
                    AND notifications.external_complaint_id IS NULL
                )
            )';
        } else {
            // Captain and other officials: original logic with resident/external guard
            $officialClause = '(
                (notifications.official_id = ?
                    AND notifications.resident_id IS NULL
                    AND notifications.external_complaint_id IS NULL
                )
                OR (
                    notifications.lupon_id = ?
                    AND notifications.official_id IS NULL
                    AND notifications.resident_id IS NULL
                    AND notifications.external_complaint_id IS NULL
                )
            )';
        }
        $params[] = $oid; $params[] = $oid; $types .= 'ii';

        // Broadcast clause: notifications without a specific user mapping but relevant to officials
        // We keep it conservative: only include rows whose type indicates relevance.
        // Prepare broadcast placeholders and parameters only when the broadcast clause will be used.
        $broadcastTypes = ['unverified','complaint','case','hearing','arbitration','resolution'];
        $btPlaceholders = implode(',', array_fill(0, count($broadcastTypes), '?'));
        
        // System-level broadcast check: no specific user/official targeting
        // NOTE: related_id can be set (for linking to complaint/case) - it's not a targeting field
        $systemBroadcastNullCheck = '(notifications.official_id IS NULL AND notifications.lupon_id IS NULL AND notifications.resident_id IS NULL AND notifications.external_complaint_id IS NULL)';

        // Captain and Secretary should see all complaint/case-related broadcasts
        // Lupon and other officials should NOT see these broadcasts
        if ($isCaptain || $isSecretary || $roleHint === 'secretary') {
            // Captain and Secretary: see all allowlisted types when no specific targeting is set
            // Add the broadcast type params here because the SQL will include placeholders
            foreach ($broadcastTypes as $bt) { $params[] = $bt; $types .= 's'; }
            $broadcastClause = '(
                LOWER(notifications.type) IN (' . $btPlaceholders . ')
                AND ' . $systemBroadcastNullCheck . '
            )';
        } else {
            // Lupon and other officials: NO broadcast notifications (only explicitly targeted ones)
            $broadcastClause = '(1=0)'; // Always false - no broadcasts for lupon
        }

        $clauses[] = '(' . $officialClause . ' OR ' . $broadcastClause . ')';
    }
    if ($hasUser) {
        $uid = (int)$_SESSION['user_id'];
        $clauses[] = '(notifications.resident_id = ? OR notifications.external_complaint_id = ?)';
        $params[] = $uid; $params[] = $uid; $types .= 'ii';
    }

    $where = implode(' OR ', $clauses);
    if ($where === '') {
        echo json_encode(['success'=>true,'data'=>[], 'max_id'=>$sinceId]);
        exit;
    }

    $excludeCaptainTarget = '';
    if ($hasOfficial && $isLupon) {
        $excludeCaptainTarget = " AND NOT EXISTS (\n            SELECT 1 FROM barangay_officials bo_target\n            WHERE bo_target.Official_ID = notifications.official_id\n              AND (LOWER(bo_target.Position) LIKE '%barangay captain%' OR LOWER(bo_target.Position) LIKE '%secretary%')\n        )";
    }

    // Build a role-aware read-filter so already-read notifications aren't pushed.
    $readFilterParts = [];
    if ($hasOfficial) {
        // Keep the base resident/global read flag check to maintain backward compatibility
        $readFilterParts[] = '(notifications.is_read = 0 OR notifications.is_read IS NULL)';

        // If the per-captain column exists, ensure it is not marked read for captain viewers
        $readFilterParts[] = "(CASE WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'is_read_captain') THEN (notifications.is_read_captain = 0 OR notifications.is_read_captain IS NULL) ELSE TRUE END)";

        // If the per-secretary column exists, ensure it is not marked read for secretary viewers
        $readFilterParts[] = "(CASE WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'is_read_secretary') THEN (notifications.is_read_secretary = 0 OR notifications.is_read_secretary IS NULL) ELSE TRUE END)";
    } else {
        // Resident / external complaint viewers rely on `is_read`
        $readFilterParts[] = '(notifications.is_read = 0 OR notifications.is_read IS NULL)';
    }

    $readFilter = 'AND (' . implode(' AND ', $readFilterParts) . ')';

    // Use notification_id only (avoid unknown column "id")
    // Only return notifications that are relevant to this user/official AND are unread
    // according to role-specific flags. Exclude notifications that are in the notifications_trash table.
    $sql = "
     SELECT notifications.notification_id AS nid,
         COALESCE(notifications.title,'Notification') AS ttl,
         COALESCE(notifications.message,'') AS msg,
         COALESCE(notifications.type,'notice') AS typ,
         COALESCE(notifications.created_at, NOW()) AS crt,
         notifications.is_read,
         notifications.is_read_captain,
         notifications.is_read_secretary,
         notifications.related_id,
         notifications.isPriority,
         notifications.official_id,
         notifications.lupon_id,
         notifications.external_complaint_id
                FROM notifications
                LEFT JOIN notifications_trash ON notifications.notification_id = notifications_trash.notification_id
                WHERE notifications_trash.notification_id IS NULL
                  AND ( ($where) )
                    {$excludeCaptainTarget}
          {$readFilter}
          AND notifications.notification_id > ?
        ORDER BY notifications.notification_id ASC
        LIMIT 50
    ";
    $params[] = $sinceId; $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'SQL prepare error','err'=>$conn->error]);
        exit;
    }

    if (!stmt_bind_params($stmt, $types, $params)) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Bind params error']);
        exit;
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'SQL execute error','err'=>$stmt->error]);
        $stmt->close();
        exit;
    }

    // Fetch assoc
    $res = bpamis_stmt_get_result($stmt);
    if ($res === false) {
        // Fallback to bind_result if mysqlnd is unavailable
        $stmt->bind_result($nid, $ttl, $msg, $typ, $crt);
        $data = [];
        while ($stmt->fetch()) {
            $data[] = [
                'id'      => (int)$nid,
                'title'   => (string)$ttl,
                'message' => (string)$msg,
                'type'    => (string)$typ,
                'created' => (string)$crt,
            ];
        }
        $stmt->close();
    } else {
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'      => (int)$row['nid'],
                'title'   => (string)$row['ttl'],
                'message' => (string)$row['msg'],
                'type'    => (string)$row['typ'],
                'created' => (string)$row['crt'],
                // pass extra metadata so clients/worker can make better decisions
                'related_id' => isset($row['related_id']) ? $row['related_id'] : null,
                'isPriority' => isset($row['isPriority']) ? (bool)$row['isPriority'] : false,
                'official_id' => isset($row['official_id']) ? $row['official_id'] : null,
                'lupon_id' => isset($row['lupon_id']) ? $row['lupon_id'] : null,
                'external_complaint_id' => isset($row['external_complaint_id']) ? $row['external_complaint_id'] : null,
                'is_read_secretary' => isset($row['is_read_secretary']) ? (bool)$row['is_read_secretary'] : false
            ];
        }
        $stmt->close();
    }

    // Normalize messages for official viewers: avoid addressing them with 'your' — replace with 'the'
    if ($hasOfficial && is_array($data) && count($data)) {
        $replaceYour = function($text) {
            if (!is_string($text) || $text === '') return $text;
            return preg_replace_callback('/\byour\b/i', function($m){
                $match = $m[0];
                // Preserve capitalization: 'Your' -> 'The', 'your' -> 'the'
                if (ctype_upper(substr($match,0,1))) return 'The';
                return 'the';
            }, $text);
        };

        foreach ($data as &$n) {
            if (isset($n['message'])) $n['message'] = $replaceYour($n['message']);
            if (isset($n['title']))   $n['title']   = $replaceYour($n['title']);
        }
        unset($n);
    }

    // de-dup in session
    if (!isset($_SESSION['pn_seen_ids'])) $_SESSION['pn_seen_ids'] = [];
    $seen = $_SESSION['pn_seen_ids'];
    $filtered = [];
    $maxId = $sinceId;
    foreach ($data as $n) {
        if (!in_array($n['id'], $seen, true)) {
            $filtered[] = $n;
            $seen[] = $n['id'];
            if ($n['id'] > $maxId) $maxId = $n['id'];
        }
    }
    $_SESSION['pn_seen_ids'] = array_slice(array_values(array_unique($seen)), -500);

    echo json_encode([
        'success' => true,
        'data'    => $filtered,
        'max_id'  => $maxId
    ]);
    exit;

} catch (Throwable $e) {
    error_log('push_notifications.php error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','err'=>$e->getMessage()]);
    exit;
}

if (isset($_GET['self_test'])) {
    // Return one fake notification so you can verify native toasts
    $fakeId = time() % 1000000000;
    echo json_encode([
        'success' => true,
        'data'    => [[
            'id' => $fakeId,
            'title' => 'Test notification',
            'message' => 'This is a test from self_test=1',
            'type' => 'notice',
            'created' => date('Y-m-d H:i:s')
        ]],
        'max_id'  => $fakeId
    ]);
    exit;
}
?>
