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
$T_MEDIATION_INFO = bpamis_table($conn, 'mediation_info');
$T_CONCILIATION = bpamis_table($conn, 'conciliation');
$T_RESOLUTION = bpamis_table($conn, 'resolution');
$T_SETTLEMENT = bpamis_table($conn, 'settlement');
$T_ARBITRATION = bpamis_table($conn, 'arbitration');
$T_FEEDBACK = bpamis_table($conn, 'feedback');
$T_MEETING_LOGS = bpamis_table($conn, 'MEETING_LOGS');
$T_SCHEDULE_LIST = bpamis_table($conn, 'schedule_list');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_EXTERNAL_COMPLAINANT = bpamis_quote_table($T_EXTERNAL_COMPLAINANT);
$TB_COMPLAINT_RESPONDENTS = bpamis_quote_table($T_COMPLAINT_RESPONDENTS);
$TB_MEDIATION_INFO = bpamis_quote_table($T_MEDIATION_INFO);
$TB_CONCILIATION = bpamis_quote_table($T_CONCILIATION);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_SETTLEMENT = bpamis_quote_table($T_SETTLEMENT);
$TB_ARBITRATION = bpamis_quote_table($T_ARBITRATION);
$TB_FEEDBACK = bpamis_quote_table($T_FEEDBACK);
$TB_MEETING_LOGS = bpamis_quote_table($T_MEETING_LOGS);
$TB_SCHEDULE_LIST = bpamis_quote_table($T_SCHEDULE_LIST);

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

// Detect original case id column in CASE_INFO (flexible to common names)
$caseOriginalSelect = "cs.Case_ID AS Case_Original,"; // default fallback
$cols = $conn->query("SHOW COLUMNS FROM {$TB_CASE_INFO}");
if ($cols) {
    $candidates = [
        'case_original_id','case_original','original_case_id','original_case',
        'case_number','case_no','original_casenumber','caseorig','caseid_original'
    ];
    while ($col = $cols->fetch_assoc()) {
        $field = $col['Field'];
        $lf = strtolower($field);
        if (in_array($lf, $candidates, true)) {
            $caseOriginalSelect = "cs.`$field` AS Case_Original,";
            break;
        }
    }
}

$sql = "SELECT 
            cs.Case_ID,
            $caseOriginalSelect
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
        function encode_path_segments(string $path): string
        {
            $path = str_replace('\\', '/', trim($path));
            $segs = array_values(array_filter(explode('/', $path), function ($s) {
                return $s !== '' && $s !== '.';
            }));
            $enc = array_map(function ($s) {
                return rawurlencode($s);
            }, $segs);
            return implode('/', $enc);
        }
    }

    // Allowed upload roots to validate files exist and prevent path traversal
    $allowedRoots = [];
    $r1 = realpath(__DIR__ . '/../uploads');
    if ($r1) $allowedRoots[] = $r1;
    $r2 = realpath(__DIR__ . '/../uploads_id');
    if ($r2) $allowedRoots[] = $r2;

    $rawParts = preg_split('/[;,]+/', (string)$case['Attachment_Path']);
    foreach ($rawParts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $norm = str_replace('\\', '/', $p);
        // candidate absolute path relative to project root
        $candidate = realpath(__DIR__ . '/../' . ltrim($norm, '/'));
        // only include attachments that resolve to an allowed upload root and exist
        $isValid = false;
        if ($candidate) {
            foreach ($allowedRoots as $root) {
                if (strpos($candidate, $root) === 0) {
                    $isValid = true;
                    break;
                }
            }
        }
        if (!$isValid) continue; // skip unknown/missing files

        $ext = strtolower(pathinfo($norm, PATHINFO_EXTENSION));
        $attachments[] = [
            'raw' => $norm,
            'encoded' => encode_path_segments($norm),
            'ext' => $ext,
            'name' => basename($norm)
        ];
    }
}

