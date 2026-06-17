<?php
include '../controllers/session_control.php';

// Ensure a session is active so $_SESSION can be inspected regardless of role-based session name
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// If user_id isn't present, redirect to the centralized login page (use absolute path to avoid relative errors)
if (empty($_SESSION['user_id'])) {
    header("Location: /BPAMIS/bpamis_website/login.php");
    exit();
}
$role = strtolower($_SESSION['role'] ?? '');

// Enforce that this page is only accessible to external users. If another role is active,
// redirect to that role's home. This mirrors resident behavior where pages are scoped by role.
if ($role !== 'external') {
    if ($role === 'resident') {
        header('Location: /BPAMIS/ResidentMenu/home-resident.php');
        exit();
    } elseif ($role === 'official') {
        // Fallback for officials — send to secretary home (most officials also have position-specific homes)
        header('Location: /BPAMIS/SecMenu/home-secretary.php');
        exit();
    } else {
        // Not logged in as a recognized role — send to centralized login
        header('Location: /BPAMIS/bpamis_website/login.php');
        exit();
    }
}

$external_id = (int)($_SESSION['user_id'] ?? 0);
include '../server/server.php'; 


$sql = "
    SELECT 
        ci.Case_ID,
        co.Complaint_Title, /* legacy retained */
        co.case_type,       /* new display target */
        co.Date_Filed,
        ci.case_status,
        sl.hearingDateTime AS Next_Hearing_Date
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    LEFT JOIN (
        SELECT Case_ID, MIN(hearingDateTime) AS hearingDateTime
        FROM schedule_list
        WHERE hearingDateTime >= NOW()
        GROUP BY Case_ID
    ) sl ON ci.Case_ID = sl.Case_ID
    WHERE co.external_complainant_id = ?
    ORDER BY co.Date_Filed DESC
";



$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $external_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$cases = [];
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
// Pagination & status counts (premium UI parity)
$casesPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$totalCases = count($cases);
$startIndex = ($currentPage - 1) * $casesPerPage;
$paginatedCases = array_slice($cases, $startIndex, $casesPerPage);
$totalPages = $totalCases > 0 ? (int)ceil($totalCases / $casesPerPage) : 1;

// Dynamic status counts
$statusCounts = [];
foreach($cases as $c){
    $s = trim($c['case_status'] ?? 'Unknown');
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}

$pendingCount = 0;
$mediationCount = 0;
$resolvedCount = 0;
$cfaCount = 0; // Certificate to File Action

