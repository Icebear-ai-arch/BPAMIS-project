<?php
// Secretary Case Details - Premium UI aligned with complaint details styling
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php'; // provides $conn

// Accept both `case_id` and `id` for compatibility with different links/refs.
$case_id = null;
if (isset($_GET['case_id'])) {
    $case_id = intval($_GET['case_id']);
} elseif (isset($_GET['id'])) {
    $case_id = intval($_GET['id']);
}
if (empty($case_id)) {
    echo "<p class='text-center text-red-500'>Case ID not provided.</p>";
    exit;
}

// Detect if Attachment_Path and case_type columns exist for later rendering (support flexible schema)
// Resolve actual table names for Linux/case-sensitive hosts
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');
$T_COMPLAINT_INFO = bpamis_table($conn, 'COMPLAINT_INFO');
$T_RESIDENT_INFO = bpamis_table($conn, 'RESIDENT_INFO');
$T_EXTERNAL_COMPLAINANT = bpamis_table($conn, 'external_complainant');
$T_COMPLAINT_RESPONDENTS = bpamis_table($conn, 'COMPLAINT_RESPONDENTS');
$T_MEETING_LOGS = bpamis_table($conn, 'MEETING_LOGS');
$T_SCHEDULE_LIST = bpamis_table($conn, 'schedule_list');
$T_FEEDBACK = bpamis_table($conn, 'feedback');
$T_MEDIATION_INFO = bpamis_table($conn, 'mediation_info');
$T_CONCILIATION = bpamis_table($conn, 'conciliation');
$T_RESOLUTION = bpamis_table($conn, 'resolution');
$T_SETTLEMENT = bpamis_table($conn, 'settlement');
$T_ARBITRATION = bpamis_table($conn, 'arbitration');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_EXTERNAL_COMPLAINANT = bpamis_quote_table($T_EXTERNAL_COMPLAINANT);
$TB_COMPLAINT_RESPONDENTS = bpamis_quote_table($T_COMPLAINT_RESPONDENTS);
$TB_MEETING_LOGS = bpamis_quote_table($T_MEETING_LOGS);
$TB_SCHEDULE_LIST = bpamis_quote_table($T_SCHEDULE_LIST);
$TB_FEEDBACK = bpamis_quote_table($T_FEEDBACK);
$TB_MEDIATION_INFO = bpamis_quote_table($T_MEDIATION_INFO);
$TB_CONCILIATION = bpamis_quote_table($T_CONCILIATION);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_SETTLEMENT = bpamis_quote_table($T_SETTLEMENT);
$TB_ARBITRATION = bpamis_quote_table($T_ARBITRATION);

// Detect optional columns
$hasAttachmentCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Attachment_Path');
$hasCaseTypeCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'case_type');
$hasCertificateCol = bpamis_table_has_column($conn, $T_CASE_INFO, 'Certificate_Path');

$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID', 'respondent_id']);

$hasComplaintRespondents = false;
$tblCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($T_COMPLAINT_RESPONDENTS) . "'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $hasComplaintRespondents = true;
}

// Fetch case + complaint + complainant (+ attachment path / case_type if present)
$selectExtras = '';
if($hasAttachmentCol) $selectExtras .= ', ci.Attachment_Path';
if($hasCaseTypeCol) $selectExtras .= ', ci.case_type';
// Include external complainant identifiers and agreement flag so we can render arbitration UI
$sql = "SELECT cs.Case_ID, cs.case_original_id, cs.Case_Status, cs.Date_Opened, ci.Complaint_ID, ci.Complaint_Title, ci.Complaint_Details, ci.Date_Filed".$selectExtras.",
                                ci.External_Complainant_ID AS External_Complainant_ID, ci.external_complainant_agree AS external_complainant_agree,
                                COALESCE(comp.First_Name, ext.First_Name) AS Complainant_First, COALESCE(comp.Last_Name, ext.Last_Name) AS Complainant_Last
                 FROM {$TB_CASE_INFO} cs
                 LEFT JOIN {$TB_COMPLAINT_INFO} ci ON cs.Complaint_ID = ci.Complaint_ID
                 LEFT JOIN {$TB_RESIDENT_INFO} comp ON ci.Resident_ID = comp.Resident_ID
                 LEFT JOIN {$TB_EXTERNAL_COMPLAINANT} ext ON ci.External_Complainant_ID = ext.External_Complaint_ID
                 WHERE cs.Case_ID = ?";
$stmt = $conn->prepare($sql);
if(!$stmt){ echo "<p class='text-center text-red-500'>Query preparation failed.</p>"; exit; }
$stmt->bind_param('i',$case_id); $stmt->execute(); $result=bpamis_stmt_get_result($stmt);
if($result->num_rows===0){ echo "<p class='text-center text-red-500'>Case not found.</p>"; exit; }
$case = $result->fetch_assoc(); $stmt->close();
$complaint_id = $case['Complaint_ID'];

// If certificate column exists, fetch it (separate query to avoid breaking when column absent)
if ($hasCertificateCol) {
    $cst = $conn->prepare("SELECT Certificate_Path FROM {$TB_CASE_INFO} WHERE Case_ID = ? LIMIT 1");
    if ($cst) {
        $cst->bind_param('i', $case_id);
        $cst->execute();
        $cres = bpamis_stmt_get_result($cst);
        if ($cres && $crow = $cres->fetch_assoc()) {
            $case['Certificate_Path'] = $crow['Certificate_Path'];
        }
        $cst->close();
    }
}