// Determine assigned Lupon (mediator/arbitrator) for this case — prefer mediator names from related tables
// Default text when none is available
$lupon_assigned = 'Not Yet Assigned';

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
            if (!empty($row['lupon_tagapamayapa'])) {
                $lupon_assigned = $row['lupon_tagapamayapa'];
            } else {
                // Fallback: if CASE_INFO has a lupon_assign column use it
                if (bpamis_table_has_column($conn, $T_CASE_INFO, 'lupon_assign')) {
                    $q = $conn->prepare("SELECT lupon_assign FROM {$TB_CASE_INFO} WHERE Case_ID = ? LIMIT 1");
                    if ($q) {
                        $q->bind_param('i', $case_id);
                        if ($q->execute()) {
                            $rrows = bpamis_stmt_fetch_all_assoc($q);
                            if (!empty($rrows)) {
                                $r = $rrows[0];
                                $val = trim((string)($r['lupon_assign'] ?? ''));
                                if ($val !== '') $lupon_assigned = $val;
                            }
                        }
                        $q->close();
                    }
                }
            }
        }
    }
    $lupon_stmt->close();
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

// Fetch ALL feedback for this case (for quick action view)
$feedbackList = [];
if ($fbst = $conn->prepare("SELECT * FROM {$TB_FEEDBACK} WHERE case_id = ? ORDER BY created_at DESC")) {
    $fbst->bind_param('i', $case_id);
    if ($fbst->execute()) {
        $feedbackList = bpamis_stmt_fetch_all_assoc($fbst);
    }
    $fbst->close();
}

// Fetch meeting logs for quick actions
$meetingLogs = [];
if ($mlst = $conn->prepare("SELECT ml.*, sl.hearingTitle FROM {$TB_MEETING_LOGS} ml LEFT JOIN {$TB_SCHEDULE_LIST} sl ON sl.Case_ID = ml.Case_ID AND DATE(sl.HearingDateTime) = ml.Hearing_Date AND TIME(sl.HearingDateTime) = ml.Hearing_Time WHERE ml.Case_ID = ? ORDER BY ml.Hearing_Date DESC, ml.Hearing_Time DESC, ml.Log_ID DESC")) {
    $mlst->bind_param('i', $case_id);
    if ($mlst->execute()) {
        $meetingLogs = bpamis_stmt_fetch_all_assoc($mlst);
    }
    $mlst->close();
}