foreach($statusCounts as $k => $v){
    $kl = strtolower($k);
    if (stripos($kl, 'pending') !== false) {
        $pendingCount += $v;
    }
    if (stripos($kl, 'mediation') !== false) {
        $mediationCount += $v;
    }
    // Any status that contains the word 'resolved' should count as Resolved
    if (stripos($kl, 'resolved') !== false) {
        $resolvedCount += $v;
    }
    // Certificate to File Action - match common phrasings
    if (stripos($kl, 'certificate') !== false || stripos($kl, 'file action') !== false) {
        $cfaCount += $v;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Cases</title>
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
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">    <?php include_once('../includes/external_nav.php'); ?>
    <!-- Background Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <!-- Premium Hero -->
    <div class="w-full mt-8 px-4">
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
                        <button type="button" data-role="complainant" class="r-tab px-3 py-2 text-xs font-medium text-gray-600 hover:text-gray-800 border-b-2 border-transparent">As Complainant</button>
                    </div>
                    <div class="flex flex-row items-center justify-between gap-2 flex-nowrap md:flex-wrap">
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium min-w-0">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Search & Filter</span>
                            <span class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine</span>
                        </div>
                        <a href="view_hearing_calendar_external.php" class="group relative inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-semibold shadow-sm hover:shadow-md transition-all flex-shrink-0">
                            <i class="fa-solid fa-calendar-days text-white"></i>
                            <span>View Hearing Calendar</span>
                            <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/20"></span>
                        </a>
                    </div>
                    <!-- Status Chips -->
                    <div class="flex flex-wrap gap-2 pt-1" id="statusChips">
                        <button type="button" data-status="" class="c-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-status="Pending" class="c-chip px-3 py-1.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Pending</button>
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
                    $status=$case['case_status'];
                    $statusClassMap = [
                        'Pending' => 'text-amber-700 bg-amber-50 border border-amber-200',
                        'Mediation' => 'text-purple-700 bg-purple-50 border border-purple-200',
                        'Resolved' => 'text-green-700 bg-green-50 border border-green-200',
                    ];
                    $statusClass = $statusClassMap[$status] ?? 'text-blue-700 bg-blue-50 border border-blue-200';
                    $filed = $case['Date_Filed'] ?? '';
                    $filedDateRaw = $filed ? date('Y-m-d', strtotime($filed)) : '';
                    $filedDisplay = $filed ? date('F j, Y', strtotime($filed)) : 'N/A';
                    $hearing = $case['Next_Hearing_Date'] ?? '';
                    $hearingDisplay = $hearing ? date('F j, Y', strtotime($hearing)) : 'N/A';
                    $caseTypeRaw = trim($case['case_type'] ?? '');
                    $caseTypeDisplay = $caseTypeRaw !== '' ? ucwords($caseTypeRaw) : 'Unspecified Case Type';
                    $lcType = strtolower($caseTypeRaw);
                    if($lcType === 'criminal case' || $lcType === 'criminal'){
                        $typeBorderClass = 'border-red-300 bg-red-50/60 ring-1 ring-red-100';
                    } elseif($lcType === 'civil case' || $lcType === 'civil') {
                        $typeBorderClass = 'border-gray-300 bg-gray-50/70 ring-1 ring-gray-200';
                    } else {
                        $typeBorderClass = 'border-gray-100 bg-white/80';
                    }
                ?>
                <div class="case-card group relative backdrop-blur rounded-xl border <?= $typeBorderClass ?> p-4 flex flex-col gap-3 hover:-translate-y-[3px] hover:shadow-md transition-all" data-status="<?= strtolower($status) ?>" data-date="<?= $filedDateRaw ?>" data-id-text="<?= htmlspecialchars($case['Case_ID']) ?>">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-mono tracking-wide text-gray-500"><?= htmlspecialchars($case['Case_ID']) ?></span>
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
                            <?= htmlspecialchars($status) ?>
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
            <a href="home-external.php" class="inline-flex items-center gap-2 px-4 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium transition"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        const chips=document.querySelectorAll('#statusChips .c-chip');
        const searchInput=document.getElementById('searchInput');
        const monthFilter=document.getElementById('monthFilter');
        const yearFilter=document.getElementById('yearFilter');
        const sortOrder=document.getElementById('sortOrder');
        const resetBtn=document.getElementById('resetFilters');
        const cards=[...document.querySelectorAll('.case-card')];
        const visibleCount=document.getElementById('visibleCount');
        let statusOverride='';
        function applyFilters(){
            const q=(searchInput.value||'').toLowerCase();
            const m=monthFilter.value; const y=yearFilter.value; const order=sortOrder.value; let shown=0;
            cards.forEach(c=>{
                const status=c.dataset.status||''; const idTxt=(c.dataset.idText||'').toLowerCase();
                const dateRaw=c.dataset.date||''; const title=c.querySelector('h3')?.textContent.toLowerCase()||'';
                let show=true;
                if(statusOverride){
                    const st = (status || '').toLowerCase();
                    if(statusOverride === 'resolved'){
                        // Match any status that contains 'resolved' (e.g. 'Mediation Resolved')
                        show = show && st.includes('resolved');
                    } else if (statusOverride === 'certificate to file action' || statusOverride === 'certificate' || statusOverride === 'cfa'){
                        // Match any status referencing certificate / file action
                        show = show && (st.includes('certificate') || st.includes('file action'));
                    } else if (statusOverride === 'conciliation') {
                        // match any status that contains 'conciliation' (includes variants like 'conciliation resolved')
                        show = show && st.includes('conciliation');
                    } else if (statusOverride === 'other'){
                        // legacy: other was anything not pending/mediation/resolved
                        show = show && !['pending','mediation'].includes(st) && !st.includes('resolved');
                    } else if (statusOverride === ''){
                        show = show && true;
                    } else {
                        // exact case-insensitive match
                        show = show && st === statusOverride;
                    }
                }
                if(q) show=show && (idTxt.includes(q)||title.includes(q));
                if((m||y)&&dateRaw){ const parts=dateRaw.split('-'); if(parts.length>=3){ const Y=parts[0]; const M=parts[1]; if(m) show=show && M===m; if(y) show=show && Y===y; } }
                c.style.display=show?'':'none'; if(show) shown++; });
            const container=document.getElementById('casesContainer');
            const sorted=cards.filter(c=>c.style.display!=='none').sort((a,b)=>{ const da=new Date(a.dataset.date); const db=new Date(b.dataset.date); return order==='asc'? da-db : db-da; });
            sorted.forEach(el=>container.appendChild(el));
            visibleCount.textContent='Showing '+shown+' item'+(shown===1?'':'s');
        }
        chips.forEach(ch=> ch.addEventListener('click',()=>{ chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow')); ch.classList.add('active','bg-primary-600','text-white','shadow'); statusOverride=(ch.dataset.status||'').toLowerCase(); applyFilters(); }));
        [searchInput,monthFilter,yearFilter,sortOrder].forEach(el=> el.addEventListener('input',applyFilters));
        monthFilter.addEventListener('change',applyFilters); yearFilter.addEventListener('change',applyFilters); sortOrder.addEventListener('change',applyFilters);
        resetBtn.addEventListener('click',()=>{ searchInput.value=''; monthFilter.value=''; yearFilter.value=''; sortOrder.value='desc'; statusOverride=''; chips.forEach((c,i)=>{ c.classList.remove('active','bg-primary-600','text-white','shadow'); if(i===0){ c.classList.add('active','bg-primary-600','text-white','shadow'); } }); applyFilters(); });
        applyFilters();
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