// Derive case type label & style
$caseTypeRaw = $hasCaseTypeCol ? trim((string)$case['case_type']) : '';
$caseType = $caseTypeRaw !== '' ? strtoupper($caseTypeRaw) : 'UNSPECIFIED';
$typeStyles = [
    'CRIMINAL' => 'bg-red-50 text-red-600 border border-red-200',
    'CIVIL'    => 'bg-gray-100 text-gray-700 border border-gray-300'
];
$typeClass = $typeStyles[$caseType] ?? 'bg-primary-50 text-primary-600 border border-primary-200';

// Parse attachments into array (if column present)
$attachments = [];
if($hasAttachmentCol && !empty($case['Attachment_Path'])) {
    $rawParts = explode(';', $case['Attachment_Path']);
    foreach($rawParts as $p){
        $p = trim($p);
        if($p==='') continue;
        $p = ltrim($p,'/');
        $segments = array_map(function($seg){ return rawurlencode($seg); }, explode('/', $p));
        $encoded = implode('/', $segments);
        $attachments[] = [
            'raw' => $p,
            'encoded' => $encoded,
            'ext' => strtolower(pathinfo($p, PATHINFO_EXTENSION))
        ];
    }
}

// Respondents aggregation
$respondent_names=[];

// Get main respondent id (prefer prepared, fallback to direct query)
$main_respondent_id = null;
if ($respondentIdCol) {
    $stmt_main_id = $conn->prepare("SELECT " . bpamis_quote_ident($respondentIdCol) . " AS Respondent_ID FROM {$TB_COMPLAINT_INFO} WHERE Complaint_ID=?");
    if ($stmt_main_id) {
        $stmt_main_id->bind_param('i', $complaint_id);
        $stmt_main_id->execute();
        $rRows = bpamis_stmt_fetch_all_assoc($stmt_main_id);
        if (!empty($rRows)) {
            $main_respondent_id = $rRows[0]['Respondent_ID'] ?? null;
        }
        $stmt_main_id->close();
    } else {
        $q = $conn->query("SELECT " . bpamis_quote_ident($respondentIdCol) . " AS Respondent_ID FROM {$TB_COMPLAINT_INFO} WHERE Complaint_ID = " . (int)$complaint_id);
        if ($q && $r = $q->fetch_assoc()) { $main_respondent_id = $r['Respondent_ID']; }
    }
}

// If we have a main respondent, resolve their name
if ($main_respondent_id) {
    $stmt_main_name = $conn->prepare("SELECT First_Name,Last_Name FROM {$TB_RESIDENT_INFO} WHERE Resident_ID=?");
    if ($stmt_main_name) {
        $stmt_main_name->bind_param('i', $main_respondent_id);
        $stmt_main_name->execute();
        $stmt_main_name->bind_result($f, $l);
        while ($stmt_main_name->fetch()) { $respondent_names[] = $f . ' ' . $l; }
        $stmt_main_name->close();
    } else {
        $q = $conn->query("SELECT First_Name,Last_Name FROM {$TB_RESIDENT_INFO} WHERE Resident_ID = " . (int)$main_respondent_id);
        if ($q) {
            while ($r = $q->fetch_assoc()) { $respondent_names[] = $r['First_Name'] . ' ' . $r['Last_Name']; }
        }
    }
}

// Additional respondents
if ($hasComplaintRespondents) {
    $stmt_others = $conn->prepare("SELECT ri.First_Name,ri.Last_Name FROM {$TB_COMPLAINT_RESPONDENTS} cr JOIN {$TB_RESIDENT_INFO} ri ON cr.Respondent_ID=ri.Resident_ID WHERE cr.Complaint_ID=?");
    if ($stmt_others) {
        $stmt_others->bind_param('i', $complaint_id);
        $stmt_others->execute();
        $oRows = bpamis_stmt_fetch_all_assoc($stmt_others);
        foreach ($oRows as $row) { $respondent_names[] = $row['First_Name'] . ' ' . $row['Last_Name']; }
        $stmt_others->close();
    } else {
        $q = $conn->query("SELECT ri.First_Name,ri.Last_Name FROM {$TB_COMPLAINT_RESPONDENTS} cr JOIN {$TB_RESIDENT_INFO} ri ON cr.Respondent_ID=ri.Resident_ID WHERE cr.Complaint_ID = " . (int)$complaint_id);
        if ($q) { while ($row = $q->fetch_assoc()) { $respondent_names[] = $row['First_Name'] . ' ' . $row['Last_Name']; } }
    }
}

$respondents_display = $respondent_names ? implode(', ', $respondent_names) : 'N/A';

// Prepare respondent IDs and agreement flags so we can show arbitration UI
$respondentIds = [];
$respondentAgreements = [];
if ($hasComplaintRespondents) {
    $stmt_agree = $conn->prepare("SELECT GROUP_CONCAT(cr.Respondent_ID) AS respondent_ids, GROUP_CONCAT(cr.respondent_agree) AS respondent_agreements FROM {$TB_COMPLAINT_RESPONDENTS} cr WHERE cr.Complaint_ID = ?");
    if ($stmt_agree) {
        $stmt_agree->bind_param('i', $complaint_id);
        $stmt_agree->execute();
        $res_ag = bpamis_stmt_get_result($stmt_agree);
        if ($row_ag = $res_ag->fetch_assoc()) {
            if (!empty($row_ag['respondent_ids'])) {
                $respondentIds = array_map('intval', explode(',', $row_ag['respondent_ids']));
            }
            if (!empty($row_ag['respondent_agreements'])) {
                $respondentAgreements = explode(',', $row_ag['respondent_agreements']);
            }
        }
        $stmt_agree->close();
    }
}