if (!function_exists('truncate_preserve_words')) {
    function truncate_preserve_words(string $text, int $limit = 220): array
    {
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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        html {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        body {
            overflow-x: hidden;
        }
    </style>
    <title>Case Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae2fd',
                            300: '#7cccfd',
                            400: '#36b3f9',
                            500: '#0c9ced',
                            600: '#0281d4',
                            700: '#026aad',
                            800: '#065a8f',
                            900: '#0a4b76'
                        }
                    },
                    boxShadow: {
                        glow: '0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'
                    },
                    animation: {
                        'fade-in': 'fadeIn .4s ease-out',
                        'float': 'float 6s ease-in-out infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: 0
                            },
                            '100%': {
                                opacity: 1
                            }
                        },
                        float: {
                            '0%,100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-8px)'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .glass {
            background: linear-gradient(140deg, rgba(255, 255, 255, .92), rgba(255, 255, 255, .68));
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

        /* Mobile Optimization - Compressed & Compact */
        @media (max-width: 640px) {

            /* Main container padding */
            main {
                padding-top: 1.5rem !important;
                padding-bottom: 3rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Back button row */
            .mb-8.flex.items-center.gap-3 {
                margin-bottom: 1rem !important;
            }

            .mb-8.flex.items-center.gap-3 a {
                font-size: 0.75rem !important;
            }

            .mb-8.flex.items-center.gap-3 .h-8.w-8 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }

            /* Main card */
            section.glass {
                padding: 1rem !important;
                border-radius: 1rem !important;
            }

            /* Header section - icon beside title */
            header.relative.flex {
                flex-direction: row !important;
                gap: 0.75rem !important;
                margin-bottom: 1.25rem !important;
                align-items: flex-start !important;
            }

            /* Icon container - smaller for inline layout */
            .w-20.h-20 {
                width: 2.5rem !important;
                height: 2.5rem !important;
                border-radius: 0.65rem !important;
                flex-shrink: 0 !important;
            }

            .w-20.h-20 i {
                font-size: 1.1rem !important;
            }

            /* Title and badges - keep in same row */
            h1.text-2xl {
                font-size: 1rem !important;
                line-height: 1.3 !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.35rem !important;
                flex-wrap: wrap !important;
            }

            /* Case number text */
            h1.text-2xl>span:first-child {
                flex-shrink: 0 !important;
            }

                /* Mobile: enlarge Case Original ID (header), badges and original-id card */
                h1.text-2xl .bg-clip-text {
                    font-size: 1.15rem !important;
                    line-height: 1.2 !important;
                }

                /* Status and Type badges: slightly larger and more padded on mobile */
                h1 span.inline-flex.items-center {
                    font-size: 11px !important;
                    padding: 0.28rem 0.6rem !important;
                }

                /* Make the Case Original ID card text more prominent */
                .relative.space-y-6 > .group:first-of-type p.font-semibold.text-gray-800 {
                    font-size: 1.125rem !important;
                }

                /* Meta row (Opened / Complaint / ID) — increase the ID size for readability */
                .mt-3.flex.flex-wrap .inline-flex.items-center {
                    font-size: 0.88rem !important;
                }

            /* Status and type badges - same row */
            h1 span.inline-flex.items-center {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
                flex-shrink: 0 !important;
            }

            /* Meta info */
            .mt-3.flex.flex-wrap {
                margin-top: 0.5rem !important;
                font-size: 0.7rem !important;
                gap: 0.5rem !important;
            }

            /* Content sections */
            .space-y-10 {
                gap: 1.5rem !important;
            }

            .space-y-10>div {
                margin-top: 1.5rem !important;
            }

            .space-y-10>div:first-child {
                margin-top: 0 !important;
            }

            /* Section headings */
            h2.text-sm {
                font-size: 0.7rem !important;
                margin-bottom: 0.5rem !important;
            }

            /* Respondents box */
            .rounded-xl.border.bg-white\/70 {
                padding: 0.65rem !important;
                border-radius: 0.65rem !important;
            }

            .rounded-xl.border.bg-white\/70 p {
                font-size: 0.8rem !important;
            }

            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }

            /* Case information grid */
            .grid.gap-5 {
                gap: 0.65rem !important;
            }

            .grid.gap-5>div {
                padding: 0.65rem !important;
            }

            .grid.gap-5 p.font-semibold {
                font-size: 0.8rem !important;
            }

            .grid.gap-5 p.text-gray-700 {
                font-size: 0.78rem !important;
                line-height: 1.4 !important;
            }

            /* Attachments grid */
            .grid.grid-cols-1.sm\:grid-cols-2 {
                grid-template-columns: 1fr !important;
                gap: 0.65rem !important;
            }

            /* Attachment cards */
            .grid.grid-cols-1.sm\:grid-cols-2 .rounded-xl {
                border-radius: 0.65rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .h-40 {
                height: 8rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .p-3 {
                padding: 0.6rem !important;
                font-size: 0.75rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .p-4 {
                padding: 0.65rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .text-3xl {
                font-size: 1.5rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .text-sm {
                font-size: 0.75rem !important;
            }

            .grid.grid-cols-1.sm\:grid-cols-2 .text-xs {
                font-size: 0.7rem !important;
            }

            /* Feedback history */
            .space-y-3 {
                gap: 0.6rem !important;
            }

            .space-y-3>div {
                margin-top: 0.6rem !important;
            }

            .space-y-3>div:first-child {
                margin-top: 0 !important;
            }

            .space-y-3 .rounded-xl {
                padding: 0.65rem !important;
            }

            .space-y-3 .text-xs {
                font-size: 0.7rem !important;
            }

            .space-y-3 .text-gray-800 {
                font-size: 0.78rem !important;
                line-height: 1.4 !important;
            }

            /* Back button */
            .pt-2.flex a {
                font-size: 0.75rem !important;
                padding: 0.6rem 0.85rem !important;
                border-radius: 0.65rem !important;
            }

            .pt-2.flex a i {
                font-size: 0.7rem !important;
            }

            /* Background orbs - reduce on mobile */
            .pointer-events-none.absolute.inset-0>div {
                opacity: 0.15 !important;
                transform: scale(0.7);
            }
        }

        @media (max-width: 380px) {

            /* Extra small screens - further compression */
            main {
                padding-top: 1rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            section.glass {
                padding: 0.75rem !important;
            }

            h1.text-2xl {
                font-size: 0.95rem !important;
            }

            .w-20.h-20 {
                width: 2.25rem !important;
                height: 2.25rem !important;
            }

            .w-20.h-20 i {
                font-size: 1rem !important;
            }

            .grid.gap-5 p.text-gray-700 {
                font-size: 0.72rem !important;
            }

            h1 span.inline-flex.items-center {
                font-size: 8px !important;
                padding: 0.2rem 0.4rem !important;
            }
        }
    </style>
</head>

<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/lupon_head_nav.php'; ?>

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
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="javascript:history.back();" onclick="event.preventDefault(); history.back();" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition" aria-label="Back to previous page">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to Previous Page</span>
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
                                Case # <?= htmlspecialchars($case['Case_Original'] ?? $case['Case_ID']) ?>
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
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hashtag"></i> ID: <?= htmlspecialchars($case['Case_Original'] ?? $case['Case_ID']) ?></span>
                    </div>
                </div>
            </header>

        </section>

        <!-- 2-Column Layout: Case Details (Left) + Quick Actions (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

            <!-- LEFT COLUMN: Case Details Container -->
            <div class="lg:col-span-2">
                <section class="relative glass shadow-glow rounded-2xl p-6 md:p-8 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none">
                        <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
                    </div>

                    <div class="relative space-y-6">
                        <!-- Case Original ID Card -->
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1 text-xs font-semibold tracking-wider text-gray-500 uppercase">Case Original ID</p>
                            <p class="font-semibold text-gray-800 text-lg">
                                <span class="text-gray-400">C<?= htmlspecialchars($case['Case_Original'] ?? $case['Case_ID']) ?></span>
                            </p>
                        </div>

                        <!-- Parties Involved Section -->
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Parties Involved</h2>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                    <p class="field-label mb-1">Complainant</p>
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars(trim(($case['Complainant_First'] ?? '') . ' ' . ($case['Complainant_Last'] ?? ''))) ?></p>
                                </div>
                                <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                    <p class="field-label mb-1">Respondents</p>
                                    <p class="text-gray-700 leading-relaxed"><?= htmlspecialchars($respondents_display) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Case Information Section -->
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Complaint Description</h2>
                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($case['Complaint_Details'] ?? '')) ?></p>
                            </div>
                        </div>

                        <!-- Date Filed & Lupon Assigned -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="field-label mb-1">Date Filed</p>
                                <p class="font-semibold text-gray-800"><?= date('F d, Y', strtotime($case['Date_Filed'] ?? '')) ?></p>
                            </div>

                            <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="field-label mb-1">Lupon Assigned</p>
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($lupon_assigned) ?></p>
                            </div>
                        </div>



                        <!-- Feedback History Section -->
                        <?php if (!empty($feedback_history)): ?>
                            <div>
                                <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Your Feedback History</h2>
                                <div class="space-y-3">
                                    <?php foreach ($feedback_history as $idx => $fb):
                                        $msg = (string)($fb['message'] ?? '');
                                        [$short, $truncated] = truncate_preserve_words($msg, 220);
                                        $created = !empty($fb['created_at']) ? date('M d, Y h:i A', strtotime($fb['created_at'])) : '';
                                        $rowId = 'fbrow_' . $idx;
                                    ?>
                                        <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs text-gray-500 mb-1"><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($created) ?></div>
                                                    <div class="text-gray-800 leading-relaxed">
                                                        <span id="<?= $rowId ?>_short"><?= nl2br(htmlspecialchars($short)) ?></span>
                                                        <?php if ($truncated): ?>
                                                            <span id="<?= $rowId ?>_full" class="hidden"><?= nl2br(htmlspecialchars($msg)) ?></span>
                                                            <button type="button" class="ml-1 text-primary-600 hover:text-primary-800 text-sm font-medium underline align-baseline" onclick="(function(){var s=document.getElementById('<?= $rowId ?>_short');var f=document.getElementById('<?= $rowId ?>_full');var b=event.currentTarget;if(s.classList.contains('hidden')){s.classList.remove('hidden');f.classList.add('hidden');b.textContent='See more';}else{s.classList.add('hidden');f.classList.remove('hidden');b.textContent='See less';}})()">See more</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="shrink-0 text-primary-500"><i class="fa-solid fa-message"></i></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN: Quick Actions Container (standardized) -->
            <div class="lg:col-span-1">
                <section class="relative glass shadow-glow rounded-2xl p-6 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none">
                        <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
                    </div>

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

                            </div>

                            <!-- Metadata Card -->
                            <div class="mt-6 rounded-2xl bg-gradient-to-br <?= $statusClass ?> text-white p-6 shadow-sm">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center"><i class="fa fa-gavel text-gray text-xl"></i></div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-gray/70 font-semibold">Current Status</p>
                                        <p class="text-base font-medium"><?= htmlspecialchars($case['Case_Status']) ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-gray/90 leading-relaxed">This page displays case details and quick actions. Use the buttons above to view logs, feedback, and attachments.</p>
                                <div class="mt-5 flex flex-col gap-3">
                                    <a href="javascript:history.back();" onclick="event.preventDefault(); history.back();" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back to Previous</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </section>
            </div>
        </div>

        <!-- DYNAMIC CONTENT AREA (appears below when quick action clicked) -->
        <div id="dynamicContent" class="relative mt-6">
            <section class="glass shadow-glow rounded-2xl p-6 md:p-8 border border-white/60 ring-1 ring-primary-100/40">
                <div id="contentArea"></div>
            </section>
        </div>

    </main>

    <script>
        function previewImage(src) {
            var modal = document.getElementById('imgPreviewModal');
            var img = document.getElementById('imgPreviewTag');
            if (!modal || !img) return;
            img.src = src;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closePreview() {
            var modal = document.getElementById('imgPreviewModal');
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Quick Actions Dynamic Content
        let currentSection = null;

        function showSection(section) {
            const dynamicContent = document.getElementById('dynamicContent');
            const contentArea = document.getElementById('contentArea');
            const buttons = document.querySelectorAll('.action-btn');

            if (currentSection === section) {
                dynamicContent.classList.remove('show');
                buttons.forEach(btn => btn.classList.remove('active'));
                currentSection = null;
                setTimeout(() => contentArea.innerHTML = '', 300);
                return;
            }

            buttons.forEach(btn => btn.classList.remove('active'));
            currentSection = section;
            let content = '';
            if (section === 'feedback') {
                content = generateFeedbackContent();
            } else if (section === 'meetings') {
                content = generateMeetingLogsContent();
            } else if (section === 'attachments') {
                content = generateAttachmentsContent();
            }

            contentArea.innerHTML = content;
            dynamicContent.classList.add('show');

            // mark clicked button active (try to find nearest .action-btn from event)
            try {
                event.target.closest('.action-btn')?.classList.add('active');
            } catch (e) {}

            setTimeout(() => {
                dynamicContent.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }, 100);
        }

        function generateFeedbackContent() {
            const feedbackList = <?= json_encode($feedbackList) ?>;
            if (!feedbackList || feedbackList.length === 0) {
                return `
                <div class="mt-8 p-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-comments text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No feedback has been submitted for this case yet.</p>
                </div>
            `;
            }

            let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-comments text-primary-600"></i> Case Feedback</h3><div class="space-y-4">';
            feedbackList.forEach(fb => {
                const date = new Date(fb.created_at);
                const formattedDate = date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                const formattedTime = date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                html += `
                <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                            <i class="fa fa-user text-primary-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-semibold text-gray-800 text-sm">${escapeHtml(fb.official_name || fb.author || 'Anonymous')}</p>
                                <span class="text-xs text-gray-500">•</span>
                                <span class="text-xs text-gray-500">${formattedDate} at ${formattedTime}</span>
                            </div>
                            <p class="text-gray-700 text-sm leading-relaxed">${escapeHtml(fb.message || fb.feedback || '') .replace(/\n/g, '<br>')}</p>
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
            if (!meetingLogs || meetingLogs.length === 0) {
                return `
                <div class="mt-8 p-6 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fa fa-clipboard-list text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-sm">No meeting logs have been recorded for this case yet.</p>
                </div>
            `;
            }

            let html = '<div class="mt-8"><h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="fa fa-clipboard-list text-primary-600"></i> Meeting Logs History</h3><div class="space-y-6">';
            meetingLogs.forEach((log, index) => {
                const hearingDate = new Date((log.Hearing_Date || '') + ' ' + (log.Hearing_Time || ''));
                const formattedDate = isNaN(hearingDate) ? (log.Hearing_Date || 'N/A') : hearingDate.toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });
                const timeIn = log.Hearing_Time ? new Date('1970-01-01 ' + log.Hearing_Time).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                }) : 'N/A';
                const timeOut = log.Hearing_End_Time ? new Date('1970-01-01 ' + log.Hearing_End_Time).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                }) : 'N/A';
                html += `
                <div class="rounded-xl border bg-white/70 border-gray-200 p-5 shadow-sm">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center ring-4 ring-primary-50">
                                <span class="text-primary-700 font-bold text-sm">#${meetingLogs.length - index}</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-gray-800 mb-1">${escapeHtml(log.hearingTitle || 'Hearing Session')}</h4>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> ${formattedDate}</span>
                                <span class="inline-flex items-center gap-1"><i class="fa fa-clock"></i> ${timeIn} - ${timeOut}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 mb-3">
                        <div class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">
                            <p class="text-xs text-gray-500 mb-0.5">Attendance</p>
                            <p class="text-sm font-semibold text-gray-800">${escapeHtml(log.Attendance || 'Not recorded')}</p>
                        </div>
                        ${log.Reason_Incompliance ? `
                        <div class="px-3 py-2 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-xs text-amber-600 mb-0.5">Reason for Incompliance</p>
                            <p class="text-sm font-semibold text-amber-900">${escapeHtml(log.Reason_Incompliance)}</p>
                        </div>
                        ` : ''}
                    </div>
                    ${log.Hearing_Details ? `
                    <div class="px-3 py-2 rounded-lg bg-blue-50 border border-blue-200">
                        <p class="text-xs text-blue-600 mb-1 font-medium">Hearing Notes</p>
                        <p class="text-sm text-blue-900 leading-relaxed whitespace-pre-line">${escapeHtml(log.Hearing_Details)}</p>
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
            if (!attachments || attachments.length === 0) {
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
                const basename = att.name || (att.raw || '').split('/').pop();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes((att.ext || '').toLowerCase());
                const isPdf = (att.ext || '').toLowerCase() === 'pdf';
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