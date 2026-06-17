<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Meeting Logs</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',
                            400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'
                        }
                    },
                    animation:{'float':'float 3s ease-in-out infinite'},
                    keyframes:{ float:{'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-10px)'}} }
                }
            }
        };
    </script>
    <style>
        .gradient-bg { background: linear-gradient(to right,#f0f7ff,#e0effe); }
        /* Compact mobile adjustments for filters, hero and inputs */
        @media (max-width: 640px) {
            /* filter card spacing */
            .relative.bg-white\/90.p-6 { padding: .6rem !important; }
            .relative.bg-white\/90.p-6 .space-y-5 { gap: .5rem !important; }
            .c-chip { padding: .28rem .5rem !important; font-size: .68rem !important; }
            #searchInput, #month, #day, #year { padding-top: .45rem !important; padding-bottom: .45rem !important; }
            #searchInput { font-size: .9rem !important; }
            .view-type-btn { padding: .3rem .5rem !important; font-size: .75rem !important; }
            .grid.md\:grid-cols-12 { gap: .5rem !important; }
            .bg-white.rounded-2xl.p-6, .bg-white.rounded-2xl.p-8 { padding: .6rem !important; }

            /* Hero header smaller */
            .relative.gradient-bg.p-8 { padding: .8rem !important; }
            .relative.gradient-bg h1 { font-size: 1.125rem !important; line-height: 1.2 !important; }
            .relative.gradient-bg p { font-size: .85rem !important; }
            .relative.gradient-bg .mt-5 span { font-size: .65rem !important; padding: .2rem .45rem !important; }

            /* Table and card compactness */
            .bg-white.rounded-2xl.p-6, .bg-white.rounded-2xl.p-8, .bg-white.rounded-2xl.p-7 { padding: .6rem !important; }
            .overflow-x-auto table th, .overflow-x-auto table td { padding: .45rem .5rem !important; font-size: .78rem !important; }
            #visibleCount { font-size: .7rem !important; padding: .2rem .5rem !important; }

            /* Buttons and reset */
            #resetFilters, .inline-flex.items-center.justify-center { padding: .35rem .5rem !important; font-size: .85rem !important; }

            /* Details and hero icons */
            .inline-flex.w-12.h-12 { width: 2.25rem !important; height: 2.25rem !important; }

            /* Compact the month/day/year/reset controls into a single non-scrolling row */
            /* Target the filter row we added (id=filterRow) and allow children to flex/shrink */
            #filterRow { overflow-x: visible !important; }
            #filterRow > .relative { flex: 1 1 0 !important; min-width: 0 !important; width: auto !important; }
            #filterRow .w-28, #filterRow .w-20, #filterRow .w-24 { width: auto !important; flex: 1 1 0 !important; min-width: 0 !important; }
            #filterRow select { min-width: 0 !important; }
            /* Make reset button compact and keep it visible (icon only on small screens) */
            #resetFilters { flex: 0 0 auto !important; min-width: 2.2rem !important; padding-left: .5rem !important; padding-right: .5rem !important; }
            /* Reduce caret icon spacing inside the selects */
            #filterRow i.fa-caret-down { right: .6rem !important; }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <!-- Optional: Consider using FA CSS for more reliable icon rendering -->
</head>
<body class="font-sans relative overflow-x-hidden bg-gray-50">
<?php include '../includes/barangay_official_sec_nav.php'; ?>

<!-- Global Blue Blush Background Orbs -->
<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>

<!-- Hero Header -->
<div class="w-full mt-8 px-4">
    <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden relative max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                    <div class="max-w-2xl">
                            <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">Scheduled Hearings</span></h1>
                            <p class="mt-4 text-gray-600 leading-relaxed">Record, filter, and review barangay scheduled hearings. Use the smart filters below to quickly narrow results.</p>
                            <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Smart Filters</span>
                                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-calendar-days text-primary-500"></i> By Month/Year</span>
                                    <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clipboard-list text-primary-500"></i> Status Overview</span>
                            </div>
                    </div>
            </div>
    </div>
</div>

<!-- Filters & Table Container -->
<div class="w-full mt-8 px-4">
    <div class="max-w-screen-2xl mx-auto space-y-6">
            <!-- Filter / Search Card -->
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                    <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                            <div class="flex items-center gap-2 text-primary-700/80 text-sm font-medium">
                                <span class="inline-flex items-center gap-2 px-2 py-1 rounded-lg bg-primary-50/70 border border-primary-100 text-[13px]"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                                <span class="hidden sm:inline-flex items-center gap-2 px-2 py-1 rounded-lg bg-primary-50/70 border border-primary-100 text-[13px]"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                            </div>
                        </div>
                            <!-- Status Chips -->
                <div class="flex flex-wrap gap-2 pt-1" id="statusChips">
                    <button type="button" data-status="All" class="c-chip active px-2 py-1 text-[12px] font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                    <button type="button" data-status="Open" class="c-chip px-2 py-1 text-[12px] font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Open</button>
                    <button type="button" data-status="Mediation" class="c-chip px-2 py-1 text-[12px] font-medium rounded-full bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition">Mediation</button>
                    <button type="button" data-status="Resolution" class="c-chip px-2 py-1 text-[12px] font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Resolution</button>
                    <button type="button" data-status="Settlement" class="c-chip px-2 py-1 text-[12px] font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Settlement</button>
                </div>
                            <!-- View Type Filter: Pending vs History -->
                            <div class="flex items-center gap-2 pt-2 pb-1">
                                <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">View:</span>
                                <div class="inline-flex rounded-lg border border-primary-200 bg-primary-50/30 p-0.5">
                                    <button type="button" id="viewPending" class="view-type-btn active px-3 py-1 text-[13px] font-semibold rounded-md transition bg-primary-600 text-white shadow-sm">
                                        <i class="fa-solid fa-hourglass-half mr-1"></i> Pending Logs
                                    </button>
                                    <button type="button" id="viewHistory" class="view-type-btn px-3 py-1 text-[13px] font-semibold rounded-md transition text-primary-700 hover:bg-primary-100/50">
                                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> History
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                    <div class="md:col-span-7">
                                        <div class="relative group">
                                            <input id="searchInput" type="text" placeholder="Search by Case ID, title, date, or status..." class="w-full pl-10 pr-3 py-2 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                                        </div>
                                    </div>
                                    <div class="md:col-span-5">
                                        <div id="filterRow" class="flex items-center gap-2 mt-2 md:mt-0 flex-nowrap overflow-x-auto">
                                            <div class="relative flex-shrink-0 w-28">
                                                <select id="month" class="w-full pl-3 pr-8 py-2 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                                    <option value="All">All Months</option>
                                                    <?php for ($m = 1; $m <= 12; $m++): $monthName = date("F", mktime(0, 0, 0, $m, 1)); ?>
                                                        <option value="<?= $m ?>"><?= $monthName ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                            </div>
                                            <div class="relative flex-shrink-0 w-20">
                                                <select id="day" class="w-full pl-3 pr-8 py-2 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                                    <option value="All">All Days</option>
                                                    <!-- JS will populate day options based on month/year -->
                                                </select>
                                                <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                            </div>
                                            <div class="relative flex-shrink-0 w-24">
                                                <select id="year" class="w-full pl-3 pr-8 py-2 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                                    <option value="All">All Years</option>
                                                    <?php for ($y = $currentYear; $y >= 2000; $y--): ?>
                                                        <option value="<?= $y ?>"><?= $y ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                                            </div>
                                            <div class="flex-shrink-0 w-20">
                                                <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                            <!-- Hidden native select to preserve backend param for status -->
                            <div class="hidden">
                                <select id="status">
                                        <option value="All">All</option>
                                        <option value="Open">Open</option>
                                        <option value="Mediation">Mediation</option>
                                        <option value="Resolution">Resolution</option>
                                        <option value="Settlement">Settlement</option>
                                </select>
                            </div>
                    </div>
            </div>

            <!-- Meetings Table Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8 overflow-hidden">
                    <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-table-list text-primary-500"></i> Hearings Scheduled for Meeting Log</h2>
                            <span id="visibleCount" class="text-xs px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 font-medium border border-primary-100">0 Showing</span>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                            <table class="w-full mt-0">
                                    <thead class="bg-primary-50/60">
                                            <tr>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">case_original_id</th>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Case Type</th>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Date Filed</th>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Hearing Date</th>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Hearing Time</th>
                                                    <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Status</th>
                                                    <th class="p-3 text-center text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Action</th>
                                            </tr>
                                    </thead>
                                    <tbody id="tableBody">
                                            <tr>
                                                    <td colspan="7" class="text-center px-4 py-4 text-gray-500">Loading...</td>
                                            </tr>
                                    </tbody>
                            </table>
                    </div>
            </div>
    </div>
</div>

<?php include 'sidebar_.php';?>

<script>
(function(){
    const statusSelect = document.getElementById('status');
    const monthSelect = document.getElementById('month');
    const daySelect = document.getElementById('day');
    const yearSelect = document.getElementById('year');
    const tableBody = document.getElementById('tableBody');
    const visibleCount = document.getElementById('visibleCount');
    const searchInput = document.getElementById('searchInput');
    const resetBtn = document.getElementById('resetFilters');
    const chips = document.querySelectorAll('#statusChips .c-chip');
    const viewPendingBtn = document.getElementById('viewPending');
    const viewHistoryBtn = document.getElementById('viewHistory');
    
    let currentView = 'pending'; // 'pending' or 'history'

    function updateVisibleCount(){
        const rows = Array.from(tableBody.querySelectorAll('tr'));
        // Count only rows that are visible and not placeholders
        let count = 0;
        rows.forEach(r=>{
            const isHidden = r.style.display === 'none';
                const isPlaceholder = !!r.querySelector('[data-placeholder="true"]');
            if(!isHidden && !isPlaceholder){ count++; }
        });
        visibleCount.textContent = count + ' Showing';
    }

    function clientSearchFilter(){
        const q = (searchInput.value || '').toLowerCase().trim();
        const rows = Array.from(tableBody.querySelectorAll('tr'));
        rows.forEach(r=>{
            // Skip if placeholder row
            if(r.querySelector('[data-placeholder]')){ return; }
            const text = r.textContent.toLowerCase();
            r.style.display = q ? (text.includes(q) ? '' : 'none') : '';
        });
        updateVisibleCount();
    }

    function setActiveChipByValue(val){
        chips.forEach(c=>{
            c.classList.remove('active','bg-primary-600','text-white','shadow');
            if((c.getAttribute('data-status')||'') === (val||'')){
                c.classList.add('active','bg-primary-600','text-white','shadow');
            }
        });
    }

    function setActiveViewBtn(view){
        const btns = document.querySelectorAll('.view-type-btn');
        btns.forEach(btn => {
            btn.classList.remove('active','bg-primary-600','text-white','shadow-sm');
            btn.classList.add('text-primary-700','hover:bg-primary-100/50');
        });
        
        if(view === 'pending'){
            viewPendingBtn.classList.add('active','bg-primary-600','text-white','shadow-sm');
            viewPendingBtn.classList.remove('text-primary-700','hover:bg-primary-100/50');
        } else {
            viewHistoryBtn.classList.add('active','bg-primary-600','text-white','shadow-sm');
            viewHistoryBtn.classList.remove('text-primary-700','hover:bg-primary-100/50');
        }
    }

    function populateDays(month, year){
        // month: 1-12 or 'All', year: 4-digit or 'All'
        daySelect.innerHTML = '<option value="All">All Days</option>';
        if(month === 'All'){
            return; // no month selected -> keep All Days only
        }
        const m = parseInt(month, 10);
        // if year is 'All' use current year so we can compute correct days for the month
        const y = (year === 'All') ? new Date().getFullYear() : parseInt(year, 10);
        // get days in month (account for leap years)
        const daysInMonth = new Date(y, m, 0).getDate();
        for(let d=1; d<=daysInMonth; d++){
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            daySelect.appendChild(opt);
        }
    }

    function loadTable(){
        const status = statusSelect.value;
        const month = monthSelect.value;
        const day = daySelect.value;
        const year = yearSelect.value;
        
        // Determine which endpoint to use based on current view
        const endpoint = currentView === 'pending' ? 'fetch_meeting_logs.php' : 'fetch_meeting_logs_history.php';
        
        // Show loading
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center px-4 py-4 text-gray-500">Loading...</td></tr>';
    fetch(`${endpoint}?status=${encodeURIComponent(status)}&month=${encodeURIComponent(month)}&day=${encodeURIComponent(day)}&year=${encodeURIComponent(year)}`)
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
                // Add marker to known placeholders if backend sends a "no records" row with specific class or content
                Array.from(tableBody.querySelectorAll('tr')).forEach(tr=>{
                    const td = tr.querySelector('td');
                    if(td && td.getAttribute('colspan') && td.textContent.toLowerCase().includes('no')){
                        td.setAttribute('data-placeholder','true');
                    }
                });
                clientSearchFilter(); // apply current search on new data
            })
            .catch(()=>{
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center px-4 py-4 text-rose-500">Failed to load records.</td></tr>';
                updateVisibleCount();
            });
    }

    // View type toggle
    viewPendingBtn.addEventListener('click', function(){
        currentView = 'pending';
        setActiveViewBtn('pending');
        loadTable();
    });

    viewHistoryBtn.addEventListener('click', function(){
        currentView = 'history';
        setActiveViewBtn('history');
        loadTable();
    });

    // Initial population of days (if month/year pre-selected) then initial load
    populateDays(monthSelect.value, yearSelect.value);
    loadTable();

    // Chips behavior -> update hidden select and reload
    chips.forEach(chip=>{
        chip.addEventListener('click', function(){
            const val = this.getAttribute('data-status') || 'All';
            statusSelect.value = val;
            setActiveChipByValue(val);
            loadTable();
        });
    });

    // Select changes
    monthSelect.addEventListener('change', function(){
        populateDays(monthSelect.value, yearSelect.value);
        loadTable();
    });
    yearSelect.addEventListener('change', function(){
        populateDays(monthSelect.value, yearSelect.value);
        loadTable();
    });
    daySelect.addEventListener('change', loadTable);

    // Keep chips in sync if statusSelect is ever changed programmatically
    statusSelect.addEventListener('change', function(){
        setActiveChipByValue(this.value);
        loadTable();
    });

    // Search input
    searchInput.addEventListener('input', clientSearchFilter);

    // Reset
    resetBtn.addEventListener('click', function(){
        searchInput.value = '';
        statusSelect.value = 'All';
        monthSelect.value = 'All';
        daySelect.value = 'All';
        yearSelect.value = 'All';
        setActiveChipByValue('All');
        loadTable();
    });
})();
</script>

</body>
</html>
