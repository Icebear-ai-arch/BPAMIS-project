<?php
// Secretary Update Case Status - Premium UI (clean file)
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';
date_default_timezone_set('Asia/Manila');
if (!isset($_GET['id'])) {
    header('Location: view_cases.php');
    exit;
}
$caseId = intval($_GET['id']);

// Resolve real table names for case-sensitive Linux hosts
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');
$T_COMPLAINT_INFO = bpamis_table($conn, 'COMPLAINT_INFO');
$T_RESIDENT_INFO = bpamis_table($conn, 'RESIDENT_INFO');
$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
$T_OFFICIAL_ACCOUNTS = bpamis_table($conn, 'official_accounts');
$T_COMPLAINT_RESPONDENTS = bpamis_table($conn, 'COMPLAINT_RESPONDENTS');
$T_MEDIATION_INFO = bpamis_table($conn, 'mediation_info');
$T_CONCILIATION = bpamis_table($conn, 'conciliation');
$T_RESOLUTION = bpamis_table($conn, 'resolution');
$T_SETTLEMENT = bpamis_table($conn, 'settlement');
$T_ARBITRATION = bpamis_table($conn, 'arbitration');
$T_BARANGAY_OFFICIALS = bpamis_table($conn, 'barangay_officials');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
$TB_OFFICIAL_ACCOUNTS = bpamis_quote_table($T_OFFICIAL_ACCOUNTS);
$TB_COMPLAINT_RESPONDENTS = bpamis_quote_table($T_COMPLAINT_RESPONDENTS);
$TB_MEDIATION_INFO = bpamis_quote_table($T_MEDIATION_INFO);
$TB_CONCILIATION = bpamis_quote_table($T_CONCILIATION);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_SETTLEMENT = bpamis_quote_table($T_SETTLEMENT);
$TB_ARBITRATION = bpamis_quote_table($T_ARBITRATION);
$TB_BARANGAY_OFFICIALS = bpamis_quote_table($T_BARANGAY_OFFICIALS);

// Optional columns across DB dumps
$hasComplaintCaseType = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'case_type');

// case_original_id can differ between dumps
$caseOriginalExpr = 'cs.Case_ID AS case_original_id';
$caseOrigCol = bpamis_first_existing_column($conn, $T_CASE_INFO, [
    'case_original_id','case_original','original_case_id','original_case',
    'case_number','case_no','original_casenumber','caseorig','caseid_original'
]);
if ($caseOrigCol) {
    $caseOriginalExpr = 'cs.' . bpamis_quote_ident($caseOrigCol) . ' AS case_original_id';
}

// Complaint details column name drift
$complaintDetailsExpr = 'ci.Complaint_Details';
if (!bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Details')) {
    if (bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Description')) {
        $complaintDetailsExpr = 'ci.Complaint_Description';
    } else {
        $complaintDetailsExpr = 'ci.Complaint_Title';
    }
}

// Respondent id can be absent or different casing
$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID','respondent_id']);

// Fetch core case info (guard prepare and provide fallback)
$sql =
    "SELECT cs.Case_ID, cs.Case_Status, {$caseOriginalExpr}, cs.Date_Opened,\n" .
    "        ci.Complaint_ID, ci.Complaint_Title, {$complaintDetailsExpr} AS Complaint_Details, ci.Date_Filed, " .
    ($hasComplaintCaseType ? "ci.case_type" : "NULL") . " AS case_type,\n" .
    "        comp.First_Name AS Complainant_First, comp.Last_Name AS Complainant_Last" .
    ($respondentIdCol ? ",\n        resp.First_Name AS Respondent_First, resp.Last_Name AS Respondent_Last" : "") . "\n" .
    "    FROM {$TB_CASE_INFO} cs\n" .
    "    LEFT JOIN {$TB_COMPLAINT_INFO} ci ON cs.Complaint_ID = ci.Complaint_ID\n" .
    "    LEFT JOIN {$TB_RESIDENT_INFO} comp ON ci.Resident_ID = comp.Resident_ID\n" .
    ($respondentIdCol ? ("    LEFT JOIN {$TB_RESIDENT_INFO} resp ON ci." . bpamis_quote_ident($respondentIdCol) . " = resp.Resident_ID\n") : "") .
    "    WHERE cs.Case_ID = ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $caseId);
    $stmt->execute();
    $rows = bpamis_stmt_fetch_all_assoc($stmt);
    $stmt->close();
} else {
    // Fallback: inline ID into query as integer to avoid prepare fatal; log error
    error_log('update_case_status.php prepare failed: ' . $conn->error);
    $fallbackSql = str_replace('WHERE cs.Case_ID = ?', 'WHERE cs.Case_ID = ' . $caseId, $sql);
    $result = $conn->query($fallbackSql);
    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
        $result->free();
    }
}

if (empty($rows)) {
    echo "<p class='text-center text-red-600'>Case not found or query error.</p>";
    echo "<p class='text-center text-xs text-gray-500'>" . htmlspecialchars($conn->error) . "</p>";
    exit;
}
$case = $rows[0];
// Prefer original case identifier when available for user-facing messages
$caseOriginalId = isset($case['case_original_id']) && $case['case_original_id'] !== '' ? $case['case_original_id'] : $caseId;
// If the Certificate_Path column exists, fetch it separately to avoid breaking on older schemas
$hasCertificatePath = bpamis_table_has_column($conn, $T_CASE_INFO, 'Certificate_Path');
if ($hasCertificatePath) {
    $cst = $conn->prepare("SELECT Certificate_Path FROM {$TB_CASE_INFO} WHERE Case_ID = ? LIMIT 1");
    if ($cst) {
        $cst->bind_param('i', $caseId);
        $cst->execute();
        $crows = bpamis_stmt_fetch_all_assoc($cst);
        if (!empty($crows)) $case['Certificate_Path'] = $crows[0]['Certificate_Path'] ?? null;
        $cst->close();
    }
}
// Capture complaint id for updates
$complaintId = isset($case['Complaint_ID']) ? intval($case['Complaint_ID']) : 0;

