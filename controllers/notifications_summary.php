<?php
// Lightweight summary endpoint for unread notifications (used for auto-updating nav badge)
// Returns: { count: number }

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../server/server.php';

try {
    $count = 0;

    // Determine which read column to use. Prefer captain-specific column when
    // the logged-in official is actually the Barangay Captain.
    $useCol = 'is_read';

    // Check if captain-specific column exists
    $hasCapCol = false;
    if ($res = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read_captain'")) {
        $hasCapCol = $res->num_rows > 0;
        $res->free();
    }

    // Check if secretary-specific column exists
    $hasSecCol = false;
    if ($res2 = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read_secretary'")) {
        $hasSecCol = $res2->num_rows > 0;
        $res2->free();
    }

    // If an official is logged in, check their Position in barangay_officials.
    // If their position contains 'captain' (case-insensitive) and the
    // is_read_captain column exists, use that column for unread checks.
    if (!empty($_SESSION['official_id']) && is_numeric($_SESSION['official_id']) && $hasCapCol) {
        $officialId = (int) $_SESSION['official_id'];
        $position = '';
        if ($stmt = $conn->prepare("SELECT Position FROM barangay_officials WHERE Official_ID = ? LIMIT 1")) {
            $stmt->bind_param('i', $officialId);
            $stmt->execute();
            $resPos = bpamis_stmt_get_result($stmt);
            if ($resPos && ($rowPos = $resPos->fetch_assoc())) {
                $position = strtolower(trim((string)($rowPos['Position'] ?? '')));
            }
            $stmt->close();
        }

        if ($position !== '' && strpos($position, 'captain') !== false) {
            $useCol = 'is_read_captain';
        }
    }

    // Allow an optional role override via GET parameter (used by nav includes)
    // Example: notifications_summary.php?role=captain
    $forcedRole = '';
    if (!empty($_GET['role'])) {
        $forcedRole = strtolower(trim((string)$_GET['role']));
        if ($forcedRole === 'captain' && $hasCapCol) {
            $useCol = 'is_read_captain';
        }
        if ($forcedRole === 'secretary' && $hasSecCol) {
            $useCol = 'is_read_secretary';
        }
    }

    // Resident-specific count: if a resident is logged in, return their unread notifications only (exclude trashed)
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'resident' && !empty($_SESSION['user_id'])) {
        $residentId = (int) $_SESSION['user_id'];
        $sql = "SELECT COUNT(*) AS count 
                FROM notifications n
                LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                WHERE n.resident_id = ? AND n.is_read = 0 AND t.notification_id IS NULL";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $residentId);
            $stmt->execute();
            $res = bpamis_stmt_get_result($stmt);
            if ($res && ($row = $res->fetch_assoc())) {
                $count = (int)$row['count'];
            }
            $stmt->close();
        } else {
            $rid = $residentId;
            if ($res = $conn->query("SELECT COUNT(*) AS count 
                                     FROM notifications n
                                     LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                                     WHERE n.resident_id = $rid AND n.is_read = 0 AND t.notification_id IS NULL")) {
                if ($row = $res->fetch_assoc()) $count = (int)$row['count'];
                $res->free();
            }
        }
        echo json_encode(['count' => $count]);
        exit;
    }

    // External-specific count: external complainants use session role 'external' and user_id as external_complaint_id (exclude trashed)
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'external' && !empty($_SESSION['user_id'])) {
        $extId = (int) $_SESSION['user_id'];
        // common column name used elsewhere is external_complaint_id
        $col = 'external_complaint_id';
        $sql = "SELECT COUNT(*) AS count 
                FROM notifications n
                LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                WHERE n.{$col} = ? AND n.is_read = 0 AND t.notification_id IS NULL";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $extId);
            $stmt->execute();
            $res = bpamis_stmt_get_result($stmt);
            if ($res && ($row = $res->fetch_assoc())) {
                $count = (int)$row['count'];
            }
            $stmt->close();
        } else {
            $eid = $extId;
            if ($res = $conn->query("SELECT COUNT(*) AS count 
                                     FROM notifications n
                                     LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                                     WHERE n.{$col} = $eid AND n.is_read = 0 AND t.notification_id IS NULL")) {
                if ($row = $res->fetch_assoc()) $count = (int)$row['count'];
                $res->free();
            }
        }
        echo json_encode(['count' => $count]);
        exit;
    }

    // Prepare and execute. Apply role-aware scoping:
    // - Captain: use is_read_captain (already handled above) and filter types used by captain page
    // - Lupon (Lupon Tagapamayapa / Lupon-Hepe): scope to lupon_id OR official_id and filter types used by lupon page
    // - Default: global unread count

    $isLupon = false;
    if (isset($position) && $position !== '') {
        // Treat any position containing the word "lupon" as a Lupon role.
        // This covers variants like 'lupon-tagapayapa', 'lupon-tagapamayapa', 'lupon-hepe', etc.
        if (strpos($position, 'lupon') !== false) {
            $isLupon = true;
        }
    }

    // If a forced role was provided via GET, respect it for branch selection
    if (!empty($forcedRole) && $forcedRole === 'lupon') {
        $isLupon = true;
    }

    // Decide whether to exclude assignment notifications. We previously applied a global
    // exclusion; make it conditional so Lupon role receives exact unread counts.
    $excludeClause = '';
    $applyExclude = true;
    // If a forced role is provided and it's 'lupon', do not apply the exemption.
    if (!empty($forcedRole) && $forcedRole === 'lupon') {
        $applyExclude = false;
    }
    // If session-detected position is Lupon, do not apply the exemption.
    if (isset($isLupon) && $isLupon) {
        $applyExclude = false;
    }
    if ($applyExclude) {
        $excludeClause = " AND NOT (LOWER(COALESCE(n.title, '')) = 'new case assigned' ";
        // also exclude the respondent notice which should not appear in nav badges/lists
        $excludeClause .= " OR LOWER(COALESCE(n.title, '')) = 'barangay notice: you have been named as respondent'";
        $excludeClause .= " OR LOWER(COALESCE(n.message, '')) RLIKE 'a new case #[0-9]+ has been assigned to you in the conciliation stage')";
    }

    if ($isLupon && !empty($_SESSION['official_id']) && is_numeric($_SESSION['official_id'])) {
        // Scope to this lupon/official and apply lupon type filter (exclude trashed)
        $luponId = (int) $_SESSION['official_id'];
                        // Use the global `is_read` flag for Lupon counts (simpler and consistent)
                        $sql = "SELECT COUNT(*) AS count 
                                FROM notifications n
                                LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                                WHERE n.is_read = 0 AND (n.lupon_id = ? OR n.official_id = ?) 
                                    AND n.type IN ('Case','Hearing','Complaint','Unverified','Resolution','Conciliation','Arbitration')
                                    AND t.notification_id IS NULL" . $excludeClause;
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ii', $luponId, $luponId);
            $stmt->execute();
            $res = bpamis_stmt_get_result($stmt);
            if ($res && ($row = $res->fetch_assoc())) {
                $count = (int)$row['count'];
            }
            $stmt->close();
        } else {
            // fallback to direct query with sanitized id
            $lid = (int)$luponId;
                        $fallbackSql = "SELECT COUNT(*) AS count 
                                                                    FROM notifications n
                                                                    LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                                                                    WHERE n.is_read = 0 AND (n.lupon_id = $lid OR n.official_id = $lid) 
                                                                        AND n.type IN ('Case','Hearing','Complaint','Unverified','Resolution','Conciliation','Arbitration')
                                                                        AND t.notification_id IS NULL" . $excludeClause;
            if ($res = $conn->query($fallbackSql)) {
                if ($row = $res->fetch_assoc()) {
                    $count = (int)$row['count'];
                }
                $res->free();
            }
        }
    } elseif ($useCol === 'is_read_captain') {
        // Captain: count only captain-relevant types (match notifications-captain.php, exclude trashed)
        $sql = "SELECT COUNT(*) AS count 
            FROM notifications n
            LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
            WHERE n.{$useCol} = 0 AND LOWER(n.type) IN ('unverified','hearing','complaint','case','arbitration')
              AND t.notification_id IS NULL" . $excludeClause;
        if ($result = $conn->query($sql)) {
            if ($row = $result->fetch_assoc()) {
                $count = (int)$row['count'];
            }
            $result->free();
        }
    } else {
        // Generic/global unread count (exclude trashed)
        $sql = "SELECT COUNT(*) AS count 
            FROM notifications n
            LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
            WHERE n.{$useCol} = 0 AND t.notification_id IS NULL" . $excludeClause;
        if ($result = $conn->query($sql)) {
            if ($row = $result->fetch_assoc()) {
                $count = (int)$row['count'];
            }
            $result->free();
        }
    }

    echo json_encode(['count' => $count]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['count' => 0, 'error' => true, 'msg' => $e->getMessage()]);
}
