<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
$success_message = '';
$error_message = '';

// Helper: resolve resident id by full name (with or without middle)
function getResidentId($conn, $full_name) {
    $full_name = preg_replace('/\s+/', ' ', trim($full_name));
    if ($full_name === '') return null;

    $needle = mb_strtolower($full_name);

    // try exact matches first (with or without middle)
    $sql = "SELECT Resident_ID, First_Name, Middle_Name, Last_Name FROM resident_info";
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $a = mb_strtolower(trim($row['First_Name'] . ' ' . ($row['Middle_Name'] ?? '') . ' ' . $row['Last_Name']));
            $b = mb_strtolower(trim($row['First_Name'] . ' ' . $row['Last_Name']));
            if ($needle === $a || $needle === $b) {
                return (int)$row['Resident_ID'];
            }
        }
        $result->close();
    }

    // fallback: try matching by first and last name parts
    $parts = preg_split('/\s+/', $full_name);
    if (count($parts) >= 2) {
        $first = $conn->real_escape_string($parts[0]);
        $last = $conn->real_escape_string(end($parts));
        $q = $conn->prepare("SELECT Resident_ID FROM resident_info WHERE First_Name LIKE ? AND Last_Name LIKE ? LIMIT 1");
        if ($q) {
            $likeFirst = $first;
            $likeLast = $last;
            $q->bind_param('ss', $likeFirst, $likeLast);
            $q->execute();
            $r = bpamis_stmt_get_result($q);
            if ($r && $r->num_rows > 0) {
                $id = (int)$r->fetch_assoc()['Resident_ID'];
                $q->close();
                return $id;
            }
            $q->close();
        }
    }

    // last resort: LIKE search on concatenated name
    $likePattern = '%' . str_replace(' ', '%', $full_name) . '%';
    $q = $conn->prepare("SELECT Resident_ID FROM resident_info WHERE CONCAT(First_Name,' ',COALESCE(Middle_Name,''),' ',Last_Name) LIKE ? OR CONCAT(First_Name,' ',Last_Name) LIKE ? LIMIT 1");
    if ($q) {
        $q->bind_param('ss', $likePattern, $likePattern);
        $q->execute();
        $r = bpamis_stmt_get_result($q);
        if ($r && $r->num_rows > 0) {
            $id = (int)$r->fetch_assoc()['Resident_ID'];
            $q->close();
            return $id;
        }
        $q->close();
    }

    return null;
}
// AJAX endpoint: quick duplicate-check for complaints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_check_duplicates'])) {
    // Use shared $conn from server/server.php
    $db = $conn;
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $likeTitle = '%' . $db->real_escape_string(mb_substr($title,0,80)) . '%';
    $likeDesc = '%' . $db->real_escape_string(mb_substr($desc,0,120)) . '%';
    $out = [];
    $sql = "SELECT Complaint_ID, Complaint_Title, Complaint_Details, Date_Filed FROM COMPLAINT_INFO WHERE (Complaint_Details LIKE ? OR Complaint_Title LIKE ?) ORDER BY Date_Filed DESC LIMIT 6";
    if ($q = $db->prepare($sql)) {
        $q->bind_param('ss', $likeDesc, $likeTitle);
        $q->execute();
        $r = bpamis_stmt_get_result($q);
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                'id' => $row['Complaint_ID'],
                'title' => $row['Complaint_Title'],
                'snippet' => mb_substr(strip_tags($row['Complaint_Details']), 0, 180),
                'date' => $row['Date_Filed']
            ];
        }
        $q->close();
    }
    header('Content-Type: application/json'); echo json_encode($out); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $conn provided by server/server.php
    $complainant_name      = trim($_POST['complainant_name'] ?? '');
    $rawRespondents        = $_POST['respondent_name'] ?? '';
    // Support Tagify JSON payload for complainant (Tagify posts JSON array when used)
    if (is_string($complainant_name) && str_starts_with(ltrim($complainant_name), '[')) {
        $dec = json_decode($complainant_name, true) ?? [];
        if (!empty($dec) && !empty($dec[0]['value'])) {
            $complainant_name = trim($dec[0]['value']);
        }
    }
    $complaint_description = trim($_POST['complaint_description'] ?? '');
    $incident_date         = trim($_POST['incident_date'] ?? '');
    $incident_time         = trim($_POST['incident_time'] ?? ''); // optional
    $status = 'Pending';
    // If the form included the Record Purposes checkbox, treat this complaint as Record Purposes
    $isRecordPurposes = !empty($_POST['record_purposes']);
    if ($isRecordPurposes) {
        $status = 'Record Purposes';
    }

    // Detect Tagify JSON vs comma list for respondents
    $respondent_names = [];
    if (is_string($rawRespondents) && str_starts_with(ltrim($rawRespondents), '[')) {
        $decoded = json_decode($rawRespondents, true) ?? [];
        foreach ($decoded as $item) { if (!empty($item['value'])) $respondent_names[] = trim($item['value']); }
    } else {
        $respondent_names = array_filter(array_map('trim', preg_split('/\s*,\s*/', $rawRespondents)));
    }

    // Server-side dedupe: prevent same respondent being added multiple times
    if (!empty($respondent_names) && is_array($respondent_names)) {
        // Normalize spacing/casing for dedupe but preserve original capitalization
        $seen = [];
        $unique = [];
        foreach ($respondent_names as $rnm) {
            $k = mb_strtolower(preg_replace('/\s+/', ' ', trim($rnm)));
            if ($k === '') continue;
            if (!isset($seen[$k])) { $seen[$k] = true; $unique[] = $rnm; }
        }
        $respondent_names = array_values($unique);
    }

    // Multi-file attachments (drag & drop) - store semicolon-separated relative paths
    $attachment_paths = [];
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
        foreach ($_FILES['attachments']['name'] as $i => $nm) {
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $size = (int)$_FILES['attachments']['size'][$i];
            // Size limit ~20MB per file
            if ($size > 20*1024*1024) continue;
            $safeName = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/','_', $nm);
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $uploadDir.$safeName)) {
                $attachment_paths[] = 'uploads/'.$safeName;
            }
        }
    }
    $attachment_path_field = $attachment_paths ? implode(';', $attachment_paths) : null;

    if ($complainant_name && $complaint_description && $incident_date) {
        $complaint_title = mb_substr($complaint_description, 0, 60);
        if ($complaint_title === '') { $complaint_title = 'Complaint '.date('Y-m-d H:i'); }
        $complainant_id = getResidentId($conn, $complainant_name);
        $main_respondent_id = null;
        if (!empty($respondent_names)) { $main_respondent_id = getResidentId($conn, $respondent_names[0]); }

    // Choose insert columns based on optional Attachment_Path, Incident Date/Time and case_type existing in schema
    $hasAttachmentColumn = false; $hasIncidentTimeColumn = false; $hasIncidentDateColumn = false; $hasCaseTypeColumn = false;
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Attachment_Path'")) { if ($res->num_rows>0) $hasAttachmentColumn=true; $res->close(); }
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Incident_Time'")) { if ($res->num_rows>0) $hasIncidentTimeColumn=true; $res->close(); }
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Incident_Date'")) { if ($res->num_rows>0) $hasIncidentDateColumn=true; $res->close(); }
    if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'case_type'")) { if ($res->num_rows>0) $hasCaseTypeColumn=true; $res->close(); }

        // Detect columns for per-year numbering if present
        $hasComplaintYear = false; $hasComplaintSeq = false; $hasComplaintNo = false;
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Complaint_Year'")) { if ($res->num_rows>0) $hasComplaintYear=true; $res->close(); }
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Complaint_Seq'")) { if ($res->num_rows>0) $hasComplaintSeq=true; $res->close(); }
        if ($res=$conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Complaint_No'")) { if ($res->num_rows>0) $hasComplaintNo=true; $res->close(); }

        // Use server timestamp (Asia/Manila) for Date_Filed (time of submission)
        try {
            $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $date_filed_now = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // fallback to server time if timezone not available
            $date_filed_now = date('Y-m-d H:i:s');
        }

        $columns = "Resident_ID, Respondent_ID, Complaint_Title, Complaint_Details, Date_Filed, Status";
        $placeholders = "?,?,?,?,?,?";
        $types = 'iissss';
        // Date_Filed now receives the submission timestamp; incident date/time go into their own columns when present
        $values = [$complainant_id, $main_respondent_id, $complaint_title, $complaint_description, $date_filed_now, $status];

        // If the table has per-year numbering columns, compute the next sequence for the current year
        $generated_complaint_no = null;
        $currentYear = date('Y');
        if ($hasComplaintYear && $hasComplaintSeq) {
            // Find max seq for current year
            $q = $conn->prepare('SELECT MAX(Complaint_Seq) AS mx FROM COMPLAINT_INFO WHERE Complaint_Year = ?');
            if ($q) {
                $q->bind_param('i', $currentYear);
                $q->execute();
                $r = bpamis_stmt_get_result($q);
                $row = $r->fetch_assoc();
                $nextSeq = ($row && $row['mx']) ? ((int)$row['mx'] + 1) : 1;
                $q->close();
            } else {
                // fallback
                $nextSeq = 1;
            }
            $columns .= ", Complaint_Year, Complaint_Seq";
            $placeholders .= ",?,?";
            $types .= 'ii';
            $values[] = (int)$currentYear;
            $values[] = (int)$nextSeq;
            if ($hasComplaintNo) {
                $generated_complaint_no = sprintf('%s-%04d', $currentYear, $nextSeq);
                $columns .= ", Complaint_No";
                $placeholders .= ",?";
                $types .= 's';
                $values[] = $generated_complaint_no;
            }
        } elseif ($hasComplaintNo) {
            // Only Complaint_No exists -- try to find last for current year using LIKE pattern
            $pattern = $currentYear . '-%';
            $q = $conn->prepare("SELECT Complaint_No FROM COMPLAINT_INFO WHERE Complaint_No LIKE ? ORDER BY Complaint_No DESC LIMIT 1");
            if ($q) {
                $q->bind_param('s', $pattern);
                $q->execute();
                $r = bpamis_stmt_get_result($q);
                $row = $r->fetch_assoc();
                $nextSeq = 1;
                if ($row && !empty($row['Complaint_No'])) {
                    $parts = explode('-', $row['Complaint_No']);
                    $lastSeq = (int)end($parts);
                    $nextSeq = $lastSeq + 1;
                }
                $q->close();
            } else { $nextSeq = 1; }
            $generated_complaint_no = sprintf('%s-%04d', $currentYear, $nextSeq);
            $columns .= ", Complaint_No";
            $placeholders .= ",?";
            $types .= 's';
            $values[] = $generated_complaint_no;
        }

        // If table supports storing the actual incident date/time separately, include them
        if ($hasIncidentDateColumn) { $columns .= ", Incident_Date"; $placeholders .= ",?"; $types .= 's'; $values[] = $incident_date ?: null; }
        if ($hasIncidentTimeColumn) { $columns .= ", Incident_Time"; $placeholders .= ",?"; $types .= 's'; $values[] = $incident_time ?: null; }
    // If the form asked to mark as Record Purposes and the table has a case_type column, include it
    if ($hasCaseTypeColumn && $isRecordPurposes) { $columns .= ", case_type"; $placeholders .= ",?"; $types .= 's'; $values[] = 'record purposes'; }

    if ($hasAttachmentColumn) { $columns .= ", Attachment_Path"; $placeholders .= ",?"; $types .= 's'; $values[] = $attachment_path_field; }

        $stmt = $conn->prepare("INSERT INTO COMPLAINT_INFO ($columns) VALUES ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                $complaint_id = $stmt->insert_id;
                // human-friendly reference
                $complaint_ref = 'COMP#' . str_pad((int)$complaint_id, 2, '0', STR_PAD_LEFT);
                if (count($respondent_names) > 1) {
                    $ins = $conn->prepare('INSERT INTO COMPLAINT_RESPONDENTS (Complaint_ID, Respondent_ID) VALUES (?, ?)');
                    for ($i=1;$i<count($respondent_names);$i++) {
                        $rid = getResidentId($conn, $respondent_names[$i]);
                        if ($rid) { $ins->bind_param('ii', $complaint_id, $rid); $ins->execute(); }
                    }
                    $ins->close();
                }
                // If this was marked for Record Purposes, also create a blotter entry
                if ($isRecordPurposes) {
                    // Use complaint description or title as blotter description
                    $raw_details = $complaint_description ?: $complaint_title;
                    $raw_details = trim(preg_replace('/\s+/u', ' ', (string)$raw_details));
                    $blotter_description = $raw_details !== '' ? $raw_details : ('Record Purposes - ' . ($complaint_title ?? 'Complaint'));
                    $blotter_description = strip_tags($blotter_description);
                    $max_len = 1000;
                    if (mb_strlen($blotter_description) > $max_len) {
                        $blotter_description = mb_substr($blotter_description, 0, $max_len) . '...';
                    }
                    // reported_by: use the complainant name provided on the form
                    $reported_by = $complainant_name ?: '';
                    $date_reported = date('Y-m-d H:i:s');
                    $stmtB = $conn->prepare("INSERT INTO blotter_info (Blotter_Description, Reported_By, Date_Reported, Complaint_ID) VALUES (?, ?, ?, ?)");
                    if ($stmtB) {
                        $stmtB->bind_param('sssi', $blotter_description, $reported_by, $date_reported, $complaint_id);
                        $stmtB->execute();
                        $stmtB->close();
                    }
                }
                // Build a success message that includes the compact reference and any generated complaint no
                if (!empty($generated_complaint_no)) {
                    $success_message = 'Complaint successfully recorded. Reference: ' . htmlspecialchars($complaint_ref) . ' — Complaint No: ' . htmlspecialchars($generated_complaint_no);
                } else {
                    $success_message = 'Complaint successfully recorded. Reference: ' . htmlspecialchars($complaint_ref);
                }
            } else {
                $error_message = 'Failed to save complaint: '.$stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = 'Database error: '.$conn->error;
        }
    } else {
        $error_message = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Add Complaint</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,0.10), 0 4px 18px -2px rgba(6,90,143,0.20)'}, keyframes:{fadeIn:{'0%':{opacity:0,transform:'translateY(4px)'},'100%':{opacity:1,transform:'translateY(0)'}},pulseSoft:{'0%,100%':{opacity:1},'50%':{opacity:.55}}}, animation:{'fade-in':'fadeIn .5s ease-out','pulse-soft':'pulseSoft 3s ease-in-out infinite'} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
    <style>
        .bg-orbs:before, .bg-orbs:after { content:""; position:absolute; border-radius:9999px; filter:blur(70px); opacity:.35; }
        .bg-orbs:before { width:480px; height:480px; background:linear-gradient(135deg,#7cccfd,#0c9ced); top:-160px; left:-140px; }
        .bg-orbs:after { width:420px; height:420px; background:linear-gradient(135deg,#bae2fd,#7cccfd); bottom:-140px; right:-120px; }
        .glass { background:linear-gradient(145deg,rgba(255,255,255,.88),rgba(255,255,255,.65)); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); }
        .input-base { width:100%; border-radius:0.5rem; border:1px solid rgba(209,213,219,.7); background:rgba(255,255,255,.7); padding:.625rem .75rem; font-size:.875rem; transition:.2s; }
        .input-base:not(textarea){ height:44px; line-height:1.2; }
        .input-base:focus { outline:none; background:#fff; border-color:#36b3f9; box-shadow:0 0 0 4px rgba(12,156,237,.25); }
        .field-label { font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; display:flex; gap:4px; align-items:center; color:#4b5563; }
        .thumb-tile { position:relative; }
        .thumb-tile .overlay { position:absolute; inset:0; background:linear-gradient(135deg,rgba(0,0,0,.55),rgba(0,0,0,.35)); display:flex; flex-direction:column; justify-content:center; align-items:center; gap:.5rem; opacity:0; transition:.25s; }
        .thumb-tile:hover .overlay { opacity:1; }
        .action-row { display:flex; gap:.5rem; }
        .thumb-btn { height:34px; width:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:.75rem; background:rgba(255,255,255,.92); color:#0c9ced; font-size:.85rem; font-weight:600; box-shadow:0 2px 6px -1px rgba(0,0,0,.25); backdrop-filter:blur(4px); }
        .thumb-btn:hover { background:#fff; }
        .thumb-btn.danger { color:#dc2626; }
        /* Tagify tweaks: wrap tags, limit height so they don't push surrounding layout, and keep tags removable */
        .tagify {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            padding: .25rem .35rem;
            align-items: center;
            min-height: 44px;
            max-height: 7.5rem; /* ~120px */
            overflow: auto;
            border-radius: .5rem;
        }
        .tagify__tag {
            white-space: normal;
            line-height: 1.15;
            max-width: calc(100% - 40px);
        }
        .tagify__input {
            min-width: 120px;
            flex: 1 1 160px;
            padding: 6px 0;
        }
        /* Mobile adjustments: compact hero and form for small screens */
        @media (max-width: 640px) {
            /* Prevent horizontal scroll */
            html, body {
                overflow-x: hidden !important;
                max-width: 100vw !important;
            }
            
            body {
                position: relative !important;
            }
            
            /* Preserve sidebar font sizes */
            #sidebar, #sidebar *, 
            #sidebar p, #sidebar span, #sidebar label, #sidebar div,
            #sidebar button, #sidebar a, #sidebar h1, #sidebar h2, #sidebar h3, #sidebar h4,
            #sidebar input, #sidebar select, #sidebar textarea,
            #sidebar i.fas, #sidebar i.far, #sidebar i.fa {
                font-size: inherit !important;
            }
            
            /* Reduce background orbs */
            .bg-orbs:before {
                width: 280px !important;
                height: 280px !important;
                top: -100px !important;
                left: -80px !important;
                filter: blur(48px) !important;
            }
            .bg-orbs:after {
                width: 240px !important;
                height: 240px !important;
                bottom: -80px !important;
                right: -60px !important;
                filter: blur(48px) !important;
            }
            
            /* Header - compact */
            header.max-w-screen-2xl {
                padding-top: 1rem !important;
            }
            
            header .glass {
                padding: 0.75rem !important;
            }
            
            header h1 {
                font-size: 1.125rem !important;
            }
            
            header h1 .w-12 {
                width: 2.25rem !important;
                height: 2.25rem !important;
            }
            
            header h1 i {
                font-size: 0.875rem !important;
            }
            
            header p {
                font-size: 0.7rem !important;
                margin-top: 0.5rem !important;
            }
            
            header .flex.items-center.gap-3 {
                gap: 0.375rem !important;
            }
            
            header .px-3.py-1 {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Main content spacing */
            main {
                margin-top: 1rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-bottom: 1rem !important;
            }
            
            /* Form section */
            section.glass {
                padding: 0.75rem !important;
            }
            
            /* Section heading */
            section h2 {
                font-size: 0.85rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Success/Error messages */
            .border-green-300,
            .border-red-300 {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Form spacing */
            form.space-y-10 > * + * {
                margin-top: 1rem !important;
            }
            
            .grid.md\\:grid-cols-2.gap-6 {
                gap: 1rem !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 3px !important;
                gap: 4px !important;
            }
            
            /* Input fields */
            .input-base {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.625rem !important;
                border-radius: 0.5rem !important;
            }
            
            .input-base:not(textarea) {
                height: 38px !important;
            }
            
            textarea.input-base {
                min-height: 80px !important;
            }
            
            /* Helper text */
            p.text-\[11px\],
            p.text-xs {
                font-size: 9px !important;
                margin-top: 0.25rem !important;
            }
            
            /* Dropzone */
            #dropZone {
                padding: 0.75rem !important;
            }
            
            #dropZone i {
                font-size: 1.25rem !important;
            }
            
            #dropZone p {
                font-size: 0.7rem !important;
            }
            
            #dropZone .text-\[10px\] {
                font-size: 8px !important;
            }
            
            /* File grid */
            #fileGrid {
                margin-top: 0.5rem !important;
                gap: 0.5rem !important;
            }
            
            #fileGrid .thumb-tile {
                font-size: 10px !important;
            }
            
            /* Form buttons */
            form button,
            form a.inline-flex {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            .flex.gap-3 {
                gap: 0.5rem !important;
            }
            
            .pt-4 {
                padding-top: 0.75rem !important;
            }
            
            /* Icons */
            .fa, .fas, .far {
                font-size: 0.7rem !important;
            }
            
            header .fa {
                font-size: 0.65rem !important;
            }
            
            /* Decorative elements in glass cards */
            .glass .absolute.w-48,
            .glass .absolute.w-64,
            .glass .absolute.-top-10,
            .glass .absolute.-bottom-12 {
                display: none !important;
            }
            
            /* Reduce border radius for compact feel */
            .rounded-2xl {
                border-radius: 0.75rem !important;
            }
            
            .rounded-xl {
                border-radius: 0.5rem !important;
            }
            
            .rounded-lg {
                border-radius: 0.375rem !important;
            }
            
            /* Tagify compact */
            .tagify {
                min-height: 38px !important;
                max-height: 6rem !important;
                padding: 0.25rem 0.35rem !important;
            }
            
            .tagify__tag {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            .tagify__input {
                min-width: 100px !important;
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Page Heading -->
    <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
                <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
                    <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
                    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-file-pen text-lg"></i></span>
                                <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Add Complaint</span>
                            </h1>
                            <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">File a new complaint for barangay records. Provide full names for accurate resident matching.</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                            <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-database text-primary-500"></i> Auto Link Residents</div>
                        </div>
                    </div>
                </div>
            </header>

    <!-- Form Section -->
    <main class="relative z-10 max-w-7xl mx-auto px-4 md:px-8 mt-10 pb-24">
        <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 p-6 md:p-10 animate-fade-in">
            <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center gap-2"><i class="fa fa-circle-plus text-primary-500"></i> New Complaint Details</h2>
                <a href="home-secretary.php" class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
            <?php if($success_message): ?>
                <div class="mb-6 rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-check-circle mt-0.5"></i><span><?php echo htmlspecialchars($success_message); ?></span></div>
            <?php elseif($error_message): ?>
                <div class="mb-6 rounded-lg border border-red-300 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-circle-exclamation mt-0.5"></i><span><?php echo htmlspecialchars($error_message); ?></span></div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" class="space-y-10" id="complaintForm">
                <!-- Row 1: Complainant / Respondent -->
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label for="complainant-name" class="field-label"><i class="fa fa-user"></i> Complainant Name</label>
                        <?php
                        $suggestName = '';
                        if (!empty($_SESSION['user_id'])) {
                            $uid = (int)$_SESSION['user_id'];
                            $rsn = $conn->query("SELECT First_Name, Middle_Name, Last_Name FROM resident_info WHERE Resident_ID = $uid");
                            if($rsn && $rsn->num_rows){
                                $nm = $rsn->fetch_assoc();
                                $suggestName = preg_replace('/\s+/', ' ', trim($nm['First_Name'].' '.($nm['Middle_Name']??'').' '.$nm['Last_Name']));
                            }
                        }
                        ?>
                        <input type="text" id="complainant-name" name="complainant_name" class="input-base" value="<?php echo htmlspecialchars($suggestName); ?>" placeholder="Full name" required />
                        <p class="mt-1 text-[11px] text-gray-500">Auto-filled with your account name.</p>
                    </div>
                    <div class="space-y-2">
                            <label for="respondent-name" class="block text-sm font-medium text-gray-700">Respondent Name(s) <span class="text-gray-400 font-normal">(Optional)</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400"><i class="fa-solid fa-user-group"></i></span>
                                <input type="text" id="respondent-name" name="respondent_name" class="w-full pl-10 pr-3 py-3 rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition form-input" placeholder="Type and select respondent names (optional)">
                            </div>
                            <p class="text-xs text-gray-500 italic">Use full names (First Middle Last). You can add multiple respondents.</p>
                        </div>
                </div>
                <!-- Row 2: Date / Time -->
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label for="incident-date" class="field-label"><i class="fa fa-calendar-day"></i> Incident Date</label>
                        <input type="date" id="incident-date" name="incident_date" class="input-base" required max="<?= date('Y-m-d') ?>" />
                    </div>
                    <div>
                        <label for="incident-time" class="field-label"><i class="fa fa-clock"></i> Incident Time (Optional)</label>
                        <input type="time" id="incident-time" name="incident_time" class="input-base" />
                        <p class="mt-1 text-[11px] text-gray-500">Leave blank if unknown.</p>
                    </div>
                </div>
                <!-- Row 3: Description full width -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="complaint-description" class="field-label"><i class="fa fa-align-left"></i> Description</label>
                        <button type="button" id="open-tips" class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-md bg-white/80 backdrop-blur border border-gray-200 text-primary-700 hover:text-primary-800 hover:bg-white shadow-sm transition pointer-events-auto" title="Tips in writing complaint" aria-haspopup="dialog" aria-controls="tips-modal" role="button" tabindex="0">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 text-primary-600 ring-1 ring-white/60"><i class="fa fa-lightbulb text-sm"></i></span>
                            <span class="hidden sm:inline text-[11px] font-semibold">Tips</span>
                        </button>
                    </div>
                    <textarea id="complaint-description" name="complaint_description" rows="5" class="input-base resize-y" placeholder="Provide a clear statement of the incident..." required></textarea>
                    <p class="mt-1 text-[11px] text-gray-500">Be as specific as possible. This will help in case assessment.</p>
                </div>
                <!-- Row 4: Attachments full width -->
                <div>
                    <label class="field-label"><i class="fa fa-paperclip"></i> Attachments (Optional)</label>
                    <div id="dropZone" class="mt-1 border-2 border-dashed border-primary-300/70 rounded-xl p-6 flex flex-col items-center justify-center gap-3 text-sm text-gray-500 bg-white/60 hover:bg-white transition cursor-pointer">
                        <i class="fa fa-cloud-arrow-up text-primary-500 text-2xl"></i>
                        <p class="text-center leading-snug"><span class="font-medium text-primary-600">Click to browse</span> or drag & drop files here<br><span class="text-[10px] text-gray-400">Images & documents (max 20MB each)</span></p>
                        <input type="file" name="attachments[]" id="attachmentInput" class="hidden" multiple />
                        <div id="fileGrid" class="mt-4 grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 w-full"></div>
                        <p id="fileHint" class="hidden w-full text-[11px] text-gray-500"></p>
                    </div>
                </div>
                <!-- Row 5: Record Purposes checkbox -->
                <div>
                    <label class="inline-flex items-center gap-3">
                        <input type="checkbox" name="record_purposes" value="1" class="h-4 w-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500" />
                        <span class="text-sm text-gray-700">Record Purposes — mark this complaint as record purposes (will create record entries instead of a regular case)</span>
                    </label>
                </div>
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-dashed border-primary-200/60">
                    <a href="home.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                        <i class="fa fa-paper-plane"></i> Submit Complaint
                    </button>
                </div>
            </form>
        </section>
    </main>

    <script>
        // Multi-file drag & drop with previews, view & delete actions
        (function(){
            const dz = document.getElementById('dropZone');
            const input = document.getElementById('attachmentInput');
            const grid = document.getElementById('fileGrid');
            const hint = document.getElementById('fileHint');
            if(!dz) return;

            let dt = new DataTransfer(); // holds current selection

            const over = e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('ring-2','ring-primary-400','bg-white'); };
            const leave = e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('ring-2','ring-primary-400'); };
            ['dragenter','dragover'].forEach(evt=>dz.addEventListener(evt,over));
            ['dragleave','drop'].forEach(evt=>dz.addEventListener(evt,leave));
            dz.addEventListener('click', () => input.click());
            dz.addEventListener('drop', e => { e.preventDefault(); if(e.dataTransfer.files.length){ addFiles(e.dataTransfer.files); }});
            input.addEventListener('change', () => addFiles(input.files));

            function addFiles(fileList){
                Array.from(fileList).forEach(f => {
                    if(f.size > 20*1024*1024) return; // skip >20MB
                    // prevent duplicates by name+size
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
                if(!dt.files.length){ hint.classList.add('hidden'); return; }
                hint.textContent = dt.files.length + ' file(s) ready for upload';
                hint.classList.remove('hidden');
                Array.from(dt.files).forEach((f,idx) => {
                    const ext = f.name.split('.').pop().toLowerCase();
                    const isImage = ['jpg','jpeg','png','gif','webp','bmp'].includes(ext);
                    const tile = document.createElement('div');
                    tile.className = 'thumb-tile relative rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden group';
                    tile.innerHTML = `
                        <div class="w-full aspect-video flex items-center justify-center bg-gray-100 overflow-hidden">
                            ${isImage ? `<img class=\"w-full h-full object-cover\" />` : `<div class=\"flex flex-col items-center justify-center text-gray-500 text-xs p-3\"><i class=\"fa fa-file text-2xl mb-2\"></i><span class=\"break-all leading-tight\">${escapeHtml(f.name)}</span></div>`}
                        </div>
                        <div class="overlay">
                            <div class="action-row">
                                ${isImage ? `<button type=\"button\" class=\"thumb-btn view-btn\" title=\"View\"><i class=\"fa fa-eye\"></i></button>` : ''}
                                <button type="button" class="thumb-btn danger del-btn" title="Remove"><i class="fa fa-trash"></i></button>
                            </div>
                        </div>`;
                    grid.appendChild(tile);
                    if(isImage){
                        const imgEl = tile.querySelector('img');
                        const reader = new FileReader();
                        reader.onload = e => imgEl.src = e.target.result;
                        reader.readAsDataURL(f);
                    }
                    tile.querySelector('.del-btn').addEventListener('click', ()=> removeFile(idx));
                    if(isImage){
                        tile.querySelector('.view-btn').addEventListener('click', ()=> openPreview(f));
                    }
                });
            }

            function escapeHtml(str){ return str.replace(/[&<>"] /g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"," ":"&nbsp;"}[c]||c)); }

            // Simple modal preview
            function openPreview(file){
                const r = new FileReader();
                r.onload = e => {
                    const wrap = document.createElement('div');
                    wrap.className='fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-6';
                    wrap.innerHTML = `<div class=\"relative bg-white rounded-xl shadow-xl max-w-3xl w-full p-4\">
                        <button class=\"absolute -top-3 -right-3 h-10 w-10 rounded-full bg-white text-gray-600 shadow flex items-center justify-center hover:text-red-600 close-btn\"><i class=\"fa fa-xmark\"></i></button>
                        <img src='${e.target.result}' class='w-full h-auto rounded-md object-contain max-h-[70vh]' />
                    </div>`;
                    document.body.appendChild(wrap);
                    wrap.addEventListener('click', ev=>{ if(ev.target===wrap || ev.target.closest('.close-btn')) wrap.remove(); });
                };
                r.readAsDataURL(file);
            }
        })();

        // Tagify Respondents — build whitelist server-side (sorted alphabetically) and init Tagify with mobile-safe dropdown
        (function(){
            <?php
            $__names = [];
            $resn = $conn->query("SELECT First_Name, Middle_Name, Last_Name FROM resident_info");
            if($resn){
                while($r = $resn->fetch_assoc()){
                    $full = preg_replace('/\s+/', ' ', trim($r['First_Name'].' '.($r['Middle_Name']??'').' '.$r['Last_Name']));
                    if($full !== '') $__names[] = $full;
                }
                $resn->close();
            }
            usort($__names, 'strcasecmp');
            ?>
            const names = <?php echo json_encode($__names, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const respondentInput = document.querySelector('#respondent-name');
            const complainantInput = document.querySelector('#complainant-name');
            function initTagifyForInput(inputEl, opts){
                const tagify = new Tagify(inputEl, opts);
                function positionDropdown(){
                    const dd = tagify.DOM.dropdown; if(!dd) return;
                    const wrap = tagify.DOM.scope; const rect = wrap.getBoundingClientRect();
                    dd.style.position = 'fixed'; dd.style.left = Math.round(rect.left) + 'px';
                    dd.style.top = Math.round(rect.bottom + 2) + 'px';
                    dd.style.width = Math.round(rect.width) + 'px'; dd.style.zIndex = '10000'; dd.style.transform = 'none';
                }
                tagify.on('dropdown:show', positionDropdown);
                window.addEventListener('resize', positionDropdown);
                window.addEventListener('orientationchange', ()=> setTimeout(positionDropdown,200));
                inputEl.addEventListener('focus', ()=> setTimeout(positionDropdown, 200));
                return tagify;
            }
            if(respondentInput){
                if(window.Tagify){
                    initTagifyForInput(respondentInput, {
                        whitelist: names,
                        dropdown: { classname: 'tags-look', enabled: 0, maxItems: 10, closeOnSelect: false },
                        enforceWhitelist: false,
                        editTags: 1,
                        originalInputValueFormat: valuesArr => JSON.stringify(valuesArr.map(v => ({ value: v.value })))
                    });
                } else {
                    // lazy-load Tagify if missing
                    const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/@yaireo/tagify'; s.onload = ()=> initTagifyForInput(respondentInput, { whitelist: names, dropdown:{ classname: 'tags-look', enabled:0, maxItems:10, closeOnSelect:false }, enforceWhitelist:false, editTags:1, originalInputValueFormat: valuesArr => JSON.stringify(valuesArr.map(v => ({ value: v.value }))) });
                    document.head.appendChild(s);
                }
            }
            if(complainantInput){
                // single-value Tagify for complainant (if present)
                if(window.Tagify){ initTagifyForInput(complainantInput, { whitelist: names, dropdown:{ maxItems:10, enabled:0, closeOnSelect:true }, enforceWhitelist:true, maxTags:1 }); }
            }
        })();
    </script>

    <!-- Duplicate-check Modal (premium) -->
    <div id="scope-modal" role="dialog" aria-modal="true" aria-labelledby="scope-title" class="fixed inset-0 hidden z-50 items-center justify-center">
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="relative z-10 mx-auto max-w-3xl w-[94%] sm:w-full">
            <div class="relative rounded-2xl p-6 md:p-7 border border-white/60 shadow-[0_18px_50px_-12px_rgba(14,116,144,0.25)] overflow-hidden bg-gradient-to-br from-amber-50 via-white to-yellow-50">
                <div class="absolute -top-16 -right-16 w-56 h-56 bg-gradient-to-br from-primary-200/60 to-primary-400/40 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-gradient-to-tr from-white/50 to-primary-100/50 rounded-full blur-3xl"></div>
                <div class="relative z-10">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-yellow-100 text-yellow-700 ring-1 ring-white/60 shadow-inner"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <div>
                                <h3 id="scope-title" class="text-lg font-semibold text-yellow-800">Possible duplicate complaint(s) found</h3>
                                <p class="text-xs text-yellow-700/80">We found similar complaints — please review before submitting to avoid duplicates.</p>
                            </div>
                        </div>
                        <button type="button" id="cancel-submit" class="p-2 rounded-lg bg-white border border-white/60 text-yellow-700 hover:text-yellow-900 hover:bg-white shadow-sm" aria-label="Cancel" role="button" tabindex="0">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div id="dup-list" class="bg-white/80 p-4 rounded-lg border border-white/60 max-h-72 overflow-auto"></div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" id="proceed-submit" class="px-3 py-2 rounded-md bg-yellow-700 text-white hover:bg-yellow-800">Proceed anyway</button>
                        <button type="button" id="cancel-submit-2" class="px-3 py-2 rounded-md bg-white border border-gray-200 text-yellow-700 hover:bg-gray-50">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Duplicate-check wiring: call server-side quick search to detect similar complaints before actual submit
    (function(){
        function escapeHtml(s){ return String(s).replace(/[&<>"'`]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;','`':'&#096;'}[c])); }
        document.addEventListener('DOMContentLoaded', ()=>{
            const form = document.getElementById('complaintForm'); if(!form) return;
            const modal = document.getElementById('scope-modal');
            const cancelBtns = Array.from(document.querySelectorAll('#cancel-submit, #cancel-submit-2'));
            const proceedBtn = document.getElementById('proceed-submit');
            let allowed = false;

            form.addEventListener('submit', async (e)=>{
                if(allowed) return;
                e.preventDefault();
                const descEl = document.getElementById('complaint-description');
                const dateEl = document.getElementById('incident-date');
                const desc = (descEl?.value || '').trim();
                const date = (dateEl?.value || '').trim();
                const words = (desc.match(/\b\w+\b/g) || []).length;
                if(desc === '' || date === '' || words < 10 || words > 200){
                    if(desc === '') descEl?.classList.add('ring-2','ring-red-300');
                    if(date === '') dateEl?.classList.add('ring-2','ring-red-300');
                    return;
                }
                // ensure there is a title hidden field to match other pages behaviour
                let titleField = document.getElementById('complaint-title');
                if(!titleField){ titleField = document.createElement('input'); titleField.type='hidden'; titleField.id='complaint-title'; titleField.name='complaint_title'; form.appendChild(titleField); }
                titleField.value = desc.substring(0,40) || 'Complaint';

                try{
                    const fd = new FormData(); fd.append('ajax_check_duplicates','1'); fd.append('title', titleField.value); fd.append('description', desc);
                    const resp = await fetch(location.href, { method:'POST', body:fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await resp.json();
                    if(Array.isArray(data) && data.length > 0){
                        const list = modal.querySelector('#dup-list');
                        list.innerHTML = data.map(d => `<div class="p-3 border-b last:border-b-0"><div class="text-sm font-semibold text-gray-800">${escapeHtml(d.title||'(no title)')}</div><div class="text-xs text-gray-500 mt-1">${escapeHtml(d.snippet)}</div><div class="text-xs text-gray-400 mt-1">${escapeHtml(d.date)}</div></div>`).join('');
                        modal.classList.remove('hidden'); modal.classList.add('flex');
                    } else {
                        allowed = true; form.submit();
                    }
                } catch(err){ console.warn('Duplicate-check failed, proceeding', err); allowed = true; form.submit(); }
            });

            cancelBtns.forEach(b=> b?.addEventListener('click', ()=>{ if(modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } }));
            proceedBtn?.addEventListener('click', ()=>{ allowed = true; if(modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } form.submit(); });
        });
    })();
    </script>

    <!-- Tips Modal -->
    <div id="tips-modal" role="dialog" aria-modal="true" aria-labelledby="tips-title" class="fixed inset-0 hidden z-50 flex items-center justify-center">
        <div id="tips-overlay" class="absolute inset-0 bg-black/40"></div>
        <div class="relative z-10 mx-auto max-w-lg w-[92%] sm:w-full">
            <div class="relative rounded-2xl p-6 md:p-7 border border-white/60 shadow-[0_18px_50px_-12px_rgba(14,116,144,0.25)] overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
                <div class="absolute -top-16 -right-16 w-56 h-56 bg-gradient-to-br from-primary-200/60 to-primary-400/40 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-gradient-to-tr from-white/50 to-primary-100/50 rounded-full blur-3xl"></div>
                <div class="relative z-10">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary-100 text-primary-600 ring-1 ring-white/60 shadow-inner"><i class="fa-solid fa-lightbulb"></i></span>
                            <div>
                                <h3 id="tips-title" class="text-lg font-semibold text-sky-900">Tips for a good complaint</h3>
                                <p class="text-xs text-sky-700/80">Write clearly and stick to the facts.</p>
                            </div>
                        </div>
                        <button type="button" id="close-tips" class="p-2 rounded-lg bg-white border border-white/60 text-sky-700 hover:text-sky-900 hover:bg-white shadow-sm pointer-events-auto" aria-label="Close tips" role="button" tabindex="0">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div id="tips-stepper" class="space-y-4">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div class="flex items-center w-full">
                                <div class="flex items-center justify-between w-full">
                                    <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="0" role="button" tabindex="0">
                                        <div class="w-10 h-10 rounded-full bg-sky-600 text-white flex items-center justify-center transition-all">1</div>
                                        <div class="mt-2 text-xs text-sky-800">State facts</div>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                    <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="1" role="button" tabindex="0">
                                        <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">2</div>
                                        <div class="mt-2 text-xs text-sky-800">Be specific</div>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                    <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="2" role="button" tabindex="0">
                                        <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">3</div>
                                        <div class="mt-2 text-xs text-sky-800">Describe evidence</div>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                    <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="3" role="button" tabindex="0">
                                        <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">4</div>
                                        <div class="mt-2 text-xs text-sky-800">State impact</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="tips-contents" class="bg-white/80 p-4 rounded-lg border border-white/60">
                            <div class="tip-pane" data-step="0">
                                <h4 class="font-semibold text-sky-900 mb-2">State the facts chronologically</h4>
                                <p class="text-sm text-sky-800">Write what happened, when and where it happened, and who was involved. Stick to objective facts and the sequence of events.</p>
                            </div>
                            <div class="tip-pane hidden" data-step="1">
                                <h4 class="font-semibold text-sky-900 mb-2">Be specific</h4>
                                <p class="text-sm text-sky-800">Include exact dates, approximate times, locations, and full names if you know them. Small details help investigators follow up.</p>
                            </div>
                            <div class="tip-pane hidden" data-step="2">
                                <h4 class="font-semibold text-sky-900 mb-2">Describe evidence</h4>
                                <p class="text-sm text-sky-800">List any photos, messages, receipts, or witnesses. Attach files using the Attachments panel so evidence is preserved.</p>
                            </div>
                            <div class="tip-pane hidden" data-step="3">
                                <h4 class="font-semibold text-sky-900 mb-2">State the impact</h4>
                                <p class="text-sm text-sky-800">Explain briefly how the incident affected you (harm, loss, inconvenience) and what outcome you are seeking, if any.</p>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" id="tips-prev" class="px-3 py-2 rounded-md bg-white border border-gray-200 text-sky-700 hover:bg-gray-50 pointer-events-auto cursor-pointer" role="button" tabindex="0">Previous</button>
                            <button type="button" id="tips-next" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700 pointer-events-auto cursor-pointer" role="button" tabindex="0">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Tips modal stepper logic
    document.addEventListener('DOMContentLoaded', () => {
        const tipsModal = document.getElementById('tips-modal');
        const tipsOverlay = document.getElementById('tips-overlay');
        const openTips = document.getElementById('open-tips');
        const closeTips = document.getElementById('close-tips');
        const steps = Array.from(document.querySelectorAll('#tips-stepper .step'));
        const panes = Array.from(document.querySelectorAll('#tips-contents .tip-pane'));
        const prevBtn = document.getElementById('tips-prev');
        const nextBtn = document.getElementById('tips-next');
        let currentStepIndex = 0;

        function hideTips(){ if(tipsModal) tipsModal.classList.add('hidden'); }
        function showTips(){ if(tipsModal) tipsModal.classList.remove('hidden'); }

        function showStepAtIndex(i){
            if(i < 0) i = 0;
            if(i >= steps.length) i = steps.length - 1;
            currentStepIndex = i;

            steps.forEach((s,sn)=>{
                const circle = s.querySelector('.w-10');
                if(sn <= currentStepIndex){
                    circle.classList.remove('bg-sky-200','text-sky-800');
                    circle.classList.add('bg-sky-600','text-white');
                } else {
                    circle.classList.remove('bg-sky-600','text-white');
                    circle.classList.add('bg-sky-200','text-sky-800');
                }
            });

            panes.forEach(p=> p.classList.add('hidden'));
            const active = panes.find(p=> parseInt(p.getAttribute('data-step')) === currentStepIndex);
            if(active) active.classList.remove('hidden');

            if(prevBtn) {
                prevBtn.disabled = (currentStepIndex === 0);
                if(currentStepIndex === 0) {
                    prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            if(nextBtn){
                if(currentStepIndex === steps.length - 1){
                    nextBtn.textContent = 'Close';
                } else {
                    nextBtn.textContent = 'Next';
                }
            }
        }

        steps.forEach((s, i)=> {
            s.addEventListener('click', ()=> showStepAtIndex(i));
        });

        if(prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if(currentStepIndex > 0) showStepAtIndex(currentStepIndex - 1);
            });
        }

        if(nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if(currentStepIndex === steps.length - 1){
                    hideTips();
                } else {
                    showStepAtIndex(currentStepIndex + 1);
                }
            });
        }

        openTips?.addEventListener('click', ()=> {
            showTips();
            setTimeout(()=> showStepAtIndex(0), 60);
        });
        closeTips?.addEventListener('click', hideTips);
        tipsOverlay?.addEventListener('click', hideTips);
    });
    </script>

    <?php include 'sidebar_.php'; ?>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
<script>
// Ensure incident date cannot be in the future (defensive client-side check)
document.addEventListener('DOMContentLoaded', function(){
    const inc = document.getElementById('incident-date');
    if(!inc) return;
    const today = new Date().toISOString().slice(0,10);
    // enforce max attribute (in case browser didn't pick up PHP max)
    try{ inc.setAttribute('max', today); }catch(e){}

    function showFutureError(){
        alert('Incident date cannot be in the future. Please select today or a past date.');
        inc.value = today;
        inc.focus();
    }

    inc.addEventListener('change', function(){ if(inc.value && inc.value > today) showFutureError(); });

    // Prevent form submit if date is future (extra guard)
    const form = inc.closest('form') || document.querySelector('form');
    if(form){
        form.addEventListener('submit', function(e){ if(inc.value && inc.value > today){ e.preventDefault(); showFutureError(); } });
    }
});
</script>