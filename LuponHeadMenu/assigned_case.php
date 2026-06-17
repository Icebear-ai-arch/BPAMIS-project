<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Redirect if not logged in
if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

$luponId = $_SESSION['official_id'];

// First, get the Lupon full name from barangay_officials to match with mediator_name
$stmt = $conn->prepare("SELECT Name FROM barangay_officials WHERE Official_ID = ?");
$stmt->bind_param("i", $luponId);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

if ($result && $row = $result->fetch_assoc()) {
    $luponName = $row['Name'];
} else {
    $luponName = ''; // fallback
}
$stmt->close();

// Query cases assigned to this Lupon (check mediator_name contains the Lupon's name)
$hasDesc = false;
if ($chk = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'Complaint_Description'")) {
    $hasDesc = $chk->num_rows > 0;
    $chk->close();
}
$descField = $hasDesc ? 'co.Complaint_Description' : 'co.Complaint_Title';

$sqlCases = "
    SELECT 
        ci.Case_ID, 
        ci.case_original_id,
        " . $descField . " AS complaint_desc,
        co.Complaint_ID AS Complaint_ID,
        ci.Case_Status,
        COALESCE(NULLIF(TRIM(LOWER(co.case_type)), ''), '') AS case_type,
        (
            SELECT sl.hearingdatetime
            FROM schedule_list sl
            WHERE sl.case_id = ci.Case_ID
            ORDER BY sl.hearingdatetime ASC
            LIMIT 1
        ) AS next_hearing
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    LEFT JOIN mediation_info mi ON mi.case_id = ci.Case_ID
    LEFT JOIN resolution r ON r.case_id = ci.Case_ID
    LEFT JOIN conciliation c ON c.case_id = ci.Case_ID
    LEFT JOIN arbitration s ON s.case_id = ci.Case_ID
    WHERE (
        mi.mediator_name LIKE CONCAT('%', ?, '%') OR
        r.mediator_name LIKE CONCAT('%', ?, '%') OR
        c.mediator_name LIKE CONCAT('%', ?, '%') OR
        s.mediator_name LIKE CONCAT('%', ?, '%') OR
        ci.lupon_assign LIKE CONCAT('%', ?, '%')
    )
    ORDER BY ci.Case_ID ASC
";

$stmt = $conn->prepare($sqlCases);
if ($stmt) {
    // 5 placeholders in WHERE clause (mi, r, c, s, ci.lupon_assign)
    $stmt->bind_param("sssss", $luponName, $luponName, $luponName, $luponName, $luponName);
    $stmt->execute();
    $resultCases = bpamis_stmt_get_result($stmt);
    $stmt->close();
} else {
    // Fallback simple query if prepare fails (e.g., due to schema diffs)
    $esc = $conn->real_escape_string($luponName);
    $resultCases = $conn->query(
        "SELECT ci.Case_ID, " . $descField . " AS complaint_desc, co.Complaint_ID AS Complaint_ID, ci.Case_Status, 
            COALESCE(NULLIF(TRIM(LOWER(co.case_type)), ''), '') AS case_type,
            (
                SELECT sl.hearingdatetime FROM schedule_list sl
                WHERE sl.case_id = ci.Case_ID
                ORDER BY sl.hearingdatetime ASC LIMIT 1
            ) AS next_hearing
         FROM case_info ci
         JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
         LEFT JOIN mediation_info mi ON mi.case_id = ci.Case_ID
         LEFT JOIN resolution r ON r.case_id = ci.Case_ID
         LEFT JOIN settlement s ON s.case_id = ci.Case_ID
         WHERE (
            mi.mediator_name LIKE '%$esc%' OR
            r.mediator_name LIKE '%$esc%' OR
            s.mediator_name LIKE '%$esc%' OR
            ci.lupon_assign LIKE '%$esc%'
         )
         ORDER BY ci.Case_ID ASC"
    );
}

// Buffer results into array for easier processing and counts
$cases = [];
if ($resultCases) {
    while ($row = $resultCases->fetch_assoc()) {
        $cases[] = $row;
    }
}