// Removed: Secretary does not assign Lupon Tagapamayapa

// Resolve case type display from COMPLAINT_INFO.case_type if present, else fallback to CASE_INFO.Case_Type
$caseType = '';
if (isset($case['case_type'])) {
    $caseType = trim((string)($case['case_type'] ?? ''));
}
if ($caseType === '') {
    if (bpamis_table_has_column($conn, $T_CASE_INFO, 'Case_Type')) {
        if ($__st = $conn->prepare("SELECT Case_Type FROM {$TB_CASE_INFO} WHERE Case_ID = ?")) {
            $__st->bind_param('i', $caseId);
            $__st->execute();
            $__rows = bpamis_stmt_fetch_all_assoc($__st);
            if (!empty($__rows)) $caseType = trim((string)($__rows[0]['Case_Type'] ?? ''));
            $__st->close();
        } else {
            $tmp = $conn->query("SELECT Case_Type FROM {$TB_CASE_INFO} WHERE Case_ID = $caseId");
            if ($tmp && $tmp->num_rows > 0) {
                $row = $tmp->fetch_assoc();
                $caseType = trim((string)($row['Case_Type'] ?? ''));
            }
        }
    }
}

$current = $case['Case_Status'];
$transitions = [
    'Open' => ['Mediation', 'Resolved', 'Closed'],
    'Mediation' => ['Endorsement for Conciliation', 'Mediation Resolved', 'Certificate to File Action'],
    'Conciliation' => ['Endorsement for Arbitration', 'Conciliation Resolved', 'Certificate to File Action'],
    'Arbitration' => ['Arbitration Resolved', 'Certificate to file Action'],
];
$available = $transitions[$current] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $caseTypeUpdated = false;

    // -------------------- ATTACH CERTIFICATE (Secretary) --------------------
    // Handle certificate upload when secretary attaches a certificate while case is in Certificate to File Action
    if (isset($_POST['attach_certificate'])) {
        // basic permission: allow only when current case status is Certificate to File Action
        if (strcasecmp(trim($current), 'Certificate to File Action') !== 0 && strcasecmp(trim($current), 'This Case give Certificate to file action') !== 0) {
            header('Location: update_case_status.php?id=' . $caseId . '&attach=0&error=invalid_status');
            exit;
        }

        if (!isset($_FILES['certificate_file']) || $_FILES['certificate_file']['error'] !== UPLOAD_ERR_OK) {
            header('Location: update_case_status.php?id=' . $caseId . '&attach=0&error=no_file');
            exit;
        }

        $file = $_FILES['certificate_file'];
        $allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfoType = mime_content_type($file['tmp_name']);
        if (!array_key_exists($ext, $allowed) || $allowed[$ext] !== $finfoType) {
            // Allow if extension matches known types; if mime check fails, still allow common image types
            if (!in_array($finfoType, $allowed, true)) {
                header('Location: update_case_status.php?id=' . $caseId . '&attach=0&error=invalid_type');
                exit;
            }
        }

        // prepare upload dir
        $relDir = 'uploads/cases/' . $caseId . '/';
        $absDir = __DIR__ . '/../' . $relDir;
        if (!is_dir($absDir)) {
            if (!mkdir($absDir, 0755, true) && !is_dir($absDir)) {
                header('Location: update_case_status.php?id=' . $caseId . '&attach=0&error=mkdir');
                exit;
            }
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name']));
        $target = $absDir . time() . '_' . $safeName;
        $relativePath = $relDir . basename($target);

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            header('Location: update_case_status.php?id=' . $caseId . '&attach=0&error=move');
            exit;
        }

        // Ensure Certificate_Path column exists
        if (!bpamis_table_has_column($conn, $T_CASE_INFO, 'Certificate_Path')) {
            $conn->query("ALTER TABLE {$TB_CASE_INFO} ADD COLUMN Certificate_Path VARCHAR(512) NULL");
        }

        // Update CASE_INFO with the relative path
        if ($upd = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Certificate_Path = ? WHERE Case_ID = ?")) {
            $upd->bind_param('si', $relativePath, $caseId);
            $upd->execute();
            $upd->close();
        } else {
            $conn->query("UPDATE {$TB_CASE_INFO} SET Certificate_Path='" . $conn->real_escape_string($relativePath) . "' WHERE Case_ID=$caseId");
        }

        // Notify complainant(s)
        $resn = null;
        if ($ns = $conn->prepare("SELECT co.Resident_ID, co.External_Complainant_ID FROM {$TB_CASE_INFO} cs JOIN {$TB_COMPLAINT_INFO} co ON cs.Complaint_ID=co.Complaint_ID WHERE cs.Case_ID=? LIMIT 1")) {
            $ns->bind_param('i', $caseId);
            $ns->execute();
            $nrows = bpamis_stmt_fetch_all_assoc($ns);
            $ns->close();
            if (!empty($nrows)) {
                $resn = $nrows[0];
            }
        }

        $title = 'Certificate Attached';
        // Prefer case_original_id (if available) for user-facing references; fall back to internal Case_ID
        $caseOriginalId = isset($case['case_original_id']) && $case['case_original_id'] !== '' ? $case['case_original_id'] : $caseId;
        $msg = "A certificate has been attached to your case (ID: $caseOriginalId).";
        $created = date('Y-m-d H:i:s');
        if (!empty($resn)) {
            $resident_id = $resn['Resident_ID'] ?? null;
            $external_id = $resn['External_Complainant_ID'] ?? null;
            if (!empty($resident_id)) {
                if ($ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at, related_id, type) VALUES (?, ?, ?, 0, ?, ?, 'Case')")) {
                    $ins->bind_param('issis', $resident_id, $title, $msg, $created, $caseId);
                    $ins->execute(); $ins->close();
                } else {
                    $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (resident_id,title,message,is_read,created_at,related_id,type) VALUES (" . intval($resident_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($msg) . "', 0, '" . $conn->real_escape_string($created) . "', " . intval($caseId) . ", 'Case')");
                }
            }
            if (!empty($external_id)) {
                if ($ins2 = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at, related_id, type) VALUES (?, ?, ?, 0, ?, ?, 'Case')")) {
                    $ins2->bind_param('issis', $external_id, $title, $msg, $created, $caseId);
                    $ins2->execute(); $ins2->close();
                } else {
                    $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id,title,message,is_read,created_at,related_id,type) VALUES (" . intval($external_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($msg) . "', 0, '" . $conn->real_escape_string($created) . "', " . intval($caseId) . ", 'Case')");
                }
            }
        }

        header('Location: update_case_status.php?id=' . $caseId . '&attach=1');
        exit;
    }

    // -------------------- CASE DISMISSAL (Secretary) --------------------
    // If the secretary clicks Dismiss (available while case is in Mediation/Conciliation/Arbitration)
    if (isset($_POST['dismiss_case'])) {
        $allowedDismissStates = ['Mediation','Conciliation','Arbitration'];
        if (!in_array($current, $allowedDismissStates, true)) {
            // not allowed from current state
            header('Location: view_cases.php?status_updated=0&error=invalid_dismiss');
            exit;
        }

        $reason = trim($_POST['dismiss_reason_case'] ?? '');
        if ($reason === '') {
            // missing reason
            header('Location: update_case_status.php?id=' . $caseId . '&dismissed=0&error=missing_reason');
            exit;
        }

        $conn->begin_transaction();
        try {
            // update case status to Dismissed
            $upd = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Case_Status = ? WHERE Case_ID = ?");
            if ($upd) { $st = 'Dismissed'; $upd->bind_param('si', $st, $caseId); $upd->execute(); $upd->close(); }

            // store dismiss reason on CASE_INFO if column exists (create if missing)
            if (!bpamis_table_has_column($conn, $T_CASE_INFO, 'Dismiss_Reason')) {
                $conn->query("ALTER TABLE {$TB_CASE_INFO} ADD COLUMN Dismiss_Reason TEXT NULL");
            }
            $ust = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Dismiss_Reason = ? WHERE Case_ID = ?");
            if ($ust) { $ust->bind_param('si', $reason, $caseId); $ust->execute(); $ust->close(); }

            // Notify complainant (resident or external)
            $stmtC = $conn->prepare("SELECT co.Resident_ID, co.External_Complainant_ID, co.Complaint_ID FROM {$TB_CASE_INFO} ci JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID WHERE ci.Case_ID = ?");
            $complaintIdForNotif = 0;
            if ($stmtC) {
                $stmtC->bind_param('i', $caseId);
                $stmtC->execute();
                $cRows = bpamis_stmt_fetch_all_assoc($stmtC);
                if (!empty($cRows)) {
                    $rowc = $cRows[0];
                    $resident_id = $rowc['Resident_ID'];
                    $external_id = $rowc['External_Complainant_ID'];
                    $complaintIdForNotif = intval($rowc['Complaint_ID']);
                    $title = 'Case Dismissed';
                    // Use original case identifier for user-facing messages when available
                    $msg = "Your case (ID: $caseOriginalId) has been dismissed. Reason: " . $reason;
                    $now = date('Y-m-d H:i:s');
                    if (!empty($resident_id) && $ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)") ) {
                        $ins->bind_param('isss', $resident_id, $title, $msg, $now); $ins->execute(); $ins->close();
                    } elseif (!empty($resident_id)) {
                        $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (resident_id,title,message,is_read,created_at) VALUES (" . intval($resident_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($msg) . "', 0, '" . $conn->real_escape_string($now) . "')");
                    }
                    if (!empty($external_id) && $ins2 = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)") ) {
                        $ins2->bind_param('isss', $external_id, $title, $msg, $now); $ins2->execute(); $ins2->close();
                    } elseif (!empty($external_id)) {
                        $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id,title,message,is_read,created_at) VALUES (" . intval($external_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($msg) . "', 0, '" . $conn->real_escape_string($now) . "')");
                    }
                }
                $stmtC->close();
            }

            // Notify respondents (resident respondents recorded in COMPLAINT_RESPONDENTS)
            if ($complaintIdForNotif > 0) {
                $rstmt = $conn->prepare("SELECT Respondent_ID FROM {$TB_COMPLAINT_RESPONDENTS} WHERE Complaint_ID = ?");
                if ($rstmt) {
                    $rstmt->bind_param('i', $complaintIdForNotif);
                    $rstmt->execute();
                    $rrRows = bpamis_stmt_fetch_all_assoc($rstmt);
                    foreach ($rrRows as $rowr) {
                        $rid = intval($rowr['Respondent_ID'] ?? 0);
                        if ($rid && $insr = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)") ) {
                            $now = date('Y-m-d H:i:s');
                            $insr->bind_param('isss', $rid, $title, $msg, $now); $insr->execute(); $insr->close();
                        }
                    }
                    $rstmt->close();
                }
            }

            // Notify mediators / lupon: look into mediation_info, resolution, settlement mediator_name fields and CASE_INFO.lupon_assign
            $mediatorNames = [];
            $mQ = $conn->prepare("SELECT mediator_name FROM {$TB_MEDIATION_INFO} WHERE Case_ID = ? LIMIT 1");
            if ($mQ) {
                $mQ->bind_param('i', $caseId);
                $mQ->execute();
                $mr = bpamis_stmt_fetch_all_assoc($mQ);
                if (!empty($mr)) $mediatorNames[] = $mr[0]['mediator_name'] ?? '';
                $mQ->close();
            }
            $rQ = $conn->prepare("SELECT mediator_name FROM {$TB_RESOLUTION} WHERE Case_ID = ? LIMIT 1");
            if ($rQ) {
                $rQ->bind_param('i', $caseId);
                $rQ->execute();
                $rrr = bpamis_stmt_fetch_all_assoc($rQ);
                if (!empty($rrr)) $mediatorNames[] = $rrr[0]['mediator_name'] ?? '';
                $rQ->close();
            }
            $sQ = $conn->prepare("SELECT mediator_name FROM {$TB_SETTLEMENT} WHERE Case_ID = ? LIMIT 1");
            if ($sQ) {
                $sQ->bind_param('i', $caseId);
                $sQ->execute();
                $rs2 = bpamis_stmt_fetch_all_assoc($sQ);
                if (!empty($rs2)) $mediatorNames[] = $rs2[0]['mediator_name'] ?? '';
                $sQ->close();
            }
            // CASE_INFO lupon_assign column (may contain assigned lupon names or string)
            if (bpamis_table_has_column($conn, $T_CASE_INFO, 'lupon_assign')) {
                $ci = $conn->prepare("SELECT lupon_assign FROM {$TB_CASE_INFO} WHERE Case_ID = ? LIMIT 1");
                if ($ci) {
                    $ci->bind_param('i', $caseId);
                    $ci->execute();
                    $rci = bpamis_stmt_fetch_all_assoc($ci);
                    if (!empty($rci)) $mediatorNames[] = $rci[0]['lupon_assign'] ?? '';
                    $ci->close();
                }
            }

            // normalize and split possible comma/semicolon separated names
            $namesFlat = [];
            foreach ($mediatorNames as $mn) {
                if (!$mn) continue;
                $parts = preg_split('/[,;|\\\\\/]+/', $mn);
                foreach ($parts as $p) { $p = trim($p); if ($p !== '') $namesFlat[] = $p; }
            }
            $namesFlat = array_unique($namesFlat);

            foreach ($namesFlat as $nm) {
                // Attempt to find official by name
                $of = $conn->prepare("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE Name LIKE ? LIMIT 1");
                if ($of) {
                    $like = '%' . $nm . '%';
                    $of->bind_param('s', $like);
                    $of->execute();
                    $ofRows = bpamis_stmt_fetch_all_assoc($of);
                    if (!empty($ofRows)) {
                        $orow = $ofRows[0];
                        $oid = intval($orow['Official_ID']);
                        if ($oid && $insO = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (official_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)") ) {
                            $type = 'Dismissal';
                            $now = date('Y-m-d H:i:s');
                            $insO->bind_param('issss', $oid, $title, $msg, $type, $now);
                            $insO->execute(); $insO->close();
                        }
                    }
                    $of->close();
                }
            }

            $conn->commit();
            header('Location: view_cases.php?status_updated=1&dismissed=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Dismiss case failed: ' . $e->getMessage());
            header('Location: update_case_status.php?id=' . $caseId . '&dismissed=0&error=server');
            exit;
        }
    }

    // Normalize special transitions before saving
    if ($newStatus === 'Endorsement for Conciliation') {
        $newStatus = 'Conciliation';
    } elseif ($newStatus === 'Endorsement for Arbitration') {
        $newStatus = 'Arbitration';
    }

    // Optional: update complaint case_type when provided
    $postedType = isset($_POST['complaint_case_type']) ? strtolower(trim($_POST['complaint_case_type'])) : '';
    if ($postedType !== '' && $hasComplaintCaseType && $complaintId > 0) {
        if ($postedType === 'civil' || $postedType === 'civil case') { $postedType = 'civil case'; }
        elseif ($postedType === 'criminal' || $postedType === 'criminal case') { $postedType = 'criminal case'; }
        elseif ($postedType === 'blotter') { $postedType = 'blotter'; }
        else { $postedType = ''; }

        if ($postedType !== '') {
            if ($updCt = $conn->prepare("UPDATE {$TB_COMPLAINT_INFO} SET case_type = ? WHERE Complaint_ID = ?")) {
                $updCt->bind_param('si', $postedType, $complaintId);
                if (!$updCt->execute()) { error_log('updCt execute error: '.$updCt->error); }
                $updCt->close();
                $caseTypeUpdated = true;
            } else {
                $res = $conn->query("UPDATE {$TB_COMPLAINT_INFO} SET case_type='" . $conn->real_escape_string($postedType) . "' WHERE Complaint_ID=" . $complaintId);
                if (!$res) { error_log('fallback update COMPLAINT_INFO failed: '.$conn->error); }
                $caseTypeUpdated = true;
            }
        }
    }

    // Ensure the requested transition is allowed
    if (!in_array($_POST['status'], $available, true)) {
        error_log("Invalid status transition attempted for Case_ID $caseId: $newStatus from $current");
        header('Location: view_cases.php?status_updated=0&error=invalid_transition');
        exit;
    }

    // Update case status
    if ($upd = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Case_Status=? WHERE Case_ID=?")) {
        $upd->bind_param('si', $newStatus, $caseId);
        if (!$upd->execute()) { error_log('Update CASE_INFO error: '.$upd->error); }
        $upd->close();
    } else {
        $res = $conn->query("UPDATE {$TB_CASE_INFO} SET Case_Status='" . $conn->real_escape_string($newStatus) . "' WHERE Case_ID=$caseId");
        if (!$res) { error_log('fallback update CASE_INFO failed: '.$conn->error); }
    }


    // Notify complainant(s) (resident / external) — use prepared statements if possible
    $notifSql = "SELECT co.Resident_ID, co.External_Complainant_ID FROM {$TB_CASE_INFO} cs JOIN {$TB_COMPLAINT_INFO} co ON cs.Complaint_ID=co.Complaint_ID WHERE cs.Case_ID=?";
    $nrow = null;
    if ($ns = $conn->prepare($notifSql)) {
        $ns->bind_param('i', $caseId);
        $ns->execute();
        $nrows = bpamis_stmt_fetch_all_assoc($ns);
        if (!empty($nrows)) $nrow = $nrows[0];
        $ns->close();
    } else {
        $nres = $conn->query(str_replace('WHERE cs.Case_ID=?', 'WHERE cs.Case_ID=' . $caseId, $notifSql));
        if (!$nres) { error_log('notifSql fallback failed: '.$conn->error); }
        if ($nres) { $nrow = $nres->fetch_assoc(); $nres->free(); }
    }

    if (!empty($nrow)) {
        $resident_id = $nrow['Resident_ID'] ?? null;
        $external_id = $nrow['External_Complainant_ID'] ?? null;
    $title = 'Case Status Updated';
    // Use original case identifier for user-facing messages when available
    $message = "The status of your case (ID: $caseOriginalId) has been updated to \"$newStatus\".";
        $created = date('Y-m-d H:i:s');

        // Prepared insert for resident notification
        if (!empty($resident_id) && $ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
            $ins->bind_param('isss', $resident_id, $title, $message, $created);
            if (!$ins->execute()) { error_log('Insert resident notification failed: '.$ins->error); }
            $ins->close();
        } elseif (!empty($resident_id)) {
            $res = $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (resident_id,title,message,is_read,created_at) VALUES (" . intval($resident_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created) . "')");
            if (!$res) { error_log('fallback resident notification failed: '.$conn->error); }
        }

        // External complainant
        if (!empty($external_id) && $ins2 = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
            $ins2->bind_param('isss', $external_id, $title, $message, $created);
            if (!$ins2->execute()) { error_log('Insert external notification failed: '.$ins2->error); }
            $ins2->close();
        } elseif (!empty($external_id)) {
            $res = $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id,title,message,is_read,created_at) VALUES (" . intval($external_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created) . "')");
            if (!$res) { error_log('fallback external notification failed: '.$conn->error); }
        }
    }

    // Notify Lupon Hepe when case moves to Conciliation or Arbitration
    if ($newStatus === 'Conciliation' || $newStatus === 'Arbitration') {
        $luponNotifTitle = ($newStatus === 'Conciliation') ? "Case Updated to Conciliation" : "Case Updated to Arbitration";
        $luponNotifMsg = "Case ID: $caseOriginalId has progressed to the $newStatus stage. Please review the case details.";
        $luponNotifType = $newStatus;
        $luponCreatedAt = date('Y-m-d H:i:s');

        // Robust lookup: case-insensitive, tolerate hyphen/space variants and notify all matches
        $pattern = '%lupon%hepe%';
        $luponStmt = $conn->prepare("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE LOWER(REPLACE(Position, '-', '')) LIKE ? OR LOWER(Position) LIKE ?");
        if ($luponStmt) {
            $luponStmt->bind_param('ss', $pattern, $pattern);
            $luponStmt->execute();
            $luponRows = bpamis_stmt_fetch_all_assoc($luponStmt);
            if (!empty($luponRows)) {
                foreach ($luponRows as $lr) {
                    $luponId = intval($lr['Official_ID']);
                    if (!$luponId) continue;
                    $insLupon = $conn->prepare(
                        "INSERT INTO {$TB_NOTIFICATIONS} (official_id, type, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)"
                    );
                    if ($insLupon) {
                        $insLupon->bind_param('issss', $luponId, $luponNotifType, $luponNotifTitle, $luponNotifMsg, $luponCreatedAt);
                        if (!$insLupon->execute()) { error_log('Insert Lupon notification failed: '.$insLupon->error); }
                        $insLupon->close();
                    } else {
                        error_log('Prepare insert Lupon notification failed: ' . $conn->error);
                    }
                }
            } else {
                error_log("No Lupon Hepe found in barangay_officials for Case_ID $caseId");
            }
            $luponStmt->close();
        } else {
            error_log('Prepare barangay_officials query for Lupon Hepe failed: ' . $conn->error);
        }
    }

    // Deadlines & creating mediation/resolution/settlement/arbitration rows
    $today = date('Y-m-d');
    $deadline = date('Y-m-d', strtotime('+15 days'));

    if ($newStatus === 'Mediation') {
        $check = $conn->query("SELECT 1 FROM {$TB_MEDIATION_INFO} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_MEDIATION_INFO} (Case_ID,Mediation_Date,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert mediation_info failed: '.$conn->error);
            } else {
                $case_deadline = date('Y-m-d', strtotime('+45 days'));
                $deadline_overdue = date('Y-m-d', strtotime('+60 days'));
                if (!$conn->query("UPDATE {$TB_CASE_INFO} SET case_deadline='$case_deadline', deadline_overdue='$deadline_overdue' WHERE Case_ID=$caseId")) {
                    error_log('Update case_info deadlines failed: '.$conn->error);
                }
            }
        }
    } elseif ($newStatus === 'Conciliation') {
        // Create conciliation record if missing
        $check = $conn->query("SELECT 1 FROM {$TB_CONCILIATION} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_CONCILIATION} (Case_ID,Conciliation_Date,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert conciliation failed: '.$conn->error);
            }
        }
    } elseif ($newStatus === 'Resolution') {
        $check = $conn->query("SELECT 1 FROM {$TB_RESOLUTION} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_RESOLUTION} (Case_ID,Resolution_Date,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert resolution failed: '.$conn->error);
            }
        }
    } elseif ($newStatus === 'Settlement') {
        $check = $conn->query("SELECT 1 FROM {$TB_SETTLEMENT} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_SETTLEMENT} (Case_ID,Date_Agreed,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert settlement failed: '.$conn->error);
            }
        }
    } elseif ($newStatus === 'Arbitration') {
        // Create arbitration record if missing
        $check = $conn->query("SELECT 1 FROM {$TB_ARBITRATION} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            $arbDeadline = date('Y-m-d', strtotime('+10 days'));
            if (!$conn->query("INSERT INTO {$TB_ARBITRATION} (Case_ID, Arbitration_Date, Deadline) VALUES ($caseId, '$today', '$arbDeadline')")) {
                error_log('Insert arbitration failed: '.$conn->error);
            }
        }

      // 🔔 Notify Barangay Captain — prepared statement version
$notifTitle = "New Case Moved to Arbitration";
$notifMsg = "Case ID: $caseId has progressed to the Arbitration stage. Please review the case details.";
$notifType = "Arbitration";
$createdAt = date('Y-m-d H:i:s');

// Fetch Barangay Captain
$captainQuery = $conn->prepare("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE Position = ? LIMIT 1");
if ($captainQuery) {
    $position = 'Barangay Captain';
    $captainQuery->bind_param('s', $position);
    $captainQuery->execute();
    $capRows = bpamis_stmt_fetch_all_assoc($captainQuery);

    if (!empty($capRows)) {
        $captainId = intval($capRows[0]['Official_ID'] ?? 0);

        // ✅ Insert notification with type = 'Arbitration'
        $insCap = $conn->prepare("
            INSERT INTO {$TB_NOTIFICATIONS} (official_id, type, title, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        if ($insCap) {
            $insCap->bind_param('issss', $captainId, $notifType, $notifTitle, $notifMsg, $createdAt);
            if (!$insCap->execute()) {
                error_log('❌ Insert captain arbitration notification failed: ' . $insCap->error);
            }
            $insCap->close();
        } else {
            error_log('❌ Prepare insert notification failed: ' . $conn->error);
        }
    } else {
        error_log("⚠️ No Barangay Captain found in barangay_officials for Case_ID $caseId");
    }
    $captainQuery->close();
} else {
    error_log('❌ Prepare barangay_officials query failed: ' . $conn->error);
}

    }

    // Final redirect once everything done
    header('Location: view_cases.php?status_updated=1');
    exit;
}



$statusUpper = strtoupper($current);
$statusStyles = ['OPEN' => 'bg-sky-50 text-sky-600 border border-sky-200', 'MEDIATION' => 'bg-amber-50 text-amber-600 border border-amber-200', 'CONCILIATION' => 'bg-indigo-50 text-indigo-600 border border-indigo-200', 'SETTLEMENT' => 'bg-fuchsia-50 text-fuchsia-600 border border-fuchsia-200', 'RESOLVED' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'CLOSED' => 'bg-gray-100 text-gray-700 border border-gray-300'];
$statusClass = $statusStyles[$statusUpper] ?? 'bg-primary-50 text-primary-600 border border-primary-200';

// // ✅ Check if the status is 'Arbitration'
// if (strtolower($new_status) === 'arbitration') {
//     // Create a notification for the captain
//     $title = "Case #$case_id is now in Arbitration";
//     $message = "The case with ID #$case_id has entered arbitration. Please review and take appropriate action.";

//     $insertNotif = $conn->prepare("
//         INSERT INTO notifications (title, message, type, target_role, status, created_at)
//         VALUES (?, ?, 'Arbitration', 'Captain', 'Unread', NOW())
//     ");
//     $insertNotif->bind_param("ss", $title, $message);
//     $insertNotif->execute();
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <title>Case • Update Status</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: { 50: '#f0f7ff', 100: '#e0effe', 200: '#bae2fd', 300: '#7cccfd', 400: '#36b3f9', 500: '#0c9ced', 600: '#0281d4', 700: '#026aad', 800: '#065a8f', 900: '#0a4b76' } }, boxShadow: { glow: '0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)' }, animation: { 'fade-in': 'fadeIn .4s ease-out' }, keyframes: { fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } } } } } };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        * { box-sizing: border-box; }

        .glass {
            background: linear-gradient(140deg, rgba(255,255,255,.92), rgba(255,255,255,.68));
            backdrop-filter: blur(14px) saturate(140%);
            -webkit-backdrop-filter: blur(14px) saturate(140%);
        }

        .field-label {
            font-size: 11px;
            letter-spacing: .05em;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
        }

        /* Tablet responsive styles (641px - 1024px) */
        @media (min-width:641px) and (max-width:1024px) {
            main { padding-left:1.5rem; padding-right:1.5rem; }
            section.glass { padding:1.5rem !important; }
            header h1 { font-size:1.5rem !important; }
            .grid.md\:grid-cols-2 { grid-template-columns: repeat(2,1fr); }
        }

        /* Mobile (<=640px) - match view_case_details sizing */
        @media (max-width:640px) {
            body { font-size:14px; }
            main { padding-left:0.5rem !important; padding-right:0.5rem !important; padding-top:1rem !important; padding-bottom:1.5rem !important; }
            section.glass { padding:0.75rem !important; border-radius:0.75rem !important; }

            /* Header adjustments */
            header { margin-bottom:1rem !important; }
            header h1 { font-size:1rem !important; line-height:1.3 !important; gap:0.375rem !important; }

            /* If icon exists in header, make smaller and inline */
            header .w-20 { display:none; }
            header h1 .w-8 { width:1.75rem !important; height:1.75rem !important; border-radius:0.5rem !important; }
            header h1 .w-8 i { font-size:0.8rem !important; }

            /* Badges and meta */
            header h1 span.inline-flex { font-size:0.6rem !important; padding:0.2rem 0.4rem !important; }
            header .mt-3 { margin-top:0.375rem !important; font-size:0.65rem !important; }

            /* Grids -> single column */
            .grid.gap-5, .grid.md\:grid-cols-2 { grid-template-columns:1fr !important; gap:0.4rem !important; }

            /* Card padding */
            .group.rounded-xl { padding:0.5rem !important; border-radius:0.5rem !important; }

            /* Certificate / action buttons stack full width */
            .mt-3 a.inline-flex, .pt-4.border-t a, .pt-4.border-t button { width:100% !important; font-size:0.75rem !important; padding:0.5rem 0.75rem !important; }

            /* Reduce decorative background scale */
            .pointer-events-none .absolute { transform: scale(0.5); }

            /* Smaller selects and action buttons on mobile */
            select[name="status"], select[name="complaint_case_type"] {
                font-size: 0.85rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            /* Action buttons (Cancel / Update / Dismiss) smaller on mobile */
            .flex.flex-wrap .inline-flex, #btnOpenDismiss {
                font-size: 0.85rem !important;
                padding: 0.45rem 0.6rem !important;
            }

            /* Back-to-Cases: smaller link and icon on mobile */
            .back-to-cases { margin-bottom: 0.5rem !important; }
            .back-to-cases a { font-size: 0.9rem !important; }
            .back-to-cases a .inline-flex { width: 2rem !important; height: 2rem !important; }
            .back-to-cases a .ml-2 { margin-left: 0.5rem !important; }
        }

        /* Extra small devices (<=380px) */
        @media (max-width:380px) {
            body { font-size:13px; }
            main { padding-left:0.375rem !important; padding-right:0.375rem !important; }
            section.glass { padding:0.5rem !important; }
            header h1 { font-size:0.9rem !important; }
            header h1 span.inline-flex { font-size:0.55rem !important; padding:0.15rem 0.35rem !important; }
            .group p:not(.field-label) { font-size:0.7rem !important; }
            h2 { font-size:0.55rem !important; }
            .field-label { font-size:0.5rem !important; }
            .pt-4.border-t a, .pt-4.border-t button { font-size:0.7rem !important; padding:0.45rem 0.6rem !important; }

            /* Extra small: reduce select and button sizes further */
            select[name="status"], select[name="complaint_case_type"] {
                font-size: 0.78rem !important;
                padding: 0.4rem 0.6rem !important;
            }
            .flex.flex-wrap .inline-flex, #btnOpenDismiss {
                font-size: 0.78rem !important;
                padding: 0.4rem 0.55rem !important;
            }

            /* Back-to-Cases even smaller on very small screens */
            .back-to-cases a { font-size: 0.8rem !important; }
            .back-to-cases a .inline-flex { width: 1.75rem !important; height: 1.75rem !important; }
            .back-to-cases a .ml-2 { margin-left: 0.4rem !important; }
        }
    </style>