// Fetch respondent details (id, name, agree) for modal display
$respondentsDetail = [];
if ($hasComplaintRespondents) {
    $stmt_resp_detail = $conn->prepare("SELECT cr.Respondent_ID, cr.respondent_agree, ri.First_Name, ri.Last_Name FROM {$TB_COMPLAINT_RESPONDENTS} cr JOIN {$TB_RESIDENT_INFO} ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?");
    if ($stmt_resp_detail) {
        $stmt_resp_detail->bind_param('i', $complaint_id);
        $stmt_resp_detail->execute();
        $res_rd = bpamis_stmt_get_result($stmt_resp_detail);
        while ($r = $res_rd->fetch_assoc()) {
            $respondentsDetail[] = [
                'id' => (int)$r['Respondent_ID'],
                'name' => trim($r['First_Name'].' '.$r['Last_Name']),
                'agree' => intval($r['respondent_agree']) === 1
            ];
        }
        $stmt_resp_detail->close();
    }
}

// If we prepared the detail stmt but it returned no rows, still try to include the main respondent
if (empty($respondentsDetail) && $main_respondent_id) {
    $q = $conn->prepare("SELECT First_Name, Last_Name FROM {$TB_RESIDENT_INFO} WHERE Resident_ID = ? LIMIT 1");
    if ($q) {
        $q->bind_param('i', $main_respondent_id);
        $q->execute();
        $rres = bpamis_stmt_get_result($q);
        if ($rres && $rr = $rres->fetch_assoc()) {
            $respondentsDetail[] = ['id' => (int)$main_respondent_id, 'name' => trim($rr['First_Name'].' '.$rr['Last_Name']), 'agree' => false];
        }
        $q->close();
    } else {
        // fallback query
        $qq = $conn->query("SELECT First_Name,Last_Name FROM {$TB_RESIDENT_INFO} WHERE Resident_ID = " . (int)$main_respondent_id);
        if ($qq && $rr = $qq->fetch_assoc()) {
            $respondentsDetail[] = ['id' => (int)$main_respondent_id, 'name' => trim($rr['First_Name'].' '.$rr['Last_Name']), 'agree' => false];
        }
    }
}

// Current user id (external or logged-in resident)
$currentUserId = (int)($_SESSION['external_id'] ?? $_SESSION['user_id'] ?? 0);

// Agreement evaluation
$allRespondentsAgreed = true;
if (count($respondentAgreements) === 0) {
    // If no respondents found, treat as not all agreed
    $allRespondentsAgreed = false;
} else {
    if (in_array('0', $respondentAgreements, true)) $allRespondentsAgreed = false;
}
$complainantAgreed = isset($case['external_complainant_agree']) && intval($case['external_complainant_agree']) === 1;

$currentUserAgreed = false;
if (in_array($currentUserId, $respondentIds, true)) {
    $idx = array_search($currentUserId, $respondentIds, true);
    if ($idx !== false && isset($respondentAgreements[$idx]) && intval($respondentAgreements[$idx]) === 1) {
        $currentUserAgreed = true;
    }
}

$everyoneAgreed = ($complainantAgreed && $allRespondentsAgreed);


// Status badge mapping
$status = strtoupper(trim($case['Case_Status']));
$caseStatusStyles=[
    'OPEN'     => 'bg-sky-50 text-sky-600 border border-sky-200',
    'PENDING'  => 'bg-amber-50 text-amber-600 border border-amber-200',
    'CLOSED'   => 'bg-gray-100 text-gray-700 border border-gray-300',
    'RESOLVED' => 'bg-emerald-50 text-emerald-600 border border-emerald-200'
];
$statusClass = $caseStatusStyles[$status] ?? 'bg-primary-50 text-primary-600 border border-primary-200';

// Fetch assigned Lupon Tagapamayapa (mediator/arbitrator)
$lupon_name = 'Not Yet Assigned';
$lupon_sql = "
    SELECT 
        CASE 
            WHEN cs.Case_Status = 'Mediation' THEN mi.Mediator_Name
            WHEN cs.Case_Status = 'Conciliation' THEN ci.Mediator_Name
            WHEN cs.Case_Status = 'Resolution' THEN ri.Mediator_Name
            WHEN cs.Case_Status = 'Settlement' THEN si.Mediator_Name
            WHEN cs.Case_Status = 'Arbitration' THEN ai.Mediator_Name
            ELSE NULL
        END AS lupon_tagapamayapa
    FROM {$TB_CASE_INFO} cs
    LEFT JOIN {$TB_MEDIATION_INFO} mi ON cs.Case_ID = mi.Case_ID
    LEFT JOIN {$TB_CONCILIATION} ci ON cs.Case_ID = ci.Case_ID
    LEFT JOIN {$TB_RESOLUTION} ri ON cs.Case_ID = ri.Case_ID
    LEFT JOIN {$TB_SETTLEMENT} si ON cs.Case_ID = si.Case_ID
    LEFT JOIN {$TB_ARBITRATION} ai ON cs.Case_ID = ai.Case_ID
    WHERE cs.Case_ID = ?
