<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../bpamis_website/login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Resolve actual table names for Linux/case-sensitive hosts
$T_CASE_INFO = bpamis_table($conn, 'case_info');
$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$T_COMPLAINT_RESPONDENTS = bpamis_table($conn, 'complaint_respondents');
$T_SCHEDULE_LIST = bpamis_table($conn, 'schedule_list');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_COMPLAINT_RESPONDENTS = bpamis_quote_table($T_COMPLAINT_RESPONDENTS);
$TB_SCHEDULE_LIST = bpamis_quote_table($T_SCHEDULE_LIST);

$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID', 'respondent_id']);
$hasComplaintRespondents = false;
$tblCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($T_COMPLAINT_RESPONDENTS) . "'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $hasComplaintRespondents = true;
}


$selectMainRespondent = $respondentIdCol
    ? '(co.' . bpamis_quote_ident($respondentIdCol) . ' = ?) AS is_main_respondent,'
    : '0 AS is_main_respondent,';

$selectAdditionalRespondent = $hasComplaintRespondents
    ? "EXISTS (SELECT 1 FROM {$TB_COMPLAINT_RESPONDENTS} cr2 WHERE cr2.Complaint_ID = co.Complaint_ID AND cr2.Respondent_ID = ?) AS is_additional_respondent"
    : '0 AS is_additional_respondent';

$joinComplaintRespondents = $hasComplaintRespondents
    ? "LEFT JOIN {$TB_COMPLAINT_RESPONDENTS} cr ON co.Complaint_ID = cr.Complaint_ID"
    : '';

$whereMainRespondent = $respondentIdCol
    ? 'OR co.' . bpamis_quote_ident($respondentIdCol) . ' = ?'
    : '';

$whereAdditionalRespondent = $hasComplaintRespondents
    ? 'OR cr.Respondent_ID = ?'
    : '';

$sql = "
    SELECT DISTINCT
        ci.Case_ID,
        ci.case_original_id,
        co.Complaint_Title,
        co.case_type,
        co.Date_Filed,
        ci.case_status,
        sl.hearingDateTime AS Next_Hearing_Date,
        (co.Resident_ID = ?) AS is_complainant,
        {$selectMainRespondent}
        {$selectAdditionalRespondent}
    FROM {$TB_CASE_INFO} ci
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID
    {$joinComplaintRespondents}
    LEFT JOIN (
        SELECT Case_ID, MIN(hearingDateTime) AS hearingDateTime
        FROM {$TB_SCHEDULE_LIST}
        WHERE hearingDateTime >= NOW()
        GROUP BY Case_ID
    ) sl ON ci.Case_ID = sl.Case_ID
    WHERE
        co.Resident_ID = ?
        {$whereMainRespondent}
        {$whereAdditionalRespondent}
    ORDER BY co.Date_Filed DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Query prepare failed: ' . $conn->error);
}

$types = '';
$params = [];

// SELECT computed flags
$params[] = $user_id; $types .= 'i';
if ($respondentIdCol) { $params[] = $user_id; $types .= 'i'; }
if ($hasComplaintRespondents) { $params[] = $user_id; $types .= 'i'; }

// WHERE filters
$params[] = $user_id; $types .= 'i';
if ($respondentIdCol) { $params[] = $user_id; $types .= 'i'; }
if ($hasComplaintRespondents) { $params[] = $user_id; $types .= 'i'; }

