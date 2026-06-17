<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

$blotters = [];
$result = $conn->query("
    SELECT b.Blotter_ID, b.Blotter_Description, b.Reported_By, b.Date_Reported,
           r.First_Name, r.Middle_Name, r.Last_Name
    FROM blotter_info b
    LEFT JOIN resident_info r ON b.Reported_By = r.Resident_ID
    ORDER BY b.Blotter_ID DESC
");

// Also include complaints that were converted as 'Record Purposes' so they appear in this list
$rec = $conn->query("SELECT c.Complaint_ID AS Blotter_ID, c.Complaint_Details AS Blotter_Description, c.Resident_ID AS Reported_By, c.Date_Filed AS Date_Reported, r.First_Name, r.Middle_Name, r.Last_Name FROM COMPLAINT_INFO c LEFT JOIN RESIDENT_INFO r ON c.Resident_ID = r.Resident_ID WHERE UPPER(TRIM(c.Status)) = 'RECORD PURPOSES' ORDER BY c.Complaint_ID DESC");
if ($rec && $rec->num_rows > 0) {
    while ($r = $rec->fetch_assoc()) {
        // Mark source to distinguish complaint-based entries
        $r['is_record_purposes'] = 1;
        $blotters[] = $r;
    }
}
while ($row = $result->fetch_assoc()) {
    $blotters[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Blotter Reports</title>
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
            
            /* Add Blotter button - compact */
            a[href="add_blotter.php"] {
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
                table-layout: auto !important;
            }
            
            /* Remove fixed table layout on mobile */
            table.table-fixed {
                table-layout: auto !important;
            }
            
            /* Adjust column widths for mobile */
            table colgroup {
                display: none !important;
            }
            
            table th {
                font-size: 9px !important;
                padding: 0.5rem 0.375rem !important;
                white-space: nowrap !important;
            }
            
            table td {
                font-size: 0.65rem !important;
                padding: 0.5rem 0.375rem !important;
            }
            
            /* Specific column widths for mobile */
            table th:nth-child(1),
            table td:nth-child(1) {
                width: 50px !important;
                min-width: 50px !important;
            }
            
            table th:nth-child(2),
            table td:nth-child(2) {
                min-width: 120px !important;
                max-width: 150px !important;
            }
            
            table th:nth-child(3),
            table td:nth-child(3) {
                min-width: 90px !important;
                max-width: 110px !important;
            }
            
            table th:nth-child(4),
            table td:nth-child(4) {
                min-width: 75px !important;
                max-width: 85px !important;
            }
            
            table th:nth-child(5),
            table td:nth-child(5) {
                width: 45px !important;
                min-width: 45px !important;
            }
            
            /* Text overflow handling */
            table td:nth-child(2),
            table td:nth-child(3) {
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
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
            
            /* Pagination */
            .flex.mt-4 a {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.625rem !important;
            }
            
            /* Grid gaps */
            .grid.gap-4 {
                gap: 0.5rem !important;
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
<body class="bg-gray-50 font-sans">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
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
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">Record Purposes</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Browse and review record purposes. Use the filters below to quickly narrow the list.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Smart Filters</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-primary-500"></i> Recent Focus</span>
                    </div>
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
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                            
                        </div>
                        
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <!-- Search (Full width row) -->
                        <div class="relative group">
                            <input type="text" id="searchInput" placeholder="Search by ID, reporter, or keyword..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                        </div>
                        <!-- Month, Year, Reset (Compressed row) -->
                        <div class="grid grid-cols-[1fr_1fr_auto] gap-2 md:grid-cols-[2fr_1fr_1fr_auto] md:gap-4">
                            <!-- Empty spacer for desktop to maintain layout -->
                            <div class="hidden md:block"></div>
                            <!-- Month -->
                            <div class="relative">
                                <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                    <option value="">All Months</option>
                                    <?php for ($m = 1; $m <= 12; $m++): $monthName = date('F', mktime(0,0,0,$m,1)); ?>
                                        <option value="<?= $m ?>"><?= $monthName ?></option>
                                    <?php endfor; ?>
                                </select>
                                <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                            </div>
                            <!-- Year -->
                            <div class="relative">
                                <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                    <option value="">All Years</option>
                                    <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                            </div>
                            <!-- Reset -->
                            <div class="flex">
                                <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8 overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-table text-primary-500"></i> Blotter Records</h2>
                    <span id="visibleCount" class="text-xs px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 font-medium border border-primary-100">0 Showing</span>
                </div>
                <div class="overflow-x-auto rounded-lg border border-gray-100">
                    <table class="w-full mt-0 table-fixed">
                        <colgroup>
                            <col class="w-28">
                            <col>
                            <col class="w-64">
                            <col class="w-40">
                            <col class="w-28">
                        </colgroup>
                        <thead class="bg-primary-50/60">
                            <tr>
                                <th class="p-3 text-left text-[11px] md:text-xs font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Blotter ID</th>
                                <th class="p-3 text-left text-[11px] md:text-xs font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Blotter Details</th>
                                <th class="p-3 text-left text-[11px] md:text-xs font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Reported By</th>
                                <th class="p-3 text-left text-[11px] md:text-xs font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Date Reported</th>
                                <th class="p-3 text-center text-[11px] md:text-xs font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="blotterTable">
                        <?php if (count($blotters) > 0): ?>
                            <?php foreach ($blotters as $index => $b): ?>
                                <?php $isRP = !empty($b['is_record_purposes']); ?>
                                <tr data-href="<?= $isRP ? ('view_complaint_details_cap.php?id='.urlencode($b['Blotter_ID'])) : ('view_blotter_details.php?id='.urlencode($b['Blotter_ID'])) ?>" tabindex="0" class="border-b border-gray-100 hover:bg-primary-50/40 transition cursor-pointer">
                                    <td class="p-3 text-sm text-gray-700 font-mono text-center">#<?= htmlspecialchars($b['Blotter_ID']) ?></td>
                                    <td class="p-3 text-sm text-gray-700">
                                        <div class="font-semibold text-gray-800 mb-0.5 truncate" title="<?= htmlspecialchars($b['Blotter_Description']) ?>"><?= htmlspecialchars($b['Blotter_Description']) ?></div>
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        <?php
                                            if (!empty($b['First_Name'])) {
                                                echo htmlspecialchars(
                                                    $b['First_Name'] . ' ' .
                                                    (!empty($b['Middle_Name']) ? $b['Middle_Name'][0] . '. ' : '') .
                                                    $b['Last_Name']
                                                );
                                            } else {
                                                echo htmlspecialchars($b['Reported_By']);
                                            }
                                        ?>
                                    </td>
                                    <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($b['Date_Reported']) ?></td>
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <?php if ($isRP): ?>
                                                <a href="view_complaint_details_cap.php?id=<?= urlencode($b['Blotter_ID']) ?>" class="inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 transition" title="View Complaint">
                                                    <i class="fa-solid fa-eye"></i>
                                                    <span class="hidden md:inline">View</span>
                                                </a>
                                            <?php else: ?>
                                                <a href="view_blotter_details.php?id=<?= urlencode($b['Blotter_ID']) ?>" class="inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 transition" title="View Details">
                                                    <i class="fa-solid fa-eye"></i>
                                                    <span class="hidden md:inline">View</span>
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit_blotter.php?id=<?= urlencode($b['Blotter_ID']) ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-green-50 text-green-700 text-xs font-medium hover:bg-green-100 transition" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                                <span class="hidden md:inline">Edit</span>
                                            </a>
                                            <button onclick="confirmDelete(<?= $b['Blotter_ID'] ?>); event.stopPropagation();" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-50 text-red-700 text-xs font-medium hover:bg-red-100 transition" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                                <span class="hidden md:inline">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-500 text-sm">No blotter reports found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noResultsMsg" class="hidden text-center py-12 text-gray-400 text-sm">
                    <i class="fa-solid fa-inbox text-4xl mb-3 opacity-50"></i>
                    <p>No blotter records found matching your filters.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center px-4" style="background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 transform transition-all">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fa-solid fa-trash-can text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Blotter Record</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this blotter record? This action cannot be undone.</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
                    <button id="confirmDeleteBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Make rows clickable
    document.querySelectorAll('tr[data-href]').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on action buttons
            if (!e.target.closest('button') && !e.target.closest('a')) {
                window.location.href = this.dataset.href;
            }
        });
        
        row.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.target.closest('button') && !e.target.closest('a')) {
                window.location.href = this.dataset.href;
            }
        });
    });

    // Filter functionality
    const searchInput = document.getElementById('searchInput');
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    const resetBtn = document.getElementById('resetFilters');
    const rows = document.querySelectorAll('#blotterTable tr');
    const visibleCount = document.getElementById('visibleCount');
    const noResultsMsg = document.getElementById('noResultsMsg');
    const tableContainer = document.querySelector('.overflow-x-auto');

    function filterTable() {
        const searchQuery = (searchInput.value || '').toLowerCase();
        const selectedMonth = monthFilter.value;
        const selectedYear = yearFilter.value;
        let shown = 0;

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (!cells.length) return;
            
            // Skip the "No blotter reports found" row
            if (cells[0].getAttribute('colspan')) {
                row.style.display = 'none';
                return;
            }
            
            const rowText = row.innerText.toLowerCase();
            const dateText = cells[3]?.innerText.trim();

            let matchesMonth = true;
            let matchesYear = true;
            if (dateText) {
                const d = new Date(dateText);
                if (selectedMonth) matchesMonth = (d.getMonth() + 1) == parseInt(selectedMonth);
                if (selectedYear) matchesYear = d.getFullYear() == parseInt(selectedYear);
            }

            const matchesSearch = rowText.includes(searchQuery);
            const show = matchesSearch && matchesMonth && matchesYear;
            row.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        
        // Update count and show/hide no results message
        visibleCount.textContent = shown + ' Showing';
        
        if (shown === 0) {
            tableContainer.classList.add('hidden');
            noResultsMsg.classList.remove('hidden');
        } else {
            tableContainer.classList.remove('hidden');
            noResultsMsg.classList.add('hidden');
        }
    }

    function resetFilters(e) {
        e?.preventDefault?.();
        searchInput.value = '';
        monthFilter.value = '';
        yearFilter.value = '';
        filterTable();
    }

    searchInput.addEventListener('input', filterTable);
    monthFilter.addEventListener('change', filterTable);
    yearFilter.addEventListener('change', filterTable);
    resetBtn.addEventListener('click', resetFilters);

    // Delete modal functions
    let deleteBlotterId = null;

    function confirmDelete(id) {
        deleteBlotterId = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        deleteBlotterId = null;
        document.getElementById('deleteModal').classList.add('hidden');
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
        if (deleteBlotterId) {
            window.location.href = 'delete_blotter.php?id=' + deleteBlotterId;
        }
    });

    // Initialize count on page load
    filterTable();
    </script>

    <?php include '../chatbot/bpamis_case_assistant.php'?>
</body>
</html>
