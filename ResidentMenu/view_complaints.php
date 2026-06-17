<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /BPAMIS/bpamis_website/bpamis.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
$T_CASE_INFO = bpamis_table($conn, 'case_info');

$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);

// Handle schema drift (different column names across dumps/hosts)
$complaintResidentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Resident_ID', 'resident_id']);
$complaintStatusCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Status', 'status', 'Complaint_Status', 'complaint_status']);
$caseStatusCol = bpamis_first_existing_column($conn, $T_CASE_INFO, ['Case_Status', 'case_status', 'Status', 'status']);

// Safe scalar helper to avoid blank-page fatals when query fails
$__bpamis_scalar = function (string $sql, string $types = '', array $params = []) use ($conn) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('view_complaints.php: prepare failed: ' . $conn->error);
        return 0;
    }
    if ($types !== '') {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    if (!$stmt->execute()) {
        error_log('view_complaints.php: execute failed: ' . $stmt->error);
        $stmt->close();
        return 0;
    }
    $res = bpamis_stmt_get_result($stmt);
    $row = $res ? $res->fetch_row() : null;
    $stmt->close();
    return (int)($row[0] ?? 0);
};
$selectStatusExpr = $complaintStatusCol ? ('ci.' . bpamis_quote_ident($complaintStatusCol) . ' AS Status') : 'ci.Status';

$sql = "
SELECT 
    ci.Complaint_ID, 
    ci.Complaint_Title, 
    ci.Complaint_Details, 
    ci.Date_Filed, 
    {$selectStatusExpr},
    n.created_at AS notification_created_at
FROM 
    {$TB_COMPLAINT_INFO} ci
LEFT JOIN (
    SELECT n1.*
    FROM {$TB_NOTIFICATIONS} n1
    INNER JOIN (
        SELECT related_id, MAX(created_at) AS max_created
        FROM {$TB_NOTIFICATIONS} 
        WHERE type = 'complaint'
        GROUP BY related_id
    ) n2 ON n1.related_id = n2.related_id AND n1.created_at = n2.max_created
    WHERE n1.type = 'complaint'
) n ON ci.Complaint_ID = n.related_id
WHERE 
    " . ($complaintResidentIdCol ? ('ci.' . bpamis_quote_ident($complaintResidentIdCol)) : 'ci.Resident_ID') . " = ?
ORDER BY 
    n.created_at DESC
";



$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('view_complaints.php: prepare list failed: ' . $conn->error);
    $complaints = [];
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log('view_complaints.php: execute list failed: ' . $stmt->error);
        $complaints = [];
    } else {
        $complaints = bpamis_stmt_fetch_all_assoc($stmt);
    }
    $stmt->close();
}

// Resolved cases count (only if case status column exists)
$resolvedCases = 0;
if ($caseStatusCol && $complaintResidentIdCol) {
    $resolvedSql = "SELECT COUNT(*)
        FROM {$TB_CASE_INFO} c
        JOIN {$TB_COMPLAINT_INFO} co ON c.Complaint_ID = co.Complaint_ID
        WHERE co." . bpamis_quote_ident($complaintResidentIdCol) . " = ?
          AND c." . bpamis_quote_ident($caseStatusCol) . " = 'Resolved'";
    $resolvedCases = $__bpamis_scalar($resolvedSql, 'i', [$user_id]);
}

// Pending / dismissed complaints count (only if complaint status column exists)
$pendingComplaints = 0;
$dismissedComplaints = 0;
if ($complaintStatusCol && $complaintResidentIdCol) {
    $pendingSql = "SELECT COUNT(*) FROM {$TB_COMPLAINT_INFO}
        WHERE " . bpamis_quote_ident($complaintResidentIdCol) . " = ?
          AND " . bpamis_quote_ident($complaintStatusCol) . " = 'Pending'";
    $pendingComplaints = $__bpamis_scalar($pendingSql, 'i', [$user_id]);

    $dismissedSql = "SELECT COUNT(*) FROM {$TB_COMPLAINT_INFO}
        WHERE " . bpamis_quote_ident($complaintResidentIdCol) . " = ?
          AND " . bpamis_quote_ident($complaintStatusCol) . " = 'Dismissed'";
    $dismissedComplaints = $__bpamis_scalar($dismissedSql, 'i', [$user_id]);
}

