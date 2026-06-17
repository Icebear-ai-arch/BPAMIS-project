<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

if (!isset($_GET['id'])) {
    echo "<p class='text-center text-red-500'>Case ID not provided.</p>";
    exit;
}

$case_id = intval($_GET['id']);

// Resolve real table names for case-sensitive Linux hosts
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');
$T_COMPLAINT_INFO = bpamis_table($conn, 'COMPLAINT_INFO');
$T_RESIDENT_INFO = bpamis_table($conn, 'RESIDENT_INFO');
$T_EXTERNAL_COMPLAINANT = bpamis_table($conn, 'external_complainant');
$T_COMPLAINT_RESPONDENTS = bpamis_table($conn, 'COMPLAINT_RESPONDENTS');
$T_FEEDBACK = bpamis_table($conn, 'feedback');
$T_MEETING_LOGS = bpamis_table($conn, 'MEETING_LOGS');
$T_SCHEDULE_LIST = bpamis_table($conn, 'schedule_list');
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
$TB_FEEDBACK = bpamis_quote_table($T_FEEDBACK);
$TB_MEETING_LOGS = bpamis_quote_table($T_MEETING_LOGS);
$TB_SCHEDULE_LIST = bpamis_quote_table($T_SCHEDULE_LIST);
$TB_MEDIATION_INFO = bpamis_quote_table($T_MEDIATION_INFO);
$TB_CONCILIATION = bpamis_quote_table($T_CONCILIATION);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_SETTLEMENT = bpamis_quote_table($T_SETTLEMENT);
$TB_ARBITRATION = bpamis_quote_table($T_ARBITRATION);

$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID','respondent_id']);

// Schema guards
$hasDescCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Description');
$descField = $hasDescCol ? 'Complaint_Description' : 'Complaint_Title';
$hasCaseTypeCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'case_type');
$caseTypeSelect = $hasCaseTypeCol ? 'ci.case_type AS Case_Type,' : "NULL AS Case_Type,";
// Detect if attachments column exists
$hasAttachmentCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Attachment_Path');
$attachSelect = $hasAttachmentCol ? ', ci.Attachment_Path' : '';

$complaintDetailsExpr = 'ci.Complaint_Details';
if (!bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Details')) {
    if (bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Description')) {
        $complaintDetailsExpr = 'ci.Complaint_Description';
    } else {
        $complaintDetailsExpr = 'ci.Complaint_Title';
    }
}

$sql = "SELECT 
            cs.Case_ID,
            cs.Case_Status,
            cs.Date_Opened,
            ci.Complaint_ID,
            ci.Complaint_Title,
            ci.$descField AS complaint_desc,
            {$complaintDetailsExpr} AS Complaint_Details,
            ci.Date_Filed,
            $caseTypeSelect
            COALESCE(comp.First_Name, ext_comp.First_Name) AS Complainant_First,
            COALESCE(comp.Last_Name, ext_comp.Last_Name) AS Complainant_Last,
            " . ($respondentIdCol ? "resp.First_Name AS Respondent_First,\n            resp.Last_Name AS Respondent_Last" : "NULL AS Respondent_First,\n            NULL AS Respondent_Last") . "
            $attachSelect
        FROM {$TB_CASE_INFO} cs
        LEFT JOIN {$TB_COMPLAINT_INFO} ci ON cs.Complaint_ID = ci.Complaint_ID
        LEFT JOIN {$TB_RESIDENT_INFO} comp ON ci.Resident_ID = comp.Resident_ID
        LEFT JOIN {$TB_EXTERNAL_COMPLAINANT} ext_comp ON ci.External_Complainant_ID = ext_comp.External_Complaint_ID
        " . ($respondentIdCol ? ("LEFT JOIN {$TB_RESIDENT_INFO} resp ON ci." . bpamis_quote_ident($respondentIdCol) . " = resp.Resident_ID") : "") . "
        WHERE cs.Case_ID = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='text-center text-red-500'>Failed to prepare query.</p>";
    exit;
}
$stmt->bind_param("i", $case_id);
$stmt->execute();
$rows = bpamis_stmt_fetch_all_assoc($stmt);

if (empty($rows)) {
    echo "<p class='text-center text-red-500'>Case not found.</p>";
    exit;
}

$case = $rows[0];
$stmt->close();

