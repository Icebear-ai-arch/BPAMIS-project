
<?php
/**
 * View Cases Page
 * Barangay Panducot Adjudication Management Information System
 */
require_once __DIR__ . '/../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
$pageTitle = "View Cases";

// Hosting-safe helpers: handle Linux table-name casing + optional columns across different DB dumps.
if (!function_exists('bpamis_find_table_name')) {
    function bpamis_find_table_name(mysqli $conn, string $desired): ?string
    {
        $res = $conn->query('SHOW TABLES');
        if (!$res) return null;
        $desiredLower = strtolower($desired);
        while ($row = $res->fetch_row()) {
            $tbl = (string)$row[0];
            if (strtolower($tbl) === $desiredLower) {
                $res->free();
                return $tbl;
            }
        }
        $res->free();
        return null;
    }
}

if (!function_exists('bpamis_table_has_column')) {
    function bpamis_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = str_replace('`', '``', $table);
        $colEsc = $conn->real_escape_string($column);
        $q = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
        if (!$q) return false;
        $ok = $q->num_rows > 0;
        $q->close();
        return $ok;
    }
}

// Resolve canonical table names regardless of casing (Windows is case-insensitive; many Linux hosts are not)
$T_CASE_INFO = bpamis_find_table_name($conn, 'CASE_INFO') ?? bpamis_find_table_name($conn, 'case_info') ?? 'CASE_INFO';
$T_COMPLAINT_INFO = bpamis_find_table_name($conn, 'COMPLAINT_INFO') ?? bpamis_find_table_name($conn, 'complaint_info') ?? 'COMPLAINT_INFO';
$T_RESIDENT_INFO = bpamis_find_table_name($conn, 'RESIDENT_INFO') ?? bpamis_find_table_name($conn, 'resident_info') ?? 'RESIDENT_INFO';
$T_EXT_COMPLAINANT = bpamis_find_table_name($conn, 'external_complainant') ?? bpamis_find_table_name($conn, 'EXTERNAL_COMPLAINANT') ?? 'external_complainant';
$T_COMPLAINT_RESPONDENTS = bpamis_find_table_name($conn, 'COMPLAINT_RESPONDENTS') ?? bpamis_find_table_name($conn, 'complaint_respondents') ?? 'COMPLAINT_RESPONDENTS';