$conn->close();

// We now load all complaints for client-side filtering (no slicing)
$totalComplaints = count($complaints);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaints</title>
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
        </style>
    <style>
        .status-badge {
            transition: all 0.3s ease;
        }
        .table-row {
            transition: all 0.2s ease;
        }
        .table-row:hover {
            background-color: #f0f7ff;
        }
        .c-chip.active {
            box-shadow: 0 0 0 2px rgba(59,130,246,.3);
        }
        
        /* Mobile optimizations for compact view - based on secretary implementation */
        @media (max-width: 768px) {
            /* Hero header: do NOT override explicit Tailwind mt (e.g., mt-16/mt-18). Remove forced top margin rule. */
            /* Submit button beside label on mobile */
            .filter-header-row { flex-direction: row !important; align-items: center !important; justify-content: space-between !important; gap: 0.5rem !important; }
            .filter-header-row > a { flex: 0 0 auto; }
            .filter-header-row > div { flex: 1 1 auto; }
            .gradient-bg.rounded-2xl { padding: 1.25rem !important; border-radius: 1rem !important; }
            .gradient-bg h1 { font-size: 1.25rem !important; line-height: 1.75rem !important; }
            .gradient-bg p { font-size: 0.8rem !important; margin-top: 0.5rem !important; }
            .gradient-bg .mt-5 { margin-top: 0.75rem !important; }
            .gradient-bg .px-3.py-1\.5 { padding: 0.35rem 0.6rem !important; font-size: 0.7rem !important; }
            
            /* Stats grid - hide on mobile for more compact view */
            .gradient-bg .hidden.md\:flex { display: none !important; }
            
            /* Filter card adjustments - keep compact spacing under header */
            .filters-section { margin-top: 0.5rem !important; }
            .bg-white\/90.backdrop-blur-sm { padding: 1rem !important; border-radius: 1rem !important; }
            .bg-white\/90 .text-sm { font-size: 0.8rem !important; }
            .bg-white\/90 .px-3.py-1\.5 { padding: 0.35rem 0.6rem !important; font-size: 0.7rem !important; }

            /* Filters layout: search on its own row; month/year/sort/reset in one row */
            .filters-grid { display: flex !important; flex-wrap: wrap !important; gap: 0.65rem !important; align-items: stretch !important; }
            .filters-grid .filter-search { width: 100% !important; order: 1 !important; flex: 0 0 100% !important; }
            .filters-grid .filter-select { order: 2 !important; flex: 1 1 0 !important; min-width: 0 !important; }
            .filters-grid .filter-reset { order: 2 !important; flex: 0 0 auto !important; width: 3rem !important; }
            
            /* Status chips */
            #statusChips .c-chip { padding: 0.35rem 0.65rem !important; font-size: 0.7rem !important; }
            
            /* Search and filter inputs */
            .grid.grid-cols-1.md\:grid-cols-12 { gap: 0.65rem !important; }
            /* Search input keeps compact padding but with larger left for icon */
            #searchInput { padding: 0.6rem 0.75rem !important; font-size: 0.8rem !important; border-radius: 0.75rem !important; padding-left: 2.25rem !important; }
            /* Selects need extra right padding so text doesn't collide with caret icon */
            #monthFilter, #yearFilter, #sortOrder { padding: 0.55rem 2rem 0.55rem 0.7rem !important; font-size: 0.8rem !important; line-height: 1.2 !important; border-radius: 0.75rem !important; }
            .fa-search { left: 0.75rem !important; font-size: 0.8rem !important; }
            .fa-caret-down { font-size: 0.75rem !important; }
            
            /* Reset button */
            #resetFilters { padding: 0.6rem 0.75rem !important; font-size: 0.8rem !important; border-radius: 0.75rem !important; }
            #resetFilters span { display: none !important; }
            
            /* Submit complaint button */
            .from-primary-500.to-primary-600 { padding: 0.5rem 0.85rem !important; font-size: 0.8rem !important; border-radius: 0.75rem !important; }
            
            /* Complaints cards container - reduce top margin */
            .complaints-section { margin-top: 0.5rem !important; }
            .bg-white.rounded-2xl { padding: 1rem !important; border-radius: 1rem !important; }
            #complaintsContainer { gap: 0.75rem !important; }
            
            /* Individual complaint cards */
            .complaint-card { padding: 0.85rem !important; gap: 0.65rem !important; border-radius: 0.75rem !important; }
            .complaint-card .text-\[11px\] { font-size: 0.65rem !important; }
            .complaint-card .px-2\.5.py-1 { padding: 0.25rem 0.5rem !important; font-size: 0.65rem !important; }
            .complaint-card .text-xs { font-size: 0.7rem !important; }
            .complaint-card .text-sm { font-size: 0.8rem !important; line-height: 1.3 !important; }
            .complaint-card .px-3.py-2 { padding: 0.45rem 0.65rem !important; font-size: 0.7rem !important; }
            .complaint-card .fas.fa-eye { font-size: 0.7rem !important; }
            
            /* Visible count */
            #visibleCount { font-size: 0.7rem !important; padding: 0.25rem 0.6rem !important; }
            
            /* No results message */
            #noResults { padding: 1.5rem !important; font-size: 0.8rem !important; }
            #noResults .fa-circle-exclamation { font-size: 1.25rem !important; }
            #noResults p { font-size: 0.75rem !important; }
            
            /* Compact spacing */
            .space-y-5 { gap: 0.75rem !important; }
            .gap-8 { gap: 1rem !important; }
            .gap-4 { gap: 0.65rem !important; }
            .gap-3 { gap: 0.5rem !important; }
            .gap-2 { gap: 0.4rem !important; }
        }
        
        @media (max-width: 640px) {
            /* Extra small screens - even more compact */
            .gradient-bg.rounded-2xl { padding: 1rem !important; }
            .gradient-bg h1 { font-size: 1.1rem !important; }
            .gradient-bg p { font-size: 0.75rem !important; }
            .gradient-bg .px-3.py-1\.5 { padding: 0.3rem 0.5rem !important; font-size: 0.65rem !important; }
            
            .bg-white\/90.backdrop-blur-sm { padding: 0.75rem !important; }
            #statusChips .c-chip { padding: 0.3rem 0.55rem !important; font-size: 0.65rem !important; }
            
            /* Extra-small: keep room for caret icon on selects */
            #searchInput { padding: 0.5rem 0.65rem !important; font-size: 0.75rem !important; border-radius: 0.75rem !important; padding-left: 2rem !important; }
            #monthFilter, #yearFilter, #sortOrder { padding: 0.45rem 1.9rem 0.45rem 0.6rem !important; font-size: 0.75rem !important; line-height: 1.2 !important; border-radius: 0.75rem !important; }
            
            #resetFilters { padding: 0.5rem 0.6rem !important; font-size: 0.75rem !important; }
            .from-primary-500.to-primary-600 { padding: 0.45rem 0.75rem !important; font-size: 0.75rem !important; }
            
            .bg-white.rounded-2xl { padding: 0.75rem !important; }
            #complaintsContainer { gap: 0.6rem !important; }
            
            /* Complaint cards - more compact */
            .complaint-card { padding: 0.7rem !important; gap: 0.5rem !important; }
            .complaint-card .text-\[11px\] { font-size: 0.6rem !important; }
            .complaint-card .px-2\.5.py-1 { padding: 0.2rem 0.45rem !important; font-size: 0.6rem !important; }
            .complaint-card .text-xs { font-size: 0.65rem !important; }
            .complaint-card .text-sm { font-size: 0.75rem !important; line-height: 1.25 !important; }
            .complaint-card .px-3.py-2 { padding: 0.4rem 0.6rem !important; font-size: 0.65rem !important; }
            .complaint-card .fas.fa-eye { font-size: 0.65rem !important; }
            
            #visibleCount { font-size: 0.65rem !important; padding: 0.2rem 0.5rem !important; }
            
            #noResults { padding: 1.25rem !important; }
            #noResults .fa-circle-exclamation { font-size: 1.1rem !important; margin-bottom: 0.5rem !important; }
            #noResults p { font-size: 0.7rem !important; }
            
            /* Bottom info text */
            .text-\[11px\].text-gray-400 { font-size: 0.65rem !important; }
        }
        
        @media (max-width: 480px) {
            /* Very small screens - ultra compact */
            .gradient-bg.rounded-2xl { padding: 0.85rem !important; }
            .gradient-bg h1 { font-size: 1rem !important; }
            .gradient-bg p { font-size: 0.7rem !important; }
            
            .bg-white\/90.backdrop-blur-sm { padding: 0.65rem !important; }
            
            .bg-white.rounded-2xl { padding: 0.65rem !important; }
            #complaintsContainer { gap: 0.5rem !important; grid-template-columns: 1fr !important; }
            
            .complaint-card { padding: 0.6rem !important; gap: 0.45rem !important; }
            .complaint-card .text-\[11px\] { font-size: 0.55rem !important; }
            .complaint-card .px-2\.5.py-1 { padding: 0.18rem 0.4rem !important; font-size: 0.55rem !important; }
            .complaint-card .text-xs { font-size: 0.6rem !important; }
            .complaint-card .text-sm { font-size: 0.7rem !important; }
            .complaint-card .px-3.py-2 { padding: 0.35rem 0.55rem !important; font-size: 0.6rem !important; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once __DIR__ . '/../includes/resident_nav.php'; ?>

    <!-- Global Blue Blush Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <?php 
        // Additional count for 'In Case'
        $inCaseCount = 0; 
        foreach($complaints as $c){ if(strtolower($c['Status'])==='in case'){ $inCaseCount++; } }
    ?>
    <!-- Premium Hero Header -->
    <div class="w-full mt-16 md:mt-8 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden max-w-screen-2xl mx-auto px-5 pt-10 relative">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_7s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Complaints</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Review status, track updates, and monitor resolutions. Use the smart filters below to narrow results instantly.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-layer-group text-primary-500"></i> Organized Timeline</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Smart Search</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> Quick Insights</span>
                    </div>
                </div>
                <div class="hidden md:flex flex-col gap-3 min-w-[260px]">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">Pending</span><span class="mt-1 text-lg font-semibold text-amber-700"><?= $pendingComplaints ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-green-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-green-600 font-semibold">Resolved</span><span class="mt-1 text-lg font-semibold text-green-700"><?= $resolvedCases ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-red-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-red-600 font-semibold">Dismissed</span><span class="mt-1 text-lg font-semibold text-red-700"><?= $dismissedComplaints ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">In Case</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= $inCaseCount ?></span></div>
                    </div>
                    <div class="text-[11px] text-primary-700/70 text-center">Status overview</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters & Search -->
    <div class="w-full mt-8 px-4 filters-section">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-5">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 filter-header-row">
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                        </div>
                        <a href="submit_complaints.php" class="group relative inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-circle-plus text-white"></i>
                            <span>Submit Complaint</span>
                            <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    <!-- Status Chips -->
                    <div class="flex flex-wrap gap-2 pt-1" id="statusChips">
                        <button type="button" data-status="" class="c-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-status="Pending" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Pending</button>
                        <button type="button" data-status="Resolved" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Resolved</button>
                        <button type="button" data-status="Dismissed" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 transition">Dismissed</button>
                        <button type="button" data-status="In Case" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition">In Case</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 filters-grid">
                        <div class="md:col-span-5 relative group filter-search">
                            <input id="searchInput" type="text" placeholder="Search by ID, status or description..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                        </div>
                        <div class="md:col-span-2 relative filter-select">
                            <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); ?>
                                    <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>"><?= $mn ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-2 relative filter-select">
                            <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Years</option>
                                <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-2 relative filter-select">
                            <select id="sortOrder" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="desc">Newest First</option>
                                <option value="asc">Oldest First</option>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-1 flex filter-reset">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Complaints List -->
    <div class="w-full mt-8 px-4 pb-16 complaints-section">
        <div class="max-w-screen-2xl mx-auto bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden p-4 md:p-6">
            <div id="complaintsContainer" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($complaints as $complaint): 
                    $status = $complaint['Status'];
                    $statusNorm = strtolower(trim((string)$status));
                    $statusClassMap = [
                        'resolved' => 'text-green-700 bg-green-50 border border-green-200',
                        'dismissed' => 'text-red-700 bg-red-50 border border-red-200',
                        'pending' => 'text-amber-700 bg-amber-50 border border-amber-200',
                        'in case' => 'text-blue-700 bg-blue-50 border border-blue-200',
                    ];
                    $statusClass = $statusClassMap[$statusNorm] ?? 'text-gray-700 bg-gray-50 border border-gray-200';

                    $fullDesc = $complaint['Complaint_Details'] ?? '';
                    $descLen = function_exists('mb_strlen') ? mb_strlen($fullDesc) : strlen($fullDesc);
                    if ($descLen > 140) {
                        $cut = function_exists('mb_substr') ? mb_substr($fullDesc, 0, 137) : substr($fullDesc, 0, 137);
                        $shortDesc = htmlspecialchars($cut) . '…';
                    } else {
                        $shortDesc = htmlspecialchars($fullDesc);
                    }
                    $filedDate = !empty($complaint['notification_created_at']) ? $complaint['notification_created_at'] : $complaint['Date_Filed'];
                    // Resolution message
                    $resMsgMap = [
                        'in case' => 'This complaint is under resolution by the Barangay Justice System.',
                        'dismissed' => 'This complaint is not part of the Barangay Justice System.',
                        'pending' => 'This complaint is currently being processed.',
                        'resolved' => 'This complaint was successfully resolved by the barangay.',
                    ];
                    $resMsg = $resMsgMap[$statusNorm] ?? 'No resolution information available.';
                    $searchBlob = strtolower(
                        'COMP-' . str_pad($complaint['Complaint_ID'],3,'0',STR_PAD_LEFT).' '.
                        ($complaint['Complaint_Title'] ?? '').' '.
                        $status.' '.
                        $fullDesc
                    );
                ?>
                <div class="complaint-card bg-white/80 backdrop-blur rounded-xl border border-gray-100 p-4 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" 
                     data-status="<?= strtolower($status) ?>" 
                     data-date="<?= htmlspecialchars($filedDate) ?>" 
                        data-id-text="<?= 'COMP#' . str_pad($complaint['Complaint_ID'],3,'0',STR_PAD_LEFT) ?>"
                     data-title="<?= htmlspecialchars(strtolower($complaint['Complaint_Title'])) ?>" 
                     data-desc="<?= htmlspecialchars(strtolower($fullDesc)) ?>" 
                     data-search="<?= htmlspecialchars($searchBlob) ?>">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-mono tracking-wide text-gray-500">
                                <?= 'COMP#' . str_pad($complaint['Complaint_ID'], 3, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($status) ?>
                        </span>
                    </div>
                    <div class="text-xs text-gray-500">Filed on: <?= date('F j, Y', strtotime($filedDate)) ?></div>
                    <p class="text-sm text-gray-600 leading-snug line-clamp-3" title="<?= htmlspecialchars($fullDesc) ?>" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                        <?= $shortDesc ?: '<span class=\'italic text-gray-400\'>No description provided.</span>' ?>
                    </p>
                    <div class="mt-auto pt-1 flex items-center justify-end">
                        <a href="view_complaint_details.php?id=<?= $complaint['Complaint_ID'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-primary-50 text-primary-600 text-xs font-medium hover:bg-primary-100 transition">
                            <i class="fas fa-eye text-primary-500"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="noResults" class="hidden mt-6 p-8 border-2 border-dashed border-primary-200 rounded-xl text-center text-primary-700 bg-primary-50/40">
                <i class="fa-solid fa-circle-exclamation text-primary-500 text-2xl mb-2"></i>
                <p class="font-medium">No complaints match your filters.</p>
                <p class="text-sm opacity-80">Try adjusting status, date filters, or clearing the search.</p>
            </div>
            <div class="mt-6 flex items-center justify-between text-xs text-gray-600 flex-col md:flex-row gap-3">
                <div id="visibleCount" class="px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Showing <?= $totalComplaints ?> items</div>
                
            </div>
        </div>
    </div>
    <?php include_once __DIR__ . '/../chatbot/bpamis_case_assistant.php'; ?>
    <script>
    (function(){
        const cards=[...document.querySelectorAll('.complaint-card')];
        const statusChips=document.getElementById('statusChips')?document.querySelectorAll('#statusChips .c-chip'):[];
        const searchInput=document.getElementById('searchInput');
        const monthFilter=document.getElementById('monthFilter');
        const yearFilter=document.getElementById('yearFilter');
        const sortOrder=document.getElementById('sortOrder');
        const resetBtn=document.getElementById('resetFilters');
        const visibleCount=document.getElementById('visibleCount');
        const noResults=document.getElementById('noResults');

        // Label shortening for very small widths to keep selects readable
        function shortenLabelsIfNeeded(){
            if(window.innerWidth <= 420){
                if(sortOrder){
                    [...sortOrder.options].forEach(o=>{
                        if(/Newest First/i.test(o.text)) o.text = 'Newest';
                        if(/Oldest First/i.test(o.text)) o.text = 'Oldest';
                    });
                }
                if(monthFilter){
                    [...monthFilter.options].forEach(o=>{
                        if(/All Months/i.test(o.text)) o.text = 'Months';
                    });
                }
                if(yearFilter){
                    [...yearFilter.options].forEach(o=>{
                        if(/All Years/i.test(o.text)) o.text = 'Years';
                    });
                }
            }
        }
        shortenLabelsIfNeeded();
        window.addEventListener('resize', shortenLabelsIfNeeded);

        function parseDate(ds){return new Date(ds.replace(' ','T'));}
        function apply(){
            const q=(searchInput?.value||'').toLowerCase();
            const active=document.querySelector('#statusChips .c-chip.active');
            const statusFilter=active?active.dataset.status.toLowerCase():'';
            const m=monthFilter?monthFilter.value:''; const y=yearFilter?yearFilter.value:'';
            let vis=[];
            cards.forEach(c=>{
                const status=c.dataset.status; // already lowercase
                const blob=c.dataset.search;
                const dstr=c.dataset.date || '';
                let keep=true;
                if(statusFilter && status!==statusFilter) keep=false;
                if(keep && q && !blob.includes(q)) keep=false;
                if(keep && (m||y)){
                    if(dstr){
                        const d=parseDate(dstr);
                          if(m && String(d.getMonth()+1).padStart(2,'0')!==m) keep=false;
                          if(y && String(d.getFullYear())!==y) keep=false;
                    } else keep=false;
                }
                c.classList.toggle('hidden',!keep);
                if(keep) vis.push(c);
            });
            // sort
            if(sortOrder){
                const o=sortOrder.value;
                vis.sort((a,b)=>{
                    const da=parseDate(a.dataset.date).getTime();
                    const db=parseDate(b.dataset.date).getTime();
                    return o==='desc'? db-da : da-db;
                });
                const cont=document.getElementById('complaintsContainer');
                vis.forEach(v=>cont.appendChild(v));
            }
            visibleCount.textContent='Showing '+vis.length+' item'+(vis.length!==1?'s':'');
            noResults.classList.toggle('hidden', vis.length!==0);
        }
        let timer; if(searchInput) searchInput.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(apply,200);});
        statusChips.forEach(ch=>ch.addEventListener('click',()=>{statusChips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white')); if(ch.dataset.status===''){ch.classList.add('bg-primary-600','text-white');} ch.classList.add('active'); apply();}));
        [monthFilter,yearFilter,sortOrder].forEach(sel=> sel && sel.addEventListener('change',apply));
        if(resetBtn) resetBtn.addEventListener('click',()=>{ if(searchInput) searchInput.value=''; if(monthFilter) monthFilter.value=''; if(yearFilter) yearFilter.value=''; if(sortOrder) sortOrder.value='desc'; statusChips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white')); const all=document.querySelector('#statusChips .c-chip[data-status=""]'); if(all){all.classList.add('active','bg-primary-600','text-white');} apply(); });
        apply();
    })();
    </script>
</body>
</html>