// Build respondents list (main + additional)
$respondent_names = [];
if (!empty($case['Respondent_First']) || !empty($case['Respondent_Last'])) {
    $respondent_names[] = trim(($case['Respondent_First'] ?? '') . ' ' . ($case['Respondent_Last'] ?? ''));
}
$complaint_id = (int)($case['Complaint_ID'] ?? 0);
if ($complaint_id > 0) {
    $addResSql = "SELECT ri.First_Name, ri.Last_Name FROM {$TB_COMPLAINT_RESPONDENTS} cr JOIN {$TB_RESIDENT_INFO} ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?";
    if ($rs = $conn->prepare($addResSql)) {
        $rs->bind_param('i', $complaint_id);
        $rs->execute();
        $resRows = bpamis_stmt_fetch_all_assoc($rs);
        foreach ($resRows as $r) {
            $respondent_names[] = trim(($r['First_Name'] ?? '') . ' ' . ($r['Last_Name'] ?? ''));
        }
        $rs->close();
    }
}
$respondents_display = !empty($respondent_names) ? implode(', ', array_filter($respondent_names)) : 'N/A';

// Parse attachments (if column present and non-empty)
$attachments = [];
if ($hasAttachmentCol && !empty($case['Attachment_Path'] ?? '')) {
    if (!function_exists('encode_path_segments')) {
        function encode_path_segments(string $path): string {
            $path = str_replace('\\', '/', trim($path));
            $segs = array_values(array_filter(explode('/', $path), function($s){ return $s !== '' && $s !== '.'; }));
            $enc = array_map(function($s){ return rawurlencode($s); }, $segs);
            return implode('/', $enc);
        }
    }
    $rawParts = preg_split('/[;,]+/', (string)$case['Attachment_Path']);
    foreach ($rawParts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $norm = str_replace('\\', '/', $p);
        $ext = strtolower(pathinfo($norm, PATHINFO_EXTENSION));
        $attachments[] = [
            'raw' => $norm,
            'encoded' => encode_path_segments($norm),
            'ext' => $ext,
            'name' => basename($norm)
        ];
    }
}

// Load feedback history submitted by the logged-in official for this case
$feedback_history = [];
$official_id = $_SESSION['official_id'] ?? null;
if ($official_id) {
    $fbSql = "SELECT message, created_at FROM {$TB_FEEDBACK} WHERE case_id = ? AND official_id = ? ORDER BY created_at DESC";
    if ($fb = $conn->prepare($fbSql)) {
        $fb->bind_param('ii', $case_id, $official_id);
        if ($fb->execute()) {
            $feedback_history = bpamis_stmt_fetch_all_assoc($fb);
        }
        $fb->close();
    }
}

// Also fetch full feedback list and meeting logs for quick actions
$feedbackList = [];
// 'author' column may not exist on all installations; select NULL AS author to keep compatibility
$fbSql = "SELECT official_id, official_name, message, created_at, NULL AS author FROM {$TB_FEEDBACK} WHERE case_id = ? ORDER BY created_at DESC";
if ($fb = $conn->prepare($fbSql)) {
    $fb->bind_param('i', $case_id);
    if ($fb->execute()) {
        $feedbackList = bpamis_stmt_fetch_all_assoc($fb);
    }
    $fb->close();
}

$meetingLogs = [];
// Determine which column, if any, on MEETING_LOGS references schedule_list.id
$possibleScheduleCols = ['Schedule_ID','ScheduleId','Schedule_Id','ScheduleID','schedule_id','Schedule'];
$foundScheduleCol = null;
foreach ($possibleScheduleCols as $col) {
    $colEsc = $conn->real_escape_string($col);
    $check = $conn->query("SHOW COLUMNS FROM {$TB_MEETING_LOGS} LIKE '" . $colEsc . "'");
    if ($check && $check->num_rows > 0) { $foundScheduleCol = $col; break; }
}

if ($foundScheduleCol) {
    $colName = $foundScheduleCol; // safe controlled value from whitelist
    $mlSqlBase = "SELECT ml.*, sl.hearingTitle FROM {$TB_MEETING_LOGS} ml LEFT JOIN {$TB_SCHEDULE_LIST} sl ON ml.`" . $colName . "` = sl.id WHERE ml.Case_ID = ?";
} else {
    // Fall back: schedule foreign key not present in this installation — still fetch meeting logs without join
    $mlSqlBase = "SELECT ml.*, NULL AS hearingTitle FROM {$TB_MEETING_LOGS} ml WHERE ml.Case_ID = ?";
}

