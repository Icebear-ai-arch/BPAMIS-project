<?php
include '../controllers/session_control.php';
// DB Connection
include_once __DIR__ . '/../server/server.php';
$pageTitle = "View Complaints";
// Status counts (lightweight aggregation)
$complaintCounts = [];
$countsRes = $conn->query("SELECT Status, COUNT(*) total FROM COMPLAINT_INFO GROUP BY Status");
if($countsRes){ while($r = $countsRes->fetch_assoc()){ $complaintCounts[$r['Status']] = (int)$r['total']; } }
function cc($k,$arr){ return $arr[$k] ?? 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
        <script>
                tailwind.config = { theme: { extend: { colors: { primary: {50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'} }, animation:{'float':'float 3s ease-in-out infinite'}, keyframes:{ float:{'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-10px)'}} } } } };
        </script>
        <style>
                .gradient-bg { background: linear-gradient(to right,#f0f7ff,#e0effe); }
        </style>
</head>
<body class="font-sans relative overflow-x-hidden bg-gray-50">
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

<!-- Hero Header -->
<div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
    <div class="relative gradient-bg rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-6 md:p-8 lg:p-10 overflow-hidden relative max-w-screen-2xl mx-auto">
        <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6 md:gap-8">
            <div class="max-w-2xl">
                <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-light text-primary-900 tracking-tight">Manage <span class="font-semibold">Barangay Complaints</span></h1>
                <p class="mt-2 sm:mt-3 md:mt-4 text-xs sm:text-sm md:text-base text-gray-600 leading-relaxed">Browse, review details, and monitor resolution progress. Use the smart filters below to quickly narrow results.</p>
                <div class="mt-3 sm:mt-4 md:mt-5 flex flex-wrap gap-2 sm:gap-3 text-[10px] sm:text-xs text-primary-700/80 font-medium">
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> <span class="hidden sm:inline">Smart Filters</span></span>
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> <span class="hidden sm:inline">Status Insights</span></span>
                    <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-primary-500"></i> <span class="hidden sm:inline">Recent Focus</span></span>
                </div>
            </div>
            <div class="hidden md:flex flex-col gap-3 min-w-[250px]">
                <div class="grid grid-cols-3 gap-2">
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">Pending</span><span class="mt-1 text-lg font-semibold text-primary-700"><?= cc('Pending',$complaintCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">In Case</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= cc('IN CASE',$complaintCounts) ?></span></div>
                    <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-rose-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-rose-600 font-semibold">Rejected</span><span class="mt-1 text-lg font-semibold text-rose-700"><?= cc('Rejected',$complaintCounts) ?></span></div>
                </div>
                <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Table Container -->
<div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
    <div class="max-w-screen-2xl mx-auto space-y-4 sm:space-y-6">
        <!-- Filter / Search Card -->
        <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-5 md:p-6 lg:p-7 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
            <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
            <div class="relative z-10 space-y-3 sm:space-y-4 md:space-y-5">
                <!-- Row 1: Search & Filter label + Add Complaint button -->
                <div class="flex items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center gap-2 sm:gap-3 text-primary-700/80 text-xs sm:text-sm font-medium">
                        <span class="inline-flex items-center gap-1.5 sm:gap-2 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500 text-xs sm:text-sm"></i> <span class="sm:inline">Search & Filter</span></span>
                        <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                    </div>
                    <!-- <a href="add_complaints.php" class="group relative inline-flex items-center gap-1.5 sm:gap-2 px-3 py-2 sm:px-5 sm:py-2.5 rounded-lg sm:rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                        <i class="fa-solid fa-circle-plus text-white"></i>
                        <span>Add Complaint</span>
                        <span class="absolute inset-0 rounded-lg sm:rounded-xl ring-1 ring-inset ring-white/20"></span>
                    </a> -->
                </div>
                
                <!-- Status Chips -->
                <div class="flex flex-wrap gap-1.5 sm:gap-2" id="statusChips">
                    <button type="button" data-status="" class="c-chip active px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                    <button type="button" data-status="Pending" class="c-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Pending</button>
                    <button type="button" data-status="IN CASE" class="c-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">In Case</button>
                    <button type="button" data-status="Record Purposes" class="c-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-blue-100 text-blue-600 border border-blue-100 hover:bg-blue-100 transition">Record Purposes</button>
                    <button type="button" data-status="Rejected" class="c-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-100 transition">Rejected</button>
                    <button type="button" data-status="Archives" class="c-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-gray-50 text-gray-600 border border-gray-100 hover:bg-gray-100 transition">Archives</button>
                </div>
                
                <!-- Row 2: Search input (full width on mobile) -->
                <div class="relative group">
                    <input id="searchInput" type="text" placeholder="Search by ID, complainant, respondent, description..." class="w-full pl-9 sm:pl-11 pr-3 sm:pr-4 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-xs sm:text-sm transition" />
                    <i class="fa-solid fa-search absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition text-xs sm:text-sm"></i>
                </div>
                
                <!-- Row 3: Month, Year, Reset (single row on mobile) -->
                <div class="grid grid-cols-[1fr_1fr_auto] gap-2 sm:gap-3">
                    <!-- Month -->
                    <div class="relative">
                        <select id="monthFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Months</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                    </div>
                    <!-- Year -->
                    <div class="relative" id="yearFilterWrapper">
                        <select id="yearFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Years</option>
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
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

        <!-- Complaints Table Card -->
        <div class="bg-white rounded-xl sm:rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 md:p-6 lg:p-8 overflow-hidden">
            <div class="flex items-center justify-between mb-3 sm:mb-4">
                <h2 class="text-base sm:text-lg font-semibold text-gray-800 flex items-center gap-1.5 sm:gap-2"><i class="fa-solid fa-table-list text-primary-500 text-sm sm:text-base"></i> <span class="text-sm sm:text-lg">Complaint Records</span></h2>
                <span id="visibleCount" class="text-[10px] sm:text-xs px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full bg-primary-50 text-primary-600 font-medium border border-primary-100">0 Showing</span>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="w-full mt-0">
                    <thead class="bg-primary-50/60">
                        <tr>
                            <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Complaint ID</th>
                            <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Complainant</th>
                            <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Respondent</th>
                            <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100 w-56">Description</th>
                            <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Date Filed</th>
                                <th class="p-3 text-left text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100 md:w-40">Status</th>
                            <th class="p-3 text-center text-[11px] font-semibold text-primary-700 uppercase tracking-wider border-b border-primary-100">Action</th>
                        </tr>
                    </thead>
                    <tbody id="complaintsTableBody">
                    <?php
                    $sql = "SELECT c.Complaint_ID,c.Complaint_Title,c.Complaint_Details,c.Date_Filed,c.Status,c.Resident_ID,c.External_Complainant_ID, b.Blotter_ID AS Blotter_ID, r.First_Name AS Resident_First_Name,r.Last_Name AS Resident_Last_Name,e.First_Name AS External_First_Name,e.Last_Name AS External_Last_Name FROM COMPLAINT_INFO c LEFT JOIN RESIDENT_INFO r ON c.Resident_ID=r.Resident_ID LEFT JOIN external_complainant e ON c.External_Complainant_ID=e.External_Complaint_ID LEFT JOIN blotter_info b ON b.Complaint_ID = c.Complaint_ID ORDER BY c.Date_Filed DESC,c.Complaint_ID DESC";
                    $result=$conn->query($sql); $total=$result? $result->num_rows:0;
                    if($total>0): while($complaint=$result->fetch_assoc()):
                            if(!empty($complaint['Resident_ID'])){ $complainantName=$complaint['Resident_First_Name'].' '.$complaint['Resident_Last_Name']; }
                            elseif(!empty($complaint['External_Complainant_ID'])){ $complainantName=$complaint['External_First_Name'].' '.$complaint['External_Last_Name']; }
                            else { $complainantName='Unknown'; }
                            $complaint_id=(int)$complaint['Complaint_ID'];
                            // Display-only complaint reference
                            $complaint_ref = 'COMP#' . str_pad($complaint_id, 2, '0', STR_PAD_LEFT);
                            $respondent_names=[];
                            $mainResQuery=$conn->query("SELECT First_Name,Last_Name FROM RESIDENT_INFO WHERE Resident_ID=(SELECT Respondent_ID FROM COMPLAINT_INFO WHERE Complaint_ID=$complaint_id)");
                            if($mainResQuery&&$mainResQuery->num_rows>0){ $row=$mainResQuery->fetch_assoc(); $respondent_names[]=$row['First_Name'].' '.$row['Last_Name']; }
                            $additionalResQuery=$conn->query("SELECT r.First_Name,r.Last_Name FROM COMPLAINT_RESPONDENTS cr JOIN RESIDENT_INFO r ON cr.Respondent_ID=r.Resident_ID WHERE cr.Complaint_ID=$complaint_id");
                            if($additionalResQuery&&$additionalResQuery->num_rows>0){ while($row=$additionalResQuery->fetch_assoc()){ $respondent_names[]=$row['First_Name'].' '.$row['Last_Name']; } }
                            $respondents_str=count($respondent_names)? implode(', ',$respondent_names):'N/A';
                            $fullDesc=$complaint['Complaint_Details']??''; $cleanDesc=htmlspecialchars($fullDesc); $shortDesc=mb_strlen($fullDesc)>120? htmlspecialchars(mb_substr($fullDesc,0,117)).'…':$cleanDesc; $status=$complaint['Status'];
                            // Default display status is the complaint status. If it's IN CASE, try to find the linked Case_Status.
                            $displayStatus = $status;
                            if (is_string($status) && strcasecmp(trim($status), 'IN CASE') === 0) {
                                $cid = (int)$complaint['Complaint_ID'];
                                $caseStatus = null;
                                $csRes = $conn->query("SELECT Case_Status FROM CASE_INFO WHERE Complaint_ID = $cid LIMIT 1");
                                if ($csRes && $csRes->num_rows > 0) {
                                    $r = $csRes->fetch_assoc();
                                    if (!empty($r['Case_Status'])) {
                                        $caseStatus = $r['Case_Status'];
                                    }
                                }
                                if ($caseStatus) {
                                    $displayStatus = 'In Case - ' . $caseStatus;
                                }
                            }

                            // Normalize some common status casings for styling (case-insensitive checks)
                            $stLower = is_string($status) ? strtolower(trim($status)) : '';
                            switch(true){
                                case $stLower === 'pending':
                                    $sClass='text-amber-700 bg-amber-50 border border-amber-200'; $sIcon='fa-clock'; break;
                                case $stLower === 'scheduled for hearing':
                                    $sClass='text-blue-700 bg-blue-50 border border-blue-200'; $sIcon='fa-calendar-check'; break;
                                case $stLower === 'resolved' || strpos($stLower,'resolved') !== false:
                                    $sClass='text-green-700 bg-green-50 border border-green-200'; $sIcon='fa-check-circle'; break;
                                case $stLower === 'rejected':
                                    $sClass='text-rose-700 bg-rose-50 border border-rose-200'; $sIcon='fa-ban'; break;
                                case $stLower === 'record purposes' || strpos($stLower,'record purposes') !== false:
                                    // Make Record Purposes blue to match the filter chip
                                    $sClass='text-blue-700 bg-blue-50 border border-blue-200'; $sIcon='fa-file-circle-check'; break;
                                default:
                                    $sClass='text-gray-700 bg-gray-50 border border-gray-200'; $sIcon='fa-info-circle';
                            }
                    ?>
                        <?php
                            // Determine row target: view for IN CASE or Record Purposes, edit otherwise
                            $rowHref = 'view_complaint_details_cap.php?id=' . $complaint['Complaint_ID'];
                            $stLower = is_string($status) ? strtolower(trim($status)) : '';
                            $isViewOnly = ($stLower === 'in case' || strpos($stLower, 'in case') !== false) || ($stLower === 'record purposes' || strpos($stLower, 'record purposes') !== false);
                            if (!$isViewOnly) {
                                $rowHref = 'view_complaint_details_cap.php?id=' . $complaint['Complaint_ID'] . '&edit=true';
                            }
                        ?>
                        <tr class="border-b border-gray-100 hover:bg-primary-50/40 transition table-row" data-datefiled="<?= $complaint['Date_Filed'] ?>" data-href="<?= $rowHref ?>">
                            <td class="p-3 text-sm text-gray-700 font-mono text-[11px] tracking-wide"><?= htmlspecialchars($complaint_ref) ?></td>
                            <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($complainantName) ?></td>
                            <td class="p-3 text-sm text-gray-700"><?= htmlspecialchars($respondents_str) ?></td>
                            <td class="p-3 text-sm text-gray-700 align-top"><span class="block max-w-[13rem] leading-snug" title="<?= $cleanDesc ?>" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"><?= $shortDesc ?></span></td>
                            <td class="p-3 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($complaint['Date_Filed']) ?></td>
                                <td class="p-3 text-sm text-gray-700 md:w-40"><span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $sClass ?> truncate max-w-[9rem]"><i class="fas <?= $sIcon ?> mr-1"></i><?= htmlspecialchars($displayStatus) ?></span></td>
                            <td class="p-3 text-center">
                                <div class="flex justify-center gap-1.5">
                                    <!-- Always show view action only -->
                                    <a href="view_complaint_details_cap.php?id=<?= $complaint['Complaint_ID'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-primary-600 hover:text-white hover:bg-primary-600 transition text-sm" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="p-6 text-center text-gray-500 text-sm">No complaints found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
                <div id="rangeDisplay">Showing 0 entries</div>
                <!-- <div class="flex mt-4 md:mt-0">
                    <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50"><i class="fas fa-chevron-left mr-1"></i> Previous</a>
                    <a href="#" class="mx-1 px-4 py-2 bg-primary-500 text-white rounded-lg transition">1</a>
                    <a href="#" class="mx-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 transition disabled:opacity-50">Next <i class="fas fa-chevron-right ml-1"></i></a>
                </div> -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const searchInput=document.getElementById('searchInput');
    const monthFilter=document.getElementById('monthFilter');
    const yearFilter=document.getElementById('yearFilter');
    const resetBtn=document.getElementById('resetFilters');
    const rows=document.querySelectorAll('#complaintsTableBody tr.table-row');
    const visibleCount=document.getElementById('visibleCount');
    const rangeDisplay=document.getElementById('rangeDisplay');
    const chips=document.querySelectorAll('.c-chip');
    const yearWrapper=document.getElementById('yearFilterWrapper');
    let statusOverride='';
    
    function filterAll(){
        const q=(searchInput.value||'').toLowerCase(); 
        const m=monthFilter.value; 
        const y=yearFilter.value; 
        let shown=0;
        const nowYear = new Date().getFullYear();
        
        rows.forEach(r=>{
            const id=r.querySelector('td:nth-child(1)')?.textContent.toLowerCase()||'';
            const complainant=r.querySelector('td:nth-child(2)')?.textContent.toLowerCase()||'';
            const respondents=r.querySelector('td:nth-child(3)')?.textContent.toLowerCase()||'';
            const desc=r.querySelector('td:nth-child(4) span')?.title.toLowerCase()||'';
            const dateText=r.dataset.datefiled||'';
            const statusText=r.querySelector('td:nth-child(6) span')?.textContent.toLowerCase()||'';
            let show=true;
            
            // Search filter
            if(q){ 
                show=id.includes(q)||complainant.includes(q)||respondents.includes(q)||desc.includes(q); 
            }
            
            // Status filtering
            const so = (statusOverride||'').toLowerCase();
            if(show && statusOverride){
                if(so==='in case'){
                    // Match "in case" or anything starting with "in case -" (e.g., "in case - open")
                    show = statusText.startsWith('in case');
                } else if(so==='record purposes'){
                    show = statusText.includes('record purposes');
                } else if(so==='archives'){
                    // Archives: show records from previous years
                    if(!dateText){ 
                        show = false; 
                    } else { 
                        const parts = dateText.split('-'); 
                        const Y = parseInt(parts[0]||0,10); 
                        if(y) { 
                            // If year filter is set, show only that year
                            show = (Y>0 && Y === parseInt(y,10)); 
                        } else { 
                            // Otherwise show all years before current year
                            show = (Y>0 && Y < nowYear); 
                        } 
                    }
                } else {
                    // For Pending, Rejected, etc.
                    show = statusText.includes(so);
                }
            }

            // Date filters (month and year)
            if(show && dateText){
                const parts=dateText.split('-'); 
                const Y=parseInt(parts[0]||0,10); 
                const M=parseInt(parts[1]||0,10);
                
                if(so==='archives'){
                    // For archives, month filter works with the year logic above
                    if(m) show = show && M === parseInt(m,10);
                } else {
                    // For non-archive filters, apply year/month filters normally
                    // Don't restrict to current year only - show all years unless year filter is set
                    if(y) show = show && Y === parseInt(y,10);
                    if(m) show = show && M === parseInt(m,10);
                }
            } else if(show && !dateText) {
                // No date -> exclude from listing (can't filter by date)
                show = false;
            }
            
            r.style.display=show?'':'none'; 
            if(show) shown++;
        });
        
        visibleCount.textContent=shown+' Showing'; 
        if(rangeDisplay) rangeDisplay.textContent='Showing '+shown+' entr'+(shown===1?'y':'ies');
    }
    
    function resetFilters(){ 
        searchInput.value=''; 
        monthFilter.value=''; 
        yearFilter.value=''; 
        statusOverride=''; 
        chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow')); 
        chips[0].classList.add('active','bg-primary-600','text-white','shadow'); 
        if(yearWrapper) yearWrapper.classList.remove('hidden');
        filterAll(); 
    }
    
    chips.forEach(chip=>{ 
        chip.addEventListener('click',()=>{
            chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow'));
            chip.classList.add('active','bg-primary-600','text-white','shadow');
            statusOverride=chip.dataset.status||'';
            
            // Show year selector only for Archives
            if(yearWrapper) {
                if((statusOverride||'').toLowerCase()==='archives'){
                    yearWrapper.classList.remove('hidden');
                } else {
                    yearWrapper.classList.remove('hidden'); // Keep year filter visible for all
                    // clear year filter when not archives
                    // yearFilter.value='';
                }
            }
            filterAll();
        });
    });
    
    [searchInput,monthFilter,yearFilter].forEach(el=> el.addEventListener('input',filterAll));
    monthFilter.addEventListener('change',filterAll); 
    yearFilter.addEventListener('change',filterAll); 
    resetBtn.addEventListener('click',resetFilters); 
    
    // Initial filter on page load
    filterAll();
    
    if(typeof menuButton!=='undefined' && typeof mobileMenu!=='undefined'){ 
        menuButton.addEventListener('click',function(){ 
            this.classList.toggle('active'); 
            mobileMenu.style.transform=(mobileMenu.style.transform==='translateY(0%)')? 'translateY(-100%)':'translateY(0%)'; 
        }); 
    }
});
</script>
<script>
// Make table rows clickable, honoring action buttons/links inside the row
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('#complaintsTableBody tr.table-row').forEach(function(row){
        row.addEventListener('click', function(e){
            // Ignore clicks originating from interactive elements
            var tag = e.target.tagName.toLowerCase();
            if (tag === 'a' || tag === 'button' || e.target.closest('a') || e.target.closest('button')) return;
            // Allow Ctrl/Cmd-click to open in new tab
            if (e.ctrlKey || e.metaKey) {
                var href = row.dataset.href;
                if (href) window.open(href, '_blank');
                return;
            }
            var href = row.dataset.href;
            if (href) window.location.href = href;
        });
        // Make cursor indicate clickability
        row.style.cursor = 'pointer';
    });
});
</script>
<?php include 'sidebar_.php'; ?>
<?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