";
$lupon_stmt = $conn->prepare($lupon_sql);
if ($lupon_stmt) {
    $lupon_stmt->bind_param('i', $case_id);
    $lupon_stmt->execute();
    $lupon_result = bpamis_stmt_get_result($lupon_stmt);
    if ($row = $lupon_result->fetch_assoc()) {
        $lupon_name = $row['lupon_tagapamayapa'] ?? 'Not Yet Assigned';
    }
    $lupon_stmt->close();
}

// Fetch ALL meeting logs for this case (no pagination, for dynamic display)
$stmt_logs = $conn->prepare("SELECT ml.*, sl.hearingTitle FROM {$TB_MEETING_LOGS} ml LEFT JOIN {$TB_SCHEDULE_LIST} sl ON sl.Case_ID = ml.Case_ID AND DATE(sl.HearingDateTime) = ml.Hearing_Date AND TIME(sl.HearingDateTime) = ml.Hearing_Time WHERE ml.Case_ID = ? ORDER BY ml.Hearing_Date DESC, ml.Hearing_Time DESC, ml.Log_ID DESC");
$meetingLogs = [];
if ($stmt_logs) {
    $stmt_logs->bind_param('i', $case_id);
    $stmt_logs->execute();
    $logsResult = bpamis_stmt_get_result($stmt_logs);
    while ($row = $logsResult->fetch_assoc()) {
        $meetingLogs[] = $row;
    }
    $stmt_logs->close();
} else {
    $logsResult = $conn->query("SELECT ml.*, sl.hearingTitle FROM {$TB_MEETING_LOGS} ml LEFT JOIN {$TB_SCHEDULE_LIST} sl ON sl.Case_ID = ml.Case_ID AND DATE(sl.HearingDateTime) = ml.Hearing_Date AND TIME(sl.HearingDateTime) = ml.Hearing_Time WHERE ml.Case_ID = " . (int)$case_id . " ORDER BY ml.Hearing_Date DESC, ml.Hearing_Time DESC, ml.Log_ID DESC");
    while ($row = $logsResult->fetch_assoc()) {
        $meetingLogs[] = $row;
    }
}