// Determine a safe ORDER BY column on MEETING_LOGS if available
$possibleOrderCols = ['Meeting_Log_ID','Meeting_LogId','MeetingLog_ID','MeetingLogID','Meeting_Log_Id','MeetingLogId','id','meeting_log_id','Meeting_LogID','MeetingLog_Id'];
$foundOrderCol = null;
foreach ($possibleOrderCols as $ocol) {
    $oEsc = $conn->real_escape_string($ocol);
    $chk = $conn->query("SHOW COLUMNS FROM {$TB_MEETING_LOGS} LIKE '" . $oEsc . "'");
    if ($chk && $chk->num_rows > 0) { $foundOrderCol = $ocol; break; }
}

$orderClause = '';
if ($foundOrderCol) {
    $orderClause = " ORDER BY ml.`" . $foundOrderCol . "` DESC";
} else {
    // Fallback: try ordering by created_at if present
    $chkCreated = $conn->query("SHOW COLUMNS FROM {$TB_MEETING_LOGS} LIKE 'created_at'");
    if ($chkCreated && $chkCreated->num_rows > 0) {
        $orderClause = " ORDER BY ml.`created_at` DESC";
    }
    // otherwise leave $orderClause empty (no ORDER BY)
}

$mlSql = $mlSqlBase . $orderClause;

if ($ml = $conn->prepare($mlSql)) {
    $ml->bind_param('i', $case_id);
    if ($ml->execute()) {
        $meetingLogs = bpamis_stmt_fetch_all_assoc($ml);
    }
    $ml->close();
}

// Determine assigned Lupon (mediator/arbitrator) for this case (compatibly across tables)
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
    if ($lupon_stmt->execute()) {
        $luponRows = bpamis_stmt_fetch_all_assoc($lupon_stmt);
        if (!empty($luponRows)) {
            $row = $luponRows[0];
            if (!empty($row['lupon_tagapamayapa'])) $lupon_name = $row['lupon_tagapamayapa'];
        }
    }
    $lupon_stmt->close();
}