$bind = [];
$bind[] = $types;
foreach ($params as $k => $v) {
    $bind[] = &$params[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$cases = bpamis_stmt_fetch_all_assoc($stmt);
$stmt->close();

// Set how many cases to display per page
$casesPerPage = 10;

// Current page number from URL (default = 1)
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

// Total number of cases
$totalCases = count($cases);

// Calculate offset for slicing
$startIndex = ($currentPage - 1) * $casesPerPage;

// Slice array for current page
$paginatedCases = array_slice($cases, $startIndex, $casesPerPage);

// Calculate total number of pages
$totalPages = ceil($totalCases / $casesPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Cases</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
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
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .table-row {
            transition: all 0.2s ease;
        }
        .table-row:hover {
            background-color: #f0f7ff;
        }
        .timeline-dot::before {
            content: '';
            position: absolute;
            left: -19px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e0effe;
            z-index: 0;
        }
        
        /* Mobile compact adjustments - matching view_complaints */
        @media (max-width: 768px) {
            /* Hero section - compact */
            .gradient-bg {
                padding: 0.75rem !important;
                margin-top: 1.5rem !important;
            }
            
            .gradient-bg h1 {
                font-size: 1.125rem !important;
                line-height: 1.5rem !important;
            }
            
            .gradient-bg p {
                font-size: 0.6875rem !important;
                margin-top: 0.25rem !important;
                line-height: 1.25rem !important;
            }
            
            .gradient-bg .flex.flex-wrap.gap-3 {
                gap: 0.375rem !important;
                margin-top: 0.75rem !important;
            }
            
            .gradient-bg .flex.flex-wrap.gap-3 span {
                font-size: 0.625rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            .gradient-bg .flex.flex-wrap.gap-3 i {
                font-size: 0.625rem !important;
            }
            
            /* Filters section - compact */
            .bg-white\/90.backdrop-blur-sm {
                padding: 0.75rem !important;
                margin-top: 1rem !important;
            }
            
            .bg-white\/90 .space-y-5 {
                gap: 0.75rem !important;
            }
            
            /* Search & Filter header */
            .bg-white\/90 .text-sm {
                font-size: 0.7rem !important;
            }
            
            .bg-white\/90 .px-3.py-1\.5 {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.625rem !important;
            }
            
            /* View Hearing Calendar button */
            .bg-white\/90 a.group {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.7rem !important;
            }
            
            .bg-white\/90 a.group i {
                font-size: 0.7rem !important;
            }
            
            /* Status chips */
            #statusChips button {
                font-size: 0.625rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Filter inputs - refined paddings so text doesn't collide with caret */
            #searchInput { font-size: 0.75rem !important; padding: 0.5rem 0.625rem !important; padding-left: 2rem !important; height: auto !important; border-radius: 0.75rem !important; }
            #monthFilter, #yearFilter, #sortOrder { font-size: 0.75rem !important; padding: 0.45rem 1.9rem 0.45rem 0.6rem !important; height: auto !important; line-height: 1.2 !important; border-radius: 0.75rem !important; }
            #resetFilters { font-size: 0.75rem !important; padding: 0.5rem 0.625rem !important; height: auto !important; border-radius: 0.75rem !important; }
            .absolute.left-4 { left: 0.625rem !important; font-size: 0.75rem !important; }
            .absolute.right-3 { right: 0.625rem !important; font-size: 0.75rem !important; }
            
            #resetFilters span {
                font-size: 0.7rem !important;
            }
            
            /* Force single column layout for filters on mobile */
            .grid.grid-cols-1.md\\:grid-cols-12 {
                display: flex !important;
                flex-direction: column !important;
                gap: 0.5rem !important;
            }
            
            .grid.grid-cols-1.md\\:grid-cols-12 > * {
                width: 100% !important;
            }
            
            /* Grid gaps */
            .grid.gap-4 {
                gap: 0.65rem !important;
            }
            
            /* Cases container */
            .bg-white.rounded-2xl {
                padding: 0.75rem !important;
                margin-top: 1rem !important;
            }
            
            /* Distribute filter widths on mobile: Month wider, Sort a bit narrower */
            .md\:col-span-7 > .flex > div:nth-child(1) { /* Month */
                flex: 1.4 1 0% !important;
            }
            .md\:col-span-7 > .flex > div:nth-child(2) { /* Year */
                flex: 1 1 0% !important;
            }
            .md\:col-span-7 > .flex > div:nth-child(3) { /* Sort */
                flex: 0.9 1 0% !important;
            }
            .md\:col-span-7 > .flex > div:nth-child(4) { /* Reset */
                flex: 0.6 1 0% !important;
            }
            
            /* Case cards */
            .case-card {
                padding: 0.65rem !important;
                gap: 0.5rem !important;
            }
            
            .case-card .text-\[11px\] {
                font-size: 0.625rem !important;
            }
            
            .case-card h3 {
                font-size: 0.8125rem !important;
                margin-top: 0.25rem !important;
                line-height: 1.25rem !important;
            }
            
            .case-card h3 span {
                font-size: 0.5625rem !important;
                padding: 0.125rem 0.375rem !important;
            }
            
            .case-card h3 i {
                font-size: 0.5625rem !important;
            }
            
            .case-card .px-2\.5.py-1 {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.625rem !important;
            }
            
            .case-card .text-xs {
                font-size: 0.65rem !important;
            }
            
            .case-card .flex.flex-col.gap-1 {
                gap: 0.25rem !important;
                margin-top: 0.25rem !important;
            }
            
            .case-card .mt-auto {
                margin-top: 0.5rem !important;
                padding-top: 0.25rem !important;
            }
            
            .case-card a {
                padding: 0.375rem 0.625rem !important;
                font-size: 0.7rem !important;
            }
            
            .case-card a i {
                font-size: 0.7rem !important;
            }
            
            /* Pagination */
            #visibleCount {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            .flex.items-center.gap-1 a {
                padding: 0.375rem 0.625rem !important;
                font-size: 0.7rem !important;
            }
            
            /* Bottom spacing */
            .pb-16 {
                padding-bottom: 1.5rem !important;
            }
            
            .mt-8 {
                margin-top: 0.75rem !important;
            }
            
            /* Back button */
            .mt-8.flex.justify-center a {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.625rem !important;
            }
            
            /* Page padding */
            .px-4 {
                padding-left: 0.625rem !important;
                padding-right: 0.625rem !important;
            }
        }
        
        @media (max-width: 640px) {
            /* Extra compact for very small screens */
            .gradient-bg {
                padding: 0.65rem !important;
            }
            
            .gradient-bg h1 {
                font-size: 1rem !important;
            }
            
            .gradient-bg p {
                font-size: 0.65rem !important;
            }
            
            .bg-white\/90.backdrop-blur-sm {
                padding: 0.65rem !important;
            }
            
            .case-card {
                padding: 0.5rem !important;
            }
            /* Extra-small filters: reserve caret space */
            #searchInput { padding: 0.45rem 0.6rem 0.45rem 1.9rem !important; font-size: 0.7rem !important; border-radius: 0.75rem !important; }
            #monthFilter, #yearFilter, #sortOrder { padding: 0.4rem 1.8rem 0.4rem 0.55rem !important; font-size: 0.7rem !important; line-height: 1.2 !important; border-radius: 0.75rem !important; }
            
            .case-card h3 {
                font-size: 0.75rem !important;
            }
            
            .case-card .text-xs {
                font-size: 0.625rem !important;
            }
            
            #statusChips button {
                font-size: 0.5625rem !important;
                padding: 0.25rem 0.375rem !important;
            }
        }
        
        @media (max-width: 480px) {
            /* Ultra compact for smallest screens */
            .gradient-bg h1 {
                font-size: 0.95rem !important;
            }
            
            .case-card h3 {
                font-size: 0.7rem !important;
            }
            
            .grid.sm\\:grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once('../includes/resident_nav.php'); ?>
    <?php 
        // Dynamic status counts (normalize variants)
            $statusCounts = [];
            foreach($cases as $c){
                $raw = (string)($c['case_status'] ?? 'Unknown');
                $low = strtolower($raw);
                if (stripos($low, 'resolved') !== false) {
                    $s = 'Resolved';
                } elseif (stripos($low, 'mediation') !== false) {
                    $s = 'Mediation';
                } elseif (stripos($low, 'conciliation') !== false) {
                    $s = 'Conciliation';
                } elseif (stripos($low, 'arbitration') !== false) {
                    $s = 'Arbitration';
                } elseif (stripos($low, 'certificate') !== false || stripos($low, 'file action') !== false || stripos($low, 'cfa') !== false) {
                    $s = 'Certificate to File Action';
                } elseif (stripos($low, 'pending') !== false) {
                    $s = 'Pending';
                } else {
                    $s = $raw;
                }
                $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
            }
            $pendingCount   = $statusCounts['Pending']   ?? 0;
            $mediationCount = $statusCounts['Mediation'] ?? 0;
            $resolvedCount  = $statusCounts['Resolved']  ?? 0;
            $cfaCount       = $statusCounts['Certificate to File Action'] ?? 0;
    ?>
    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <!-- Premium Hero -->
    <div class="w-full mt-16 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[620px] h-[620px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Legal Cases</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Track progress, hearing schedules, and outcomes of your barangay-level case records. Use smart filters to narrow results instantly.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-gavel text-primary-500"></i> Case Tracker</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-calendar-day text-primary-500"></i> Hearing Aware</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Smart Search</span>
                    </div>
                </div>
                <div class="hidden md:flex flex-col gap-3 min-w-[260px]">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">Pending</span><span class="mt-1 text-lg font-semibold text-amber-700"><?= $pendingCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-purple-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-purple-600 font-semibold">Mediation</span><span class="mt-1 text-lg font-semibold text-purple-700"><?= $mediationCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= $resolvedCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">Certificate to File Action</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= $cfaCount ?></span></div>
                    </div>
                    <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters & Search -->
    <div class="w-full mt-8 px-4">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-5">
                    <!-- Role Tabs (upper-left above Search & Filter) -->
                    <div class="flex items-center gap-4 border-b border-gray-200 pb-2" id="roleTabs">
                        <button type="button" data-role="" class="r-tab active px-3 py-2 text-xs font-semibold text-primary-700 border-b-2 border-primary-600">All roles</button>
                        <button type="button" data-role="complainant" class="r-tab px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-800 border-b-2 border-transparent">As Complainant</button>
                        <button type="button" data-role="respondent" class="r-tab px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-800 border-b-2 border-transparent">As Respondent</button>
                    </div>
                    <div class="flex flex-row flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                        </div>
                        <a href="view_hearing_calendar_resident.php" class="group relative inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-calendar-days text-white"></i>
                            <span>View Hearing Calendar</span>
                            <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    <!-- Status Chips -->
                    <div class="flex flex-wrap gap-2 pt-1" id="statusChips">
                        <button type="button" data-status="" class="c-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-status="Mediation" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Mediation</button>
                         <button type="button" data-status="Resolved" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Resolved</button>
                         <button type="button" data-status="Conciliation" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600 border border-green-100 hover:bg-green-100 transition">Conciliation</button>
                        <button type="button" data-status="Arbitration" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-red-50 text-red-600 border border-green-100 hover:bg-green-100 transition">Arbitration</button>
                        <button type="button" data-status="Certificate to File Action" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition">Certificate to File Action</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-5 relative group">
                            <input id="searchInput" type="text" placeholder="Search by ID, type, status..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                        </div>
                        <div class="md:col-span-7">
                            <div class="flex gap-2 items-center flex-nowrap py-0">
                                <div class="flex-1 min-w-0 relative">
                                    <select id="monthFilter" class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                        <option value="">All Months</option>
                                        <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); ?>
                                            <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>"><?= $mn ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                </div>
                                <div class="flex-1 min-w-0 relative">
                                    <select id="yearFilter" class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                        <option value="">All Years</option>
                                        <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                </div>
                                <div class="flex-[1.6] min-w-0 relative">
                                    <select id="sortOrder" class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                        <option value="desc">Newest First</option>
                                        <option value="asc">Oldest First</option>
                                    </select>
                                    <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                </div>
                                <div class="flex-[0.6] min-w-0 flex">
                                    <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1 px-2 py-2.5 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="ml-1 hidden xl:inline">Reset</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cases List -->
    <div class="w-full mt-8 px-4 pb-16">
        <div class="max-w-screen-2xl mx-auto bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden p-4 md:p-6">
            <div id="casesContainer" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if(!empty($paginatedCases)): foreach($paginatedCases as $case): 
                    $statusRaw = (string)($case['case_status'] ?? 'Unknown');
                    $lowStat = strtolower($statusRaw);
                    if (stripos($lowStat, 'resolved') !== false) {
                        $statusNorm = 'Resolved';
                    } elseif (stripos($lowStat, 'mediation') !== false) {
                        $statusNorm = 'Mediation';
                    } elseif (stripos($lowStat, 'conciliation') !== false) {
                        $statusNorm = 'Conciliation';
                    } elseif (stripos($lowStat, 'arbitration') !== false) {
                        $statusNorm = 'Arbitration';
                    } elseif (stripos($lowStat, 'certificate') !== false || stripos($lowStat, 'file action') !== false || stripos($lowStat, 'cfa') !== false) {
                        $statusNorm = 'Certificate to File Action';
                    } elseif (stripos($lowStat, 'pending') !== false) {
                        $statusNorm = 'Pending';
                    } else {
                        $statusNorm = $statusRaw;
                    }
                    $statusClassMap = [
                        'Pending' => 'text-amber-700 bg-amber-50 border border-amber-200',
                        'Mediation' => 'text-purple-700 bg-purple-50 border border-purple-200',
                        'Resolved' => 'text-green-700 bg-green-50 border border-green-200',
                    ];
                    $statusClass = $statusClassMap[$statusNorm] ?? 'text-blue-700 bg-blue-50 border border-blue-200';
                    $filed = $case['Date_Filed'] ?? '';
                    $filedDateRaw = $filed ? date('Y-m-d', strtotime($filed)) : '';
                    $filedDisplay = $filed ? date('F j, Y', strtotime($filed)) : 'N/A';
                    $hearing = $case['Next_Hearing_Date'] ?? '';
                    $hearingDisplay = $hearing ? date('F j, Y', strtotime($hearing)) : 'N/A';
                    $caseTypeRaw = trim($case['case_type'] ?? '');
                    $caseTypeDisplay = $caseTypeRaw !== '' ? ucwords($caseTypeRaw) : 'Unspecified Case Type';
                    $typeBorderClass = '';
                    $lcType = strtolower($caseTypeRaw);
                    // Determine user's role in this case for client-side filtering
                    $isComplainant = !empty($case['is_complainant']);
                    $isMainResp = !empty($case['is_main_respondent']);
                    $isAddResp = !empty($case['is_additional_respondent']);
                    $isRespondent = $isMainResp || $isAddResp;
                    $roleTag = $isComplainant && $isRespondent ? 'both' : ($isComplainant ? 'complainant' : ($isRespondent ? 'respondent' : ''));
                    if($lcType === 'criminal case' || $lcType === 'criminal'){
                        $typeBorderClass = 'border-red-300 bg-red-50/60 ring-1 ring-red-100';
                    } elseif($lcType === 'civil case' || $lcType === 'civil') {
                        $typeBorderClass = 'border-gray-300 bg-gray-50/70 ring-1 ring-gray-200';
                    } else {
                        $typeBorderClass = 'border-gray-100 bg-white/80';
                    }
                ?>
                <div class="case-card group relative backdrop-blur rounded-xl border <?= $typeBorderClass ?> p-4 flex flex-col gap-3 hover:-translate-y-[3px] hover:shadow-md transition-all" data-status="<?= strtolower($statusNorm) ?>" data-date="<?= $filedDateRaw ?>" data-id-text="<?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?>" data-role="<?= htmlspecialchars($roleTag) ?>">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-mono tracking-wide text-gray-500"><?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?></span>
                            <h3 class="mt-1 font-semibold text-gray-800 leading-snug line-clamp-2 flex items-center gap-2" title="<?= htmlspecialchars($caseTypeDisplay) ?>">
                                <?= htmlspecialchars($caseTypeDisplay) ?>
                                <?php if($lcType === 'criminal case' || $lcType === 'criminal'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 border border-red-200">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Criminal
                                    </span>
                                <?php elseif($lcType === 'civil case' || $lcType === 'civil'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200 text-gray-700 border border-gray-300">
                                        <i class="fa-solid fa-scale-balanced"></i> Civil
                                    </span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($statusRaw) ?>
                        </span>
                    </div>
                    <div class="flex flex-col gap-1 text-xs text-gray-500 mt-1">
                        <div>Filed on: <span class="font-medium text-gray-700"><?= $filedDisplay ?></span></div>
                    </div>
                    <div class="mt-auto pt-1 flex items-center justify-end">
                        <a href="case_details.php?case_id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-primary-50 text-primary-600 text-xs font-medium hover:bg-primary-100 transition"><i class="fas fa-eye text-primary-500"></i> Details</a>
                    </div>
                </div>
                <?php endforeach; else: ?>
                    <div class="col-span-full py-10 text-center text-gray-500 text-sm">You have no legal cases filed.</div>
                <?php endif; ?>
            </div>
            <div class="mt-6 flex items-center justify-between text-xs text-gray-600 flex-col md:flex-row gap-3">
                <div id="visibleCount" class="px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Showing <?= count($paginatedCases) ?> items</div>
                
            </div>
        </div>
        <div class="mt-8 flex justify-center">
            <a href="home-resident.php" class="inline-flex items-center gap-2 px-4 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium transition"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter buttons functionality
    const filterButtons = document.querySelectorAll('.px-3.py-1.rounded-lg.text-sm');
    const tableRows = document.querySelectorAll('.table-row');
    const mobileCards = document.querySelectorAll('.md\\:hidden .p-4');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filter = this.textContent.trim().toLowerCase();

            // Reset all buttons
            filterButtons.forEach(btn => {
                btn.classList.remove('bg-primary-50', 'text-primary-700', 'border', 'border-primary-100');
                btn.classList.add('text-gray-500');
            });

            // Set active button
            this.classList.remove('text-gray-500');
            this.classList.add('bg-primary-50', 'text-primary-700', 'border', 'border-primary-100');

            // Show/hide based on filter
            tableRows.forEach(row => {
                const status = row.dataset.status;
                row.style.display = (filter === 'all cases' || status === filter) ? '' : 'none';
            });

            mobileCards.forEach(card => {
                const status = card.dataset.status;
                card.style.display = (filter === 'all cases' || status === filter) ? '' : 'none';
            });
        });
    });

    // Search functionality
    const searchInput = document.querySelector('input[type="text"]');
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        
        // Filter desktop rows
        tableRows.forEach(row => {
            const id = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
            const type = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            
            if (id.includes(query) || type.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Filter mobile cards
        mobileCards.forEach(card => {
            const id = card.querySelector('.text-xs.font-medium').textContent.toLowerCase();
            const type = card.querySelector('h3').textContent.toLowerCase();
            
            if (id.includes(query) || type.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // View Case functionality
    const viewButtons = document.querySelectorAll('.view-case-btn');
    const body = document.body;
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const caseId = this.getAttribute('data-id');
            
            // Create case details based on ID
            let caseDetails = {};
            
            if (caseId === 'CASE-001') {
                caseDetails = {
                    id: 'CASE-001',
                    type: 'Property Dispute',
                    status: 'Pending',
                    filedDate: 'May 1, 2025',
                    parties: [
                        { name: 'John Doe (You)', role: 'Complainant' },
                        { name: 'Jane Smith', role: 'Respondent' }
                    ],
                    hearingDate: 'May 20, 2025',
                    description: 'Property boundary dispute regarding fence placement between adjacent properties.',
                    nextSteps: 'Scheduled for initial hearing on May 20, 2025 at 10:00 AM.',
                    timeline: [
                        { date: 'May 1, 2025', event: 'Case filed', status: 'Completed' },
                        { date: 'May 5, 2025', event: 'Notice served to respondent', status: 'Completed' },
                        { date: 'May 20, 2025', event: 'Initial hearing', status: 'Scheduled' },
                        { date: 'TBD', event: 'Mediation', status: 'Pending' },
                        { date: 'TBD', event: 'Resolution', status: 'Pending' }
                    ]
                };
            } else if (caseId === 'CASE-002') {
                caseDetails = {
                    id: 'CASE-002',
                    type: 'Noise Complaint',
                    status: 'Resolved',
                    filedDate: 'April 15, 2025',
                    parties: [
                        { name: 'John Doe (You)', role: 'Complainant' },
                        { name: 'Pedro Santos', role: 'Respondent' }
                    ],
                    hearingDate: 'April 25, 2025 (Completed)',
                    description: 'Complaint about excessive noise during late hours from neighboring residence.',
                    nextSteps: 'Case resolved with agreement. Respondent agreed to minimize noise after 10 PM.',
                    timeline: [
                        { date: 'Apr 15, 2025', event: 'Case filed', status: 'Completed' },
                        { date: 'Apr 18, 2025', event: 'Notice served to respondent', status: 'Completed' },
                        { date: 'Apr 25, 2025', event: 'Mediation session', status: 'Completed' },
                        { date: 'Apr 30, 2025', event: 'Resolution agreement signed', status: 'Completed' }
                    ]
                };
            } else if (caseId === 'CASE-003') {
                caseDetails = {
                    id: 'CASE-003',
                    type: 'Boundary Dispute',
                    status: 'Mediation',
                    filedDate: 'May 8, 2025',
                    parties: [
                        { name: 'Maria Cruz', role: 'Complainant' },
                        { name: 'John Doe (You)', role: 'Respondent' }
                    ],
                    hearingDate: 'May 25, 2025',
                    description: 'Dispute over boundary line between properties in Barangay Panducot.',
                    nextSteps: 'Mediation session scheduled to attempt resolution.',
                    timeline: [
                        { date: 'May 8, 2025', event: 'Case filed', status: 'Completed' },
                        { date: 'May 12, 2025', event: 'Notice served to respondent', status: 'Completed' },
                        { date: 'May 25, 2025', event: 'Mediation session', status: 'Scheduled' },
                        { date: 'TBD', event: 'Resolution', status: 'Pending' }
                    ]
                };
            }

            console.log("Case Details:", caseDetails);
            // TODO: You can now display caseDetails in a modal or detail panel.
        });
    });
});
</script>