</head>

<body
    class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl">
        </div>
    </div>
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    <main class="relative z-10 max-w-4xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3 back-to-cases"><a href="view_cases.php"
                class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition"><span
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i
                        class="fa fa-arrow-left"></i></span><span class="ml-2">Back to Cases</span></a></div>
        <section
            class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40">
                </div>
            </div>
            <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-8">
                <div class="flex-1 min-w-0">
                    <h1
                        class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-primary-50 ring-2 ring-primary-100"><i class="fa fa-gavel text-base text-primary-600"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Case
                            #<?= htmlspecialchars($case['Case_ID']) ?></span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $statusClass ?> shadow-sm"><i
                                class="fa fa-circle text-[8px]"></i>
                            <?= htmlspecialchars($case['Case_Status']) ?></span>
                        <?php if (trim((string)($caseType ?? '')) !== ''): ?>
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full bg-gray-100 text-gray-700"><?= htmlspecialchars(ucwords($caseType)) ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500"><span
                            class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> Opened
                            <?= date('F d, Y', strtotime($case['Date_Opened'])) ?></span><span
                            class="inline-flex items-center gap-1"><i class="fa fa-file-lines"></i> Filed
                            <?= date('F d, Y', strtotime($case['Date_Filed'])) ?></span></div>
                </div>
            </header>
            <div class="space-y-10">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Case Context</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Complainant</p>
                            <p class="font-semibold text-gray-800">
                                <?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></p>
                        </div>
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Respondent</p>
                            <p class="text-gray-700">
                                <?= htmlspecialchars(trim($case['Respondent_First'] . ' ' . $case['Respondent_Last'])) ?>
                            </p>
                        </div>
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm md:col-span-2">
                            <p class="field-label mb-1">Complaint Description</p>
                            <div class="text-gray-800 whitespace-pre-line break-words">
                                <?= nl2br(htmlspecialchars($case['Complaint_Details'] ?? '')) ?>
                            </div>
                        </div>
                    
                    </div>
                    
                </div>
                <!-- Lupon Tagapamayapa assignment UI removed for Secretary -->
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Update Status</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-5">
                        <?php if ($hasComplaintCaseType && trim($caseType) === ''): ?>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="field-label mb-2 block">Case Type</label>
                                    <select name="complaint_case_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium" required>
                                        <option value="" disabled selected>Select case type</option>
                                        <option value="civil">Civil</option>
                                        <option value="criminal">Criminal</option>
                                        <option value="blotter">Blotter</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Required: choose the case type to be saved to the complaint record.</p>
                                    <?php if (!empty($_GET['case_type_updated'])): ?>
                                        <p class="mt-1 text-xs text-emerald-700 font-medium">Case type saved.</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($available): ?>
                                        <label class="field-label mb-2 block">Select New Status</label>
                                        <select name="status"
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium">
                                            <?php foreach ($available as $s): ?>
                                                <option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                                        </select>
                                                 <?php elseif (strcasecmp(trim($current), 'Certificate to File Action') === 0 || strcasecmp(trim($current), 'This Case give Certificate to file action') === 0): ?>
                                                     <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-emerald-700 font-medium">This case is now marked as Certificate to File Action.</div>
                                                     <div class="mt-3">
                                                         <label class="field-label mb-2 block">Attach Certificate</label>
                                                         <?php if (!empty($case['Certificate_Path'])): ?>
                                                             <p class="text-sm text-gray-600">Current Certificate: <a href="../<?= htmlspecialchars($case['Certificate_Path']) ?>" target="_blank" class="text-primary-600 underline">View</a></p>
                                                         <?php endif; ?>
                                                         <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block" />
                                                         <div class="mt-3">
                                                             <button type="submit" name="attach_certificate" value="1" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow text-sm font-medium">Attach Certificate</button>
                                                         </div>
                                                     </div>
                                    <?php else: ?>
                                        <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-red-600 font-medium">This case is closed. No further updates permitted.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($available): ?>
                                <div>
                                    <label class="field-label mb-2 block">Select New Status</label>
                                    <select name="status"
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium">
                                        <?php foreach ($available as $s): ?>
                                            <option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            <?php elseif (strcasecmp(trim($current), 'Certificate to File Action') === 0 || strcasecmp(trim($current), 'This Case give Certificate to file action') === 0): ?>
                                     <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-emerald-700 font-medium">This case is now marked as Certificate to File Action.</div>
                                     <div class="mt-3">
                                         <label class="field-label mb-2 block">Attach Certificate</label>
                                         <?php if (!empty($case['Certificate_Path'])): ?>
                                             <p class="text-sm text-gray-600">Current Certificate: <a href="../<?= htmlspecialchars($case['Certificate_Path']) ?>" target="_blank" class="text-primary-600 underline">View</a></p>
                                         <?php endif; ?>
                                         <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block" />
                                         <div class="mt-3">
                                             <button type="submit" name="attach_certificate" value="1" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow text-sm font-medium">Attach Certificate</button>
                                         </div>
                                     </div>
                            <?php else: ?>
                                <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-red-600 font-medium">This case is closed. No further updates permitted.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <a href="view_cases.php"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i
                                    class="fa fa-arrow-left"></i> Cancel</a>
                            <button type="submit" <?= !$available ? 'disabled' : '' ?>
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition disabled:opacity-60 disabled:cursor-not-allowed"><i
                                    class="fa fa-save"></i> Update Status</button>
                            <?php if (in_array($current, ['Mediation','Conciliation','Arbitration'], true)): ?>
                                <button type="button" id="btnOpenDismiss" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white shadow text-sm font-medium ml-2"><i class="fa fa-ban"></i> Dismiss Case</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
    <!-- Dismiss Case Modal -->
    <div id="dismissModal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" id="dismissOverlay"></div>
        <div class="relative w-full max-w-xl mx-4">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <form method="POST" class="p-6" id="dismissForm">
                    <input type="hidden" name="dismiss_case" value="1">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Dismiss Case</h3>
                    <p class="text-sm text-gray-600 mb-4">Provide a short reason why this case is being dismissed. Notifications will be sent to the complainant, respondent(s), and mediators / lupon tagapamayapa (if any).</p>
                    <label class="field-label mb-1 block">Reason for Dismissal</label>
                    <textarea name="dismiss_reason_case" id="dismiss_reason_case" rows="4" required class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-400 focus:border-rose-400 text-sm mb-4" placeholder="Enter dismissal reason..."></textarea>
                    <div class="flex justify-end gap-3">
                        <button type="button" id="cancelDismiss" class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white">Confirm Dismiss</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function(){
            var btn = document.getElementById('btnOpenDismiss');
            var modal = document.getElementById('dismissModal');
            var overlay = document.getElementById('dismissOverlay');
            var cancel = document.getElementById('cancelDismiss');

            function show() { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
            function hide() { modal.classList.add('hidden'); document.body.style.overflow = ''; }

            if (btn) btn.addEventListener('click', function(e){ e.preventDefault(); show(); });
            if (overlay) overlay.addEventListener('click', hide);
            if (cancel) cancel.addEventListener('click', function(e){ e.preventDefault(); hide(); });

            // Close on Escape
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hide(); });
        })();
    </script>

    <?php $conn->close(); ?>
</body>

</html>