if (!function_exists('truncate_preserve_words')) {
    function truncate_preserve_words(string $text, int $limit = 220): array {
        $text = trim($text);
        if (mb_strlen($text) <= $limit) {
            return [$text, false];
        }
        $slice = mb_substr($text, 0, $limit);
        // Try not to cut in the middle of a word
        $lastSpace = mb_strrpos($slice, ' ');
        if ($lastSpace !== false && $lastSpace > ($limit * 0.6)) {
            $slice = mb_substr($slice, 0, $lastSpace);
        }
        return [$slice . '…', true];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}
                    },
                    boxShadow: { glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)' },
                    animation: { 'fade-in':'fadeIn .4s ease-out', 'float':'float 6s ease-in-out infinite' },
                    keyframes: {
                        fadeIn: { '0%':{opacity:0}, '100%':{opacity:1} },
                        float: { '0%,100%':{ transform:'translateY(0)' }, '50%':{ transform:'translateY(-8px)' } }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .glass { padding: 1rem !important; }
            .field-label { font-size: 10px; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>

    <?php 
        $status = strtoupper(trim($case['Case_Status'] ?? ''));
        $statusStyles = [
            'OPEN' => 'bg-sky-50 text-sky-600 border border-sky-200',
            'PENDING HEARING' => 'bg-amber-50 text-amber-600 border border-amber-200',
            'MEDIATION' => 'bg-indigo-50 text-indigo-600 border border-indigo-200',
            'RESOLUTION' => 'bg-purple-50 text-purple-600 border border-purple-200',
            'SETTLEMENT' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
            'RESOLVED' => 'bg-emerald-50 text-emerald-600 border border-emerald-200',
            'CLOSED' => 'bg-gray-100 text-gray-600 border border-gray-200',
        ];
        $statusClass = $statusStyles[$status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
    ?>
    <main class="relative z-10 max-w-5xl mx-auto px-3 sm:px-4 md:px-8 pt-4 sm:pt-6 md:pt-10 pb-12 sm:pb-16 md:pb-24 animate-fade-in">
        <div class="mb-4 sm:mb-6 md:mb-8 flex items-center gap-2 sm:gap-3">
            <a href="assigned_case.php" class="group inline-flex items-center text-xs sm:text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left text-xs sm:text-sm"></i></span>
                <span class="ml-2">Back to Case Lists</span>
            </a>
        </div>
        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
            </div>
            
            <!-- Header -->
            <header class="relative mb-8">
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 mr-3 rounded-lg bg-primary-50 ring-2 ring-primary-100 shadow-sm">
                                <i class="fa fa-gavel text-base text-primary-600"></i>
                            </span>
                            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">
                                Case #<?= htmlspecialchars($case['Case_ID']) ?>
                            </span>
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $statusClass ?> shadow-sm"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($case['Case_Status']) ?></span>
                        <?php if (!empty($case['Case_Type'])): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 border-indigo-200 shadow-sm"><i class="fa fa-tag text-[10px]"></i> <?= htmlspecialchars(ucwords(trim($case['Case_Type']))) ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> Opened <?= date('F d, Y', strtotime($case['Date_Opened'] ?? $case['Date_Filed'])) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-folder-open"></i> Complaint #<?= htmlspecialchars($case['Complaint_ID']) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hashtag"></i> ID: <?= htmlspecialchars($case['Case_ID']) ?></span>
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
                                <button onclick="showSection('feedback')" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
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
                                <button onclick="showSection('meetings')" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
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
                                <button onclick="showSection('attachments')" class="action-btn w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-md transition group">
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

                                <!-- Decline Assigned Case Button (Lupon) -->
                                <button id="decline-case-btn" class="w-full text-left flex items-center gap-3 px-4 py-3 rounded-xl border bg-red-50 border-red-200 hover:border-red-300 hover:shadow-md transition group">
                                    <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center transition">
                                        <i class="fa fa-ban text-red-600"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-red-800 text-sm">Decline Assigned Case</p>
                                        <p class="text-xs text-red-600">Notify Lupon Head with reason</p>
                                    </div>
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
    <?php include 'sidebar_.php'; ?>

    <script>
    function previewImage(src){
        var modal=document.getElementById('imgPreviewModal');
        var img=document.getElementById('imgPreviewTag');
        if(!modal||!img) return; img.src=src; modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
    }
    function closePreview(){ var modal=document.getElementById('imgPreviewModal'); if(!modal) return; modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }

    let currentSection = null;
    function showSection(section) {
        const dynamicContent = document.getElementById('dynamicContent');
        const contentArea = document.getElementById('contentArea');
        const buttons = document.querySelectorAll('.action-btn');
        if (currentSection === section) { dynamicContent.classList.remove('show'); buttons.forEach(b=>b.classList.remove('active')); currentSection = null; setTimeout(()=>contentArea.innerHTML='',(300)); return; }
        buttons.forEach(b=>b.classList.remove('active'));
        currentSection = section; let content=''; if(section==='feedback') content = generateFeedbackContent(); else if(section==='meetings') content = generateMeetingLogsContent(); else if(section==='attachments') content = generateAttachmentsContent();
        contentArea.innerHTML = content; dynamicContent.classList.add('show'); try{ event.target.closest('.action-btn')?.classList.add('active'); }catch(e){}
        setTimeout(()=>{ dynamicContent.scrollIntoView({behavior:'smooth', block:'nearest'}); }, 100);
    }

    function generateFeedbackContent(){
        const feedbackList = <?= json_encode($feedbackList) ?>;
        if(!feedbackList || feedbackList.length===0){
            return `<div class="mt-8 p-6 text-center"><div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4"><i class="fa fa-comments text-2xl text-gray-400"></i></div><p class="text-gray-500 text-sm">No feedback has been submitted for this case yet.</p></div>`;
        }
        let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-comments text-primary-600"></i> Case Feedback</h3><div class="space-y-4">';
        feedbackList.forEach(fb=>{ const date=new Date(fb.created_at); const formattedDate = date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); const formattedTime = date.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}); html += `<div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm"><div class="flex items-start gap-3"><div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center"><i class="fa fa-user text-primary-600"></i></div><div class="flex-1 min-w-0"><div class="flex items-center gap-2 mb-1"><p class="font-semibold text-gray-800 text-sm">${escapeHtml(fb.official_name||fb.author||'Anonymous')}</p><span class="text-xs text-gray-500">•</span><span class="text-xs text-gray-500">${formattedDate} at ${formattedTime}</span></div><p class="text-gray-700 text-sm leading-relaxed">${escapeHtml(fb.message||fb.feedback||'').replace(/\n/g,'<br>')}</p></div></div></div>`; }); html += '</div></div>'; return html; }

    function generateMeetingLogsContent(){
        const meetingLogs = <?= json_encode($meetingLogs) ?>;
        if(!meetingLogs||meetingLogs.length===0) return `<div class="mt-8 p-6 text-center"><div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4"><i class="fa fa-clipboard-list text-2xl text-gray-400"></i></div><p class="text-gray-500 text-sm">No meeting logs have been recorded for this case yet.</p></div>`;
        let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-clipboard-list text-primary-600"></i> Meeting Logs History</h3><div class="space-y-6">';
        meetingLogs.forEach((log,index)=>{ const hearingDate = new Date((log.Hearing_Date||'') + ' ' + (log.Hearing_Time||'')); const formattedDate = isNaN(hearingDate)?(log.Hearing_Date||'N/A'):hearingDate.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}); const timeIn = log.Hearing_Time?new Date('1970-01-01 '+log.Hearing_Time).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}):'N/A'; const timeOut = log.Hearing_End_Time?new Date('1970-01-01 '+log.Hearing_End_Time).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}):'N/A'; html += `<div class="rounded-xl border bg-white/70 border-gray-200 p-5 shadow-sm"><div class="flex items-start gap-4 mb-4"><div class="flex-shrink-0"><div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center ring-4 ring-primary-50"><span class="text-primary-700 font-bold text-sm">#${meetingLogs.length - index}</span></div></div><div class="flex-1 min-w-0"><h4 class="font-semibold text-gray-800 mb-1">${escapeHtml(log.hearingTitle||'Hearing Session')}</h4><div class="flex flex-wrap items-center gap-3 text-xs text-gray-500"><span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> ${formattedDate}</span><span class="inline-flex items-center gap-1"><i class="fa fa-clock"></i> ${timeIn} - ${timeOut}</span></div></div></div><div class="grid gap-3 md:grid-cols-2 mb-3"><div class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200"><p class="text-xs text-gray-500 mb-0.5">Attendance</p><p class="text-sm font-semibold text-gray-800">${escapeHtml(log.Attendance||'Not recorded')}</p></div>${log.Reason_Incompliance?`<div class="px-3 py-2 rounded-lg bg-amber-50 border border-amber-200"><p class="text-xs text-amber-600 mb-0.5">Reason for Incompliance</p><p class="text-sm font-semibold text-amber-900">${escapeHtml(log.Reason_Incompliance)}</p></div>`:''}</div>${log.Hearing_Details?`<div class="px-3 py-2 rounded-lg bg-blue-50 border border-blue-200"><p class="text-xs text-blue-600 mb-1 font-medium">Hearing Notes</p><p class="text-sm text-blue-900 leading-relaxed whitespace-pre-line">${escapeHtml(log.Hearing_Details)}</p></div>`:''}</div>`; }); html += '</div></div>'; return html; }

    function generateAttachmentsContent(){
        const attachments = <?= json_encode($attachments) ?>;
        if(!attachments||attachments.length===0) return `<div class="mt-8 p-6 text-center"><div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4"><i class="fa fa-paperclip text-2xl text-gray-400"></i></div><p class="text-gray-500 text-sm">No attachments have been uploaded for this case.</p></div>`;
        let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-paperclip text-primary-600"></i> Case Attachments</h3><div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">';
        attachments.forEach(att=>{ const basename = att.name || (att.raw||'').split('/').pop(); const isImage = ['jpg','jpeg','png','gif','webp'].includes((att.ext||'').toLowerCase()); const isPdf = (att.ext||'').toLowerCase()==='pdf'; html += `<div class="group relative rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-glow transition overflow-hidden"><div class="aspect-video w-full bg-gray-100 flex items-center justify-center overflow-hidden">`;
        html += isImage?`<img src="../${escapeHtml(att.encoded)}" alt="Attachment" class="w-full h-full object-cover object-center group-hover:scale-105 transition" onerror="this.src='https://via.placeholder.com/300x180?text=Missing';" />`:(isPdf?`<div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium"><i class="fa fa-file-pdf text-3xl mb-1"></i>PDF File</div>`:`<div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium px-2 text-center"><i class="fa fa-paperclip text-3xl mb-1"></i><span class="break-all leading-tight text-[11px]">${escapeHtml(basename)}</span></div>`);
        html += `</div><div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100"><div class="flex gap-2">${isImage?`<button type="button" onclick="previewImage('../${escapeHtml(att.encoded)}')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-primary-700 text-xs font-medium shadow-sm"><i class="fa fa-eye"></i> View</button>`:''}<a href="../${escapeHtml(att.encoded)}" download class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium shadow-sm"><i class="fa fa-download"></i> Download</a></div></div></div>`;
        }); html += '</div></div>'; return html; }

    function escapeHtml(text){ if(!text) return ''; const d=document.createElement('div'); d.textContent=text; return d.innerHTML; }
    </script>

    <div id="imgPreviewModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
        <div class="relative max-w-4xl w-full">
            <button onclick="closePreview()" class="absolute -top-4 -right-4 w-10 h-10 rounded-full bg-white text-gray-700 flex items-center justify-center shadow-lg hover:bg-primary-600 hover:text-white transition"><i class="fa fa-xmark text-lg"></i></button>
            <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
                <img id="imgPreviewTag" src="" alt="Preview" class="w-full max-h-[80vh] object-contain bg-black" />
            </div>
        </div>
    </div>

    <!-- Decline Assigned Case Modal -->
    <div id="declineModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
                <div class="p-5 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">Decline Assigned Case</h3>
                    <p class="text-xs text-gray-500 mt-1">Provide a brief reason for declining this assignment. This will be sent to the Lupon Head.</p>
                </div>
                <div class="p-5">
                    <label class="field-label">Reason</label>
                    <textarea id="decline-reason" rows="5" class="mt-2 w-full border border-gray-200 rounded-lg p-3 text-sm" placeholder="Enter reason (required)"></textarea>
                </div>
                <div class="p-4 border-t flex items-center justify-end gap-3">
                    <button id="decline-cancel" class="px-4 py-2 rounded-lg bg-white border text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button id="decline-confirm" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Send Decline</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const btn = document.getElementById('decline-case-btn');
        const modal = document.getElementById('declineModal');
        const cancel = document.getElementById('decline-cancel');
        const confirm = document.getElementById('decline-confirm');
        const reasonEl = document.getElementById('decline-reason');
        const caseId = <?= json_encode($case_id) ?>;

        if(btn && modal){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                reasonEl.focus();
            });
        }
        if(cancel && modal){
            cancel.addEventListener('click', function(e){ e.preventDefault(); modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); reasonEl.value=''; });
        }

        if(confirm){
            confirm.addEventListener('click', function(e){
                e.preventDefault();
                const reason = (reasonEl.value||'').trim();
                if(!reason){
                    alert('Please provide a reason for declining the assignment.');
                    reasonEl.focus();
                    return;
                }
                confirm.disabled = true; confirm.textContent = 'Sending...';
                const fd = new FormData(); fd.append('case_id', caseId); fd.append('reason', reason);
                fetch('../controllers/lupon_decline.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => {
                    if (!r.ok) {
                        return r.text().then(t => { throw new Error('Server error: ' + (t || r.status)); });
                    }
                    const ct = r.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        return r.text().then(t => { throw new Error('Invalid server response: ' + (t ? t.substring(0,250) : '')); });
                    }
                    return r.json();
                })
                .then(js=>{
                    if(js && js.success){
                        modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); reasonEl.value='';
                        // Simple inline success notification
                        const toast = document.createElement('div'); toast.className = 'fixed bottom-6 right-6 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow-lg'; toast.textContent = js.message || 'Decline recorded'; document.body.appendChild(toast);
                        setTimeout(()=>{ toast.classList.add('opacity-0'); setTimeout(()=>toast.remove(),400); }, 2600);
                        try{ btn.disabled = true; btn.classList.add('opacity-60','cursor-not-allowed'); }catch(e){}
                    } else {
                        throw new Error(js && js.message ? js.message : 'Failed to record decline.');
                    }
                })
                .catch(err=>{ console.error(err); alert(err.message || 'Network error. Please try again.'); })
                .finally(()=>{ confirm.disabled = false; confirm.textContent = 'Send Decline'; });
            });
        }
    })();
    </script>

 </body>
 </html>
