<?php
// Secretary Complaint Details (Premium UI)
include '../controllers/session_control.php';

// ✅ Ensure PHP uses Manila time
date_default_timezone_set('Asia/Manila');

// ✅ Include DB connection
include '../server/server.php';

// ✅ Make sure MySQL also uses Asia/Manila timezone for NOW()
$conn->query("SET time_zone = '+08:00'");

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id <= 0) {
    echo "Invalid complaint.";
    exit;
}

// enable edit mode via ?edit=1 (click "Validate / Edit")
$editing = isset($_GET['edit']) && ($_GET['edit'] === '1' || $_GET['edit'] === 'true');
// determine current editor from session (adjust keys to match your auth)
$current_user = 'Unknown User';
if (!empty($_SESSION['username'])) $current_user = $_SESSION['username'];
elseif (!empty($_SESSION['user_name'])) $current_user = $_SESSION['user_name'];
elseif (!empty($_SESSION['user'])) $current_user = $_SESSION['user'];
elseif (!empty($_SESSION['FullName'])) $current_user = $_SESSION['FullName'];
elseif (!empty($_SESSION['First_Name']) || !empty($_SESSION['Last_Name'])) $current_user = trim(($_SESSION['First_Name'] ?? '') . ' ' . ($_SESSION['Last_Name'] ?? ''));
$error = '';

// Load version history (complaint_info_history)
$historyEntries = [];
$hstmt = $conn->prepare("SELECT id, version_number, details, updated_by, updated_at FROM complaint_info_history WHERE complaint_id = ? ORDER BY version_number DESC, updated_at DESC");
if ($hstmt) {
    $hstmt->bind_param('i', $complaint_id);
    $hstmt->execute();
    $hres = bpamis_stmt_get_result($hstmt);
    while ($hrow = $hres->fetch_assoc()) $historyEntries[] = $hrow;
    $hstmt->close();
}

// ✅ Fetch complaint and complainant info
$sql = "SELECT c.*, 
               r.First_Name AS Res_First_Name, 
               r.Last_Name AS Res_Last_Name, 
               e.First_Name AS Ext_First_Name, 
               e.Last_Name AS Ext_Last_Name
        FROM COMPLAINT_INFO c
        LEFT JOIN RESIDENT_INFO r ON c.Resident_ID = r.Resident_ID
        LEFT JOIN EXTERNAL_COMPLAINANT e ON c.External_Complainant_ID = e.External_Complaint_ID
        WHERE c.Complaint_ID = $complaint_id";
$res = $conn->query($sql);
if (!$res || $res->num_rows === 0) {
    echo "Complaint not found.";
    exit;
}
$complaint = $res->fetch_assoc();
// Human-friendly complaint reference (display only)
$complaint_ref = 'COMP#' . str_pad((int)$complaint_id, 2, '0', STR_PAD_LEFT);

$is_case = $conn->query("SELECT 1 FROM CASE_INFO WHERE Complaint_ID=$complaint_id LIMIT 1")->num_rows > 0;
$is_rejected = strtolower($complaint['Status']) === 'rejected';
$is_dismissed = strtolower($complaint['Status']) === 'dismissed';
$is_locked = $is_case || $is_rejected || $is_dismissed; // non-editable states

// ✅ Load persisted custom case types (persisted in CASE_TYPES table)
$caseTypes = ['Record Purposes'];
$ctTableCheck = $conn->query("SHOW TABLES LIKE 'CASE_TYPES'");
if ($ctTableCheck && $ctTableCheck->num_rows > 0) {
    $rs = $conn->query("SELECT Case_Type FROM CASE_TYPES ORDER BY Case_Type ASC");
    if ($rs) {
        while ($r = $rs->fetch_assoc()) {
            if (!in_array($r['Case_Type'], $caseTypes, true)) $caseTypes[] = $r['Case_Type'];
        }
        $rs->close();
    }
}

// ✅ Fetch existing case type (if CASE_INFO has Case_Type column)
$existing_case_type = null;
if ($is_case) {
    $colCheck = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'Case_Type'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $ct = $conn->query("SELECT Case_Type FROM CASE_INFO WHERE Complaint_ID=$complaint_id ORDER BY Case_ID DESC LIMIT 1");
        if ($ct && $ct->num_rows > 0) {
            $existing_case_type = $ct->fetch_assoc()['Case_Type'] ?? null;
        }
    }
}

// ✅ Fetch Case_ID for this complaint if it exists
$case_id_for_complaint = null;
if ($is_case) {
    $cidRes = $conn->query("SELECT Case_ID FROM CASE_INFO WHERE Complaint_ID = $complaint_id ORDER BY Case_ID DESC LIMIT 1");
    if ($cidRes && $cidRes->num_rows > 0) {
        $case_id_for_complaint = (int)($cidRes->fetch_assoc()['Case_ID'] ?? 0);
    }
}

