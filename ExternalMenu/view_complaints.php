<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Determine external user id (prefer external_id session variable)
$external_id = null;
if(isset($_SESSION['external_id'])) $external_id = (int)$_SESSION['external_id'];
elseif(isset($_SESSION['user_id'])) $external_id = (int)$_SESSION['user_id'];

if(!$external_id){
    header('Location: ../bpamis_website/login.php');
    exit;
}

// Detect whether complaint_info has external_complainant_id column
$useExternalCol = false;
if($conn){
    if($colRes = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'external_complainant_id'")){
        if($colRes->num_rows>0) $useExternalCol = true; $colRes->close();
    }
}

if($useExternalCol){
    $sql = "SELECT Complaint_ID, Complaint_Title, Complaint_Details, Date_Filed, Status FROM complaint_info WHERE external_complainant_id = ? ORDER BY Complaint_ID DESC";
} else {
    // Fallback: store external complaints under Resident_ID (legacy) so filter by Resident_ID
    $sql = "SELECT Complaint_ID, Complaint_Title, Complaint_Details, Date_Filed, Status FROM complaint_info WHERE Resident_ID = ? ORDER BY Complaint_ID DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $external_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
// Derive counts in-memory (avoid extra queries) & track In Case
$resolvedCases = $pendingComplaints = $rejectedComplaints = $inCaseCount = 0;
foreach($complaints as $c){
    $s = strtolower(trim($c['Status']));
    if($s==='resolved') $resolvedCases++; elseif($s==='pending') $pendingComplaints++; elseif($s==='rejected') $rejectedComplaints++; elseif($s==='in case') $inCaseCount++;
}

// Pagination (match resident/external premium pattern)
$perPage = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$totalComplaints = count($complaints);
$totalPages = max(1, ceil($totalComplaints / $perPage));
$start = ($page - 1) * $perPage;
$complaintsToDisplay = array_slice($complaints, $start, $perPage);



$stmt->close();
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>View Complaints</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
        
        /* Chatbot Button Styles */
        .chatbot-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            box-shadow: 0 4px 15px rgba(2, 129, 212, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            outline: none;
        }
        
        .chatbot-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 6px 20px rgba(2, 129, 212, 0.35);
        }
        
        .chatbot-button i {
            font-size: 24px;
            color: white;
            transition: transform 0.3s ease;
        }
        
        .chatbot-button:hover i {
            transform: rotate(10deg);
        }
        
        .pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: rgba(2, 129, 212, 0.7);
            opacity: 0;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(0.95);
                opacity: 0.7;
            }
            70% {
                transform: scale(1.1);
                opacity: 0;
            }
            100% {
                transform: scale(0.95);
                opacity: 0;
            }
        }
        
        .chatbot-container {
            position: fixed;
            bottom: 5.5rem;
            right: 2rem;
            width: 350px;
            max-height: 500px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            z-index: 999;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .chatbot-container.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }
        
        .chatbot-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chatbot-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .chatbot-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .chatbot-close:hover {
            transform: rotate(90deg);
        }
        
        .chatbot-body {
            height: 340px;
            overflow-y: auto;
            padding: 20px;
        }
        
        .chatbot-footer {
            padding: 12px 15px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        
        .chatbot-input {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
        }
        
        .chatbot-input:focus {
            border-color: #0c9ced;
            box-shadow: 0 0 0 2px rgba(12, 156, 237, 0.1);
        }
        
        .send-button {
            background: #0c9ced;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .send-button:hover {
            background: #0281d4;
        }
        
        .chat-message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .bot-message {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            position: relative;
        }
        
        .user-message .message-content {
            background-color: #0c9ced;
            color: white;
            border-bottom-right-radius: 4px;
            margin-right: 10px;
        }
        
        .bot-message .message-content {
            background-color: #f0f7ff;
            color: #333;
            border-bottom-left-radius: 4px;
            margin-left: 10px;
        }
        
        .bot-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0effe;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .bot-avatar i {
            color: #0281d4;
            font-size: 16px;
        }
        
        .message-time {
            font-size: 10px;
            color: #888;
            margin-top: 4px;
            text-align: right;
        }
        
        /* Mobile responsiveness for chatbot */
        @media (max-width: 640px) {
            .chatbot-container {
                width: calc(100% - 32px);
                right: 16px;
                left: 16px;
                bottom: 5rem;
            }
            
            .chatbot-button {
                bottom: 1.5rem;
                right: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            /* Filters layout: search on its own row; month/year/sort/reset in one row */
            .filters-grid { display: flex !important; flex-wrap: wrap !important; gap: 0.65rem !important; align-items: stretch !important; }
            .filters-grid .filter-search { width: 100% !important; order: 1 !important; flex: 0 0 100% !important; }
            .filters-grid .filter-select { order: 2 !important; flex: 1 1 0 !important; min-width: 0 !important; }
            .filters-grid .filter-reset { order: 2 !important; flex: 0 0 auto !important; width: 3rem !important; }
            
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
        /* Enhanced View Details button */
        .view-details-btn {
            padding: 0.45rem 0.85rem;
            font-size: 0.7rem;
            background: rgba(12,156,237,0.08);
            color: #0281d4;
            border: 1px solid rgba(12,156,237,0.25);
            border-radius: 0.65rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
            letter-spacing: .25px;
            line-height: 1;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            transition: all .28s cubic-bezier(.4,0,.2,1);
        }
        .view-details-btn:before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg,#0281d4,#0c9ced);
            opacity: 0;
            transition: opacity .35s ease;
            z-index: 0;
        }
        .view-details-btn:hover:before {
            opacity: 1;
        }
        .view-details-btn:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px -4px rgba(12,156,237,.45);
        }
        .view-details-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px -2px rgba(12,156,237,.30);
        }
        .view-details-btn span, .view-details-btn i { position: relative; z-index: 1; }
        .view-details-btn i { font-size: .75rem; }
        .view-details-btn:focus-visible { outline: 2px solid #0c9ced; outline-offset: 2px; }
        .table-row:hover .view-details-btn:not(:hover) { background: rgba(12,156,237,0.12); }
        /* Mobile density tweaks */
        @media (max-width: 640px) {
            body { background: #f8fafc; }
            .gradient-bg { border-radius: 18px; padding: 1.75rem !important; }
            .complaint-card { padding: 0.9rem 0.95rem; }
            .complaint-card .text-sm { font-size: 0.95rem; }
            .complaint-card .text-\[11px\] {
                    font-size: 0.7rem;
                }
            #complaintsContainer { grid-template-columns: 1fr !important; gap: 14px !important; }
            .max-w-screen-2xl.mx-auto.bg-white.rounded-2xl { border-radius: 18px; }
            .inline-flex.items-center.gap-1\.5.px-3.py-2 { padding: 0.55rem 0.9rem; }
        }
        @supports (padding: max(0px)) {
            body { padding-left: max(env(safe-area-inset-left),0px); padding-right: max(env(safe-area-inset-right),0px); }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">    <?php include_once('../includes/external_nav.php'); ?>
    <!-- Enhanced Hero -->
    <div class="w-full mt-10 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary-100 rounded-full -mr-28 -mt-28 opacity-70 animate-[float_10s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-primary-200 rounded-full -ml-16 -mb-16 opacity-60 animate-[float_8s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h2 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Complaints</span></h2>
                    <p class="mt-4 text-gray-600 leading-relaxed">Track the progress and status of every complaint you've submitted. Use smart filters below to quickly narrow down results.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Filterable</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-chart-line text-primary-500"></i> Status Insights</span>
                    </div>
                </div>
                <div class="hidden md:flex flex-col gap-3 min-w-[230px]">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm">
                            <span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">Resolved</span>
                            <span class="mt-1 text-lg font-semibold text-primary-700"><?= $resolvedCases ?></span>
                        </div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm">
                            <span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">Pending</span>
                            <span class="mt-1 text-lg font-semibold text-primary-700"><?= $pendingComplaints ?></span>
                        </div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-primary-100 shadow-sm">
                            <span class="text-[10px] uppercase tracking-wide text-primary-500 font-semibold">Rejected</span>
                            <span class="mt-1 text-lg font-semibold text-primary-700"><?= $rejectedComplaints ?></span>
                        </div>
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
                    <div class="flex flex-row items-center justify-between gap-2 flex-nowrap md:flex-wrap">
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium min-w-0">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100 truncate"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> <span class="truncate">Search & Filter</span></span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                        </div>
                        <a href="submit_complaints.php" class="group relative inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all flex-shrink-0">
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
                        <button type="button" data-status="Rejected" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 transition">Rejected</button>
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

    <!-- Complaints Grid -->
    <div class="w-full mt-8 px-4 pb-16">
        <div class="max-w-screen-2xl mx-auto bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden p-4 md:p-6">
            <div id="complaintsContainer" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if(!count($complaintsToDisplay)): ?>
                    <div class="col-span-full text-center text-gray-500 py-8 text-sm">No complaints found.</div>
                <?php else: foreach($complaintsToDisplay as $complaint): 
                    $status = $complaint['Status'];
                    $statusNorm = strtolower(trim((string)$status));
                    $statusClassMap = [
                        'resolved' => 'text-green-700 bg-green-50 border border-green-200',
                        'rejected' => 'text-red-700 bg-red-50 border border-red-200',
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
                ?>
                <div class="complaint-card group relative bg-white/80 backdrop-blur rounded-xl border border-gray-100 p-4 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" data-status="<?= strtolower($status) ?>" data-date="<?= htmlspecialchars($filedDate) ?>" data-id-text="<?= 'COMP#' . str_pad($complaint['Complaint_ID'],3,'0',STR_PAD_LEFT) ?>">
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
                <?php endforeach; endif; ?>
            </div>
            <div class="mt-6 flex items-center justify-between text-xs text-gray-600 flex-col md:flex-row gap-3">
                <div id="visibleCount" class="px-2.5 py-1 rounded-full bg-primary-50 text-primary-600 border border-primary-100 font-medium">Showing <?= count($complaintsToDisplay) ?> items</div>
                
            </div>
            <div class="mt-6 flex justify-center">
                <a href="home-external.php" class="px-4 py-2 text-gray-500 hover:text-gray-700 flex items-center transition-colors text-sm"><i class="fas fa-arrow-left mr-2"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>
    <script>
    // Filtering & sorting logic for card layout
    document.addEventListener('DOMContentLoaded',()=>{
        const cardsContainer=document.getElementById('complaintsContainer');
        const searchInput=document.getElementById('searchInput');
        const monthFilter=document.getElementById('monthFilter');
        const yearFilter=document.getElementById('yearFilter');
        const sortOrder=document.getElementById('sortOrder');
        const resetBtn=document.getElementById('resetFilters');
        const statusChips=document.querySelectorAll('#statusChips .c-chip');
        const visibleCount=document.getElementById('visibleCount');
        let activeStatus='';

        statusChips.forEach(chip=> chip.addEventListener('click',()=>{ statusChips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white')); chip.classList.add('active','bg-primary-600','text-white'); activeStatus=chip.dataset.status; runFilter(); }));

        function runFilter(){
            const q=(searchInput.value||'').toLowerCase();
            const m=monthFilter.value; const y=yearFilter.value; const order=sortOrder.value;
            const cards=[...cardsContainer.querySelectorAll('.complaint-card')];
            let shown=0;
            cards.forEach(card=>{
                const idText=card.dataset.idText.toLowerCase();
                const status=(card.dataset.status||'').toLowerCase();
                const dateAttr=card.dataset.date; // raw date
                const titleEl=card.querySelector('h3');
                const descEl=card.querySelector('p.text-sm');
                const title=(titleEl?.textContent||'').toLowerCase();
                const desc=(descEl?.getAttribute('title')||'').toLowerCase();
                let match=true;
                if(q){ match = idText.includes(q) || title.includes(q) || status.includes(q) || desc.includes(q); }
                if(match && activeStatus){ match = status === activeStatus.toLowerCase(); }
                if(match && (m||y) && dateAttr){ const d=new Date(dateAttr); if(m) match = match && (('0'+(d.getMonth()+1)).slice(-2)===m); if(y) match = match && (d.getFullYear().toString()===y); }
                card.style.display = match? 'flex':'none';
                if(match) shown++;
            });
            // Sort after filtering
            cards.sort((a,b)=>{ const da=new Date(a.dataset.date); const db=new Date(b.dataset.date); return order==='desc'? db-da: da-db; }).forEach(c=>cardsContainer.appendChild(c));
            visibleCount.textContent='Showing '+shown+' items';
        }
        ['input','change'].forEach(evt=> searchInput.addEventListener(evt,runFilter));
        [monthFilter,yearFilter,sortOrder].forEach(sel=> sel.addEventListener('change', runFilter));
        resetBtn.addEventListener('click',()=>{ searchInput.value=''; monthFilter.value=''; yearFilter.value=''; sortOrder.value='desc'; activeStatus=''; statusChips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white')); statusChips[0].classList.add('active','bg-primary-600','text-white'); runFilter(); });
        runFilter();
    });
    </script>
    
    <!-- Chatbot Button and Container -->
    <button class="chatbot-button" id="chatbotButton" aria-label="Open case assistant chatbot">
        <div class="pulse"></div>
        <i class="fas fa-robot"></i>
    </button>
    
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">
            <h3><i class="fas fa-robot"></i> Case Assistant</h3>
            <button class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-body" id="chatbotBody">
            <!-- Bot welcome message -->
            <div class="chat-message bot-message">
                <div class="bot-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    Hi there! I'm your Case Assistant. How can I help you with your barangay cases today?
                    <div class="message-time">Just now</div>
                </div>
            </div>
        </div>
        <div class="chatbot-footer">
            <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Type your question here..." aria-label="Type your message">
            <button class="send-button" id="sendButton" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    
    <script>
        // Chatbot functionality
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotButton = document.getElementById('chatbotButton');
            const chatbotContainer = document.getElementById('chatbotContainer');
            const chatbotClose = document.getElementById('chatbotClose');
            const chatbotInput = document.getElementById('chatbotInput');
            const sendButton = document.getElementById('sendButton');
            const chatbotBody = document.getElementById('chatbotBody');
            
            // Toggle chatbot visibility
            chatbotButton.addEventListener('click', function() {
                chatbotContainer.classList.toggle('active');
                chatbotInput.focus();
            });
            
            // Close chatbot
            chatbotClose.addEventListener('click', function() {
                chatbotContainer.classList.remove('active');
            });
            
            // Send message function
            function sendMessage() {
                const message = chatbotInput.value.trim();
                if (message === '') return;
                
                // Add user message to chat
                const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const userMessageHTML = `
                    <div class="chat-message user-message">
                        <div class="message-content">
                            ${message}
                            <div class="message-time">${timestamp}</div>
                        </div>
                    </div>
                `;
                
                chatbotBody.innerHTML += userMessageHTML;
                chatbotInput.value = '';
                chatbotBody.scrollTop = chatbotBody.scrollHeight;
                
                // Simulate bot typing
                setTimeout(() => {
                    const botResponse = getBotResponse(message);
                    const botMessageHTML = `
                        <div class="chat-message bot-message">
                            <div class="bot-avatar">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="message-content">
                                ${botResponse}
                                <div class="message-time">${timestamp}</div>
                            </div>
                        </div>
                    `;
                    
                    chatbotBody.innerHTML += botMessageHTML;
                    chatbotBody.scrollTop = chatbotBody.scrollHeight;
                }, 800);
            }
            
            // Send message on button click
            sendButton.addEventListener('click', sendMessage);
            
            // Send message on Enter key
            chatbotInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
            
            // Simple bot response function
            function getBotResponse(message) {
                message = message.toLowerCase();
                
                if (message.includes('hello') || message.includes('hi') || message.includes('hey')) {
                    return 'Hello! How can I assist you with your case today?';
                }
                else if (message.includes('case status') || message.includes('status')) {
                    return 'To check your case status, please go to the "View Cases" section where you can see all your active and resolved cases.';
                }
                else if (message.includes('hearing') || message.includes('schedule')) {
                    return 'Your next hearing is scheduled for May 20, 2025. You can view all upcoming hearings in the calendar section of your dashboard.';
                }
                else if (message.includes('mediation') || message.includes('mediator')) {
                    return 'Mediation sessions are conducted by trained Lupong Tagapamayapa members. Your upcoming mediation session is scheduled for May 18, 2025.';
                }
                else if (message.includes('complaint') || message.includes('file') || message.includes('submit')) {
                    return 'To file a new complaint, click on the "Submit Complaint" button in the Quick Actions section of your dashboard.';
                }
                else if (message.includes('contact') || message.includes('barangay') || message.includes('office')) {
                    return 'You can contact the Barangay Office at (123) 456-7890 or visit them during office hours: Monday to Friday, 8:00 AM to 5:00 PM.';
                }
                else if (message.includes('thank')) {
                    return 'You\'re welcome! Is there anything else I can help you with?';
                }
                else {
                    return 'I\'m not sure I understand. Could you please rephrase your question? You can ask about case status, hearings, complaints, or contact information.';
                }
            }
        });
    </script>
</body>
</html>
