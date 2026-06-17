<?php
/**
 * View Cases Page
 * Barangay Panducot Adjudication Management Information System
 */
session_start();
include_once __DIR__ . '/../../server/server.php';
$pageTitle = "View Cases";
// Compute case status counts for header overview
$caseCounts = [];
try {
    $conn_counts = $conn;
    if (!$conn_counts->connect_error) {
    // Only include statuses relevant to Lupon Head
    $resC = $conn_counts->query("SELECT Case_Status, COUNT(*) total FROM CASE_INFO WHERE Case_Status IN ('Conciliation','Arbitration') GROUP BY Case_Status");
        if ($resC) {
            while ($rC = $resC->fetch_assoc()) {
                $caseCounts[$rC['Case_Status']] = (int)$rC['total'];
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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <title>View Cases</title>
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
    <?php include '../includes/lupon_head_nav.php'; ?>

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
            <div class="hidden md:flex flex-col gap-3 min-w-[250px]">
                <div class="grid grid-cols-3 gap-2">
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">Open</span><span class="mt-1 text-lg font-semibold text-primary-700"><?= cc('Open',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">Pending Hearing</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= cc('Pending Hearing',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-rose-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-rose-600 font-semibold">Mediation</span><span class="mt-1 text-lg font-semibold text-rose-700"><?= cc('Mediation',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= cc('Resolved',$caseCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-gray-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-gray-600 font-semibold">Closed</span><span class="mt-1 text-lg font-semibold text-gray-700"><?= cc('Closed',$caseCounts) ?></span></div>
                </div>
                <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
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
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Results</span>
                        </div>
                        <a href="appoint_hearing.php" class="group relative inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-calendar-plus text-white"></i>
                            <span>Schedule Hearing</span>
                            <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    
                    
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" data-status="All" class="status-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm transition hover:shadow">All</button>
                        <button type="button" data-status="Resolution" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 transition">Resolution</button>
                        <button type="button" data-status="Arbitration" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100 transition">Arbitration</button>
                        <button type="button" data-status="History" class="status-chip px-3 py-1.5 text-xs font-medium rounded-full bg-primary-50 text-gray-700 border border-primary-100 hover:bg-primary-100 transition">History</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-7 relative group">
                            <input type="text" id="searchInput" placeholder="Search by case ID, complainant, respondent or type..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                        </div>
                        
                        <!-- Month -->
                        <div class="md:col-span-2 relative">
                            <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): $monthName = date('F', mktime(0,0,0,$m,1)); ?>
                                    <option value="<?= $m ?>"><?= $monthName ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <!-- Year -->
                        <div class="md:col-span-2 relative">
                            <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Years</option>
                                <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <!-- Reset -->
                        <div class="md:col-span-1 flex">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
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
                            
$sql = "SELECT 
    cs.Case_ID,
    cs.Case_Status,
    ci.Complaint_ID,
    ci.Complaint_Title,
    ci.case_type,
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
WHERE ci.case_type != 'Blotter'
AND cs.Case_Status IN ('Conciliation','Arbitration')
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
<tr class="border-b border-gray-100 hover:bg-primary-50/40 transition">
    <td class="p-3 text-sm text-gray-700 font-mono text-[11px] tracking-wide"><?= htmlspecialchars($case['Case_ID']) ?></td>
    <td class="p-3 text-sm text-gray-700">
        <?php
            $rawType = isset($case['case_type']) ? trim(strtolower($case['case_type'])) : '';
            if ($rawType === 'civil') $rawType = 'civil case';
            if ($rawType === 'criminal') $rawType = 'criminal case';
            if ($rawType === 'criminal') $rawType = 'Blotter';
            $label = 'Not set';
            $badgeClass = 'text-gray-700 bg-gray-50 border-gray-200';
            if ($rawType === 'civil case') { $label = 'Civil Case'; $badgeClass = 'text-sky-700 bg-sky-50 border-sky-200'; }
            elseif ($rawType === 'criminal case') { $label = 'Criminal Case'; $badgeClass = 'text-rose-700 bg-rose-50 border-rose-200'; }
            elseif ($rawType === 'blotter') { $label = 'Blotter'; $badgeClass = 'text-slate-700 bg-slate-50 border-slate-200'; }
        ?>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] border font-semibold <?= $badgeClass ?>">
            <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($label) ?>
        </span>
    </td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($respondents_display) ?></td>
    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($case['Date_Filed']) ?></td>
    <td class="p-3 text-sm">
        <?php
        $status = $case['Case_Status'];
        $badge = [
            'Open' => ['text-blue-700 bg-blue-50 border-blue-200', 'fa-folder-open'],
            'Pending Hearing' => ['text-amber-700 bg-amber-50 border-amber-200', 'fa-calendar'],
            'Mediation' => ['text-purple-700 bg-purple-50 border-purple-200', 'fa-handshake'],
            'Resolution' => ['text-blue-700 bg-blue-50 border-blue-200', 'fa-scale-balanced'],
            'Arbitration' => ['text-indigo-700 bg-indigo-50 border-indigo-200', 'fa-gavel'],
            'Resolved' => ['text-green-700 bg-green-50 border-green-200', 'fa-check-circle'],
            'Closed' => ['text-gray-700 bg-gray-50 border-gray-200', 'fa-folder'],
        ];
        $class = $badge[$status][0] ?? 'text-gray-700 bg-gray-50 border-gray-200';
        $icon = $badge[$status][1] ?? 'fa-info-circle';
        ?>
        <span class="px-2.5 py-1 rounded-full text-[11px] border font-semibold <?= $class ?>">
            <i class="fas <?= $icon ?> mr-1"></i><?= $status ?>
        </span>
    </td>
    <td class="p-3 text-center">
        <?php $__status = $case['Case_Status'] ?? ''; $isHistory = ($__status === 'Resolved' || $__status === 'Closed'); ?>
        <div class="flex justify-center gap-1.5">
            <!-- Always allow viewing case details -->
            <a href="view_case_details.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center px-3 h-8 rounded-lg text-blue-600 hover:text-white hover:bg-blue-600 transition text-xs font-medium" title="View Case Details">
                <i class="fas fa-eye mr-1"></i> 
            </a>
            <?php if (!$isHistory): ?>
                <!-- For active cases, provide Update action to go to Update Case Status page -->
                <a href="update_case_status.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center px-3 h-8 rounded-lg text-amber-600 hover:text-white hover:bg-amber-500 transition text-xs font-medium" title="Update Case Status">
                    <i class="fas fa-edit mr-1"></i>
                </a>
                <a href="appoint_hearing.php?id=<?= urlencode($case['Case_ID']) ?>" class="inline-flex items-center justify-center px-3 h-8 rounded-lg text-emerald-600 hover:text-white hover:bg-emerald-500 transition text-xs font-medium" title="Update Case Status">
                    <i class="fas fa-calendar mr-1"></i>
                </a>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php
    endwhile;
else:
    echo '<tr><td colspan="7" class="p-6 text-center text-gray-500 text-sm">No cases found.</td></tr>';
endif;
?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
                    <div id="rangeDisplay">Showing 0 entries</div>
                    <div class="flex mt-4 md:mt-0">
                        <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50">
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </a>
                        <a href="#" class="mx-1 px-4 py-2 bg-primary-500 text-white rounded-lg transition">1</a>
                        <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    </div>
                    
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
                    const statusText = cells[5]?.innerText.toLowerCase();
                    const dateFiled = cells[4]?.innerText; // expect YYYY-MM-DD or similar

                    let matchesMonth = true;
                    let matchesYear = true;
                    if (dateFiled) {
                        const date = new Date(dateFiled);
                        if (selectedMonth) matchesMonth = (date.getMonth() + 1) == parseInt(selectedMonth);
                        if (selectedYear) matchesYear = date.getFullYear() == parseInt(selectedYear);
                    }

                    const matchesSearch = rowText.includes(searchQuery);

                    // Map status text for logic
                    const isResolved = statusText?.includes('resolved');
                    const isClosed = statusText?.includes('closed');
                    const isResolution = statusText?.includes('resolution');
                    const isArbitration = statusText?.includes('arbitration');

                    let matchesStatus = true;
                    if (statusQuery) {
                        if (statusQuery === 'history') {
                            matchesStatus = (isResolved || isClosed);
                        } else if (statusQuery === 'all') {
                            // All: show Resolution, Arbitration and non-historic by default here
                            matchesStatus = (isResolution || isArbitration || (!isResolved && !isClosed));
                        } else if (statusQuery === 'resolution') {
                            matchesStatus = isResolution;
                        } else if (statusQuery === 'arbitration') {
                            matchesStatus = isArbitration;
                        } else {
                            matchesStatus = true;
                        }
                    } else {
                        // Default view: show Resolution and Arbitration (Lupon Head relevant)
                        matchesStatus = (isResolution || isArbitration);
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
                    // remove chip color classes while keeping border styles intact
                    chips.forEach(c => c.classList.remove('bg-primary-50','bg-amber-50','bg-purple-50','bg-green-50','bg-gray-50','bg-blue-50','bg-indigo-50'));
                    chipStatusOverride = chip.dataset.status || '';
                    // Re-style active chip
                    chip.classList.add('active','bg-primary-600','text-white','shadow');
                    filterTable();
                });
            });
            filterTable(); // initial count
            
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