// ✅ Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // -------------------- UPDATE COMPLAINT & RESPONDENTS --------------------
    if (isset($_POST['update_complaint']) && !$is_case && !$is_rejected && !$is_dismissed) {
        // Helper: resolve resident id by full name (simple form)
        $resolveResidentId = function($conn, $full_name) {
            $full_name = trim(preg_replace('/\s+/', ' ', (string)$full_name));
            if ($full_name === '') return null;
            // Treat last token as Last_Name, everything before as First_Name (supports multi-word first names)
            $parts = preg_split('/\s+/', $full_name);
            if (count($parts) > 1) {
                $last = array_pop($parts);
                $first = trim(implode(' ', $parts));
            } else {
                $first = $parts[0] ?? '';
                $last = '';
            }
            if ($first !== '' && $last !== '') {
                $q = $conn->prepare("SELECT Resident_ID FROM resident_info WHERE First_Name = ? AND Last_Name = ? LIMIT 1");
                if ($q) {
                    $q->bind_param('ss', $first, $last);
                    $q->execute();
                    $r = bpamis_stmt_get_result($q);
                    if ($r && $row = $r->fetch_assoc()) { $id = (int)$row['Resident_ID']; $q->close(); return $id; }
                    $q->close();
                }
            }
            // Fallback: LIKE on concatenated name
            $pat = '%' . str_replace(' ', '%', $full_name) . '%';
            $q2 = $conn->prepare("SELECT Resident_ID FROM resident_info WHERE CONCAT(First_Name, ' ', COALESCE(Middle_Name,''), ' ', Last_Name) LIKE ? OR CONCAT(First_Name,' ',Last_Name) LIKE ? LIMIT 1");
            if ($q2) {
                $q2->bind_param('ss', $pat, $pat);
                $q2->execute();
                $r2 = bpamis_stmt_get_result($q2);
                if ($r2 && $row2 = $r2->fetch_assoc()) { $id = (int)$row2['Resident_ID']; $q2->close(); return $id; }
                $q2->close();
            }
            return null;
        };

        // Update complaint title/details if provided
        $newTitle = trim($_POST['complaint_title'] ?? '');
        $newDetails = trim($_POST['complaint_details'] ?? '');
        if ($newTitle !== '' || $newDetails !== '') {
            // Save current complaint details into history before updating
            $curDetails = $complaint['Complaint_Details'] ?? '';
            $curTitle = $complaint['Complaint_Title'] ?? '';
            // compute next version number
            $vstmt = $conn->prepare("SELECT COALESCE(MAX(version_number),0)+1 AS next_ver FROM complaint_info_history WHERE complaint_id = ?");
            $nextVer = 1;
            if ($vstmt) {
                $vstmt->bind_param('i', $complaint_id);
                $vstmt->execute();
                $vstmt->bind_result($nv);
                if ($vstmt->fetch() && !empty($nv)) $nextVer = (int)$nv;
                $vstmt->close();
            }
            $inst = $conn->prepare("INSERT INTO complaint_info_history (complaint_id, version_number, details, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)");
            if ($inst) {
                $now = date('Y-m-d H:i:s');
                $inst->bind_param('iisss', $complaint_id, $nextVer, $curDetails, $current_user, $now);
                $inst->execute();
                $inst->close();
            }

            $u = $conn->prepare("UPDATE COMPLAINT_INFO SET Complaint_Title = ?, Complaint_Details = ? WHERE Complaint_ID = ?");
            if ($u) { $u->bind_param('ssi', $newTitle, $newDetails, $complaint_id); $u->execute(); $u->close(); }
            else { $conn->query("UPDATE COMPLAINT_INFO SET Complaint_Title='".$conn->real_escape_string($newTitle)."', Complaint_Details='".$conn->real_escape_string($newDetails)."' WHERE Complaint_ID=$complaint_id"); }
        }

        // Parse respondent input (support Tagify JSON or respondents[] array or comma list)
        $rawRespondents = $_POST['respondent_name'] ?? null;
        $respondentList = [];
        if (is_string($rawRespondents) && strlen(trim($rawRespondents))>0) {
            $s = trim($rawRespondents);
            if (str_starts_with($s, '[')) {
                $dec = json_decode($s, true);
                if (is_array($dec)) {
                    foreach ($dec as $it) { if (!empty($it['value'])) $respondentList[] = trim($it['value']); }
                }
            } else {
                // comma separated
                $parts = preg_split('/\s*,\s*/', $s);
                foreach ($parts as $p) { if ($p !== '') $respondentList[] = trim($p); }
            }
        } elseif (!empty($_POST['respondents']) && is_array($_POST['respondents'])) {
            foreach ($_POST['respondents'] as $r) { if (trim((string)$r) !== '') $respondentList[] = trim((string)$r); }
        }

        // Normalize unique
        $unique = [];
        $seen = [];
        foreach ($respondentList as $r) { $k = mb_strtolower(preg_replace('/\s+/', ' ', $r)); if ($k !== '' && !isset($seen[$k])) { $seen[$k]=true; $unique[] = $r; } }
        $respondentList = $unique;

        // Persist respondents: clear existing then set first as primary Respondent_ID and rest into COMPLAINT_RESPONDENTS
        $conn->query("DELETE FROM COMPLAINT_RESPONDENTS WHERE Complaint_ID=$complaint_id");
        $primaryRid = null;
        if (!empty($respondentList)) {
            // Resolve resident ids
            $resolved = [];
            foreach ($respondentList as $i => $name) {
                $rid = $resolveResidentId($conn, $name);
                if ($rid) $resolved[] = $rid;
            }
            if (!empty($resolved)) {
                $primaryRid = (int)$resolved[0];
                // Update COMPLAINT_INFO Respondent_ID if column exists
                $colChk = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Respondent_ID'");
                if ($colChk && $colChk->num_rows > 0) {
                    $upd = $conn->prepare("UPDATE COMPLAINT_INFO SET Respondent_ID = ? WHERE Complaint_ID = ?");
                    if ($upd) { $upd->bind_param('ii', $primaryRid, $complaint_id); $upd->execute(); $upd->close(); }
                }
                // Insert additional respondents
                if (count($resolved) > 1) {
                    $ins = $conn->prepare('INSERT INTO COMPLAINT_RESPONDENTS (Complaint_ID, Respondent_ID) VALUES (?, ?)');
                    if ($ins) {
                        for ($j=1;$j<count($resolved);$j++) { $rr = (int)$resolved[$j]; if ($rr>0) { $ins->bind_param('ii', $complaint_id, $rr); $ins->execute(); } }
                        $ins->close();
                    }
                }
            }
        }

        header("Location: view_complaint_details.php?id=$complaint_id");
        exit;
    }
    // -------------------- SEND NOTICE TO COMPLAINANT AND RESPONDENTS --------------------
    if (isset($_POST['send_notice']) ) {
        // Do not send notices for dismissed complaints — make dismissed view-only
        if (!empty($is_dismissed)) {
            header("Location: view_complaint_details.php?id=$complaint_id");
            exit;
        }
        $conn->begin_transaction();
        try {
            // Notify complainant (resident or external)
            $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id");
            if ($cr && $cr->num_rows > 0) {
                $row = $cr->fetch_assoc();
                $rid = $row['Resident_ID'];
                $eid = $row['External_Complainant_ID'];
                $title = 'Please visit the barangay regarding your complaint';
                $msg = "Please visit the barangay office regarding your complaint ({$complaint_ref}). This complaint requires an in-person visit.";
                $now = date('Y-m-d H:i:s');
                $type = 'Notice';
                if (!empty($rid)) {
                    $stmt4 = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                    if ($stmt4) { $stmt4->bind_param('issss', $rid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                } elseif (!empty($eid)) {
                    $stmt4 = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                    if ($stmt4) { $stmt4->bind_param('issss', $eid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                }
            }

            // Notify respondents (residents listed in COMPLAINT_RESPONDENTS)
            $rs = $conn->query("SELECT Respondent_ID FROM COMPLAINT_RESPONDENTS WHERE Complaint_ID = $complaint_id");
            if ($rs && $rs->num_rows > 0) {
                while ($r = $rs->fetch_assoc()) {
                    $respId = (int)$r['Respondent_ID'];
                    if ($respId > 0) {
                        $titleR = 'Notice: Complaint Filed Against You';
                        $msgR = "A complaint ({$complaint_ref}) has been filed against you. Please visit the barangay office for mediation.";
                        $nowR = date('Y-m-d H:i:s');
                        $typeR = 'Notice';
                        $stmtR = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                        if ($stmtR) { $stmtR->bind_param('issss', $respId, $titleR, $msgR, $typeR, $nowR); $stmtR->execute(); $stmtR->close(); }
                    }
                }
            }

            $conn->commit();
            header("Location: view_complaint_details.php?id=$complaint_id&notice=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to send notice: ' . $e->getMessage();
        }
    }

    // -------------------- UPLOAD ATTACHMENTS --------------------
    if (isset($_POST['upload_attachment']) && !$is_case && !$is_rejected && !$is_dismissed) {
        $saved = [];
        $uploadDir = __DIR__ . '/../uploads/complaints/' . $complaint_id . '/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

        if (!empty($_FILES['attachments_upload']['name']) && is_array($_FILES['attachments_upload']['name'])) {
            foreach ($_FILES['attachments_upload']['name'] as $i => $nm) {
                if ($_FILES['attachments_upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $size = (int)$_FILES['attachments_upload']['size'][$i];
                if ($size > 20*1024*1024) continue; // skip >20MB
                $safeName = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/','_', $nm);
                if (move_uploaded_file($_FILES['attachments_upload']['tmp_name'][$i], $uploadDir.$safeName)) {
                    $rel = 'uploads/complaints/' . $complaint_id . '/' . $safeName;
                    $saved[] = $rel;
                }
            }
        }

        if (!empty($saved)) {
            // Append to Attachment_Path if column exists
            $hasAttachmentColumn = false;
            if ($resc = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Attachment_Path'")) { if ($resc->num_rows>0) $hasAttachmentColumn=true; $resc->close(); }
            if ($hasAttachmentColumn) {
                $cr = $conn->query("SELECT Attachment_Path FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id LIMIT 1");
                $existing = '';
                if ($cr && $cr->num_rows>0) { $existing = $cr->fetch_assoc()['Attachment_Path'] ?? ''; }
                $parts = array_filter(array_map('trim', explode(';', $existing)), fn($p)=>$p!=='');
                $parts = array_merge($parts, $saved);
                $newField = implode(';', $parts);
                $u = $conn->prepare("UPDATE COMPLAINT_INFO SET Attachment_Path = ? WHERE Complaint_ID = ?");
                if ($u) { $u->bind_param('si', $newField, $complaint_id); $u->execute(); $u->close(); }
            }

            // Redirect back to show updated attachments
            header("Location: view_complaint_details.php?id=$complaint_id&uploaded=1");
            exit;
        }

        $curDetails = $complaint['Complaint_Details'] ?? '';
        $curTitle = $complaint['Complaint_Title'] ?? '';
        $inst = $conn->prepare("INSERT INTO complaint_info_history (complaint_id, version_number, details, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)");
        if ($inst) {
            $now = date('Y-m-d H:i:s');
            $inst->bind_param('iisss', $complaint_id, $nextVer, $curDetails, $current_user, $now);
            $inst->execute();
            $inst->close();
        }

        // Now update main complaint
        $u = $conn->prepare("UPDATE COMPLAINT_INFO SET Complaint_Title = ?, Complaint_Details = ? WHERE Complaint_ID = ?");
        if ($u) { $u->bind_param('ssi', $title, $details, $complaint_id); $u->execute(); $u->close(); }
        // clear respondents (existing behavior)
        $conn->query("DELETE FROM COMPLAINT_RESPONDENTS WHERE Complaint_ID=$complaint_id");
        // after successful update redirect to view mode (no ?edit)
        header("Location: view_complaint_details.php?id=$complaint_id");
        exit;
    }



     // -------------------- VALIDATE AS CASE --------------------
    if (isset($_POST['validate_case']) && !$is_case && !$is_rejected && !$is_dismissed) {
        $decision = $_POST['validate_decision'] ?? '';
        if ($decision !== 'yes') {
            $error = 'Please select Yes to validate this complaint as a case.';
        } else {
            $case_type = trim($_POST['case_type'] ?? '');
            // allowed types include persisted ones and 'Others' option
            $allowed_types = $caseTypes;
            if (!in_array('Others', $allowed_types, true)) $allowed_types[] = 'Others';

            // If secretary chose Others, get typed value and persist it
            if ($case_type === 'Others') {
                $other_case_type = trim($_POST['other_case_type'] ?? '');
                if ($other_case_type === '') {
                    $error = 'Please enter the case type when selecting Others.';
                } else {
                    // ensure CASE_TYPES table exists and insert the new type (IGNORE duplicate)
                    $conn->query("
                        CREATE TABLE IF NOT EXISTS CASE_TYPES (
                            Type_ID INT AUTO_INCREMENT PRIMARY KEY,
                            Case_Type VARCHAR(191) NOT NULL UNIQUE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    $ins = $conn->prepare("INSERT IGNORE INTO CASE_TYPES (Case_Type) VALUES (?)");
                    if ($ins) {
                        $ins->bind_param('s', $other_case_type);
                        $ins->execute();
                        $ins->close();
                    }
                    // now use the typed value as case_type for processing
                    $case_type = $other_case_type;
                }
            }

            if (empty($error)) {
                if (!in_array($case_type, $allowed_types, true) && $case_type !== $other_case_type) {
                    // allow the typed value (which was just persisted) as valid
                    $error = 'Please choose a valid case type (Record Purposes, or Others).';
                } else {
                    // Ensure CASE_INFO has case_number and case_original_id columns
                    $col1 = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'case_number'");
                    if (!($col1 && $col1->num_rows > 0)) {
                        $conn->query("ALTER TABLE CASE_INFO ADD COLUMN case_number INT UNSIGNED NOT NULL DEFAULT 0");
                    }
                    if (isset($col1) && $col1) $col1->close();
                    $col2 = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'case_original_id'");
                    if (!($col2 && $col2->num_rows > 0)) {
                        $conn->query("ALTER TABLE CASE_INFO ADD COLUMN case_original_id VARCHAR(64) NULL");
                    }
                    if (isset($col2) && $col2) $col2->close();

                    $date_opened = date('Y-m-d H:i:s'); // Asia/Manila
                    $conn->begin_transaction();

                    try {
                        if (strtolower($case_type) === 'record purposes') {
                            // For Record Purposes: do NOT insert into CASE_INFO.
                            // Only create a blotter_info row and update complaint status to a blotter state.

                            // Build blotter description from complaint details/title
                            $raw_details = $complaint['Complaint_Details'] ?? ($complaint['Complaint_Title'] ?? '');
                            $raw_details = trim((string)$raw_details);
                            $raw_details = preg_replace('/\s+/u', ' ', $raw_details);
                            $blotter_description = $raw_details !== '' ? $raw_details : ('Record Purposes - ' . ($complaint['Complaint_Title'] ?? 'Complaint'));
                            $blotter_description = strip_tags($blotter_description);
                            $max_len = 1000;
                            if (mb_strlen($blotter_description) > $max_len) {
                                $blotter_description = mb_substr($blotter_description, 0, $max_len) . '...';
                            }

                            // Determine reporter name (prefer resident, fall back to external)
                            $reported_by = '';
                            $resName = trim((($complaint['Res_First_Name'] ?? '') . ' ' . ($complaint['Res_Last_Name'] ?? '')));
                            $extName = trim((($complaint['Ext_First_Name'] ?? '') . ' ' . ($complaint['Ext_Last_Name'] ?? '')));
                            if ($resName !== '') $reported_by = $resName;
                            elseif ($extName !== '') $reported_by = $extName;

                            $date_reported = $date_opened;

                            // Insert blotter row (no Case_ID)
                            $stmtBl = $conn->prepare("INSERT INTO blotter_info (Blotter_Description, Reported_By, Date_Reported, Complaint_ID) VALUES (?, ?, ?, ?)");
                            if ($stmtBl) {
                                $stmtBl->bind_param('sssi', $blotter_description, $reported_by, $date_reported, $complaint_id);
                                $stmtBl->execute();
                                $stmtBl->close();
                            }

                            // Update complaint status to indicate blotter conversion
                            $newStatus = 'BLOTTER_CASE';
                            $tchk3 = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'case_type'");
                            if ($tchk3 && $tchk3->num_rows > 0) {
                                $stmtU = $conn->prepare("UPDATE COMPLAINT_INFO SET Status = ?, case_type = ? WHERE Complaint_ID = ?");
                                $ctVal = 'record purposes';
                                $stmtU->bind_param('ssi', $newStatus, $ctVal, $complaint_id);
                            } else {
                                $stmtU = $conn->prepare("UPDATE COMPLAINT_INFO SET Status = ? WHERE Complaint_ID = ?");
                                $stmtU->bind_param('si', $newStatus, $complaint_id);
                            }
                            if (isset($stmtU) && $stmtU) { $stmtU->execute(); $stmtU->close(); }

                            // Notify complainant
                            $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id");
                            if ($cr && $cr->num_rows > 0) {
                                $row = $cr->fetch_assoc();
                                $rid = $row['Resident_ID'];
                                $eid = $row['External_Complainant_ID'];
                                $title = 'Complaint Converted to Record Purposes';
                                $msg = "Your complaint with {$complaint_ref} has been recorded as a blotter entry.";
                                $now = date('Y-m-d H:i:s');
                                $type = 'Blotter';
                                if (!empty($rid)) {
                                    $stmt4 = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                                    if ($stmt4) { $stmt4->bind_param('issss', $rid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                                } elseif (!empty($eid)) {
                                    $stmt4 = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                                    if ($stmt4) { $stmt4->bind_param('issss', $eid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                                }
                            }

                            $conn->commit();
                            header("Location: view_complaints.php?success=record_purposes");
                            exit;
                        } else {
                            // For endorsed or any other case type: create single case entry and set complaint status
                            $hasCaseTypeCol = false;
                            $tchkCase = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'Case_Type'");
                            if ($tchkCase && $tchkCase->num_rows > 0) $hasCaseTypeCol = true;

                            // generate next case number for this year
                            $year = (int)date('Y', strtotime($date_opened));
                            $month = (int)date('n', strtotime($date_opened));
                            $stmtMax = $conn->prepare("SELECT MAX(case_number) AS maxn FROM CASE_INFO WHERE YEAR(Date_Opened) = ? FOR UPDATE");
                            $stmtMax->bind_param('i', $year);
                            $stmtMax->execute();
                            $resMax = bpamis_stmt_get_result($stmtMax);
                            $rowMax = $resMax->fetch_assoc();
                            $last = (int)($rowMax['maxn'] ?? 0);
                            $stmtMax->close();

                            $case_number = $last + 1;
                            $case_original_id = $case_number . '-' . $month . '-' . $year;

                            if ($hasCaseTypeCol) {
                                $stmtCE = $conn->prepare("INSERT INTO CASE_INFO (Complaint_ID, Case_Status, Date_Opened, Case_Type, case_number, case_original_id) VALUES (?, 'Mediation', ?, ?, ?, ?)");
                                $ctE = $case_type;
                                $stmtCE->bind_param('issis', $complaint_id, $date_opened, $ctE, $case_number, $case_original_id);
                            } else {
                                // if no Case_Type column, still insert but encode type in status; use 'Mediation'
                                $statusLabel = (strtolower($case_type) === 'endorsed') ? 'Endorsed - Mediation' : 'Mediation';
                                $stmtCE = $conn->prepare("INSERT INTO CASE_INFO (Complaint_ID, Case_Status, Date_Opened, case_number, case_original_id) VALUES (?, ?, ?, ?, ?)");
                                $stmtCE->bind_param('issis', $complaint_id, $statusLabel, $date_opened, $case_number, $case_original_id);
                            }
                            $stmtCE->execute();
                            $case_id = $conn->insert_id;
                            $stmtCE->close();

                            // Create mediation_info table if missing and insert a mediation row for this newly created case
                            try {
                                $tbl = $conn->query("SHOW TABLES LIKE 'mediation_info'");
                                if (!($tbl && $tbl->num_rows > 0)) {
                                    $conn->query("CREATE TABLE IF NOT EXISTS mediation_info (
                                        Mediation_ID INT AUTO_INCREMENT PRIMARY KEY,
                                        Case_ID INT DEFAULT NULL,
                                        Mediator_Name VARCHAR(100) DEFAULT NULL,
                                        Mediation_Date DATE DEFAULT NULL,
                                        Mediation_Result TEXT DEFAULT NULL,
                                        Deadline DATE DEFAULT NULL
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                                }
                            } catch (Throwable $e) {
                                // ignore failures creating the table to avoid blocking case creation
                            }

                            // Insert mediation record if one doesn't already exist for this case
                            $medDate = date('Y-m-d', strtotime($date_opened));
                            $medDeadline = date('Y-m-d', strtotime($medDate . ' +15 days'));
                            $chk = $conn->query("SELECT 1 FROM mediation_info WHERE Case_ID = $case_id LIMIT 1");
                            if (!($chk && $chk->num_rows > 0)) {
                                $insM = $conn->prepare("INSERT INTO mediation_info (Case_ID, Mediation_Date, Deadline) VALUES (?, ?, ?)");
                                if ($insM) {
                                    $insM->bind_param('iss', $case_id, $medDate, $medDeadline);
                                    $insM->execute();
                                    $insM->close();
                                } else {
                                    // fallback to direct query if prepare not available
                                    $conn->query("INSERT INTO mediation_info (Case_ID, Mediation_Date, Deadline) VALUES (" . intval($case_id) . ", '" . $conn->real_escape_string($medDate) . "', '" . $conn->real_escape_string($medDeadline) . "')");
                                }
                            }

                            $newStatus = 'IN CASE';
                            $tchk3 = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'case_type'");
                            if ($tchk3 && $tchk3->num_rows > 0) {
                                $stmtU = $conn->prepare("UPDATE COMPLAINT_INFO SET Status = ?, case_type = ? WHERE Complaint_ID = ?");
                                $ctVal = $case_type;
                                $stmtU->bind_param('ssi', $newStatus, $ctVal, $complaint_id);
                            } else {
                                $stmtU = $conn->prepare("UPDATE COMPLAINT_INFO SET Status = ? WHERE Complaint_ID = ?");
                                $stmtU->bind_param('si', $newStatus, $complaint_id);
                            }
                            $stmtU->execute();
                            $stmtU->close();

                            // Notification
                            $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id");
                            if ($cr && $cr->num_rows > 0) {
                                $row = $cr->fetch_assoc();
                                $rid = $row['Resident_ID'];
                                $eid = $row['External_Complainant_ID'];
                                $title = 'Complaint Converted to Case';
                                $msg = "Your complaint with {$complaint_ref} has been validated as a $case_type case.";
                                $now = date('Y-m-d H:i:s');
                                $type = 'Case';
                                if (!empty($rid)) {
                                    $stmt4 = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                                    $stmt4->bind_param('issss', $rid, $title, $msg, $type, $now);
                                    $stmt4->execute();
                                    $stmt4->close();
                                } elseif (!empty($eid)) {
                                    $stmt4 = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                                    $stmt4->bind_param('issss', $eid, $title, $msg, $type, $now);
                                    $stmt4->execute();
                                    $stmt4->close();
                                }
                            }

                            $conn->commit();
                            header("Location: view_complaints.php?success=validated");
                            exit;
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Server error while converting the complaint: " . $e->getMessage();
                    }
                }
            }
        }
    }

    // -------------------- REJECT COMPLAINT --------------------
    if (isset($_POST['reject_complaint']) && !$is_case) {
        $conn->query("UPDATE COMPLAINT_INFO SET Status='Rejected' WHERE Complaint_ID=$complaint_id");
        $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID=$complaint_id");
        if ($cr && $cr->num_rows > 0) {
            $row = $cr->fetch_assoc();
            $rid = $row['Resident_ID'];
            $eid = $row['External_Complainant_ID'];
            $title = 'Complaint Rejected';
            $msg = "Your complaint with {$complaint_ref} has been rejected after evaluation.";
            $now = date('Y-m-d H:i:s'); // ✅ Asia/Manila
            if (!empty($rid)) {
                $conn->query("INSERT INTO notifications (resident_id, title, message, is_read, created_at) VALUES ($rid, '$title', '$msg', 0, '$now')");
            } elseif (!empty($eid)) {
                $conn->query("INSERT INTO notifications (external_complaint_id, title, message, is_read, created_at) VALUES ($eid, '$title', '$msg', 0, '$now')");
            }
        }
        header("Location: view_complaints.php?success=rejected");
        exit;
    }

    // -------------------- DISMISS COMPLAINT --------------------
    if (isset($_POST['dismiss_complaint']) && !$is_case) {
        $reason = trim($_POST['dismiss_reason'] ?? '');
        if ($reason === '') {
            $error = 'Dismissal reason is required.';
        } else {
            // update status to Dismissed
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE COMPLAINT_INFO SET Status='Dismissed' WHERE Complaint_ID=$complaint_id");

                // Prefer storing dismissal reason into `dismissal_reason` column if present; fall back to legacy `Dismiss_Reason`.
                $tchk_new = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'dismissal_reason'");
                if ($tchk_new && $tchk_new->num_rows > 0) {
                    $u = $conn->prepare("UPDATE COMPLAINT_INFO SET dismissal_reason = ? WHERE Complaint_ID = ?");
                    if ($u) { $u->bind_param('si', $reason, $complaint_id); $u->execute(); $u->close(); }
                } else {
                    $tchk_old = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Dismiss_Reason'");
                    if ($tchk_old && $tchk_old->num_rows > 0) {
                        $u = $conn->prepare("UPDATE COMPLAINT_INFO SET Dismiss_Reason = ? WHERE Complaint_ID = ?");
                        if ($u) { $u->bind_param('si', $reason, $complaint_id); $u->execute(); $u->close(); }
                    } else {
                        // Neither column exists - try to add the new preferred column and store reason
                        $conn->query("ALTER TABLE COMPLAINT_INFO ADD COLUMN dismissal_reason TEXT NULL");
                        $u = $conn->prepare("UPDATE COMPLAINT_INFO SET dismissal_reason = ? WHERE Complaint_ID = ?");
                        if ($u) { $u->bind_param('si', $reason, $complaint_id); $u->execute(); $u->close(); }
                    }
                }

                // Notify complainant with reason
                $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID=$complaint_id");
                if ($cr && $cr->num_rows > 0) {
                    $row = $cr->fetch_assoc();
                    $rid = $row['Resident_ID'];
                    $eid = $row['External_Complainant_ID'];
                    $title = 'Complaint Dismissed';
                    $msg = "Your complaint with {$complaint_ref} has been dismissed. Reason: " . $reason;
                    $now = date('Y-m-d H:i:s'); // Asia/Manila
                    if (!empty($rid)) {
                        $stmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)");
                        if ($stmt) { $stmt->bind_param('isss', $rid, $title, $msg, $now); $stmt->execute(); $stmt->close(); }
                    } elseif (!empty($eid)) {
                        $stmt = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)");
                        if ($stmt) { $stmt->bind_param('isss', $eid, $title, $msg, $now); $stmt->execute(); $stmt->close(); }
                    }
                }

                $conn->commit();
                header("Location: view_complaints.php?success=dismissed");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Server error while dismissing the complaint: " . $e->getMessage();
            }
        }
    }
    
    // -------------------- UPLOAD SALAYSAY (HARD-COPY) --------------------
    if (isset($_POST['upload_salaysay']) && $editing && !$is_case && !$is_rejected && !$is_dismissed) {
        $fileArr = null;
        if (!empty($_FILES['salaysay']) && isset($_FILES['salaysay']['name'])) {
            $fileArr = $_FILES['salaysay'];
        } elseif (!empty($_FILES['salaysay_file']) && isset($_FILES['salaysay_file']['name'])) {
            $fileArr = $_FILES['salaysay_file'];
        }

        $ok = false;
        $savedRel = '';
        if ($fileArr && isset($fileArr['error']) && $fileArr['error'] === UPLOAD_ERR_OK) {
            // Only allow JPG/PNG for Salaysay
            $allowed = ['jpg','jpeg','png'];
            $ext = strtolower(pathinfo($fileArr['name'], PATHINFO_EXTENSION));
            $size = (int)$fileArr['size'];
            if (in_array($ext, $allowed, true) && $size <= 20*1024*1024) {
                $baseUpload = __DIR__ . '/../uploads/complaints/' . $complaint_id . '/';
                if (!is_dir($baseUpload)) { @mkdir($baseUpload, 0777, true); }
                $fname = 'salaysay_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest  = $baseUpload . $fname;
                if (move_uploaded_file($fileArr['tmp_name'], $dest)) {
                    // Remove previous file if any
                    if (!empty($complaint['Salaysay_Path'])) {
                        $oldAbs = realpath(__DIR__ . '/..' . '/' . ltrim($complaint['Salaysay_Path'], '/'));
                        if ($oldAbs && is_file($oldAbs)) { @unlink($oldAbs); }
                    }
                    $savedRel = 'uploads/complaints/' . $complaint_id . '/' . $fname;
                    $ok = true;
                }
            }
        }

        if ($ok && $savedRel !== '') {
            // Ensure column exists
            $colChk = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Salaysay_Path'");
            if (!$colChk || $colChk->num_rows === 0) {
                $conn->query("ALTER TABLE COMPLAINT_INFO ADD COLUMN Salaysay_Path VARCHAR(255) NULL");
            }
            if ($colChk) { $colChk->close(); }

            $stmt = $conn->prepare("UPDATE COMPLAINT_INFO SET Salaysay_Path = ? WHERE Complaint_ID = ?");
            if ($stmt) {
                $stmt->bind_param('si', $savedRel, $complaint_id);
                $stmt->execute();
                $stmt->close();
            }

            header("Location: view_complaint_details.php?id={$complaint_id}&edit=1&salaysay=1");
            exit;
        } else {
            header("Location: view_complaint_details.php?id={$complaint_id}&edit=1&salaysay=0");
            exit;
        }
    }

    // -------------------- REVERT VERSION --------------------
    if (isset($_POST['revert_version']) && $editing && !$is_case && !$is_rejected && !$is_dismissed) {
        $hid = intval($_POST['history_id'] ?? 0);
        if ($hid <= 0) {
            $error = 'Invalid history item.';
        } else {
            $conn->begin_transaction();
            try {
                $rstmt = $conn->prepare("SELECT version_number, details, updated_by, updated_at FROM complaint_info_history WHERE id = ? AND complaint_id = ? LIMIT 1");
                if (!$rstmt) throw new Exception('Invalid request.');
                $rstmt->bind_param('ii', $hid, $complaint_id);
                $rstmt->execute();
                $res = bpamis_stmt_get_result($rstmt);
                if (!$res || $res->num_rows === 0) throw new Exception('History item not found.');
                $row = $res->fetch_assoc();
                $oldDetails = $row['details'];
                $rstmt->close();
                // push current into history as new version
                $vstmt = $conn->prepare("SELECT COALESCE(MAX(version_number),0)+1 AS next_ver FROM complaint_info_history WHERE complaint_id = ?");
                $nextVer = 1;
                if ($vstmt) { $vstmt->bind_param('i', $complaint_id); $vstmt->execute(); $vstmt->bind_result($nv); $vstmt->fetch(); if(!empty($nv)) $nextVer = (int)$nv; $vstmt->close(); }
                $curDetails = $complaint['Complaint_Details'] ?? '';
                $inst = $conn->prepare("INSERT INTO complaint_info_history (complaint_id, version_number, details, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)");
                if ($inst) {
                    $now = date('Y-m-d H:i:s');
                    $inst->bind_param('iisss', $complaint_id, $nextVer, $curDetails, $current_user, $now);
                    $inst->execute();
                    $inst->close();
                }
                // update main complaint with chosen version
                $u = $conn->prepare("UPDATE COMPLAINT_INFO SET Complaint_Details = ? WHERE Complaint_ID = ?");
                if (!$u) throw new Exception('Failed to prepare update.');
                $u->bind_param('si', $oldDetails, $complaint_id);
                $u->execute();
                $u->close();
                $conn->commit();
                header("Location: view_complaint_details.php?id=$complaint_id&reverted=1");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to revert: ' . $e->getMessage();
            }
        }
    }
}

// ✅ Respondent display list
$respondents = [];
if (!empty($complaint['Respondent_ID'])) {
    $mr = $conn->query("SELECT First_Name, Last_Name FROM RESIDENT_INFO WHERE Resident_ID={$complaint['Respondent_ID']}");
    if ($mr && $mr->num_rows > 0) {
        $r = $mr->fetch_assoc();
        $respondents[] = $r['First_Name'] . ' ' . $r['Last_Name'];
    }
}
$ar = $conn->query("SELECT r.First_Name, r.Last_Name FROM COMPLAINT_RESPONDENTS cr JOIN RESIDENT_INFO r ON cr.Respondent_ID=r.Resident_ID WHERE cr.Complaint_ID=$complaint_id");
if ($ar && $ar->num_rows > 0) {
    while ($r = $ar->fetch_assoc()) {
        $respondents[] = $r['First_Name'] . ' ' . $r['Last_Name'];
    }
}
$respondent_names = $respondents ? implode(', ', $respondents) : 'N/A';

// Build resident whitelist for Tagify (used for autocomplete suggestions)
// Build whitelist using only First + Last name (ignore middle names) to avoid duplicate variants
$__names = [];
$__r = $conn->query("SELECT First_Name, Last_Name FROM resident_info ORDER BY Last_Name, First_Name LIMIT 4000");
if ($__r && $__r->num_rows > 0) {
    while ($rn = $__r->fetch_assoc()) {
        $first = trim($rn['First_Name'] ?? '');
        $last = trim($rn['Last_Name'] ?? '');
        if ($first === '' && $last === '') continue;
        $full = preg_replace('/\s+/', ' ', trim($first . ' ' . $last));
        $__names[] = $full;
    }
}
// Unique case-insensitive
$unique = [];
foreach ($__names as $n) {
    $k = mb_strtolower($n);
    if (!isset($unique[$k])) $unique[$k] = $n;
}
$resident_whitelist_json = json_encode(array_values($unique), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ✅ Determine complainant name
$complainant_name = !empty($complaint['Res_First_Name'])
    ? $complaint['Res_First_Name'] . ' ' . $complaint['Res_Last_Name']
    : (!empty($complaint['Ext_First_Name'])
        ? $complaint['Ext_First_Name'] . ' ' . $complaint['Ext_Last_Name']
        : 'Unknown');

// Determine complainant DOB and whether they are a minor (under 18)
$complainant_is_minor = false;
$complainant_dob = '';
$complainant_dob_iso = '';
// Prefer Resident_ID if present, otherwise External_Complainant_ID
if (!empty($complaint['Resident_ID'])) {
    $rid = (int)$complaint['Resident_ID'];
    if ($s = $conn->prepare("SELECT COALESCE(Birthdate, birthdate,  '') AS Birthdate FROM resident_info WHERE Resident_ID = ? LIMIT 1")){
        $s->bind_param('i', $rid);
        $s->execute();
        $rres = bpamis_stmt_get_result($s);
        if ($rres && $rw = $rres->fetch_assoc()) { $complainant_dob = trim((string)($rw['Birthdate'] ?? '')); }
        $s->close();
    }
} elseif (!empty($complaint['External_Complainant_ID'])) {
    $eid = (int)$complaint['External_Complainant_ID'];
    if ($s = $conn->prepare("SELECT COALESCE(Birthdate, birthdate,  '') AS Birthdate FROM external_complainant WHERE External_Complaint_ID = ? LIMIT 1")){
        $s->bind_param('i', $eid);
        $s->execute();
        $rres = bpamis_stmt_get_result($s);
        if ($rres && $rw = $rres->fetch_assoc()) { $complainant_dob = trim((string)($rw['Birthdate'] ?? '')); }
        $s->close();
    }
}
// If DB returned empty DOB, try a strict fallback selecting explicit `Birthdate` column (some installs use that name)
if ($complainant_dob === '') {
    if (!empty($complaint['Resident_ID'])) {
        $rid = (int)$complaint['Resident_ID'];
        if ($q = $conn->prepare("SELECT Birthdate FROM resident_info WHERE Resident_ID = ? LIMIT 1")) {
            $q->bind_param('i', $rid);
            $q->execute();
            $rr = bpamis_stmt_get_result($q);
            if ($rr && $rowf = $rr->fetch_assoc()) {
                $complainant_dob = trim((string)($rowf['Birthdate'] ?? $rowf['birthdate'] ?? ''));
            }
            $q->close();
        }
    } elseif (!empty($complaint['External_Complainant_ID'])) {
        $eid = (int)$complaint['External_Complainant_ID'];
        if ($q = $conn->prepare("SELECT Birthdate FROM external_complainant WHERE External_Complaint_ID = ? LIMIT 1")) {
            $q->bind_param('i', $eid);
            $q->execute();
            $rr = bpamis_stmt_get_result($q);
            if ($rr && $rowf = $rr->fetch_assoc()) {
                $complainant_dob = trim((string)($rowf['Birthdate'] ?? $rowf['birthdate'] ?? ''));
            }
            $q->close();
        }
    }
}
// Additional fallback: sometimes the complaint row itself stores an external DOB under a different column
if ($complainant_dob === '') {
    foreach ($complaint as $ck => $cv) {
        $lk = strtolower($ck);
        if (strpos($lk, 'birth') !== false || strpos($lk, 'dob') !== false) {
            if (!empty($cv) && is_string($cv)) { $complainant_dob = trim($cv); break; }
        }
    }
}

// Fallback: if still empty, try to find the external_complainant by name (use Ext_First_Name/Ext_Last_Name)
if ($complainant_dob === '' && !empty($complaint['Ext_First_Name'])) {
    $ext_fname = trim((string)$complaint['Ext_First_Name']);
    $ext_lname = trim((string)($complaint['Ext_Last_Name'] ?? ''));
    if ($ext_fname !== '') {
        // 1) exact match on First_Name + Last_Name
        if ($q2 = $conn->prepare("SELECT External_Complaint_ID, Birthdate FROM external_complainant WHERE First_Name = ? AND Last_Name = ? LIMIT 1")) {
            $q2->bind_param('ss', $ext_fname, $ext_lname);
            $q2->execute();
            $r2 = bpamis_stmt_get_result($q2);
            if ($r2 && $row2 = $r2->fetch_assoc()) {
                $complainant_dob = trim((string)($row2['Birthdate'] ?? ''));
                if (empty($complaint['External_Complainant_ID']) && !empty($row2['External_Complaint_ID'])) {
                    $complaint['External_Complainant_ID'] = $row2['External_Complaint_ID'];
                }
            }
            $q2->close();
        }

        // 2) try CONCAT(First_Name, ' ', Last_Name) LIKE '%first last%'
        if ($complainant_dob === '') {
            $likeName = $ext_fname . ($ext_lname !== '' ? ' ' . $ext_lname : '');
            $likeParam = '%' . $likeName . '%';
            if ($q3 = $conn->prepare("SELECT External_Complaint_ID, Birthdate FROM external_complainant WHERE CONCAT(First_Name, ' ', Last_Name) LIKE ? LIMIT 1")) {
                $q3->bind_param('s', $likeParam);
                $q3->execute();
                $r3 = bpamis_stmt_get_result($q3);
                if ($r3 && $row3 = $r3->fetch_assoc()) {
                    $complainant_dob = trim((string)($row3['Birthdate'] ?? ''));
                    if (empty($complaint['External_Complainant_ID']) && !empty($row3['External_Complaint_ID'])) {
                        $complaint['External_Complainant_ID'] = $row3['External_Complaint_ID'];
                    }
                }
                $q3->close();
            }
        }
    }
}
if ($complainant_dob !== ''){
    // Try several parsing strategies to handle different stored formats
    $parsed = false;
    // 1) native DateTime
    try{
        $dt = new DateTime($complainant_dob);
        $complainant_dob_iso = $dt->format('Y-m-d');
        $age = (int)$dt->diff(new DateTime())->y;
        $parsed = true;
    } catch (Exception $e){
        // ignore
    }
    // 2) strtotime fallback
    if (!$parsed){
        $ts = strtotime($complainant_dob);
        if ($ts !== false && $ts !== -1){
            $dt = new DateTime(); $dt->setTimestamp($ts);
            $complainant_dob_iso = $dt->format('Y-m-d');
            $age = (int)$dt->diff(new DateTime())->y;
            $parsed = true;
        }
    }
    // 3) attempt to extract YYYY-MM-DD via regex
    if (!$parsed){
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $complainant_dob, $m)){
            $y=(int)$m[1]; $mo=(int)$m[2]; $d=(int)$m[3];
            if(checkdate($mo,$d,$y)){
                $dt = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d',$y,$mo,$d));
                if($dt instanceof DateTime){ $complainant_dob_iso = $dt->format('Y-m-d'); $age = (int)$dt->diff(new DateTime())->y; $parsed = true; }
            }
        }
    }
    // If parsed and age determined, mark minor if needed
    if ($parsed && isset($age) && $age < 18) {
        $complainant_is_minor = true;
    }
}

// Optional debug: when ?dbg=1 is present, show raw DOB and computed ISO/age to help diagnose missing notices
if (isset($_GET['dbg']) && $_GET['dbg'] === '1'){
    echo "<div style=\"margin:8px 0;padding:8px;background:#fff6f6;border:1px solid #fecaca;color:#9f1239;font-size:13px;\">";
    echo "<strong>DEBUG DOB:</strong> Raw=".htmlspecialchars($complainant_dob)." &nbsp; ISO=".htmlspecialchars($complainant_dob_iso)." &nbsp; Age=".htmlspecialchars($age ?? 'unknown')." &nbsp; Minor?=".($complainant_is_minor ? 'yes' : 'no');
    echo "</div>";
}

// ✅ Handle attachments
$attachments = [];
if (array_key_exists('Attachment_Path', $complaint) && !empty($complaint['Attachment_Path'])) {
    $raw = $complaint['Attachment_Path'];
    $parts = array_filter(array_map('trim', explode(';', $raw)), fn($p) => $p !== '');
    foreach ($parts as $p) {
        $clean = str_replace('..', '', $p);
        $clean = str_replace('\\', '/', $clean);
        $clean = ltrim($clean, '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $clean)));
        $attachments[] = [
            'raw' => $clean,
            'url' => $encoded,
            'is_image' => (bool)preg_match('/\.(jpe?g|png|gif|webp)$/i', $clean),
            'is_pdf' => (bool)preg_match('/\.pdf$/i', $clean)
        ];
    }
}

// ✅ If blotter case, fetch blotter details
$status_upper = strtoupper(trim($complaint['Status'] ?? ''));
$blotterRow = null;
if ($status_upper === 'BLOTTER_CASE') {
    $brs = $conn->query("SELECT b.*, r.First_Name AS Rept_FName, r.Last_Name AS Rept_LName
                         FROM blotter_info b
                         LEFT JOIN RESIDENT_INFO r ON b.Reported_By = r.Resident_ID
                         WHERE b.Complaint_ID = $complaint_id
                         ORDER BY b.Date_Reported DESC LIMIT 1");
    if ($brs && $brs->num_rows > 0) {
        $blotterRow = $brs->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Complaint • Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}},boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'},animation:{'fade-in':'fadeIn .4s ease-out'},keyframes:{fadeIn:{'0%':{opacity:0},'100%':{opacity:1}}}}}};</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
    <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
        textarea[disabled],input[disabled]{background-color:rgba(148,163,184,.15)!important;cursor:not-allowed;}
        
        /* Mobile optimizations: compact view for small screens */
        @media (max-width: 640px) {
            /* Preserve sidebar font sizes */
            #sidebar, #sidebar *, 
            #sidebar p, #sidebar span, #sidebar label, #sidebar div,
            #sidebar button, #sidebar a, #sidebar h1, #sidebar h2, #sidebar h3, #sidebar h4,
            #sidebar input, #sidebar select, #sidebar textarea,
            #sidebar i.fas, #sidebar i.far, #sidebar i.fa {
                font-size: inherit !important;
            }
            
            /* Reduce background orbs */
            .pointer-events-none .absolute {
                width: 200px !important;
                height: 200px !important;
            }
            
            /* Main container - reduce padding */
            main {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-top: 1.5rem !important;
                padding-bottom: 3rem !important;
            }
            
            /* Section card - tighter padding */
            section.glass {
                padding: 0.75rem !important;
            }
            
            /* Header area - compact */
            header.relative {
                margin-bottom: 1rem !important;
            }
            
            header h1 {
                font-size: 1.125rem !important;
            }
            
            header .text-sm, header .text-xs {
                font-size: 0.7rem !important;
            }
            
            /* Status badges - smaller */
            header .inline-flex.items-center.gap-1 {
                font-size: 10px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Icon boxes - smaller */
            header .w-20.h-20 {
                width: 3rem !important;
                height: 3rem !important;
            }
            
            header .w-20.h-20 i {
                font-size: 1.5rem !important;
            }
            
            /* Icon container next to Complaint ID - even smaller on mobile */
            header .w-20.h-20.rounded-2xl {
                width: 2.5rem !important;
                height: 2.5rem !important;
                border-radius: 0.75rem !important;
            }
            
            header .w-20.h-20.rounded-2xl i {
                font-size: 1.25rem !important;
            }
            
            /* Kebab menu button */
            #kebabBtn {
                width: 2rem !important;
                height: 2rem !important;
            }
            
            /* Global font size for content */
            main p, main span, main label, main div:not(#sidebar):not(#sidebar *) {
                font-size: 0.7rem !important;
            }
            
            /* Headings */
            main h2:not(#sidebar h2):not(#sidebar * h2) { 
                font-size: 0.8rem !important; 
            }
            main h3:not(#sidebar h3):not(#sidebar * h3) { 
                font-size: 0.75rem !important; 
            }
            
            /* Section headers (Respondents, Complaint Type, etc.) - even smaller */
            main h2.text-sm.font-semibold.tracking-wider {
                font-size: 0.7rem !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            /* Buttons - smaller */
            main button:not(#sidebar button):not(#sidebar * button),
            main a.inline-flex:not(#sidebar a):not(#sidebar * a) {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.625rem !important;
            }
            
            /* Form inputs and textareas */
            main input:not(#sidebar input):not(#sidebar * input), 
            main select:not(#sidebar select):not(#sidebar * select), 
            main textarea:not(#sidebar textarea):not(#sidebar * textarea) {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.5rem !important;
            }
            
            /* Content boxes - tighter padding */
            .rounded-xl.border.bg-white\/70 {
                padding: 0.5rem !important;
            }
            
            /* Grid gaps */
            .grid.gap-4 {
                gap: 0.5rem !important;
            }
            
            .grid.sm\\:grid-cols-2,
            .grid.sm\\:grid-cols-3,
            .grid.md\\:grid-cols-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
            
            /* Space-y utilities */
            .space-y-10 > * + * {
                margin-top: 1rem !important;
            }
            
            .space-y-4 > * + * {
                margin-top: 0.5rem !important;
            }
            
            /* Icons */
            main i.fa:not(#sidebar i.fa):not(#sidebar * i.fa),
            main i.fas:not(#sidebar i.fas):not(#sidebar * i.fas),
            main i.far:not(#sidebar i.far):not(#sidebar * i.far) {
                font-size: 0.7rem !important;
            }
            
            /* Attachment thumbnails */
            .thumb-tile {
                font-size: 11px !important;
            }
            
            /* Tabs */
            #tabSalaysay, #tabEvidence {
                padding: 0.375rem 0.625rem !important;
                font-size: 0.7rem !important;
            }
            
            /* Modal adjustments */
            #historyModal .bg-white {
                padding: 0.75rem !important;
            }
            
            #historyModal h3 {
                font-size: 0.95rem !important;
            }
            
            /* Dropzone - compact */
            #salaysayDropZone,
            #complDropZone {
                padding: 0.75rem !important;
            }
            
            #salaysayDropZone p,
            #complDropZone p {
                font-size: 0.7rem !important;
            }
            
            /* Form sections - reduce gaps */
            form .flex.flex-col.gap-3,
            form .flex.flex-wrap.gap-3 {
                gap: 0.5rem !important;
            }
            
            /* Back link - smaller */
            .mb-8.flex.items-center.gap-3 {
                margin-bottom: 1rem !important;
            }
            
            .mb-8.flex.items-center.gap-3 .h-8.w-8 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    <?php $status=strtoupper(trim($complaint['Status'])); $statusStyles=['PENDING'=>'bg-amber-50 text-amber-600 border border-amber-200','IN CASE'=>'bg-sky-50 text-sky-600 border border-sky-200','REJECTED'=>'bg-rose-50 text-rose-600 border border-rose-200','DISMISSED'=>'bg-rose-50 text-rose-600 border border-rose-200','RESOLVED'=>'bg-emerald-50 text-emerald-600 border border-emerald-200']; $statusClass=$statusStyles[$status]??'bg-gray-100 text-gray-600 border border-gray-200'; ?>
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <?php if(!empty($error)): ?>
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm shadow-sm"><i class="fa fa-circle-exclamation mr-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['notice']) && $_GET['notice'] === '1'): ?>
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm shadow-sm"><i class="fa fa-bell mr-2"></i>Notice sent to the complainant and respondents. They have been asked to visit the barangay.</div>
        <?php endif; ?>
        <div class="mb-8 flex items-center gap-3">
            <a href="javascript:history.back();" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition" aria-label="Go back to previous page">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to previous page</span>
            </a>
        </div>
        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
            </div>
            <header class="relative flex flex-row items-center md:items-start gap-4 md:gap-6 mb-8 flex-wrap">
                <div class="flex items-center">
                    <div class="w-12 h-12 md:w-20 md:h-20 rounded-lg md:rounded-2xl flex items-center justify-center bg-primary-50 ring-1 md:ring-4 ring-primary-100 shadow-inner">
                        <i class="fa fa-file-lines text-xl md:text-3xl text-primary-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <h1 class="text-lg md:text-2xl font-semibold tracking-tight text-gray-800 flex items-center gap-2 md:gap-3 min-w-0">
                            <span class="truncate bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Complaint <?= htmlspecialchars($complaint_ref) ?></span>
                            <span class="inline-flex items-center gap-1 text-[11px] md:text-xs font-medium px-2 md:px-3 py-0.5 md:py-1 rounded-full <?= $statusClass ?> shadow-sm"><i class="fa fa-circle text-[7px] md:text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?></span>
                            <?php if($is_case): ?><span class="hidden md:inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 shadow-sm"><i class="fa fa-gavel"></i> Case Opened</span><?php endif; ?>
                            <?php if($is_case && !empty($existing_case_type)): ?><span class="hidden md:inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-primary-50 text-primary-700 border border-primary-200 shadow-sm"><i class="fa fa-tags"></i> Type: <?= htmlspecialchars($existing_case_type) ?></span><?php endif; ?>
                            <?php if($is_rejected): ?><span class="hidden md:inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-rose-50 text-rose-600 border border-rose-200 shadow-sm"><i class="fa fa-ban"></i> Rejected</span><?php endif; ?>
                        </h1>
                    </div>
                    <!-- Desktop meta (shown md+) -->
                    <div class="mt-3 text-sm text-gray-500">

                    <!-- FIRST ROW: Name + Date -->
                    <div class="hidden md:flex flex-wrap items-center gap-4">
                        <span class="inline-flex items-center gap-1">
                            <i class="fa fa-user"></i> <?= htmlspecialchars($complainant_name) ?>
                        </span>

                        <span class="inline-flex items-center gap-1">
                            <i class="fa fa-calendar"></i>
                            <?=   htmlspecialchars(date('F d, Y', strtotime($complaint['Date_Filed']))) ?> 
                        </span>
                    </div>

                    <!-- SECOND ROW: Minor warning (always on its own line) -->
                    <?php if (!empty($complainant_is_minor)): ?>
                        <div class="mt-1 text-xs text-rose-600 flex items-center gap-1">
                            <i class="fa fa-child"></i>
                            Complainant is a minor (under 18). Parental/guardian consent required.
                        </div>
                    <?php endif; ?>

                </div>

                    <!-- Desktop dismissal (shown md+) -->
                    <?php if($is_dismissed): ?>
                            <?php
                                // Prefer new column name `dismissal_reason`, fall back to legacy `Dismiss_Reason`.
                                $dismissReasonDisplay = '';
                                if (isset($complaint['dismissal_reason'])) $dismissReasonDisplay = trim($complaint['dismissal_reason']);
                                elseif (isset($complaint['Dismiss_Reason'])) $dismissReasonDisplay = trim($complaint['Dismiss_Reason']);
                            ?>
                        <div class="mt-4 hidden md:block">
                            <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 mt-0.5"><i class="fa fa-circle-exclamation text-rose-600"></i></div>
                                    <div>
                                        <div class="font-semibold text-rose-800">Dismissal Reason</div>
                                        <div class="text-[13px] text-rose-700 mt-1"><?= htmlspecialchars($dismissReasonDisplay ?: 'No reason provided.') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Three-dot controls -->
                <div class="relative ml-auto">
                    <button id="kebabBtn" type="button" class="inline-flex items-center justify-center w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-white/80 hover:bg-white border border-primary-100 text-primary-700 shadow-sm">
                        <i class="fa fa-ellipsis-vertical text-sm md:text-base"></i>
                    </button>
                    <div id="kebabMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 z-20 overflow-hidden">
                        <button id="openHistory" type="button" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50/60 w-full text-left">
                            <i class="fa fa-clock-rotate-left text-primary-500"></i>
                            <span>Version History</span>
                        </button>
                        <?php if(!empty($case_id_for_complaint) && $status !== 'BLOTTER_CASE'): ?>
                        <a href="meeting_cases_log.php?id=<?= $case_id_for_complaint ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50/60">
                            <i class="fa fa-clock-rotate-left text-primary-500"></i>
                            <span>History of Meeting</span>
                        </a>
                        <?php endif; ?>
                        <?php if($status === 'IN CASE'): ?>
                        <a href="view_cases.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50/60">
                            <i class="fa fa-list text-primary-500"></i>
                            <span>View Case List</span>
                        </a>
                        <?php elseif($status === 'BLOTTER_CASE'): ?>
                        <a href="view_blotter.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50/60">
                            <i class="fa fa-clipboard-list text-primary-500"></i>
                            <span>View Blotter List</span>
                        </a>
                        <?php endif; ?>
                        <a href="view_complaint_details.php?id=<?= $complaint_id ?>" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-primary-50/60">
                            <i class="fa fa-up-right-from-square text-primary-500"></i>
                            <span>Open in New Tab</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Mobile meta & dismissal (shown on small screens below header) -->
            <div class="mt-3 md:hidden">
                <div class="flex flex-col gap-2 text-sm text-gray-500">
                    <div class="inline-flex items-center gap-2"><i class="fa fa-user"></i> <?= htmlspecialchars($complainant_name) ?></div>
                    <?php if(!empty($complainant_is_minor)): ?>
                        <div class="text-xs text-rose-600"><i class="fa fa-child"></i> Complainant is a minor (under 18). Parental/guardian consent required.</div>
                    <?php endif; ?>
                    <div class="inline-flex items-center gap-2"><i class="fa fa-calendar"></i> <?=  htmlspecialchars(date('F d, Y', strtotime($complaint['Date_Filed']))) ?></div>
                </div>
                <?php if($is_dismissed): ?>
                    <div class="mt-3">
                        <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5"><i class="fa fa-circle-exclamation text-rose-600"></i></div>
                                <div>
                                    <div class="font-semibold text-rose-800">Dismissal Reason</div>
                                    <div class="text-[13px] text-rose-700 mt-1"><?= htmlspecialchars($dismissReasonDisplay ?: 'No reason provided.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-10">
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Respondents</h2>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                                <?php
                                    // Prepopulate Tagify with existing respondents as JSON array [{value: 'Name'}]
                                    $tagifyInit = [];
                                    foreach ($respondents as $rn) { $tagifyInit[] = ['value' => $rn]; }
                                    $tagifyJson = htmlspecialchars(json_encode($tagifyInit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                ?>
                                <input type="text" id="respondent-name" name="respondent_name" form="editForm" class="w-full px-3 py-2 rounded-lg border border-gray-300 respondent-input focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/80" value='<?= $tagifyJson ?>' />
                                <p class="text-xs text-gray-500 italic mt-2">Edit respondent names. Press Enter to add each name.</p>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php if (!empty($respondents)): ?>
                                        <?php foreach ($respondents as $rname): ?>
                                            <?php $enc = rawurlencode($rname); ?>
                                            <a href="?id=<?= $complaint_id ?>&amp;edit=1&amp;add_respondent=<?= $enc ?>" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-sm text-gray-700 shadow-sm transition">
                                                <i class="fa fa-user text-xs text-gray-500"></i>
                                                <?= htmlspecialchars($rname) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-gray-700 leading-relaxed">N/A</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <div>
                    <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                        <form id="editForm" method="POST">
                            <input type="hidden" name="complaint_title" value="<?= htmlspecialchars($complaint['Complaint_Title'] ?? '') ?>" />
                    <?php endif; ?>

                    <?php
                        // Determine complaint type from available columns / related case
                        $complaint_type = 'N/A';
                        if (!empty($complaint['case_type'])) $complaint_type = $complaint['case_type'];
                        elseif (!empty($complaint['Case_Type'])) $complaint_type = $complaint['Case_Type'];
                        elseif (!empty($complaint['Complaint_Type'])) $complaint_type = $complaint['Complaint_Type'];
                        elseif (!empty($existing_case_type)) $complaint_type = $existing_case_type;
                    ?>

                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Complaint Type</h2>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($complaint_type) ?></p>
                        </div>
                    </div>

                     <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Complaint Information</h2>
                     <div class="grid gap-5 md:grid-cols-2">
                         
                         <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm md:col-span-2">
                             <p class="field-label mb-1">Details</p>
                            <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                                <textarea name="complaint_details" rows="5" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/80"><?= htmlspecialchars($complaint['Complaint_Details']) ?></textarea>
                            <?php else: ?>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($complaint['Complaint_Details'])) ?></p>
                            <?php endif; ?>
                         </div>
                         <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                             <p class="field-label mb-1">Incident Date</p>
                             <p class="font-semibold text-gray-800"><?=
                                !empty($complaint['incident_date'])
                                    ? (date('F d, Y', strtotime($complaint['incident_date'])) . (!empty($complaint['incident_time']) ? ' at '.date('g:i A', strtotime($complaint['incident_time'])) : ''))
                                    : date('F d, Y', strtotime($complaint['Date_Filed']))
                             ?></p>
                         </div>
                         <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                             <p class="field-label mb-1">Status</p>
                             <p class="inline-flex items-center gap-2 font-semibold <?= $status==='REJECTED' ? 'text-rose-600' : ($status==='PENDING' ? 'text-amber-600' : ($status==='IN CASE' ? 'text-sky-600':'text-emerald-600')) ?>"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?></p>
                         </div>
                     </div>
                 </div>
                 <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?></form><?php endif; ?>
                <div class="pt-4 border-t border-dashed border-primary-200/60 flex flex-col gap-4">
                    <?php if($status_upper === 'BLOTTER_CASE' && $blotterRow): ?>
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3 flex items-center gap-2"><i class="fa fa-clipboard-list text-primary-500"></i> Blotter Details</h2>
                        <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <p class="field-label mb-1">Blotter ID</p>
                                    <p class="font-semibold text-gray-800">
                                        <?= isset($blotterRow['Blotter_ID']) ? htmlspecialchars($blotterRow['Blotter_ID']) : 'N/A' ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="field-label mb-1">Date Validated as Blotter</p>
                                    <p class="font-semibold text-gray-800">
                                        <?= !empty($blotterRow['Date_Reported']) ? date('F d, Y', strtotime($blotterRow['Date_Reported'])) : 'N/A' ?>
                                    </p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="field-label mb-1">Reported By</p>
                                    <p class="font-semibold text-gray-800">
                                        <?php
                                            $rb = trim(($blotterRow['Rept_FName'] ?? '').' '.($blotterRow['Rept_LName'] ?? ''));
                                            echo $rb !== '' ? htmlspecialchars($rb) : 'N/A';
                                        ?>
                                    </p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="field-label mb-1">Description</p>
                                    <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($blotterRow['Blotter_Description'] ?? 'N/A')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php endif; ?>
                      <?php
    // Detect edit mode based on URL parameter (?edit=true)
    $is_edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

    $salaysay_raw = $complaint['Salaysay_Path'] ?? '';
    $salaysay_display = '';
    $salaysay_is_image = $salaysay_is_pdf = false;

    if (!empty($salaysay_raw)) {
        $clean = str_replace('..', '', $salaysay_raw);
        $clean = str_replace('\\', '/', $clean);
        $clean = ltrim($clean, '/');
        $salaysay_display = implode('/', array_map('rawurlencode', explode('/', $clean)));
        $salaysay_is_image = (bool)preg_match('/\.(jpe?g|png|gif|webp)$/i', $clean);
        $salaysay_is_pdf = (bool)preg_match('/\.pdf$/i', $clean);
    }
?>

 <?php
                        $salaysay_raw = $complaint['Salaysay_Path'] ?? '';
                        $salaysay_display = '';
                        $salaysay_is_image = $salaysay_is_pdf = false;
                        if (!empty($salaysay_raw)) {
                            $clean = str_replace('..', '', $salaysay_raw);
                            $clean = str_replace('\\', '/', $clean);
                            $clean = ltrim($clean, '/');
                            $salaysay_display = implode('/', array_map('rawurlencode', explode('/', $clean)));
                            $salaysay_is_image = (bool)preg_match('/\.(jpe?g|png|gif|webp)$/i', $clean);
                            $salaysay_is_pdf = (bool)preg_match('/\.pdf$/i', $clean);
                        }
                    ?>
                    <!-- Tabs header -->
                    <div class="mb-2">
                        <div class="flex items-center gap-2 border-b border-gray-200">
                            <button id="tabSalaysay" type="button" class="px-4 py-2 text-sm font-medium text-primary-700 border-b-2 border-primary-600">Salaysay</button>
                            <button id="tabEvidence" type="button" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-primary-700">Supporting Evidence</button>
                        </div>
                    </div>
                    <div id="paneSalaysay">
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Salaysay (Hard Copy)</h2>
                        <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <div id="salaysayPreviewContainer" class="mb-3 grid sm:grid-cols-3 gap-3 items-center group">
                                <?php if(!empty($salaysay_display)): ?>
                                    <div class="col-span-1 w-full aspect-video bg-gray-100 flex items-center justify-center overflow-hidden rounded-lg relative">
                                        <?php if($salaysay_is_image): ?>
                                            <img id="salaysayExistingImg" src="../<?= htmlspecialchars($salaysay_display) ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition" />
                                        <?php elseif($salaysay_is_pdf): ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-file-pdf text-3xl mb-1"></i>
                                                <div class="text-xs mt-1">PDF</div>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-paperclip text-3xl mb-1"></i>
                                                <div class="text-xs mt-1">File</div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                            <div class="flex gap-2">
                                                <?php if($salaysay_is_image): ?>
                                                    <button type="button" onclick="previewImage('../<?= htmlspecialchars($salaysay_display) ?>')" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/90 hover:bg-white text-primary-700 text-xs font-medium"><i class="fa fa-eye"></i> View</button>
                                                <?php endif; ?>
                                                <a href="../<?= htmlspecialchars($salaysay_display) ?>" download class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium"><i class="fa fa-download"></i> Download</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-span-1 w-full aspect-video bg-gray-100 flex items-center justify-center overflow-hidden rounded-lg relative" id="salaysayPlaceholder">
                                        <div class="text-gray-400">No salaysay uploaded</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>           
                            <form id="salaysayForm" method="POST" enctype="multipart/form-data" class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm" novalidate>
                                <input type="hidden" name="upload_salaysay" value="1" />
                                <input type="file" id="salaysayFile" name="salaysay" accept=".jpg,.jpeg,.png" class="hidden" />
                                <div id="salaysayDropZone" class="mt-1 border-2 border-dashed border-primary-300/70 rounded-xl p-6 flex flex-col items-center justify-center gap-3 text-sm text-gray-500 bg-white/60 hover:bg-white transition cursor-pointer">
                                    <i class="fa fa-cloud-arrow-up text-primary-500 text-2xl"></i>
                                    <p class="text-center leading-snug">
                                        <span class="font-medium text-primary-600">Click to browse</span> or drag & drop file here<br>
                                        <span class="text-[10px] text-gray-400">JPG or PNG only (max 20MB)</span>
                                    </p>
                                    <div id="salaysayGrid" class="mt-4 grid sm:grid-cols-2 md:grid-cols-3 gap-4 w-full"></div>
                                    <p id="salaysayHint" class="hidden w-full text-[11px] text-gray-500"></p>
                                </div>
                                <div class="mt-4 text-right">
                                    <button type="button" id="btnUploadSalaysay" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium">
                                        <i class="fa fa-paper-plane"></i> Upload Salaysay
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>

                    
            <div id="paneEvidence" class="hidden">
                <?php if(!empty($attachments)): ?>
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Attachments (Supporting Evidence)</h2>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <?php foreach($attachments as $att): ?>
                                <div class="group relative rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-glow transition overflow-hidden">
                                    <div class="aspect-video w-full bg-gray-100 flex items-center justify-center overflow-hidden">
                                        <?php if($att['is_image']): ?>
                                            <img src="../<?= htmlspecialchars($att['url']) ?>" alt="Attachment" class="w-full h-full object-cover object-center group-hover:scale-105 transition" />
                                        <?php elseif($att['is_pdf']): ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-file-pdf text-3xl mb-1"></i>
                                                PDF File
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-paperclip text-3xl mb-1"></i>
                                                File
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="flex gap-2">
                                            <?php if($att['is_image']): ?>
                                                <button type="button" onclick="previewImage('../<?= htmlspecialchars($att['url']) ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-primary-700 text-xs font-medium"><i class="fa fa-eye"></i> View</button>
                                            <?php endif; ?>
                                            <a href="../<?= htmlspecialchars($att['url']) ?>" download class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium"><i class="fa fa-download"></i> Download</a>
                                        </div>
                                    </div>
                                </div>
                             <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600 italic mb-3">No supporting evidence uploaded yet.</p>
                        <?php endif; ?>
                        </div>
                     
                  
                    
                    <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                        <div id="evidenceUploadSection">
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Add Supporting Evidence</h2>
                            <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                                <form method="POST" enctype="multipart/form-data" id="attachmentsForm">
                                    <input type="hidden" name="upload_attachment" value="1" />
                                    <div id="complDropZone" class="mt-1 border-2 border-dashed border-primary-300/70 rounded-xl p-6 flex flex-col items-center justify-center gap-3 text-sm text-gray-500 bg-white/60 hover:bg-white transition cursor-pointer">
                                        <i class="fa fa-cloud-arrow-up text-primary-500 text-2xl"></i>
                                        <p class="text-center leading-snug">
                                            <span class="font-medium text-primary-600">Click to browse</span> or drag & drop files here<br>
                                            <span class="text-[10px] text-gray-400">Images & documents (max 20MB each)</span>
                                        </p>
                                        <input type="file" name="attachments_upload[]" id="attachmentsUploadInput" class="hidden" multiple />
                                        <div id="attachmentsGrid" class="mt-4 grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 w-full"></div>
                                        <p id="attachmentsHint" class="hidden w-full text-[11px] text-gray-500"></p>
                                    </div>
                                    <div class="mt-4 text-right">
                                        <!-- changed: submit -> button; id added -->
                                        <button type="button" id="btnUploadEvidence" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium">
                                            <i class="fa fa-paper-plane"></i> Upload Evidence
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
            </div>
                    <?php if(!$is_case && !$is_rejected && !$is_dismissed): ?>
                    <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                        <p class="field-label mb-3">Validation Decision</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <!-- Validate card -->
                            <button id="btnValidate" type="button" class="group text-left rounded-2xl p-4 border border-transparent hover:border-primary-200 transition shadow-sm hover:shadow-md bg-gradient-to-br from-emerald-50 to-white focus:outline-none" aria-pressed="false">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow">
                                        <i class="fa fa-check text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-gray-800">Validate</h3>
                                            <span class="text-xs text-emerald-600 font-medium">Convert to Case</span>
                                        </div>
                                        <p class="mt-1 text-[13px] text-gray-500">Evaluate the complaint and convert it into a formal case. You can choose the case type after clicking.</p>
                                    </div>
                                </div>
                            </button>

                            <!-- Dismiss card -->
                            <button id="btnDismiss" type="button" class="group text-left rounded-2xl p-4 border border-transparent hover:border-rose-200 transition shadow-sm hover:shadow-md bg-gradient-to-br from-rose-50 to-white focus:outline-none" aria-pressed="false">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-rose-600 text-white flex items-center justify-center shadow">
                                        <i class="fa fa-ban text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-sm font-semibold text-gray-800">Dismiss</h3>
                                            <span class="text-xs text-rose-600 font-medium">Mark as Dismissed</span>
                                        </div>
                                        <p class="mt-1 text-[13px] text-gray-500">Dismiss this complaint with a reason. This action notifies the complainant and records the reason.</p>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div class="mt-4">
                            <!-- Dismiss form (kept intact) -->
                            <form id="dismissForm" method="POST" class="mt-3 hidden" onsubmit="return confirm('Dismiss this complaint? This action cannot be undone.');">
                                <input type="hidden" name="dismiss_complaint" value="1" />

                                <!-- Suggestive reasons -->
                                <div id="dismissSuggestions" class="flex flex-wrap gap-2 mb-3">
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Duplication of complaint">Duplication of complaint</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Trivial or minor issue">Trivial or minor issue</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Lack of merit">Lack of merit</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Lack of cause of action">Lack of cause of action</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Lack of interest to pursue">Lack of interest to pursue</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Failure to appear / non-appearance">Failure to appear / non-appearance</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Withdrawal of complaint">Withdrawal of complaint</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Complaint already filed">Complaint already filed</button>
                                    <button type="button" class="dismiss-suggest inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200 text-xs text-gray-700" data-suggest="Lack of evidence">Lack of evidence</button>
                                    <button type="button" id="dismissClear" class="ml-1 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-xs text-gray-600 hover:bg-gray-50">Clear</button>
                                </div>

                                <div class="flex gap-2 items-center">
                                    <textarea id="dismissReason" name="dismiss_reason" required placeholder="Reason for dismissal" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 bg-white/90 text-sm" rows="2" style="min-width:220px"></textarea>
                                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium"><i class="fa fa-check"></i> Confirm</button>
                                </div>
                            </form>

                            <!-- Convert form (kept intact) -->
                            <form id="convertForm" method="POST" class="mt-3 hidden" onsubmit="return confirm('Convert this complaint into a case?');">
                                <input type="hidden" name="validate_decision" value="yes" />
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <div class="flex-1">
                                        <label for="caseTypeSelect" class="block text-xs font-medium text-gray-600 mb-1">Case Type</label>
                                        <div class="relative">
                                            <select name="case_type" id="caseTypeSelect" required class="w-full appearance-none px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/90">
                                                <option value="">Select Case Type</option>
                                                <?php
                                                    // show Record Purposes + persisted types + 'Others'
                                                    $seen = [];
                                                    foreach ($caseTypes as $ct) {
                                                        if (in_array($ct, $seen, true)) continue;
                                                        $seen[] = $ct;
                                                        echo '<option value="'.htmlspecialchars($ct).'">'.htmlspecialchars($ct).'</option>';
                                                    }
                                                ?>
                                                <option value="Others">Others</option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400"><i class="fa fa-caret-down"></i></div>
                                        </div>

                                        
                                    </div>

                                    <input type="text" id="otherCaseInput" name="other_case_type" placeholder="Specify other case type" class="px-3 py-2 rounded-lg border border-gray-300 bg-white/90 hidden" />
                                    <button id="btnConvert" type="submit" name="validate_case" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow text-sm font-medium transition disabled:opacity-60 disabled:cursor-not-allowed" disabled><i class="fa fa-gavel"></i> Convert to Case</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-2">
                        <a href="view_complaints.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back</a>
                        <?php if(!$is_case && !$editing && !$is_rejected && !$is_dismissed): ?>
                                <a href="?id=<?= $complaint_id ?>&edit=1" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow text-sm font-medium transition"><i class="fa fa-pen"></i>Edit Information</a>
                                <form method="POST" onsubmit="return confirm('Send notice to the complainant and respondents to visit the barangay?');" class="inline-block ml-2">
                                    <input type="hidden" name="send_notice" value="1" />
                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow text-sm font-medium transition"><i class="fa fa-bell"></i> Send Notice</button>
                                </form>
                            <?php endif; ?>
                        <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                            <button type="submit" name="update_complaint" form="editForm" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition"><i class="fa fa-save"></i> Save Changes</button>
                            <form method="POST" onsubmit="return confirm('Send notice to the complainant and respondents to visit the barangay?');" class="inline-block ml-2">
                                <input type="hidden" name="send_notice" value="1" />
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow text-sm font-medium transition"><i class="fa fa-bell"></i> Send Notice</button>
                            </form>
                        <?php endif; ?>
                          
                        <?php if(!$is_case && !$editing && !$is_rejected && !$is_dismissed): ?>
                            <!-- Rejection handled above in Validation Decision block -->
                        <?php endif; ?>
                        <?php if($is_case): ?>
                            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium cursor-not-allowed opacity-80"><i class="fa fa-check"></i> Already a Case</span>
                        <?php endif; ?>
                        <?php if($is_rejected): ?>
                            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium cursor-not-allowed opacity-80"><i class="fa fa-ban"></i> Complaint Rejected</span>
                        <?php endif; ?>
                        <?php if($is_dismissed): ?>
                            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium cursor-not-allowed opacity-80"><i class="fa fa-ban"></i> Complaint Dismissed</span>
                        <?php endif; ?>
                        </div>
                        <?php if($status === 'IN CASE'): ?>
                            <div class="ml-auto">
                                <a href="view_cases.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition">
                                    <i class="fa fa-list"></i> View Case List
                                </a>
                            </div>
                        <?php elseif($status === 'BLOTTER_CASE'): ?>
                            <div class="ml-auto">
                                <a href="view_blotter.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition">
                                    <i class="fa fa-clipboard-list"></i> View Blotter List
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?></form><?php endif; ?>
            </div>
        </section>
    </main>
    <!-- Version History Modal -->
    <div id="historyModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-6 overflow-auto">
        <div class="bg-white rounded-2xl max-w-4xl w-full shadow-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Version History</h3>
                <div class="flex items-center gap-2">
                    <?php if($editing): ?>
                        <span class="text-xs text-gray-500">You can revert to a previous version.</span>
                    <?php else: ?>
                        <span class="text-xs text-gray-500">View-only mode — no revert allowed.</span>
                    <?php endif; ?>
                    <button id="closeHistory" class="ml-2 px-3 py-1 rounded bg-gray-100"><i class="fa fa-xmark"></i></button>
                </div>
            </div>
            <div class="space-y-4">
                <?php if(empty($historyEntries)): ?>
                    <p class="text-sm text-gray-500">No edits recorded yet.</p>
                <?php else: foreach($historyEntries as $h): ?>
                    <div class="border rounded-lg p-3 bg-gray-50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium">Version <?= (int)$h['version_number'] ?> — <span class="text-xs text-gray-500"><?= htmlspecialchars($h['updated_by'] ?? 'Unknown') ?></span></div>
                                <div class="text-xs text-gray-400"><?= date('F d, Y h:i A', strtotime($h['updated_at'])) ?></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if($editing): ?>
                                    <form method="POST" onsubmit="return confirm('Revert complaint to this version? Current content will be saved to history.');">
                                        <input type="hidden" name="revert_version" value="1" />
                                        <input type="hidden" name="history_id" value="<?= (int)$h['id'] ?>" />
                                        <button type="submit" class="px-3 py-1 rounded bg-rose-600 text-white text-sm"><i class="fa fa-rotate-left"></i> Revert</button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" onclick="toggleContent(<?= (int)$h['id'] ?>)" class="px-3 py-1 rounded bg-white border text-sm">Show</button>
                            </div>
                        </div>
                        <div id="content-<?= (int)$h['id'] ?>" class="mt-3 hidden whitespace-pre-wrap text-sm text-gray-800 border-t pt-2"><?= nl2br(htmlspecialchars($h['details'])) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
   <!-- NEW SCRIPT BLOCK (kept completely) -->
    <script>
        document.getElementById('openHistory')?.addEventListener('click', function(e){
            e.stopPropagation();
            document.getElementById('historyModal').classList.remove('hidden');
        });
        document.getElementById('closeHistory')?.addEventListener('click', function(){
            document.getElementById('historyModal').classList.add('hidden');
        });
        function toggleContent(id){
            const el = document.getElementById('content-'+id);
            if(!el) return;
            el.classList.toggle('hidden');
        }
    </script>

<script>
    // Attachments dropzone for complaint details (similar to add_complaints.php implementation)
    (function(){
        const dz = document.getElementById('complDropZone');
        const input = document.getElementById('attachmentsUploadInput');
        const grid = document.getElementById('attachmentsGrid');
        const hint = document.getElementById('attachmentsHint');
        const form = document.getElementById('attachmentsForm');
        const btn  = document.getElementById('btnUploadEvidence');
        if(!dz || !input || !grid || !hint || !form || !btn) return;

        let dt = new DataTransfer();

        const over = e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('ring-2','ring-primary-400','bg-white'); };
        const leave = e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('ring-2','ring-primary-400'); };
        ['dragenter','dragover'].forEach(evt=>dz.addEventListener(evt,over));
        ['dragleave','drop'].forEach(evt=>dz.addEventListener(evt,leave));

        dz.addEventListener('click', () => input.click());
        dz.addEventListener('drop', e => { e.preventDefault(); if(e.dataTransfer.files.length){ addFiles(e.dataTransfer.files); }});
        input.addEventListener('change', () => addFiles(input.files));

        function addFiles(fileList){
            Array.from(fileList).forEach(f => {
                if (f.size > 20*1024*1024) return;
                for(let i=0;i<dt.files.length;i++){
                    const existing = dt.files[i];
                    if(existing.name===f.name && existing.size===f.size) return;
                }
                dt.items.add(f);
            });
            input.files = dt.files;
            render();
        }

        function removeFile(idx){
            const newDT = new DataTransfer();
            for(let i=0;i<dt.files.length;i++) if(i!==idx) newDT.items.add(dt.files[i]);
            dt = newDT;
            input.files = dt.files;
            render();
        }

        function render(){
            grid.innerHTML = '';
            if(!dt.files.length){
                hint.textContent = 'No files selected.';
                hint.classList.remove('hidden');
                return;
            }
            hint.textContent = dt.files.length + ' file(s) ready to upload.';
            hint.classList.remove('hidden');

            Array.from(dt.files).forEach((f,idx)=>{
                const ext = f.name.split('.').pop().toLowerCase();
                const isImage = ['jpg','jpeg','png','gif','webp','bmp'].includes(ext);
                const tile = document.createElement('div');
                tile.className='thumb-tile relative rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden group';
                tile.innerHTML = `
                    <div class="w-full aspect-video flex items-center justify-center bg-gray-100 overflow-hidden">
                        ${isImage ? `<img class="w-full h-full object-cover" />`
                                  : `<div class="flex flex-col items-center justify-center text-gray-500 text-xs p-3">
                                        <i class="fa fa-file text-2xl mb-2"></i>
                                        <span class="break-all leading-tight">${escapeHtml(f.name)}</span>
                                     </div>`}
                    </div>
                    <div class="absolute top-2 right-2">
                        <button type="button" class="h-8 w-8 rounded-full bg-white/90 hover:bg-white text-rose-600 shadow flex items-center justify-center del-btn"><i class="fa fa-trash"></i></button>
                    </div>`;
                grid.appendChild(tile);
                if(isImage){
                    const imgEl = tile.querySelector('img');
                    const reader = new FileReader();
                    reader.onload = e => imgEl.src = e.target.result;
                    reader.readAsDataURL(f);
                }
                tile.querySelector('.del-btn').addEventListener('click', ()=> removeFile(idx));
            });
        }

        function escapeHtml(str){ return str.replace(/[&<>\"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]||c)); }

        btn.addEventListener('click', () => {
            if (!dt.files.length) {
                hint.textContent = 'Please select files first.';
                hint.classList.remove('hidden');
                dz.classList.add('ring-2','ring-rose-400');
                setTimeout(()=>dz.classList.remove('ring-2','ring-rose-400'), 1200);
                return;
            }
            input.files = dt.files;
            form.submit();
        });

        form.addEventListener('submit', (e) => {
            if (!input.files || !input.files.length) {
                e.preventDefault();
                hint.textContent = 'Please select files first.';
                hint.classList.remove('hidden');
            }
        });
    })();
</script>

<script>
    // Tabs: toggle between Salaysay and Supporting Evidence
    (function(){
        const tabS = document.getElementById('tabSalaysay');
        const tabE = document.getElementById('tabEvidence');
        const paneS = document.getElementById('paneSalaysay');
        const paneE = document.getElementById('paneEvidence');
        const uploadSec = document.getElementById('evidenceUploadSection');
        if (!tabS || !tabE || !paneS || !paneE) return;
        function activate(which){
            tabS.classList.remove('text-primary-700','border-b-2','border-primary-600');
            tabS.classList.add('text-gray-500');
            tabE.classList.remove('text-primary-700','border-b-2','border-primary-600');
            tabE.classList.add('text-gray-500');
            paneS.classList.add('hidden');
            paneE.classList.add('hidden');
            if (which === 'salaysay'){
                tabS.classList.remove('text-gray-500');
                tabS.classList.add('text-primary-700','border-b-2','border-primary-600');
                paneS.classList.remove('hidden');
                if (uploadSec) uploadSec.classList.add('hidden');
            } else {
                tabE.classList.remove('text-gray-500');
                tabE.classList.add('text-primary-700','border-b-2','border-primary-600');
                paneE.classList.remove('hidden');
                if (uploadSec) uploadSec.classList.remove('hidden');
            }
        }
        tabS.addEventListener('click', () => activate('salaysay'));
        tabE.addEventListener('click', () => activate('evidence'));
        activate('salaysay');
    })();
</script>

<script>
    // Salaysay upload: dropzone + auto submit (edit mode only)
    (function(){
        const IS_EDITING = <?php echo $editing ? 'true' : 'false'; ?>;
    const IS_ALLOWED = <?php echo ((!$is_case && !$is_rejected && !$is_dismissed) ? 'true' : 'false'); ?>;
        if (!IS_EDITING || !IS_ALLOWED) return;

        const form = document.getElementById('salaysayForm');
        const input = document.getElementById('salaysayFile');
        const dz = document.getElementById('salaysayDropZone');
        const grid = document.getElementById('salaysayGrid');
        const hint = document.getElementById('salaysayHint');
        const btn = document.getElementById('btnUploadSalaysay');
        if (!form || !input || !dz || !grid || !hint || !btn) return;

        const ALLOWED = ['jpg','jpeg','png'];
        const MAX = 20 * 1024 * 1024;

        let dt = new DataTransfer();

        const over = e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('ring-2','ring-primary-400','bg-white'); };
        const leave = e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('ring-2','ring-primary-400'); };
        ['dragenter','dragover'].forEach(evt => dz.addEventListener(evt, over));
        ['dragleave','dragend','drop'].forEach(evt => dz.addEventListener(evt, leave));

        dz.addEventListener('click', () => input.click());
        dz.addEventListener('drop', e => { e.preventDefault(); if(e.dataTransfer.files.length){ addFiles(e.dataTransfer.files); }});
        input.addEventListener('change', () => addFiles(input.files));

        function addFiles(fileList){
            const f = fileList[0];
            if (!f) return;
            const ext = f.name.split('.').pop().toLowerCase();
            if (!ALLOWED.includes(ext) || f.size > MAX) {
                hint.textContent = 'Only PNG/JPG up to 20MB is allowed.';
                hint.classList.remove('hidden');
                return;
            }
            dt = new DataTransfer();
            dt.items.add(f);
            input.files = dt.files;
            render();
        }

        function removeFile(){
            dt = new DataTransfer();
            input.value = '';
            input.files = dt.files;
            render();
        }

        function render(){
            grid.innerHTML = '';
            if (dt.files.length === 0) {
                hint.textContent = 'No file selected.';
                hint.classList.remove('hidden');
                return;
            }
            hint.textContent = 'Ready to upload 1 file.';
            hint.classList.remove('hidden');

            const f = dt.files[0];
            const tile = document.createElement('div');
            tile.className = 'thumb-tile relative rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden group';
            tile.innerHTML = `
                <div class="w-full aspect-video flex items-center justify-center bg-gray-100 overflow-hidden">
                    <img class="w-full h-full object-cover" />
                </div>
                <div class="absolute top-2 right-2">
                    <button type="button" class="h-8 w-8 rounded-full bg-white/90 hover:bg-white text-rose-600 shadow flex items-center justify-center del-btn"><i class="fa fa-trash"></i></button>
                </div>`;
            grid.appendChild(tile);

            const imgEl = tile.querySelector('img');
            const reader = new FileReader();
            reader.onload = e => imgEl.src = e.target.result;
            reader.readAsDataURL(f);

            tile.querySelector('.del-btn').addEventListener('click', removeFile);
        }

        btn.addEventListener('click', () => {
            if (dt.files.length === 0) {
                hint.textContent = 'Please select a PNG/JPG first.';
                hint.classList.remove('hidden');
                dz.classList.add('ring-2','ring-rose-400');
                setTimeout(() => dz.classList.remove('ring-2','ring-rose-400'), 1200);
                return;
            }
            input.files = dt.files;
            form.submit();
        });
    })();
</script>

<script>
(function(){
    const btnValidate = document.getElementById('btnValidate');
    const btnDismiss  = document.getElementById('btnDismiss');
    const convertForm = document.getElementById('convertForm');
    const dismissForm = document.getElementById('dismissForm');
    const caseSelect  = document.getElementById('caseTypeSelect');
    const otherInput  = document.getElementById('otherCaseInput');
    const btnConvert  = document.getElementById('btnConvert');

    function showConvert(){
        if (convertForm) convertForm.classList.remove('hidden');
        if (dismissForm) dismissForm.classList.add('hidden');
        caseSelect?.focus();
        updateConvertState();
    }
    function showDismiss(){
        if (dismissForm) dismissForm.classList.remove('hidden');
        if (convertForm) convertForm.classList.add('hidden');
    }

    function updateConvertState(){
        if (!caseSelect || !btnConvert) return;
        const val = caseSelect.value;
        if (!val) {
            btnConvert.disabled = true;
            if (otherInput) { otherInput.classList.add('hidden'); otherInput.value=''; otherInput.removeAttribute('required'); }
            return;
        }
        if (val === 'Others') {
            if (otherInput) {
                otherInput.classList.remove('hidden');
                otherInput.setAttribute('required','');
                btnConvert.disabled = otherInput.value.trim().length === 0;
            } else {
                btnConvert.disabled = true;
            }
        } else {
            if (otherInput) { otherInput.classList.add('hidden'); otherInput.value=''; otherInput.removeAttribute('required'); }
            btnConvert.disabled = false;
        }
    }

    btnValidate?.addEventListener('click', showConvert);
    btnDismiss?.addEventListener('click', showDismiss);
    caseSelect?.addEventListener('change', updateConvertState);
    otherInput?.addEventListener('input', updateConvertState);
    // Suggestion chips for dismissal reason
    const suggestButtons = document.querySelectorAll('.dismiss-suggest');
    const dismissClearBtn = document.getElementById('dismissClear');
    const dismissReason = document.getElementById('dismissReason');

    if (suggestButtons && dismissReason) {
        suggestButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const txt = btn.dataset && btn.dataset.suggest ? btn.dataset.suggest : btn.textContent.trim();
                if (!txt) return;
                // if textarea has content, append on new line; otherwise replace
                if (dismissReason.value.trim()) {
                    // avoid duplicate suggestions
                    const lines = dismissReason.value.split(/\r?\n/).map(l=>l.trim()).filter(l=>l!=='');
                    if (!lines.includes(txt)) {
                        dismissReason.value = dismissReason.value.trim() + "\n" + txt;
                    }
                } else {
                    dismissReason.value = txt;
                }
                dismissReason.focus();
            });
        });
    }
    if (dismissClearBtn && dismissReason) {
        dismissClearBtn.addEventListener('click', () => { dismissReason.value = ''; dismissReason.focus(); });
    }

    // Case suggestion chips (quick select)
    const caseSuggestBtns = document.querySelectorAll('.case-suggest');
    if (caseSuggestBtns && caseSuggestBtns.length) {
        caseSuggestBtns.forEach(c => {
            c.addEventListener('click', () => {
                const v = c.dataset.value || c.textContent.trim();
                if (!v) return;
                const sel = document.getElementById('caseTypeSelect');
                const other = document.getElementById('otherCaseInput');
                if (!sel) return;
                // try to select exact option
                let found = false;
                for (let i=0;i<sel.options.length;i++){
                    if (sel.options[i].value === v){ sel.selectedIndex = i; found = true; break; }
                }
                if (!found){
                    // set to Others and fill other input
                    for (let i=0;i<sel.options.length;i++){ if (sel.options[i].value === 'Others'){ sel.selectedIndex = i; break; } }
                    if (other){ other.classList.remove('hidden'); other.value = v; other.setAttribute('required',''); }
                } else {
                    if (other){ other.classList.add('hidden'); other.value = ''; other.removeAttribute('required'); }
                }
                sel.dispatchEvent(new Event('change'));
                sel.focus();
            });
        });
    }

    updateConvertState();
})();
</script>

<!-- OLD SCRIPT UNIQUE PARTS ONLY -->
<script>
function addRespondent(){
    const c=document.getElementById('respondents-container'); if(!c) return;
    const i=document.createElement('input'); 
    i.type='text'; 
    i.name='respondents[]'; 
    i.className='w-full px-3 py-2 rounded-lg border border-gray-300 respondent-input focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/80';
    c.appendChild(i); 
    setupAutocomplete();
}

function setupAutocomplete(){ 
    if(typeof $==='undefined'||!$.fn.autocomplete) return; 
    $('.respondent-input').autocomplete({source:'search_residents.php',minLength:1}); 
}

function previewImage(src){
    const modal=document.getElementById('imgPreviewModal');
    const img=document.getElementById('imgPreviewTag');
    if(!modal||!img) return;
    img.src=src;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closePreview(){
    const modal=document.getElementById('imgPreviewModal');
    if(!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

$(document).ready(function(){
            setupAutocomplete();
            const btnValidate = document.getElementById('btnValidate');
            const convertForm = document.getElementById('convertForm');
            const caseTypeSelect = document.getElementById('caseTypeSelect');
            const btnConvert = document.getElementById('btnConvert');
            // kebab menu toggle
            const kebabBtn = document.getElementById('kebabBtn');
            const kebabMenu = document.getElementById('kebabMenu');
            if(kebabBtn && kebabMenu){
                kebabBtn.addEventListener('click', function(e){
                    e.stopPropagation();
                    kebabMenu.classList.toggle('hidden');
                });
                document.addEventListener('click', function(){
                    if(!kebabMenu.classList.contains('hidden')) kebabMenu.classList.add('hidden');
                });
            }
            if(btnValidate && convertForm){
                btnValidate.addEventListener('click', function(){
                    convertForm.classList.remove('hidden');
                    caseTypeSelect?.focus();
                    if(btnConvert) btnConvert.disabled = (caseTypeSelect?.value==='');
                });
            }
            if(caseTypeSelect && btnConvert){
                caseTypeSelect.addEventListener('change', function(){
                    btnConvert.disabled = (this.value==='');
                });
            }
});
</script>

            <?php if($editing && !$is_case && !$is_rejected && !$is_dismissed): ?>
                <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function(){
                        var el = document.getElementById('respondent-name');
                        if (!el || !window.Tagify) return;
                        try {
                            // Injected resident names for Tagify whitelist
                            var names = <?php echo isset($resident_whitelist_json) ? $resident_whitelist_json : '[]'; ?>;

                            var tagify = new Tagify(el, {
                                whitelist: names,
                                enforceWhitelist: false,
                                dropdown: { enabled: 1, maxItems: 20, classname: 'tags-look', position: 'text' },
                                originalInputValueFormat: valuesArr => JSON.stringify(valuesArr.map(v => ({ value: v.value })))
                            });
                            // expose for other scripts to add tags when entering edit mode
                            window.tagifyRespondent = tagify;

                            // Normalize function: strip middle names (keep First + Last)
                            function normalizeName(n) {
                                if (!n || !n.trim()) return '';
                                n = n.trim().replace(/\s+/g,' ');
                                var parts = n.split(' ');
                                if (parts.length === 1) return parts[0];
                                var firstParts = parts.slice(0, parts.length - 1);
                                return firstParts.join(' ') + ' ' + parts[parts.length - 1];
                            }

                            // If the input contains Tagify JSON prepopulated server-side, load it (normalize to First Last)
                            var existing = el.value || '';
                            if (existing.trim().startsWith('[')) {
                                try {
                                    var parsed = JSON.parse(existing);
                                    var toAdd = parsed.map(function(i){ return (i && i.value) ? normalizeName(i.value) : null; }).filter(Boolean);
                                    // dedupe client-side
                                    toAdd = Array.from(new Set(toAdd));
                                    if (toAdd.length) tagify.addTags(toAdd);
                                } catch(e) { /* ignore malformed JSON */ }
                            } else if (existing.indexOf(',') !== -1) {
                                var parts = existing.split(',').map(function(s){ return normalizeName(s.trim()); }).filter(Boolean);
                                parts = Array.from(new Set(parts));
                                if (parts.length) tagify.addTags(parts);
                            } else if (existing.trim() !== '') {
                                tagify.addTags([normalizeName(existing.trim())]);
                            }
                        } catch(e) { console.warn('Tagify init failed', e); }
                        // If the URL contains ?add_respondent=Name, add that tag and focus
                        try {
                            var params = new URLSearchParams(window.location.search);
                            var addName = params.get('add_respondent');
                            if (addName && window.tagifyRespondent) {
                                // decode and normalize then add if not already present
                                try { addName = decodeURIComponent(addName); } catch(e){}
                                function normalizeName(n){ if(!n||!n.trim()) return ''; n = n.trim().replace(/\s+/g,' '); var p=n.split(' '); if(p.length===1) return p[0]; var firstParts = p.slice(0, p.length-1); return firstParts.join(' ') + ' ' + p[p.length-1]; }
                                try { addName = normalizeName(addName); } catch(e){}
                                var existing = window.tagifyRespondent.value.map(function(t){ return (t && t.value) ? normalizeName(t.value) : ''; });
                                if (existing.indexOf(addName) === -1) {
                                    window.tagifyRespondent.addTags([addName]);
                                }
                                // remove the param from URL without reloading
                                params.delete('add_respondent');
                                var newUrl = window.location.pathname + '?' + params.toString();
                                window.history.replaceState({}, document.title, newUrl);
                                // focus the Tagify input
                                setTimeout(function(){ var inputEl = document.querySelector('#respondent-name'); if(inputEl) inputEl.focus(); }, 80);
                            }
                        } catch(e) { /* ignore */ }
                    });
                </script>
            <?php endif; ?>

<!-- Image Preview Modal -->
<div id="imgPreviewModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
    <div class="relative max-w-4xl w-full">
        <button onclick="closePreview()" class="absolute -top-4 -right-4 w-10 h-10 rounded-full bg-white text-gray-700 flex items-center justify-center shadow-lg hover:bg-primary-600 hover:text-white transition"><i class="fa fa-xmark text-lg"></i></button>
        <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
            <img id="imgPreviewTag" src="" alt="Preview" class="w-full max-h-[80vh] object-contain bg-black" />
        </div>
    </div>
</div>

<?php $conn->close(); ?>

</body>
</html>