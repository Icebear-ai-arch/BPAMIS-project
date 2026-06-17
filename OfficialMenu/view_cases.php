<?php
/**
 * View Cases Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
$pageTitle = "View Cases";
// Compute case status counts for header overview
$caseCounts = [];
try {
    $conn_counts = $conn;
    if (!$conn_counts->connect_error) {
        $resC = $conn_counts->query("SELECT Case_Status, COUNT(*) total FROM CASE_INFO GROUP BY Case_Status");
        if ($resC) {
            // Track specific status categories
            while ($rC = $resC->fetch_assoc()) {
                $raw = $rC['Case_Status'];
                $count = (int)$rC['total'];
                
                if (is_string($raw)) {
                    $low = strtolower($raw);
                    
                    // Check for resolved variants first
                    if (stripos($low, 'mediation') !== false && stripos($low, 'resolved') !== false) {
                        $caseCounts['Mediation Resolved'] = ($caseCounts['Mediation Resolved'] ?? 0) + $count;
                    } elseif (stripos($low, 'conciliation') !== false && stripos($low, 'resolved') !== false) {
                        $caseCounts['Conciliation Resolved'] = ($caseCounts['Conciliation Resolved'] ?? 0) + $count;
                    } elseif (stripos($low, 'arbitration') !== false && stripos($low, 'resolved') !== false) {
                        $caseCounts['Arbitration Resolved'] = ($caseCounts['Arbitration Resolved'] ?? 0) + $count;
                    }
                    // Then check for base statuses
                    elseif (stripos($low, 'mediation') !== false) {
                        $caseCounts['Mediation'] = ($caseCounts['Mediation'] ?? 0) + $count;
                    } elseif (stripos($low, 'conciliation') !== false) {
                        $caseCounts['Conciliation'] = ($caseCounts['Conciliation'] ?? 0) + $count;
                    } elseif (stripos($low, 'arbitration') !== false) {
                        $caseCounts['Arbitration'] = ($caseCounts['Arbitration'] ?? 0) + $count;
                    } elseif (stripos($low, 'certificate') !== false || stripos($low, 'file action') !== false || stripos($low, 'cfa') !== false) {
                        $caseCounts['CFA'] = ($caseCounts['CFA'] ?? 0) + $count;
                    } elseif (stripos($low, 'dismissed') !== false) {
                        $caseCounts['Dismissed'] = ($caseCounts['Dismissed'] ?? 0) + $count;
                    }
                }
            }
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
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>

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
    <div class="relative max-w-screen-2xl mx-auto mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
        <div class="relative gradient-bg rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-6 md:p-8 lg:p-10 overflow-hidden relative max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[620px] h-[620px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6 md:gap-8">
            <div class="max-w-2xl">
                <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">Barangay Cases</span></h1>
                <p class="mt-2 sm:mt-3 md:mt-4 text-xs sm:text-sm md:text-base text-gray-600 leading-relaxed">Browse, review details, and monitor resolution progress. Use the smart filters below to quickly narrow results.</p>
                <div class="mt-3 sm:mt-4 md:mt-5 flex flex-wrap gap-2 sm:gap-3 text-[10px] sm:text-xs text-primary-700/80 font-medium">
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> <span class="hidden sm:inline">Smart Filters</span></span>
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> <span class="hidden sm:inline">Status Insights</span></span>
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-primary-500"></i> <span class="hidden sm:inline">Recent Focus</span></span>
                </div>
            </div>
            <div class="hidden md:flex flex-col gap-3 min-w-[280px]">
                <div class="grid grid-cols-4 gap-2">
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-purple-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-purple-600 font-semibold">Mediation</span><span class="mt-1 text-base font-semibold text-purple-700"><?= cc('Mediation',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-indigo-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-indigo-600 font-semibold">Conciliation</span><span class="mt-1 text-base font-semibold text-indigo-700"><?= cc('Conciliation',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-yellow-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-yellow-700 font-semibold">Arbitration</span><span class="mt-1 text-base font-semibold text-yellow-800"><?= cc('Arbitration',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-purple-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-purple-600 font-semibold">Med. Resolved</span><span class="mt-1 text-base font-semibold text-purple-700"><?= cc('Mediation Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-indigo-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-indigo-600 font-semibold">Con. Resolved</span><span class="mt-1 text-base font-semibold text-indigo-700"><?= cc('Conciliation Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-yellow-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-yellow-700 font-semibold">Arb. Resolved</span><span class="mt-1 text-base font-semibold text-yellow-800"><?= cc('Arbitration Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-cyan-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-cyan-600 font-semibold">CFA</span><span class="mt-1 text-base font-semibold text-cyan-700"><?= cc('CFA',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-rose-100 shadow-sm"><span class="text-[9px] uppercase tracking-wide text-rose-600 font-semibold">Dismissed</span><span class="mt-1 text-base font-semibold text-rose-700"><?= cc('Dismissed',$caseCounts) ?></span></div>
                </div>
                <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
            </div>
        </div>
    </div>
    
    <div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
        <div class="max-w-screen-2xl mx-auto space-y-4 sm:space-y-6">
            <!-- Filters / Search Card -->
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-5 md:p-6 lg:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-3 sm:space-y-4 md:space-y-5">
                    <!-- Row 1: Search & Filter label + Schedule Hearing button -->
                    <div class="flex items-center justify-between gap-3 sm:gap-4">
                        <div class="flex items-center gap-2 sm:gap-3 text-primary-700/80 text-xs sm:text-sm font-medium">
                            <span class="inline-flex items-center gap-1.5 sm:gap-2 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500 text-xs sm:text-sm"></i> <span class="sm:inline">Search & Filter</span></span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Results</span>
                        </div>
                        <a href="appoint_hearing.php" class="group relative inline-flex items-center gap-1.5 sm:gap-2 px-3 py-2 sm:px-5 sm:py-2.5 rounded-lg sm:rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-calendar-plus text-white"></i>
                            <span>Schedule Hearing</span>
                            <span class="absolute inset-0 rounded-lg sm:rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    
                    <!-- Status Filter Chips -->
                    <div class="flex flex-wrap gap-1.5 sm:gap-2">
                        <button type="button" data-status="" class="status-chip active px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-status="Mediation" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Mediation</button>
                        <button type="button" data-status="Conciliation" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Conciliation</button>
                        <button type="button" data-status="Arbitration" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100 hover:bg-yellow-100 transition">Arbitration</button>
                        <button type="button" data-status="Mediation Resolved" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Mediation Resolved</button>
                        <button type="button" data-status="Conciliation Resolved" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Conciliation Resolved</button>
                        <button type="button" data-status="Arbitration Resolved" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100 hover:bg-yellow-100 transition">Arbitration Resolved</button>
                        <button type="button" data-status="CFA" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-cyan-50 text-cyan-600 border border-cyan-100 hover:bg-cyan-100 transition">Certificate to File Action</button>
                        <button type="button" data-status="Dismissed" class="status-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-100 transition">Dismissed</button>
                    </div>
                    
                    <!-- Row 2: Search input (full width on mobile) -->
                    <div class="relative group">
                        <input type="text" id="searchInput" placeholder="Search by case ID, complainant, respondent or type..." class="w-full pl-9 sm:pl-11 pr-3 sm:pr-4 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-xs sm:text-sm transition" />
                        <i class="fa-solid fa-search absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition text-xs sm:text-sm"></i>
                    </div>
                    
                    <!-- Row 3: Month, Year, Reset (single row on mobile) -->
                    <div class="grid grid-cols-[1fr_1fr_auto] gap-2 sm:gap-3">
                        <!-- Month -->
                        <div class="relative">
                            <select id="monthFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): $monthName = date('F', mktime(0,0,0,$m,1)); ?>
                                    <option value="<?= $m ?>"><?= $monthName ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                        </div>
                        <!-- Year -->
                        <div class="relative">
                            <select id="yearFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Years</option>
                                <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                        </div>
                        <!-- Reset -->
                        <div class="flex">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1 sm:gap-1.5 px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-xs sm:text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left text-xs sm:text-sm"></i><span class="hidden xl:inline">Reset</span></button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Cases Table Card -->
            <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 md:p-6 lg:p-8 overflow-hidden">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-800 flex items-center gap-1.5 sm:gap-2"><i class="fa-solid fa-table text-primary-500 text-sm sm:text-base"></i> <span class="text-sm sm:text-lg">Case Records</span></h2>
                    <span id="visibleCount" class="text-[10px] sm:text-xs px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full bg-primary-50 text-primary-600 font-medium border border-primary-100">0 Showing</span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="w-full mt-0">
                        <thead class="bg-primary-50/60">
                            <tr>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Case ID</th>
                                <!-- Added Case Original ID column -->
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
$sql = "SELECT 
    cs.Case_ID,
    cs.case_original_id,
    cs.Case_Status,
    ci.Complaint_ID,
    ci.Complaint_Title,
    ci.Date_Filed,
    COALESCE(res_com.First_Name, ext_com.First_Name) AS Complainant_First,
    COALESCE(res_com.Last_Name, ext_com.Last_Name) AS Complainant_Last
FROM CASE_INFO cs
LEFT JOIN COMPLAINT_INFO ci 
    ON cs.Complaint_ID = ci.Complaint_ID
LEFT JOIN RESIDENT_INFO res_com 
    ON ci.Resident_ID = res_com.Resident_ID
LEFT JOIN external_complainant ext_com 
    ON ci.external_complainant_id = ext_com.External_Complaint_ID
-- Exclude Record Purposes entries from the regular cases listing
WHERE (ci.case_type IS NULL OR ci.case_type NOT IN ('Blotter', 'Record Purposes'))
ORDER BY cs.Case_ID DESC";

$result = $conn->query($sql);                                         
if ($result->num_rows > 0):
    while ($case = $result->fetch_assoc()):
        $complaint_id = (int)$case['Complaint_ID'];
$respondent_names = [];

// Main respondent 
$main_res_sql = "SELECT First_Name, Last_Name FROM RESIDENT_INFO WHERE Resident_ID = (
    SELECT Respondent_ID FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id
)";
$main_res_result = $conn->query($main_res_sql);
if ($main_res_result && $main_res_result->num_rows > 0) {
    while ($row = $main_res_result->fetch_assoc()) {
        $respondent_names[] = $row['First_Name'] . ' ' . $row['Last_Name'];
    }
}

// Additional respondents
$other_res_sql = "SELECT ri.First_Name, ri.Last_Name 
    FROM COMPLAINT_RESPONDENTS cr
    JOIN RESIDENT_INFO ri ON cr.Respondent_ID = ri.Resident_ID
    WHERE cr.Complaint_ID = $complaint_id";
$other_res_result = $conn->query($other_res_sql);
if ($other_res_result && $other_res_result->num_rows > 0) {
    while ($row = $other_res_result->fetch_assoc()) {
        $respondent_names[] = $row['First_Name'] . ' ' . $row['Last_Name'];
    }
}

// Combine all
$respondents_display = !empty($respondent_names) ? implode(', ', $respondent_names) : 'N/A';

?>
<tr data-href="view_case_details_cap.php?id=<?= urlencode($case['Case_ID']) ?>" tabindex="0" class="border-b border-gray-100 hover:bg-primary-50/40 transition cursor-pointer">
    <!-- Added Case Original ID column -->
    <td class="p-3 text-sm text-gray-700 font-mono text-[11px] tracking-wide"><?= htmlspecialchars($case['case_original_id']) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($respondents_display) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Date_Filed']) ?></td>
    <td class="p-3 text-sm">
        <?php
    $statusRaw = $case['Case_Status'];
    // Display the original status text (so variants like 'Mediation Resolved' show fully)
    $statusDisplay = $statusRaw;
    // Normalize for badge styling and internal classification
    $statusNorm = $statusRaw;
    if (is_string($statusRaw)) {
        $low = strtolower($statusRaw);
        // Check for resolved variants first
        if (stripos($low, 'mediation') !== false && stripos($low, 'resolved') !== false) {
            $statusNorm = 'Mediation Resolved';
        } elseif (stripos($low, 'conciliation') !== false && stripos($low, 'resolved') !== false) {
            $statusNorm = 'Conciliation Resolved';
        } elseif (stripos($low, 'arbitration') !== false && stripos($low, 'resolved') !== false) {
            $statusNorm = 'Arbitration Resolved';
        }
        // Then check for base statuses
        elseif (stripos($low, 'mediation') !== false) {
            $statusNorm = 'Mediation';
        } elseif (stripos($low, 'conciliation') !== false) {
            $statusNorm = 'Conciliation';
        } elseif (stripos($low, 'arbitration') !== false) {
            $statusNorm = 'Arbitration';
        } elseif (stripos($low, 'certificate') !== false || stripos($low, 'file action') !== false || stripos($low, 'cfa') !== false) {
            $statusNorm = 'CFA';
        } elseif (stripos($low, 'dismissed') !== false) {
            $statusNorm = 'Dismissed';
        }
    }
    // Make cases that are resolved (any variant), dismissed, or closed view-only
    $isViewOnly = false;
    if (is_string($statusRaw) && (stripos($statusRaw, 'resolved') !== false || stripos($statusRaw, 'dismissed') !== false || stripos($statusRaw, 'closed') !== false)) {
        $isViewOnly = true;
    }
        $badge = [
            'Open' => ['text-blue-700 bg-blue-50 border-blue-200', 'fa-folder-open'],
            'Pending Hearing' => ['text-amber-700 bg-amber-50 border-amber-200', 'fa-calendar'],
            'Mediation' => ['text-purple-700 bg-purple-50 border-purple-200', 'fa-handshake'],
            'Conciliation' => ['text-indigo-700 bg-indigo-50 border-indigo-200', 'fa-hands-helping'],
            'Arbitration' => ['text-yellow-800 bg-yellow-50 border-yellow-200', 'fa-gavel'],
            'Mediation Resolved' => ['text-purple-700 bg-purple-50 border-purple-200', 'fa-check-circle'],
            'Conciliation Resolved' => ['text-indigo-700 bg-indigo-50 border-indigo-200', 'fa-check-circle'],
            'Arbitration Resolved' => ['text-yellow-800 bg-yellow-50 border-yellow-200', 'fa-check-circle'],
            'CFA' => ['text-cyan-700 bg-cyan-50 border-cyan-200', 'fa-file-signature'],
            'Dismissed' => ['text-rose-700 bg-rose-50 border-rose-200', 'fa-ban'],
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
            <a href="view_case_details_cap.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-primary-500 hover:text-white hover:bg-primary-500 transition text-sm" title="View Details"><i class="fas fa-eye"></i></a>

            <!--  -->
        </div>
    </td>
</tr>
<?php
    endwhile;
else:
    echo '<tr><td colspan="6" class="p-6 text-center text-gray-500 text-sm">No cases found.</td></tr>';
endif;
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
                    <?php include 'sidebar_.php';?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');
            const resetBtn = document.getElementById('resetFilters');
            const rows = document.querySelectorAll('#casesTable tr');
            const visibleCount = document.getElementById('visibleCount');
            const rangeDisplay = document.getElementById('rangeDisplay');
            const chips = document.querySelectorAll('.status-chip');
            let chipStatusOverride = '';

            function filterTable() {
                const searchQuery = searchInput.value.toLowerCase();
                const statusQuery = (chipStatusOverride || '').toLowerCase();
                const selectedMonth = monthFilter.value;
                const selectedYear = yearFilter.value;
                let shown = 0;

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (!cells.length) return; // skip if no data cells
                    const rowText = row.innerText.toLowerCase();
                    // Table columns: 0=Case ID,1=Complainant,2=Respondent,3=Date Filed,4=Status,5=Action
                    const statusText = cells[4]?.innerText.toLowerCase() || '';
                    const dateFiled = cells[3]?.innerText; // expect YYYY-MM-DD or similar

                    let matchesMonth = true;
                    let matchesYear = true;
                    if (dateFiled) {
                        const date = new Date(dateFiled);
                        if (selectedMonth) matchesMonth = (date.getMonth() + 1) == parseInt(selectedMonth);
                        if (selectedYear) matchesYear = date.getFullYear() == parseInt(selectedYear);
                    }

                    const matchesSearch = rowText.includes(searchQuery);
                    
                    // Status filtering - match exact status types
                    let matchesStatus = false;
                    if (!statusQuery) {
                        matchesStatus = true; // "All" selected
                    } else if (statusQuery === 'mediation resolved') {
                        matchesStatus = statusText.includes('mediation') && statusText.includes('resolved');
                    } else if (statusQuery === 'conciliation resolved') {
                        matchesStatus = statusText.includes('conciliation') && statusText.includes('resolved');
                    } else if (statusQuery === 'arbitration resolved') {
                        matchesStatus = statusText.includes('arbitration') && statusText.includes('resolved');
                    } else if (statusQuery === 'mediation') {
                        // Only match pure Mediation (not Mediation Resolved)
                        matchesStatus = statusText.includes('mediation') && !statusText.includes('resolved');
                    } else if (statusQuery === 'conciliation') {
                        // Only match pure Conciliation (not Conciliation Resolved)
                        matchesStatus = statusText.includes('conciliation') && !statusText.includes('resolved');
                    } else if (statusQuery === 'arbitration') {
                        // Only match pure Arbitration (not Arbitration Resolved)
                        matchesStatus = statusText.includes('arbitration') && !statusText.includes('resolved');
                    } else if (statusQuery === 'cfa') {
                        matchesStatus = statusText.includes('certificate') || statusText.includes('file action') || statusText.includes('cfa');
                    } else if (statusQuery === 'dismissed') {
                        matchesStatus = statusText.includes('dismissed');
                    } else {
                        matchesStatus = statusText.includes(statusQuery);
                    }

                    const show = matchesSearch && matchesStatus && matchesMonth && matchesYear;
                    row.style.display = show ? '' : 'none';
                    if (show) shown++;
                });
                visibleCount.textContent = shown + ' Showing';
                rangeDisplay.textContent = 'Showing ' + shown + ' entr' + (shown === 1 ? 'y' : 'ies');
            }

            function resetFilters() {
                searchInput.value = '';
                monthFilter.value = '';
                yearFilter.value = '';
                chipStatusOverride = '';
                filterTable();
            }

            searchInput.addEventListener('input', filterTable);
            monthFilter.addEventListener('change', filterTable);
            yearFilter.addEventListener('change', filterTable);
            resetBtn.addEventListener('click', resetFilters);

                    chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('active','bg-primary-600','text-white','shadow','shadow-sm'));
                    chips.forEach(c => c.classList.remove('bg-primary-50','bg-amber-50','bg-purple-50','bg-green-50','bg-gray-50','bg-indigo-50','bg-yellow-50','bg-cyan-50'));
                    chipStatusOverride = chip.dataset.status || '';
                    // Re-style active chip
                    chip.classList.add('active','bg-primary-600','text-white','shadow');
                    filterTable();
                });
            });
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
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