$hasCaseOriginalId = bpamis_table_has_column($conn, $T_CASE_INFO, 'case_original_id');
$hasCaseType = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'case_type');
$hasRespondentId = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Respondent_ID') || bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'respondent_id');
$respondentIdCol = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Respondent_ID') ? 'Respondent_ID' : 'respondent_id';
// Compute case status counts for header overview
$caseCounts = [
    'Mediation' => 0,
    'Conciliation' => 0,
    'Arbitration' => 0,
    'Mediation Resolved' => 0,
    'Conciliation Resolved' => 0,
    'Arbitration Resolved' => 0,
    'Certificate to File Action' => 0,
    'Dismissed' => 0,
    'Closed' => 0,
];
try {
    $conn_counts = $conn;
    if (!$conn_counts->connect_error) {
        $caseTableEsc = str_replace('`', '``', $T_CASE_INFO);
        $resC = $conn_counts->query("SELECT Case_Status, COUNT(*) total FROM `{$caseTableEsc}` GROUP BY Case_Status");
        if ($resC) {
            // Normalize statuses into the desired categories:
            // Mediation, Conciliation, Arbitration, Mediation Resolved, Conciliation Resolved, Arbitration Resolved,
            // Certificate to File Action, Dismissed, Closed
            while ($rC = $resC->fetch_assoc()) {
                $raw = $rC['Case_Status'];
                if (is_string($raw)) {
                    $low = strtolower($raw);
                    $cnt = (int)$rC['total'];
                    if (strpos($low, 'resolved') !== false) {
                        if (strpos($low, 'mediation') !== false) $caseCounts['Mediation Resolved'] += $cnt;
                        elseif (strpos($low, 'conciliation') !== false) $caseCounts['Conciliation Resolved'] += $cnt;
                        elseif (strpos($low, 'arbitration') !== false) $caseCounts['Arbitration Resolved'] += $cnt;
                        else {
                            // Unclassified resolved -> count under Closed as closest summary
                            $caseCounts['Closed'] += $cnt;
                        }
                    } elseif (
                        strpos($low, 'certificate') !== false ||
                        strpos($low, 'file action') !== false ||
                        strpos($low, 'cfa') !== false
                    ) {
                        $caseCounts['Certificate to File Action'] += $cnt;
                    } elseif (strpos($low, 'dismiss') !== false) {
                        $caseCounts['Dismissed'] += $cnt;
                    } elseif (strpos($low, 'mediation') !== false) {
                        $caseCounts['Mediation'] += $cnt;
                    } elseif (strpos($low, 'conciliation') !== false) {
                        $caseCounts['Conciliation'] += $cnt;
                    } elseif (strpos($low, 'arbitration') !== false) {
                        $caseCounts['Arbitration'] += $cnt;
                    } elseif (strpos($low, 'closed') !== false) {
                        $caseCounts['Closed'] += $cnt;
                    }
                }
            }
            // Don't close $conn here: this is the shared connection used for the main table query.
            // Just free the result.
            try { $resC->free(); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) { /* ignore lightweight header counts errors */ }
if (!function_exists('cc')) {
    function cc($k,$arr){ return $arr[$k] ?? 0; }
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Mobile optimizations: compact and compressed layout */
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
            
            /* Page header - compact */
            .max-w-screen-2xl.mx-auto.mt-8 {
                margin-top: 1rem !important;
            }
            
            .gradient-bg {
                padding: 0.75rem !important;
            }
            
            .gradient-bg h1 {
                font-size: 1.25rem !important;
            }
            
            .gradient-bg p {
                font-size: 0.7rem !important;
                margin-top: 0.5rem !important;
            }
            
            .gradient-bg .flex.flex-wrap.gap-3 {
                margin-top: 0.5rem !important;
                gap: 0.375rem !important;
            }
            
            .gradient-bg .flex.flex-wrap.gap-3 span {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Hide desktop status overview on mobile */
            .gradient-bg .hidden.md\\:flex {
                display: none !important;
            }
            
            /* Main container spacing */
            .w-full.mt-8 {
                margin-top: 1rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            /* Filter card - compact */
            .bg-white\/90.backdrop-blur-sm {
                padding: 0.75rem !important;
            }
            
            .bg-white\/90 .space-y-5 {
                gap: 0.5rem !important;
            }
            
            .bg-white\/90 h2,
            .bg-white\/90 .text-sm {
                font-size: 0.7rem !important;
            }
            
            /* Filter chips */
            .status-chip {
                font-size: 10px !important;
                padding: 0.25rem 0.5rem !important;
            }
            /* Make 'Dismissed' chip slightly smaller to fit tight mobile layouts */
            .status-chip[data-status="Dismissed"] {
                font-size: 9px !important;
                padding: 0.2rem 0.4rem !important;
            }
            
            /* Search and filter inputs */
            #searchInput,
            #monthFilter,
            #yearFilter {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                height: auto !important;
            }
            
            #searchInput {
                padding-left: 2rem !important;
            }
            
            .fa-search {
                left: 0.5rem !important;
                font-size: 0.7rem !important;
            }
            
            #resetFilters {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Schedule Hearing button - compact */
            a[href="appoint_hearing.php"] {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Table card */
            .bg-white.rounded-2xl {
                padding: 0.75rem !important;
            }
            
            .bg-white.rounded-2xl h2 {
                font-size: 0.85rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            #visibleCount {
                font-size: 10px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Table styling */
            table {
                font-size: 0.7rem !important;
            }
            
            table th {
                font-size: 9px !important;
                padding: 0.5rem 0.375rem !important;
            }
            
            table td {
                font-size: 0.65rem !important;
                padding: 0.5rem 0.375rem !important;
            }
            
            /* Status badges in table */
            table td span.px-2\.5 {
                font-size: 9px !important;
                padding: 0.25rem 0.375rem !important;
            }
            
            /* Action buttons in table */
            table td a,
            table td span {
                width: 1.75rem !important;
                height: 1.75rem !important;
                font-size: 0.65rem !important;
            }
            
            /* Icons */
            .fa, .fas, .far {
                font-size: 0.7rem !important;
            }
            
            table .fas {
                font-size: 0.65rem !important;
            }
            
            /* Bottom info */
            #rangeDisplay {
                font-size: 0.7rem !important;
            }
            
            /* Grid gaps */
            .grid.gap-4 {
                gap: 0.5rem !important;
            }
            
            .grid.gap-2 {
                gap: 0.25rem !important;
            }
            
            /* Decorative orbs in filter card */
            .bg-white\/90 .absolute {
                display: none !important;
            }
            
            /* Compact spacing utilities */
            .space-y-6 > * + * {
                margin-top: 0.75rem !important;
            }
            
            /* Mobile: make table horizontally scrollable if needed */
            .overflow-x-auto {
                max-width: 100% !important;
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
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <!-- Top-left soft blue glow -->
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <!-- Mid-right cool cyan accent -->
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <!-- Bottom-center light indigo wash -->
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <!-- Subtle center diffusion -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>

    <!-- Page Header (Enhanced Hero) -->
    <div class="relative max-w-screen-2xl mx-auto mt-8 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden relative max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[620px] h-[620px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
            <div class="max-w-2xl">
                <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">Barangay Cases</span></h1>
                <p class="mt-4 text-gray-600 leading-relaxed">Browse, review details, and monitor resolution progress. Use the smart filters below to quickly narrow results.</p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Smart Filters</span>
                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> Status Insights</span>
                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-primary-500"></i> Recent Focus</span>
                </div>
            </div>
            <div class="hidden md:flex flex-col gap-3 min-w-[280px]">
                <div class="grid grid-cols-3 gap-2">
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-purple-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-purple-600 font-semibold">Mediation</span><span class="mt-1 text-lg font-semibold text-purple-700"><?= cc('Mediation',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-indigo-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-indigo-600 font-semibold">Conciliation</span><span class="mt-1 text-lg font-semibold text-indigo-700"><?= cc('Conciliation',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-yellow-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-yellow-600 font-semibold">Arbitration</span><span class="mt-1 text-lg font-semibold text-yellow-700"><?= cc('Arbitration',$caseCounts) ?></span></div>

                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Med. Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= cc('Mediation Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Con. Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= cc('Conciliation Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Arb. Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= cc('Arbitration Resolved',$caseCounts) ?></span></div>

                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-3 border border-cyan-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-cyan-600 font-semibold">CFA</span><span class="mt-1 text-lg font-semibold text-cyan-700"><?= cc('Certificate to File Action',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-3 border border-rose-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-rose-600 font-semibold">Dismissed</span><span class="mt-1 text-lg font-semibold text-rose-700"><?= cc('Dismissed',$caseCounts) ?></span></div>
                </div>
                <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
            </div>
                
            </div>
        </div>
    </div>
    
    <div class="w-full mt-8 px-4">
        <div class="max-w-screen-2xl mx-auto space-y-6">
            <!-- Filters / Search Card -->
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-5">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center justify-between md:justify-start gap-3 text-primary-700/80 text-sm font-medium w-full md:w-auto">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                                <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Results</span>
                                <!-- Archive button placed to the right of Refine Results -->
                                <button id="archiveBtn" type="button" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white/80 text-sm text-primary-600 hover:bg-primary-50 transition" title="Archive visible cases"><i class="fa-solid fa-archive text-primary-500"></i> History</button>
                            </div>
                            <a href="appoint_hearing.php" class="md:hidden group relative inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                                <i class="fa-solid fa-calendar-plus text-white"></i>
                                <span>Schedule</span>
                                <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                            </a>
                            <!-- Mobile Archive button: visible on small screens -->
                            <button id="archiveBtnMobile" type="button" class="md:hidden inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white/80 text-sm text-primary-600 hover:bg-primary-50 transition" title="Archive visible cases"><i class="fa-solid fa-archive text-primary-500"></i> History</button>
                        </div>
                        <a href="appoint_hearing.php" class="hidden md:inline-flex group relative items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-calendar-plus text-white"></i>
                            <span>Schedule Hearing</span>
                            <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    
                    
                    <div class="flex flex-nowrap gap-2 pt-1 overflow-x-auto pb-2">
                        <button type="button" data-status="" class="status-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm transition hover:shadow">All</button>
                        <button type="button" data-status="Mediation" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Mediation</button>
                        <button type="button" data-status="Conciliation" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Conciliation</button>
                        <button type="button" data-status="Arbitration" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100 hover:bg-yellow-100 transition">Arbitration</button>
                        <button type="button" data-status="Mediation Resolved" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-100 hover:bg-green-100 transition">Mediation Resolved</button>
                        <button type="button" data-status="Conciliation Resolved" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-100 hover:bg-green-100 transition">Conciliation Resolved</button>
                        <button type="button" data-status="Arbitration Resolved" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-100 hover:bg-green-100 transition">Arbitration Resolved</button>
                        <button type="button" data-status="Certificate to File Action" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-cyan-50 text-cyan-700 border border-cyan-100 hover:bg-cyan-100 transition">Certificate to File Action</button>
                        <button type="button" data-status="Dismissed" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-rose-50 text-rose-700 border border-rose-100 hover:bg-rose-100 transition">Dismissed</button>
                    </div>

                    <div class="space-y-3">
                        <!-- Search Row (Mobile: full-width search; Mobile & Desktop: month+reset compact on same row) -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-7 relative group">
                                <input type="text" id="searchInput" placeholder="Search by case ID, complainant, respondent or type..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                                <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                            </div>

                            <div class="md:col-span-5 flex gap-2 items-center">
                                <!-- Month (flex-1 so on mobile it shares row with reset compactly) -->
                                <div class="relative w-1/2 md:flex-1">
                                    <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                        <option value="">All Months</option>
                                        <?php for ($m = 1; $m <= 12; $m++): $monthName = date('F', mktime(0,0,0,$m,1)); ?>
                                            <option value="<?= $m ?>"><?= $monthName ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                </div>

                                <!-- Year (kept hidden on small screens but available on md+) -->
                                <div class="hidden md:block md:w-40 relative">
                                    <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                        <option value="">All Years</option>
                                        <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                </div>

                                <!-- Reset: full width on mobile within the flex row, auto on desktop -->
                                <div class="w-1/2 md:w-auto">
                                    <button id="resetFilters" class="w-full md:inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Cases Table Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8 overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-table text-primary-500"></i> Case Records</h2>
                    <span id="visibleCount" class="text-xs px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 font-medium border border-primary-100">0 Showing</span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="w-full mt-0">
                        <thead class="bg-primary-50/60">
                            <tr>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Case ID</th>
                                <!-- Added Case Original ID column -->
                                 <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Case Type</th>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Complainant</th>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Respondent</th>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Date Filed</th>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Status</th>
                                <th class="p-3 text-center text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Action</th>
                            </tr>
                        </thead>
                        <tbody id="casesTable">
                            <?php
                            // original PHP query & loop retained below
                            ?>
                            <?php
                            // In a real application, this would be populated from database
                            // For now, we'll use sample data
                            
                            // $conn provided by server/server.php
//added case_original_id in the select statement
                            $caseOrigSelect = $hasCaseOriginalId ? 'cs.case_original_id' : 'cs.Case_ID AS case_original_id';
                            $caseTypeSelect = $hasCaseType ? 'ci.case_type' : 'NULL AS case_type';

                            $caseTableEsc = str_replace('`', '``', $T_CASE_INFO);
                            $complaintTableEsc = str_replace('`', '``', $T_COMPLAINT_INFO);
                            $residentTableEsc = str_replace('`', '``', $T_RESIDENT_INFO);
                            $extTableEsc = str_replace('`', '``', $T_EXT_COMPLAINANT);

                            $sql = "SELECT 
                                cs.Case_ID,
                                {$caseOrigSelect},
                                cs.Case_Status,
                                ci.Complaint_ID,
                                ci.Complaint_Title,
                                cs.Date_Opened,
                                {$caseTypeSelect},
                                COALESCE(res_com.First_Name, ext_com.First_Name) AS Complainant_First,
                                COALESCE(res_com.Last_Name, ext_com.Last_Name) AS Complainant_Last
                            FROM `{$caseTableEsc}` cs
                            LEFT JOIN `{$complaintTableEsc}` ci 
                                ON cs.Complaint_ID = ci.Complaint_ID
                            LEFT JOIN `{$residentTableEsc}` res_com 
                                ON ci.Resident_ID = res_com.Resident_ID
                            LEFT JOIN `{$extTableEsc}` ext_com 
                                ON ci.external_complainant_id = ext_com.External_Complaint_ID
                            ORDER BY cs.Case_ID DESC";

$result = $conn->query($sql);
if ($result === false) {
    // Production-friendly: don't leak SQL errors to the UI, but log for debugging.
    error_log('view_cases.php: cases query failed: ' . ($conn->error ?? 'unknown error'));
    echo '<tr><td colspan="7" class="p-6 text-center text-gray-500 text-sm">Unable to load cases at the moment.</td></tr>';
} elseif ($result->num_rows > 0) {
    while ($case = $result->fetch_assoc()) {
        $complaint_id = (int)$case['Complaint_ID'];
$respondent_names = [];

// Main respondent 
if ($hasRespondentId) {
    $main_res_sql = "SELECT First_Name, Last_Name FROM `{$residentTableEsc}` WHERE Resident_ID = (
        SELECT `{$respondentIdCol}` FROM `{$complaintTableEsc}` WHERE Complaint_ID = $complaint_id
    )";
    $main_res_result = $conn->query($main_res_sql);
    if ($main_res_result && $main_res_result->num_rows > 0) {
        while ($row = $main_res_result->fetch_assoc()) {
            $respondent_names[] = ($row['First_Name'] ?? '') . ' ' . ($row['Last_Name'] ?? '');
        }
    }
}

// Additional respondents
$crTableEsc = str_replace('`', '``', $T_COMPLAINT_RESPONDENTS);
$other_res_sql = "SELECT ri.First_Name, ri.Last_Name 
    FROM `{$crTableEsc}` cr
    JOIN `{$residentTableEsc}` ri ON cr.Respondent_ID = ri.Resident_ID
    WHERE cr.Complaint_ID = $complaint_id";
$other_res_result = $conn->query($other_res_sql);
if ($other_res_result && $other_res_result->num_rows > 0) {
    while ($row = $other_res_result->fetch_assoc()) {
        $respondent_names[] = ($row['First_Name'] ?? '') . ' ' . ($row['Last_Name'] ?? '');
    }
}

// Combine all
$respondents_display = !empty($respondent_names) ? implode(', ', $respondent_names) : 'N/A';

?>
<tr data-href="view_case_details.php?id=<?= urlencode($case['Case_ID']) ?>" tabindex="0" class="border-b border-gray-100 hover:bg-primary-50/40 transition cursor-pointer">
    <!-- Added Case Original ID column -->
    <td class="p-3 text-sm text-gray-700 font-mono text-[11px] tracking-wide"><?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['case_type'] ?? 'N/A') ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($respondents_display) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Date_Opened']) ?></td>
    
    <td class="p-3 text-sm">
        <?php
    $statusRaw = $case['Case_Status'];
    // Display the original status text (so variants like 'Mediation Resolved' show fully)
    $statusDisplay = $statusRaw;
    // Normalize for badge styling and internal classification
    if (is_string($statusRaw)) {
        if (stripos($statusRaw, 'resolved') !== false) {
            $statusNorm = 'Resolved';
        } elseif (stripos($statusRaw, 'mediation') !== false) {
            $statusNorm = 'Mediation';
        } elseif (stripos($statusRaw, 'certificate') !== false || stripos($statusRaw, 'file action') !== false || stripos($statusRaw, 'cfa') !== false) {
            $statusNorm = 'CFA';
        } else {
            $statusNorm = $statusRaw;
        }
    } else {
        $statusNorm = $statusRaw;
    }
    // Make cases that are resolved (any variant) or closed view-only
    $isViewOnly = false;
    if (is_string($statusRaw) && (stripos($statusRaw, 'resolved') !== false || stripos($statusRaw, 'closed') !== false)) {
        $isViewOnly = true;
    }
        $badge = [
            'Open' => ['text-blue-700 bg-blue-50 border-blue-200', 'fa-folder-open'],
            'Pending Hearing' => ['text-amber-700 bg-amber-50 border-amber-200', 'fa-calendar'],
            'Mediation' => ['text-purple-700 bg-purple-50 border-purple-200', 'fa-handshake'],
            'Conciliation' => ['text-indigo-700 bg-indigo-50 border-indigo-200', 'fa-hands-helping'],
            'Arbitration' => ['text-yellow-800 bg-yellow-50 border-yellow-200', 'fa-gavel'],
            'CFA' => ['text-cyan-700 bg-cyan-50 border-cyan-200', 'fa-file-signature'],
            'Certificate to File Action' => ['text-cyan-700 bg-cyan-50 border-cyan-200', 'fa-file-signature'],
            'Resolved' => ['text-green-700 bg-green-50 border-green-200', 'fa-check-circle'],
            'Closed' => ['text-gray-700 bg-gray-50 border-gray-200', 'fa-folder'],
        ];
        $class = $badge[$statusNorm][0] ?? 'text-gray-700 bg-gray-50 border-gray-200';
        $icon = $badge[$statusNorm][1] ?? 'fa-info-circle';
        ?>
        <span class="px-2.5 py-1 rounded-full text-[11px] border font-semibold <?= $class ?>">
            <i class="fas <?= $icon ?> mr-1"></i><?= htmlspecialchars($statusDisplay) ?>
        </span>
    </td>
    <td class="p-3 text-center">
        <div class="flex justify-center gap-1.5">
            <!-- View always available -->
            <a href="view_case_details.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-primary-500 hover:text-white hover:bg-primary-500 transition text-sm" title="View Details"><i class="fas fa-eye"></i></a>

            <!-- Edit: show icon when allowed, otherwise show dash placeholder to preserve layout -->
            <?php if (!$isViewOnly): ?>
                <a href="update_case_status.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-amber-500 hover:text-white hover:bg-amber-500 transition text-sm" title="Update Status"><i class="fas fa-edit"></i></a>
            <?php else: ?>
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 text-sm" title="Not available" aria-hidden="true">—</span>
            <?php endif; ?>

            <!-- Schedule: show icon when allowed, otherwise show dash placeholder -->
            <?php if (!$isViewOnly): ?>
                <a href="appoint_hearing.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-green-600 hover:text-white hover:bg-green-600 transition text-sm" title="Schedule Hearing"><i class="fas fa-calendar-plus"></i></a>
            <?php else: ?>
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 text-sm" title="Not available" aria-hidden="true">—</span>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php
    }
} else {
    echo '<tr><td colspan="7" class="p-6 text-center text-gray-500 text-sm">No cases found.</td></tr>';
}
?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
                    <div id="rangeDisplay">Showing 0 entries</div>
                    <!-- <div class="flex mt-4 md:mt-0">
                        <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50">
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </a>
                        <a href="#" class="mx-1 px-4 py-2 bg-primary-500 text-white rounded-lg transition">1</a>
                        <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');
            const caseStatusSelect = document.getElementById('caseStatus');
            const dateRangeSelect = document.getElementById('dateRangeSelect');
            const printBtn = document.getElementById('printReport');
            const exportBtn = document.getElementById('exportExcel');
            const resetBtn = document.getElementById('resetFilters');
            const rows = document.querySelectorAll('#casesTable tr');
            const visibleCount = document.getElementById('visibleCount');
            const rangeDisplay = document.getElementById('rangeDisplay');
            const chips = document.querySelectorAll('.status-chip');
            let chipStatusOverride = '';
            let selectStatusOverride = '';
            let dateRangeOverride = '';

            function parseYMD(dateStr){
                if (!dateStr) return null;
                // try ISO/Date parse
                const d1 = new Date(dateStr.replace(' ', 'T'));
                if (!isNaN(d1)) return d1;
                const m = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (m) return new Date(Number(m[1]), Number(m[2])-1, Number(m[3]));
                return null;
            }

            // Robustly extract year from a date-like string without relying on Date parsing
            function parseYear(dateStr) {
                if (!dateStr) return null;
                // common ISO-like start: YYYY-MM-DD
                const m = dateStr.match(/^(\s*)(\d{4})[-\/]/);
                if (m) return Number(m[2]);
                // standalone year
                const y = dateStr.match(/(\d{4})/);
                if (y) return Number(y[1]);
                // fallback to Date parsing
                const d = parseYMD(dateStr);
                return d ? d.getFullYear() : null;
            }

            function inDateRange(d){
                if (!dateRangeOverride) return true;
                const now = new Date();
                const start = new Date(now);
                if (dateRangeOverride === '7') { start.setDate(now.getDate()-7); }
                else if (dateRangeOverride === '30') { start.setDate(now.getDate()-30); }
                else if (dateRangeOverride === 'this_month') { start.setDate(1); start.setHours(0,0,0,0); }
                else if (dateRangeOverride === 'this_year') { start.setMonth(0,1); start.setHours(0,0,0,0); }
                else return true;
                if (!d) return false;
                return d >= start && d <= now;
            }

            function filterTable() {
                const searchQuery = searchInput.value.toLowerCase();
                // status from chips or select; prefer select if set
                const statusQuery = (selectStatusOverride || chipStatusOverride || '').toLowerCase();
                const selectedMonth = monthFilter.value;
                const selectedYear = yearFilter.value;
                let shown = 0;

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (!cells.length) return; // skip if no data cells
                    const rowText = row.innerText.toLowerCase();
                    // Table columns: 0=Case ID,1=Case Type,2=Complainant,3=Respondent,4=Date Filed,5=Status,6=Action
                    const statusText = cells[5]?.innerText.toLowerCase();
                    const dateFiled = cells[4]?.innerText; // expect YYYY-MM-DD

                    let matchesMonth = true;
                    let matchesYear = true;
                    const d = parseYMD(dateFiled);
                    const y = parseYear(dateFiled);
                    if (selectedMonth) matchesMonth = d ? (d.getMonth() + 1) == parseInt(selectedMonth) : false;
                    if (selectedYear) matchesYear = y ? y == parseInt(selectedYear) : false;
                    const matchesRange = inDateRange(d);

                    const matchesSearch = rowText.includes(searchQuery);
                    // Make 'resolved' chip match any status that contains the word 'resolved'
                    let matchesStatus = false;
                    if (!statusQuery) {
                        matchesStatus = true;
                    } else if (statusQuery === 'resolved') {
                        matchesStatus = statusText && statusText.includes('resolved');
                    } else if (
                        statusQuery === 'cfa' ||
                        statusQuery === 'certificate to file action' ||
                        statusQuery.includes('certificate')
                    ) {
                        matchesStatus = statusText && (statusText.includes('certificate') || statusText.includes('file action') || statusText.includes('cfa'));
                    } else {
                        matchesStatus = statusText && statusText.includes(statusQuery);
                    }

                    const show = matchesSearch && matchesStatus && matchesMonth && matchesYear && matchesRange;
                    row.style.display = show ? '' : 'none';
                    if (show) shown++;
                });
                visibleCount.textContent = shown + ' Showing';
                rangeDisplay.textContent = 'Showing ' + shown + ' entr' + (shown === 1 ? 'y' : 'ies');
            }

            function resetFilters() {
                // Clear text search
                searchInput.value = '';
                // Clear month/year
                monthFilter.value = '';
                yearFilter.value = '';
                // Clear status overrides
                chipStatusOverride = '';
                selectStatusOverride = '';
                // Reset select and date range selects if present
                if (caseStatusSelect) caseStatusSelect.value = '';
                if (dateRangeSelect) {
                    dateRangeSelect.value = '';
                    dateRangeOverride = '';
                }
                // Reset chips visual state: activate 'All'
                chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow','shadow-sm'));
                const allChip = document.querySelector('.status-chip[data-status=""]');
                if (allChip) allChip.classList.add('active','bg-primary-600','text-white','shadow');
                filterTable();
            }

            searchInput.addEventListener('input', filterTable);
            monthFilter.addEventListener('change', filterTable);
            yearFilter.addEventListener('change', filterTable);
            resetBtn.addEventListener('click', resetFilters);

            if (caseStatusSelect) {
                caseStatusSelect.addEventListener('change', () => {
                    selectStatusOverride = caseStatusSelect.value || '';
                    // sync chips style: select corresponding chip active
                    chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow'));
                    if (selectStatusOverride === '') {
                        const allChip = document.querySelector('.status-chip[data-status=""]');
                        if (allChip) allChip.classList.add('active','bg-primary-600','text-white','shadow');
                        chipStatusOverride = '';
                    } else {
                        const chip = document.querySelector(`.status-chip[data-status="${selectStatusOverride}"]`);
                        if (chip) chip.classList.add('active','bg-primary-600','text-white','shadow');
                        chipStatusOverride = selectStatusOverride;
                    }
                    filterTable();
                });
            }
            if (dateRangeSelect) {
                dateRangeSelect.addEventListener('change', () => {
                    dateRangeOverride = dateRangeSelect.value || '';
                    filterTable();
                });
            }

            if (printBtn) printBtn.addEventListener('click', () => window.print());
            if (exportBtn) exportBtn.addEventListener('click', () => {
                // export visible rows to CSV
                const table = document.querySelector('table');
                if (!table) return;
                const rowsAll = Array.from(table.querySelectorAll('tr'));
                const csv = rowsAll.map(tr => {
                    if (tr.style.display === 'none') return null;
                    const cells = Array.from(tr.querySelectorAll('th,td')).map(td => '"' + (td.innerText||'').replace(/"/g,'""') + '"');
                    return cells.join(',');
                }).filter(Boolean).join('\n');
                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'cases_export.csv';
                document.body.appendChild(a); a.click(); a.remove();
                URL.revokeObjectURL(url);
            });

            // Archive button handler: open a year picker modal populated from case Date Filed values
            const archiveBtn = document.getElementById('archiveBtn');
            const archiveBtnMobile = document.getElementById('archiveBtnMobile');

            function openArchiveModal() {
                // collect all years available from the table (Date Filed column at index 4)
                const rowsAll = Array.from(document.querySelectorAll('#casesTable tr'));
                const yearsSet = new Set();
                rowsAll.forEach(r => {
                    const cells = r.querySelectorAll('td');
                    if (!cells.length) return;
                    const dateStr = cells[4]?.innerText;
                    const yr = parseYear(dateStr);
                    if (yr) yearsSet.add(yr);
                });
                const years = Array.from(yearsSet).sort((a,b) => b - a);
                if (!years.length) {
                    alert('No case dates found to choose a year.');
                    return;
                }

                // build modal
                const overlay = document.createElement('div');
                overlay.className = 'fixed inset-0 bg-black/40 z-50 flex items-center justify-center';
                const modal = document.createElement('div');
                modal.className = 'bg-white rounded-lg shadow-lg p-6 w-full max-w-sm';
                modal.innerHTML = `
                    <h3 class="text-lg font-semibold mb-3">Archive by Year</h3>
                    <p class="text-sm text-gray-600 mb-3">Choose a year to view or archive cases from that year.</p>
                    <div class="mb-4">
                        <label class="block text-xs text-gray-600 mb-1">Year</label>
                        <select id="archiveYearSelect" class="w-full border rounded px-3 py-2">
                            <option value="all">All Years</option>
                            ${years.map(y => `<option value="${y}">${y}</option>`).join('')}
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button id="archiveShowBtn" class="px-3 py-2 rounded bg-primary-50 text-primary-700 border">Show</button>
                        <button id="archiveCancelBtn" class="px-3 py-2 rounded border">Cancel</button>
                    </div>`;
                overlay.appendChild(modal);
                document.body.appendChild(overlay);

                const yearSelect = modal.querySelector('#archiveYearSelect');
                const showBtn = modal.querySelector('#archiveShowBtn');
                const doBtn = modal.querySelector('#archiveDoBtn');
                const cancelBtn = modal.querySelector('#archiveCancelBtn');

                function closeModal() { overlay.remove(); }

                // Show: filter the table to the selected year (applies to the yearFilter and triggers filterTable)
                showBtn.addEventListener('click', () => {
                    const y = yearSelect.value;
                    if (!y) return;
                    // If user selected "All Years", clear the main yearFilter so no year restriction is applied
                    if (y === 'all') {
                        if (yearFilter) yearFilter.value = '';
                    } else {
                        // Ensure the main year select contains this year option so setting value takes effect
                        if (yearFilter) {
                            const exists = Array.from(yearFilter.options).some(o => String(o.value) === String(y));
                            if (!exists) {
                                const opt = document.createElement('option');
                                opt.value = y; opt.text = y;
                                yearFilter.appendChild(opt);
                            }
                            yearFilter.value = y;
                        }
                    }
                    // clear month to show whole year
                    if (monthFilter) monthFilter.value = '';
                    // sync chips/select
                    chipStatusOverride = '';
                    selectStatusOverride = '';
                    if (caseStatusSelect) caseStatusSelect.value = '';
                    // apply filter and close
                    filterTable();
                    closeModal();
                });

                // Archive Year: mark rows in that year as archived (client-side only) after confirmation
                // Only attach handler if the Archive button exists (we may intentionally remove that button)
                if (doBtn) {
                    doBtn.addEventListener('click', () => {
                        const y = Number(yearSelect.value);
                        if (!y) return;
                        const yearRows = rowsAll.filter(r => {
                            const cells = r.querySelectorAll('td');
                            if (!cells.length) return false;
                            const d = parseYMD(cells[4]?.innerText);
                            return d && d.getFullYear() === y;
                        });
                        if (!yearRows.length) {
                            alert(`No cases found for ${y}.`);
                            return;
                        }
                        if (!confirm(`Archive ${yearRows.length} case(s) from ${y}? This will only mark them on the client.`)) return;
                        yearRows.forEach(r => {
                            r.classList.add('opacity-60');
                            r.dataset.archived = '1';
                        });
                        alert(`${yearRows.length} case(s) from ${y} marked archived (client-side only).`);
                        closeModal();
                    });
                }

                cancelBtn.addEventListener('click', closeModal);
                // close on overlay click (but not when clicking modal)
                overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
            }

            // Attach to both desktop and mobile archive buttons if present
            [archiveBtn, archiveBtnMobile].forEach(b => { if (b) b.addEventListener('click', openArchiveModal); });

                    chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow','shadow-sm'));
                    chips.forEach(c => c.classList.remove('bg-primary-50','bg-amber-50','bg-purple-50','bg-green-50','bg-gray-50','bg-indigo-50','bg-yellow-50','bg-cyan-50'));
                    chipStatusOverride = chip.dataset.status || '';
                    // when chip is clicked, prefer chip selection and sync the select without setting select override
                    if (caseStatusSelect) caseStatusSelect.value = chipStatusOverride;
                    selectStatusOverride = '';
                    // Re-style active chip
                    chip.classList.add('active','bg-primary-600','text-white','shadow');
                    filterTable();
                });
            });
                    // On initial load: only default to "current year" if there are any current-year rows.
                    // This prevents hosted DBs (often older data) from showing 0 rows by default.
                    try {
                        const currentYear = new Date().getFullYear();
                        const yearsSet = new Set();
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td');
                            if (!cells.length) return;
                            const dateStr = cells[4]?.innerText;
                            const yr = parseYear(dateStr);
                            if (yr) yearsSet.add(yr);
                        });

                        if (yearFilter && yearsSet.size) {
                            if (yearsSet.has(currentYear)) {
                                // ensure the select contains the current year option so setting value takes effect
                                const exists = Array.from(yearFilter.options).some(o => String(o.value) === String(currentYear));
                                if (!exists) {
                                    const opt = document.createElement('option');
                                    opt.value = currentYear; opt.text = currentYear;
                                    yearFilter.appendChild(opt);
                                }
                                yearFilter.value = String(currentYear);

                                // clear month and status overrides so the year selection is the primary filter
                                if (monthFilter) monthFilter.value = '';
                                chipStatusOverride = '';
                                selectStatusOverride = '';

                                // visually deactivate the 'All' chip so it's clear a year filter is active
                                chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow','shadow-sm'));
                                const allChip = document.querySelector('.status-chip[data-status=""]');
                                if (allChip) allChip.classList.remove('active','bg-primary-600','text-white','shadow');
                            } else {
                                // show all years
                                yearFilter.value = '';
                                chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow','shadow-sm'));
                                const allChip = document.querySelector('.status-chip[data-status=""]');
                                if (allChip) allChip.classList.add('active','bg-primary-600','text-white','shadow');
                            }
                        }
                    } catch (e) {
                        console.error('Initial year selection check failed', e);
                    }

                    filterTable(); // initial count
            // Make table rows clickable: open case details (ignore clicks on anchors/buttons inside row)
            document.querySelectorAll('#casesTable tr[data-href]').forEach(tr => {
                tr.addEventListener('click', function(e){
                    // If the user clicked on an interactive element, do nothing
                    if (e.target.closest('a, button')) return;
                    const url = this.dataset.href;
                    if (!url) return;
                    if (e.ctrlKey || e.metaKey) window.open(url, '_blank');
                    else window.location.href = url;
                });
                // Keyboard support: Enter opens the link
                tr.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') {
                        const url = this.dataset.href;
                        if (url) window.location.href = url;
                    }
                });
            });
            
            // Mobile navigation toggle
            if (typeof menuButton !== 'undefined' && typeof mobileMenu !== 'undefined') {
                menuButton.addEventListener('click', function() {
                    this.classList.toggle('active');
                    if (mobileMenu.style.transform === 'translateY(0%)') {
                        mobileMenu.style.transform = 'translateY(-100%)';
                    } else {
                        mobileMenu.style.transform = 'translateY(0%)';
                    }
                });
            }
        });
    </script>
    <?php include 'sidebar_.php'; ?>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>