// Fetch feedback for this case
$feedback_stmt = $conn->prepare("SELECT * FROM {$TB_FEEDBACK} WHERE case_id = ? ORDER BY created_at DESC");
$feedbackList = [];
if ($feedback_stmt) {
    $feedback_stmt->bind_param('i', $case_id);
    $feedback_stmt->execute();
    $feedback_res = bpamis_stmt_get_result($feedback_stmt);
    while ($row = $feedback_res->fetch_assoc()) {
        $feedbackList[] = $row;
    }
    $feedback_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <title>Case • Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}},boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'},animation:{'fade-in':'fadeIn .4s ease-out'},keyframes:{fadeIn:{'0%':{opacity:0},'100%':{opacity:1}}}}}};</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        * {
            box-sizing: border-box;
        }
        
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
        
        /* Quick action button active state */
        .action-btn {
            transition: all 0.2s ease;
        }
        .action-btn.active {
            background: linear-gradient(135deg, #0281d4 0%, #0c9ced 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(2, 129, 212, 0.3);
        }
        .action-btn.active i {
            color: white;
        }
        
        /* Dynamic content area */
        #dynamicContent {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #dynamicContent.show {
            display: block;
            opacity: 1;
        } 
        
        /* Tablet responsive styles (641px - 1024px) */
        @media (min-width: 641px) and (max-width: 1024px) {
            main {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
            
            section.glass {
                padding: 1.5rem !important;
            }
            
            header h1 {
                font-size: 1.5rem !important;
            }
            
            .grid.md\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid.md\:grid-cols-3 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Mobile responsive styles - aligned with view_cases.php format */
        @media (max-width: 640px) {
            body {
                font-size: 14px;
            }
            /* Container adjustments */
            main {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 1rem !important;
                padding-bottom: 1.5rem !important;
            }
            
            /* Back button styling */
            .mb-8 {
                margin-bottom: 0.75rem !important;
            }
            
            .mb-8 a {
                font-size: 0.7rem !important;
            }
            
            .mb-8 a .h-8 {
                height: 1.5rem !important;
                width: 1.5rem !important;
            }
            
            .mb-8 a .ml-2 {
                margin-left: 0.375rem !important;
            }
            
            /* Main section card */
            section.glass {
                padding: 0.75rem !important;
                border-radius: 0.75rem !important;
            }
            
            /* Header area */
            header {
                margin-bottom: 1rem !important;
            }
            
            /* Icon container beside case number (updated to w-8) */
            header h1 .w-8 {
                width: 1.75rem !important; /* 28px */
                height: 1.75rem !important;
                border-radius: 0.5rem !important;
            }

            header h1 .w-8 i {
                font-size: 0.8rem !important;
            }
            
            /* Title styling */
            header h1 {
                font-size: 1rem !important;
                line-height: 1.3 !important;
                gap: 0.375rem !important;
            }
            
            /* Status and type badges */
            header h1 span.inline-flex {
                font-size: 0.6rem !important;
                padding: 0.2rem 0.4rem !important;
                gap: 0.2rem !important;
            }
            
            header h1 span.inline-flex i {
                font-size: 0.45rem !important;
            }
            
            /* Meta information */
            header .mt-3 {
                margin-top: 0.375rem !important;
                font-size: 0.65rem !important;
                gap: 0.375rem !important;
            }
            
            header .mt-3 span {
                white-space: nowrap;
            }
            
            /* Section headings */
            h2 {
                font-size: 0.6rem !important;
                margin-bottom: 0.4rem !important;
                letter-spacing: 0.03em !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 0.55rem !important;
                margin-bottom: 0.2rem !important;
            }
            
            /* Field values */
            .group p:not(.field-label) {
                font-size: 0.75rem !important;
                line-height: 1.3 !important;
            }
            
            /* Grid adjustments - force single column */
            .grid.gap-5,
            .grid.md\:grid-cols-2 {
                grid-template-columns: 1fr !important;
                gap: 0.4rem !important;
            }
            
            .grid.gap-4 {
                gap: 0.4rem !important;
            }
            
            /* Card padding */
            .group.rounded-xl {
                padding: 0.5rem !important;
                border-radius: 0.5rem !important;
            }
            
            /* Complaint details card */
            .mb-5 {
                margin-bottom: 0.5rem !important;
            }
            
            /* Content sections spacing */
            .space-y-10 > * + * {
                margin-top: 1rem !important;
            }
            
            /* Attachment grid - 2 columns on mobile */
            .grid.sm\:grid-cols-2,
            .grid.md\:grid-cols-3,
            .grid.lg\:grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.4rem !important;
            }
            
            /* Attachment card icons */
            .aspect-video i {
                font-size: 1.5rem !important;
            }
            
            .aspect-video .text-\[11px\] {
                font-size: 0.55rem !important;
            }
            
            /* Attachment buttons */
            .group-hover\:opacity-100 button,
            .group-hover\:opacity-100 a {
                font-size: 0.6rem !important;
                padding: 0.3rem 0.4rem !important;
                gap: 0.2rem !important;
            }
            
            /* Certificate section */
            .mt-3 {
                margin-top: 0.5rem !important;
            }
            
            .mt-3 p {
                font-size: 0.7rem !important;
            }
            
            /* Certificate section buttons */
            .mt-3 a.inline-flex {
                font-size: 0.65rem !important;
                padding: 0.4rem 0.6rem !important;
                margin-left: 0 !important;
                margin-top: 0.375rem !important;
                display: block !important;
                text-align: center !important;
                width: 100% !important;
            }
            
            /* Action buttons at bottom */
            .pt-4.border-t {
                padding-top: 0.5rem !important;
                flex-direction: column !important;
                gap: 0.4rem !important;
            }
            
            .pt-4.border-t a,
            .pt-4.border-t button {
                width: 100% !important;
                justify-content: center !important;
                font-size: 0.75rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Image preview modal */
            #imgPreviewModal {
                padding: 0.5rem !important;
            }
            
            #imgPreviewModal .max-w-4xl {
                padding: 0 !important;
            }
            
            #imgPreviewModal button {
                top: 0.25rem !important;
                right: 0.25rem !important;
                width: 1.75rem !important;
                height: 1.75rem !important;
                font-size: 0.875rem !important;
            }
            
            #imgPreviewModal .rounded-2xl {
                border-radius: 0.75rem !important;
            }
            
            /* Background decorative blobs - reduce size on mobile */
            .pointer-events-none .absolute {
                transform: scale(0.5);
            }
        }
        
        /* Extra small devices (320px - 380px) */
        @media (max-width: 380px) {
            body {
                font-size: 13px;
            }
            
            main {
                padding-left: 0.375rem !important;
                padding-right: 0.375rem !important;
            }
            
            section.glass {
                padding: 0.5rem !important;
            }
            
            header h1 {
                font-size: 0.9rem !important;
            }
            
            header h1 span.inline-flex {
                font-size: 0.55rem !important;
                padding: 0.15rem 0.35rem !important;
            }
            
            header .mt-3 {
                font-size: 0.6rem !important;
            }
            
            .group p:not(.field-label) {
                font-size: 0.7rem !important;
            }
            
            h2 {
                font-size: 0.55rem !important;
            }
            
            .field-label {
                font-size: 0.5rem !important;
            }
            
            .pt-4.border-t a,
            .pt-4.border-t button {
                font-size: 0.7rem !important;
                padding: 0.45rem 0.6rem !important;
            }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/external_nav.php'; ?>
   
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="view_cases.php" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to Previous Page</span>
            </a>
        </div>
        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none"><div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div></div>
            
            <!-- Header -->
            <header class="relative mb-8">
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-primary-50 ring-2 ring-primary-100 shadow-sm">
                                <i class="fa fa-gavel text-base text-primary-600"></i>
                            </span>
                            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">
                                <?php if(!empty($case['case_original_id'])): ?>
                                    Case ID: <?= htmlspecialchars($case['case_original_id']) ?>
                                <?php else: ?>
                                    Case #<?= htmlspecialchars($case['Case_ID']) ?>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $statusClass ?> shadow-sm"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($case['Case_Status']) ?></span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $typeClass ?> shadow-sm"><i class="fa fa-tag text-[10px]"></i> <?= htmlspecialchars($caseType) ?></span>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> Opened <?= date('F d, Y', strtotime($case['Date_Opened'])) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-folder-open"></i> Complaint #<?= htmlspecialchars($case['Complaint_ID']) ?></span>
                        <?php if(!empty($case['case_original_id'])): ?>
                            <span class="inline-flex items-center gap-1"><i class="fa fa-hashtag"></i> ID: <?= htmlspecialchars($case['Case_ID']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

        </section>

        <!-- 2-Column Layout: Case Details (Left) + Quick Actions (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            
            <!-- LEFT COLUMN: Case Details Container -->
            <div class="lg:col-span-2">
                <section class="relative glass shadow-glow rounded-2xl p-6 md:p-8 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none"><div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div></div>
                    
                    <div class="relative space-y-6">
                        <!-- Case Original ID Card -->
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Case Original ID</p>
                            <p class="font-semibold text-gray-800 text-lg">
                                <?php if(!empty($case['case_original_id'])): ?>
                                    <?= htmlspecialchars($case['case_original_id']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">C<?= htmlspecialchars($case['Case_ID']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Parties Section -->
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Parties Involved</h2>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                    <p class="field-label mb-1">Complainant</p>
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars(trim($case['Complainant_First'].' '.$case['Complainant_Last'])) ?></p>
                                </div>
                                <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                    <p class="field-label mb-1">Respondents</p>
                                    <p class="text-gray-700 leading-relaxed"><?= htmlspecialchars($respondents_display) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Description Card -->
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Complaint Description</h2>
                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($case['Complaint_Details'])) ?></p>
                            </div>
                        </div>

                        <!-- Date Filed & Lupon Assigned -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="field-label mb-1">Date Filed</p>
                                <p class="font-semibold text-gray-800"><?= date('F d, Y', strtotime($case['Date_Filed'])) ?></p>
                            </div>
                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="field-label mb-1">Lupon Tagapamayapa Assigned</p>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($lupon_name) ?></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN: Quick Actions Container -->
            <div class="lg:col-span-1">
                <section class="relative glass shadow-glow rounded-2xl p-6 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none"><div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div></div>
                    
                    <div class="relative">
                        <div class="sticky top-24">
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-4">Quick Actions</h2>
                            <div class="space-y-3">
                                
                                <!-- View Feedback Button -->
                                <button onclick="showSection('feedback', event)" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
                                    <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition">
                                        <i class="fa fa-comments text-primary-600"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-800 text-sm">View Feedback</p>
                                        <p class="text-xs text-gray-500">Case feedback & comments</p>
                                    </div>
                                    <i class="fa fa-chevron-right text-gray-400 text-xs"></i>
                                </button>

                                <!-- View Meeting Logs Button -->
                                <button onclick="showSection('meetings', event)" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
                                    <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition">
                                        <i class="fa fa-clipboard-list text-primary-600"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-800 text-sm">Meeting Logs</p>
                                        <p class="text-xs text-gray-500">Hearing history & notes</p>
                                    </div>
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 text-primary-700 text-xs font-semibold"><?= count($meetingLogs) ?></span>
                                    <i class="fa fa-chevron-right text-gray-400 text-xs"></i>
                                </button>

                                <!-- View Attachments Button -->
                                <button onclick="showSection('attachments', event)" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
                                    <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-primary-50 flex items-center justify-center group-hover:bg-primary-100 transition">
                                        <i class="fa fa-paperclip text-primary-600"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-800 text-sm">Attachments</p>
                                        <p class="text-xs text-gray-500">Files & documents</p>
                                    </div>
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 text-primary-700 text-xs font-semibold"><?= count($attachments) ?></span>
                                    <i class="fa fa-chevron-right text-gray-400 text-xs"></i>
                                </button>

                            </div>

                            <!-- Certificate Notice (if exists) -->
                            <?php if (!empty($case['Certificate_Path'])): ?>
                            <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                                <div class="flex items-start gap-3">
                                    <i class="fa fa-certificate text-emerald-600 text-lg mt-0.5"></i>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-emerald-900 mb-1">Certificate Available</p>
                                        <p class="text-xs text-emerald-700 mb-2">Certificate to File Action has been attached</p>
                                        <?php $cert = ltrim($case['Certificate_Path'], '/'); $enc = implode('/', array_map('rawurlencode', explode('/', $cert))); ?>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="../<?= htmlspecialchars($enc) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium shadow-sm"><i class="fa fa-eye"></i> View</a>
                                            <a href="../<?= htmlspecialchars($enc) ?>" download class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white hover:bg-gray-50 text-emerald-700 border border-emerald-300 text-xs font-medium shadow-sm"><i class="fa fa-download"></i> Download</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            
                        </div>
                    </div>
                </section>
            </div>

        </div>

        <!-- DYNAMIC CONTENT AREA (appears below when action clicked) -->
        <div id="dynamicContent" class="relative mt-6">
            <section class="glass shadow-glow rounded-2xl p-6 md:p-8 border border-white/60 ring-1 ring-primary-100/40">
                <div id="contentArea"></div>
            </section>
        </div>
    </main>
    <?php $conn->close(); ?>
    <script>
    // Arbitration agree modal functions
    function openAgreeModal(){
        const modal = document.getElementById('agreeModal');
        if(!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeAgreeModal(){
        const modal = document.getElementById('agreeModal');
        if(!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    function submitAgree(){
        // submit the hidden form
        const form = document.getElementById('agreeForm');
        if(form) form.submit();
    }

    function previewImage(src){
        var modal=document.getElementById('imgPreviewModal');
        var img=document.getElementById('imgPreviewTag');
        if(!modal||!img) return;
        img.src=src;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closePreview(){
        var modal=document.getElementById('imgPreviewModal');
        if(!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Quick Actions Dynamic Content
    let currentSection = null;

    function showSection(section, evt) {
        const dynamicContent = document.getElementById('dynamicContent');
        const contentArea = document.getElementById('contentArea');
        const buttons = document.querySelectorAll('.action-btn');

        // If clicking same section, close it
        if (currentSection === section) {
            dynamicContent.classList.remove('show');
            buttons.forEach(btn => btn.classList.remove('active'));
            currentSection = null;
            setTimeout(() => contentArea.innerHTML = '', 300);
            return;
        }

        // Remove active class from all buttons
        buttons.forEach(btn => btn.classList.remove('active'));

        // Set current section
        currentSection = section;

        // Generate content based on section
        let content = '';
        if (section === 'feedback') {
            content = generateFeedbackContent();
        } else if (section === 'meetings') {
            content = generateMeetingLogsContent();
        } else if (section === 'attachments') {
            content = generateAttachmentsContent();
        }

        // Update content and show
        contentArea.innerHTML = content;
        dynamicContent.classList.add('show');

        // Add active class to clicked button when event supplied
        try {
            const btn = evt && evt.target ? evt.target.closest('.action-btn') : null;
            if (btn) btn.classList.add('active');
        } catch (e) { /* ignore */ }

        // Smooth scroll to content
        setTimeout(() => {
            dynamicContent.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }

    function generateFeedbackContent() {
        const feedbackList = <?= json_encode($feedbackList) ?>;
        
        if (feedbackList.length === 0) {
            return `
                <div class="mt-8 p-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-comments text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No feedback has been submitted for this case yet.</p>
                </div>
            `;
        }

        // Compact layout: smaller margins/paddings for mobile, keep comfortable spacing on md+
        let html = '<div class="mt-6"><h3 class="text-sm md:text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2"><i class="fa fa-comments text-primary-600"></i> Case Feedback</h3><div class="space-y-3">';

        feedbackList.forEach(fb => {
            const date = new Date(fb.created_at);
            const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const formattedTime = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            
            html += `
                <div class="rounded-xl border bg-white/70 border-gray-200 p-3 md:p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center">
                            <i class="fa fa-user text-primary-600"></i>
                        </div>
                        <div class="flex-1 min-w-0 text-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-semibold text-gray-800">${escapeHtml(fb.author || 'Anonymous')}</p>
                                <span class="text-[11px] text-gray-500">•</span>
                                <span class="text-[11px] text-gray-500">${formattedDate} at ${formattedTime}</span>
                            </div>
                            <p class="text-gray-700 text-sm leading-snug">${escapeHtml(fb.feedback).replace(/\n/g, '<br>')}</p>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div></div>';
        return html;
    }

    function generateMeetingLogsContent() {
        const meetingLogs = <?= json_encode($meetingLogs) ?>;
        
        if (meetingLogs.length === 0) {
            return `
                <div class="mt-8 p-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-clipboard-list text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No meeting logs have been recorded for this case yet.</p>
                </div>
            `;
        }

        // Compact meeting logs layout: smaller paddings on mobile, keep spacing on md+
        let html = '<div class="mt-6"><h3 class="text-sm md:text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2"><i class="fa fa-clipboard-list text-primary-600"></i> Meeting Logs History</h3><div class="space-y-4">';

        meetingLogs.forEach((log, index) => {
            const hearingDate = new Date(log.Hearing_Date + ' ' + log.Hearing_Time);
            const formattedDate = hearingDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const timeIn = log.Hearing_Time ? new Date('1970-01-01 ' + log.Hearing_Time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A';
            const timeOut = log.Hearing_End_Time ? new Date('1970-01-01 ' + log.Hearing_End_Time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A';
            
            html += `
                <div class="rounded-xl border bg-white/70 border-gray-200 p-3 md:p-5 shadow-sm">
                    <div class="flex items-start gap-3 md:gap-4 mb-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-primary-100 flex items-center justify-center ring-4 ring-primary-50">
                                <span class="text-primary-700 font-bold text-sm">#${meetingLogs.length - index}</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 text-sm">
                            <h4 class="font-semibold text-gray-800 mb-1">${escapeHtml(log.hearingTitle || 'Hearing Session')}</h4>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> ${formattedDate}</span>
                                <span class="inline-flex items-center gap-1"><i class="fa fa-clock"></i> ${timeIn} - ${timeOut}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid gap-2 md:gap-3 md:grid-cols-2 mb-2">
                        <div class="px-2 py-2 rounded-lg bg-gray-50 border border-gray-200">
                            <p class="text-[11px] text-gray-500 mb-0.5">Attendance</p>
                            <p class="text-sm font-semibold text-gray-800">${escapeHtml(log.Attendance || 'Not recorded')}</p>
                        </div>
                        ${log.Reason_Incompliance ? `
                        <div class="px-2 py-2 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-[11px] text-amber-600 mb-0.5">Reason for Incompliance</p>
                            <p class="text-sm font-semibold text-amber-900">${escapeHtml(log.Reason_Incompliance)}</p>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${log.Hearing_Details ? `
                    <div class="px-2 py-2 rounded-lg bg-blue-50 border border-blue-200">
                        <p class="text-[11px] text-blue-600 mb-1 font-medium">Hearing Notes</p>
                        <p class="text-sm text-blue-900 leading-snug whitespace-pre-line">${escapeHtml(log.Hearing_Details)}</p>
                    </div>
                    ` : ''}
                </div>
            `;
        });
        
        html += '</div></div>';
        return html;
    }

    function generateAttachmentsContent() {
        const attachments = <?= json_encode($attachments) ?>;
        
        if (attachments.length === 0) {
            return `
                <div class="mt-8 p-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-paperclip text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No attachments have been uploaded for this case.</p>
                </div>
            `;
        }

        let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-paperclip text-primary-600"></i> Case Attachments</h3><div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">';
        
        attachments.forEach(att => {
            const basename = att.raw.split('/').pop();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(att.ext.toLowerCase());
            const isPdf = att.ext.toLowerCase() === 'pdf';
            
            html += `
                <div class="group relative rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition overflow-hidden">
                    <div class="aspect-video w-full bg-gray-100 flex items-center justify-center overflow-hidden">
            `;
            
            if (isImage) {
                html += `<img src="../${escapeHtml(att.encoded)}" alt="Attachment" class="w-full h-full object-cover object-center group-hover:scale-105 transition" onerror="this.src='https://via.placeholder.com/300x180?text=Missing';" />`;
            } else if (isPdf) {
                html += `
                    <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                        <i class="fa fa-file-pdf text-3xl mb-1"></i>
                        PDF File
                    </div>
                `;
            } else {
                html += `
                    <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium px-2 text-center">
                        <i class="fa fa-paperclip text-3xl mb-1"></i>
                        <span class="break-all leading-tight text-[11px]">${escapeHtml(basename)}</span>
                    </div>
                `;
            }
            
            html += `
                    </div>
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                        <div class="flex gap-2">
            `;
            
            if (isImage) {
                html += `<button type="button" onclick="previewImage('../${escapeHtml(att.encoded)}')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-primary-700 text-xs font-medium shadow-sm"><i class="fa fa-eye"></i> View</button>`;
            }
            
            html += `
                            <a href="../${escapeHtml(att.encoded)}" download class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium shadow-sm"><i class="fa fa-download"></i> Download</a>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div></div>';
        return html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    <div id="imgPreviewModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
        <div class="relative max-w-4xl w-full">
            <button onclick="closePreview()" class="absolute -top-4 -right-4 w-10 h-10 rounded-full bg-white text-gray-700 flex items-center justify-center shadow-lg hover:bg-primary-600 hover:text-white transition"><i class="fa fa-xmark text-lg"></i></button>
            <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
                <img id="imgPreviewTag" src="" alt="Preview" class="w-full max-h-[80vh] object-contain bg-black" />
            </div>
        </div>
    </div>
</body>
</html>

<!-- Agree to Arbitration Modal -->
<div id="agreeModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-lg">
        <div class="flex items-start justify-between gap-4 mb-4">
            <h3 class="text-lg font-semibold">Confirm Agreement to Move to Arbitration</h3>
            <button onclick="closeAgreeModal()" class="text-gray-500 hover:text-gray-800"><i class="fa fa-xmark"></i></button>
        </div>
        <p class="text-sm text-gray-600 mb-4">To move this case to Arbitration both the complainant and all respondents must agree. Below is the current status of parties.</p>

        <div class="space-y-3 mb-4">
            <div class="p-3 rounded-lg border bg-gray-50">
                <p class="text-sm font-medium">Complainant</p>
                <p class="text-sm text-gray-800"><?= htmlspecialchars(trim($case['Complainant_First'].' '.$case['Complainant_Last'])) ?> — <?php if ($complainantAgreed): ?><span class="text-emerald-700 font-semibold">Agreed</span><?php else: ?><span class="text-amber-700 font-semibold">Not agreed</span><?php endif; ?></p>
            </div>
            <?php if (count($respondentsDetail)===0): ?>
                <div class="p-3 rounded-lg border bg-gray-50"><p class="text-sm text-gray-600">No respondents listed.</p></div>
            <?php else: ?>
                <?php foreach($respondentsDetail as $rd): ?>
                    <div class="p-3 rounded-lg border bg-white flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium"><?= htmlspecialchars($rd['name']) ?></p>
                        </div>
                        <div>
                            <?php if ($rd['agree']): ?>
                                <span class="text-emerald-700 font-semibold">Agreed</span>
                            <?php else: ?>
                                <span class="text-amber-700 font-semibold">Not agreed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <button onclick="closeAgreeModal()" class="px-4 py-2 rounded-lg bg-white border text-sm">Cancel</button>
            <form id="agreeForm" method="post" action="external_case_arbitration.php" class="inline">
                <input type="hidden" name="case_id" value="<?= htmlspecialchars($case['Case_ID']) ?>" />
                <input type="hidden" name="agree_arbitration" value="1" />
                <button type="button" onclick="submitAgree()" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm">I Agree</button>
            </form>
        </div>
    </div>
</div>