<!-- Second Script: Chips & Filters -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    const chips=document.querySelectorAll('#statusChips .c-chip');
    const roleTabs=document.querySelectorAll('#roleTabs .r-tab');
    const searchInput=document.getElementById('searchInput');
    const monthFilter=document.getElementById('monthFilter');
    const yearFilter=document.getElementById('yearFilter');
    const sortOrder=document.getElementById('sortOrder');
    const resetBtn=document.getElementById('resetFilters');
    const cards=[...document.querySelectorAll('.case-card')];
    const visibleCount=document.getElementById('visibleCount');
    let statusOverride='';
    let roleOverride='';

    // Shorten long option labels on very small screens to avoid crowding
    function shortenCaseLabels(){
        if(window.innerWidth <= 420){
            if(sortOrder){
                [...sortOrder.options].forEach(o=>{
                    if(/Newest First/i.test(o.text)) o.text='Newest';
                    if(/Oldest First/i.test(o.text)) o.text='Oldest';
                });
            }
            if(monthFilter){
                [...monthFilter.options].forEach(o=>{ if(/All Months/i.test(o.text)) o.text='Months'; });
            }
            if(yearFilter){
                [...yearFilter.options].forEach(o=>{ if(/All Years/i.test(o.text)) o.text='Years'; });
            }
        }
    }
    shortenCaseLabels();
    window.addEventListener('resize', shortenCaseLabels);

    function applyFilters(){
        const q=(searchInput.value||'').toLowerCase();
        const m=monthFilter.value; 
        const y=yearFilter.value; 
        const order=sortOrder.value; 
        let shown=0;

        cards.forEach(c=>{
            const status=c.dataset.status||''; 
            const idTxt=c.dataset.idText.toLowerCase();
            const dateRaw=c.dataset.date||''; 
            const role=c.dataset.role||'';
            const title=c.querySelector('h3')?.textContent.toLowerCase()||'';
            let show=true;

            if(statusOverride){
                // status and statusOverride are normalized lowercase strings (e.g. 'resolved','mediation','certificate to file action')
                show = (status === statusOverride);
            }
            if(roleOverride){
                if(roleOverride==='complainant'){
                    show = show && (role==='complainant' || role==='both');
                } else if(roleOverride==='respondent'){
                    show = show && (role==='respondent' || role==='both');
                }
            }
            if(q) show=show && (idTxt.includes(q)||title.includes(q));
            if((m||y)&&dateRaw){ 
                const parts=dateRaw.split('-'); 
                if(parts.length>=3){ 
                    const Y=parts[0]; 
                    const M=parts[1]; 
                    if(m) show=show && M===m; 
                    if(y) show=show && Y===y; 
                } 
            }
            c.style.display=show?'':'none'; 
            if(show) shown++; 
        });

        const container=document.getElementById('casesContainer');
        const sorted=cards.filter(c=>c.style.display!=='none').sort((a,b)=>{
            const da=new Date(a.dataset.date); 
            const db=new Date(b.dataset.date); 
            return order==='asc'? da-db : db-da; 
        });
        sorted.forEach(el=>container.appendChild(el));
        visibleCount.textContent='Showing '+shown+' item'+(shown===1?'':'s');
    }

    chips.forEach(ch=> 
        ch.addEventListener('click',()=>{
            chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow')); 
            ch.classList.add('active','bg-primary-600','text-white','shadow'); 
            statusOverride=(ch.dataset.status||'').toLowerCase(); 
            applyFilters(); 
        })
    );
    roleTabs.forEach(tab=>
        tab.addEventListener('click',()=>{
            roleTabs.forEach(n=>{
                n.classList.remove('active','text-primary-700','font-semibold','border-primary-600');
                n.classList.add('text-gray-600','border-transparent');
            });
            tab.classList.add('active','text-primary-700','font-semibold','border-primary-600');
            tab.classList.remove('text-gray-600','border-transparent');
            roleOverride=(tab.dataset.role||'').toLowerCase();
            applyFilters();
        })
    );
    [searchInput,monthFilter,yearFilter,sortOrder].forEach(el=> el.addEventListener('input',applyFilters));
    monthFilter.addEventListener('change',applyFilters); 
    yearFilter.addEventListener('change',applyFilters); 
    sortOrder.addEventListener('change',applyFilters);

    resetBtn.addEventListener('click',()=>{
        searchInput.value=''; 
        monthFilter.value=''; 
        yearFilter.value=''; 
        sortOrder.value='desc'; 
        statusOverride=''; 
        roleOverride='';
        chips.forEach((c,i)=>{ 
            c.classList.remove('active','bg-primary-600','text-white','shadow'); 
            if(i===0){ 
                c.classList.add('active','bg-primary-600','text-white','shadow'); 
            } 
        }); 
        roleTabs.forEach((t,i)=>{
            t.classList.remove('active','text-primary-700','font-semibold','border-primary-600');
            t.classList.add('text-gray-600','border-transparent');
            if(i===0){
                t.classList.add('active','text-primary-700','font-semibold','border-primary-600');
                t.classList.remove('text-gray-600','border-transparent');
            }
        });
        applyFilters(); 
    });

    applyFilters();

    if(typeof menuButton!=='undefined' && typeof mobileMenu!=='undefined'){ 
        menuButton.addEventListener('click',function(){ 
            this.classList.toggle('active'); 
            mobileMenu.style.transform=(mobileMenu.style.transform==='translateY(0%)')? 'translateY(-100%)':'translateY(0%)'; 
        }); 
    }
});
</script>