// Compute counts and filters
$totalCount = count($cases);
$openCount = 0;
$ongoingCount = 0;
$closedCount = 0;
$hearingCount = 0;
$noHearingCount = 0;

$months = [];
$years = [];
$now = new DateTime();

foreach ($cases as $c) {
    $status = isset($c['Case_Status']) ? (string)$c['Case_Status'] : '';
    $st = strtolower($status);
    if (strpos($st, 'close') !== false) {
        $closedCount++;
    }
    if (strpos($st, 'open') !== false || strpos($st, 'assign') !== false || strpos($st, 'pend') !== false) {
        $openCount++;
    }
    if (strpos($st, 'ongo') !== false || strpos($st, 'progress') !== false || strpos($st, 'hearing') !== false || strpos($st, 'mediate') !== false || strpos($st, 'sched') !== false) {
        $ongoingCount++;
    }

    $nh = isset($c['next_hearing']) ? $c['next_hearing'] : '';
    if (!empty($nh)) {
        $hearingCount++;
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $nh);
        if ($dt) {
            $months[(int)$dt->format('n')] = $dt->format('F');
            $years[$dt->format('Y')] = true;
        }
    } else {
        $noHearingCount++;
    }
}

// Normalize months and years for selects
ksort($months);
$yearKeys = array_keys($years);
sort($yearKeys);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Assigned Cases - Lupon</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
                    animation: { 'float': 'float 3s ease-in-out infinite' },
                    keyframes: { float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-10px)' } } }
                }
            }
        }
    </script>
    <style>
        .gradient-bg { background: linear-gradient(to right, #f0f7ff, #e0effe); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        /* Make rows/cards clearly clickable */
        .case-row, .case-card { cursor: pointer; }
        /* Selected state for keyboard / programmatic selection */
        .case-row.selected, .case-card.selected { background-color: rgba(15, 23, 42, 0.04); outline: 2px solid rgba(12,156,237,0.12); }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            /* Page Header - More Compact */
            .w-full.mt-8 > .gradient-bg {
                padding: 1rem !important;
                margin-top: 0.5rem !important;
            }
            .gradient-bg h1 {
                font-size: 1rem !important;
                line-height: 1.3 !important;
            }
            .gradient-bg p {
                font-size: 0.7rem !important;
                margin-top: 0.5rem !important;
                line-height: 1.4 !important;
            }
            .gradient-bg .flex.flex-wrap.gap-3 {
                display: none !important;
            }
            
            /* Filter Section - Ultra Compact */
            .filter-section { 
                padding: 0.625rem !important; 
                margin-top: 0.75rem !important;
            }
            .filter-section h3 {
                font-size: 0.75rem !important;
            }
            .filter-section .grid { 
                gap: 0.375rem !important; 
            }
            .filter-section input, .filter-section select { 
                font-size: 0.7rem !important; 
                padding: 0.375rem 0.5rem !important;
                height: 32px !important;
            }
            .filter-section input {
                padding-left: 1.75rem !important;
            }
            .filter-section .fa-search {
                left: 0.5rem !important;
                font-size: 0.7rem !important;
            }
            .filter-section select {
                padding-right: 1.5rem !important;
            }
            .filter-section .fa-caret-down {
                right: 0.5rem !important;
                font-size: 0.7rem !important;
            }
            .filter-section button { 
                font-size: 0.65rem !important; 
                padding: 0.375rem 0.625rem !important;
                height: 32px !important;
            }
            .filter-section .pt-2 {
                padding-top: 0.375rem !important;
            }
            .filter-section #visibleCount {
                font-size: 0.65rem !important;
            }
            
            /* Status Chips - Smaller */
            .status-chip { 
                font-size: 0.625rem !important; 
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Case Details Header */
            .bg-gradient-to-r.from-primary-50 {
                padding: 0.625rem 0.75rem !important;
            }
            .bg-gradient-to-r.from-primary-50 h3 {
                font-size: 0.75rem !important;
            }
            .bg-gradient-to-r.from-primary-50 p {
                font-size: 0.625rem !important;
                margin-top: 0.125rem !important;
            }
            
            /* Case Cards - Compact */
            .case-card {
                padding: 0.625rem !important;
                margin-bottom: 0 !important;
            }

            /* Mobile horizontal scroll layout for cards */
            #mobileCardContainer {
                display: flex;
                gap: 0.75rem;
                overflow-x: auto;
                padding: 0.75rem 0.75rem 1rem 0.75rem;
                -webkit-overflow-scrolling: touch;
            }
            #mobileCardContainer .case-card {
                min-width: 18rem; /* ~288px card width */
                flex: 0 0 auto;
                scroll-snap-align: start;
                border-radius: 0.75rem;
            }
            /* Add snapping for a nicer scroll feel */
            #mobileCardContainer { scroll-snap-type: x mandatory; }
            .case-card-header {
                font-size: 0.7rem !important;
                margin-bottom: 0.375rem !important;
                padding-bottom: 0.375rem !important;
            }
            .case-card-header span {
                font-size: 0.7rem !important;
            }
            .case-card-header a {
                font-size: 0.625rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            .case-card-header .fa-eye {
                font-size: 0.625rem !important;
            }
            
            /* Case Card Details */
            .case-card .space-y-2 {
                gap: 0.25rem !important;
            }
            .case-card-detail {
                font-size: 0.65rem !important;
                padding: 0.25rem 0 !important;
            }
            .case-card-detail strong {
                font-size: 0.625rem !important;
                width: 5rem !important;
            }
            .case-card-detail .text-xs {
                font-size: 0.65rem !important;
            }
            
            /* Badges - Smaller */
            .badge-sm {
                font-size: 0.575rem !important;
                padding: 0.125rem 0.375rem !important;
            }
            .badge-sm i {
                font-size: 0.5rem !important;
            }
            
            /* Icons in cards */
            .case-card-detail .fa-calendar-days,
            .case-card-detail .fa-clock {
                font-size: 0.625rem !important;
            }
            
            /* Empty State */
            #emptyState {
                padding: 2rem 1rem !important;
            }
            #emptyState i {
                font-size: 2rem !important;
                margin-bottom: 0.5rem !important;
            }
            #emptyState p {
                font-size: 0.7rem !important;
            }
            
            /* Bottom padding adjustment */
            .w-full.mt-8.px-4.pb-8 {
                padding-bottom: 2rem !important;
            }
            
            /* Space between sections */
            .max-w-7xl.mx-auto.space-y-6 {
                gap: 0.75rem !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans relative overflow-x-hidden">
    <?php include '../includes/lupon_head_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    
    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>

    <!-- Page Header (Enhanced Hero) -->
            <div class="w-full mt-8 px-4">
                <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden max-w-7xl mx-auto">
                    <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[620px] h-[620px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
                    <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                        <div class="max-w-2xl">
                            <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Assigned Cases</span></h1>
                            <p class="mt-4 text-gray-600 leading-relaxed">Review details and monitor progress. Use the smart filters below to quickly narrow your assigned workload.</p>
                            <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                                <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Smart Filters</span>
                                <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> Status Insights</span>
                                <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-primary-500"></i> Recent Focus</span>
                            </div>
                        </div>
                        <div class="hidden md:flex flex-col gap-3 min-w-[260px]">
                            <div class="text-xs text-gray-500">Lupon: <span class="font-medium text-gray-700"><?= htmlspecialchars($luponName) ?></span></div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">Total</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= (int)$totalCount ?></span></div>
                                <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">Open</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= (int)$openCount ?></span></div>
                                <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">On-going</span><span class="mt-1 text-lg font-semibold text-amber-700"><?= (int)$ongoingCount ?></span></div>
                                <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-emerald-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-emerald-600 font-semibold">With Hearing</span><span class="mt-1 text-lg font-semibold text-emerald-700"><?= (int)$hearingCount ?></span></div>
                                <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-gray-200 shadow-sm col-span-2"><span class="text-[10px] uppercase tracking-wide text-gray-600 font-semibold">Closed</span><span class="mt-1 text-lg font-semibold text-gray-700"><?= (int)$closedCount ?></span></div>
                            </div>
                            <div class="text-[11px] text-primary-700/70 text-center">Overview</div>
                        </div>
                    </div>
                </div>
            </div>

    <div class="w-full mt-8 px-4 pb-8">
        <div class="max-w-7xl mx-auto space-y-6">
        
        <?php if (!empty($cases)): ?>
        
        <!-- Filter Section -->
        <div class="filter-section relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
            <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
            <div class="relative z-10 space-y-5">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center justify-between md:justify-start gap-3 text-primary-700/80 text-sm font-medium w-full md:w-auto">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100">
                                <i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter
                            </span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100">
                                <i class="fa-solid fa-sliders text-primary-500"></i> Refine Results
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Status Chips -->
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="button" data-status="" class="status-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm transition hover:shadow">All Cases</button>
                    <button type="button" data-status="Conciliation" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition">Conciliation</button>
                    <button type="button" data-status="Arbitration" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100 transition">Arbitration</button>
                    <button type="button" data-status="Closed" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-gray-50 text-gray-700 border border-gray-100 hover:bg-gray-100 transition">Closed</button>
                </div>

                <!-- Search Field (Full Width Row on Mobile) -->
                <div class="relative group">
                    <input type="text" id="searchInput" placeholder="Search by case ID, type, or status..." 
                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                    <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                </div>

                <!-- Month, Year, Reset in One Row -->
                <div class="grid grid-cols-3 gap-2">
                    <!-- Month -->
                    <div class="relative">
                        <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Months</option>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                    </div>
                    <!-- Year -->
                    <div class="relative">
                        <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Years</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                    </div>
                    <!-- Reset -->
                    <div class="flex">
                        <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition">
                            <i class="fa-solid fa-rotate-left"></i>
                            <span class="hidden xl:inline">Reset</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cases Display Section -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <!-- Section Header -->
            <div class="bg-gradient-to-r from-primary-50 to-primary-100/50 px-5 py-4 border-b border-primary-200">
                <h3 class="text-base font-semibold text-primary-900 flex items-center gap-2">
                    <i class="fa-solid fa-folder-open"></i>
                    Case Details
                </h3>
                <p class="text-xs text-primary-700/70 mt-1">View and manage your assigned cases</p>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-[15%]">Case ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-[20%]">Case Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-[18%]">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-[32%]">Next Hearing</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider w-[15%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="casesTableBody" class="divide-y divide-gray-100">
                        <?php foreach ($cases as $case): 
                            $status = isset($case['Case_Status']) ? (string)$case['Case_Status'] : '';
                            $st = strtolower($status);
                            $hasHearing = !empty($case['next_hearing']);
                            $ctype = isset($case['case_type']) ? strtolower(trim($case['case_type'])) : '';
                            $ctypeLabel = $ctype ?: 'Not set';
                            $ctypeClass = 'text-gray-700 bg-gray-50 border-gray-300';
                            if ($ctype === 'civil case') { $ctypeLabel = 'Civil Case'; $ctypeClass = 'text-sky-700 bg-sky-50 border-sky-300'; }
                            elseif ($ctype === 'criminal case') { $ctypeLabel = 'Criminal Case'; $ctypeClass = 'text-rose-700 bg-rose-50 border-rose-300'; }
                            elseif ($ctype === 'blotter') { $ctypeLabel = 'Blotter'; $ctypeClass = 'text-slate-700 bg-slate-50 border-slate-300'; }
                            
                            $statusClass = 'text-gray-700 bg-gray-100 border-gray-300';
                            $icon = 'fa-info-circle';
                            if (stripos($status,'Open') !== false) { $statusClass = 'text-blue-700 bg-blue-100 border-blue-300'; $icon = 'fa-folder-open'; }
                            elseif (stripos($status,'Conciliation') !== false) { $statusClass = 'text-cyan-700 bg-cyan-100 border-cyan-300'; $icon = 'fa-handshake'; }
                            elseif (stripos($status,'Arbitration') !== false) { $statusClass = 'text-indigo-700 bg-indigo-100 border-indigo-300'; $icon = 'fa-gavel'; }
                            elseif (stripos($status,'Pending') !== false) { $statusClass = 'text-amber-700 bg-amber-100 border-amber-300'; $icon = 'fa-clock'; }
                            elseif (stripos($status,'Resolved') !== false) { $statusClass = 'text-green-700 bg-green-100 border-green-300'; $icon = 'fa-check-circle'; }
                            elseif (stripos($status,'Closed') !== false) { $statusClass = 'text-gray-600 bg-gray-100 border-gray-300'; $icon = 'fa-folder'; }
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors case-row" tabindex="0" role="button"
                            data-status="<?= htmlspecialchars($status) ?>"
                            data-has-hearing="<?= $hasHearing ? '1' : '0' ?>"
                            data-case-type="<?= htmlspecialchars($ctype) ?>"
                            data-caseid="<?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?>"
                            data-casepk="<?= htmlspecialchars($case['Case_ID']) ?>"
                            data-hearing="<?= htmlspecialchars($case['next_hearing'] ?? '') ?>">
                            <td class="px-4 py-3">
                                <span class="font-mono text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs border font-medium <?= $ctypeClass ?>">
                                    <i class="fa-solid fa-tag"></i>
                                    <?= htmlspecialchars($ctypeLabel) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs border font-medium <?= $statusClass ?>">
                                    <i class="fas <?= $icon ?>"></i>
                                    <?= htmlspecialchars($status ?: 'Pending') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php if (!empty($case['next_hearing'])): ?>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-calendar-days text-primary-500 text-xs"></i>
                                        <span><?= date("M d, Y", strtotime($case['next_hearing'])) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                        <i class="fa-solid fa-clock"></i>
                                        <span><?= date("h:i A", strtotime($case['next_hearing'])) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">No hearing scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="view_case_details.php?id=<?= urlencode($case['Case_ID']) ?>" 
                                   class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors gap-1.5"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (horizontal scroll on mobile) -->
            <div class="md:hidden" id="mobileCardContainer">
                <?php foreach ($cases as $case): 
                    $status = isset($case['Case_Status']) ? (string)$case['Case_Status'] : '';
                    $hasHearing = !empty($case['next_hearing']);
                    $ctype = isset($case['case_type']) ? strtolower(trim($case['case_type'])) : '';
                    $ctypeLabel = $ctype ?: 'Not set';
                    $ctypeClass = 'text-gray-700 bg-gray-50 border-gray-300';
                    if ($ctype === 'civil case') { $ctypeLabel = 'Civil Case'; $ctypeClass = 'text-sky-700 bg-sky-50 border-sky-300'; }
                    elseif ($ctype === 'criminal case') { $ctypeLabel = 'Criminal Case'; $ctypeClass = 'text-rose-700 bg-rose-50 border-rose-300'; }
                    elseif ($ctype === 'blotter') { $ctypeLabel = 'Blotter'; $ctypeClass = 'text-slate-700 bg-slate-50 border-slate-300'; }
                    
                    $statusClass = 'text-gray-700 bg-gray-100 border-gray-300';
                    $icon = 'fa-info-circle';
                    if (stripos($status,'Open') !== false) { $statusClass = 'text-blue-700 bg-blue-100 border-blue-300'; $icon = 'fa-folder-open'; }
                    elseif (stripos($status,'Conciliation') !== false) { $statusClass = 'text-cyan-700 bg-cyan-100 border-cyan-300'; $icon = 'fa-handshake'; }
                    elseif (stripos($status,'Arbitration') !== false) { $statusClass = 'text-indigo-700 bg-indigo-100 border-indigo-300'; $icon = 'fa-gavel'; }
                    elseif (stripos($status,'Pending') !== false) { $statusClass = 'text-amber-700 bg-amber-100 border-amber-300'; $icon = 'fa-clock'; }
                    elseif (stripos($status,'Resolved') !== false) { $statusClass = 'text-green-700 bg-green-100 border-green-300'; $icon = 'fa-check-circle'; }
                    elseif (stripos($status,'Closed') !== false) { $statusClass = 'text-gray-600 bg-gray-100 border-gray-300'; $icon = 'fa-folder'; }
                ?>
                 <div class="case-card p-3 hover:bg-gray-50 transition-colors flex items-center" tabindex="0" role="button"
                         data-status="<?= htmlspecialchars($status) ?>"
                         data-has-hearing="<?= $hasHearing ? '1' : '0' ?>"
                         data-case-type="<?= htmlspecialchars($ctype) ?>"
                         data-caseid="<?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?>"
                         data-casepk="<?= htmlspecialchars($case['Case_ID']) ?>"
                         data-hearing="<?= htmlspecialchars($case['next_hearing'] ?? '') ?>">

                        <div class="w-24 flex-shrink-0 font-mono text-sm font-semibold text-gray-900">#<?= htmlspecialchars($case['case_original_id'] ?? $case['Case_ID']) ?></div>

                        <div class="flex-shrink-0 w-28">
                            <span class="badge-sm inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border font-medium <?= $ctypeClass ?>">
                                <i class="fa-solid fa-tag text-[9px]"></i>
                                <?= htmlspecialchars($ctypeLabel) ?>
                            </span>
                        </div>

                        <div class="flex-shrink-0 w-28">
                            <span class="badge-sm inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border font-medium <?= $statusClass ?>">
                                <i class="fas <?= $icon ?> text-[9px]"></i>
                                <?= htmlspecialchars($status ?: 'Pending') ?>
                            </span>
                        </div>

                        <div class="flex-1 text-xs text-gray-700">
                            <?php if (!empty($case['next_hearing'])): ?>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-calendar-days text-primary-500 text-[10px]"></i>
                                    <span><?= date("M d, Y", strtotime($case['next_hearing'])) ?></span>
                                    <span class="mx-1 text-gray-400">•</span>
                                    <i class="fa-solid fa-clock text-[10px]"></i>
                                    <span><?= date("h:i A", strtotime($case['next_hearing'])) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 italic">Not scheduled</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex-shrink-0 ml-3">
                            <a href="view_case_details.php?id=<?= urlencode($case['Case_ID']) ?>" 
                               class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 transition-colors gap-1">
                                <i class="fas fa-eye text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="hidden p-8 text-center">
                <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-3"></i>
                <p class="text-sm text-gray-500">No cases match your filters</p>
            </div>
        </div>

        <?php else: ?>
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-12 text-center">
                <i class="fa-solid fa-folder-open text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">No Cases Assigned</h3>
                <p class="text-sm text-gray-500">You don't have any cases assigned to you at the moment.</p>
            </div>
        <?php endif; ?>
        
        </div>
    </div>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    const resetBtn = document.getElementById('resetFilters');
    const desktopRows = document.querySelectorAll('#casesTableBody .case-row');
    const mobileCards = document.querySelectorAll('#mobileCardContainer .case-card');
    const visibleCount = document.getElementById('visibleCount');
    const emptyState = document.getElementById('emptyState');
    const chips = document.querySelectorAll('.status-chip');
    let chipStatusOverride = '';

    function parseSqlDateTime(dt) {
        if (!dt) return null;
        let d = new Date(dt.replace(' ', 'T'));
        if (!isNaN(d)) return d;
        const m = dt.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (m) {
            return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), Number(m[6] || '0'));
        }
        return null;
    }

    function filterTable() {
        const searchQuery = (searchInput.value || '').toLowerCase();
        const statusQuery = (chipStatusOverride || '').toLowerCase();
        const selectedMonth = monthFilter.value ? parseInt(monthFilter.value) : null;
        const selectedYear = yearFilter.value ? parseInt(yearFilter.value) : null;
        let shown = 0;

        // Filter desktop rows
        desktopRows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            const statusText = row.dataset.status ? row.dataset.status.toLowerCase() : '';
            const hearingISO = row.dataset.hearing || '';
            
            let matchesMonth = true;
            let matchesYear = true;
            if (selectedMonth || selectedYear) {
                if (!hearingISO) {
                    matchesMonth = false;
                    matchesYear = false;
                } else {
                    const d = parseSqlDateTime(hearingISO);
                    if (!d || isNaN(d)) {
                        matchesMonth = false;
                        matchesYear = false;
                    } else {
                        if (selectedMonth) {
                            matchesMonth = (d.getMonth() + 1) === selectedMonth;
                        }
                        if (selectedYear) {
                            matchesYear = d.getFullYear() === selectedYear;
                        }
                    }
                }
            }
            
            const matchesSearch = rowText.includes(searchQuery);
            const matchesStatus = !statusQuery || statusText.includes(statusQuery);
            const show = matchesSearch && matchesStatus && matchesMonth && matchesYear;
            
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });

        // Filter mobile cards
        mobileCards.forEach(card => {
            const cardText = card.innerText.toLowerCase();
            const statusText = card.dataset.status ? card.dataset.status.toLowerCase() : '';
            const hearingISO = card.dataset.hearing || '';
            
            let matchesMonth = true;
            let matchesYear = true;
            if (selectedMonth || selectedYear) {
                if (!hearingISO) {
                    matchesMonth = false;
                    matchesYear = false;
                } else {
                    const d = parseSqlDateTime(hearingISO);
                    if (!d || isNaN(d)) {
                        matchesMonth = false;
                        matchesYear = false;
                    } else {
                        if (selectedMonth) {
                            matchesMonth = (d.getMonth() + 1) === selectedMonth;
                        }
                        if (selectedYear) {
                            matchesYear = d.getFullYear() === selectedYear;
                        }
                    }
                }
            }
            
            const matchesSearch = cardText.includes(searchQuery);
            const matchesStatus = !statusQuery || statusText.includes(statusQuery);
            const show = matchesSearch && matchesStatus && matchesMonth && matchesYear;
            
            card.style.display = show ? '' : 'none';
        });

        // Update counts
        const countSpan = visibleCount.querySelector('span.text-primary-600');
        if (countSpan) {
            countSpan.textContent = shown;
        } else {
            visibleCount.innerHTML = `<i class="fa-solid fa-list-check text-primary-500"></i> Showing <span class="text-primary-600 font-semibold">${shown}</span> cases`;
        }

        // Show/hide empty state
        if (emptyState) {
            if (shown === 0) {
                emptyState.classList.remove('hidden');
                document.querySelector('#casesTableBody')?.parentElement?.classList.add('hidden');
                document.querySelector('#mobileCardContainer')?.classList.add('hidden');
            } else {
                emptyState.classList.add('hidden');
                document.querySelector('#casesTableBody')?.parentElement?.classList.remove('hidden');
                document.querySelector('#mobileCardContainer')?.classList.remove('hidden');
            }
        }
    }

    function resetFilters() {
        if (searchInput) searchInput.value = '';
        if (monthFilter) monthFilter.value = '';
        if (yearFilter) yearFilter.value = '';
        chipStatusOverride = '';
        
        chips.forEach(c => {
            c.classList.remove('active', 'bg-primary-600', 'text-white', 'shadow-sm');
            c.classList.add('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
        });
        
        const allChip = document.querySelector('.status-chip[data-status=""]');
        if (allChip) {
            allChip.classList.remove('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
            allChip.classList.add('active', 'bg-primary-600', 'text-white', 'shadow-sm');
        }
        
        filterTable();
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (monthFilter) monthFilter.addEventListener('change', filterTable);
    if (yearFilter) yearFilter.addEventListener('change', filterTable);
    if (resetBtn) resetBtn.addEventListener('click', resetFilters);

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            chips.forEach(c => {
                c.classList.remove('active', 'bg-primary-600', 'text-white', 'shadow-sm');
                c.classList.add('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
            });
            
            chip.classList.remove('bg-blue-50', 'text-blue-700', 'border', 'border-blue-100');
            chip.classList.add('active', 'bg-primary-600', 'text-white', 'shadow-sm');
            
            chipStatusOverride = chip.dataset.status || '';
            filterTable();
        });
    });

    // Initial filter to set counts
    filterTable();

    // Make rows/cards clickable to view details
    function attachRowClickHandlers() {
        // Desktop rows
        const rows = document.querySelectorAll('.case-row');
        rows.forEach(row => {
            // Click to select and open
            row.addEventListener('click', function(e) {
                // Ignore clicks on actionable elements (buttons, links)
                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                if (tag === 'a' || tag === 'button' || (e.target.closest && e.target.closest('a, button'))) return;
                // mark selected visually
                document.querySelectorAll('.case-row.selected').forEach(r => r.classList.remove('selected'));
                row.classList.add('selected');
                const pk = row.dataset.casepk || row.getAttribute('data-casepk');
                if (pk) {
                    window.location.href = 'view_case_details.php?id=' + encodeURIComponent(pk);
                }
            });

            // Keyboard: Enter or Space opens the view
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    // mark selected visually
                    document.querySelectorAll('.case-row.selected').forEach(r => r.classList.remove('selected'));
                    row.classList.add('selected');
                    const pk = row.dataset.casepk || row.getAttribute('data-casepk');
                    if (pk) window.location.href = 'view_case_details.php?id=' + encodeURIComponent(pk);
                }
            });
        });

        // Mobile cards
        const cards = document.querySelectorAll('.case-card');
        cards.forEach(card => {
            // Touch helpers to distinguish tap vs scroll on horizontal carousel
            let touchStartX = 0, touchStartY = 0, touchMoved = false;
            card.addEventListener('touchstart', function(e) {
                if (e.touches && e.touches[0]) {
                    touchStartX = e.touches[0].clientX;
                    touchStartY = e.touches[0].clientY;
                    touchMoved = false;
                }
            }, { passive: true });
            card.addEventListener('touchmove', function(e) {
                if (e.touches && e.touches[0]) {
                    const dx = Math.abs(e.touches[0].clientX - touchStartX);
                    const dy = Math.abs(e.touches[0].clientY - touchStartY);
                    if (dx > 10 || dy > 10) touchMoved = true;
                }
            }, { passive: true });
            card.addEventListener('touchend', function(e) {
                // Only treat as a tap if the touch didn't move (i.e., not a scroll)
                if (touchMoved) return;
                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                if (tag === 'a' || tag === 'button' || (e.target.closest && e.target.closest('a, button'))) return;
                document.querySelectorAll('.case-card.selected').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const pk = card.dataset.casepk || card.getAttribute('data-casepk');
                if (pk) {
                    window.location.href = 'view_case_details.php?id=' + encodeURIComponent(pk);
                }
            });

            card.addEventListener('click', function(e) {
                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                if (tag === 'a' || tag === 'button' || (e.target.closest && e.target.closest('a, button'))) return;
                document.querySelectorAll('.case-card.selected').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const pk = card.dataset.casepk || card.getAttribute('data-casepk');
                if (pk) {
                    window.location.href = 'view_case_details.php?id=' + encodeURIComponent(pk);
                }
            });

            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    document.querySelectorAll('.case-card.selected').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    const pk = card.dataset.casepk || card.getAttribute('data-casepk');
                    if (pk) window.location.href = 'view_case_details.php?id=' + encodeURIComponent(pk);
                }
            });
        });
    }

    attachRowClickHandlers();
});
</script>

<?php $conn->close(); ?>
