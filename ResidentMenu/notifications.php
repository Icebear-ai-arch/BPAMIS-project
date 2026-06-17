<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../bpamis_website/login.php");
    exit();
}

$resident_id = $_SESSION['user_id'];
$today = new DateTime();

// Resolve actual table names for Linux/case-sensitive hosts
$T_CASE_INFO = bpamis_table($conn, 'case_info');
$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$T_RESOLUTION = bpamis_table($conn, 'resolution');
$T_COMPLAINT_RESPONDENTS = bpamis_table($conn, 'complaint_respondents');
$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
$T_NOTIFICATIONS_TRASH = bpamis_table($conn, 'notifications_trash');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_COMPLAINT_RESPONDENTS = bpamis_quote_table($T_COMPLAINT_RESPONDENTS);
$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
$TB_NOTIFICATIONS_TRASH = bpamis_quote_table($T_NOTIFICATIONS_TRASH);

$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID', 'respondent_id']);

$hasComplaintRespondents = false;
$crCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($T_COMPLAINT_RESPONDENTS) . "'");
if ($crCheck && $crCheck->num_rows > 0) {
    $hasComplaintRespondents = true;
}

$hasNotificationsTrash = false;
$trashCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($T_NOTIFICATIONS_TRASH) . "'");
if ($trashCheck && $trashCheck->num_rows > 0) {
    $hasNotificationsTrash = true;
}

// ✅ Step 1: Get all cases with approaching resolution deadline
$checkQuery = $conn->prepare("
    SELECT 
        c.Case_ID, 
        r.Deadline AS Resolution_Deadline, 
        c.Case_Status, 
        ci.resident_id AS complainant_id
    FROM {$TB_CASE_INFO} c
    INNER JOIN {$TB_COMPLAINT_INFO} ci ON c.Complaint_ID = ci.Complaint_ID
    INNER JOIN {$TB_RESOLUTION} r ON r.Case_ID = c.Case_ID
    WHERE c.Case_Status != 'Resolved'
      AND r.Deadline IS NOT NULL
");
$checkQuery->execute();
$caseRows = bpamis_stmt_fetch_all_assoc($checkQuery);

foreach ($caseRows as $case) {
    $deadline = new DateTime($case['Resolution_Deadline']);
    $interval = $today->diff($deadline)->days;
    $isUpcoming = ($deadline > $today && $interval <= 10);

    if ($isUpcoming) {
        $parties = [];

        // ✅ Add complainant
        if ($case['complainant_id']) {
            $parties[] = $case['complainant_id'];
        }

        // ✅ Add all respondents from complaint_respondents table (if available)
        if ($hasComplaintRespondents) {
            $respQuery = $conn->prepare("
                SELECT respondent_id 
                FROM {$TB_COMPLAINT_RESPONDENTS}
                WHERE complaint_id = (
                    SELECT Complaint_ID FROM {$TB_CASE_INFO} WHERE Case_ID = ?
                )
            ");
            $respQuery->bind_param("i", $case['Case_ID']);
            $respQuery->execute();
            $respRows = bpamis_stmt_fetch_all_assoc($respQuery);
            foreach ($respRows as $resp) {
                if (!empty($resp['respondent_id'])) {
                    $parties[] = $resp['respondent_id'];
                }
            }
            $respQuery->close();
        }

        // ✅ Send notification to each party
        foreach ($parties as $partyId) {
            // Skip if null
            if (!$partyId) continue;

            // Check if notification already exists
            $notifCheck = $conn->prepare("
                SELECT 1 FROM notifications 
                WHERE resident_id = ? 
                AND type = 'case' 
                AND title = 'Resolution Deadline Approaching' 
                AND message LIKE ?
                LIMIT 1
            ");
            $pattern = "%Case ID: {$case['Case_ID']}%";
            $notifCheck->bind_param("is", $partyId, $pattern);
            $notifCheck->execute();
            $notifExists = count(bpamis_stmt_fetch_all_assoc($notifCheck)) > 0;
            $notifCheck->close();

            if (!$notifExists) {
                $dt = new DateTime('now', new DateTimeZone('Asia/Manila')); 
                $notifTitle = "Resolution Deadline Approaching";
                $notifMessage = "Case ID: {$case['Case_ID']} is nearing its resolution deadline. You can now move this case to 'Arbitration' status if both parties agree.";
                $notifType = "case";
                $createdAt = $dt->format('Y-m-d H:i:s'); 

                $insertNotif = $conn->prepare("
                    INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, type, is_read, created_at)
                    VALUES (?, ?, ?, ?, 0, ?)
                ");
                $insertNotif->bind_param("issss", $partyId, $notifTitle, $notifMessage, $notifType, $createdAt);
                $insertNotif->execute();
                $insertNotif->close();
            }
        }
    }
}

// ✅ Step 1.5: Ensure resident is notified when they are named as respondent (main or additional)
try {
    $whereParts = [];
    $types = '';
    $params = [];

    if ($respondentIdCol) {
        $whereParts[] = 'ci.' . bpamis_quote_ident($respondentIdCol) . ' = ?';
        $types .= 'i';
        $params[] = $resident_id;
    }
    if ($hasComplaintRespondents) {
        $whereParts[] = 'cr.Respondent_ID = ?';
        $types .= 'i';
        $params[] = $resident_id;
    }

    if (!empty($whereParts)) {
        $joinCr = $hasComplaintRespondents ? "LEFT JOIN {$TB_COMPLAINT_RESPONDENTS} cr ON ci.Complaint_ID = cr.Complaint_ID" : '';
        $respCasesSql = "SELECT DISTINCT c.Case_ID
            FROM {$TB_CASE_INFO} c
            JOIN {$TB_COMPLAINT_INFO} ci ON c.Complaint_ID = ci.Complaint_ID
            {$joinCr}
            WHERE " . implode(' OR ', $whereParts);

        $respCasesStmt = $conn->prepare($respCasesSql);
    } else {
        $respCasesStmt = null;
    }

    if ($respCasesStmt) {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
        call_user_func_array([$respCasesStmt, 'bind_param'], $bind);
        $respCasesStmt->execute();
        $respRows = bpamis_stmt_fetch_all_assoc($respCasesStmt);
        foreach ($respRows as $rCase) {
            $caseId = (int)$rCase['Case_ID'];

            // Avoid duplicate respondent notices for the same case
            $notifCheck = $conn->prepare(
                "SELECT 1 FROM notifications WHERE resident_id = ? AND title = 'Barangay Notice: You Have Been Named as Respondent' AND message LIKE ? LIMIT 1"
            );
            if ($notifCheck) {
                $pattern = "%Case ID: {$caseId}%";
                $notifCheck->bind_param('is', $resident_id, $pattern);
                $notifCheck->execute();
                $exists = count(bpamis_stmt_fetch_all_assoc($notifCheck)) > 0;
                if (!$exists) {
                    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
                    $title = 'Barangay Notice: You Have Been Named as Respondent';
                    $message = "Case ID: {$caseId} - Please review the case details and respond as necessary.";
                    $type = 'case';
                    $createdAt = $dt->format('Y-m-d H:i:s');

                    $insert = $conn->prepare(
                        "INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)"
                    );
                    if ($insert) {
                        $insert->bind_param('issss', $resident_id, $title, $message, $type, $createdAt);
                        $insert->execute();
                        $insert->close();
                    }
                }
                $notifCheck->close();
            }
        }
        $respCasesStmt->close();
    }
} catch (Throwable $e) {
    // don't block notifications page if this secondary check fails
}

// ✅ Fetch notifications for the currently logged-in resident (exclude trashed)
$sql = $hasNotificationsTrash
    ? "SELECT n.* FROM {$TB_NOTIFICATIONS} n
        LEFT JOIN {$TB_NOTIFICATIONS_TRASH} t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
            WHERE n.resident_id = ?
                AND t.notification_id IS NULL
                AND LOWER(COALESCE(n.title, '')) <> 'barangay notice: you have been named as respondent'
        ORDER BY n.created_at DESC"
    : "SELECT n.* FROM {$TB_NOTIFICATIONS} n
        WHERE n.resident_id = ?
            AND LOWER(COALESCE(n.title, '')) <> 'barangay notice: you have been named as respondent'
        ORDER BY n.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $resident_id);
$stmt->execute();
$notifications = bpamis_stmt_fetch_all_assoc($stmt);
$stmt->close();

// Prepare counts for hero metrics
$allCount = count($notifications);
$unreadCount = 0; $hearingCount = 0; $complaintCount = 0; $caseCount = 0;
foreach ($notifications as $n) {
    if(($n['is_read'] ?? 1) == 0) $unreadCount++;
    $t = strtolower($n['type'] ?? '');
    if($t === 'hearing') $hearingCount++; elseif($t === 'complaint') $complaintCount++; elseif($t === 'case') $caseCount++; 
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
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
                        'pulse-subtle': 'pulse-subtle 2s infinite',
                        'bell-ring': 'bell-ring 1s ease-in-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'pulse-subtle': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.8 }
                        },
                        'bell-ring': {
                            '0%, 100%': { transform: 'rotate(0)' },
                            '20%, 60%': { transform: 'rotate(8deg)' },
                            '40%, 80%': { transform: 'rotate(-8deg)' }
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
        .notification-card {
            transition: all 0.2s ease;
        }
        .notification-card:hover {
            background-color: #f9fafc;
        }
        /* Slide-out on delete animation */
        .notif-card.slide-out-left {
            transform: translateX(-100%);
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }
        .unread-indicator {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #0c9ced;
            top: 22px;
            right: 22px;
        }
        
       
        .notification-item {
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-dot {
            transition: all 0.2s ease;
        }
        .notification-item:hover .notification-dot {
            transform: scale(1.2);
        }
        /* Smooth collapse for deleted notifications */
        .notif-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .notif-card.collapsing {
            overflow: hidden;
        }
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        
        /* Empty state animation */
        .empty-icon-container {
            animation: float 4s ease-in-out infinite;
        }
        
        

        /* Compact mobile adjustments for the Advanced Filters card */
        @media (max-width: 640px) {
            .advanced-filters-card {
                padding: 0.5rem !important;
                border-radius: 0.6rem !important;
            }

            .advanced-filters-card .relative.z-10 {
                gap: 0.5rem !important;
            }

            .advanced-filters-card .flex-row {
                gap: 0.5rem !important;
            }

            .advanced-filters-card .n-chip {
                padding: 0.35rem 0.6rem !important;
                font-size: 0.72rem !important;
                border-radius: 9999px !important;
            }

            .advanced-filters-card #searchInput {
                padding: 0.5rem 2.5rem 0.5rem 2.5rem !important;
                font-size: 0.9rem !important;
            }

            .advanced-filters-card select {
                padding: 0.45rem 2rem 0.45rem 0.75rem !important;
                font-size: 0.9rem !important;
            }

            .advanced-filters-card .grid {
                gap: 0.5rem !important;
            }

            .advanced-filters-card .flex-1.min-w-0 {
                min-width: 0 !important;
            }
            .advanced-filters-card .px-3\.py-1\.5 {
                padding: 0.35rem 0.6rem !important;
                font-size: 0.72rem !important;
            }

            /* Reduce vertical spacing inside chip / controls area */
            .advanced-filters-card .space-y-6 { gap: 0.45rem !important; }
        }

            /* Mobile: add extra top gap above the hero and compact the small stats */
            @media (max-width: 640px) {
                /* add extra spacing above hero to create breathing room on small screens */
                #heroSection { margin-top: 5rem !important; }

                /* compact stat cards (All / Unread) */
                .stat-card {
                    padding: 0.35rem 0.6rem !important;
                    border-radius: 0.6rem !important;
                    min-width: 72px !important;
                }

                /* target the label (first span) inside the stat card */
                .stat-card span:first-child {
                    font-size: 10px !important;
                    line-height: 1 !important;
                }

                .stat-count {
                    font-size: 1rem !important;
                    margin-top: 0.2rem !important;
                    line-height: 1 !important;
                }
            }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once('../includes/resident_nav.php'); ?>
    <!-- Global Blue Blush Orbs Background -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <!-- Premium Hero -->
    <div id="heroSection" class="w-full mt-16 px-4">
    <div class="relative gradient-bg rounded-2xl shadow-sm p-4 md:p-6 overflow-hidden max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-24 -mt-24 opacity-70 animate-[float_8s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-14 -mb-14 opacity-60 animate-[float_6s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-xl md:text-2xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Notifications</span></h1>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed hidden sm:block">Track case updates, complaint actions, and upcoming hearings in one consolidated stream. Use smart filters to narrow what you see.</p>
                    
                </div>
                    <div class="flex flex-col gap-2 min-w-[200px]">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-blue-100 shadow-sm stat-card"><span class="text-[9px] uppercase tracking-wide text-blue-600 font-semibold">All</span><span class="mt-1 text-base font-semibold text-blue-700 stat-count"><?= $allCount ?></span></div>
                            <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-2 py-2 border border-amber-100 shadow-sm stat-card"><span class="text-[9px] uppercase tracking-wide text-amber-600 font-semibold">Unread</span><span class="mt-1 text-base font-semibold text-amber-700 stat-count"><?= $unreadCount ?></span></div>
                        </div>
                        <div class="text-[11px] text-primary-700/70 text-center">Overview summary</div>
                    </div>
            </div>
        </div>
    </div>
    <!-- Advanced Filters Card -->
    <div class="w-full mt-4 px-4">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden advanced-filters-card">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-6">
                    <div class="flex flex-row flex-nowrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-0 flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                            <span class="truncate inline-flex items-center gap-2 px-3 py-2 h-10 rounded-lg bg-primary-50/70 border border-primary-100 leading-none"><i class="fa-solid fa-sliders text-primary-500 text-base"></i> Filter Options</span>
                        </div>
                        <form method="POST" action="mark_all_read.php" class="flex-shrink-0 flex items-center gap-2">
                            <button type="submit" class="group relative inline-flex items-center gap-2 px-3 py-2 h-10 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold shadow-sm hover:shadow-md transition-all leading-none">
                                <i class="fa-solid fa-check-double text-base"></i>
                                <span class="whitespace-nowrap">Mark All Read</span>
                            </button>
                        </form>
                    </div>
                    <!-- Status / Type Chips -->
                    <div class="flex flex-wrap gap-2" id="notifChips">
                        <button type="button" data-filter="" class="n-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-filter="unread" class="n-chip px-3 py-1.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Unread</button>
                        <button type="button" data-filter="hearing" class="n-chip px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Hearings</button>
                        <button type="button" data-filter="complaint" class="n-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Complaints</button>
                        <button type="button" data-filter="case" class="n-chip px-3 py-1.5 text-xs font-medium rounded-full bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition">Cases</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-5 relative group">
                            <input id="searchInput" type="text" placeholder="Search notifications..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
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

<!-- Notifications Grid -->
<div id="notificationSection" class="w-full mt-4 px-4 pb-20">
    <div class="max-w-screen-2xl mx-auto">
    <div id="notificationGrid" class="grid grid-cols-1 gap-4">
            <?php if(!empty($notifications)): foreach($notifications as $notif): 
                $icon='fa-bell'; $iconWrap='bg-gray-100 text-gray-600';
                $type=strtolower($notif['type']);
                switch($type){
                    case 'hearing': $icon='fa-calendar-alt'; $iconWrap='bg-purple-50 text-purple-600'; break;
                    case 'complaint': $icon='fa-file-alt'; $iconWrap='bg-green-50 text-green-600'; break;
                    case 'case': $icon='fa-gavel'; $iconWrap='bg-blue-50 text-blue-600'; break;
                }
                $isUnread = ($notif['is_read'] ?? 1)==0; $createdRaw=$notif['created_at']; $createdDisp=date('M j, Y g:i A', strtotime($createdRaw));
            ?>
            <div class="notif-card relative group bg-white/85 backdrop-blur rounded-xl border border-gray-100 p-4 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" data-type="<?= $type ?>" data-date="<?= date('Y-m-d H:i:s', strtotime($createdRaw)) ?>" data-unread="<?= $isUnread? '1':'0' ?>" data-search="<?= htmlspecialchars(strtolower($notif['title'].' '.$notif['message'])) ?>">
                <?php if($isUnread): ?><span class="absolute top-3 right-3 inline-flex w-2.5 h-2.5 rounded-full bg-amber-500 shadow animate-pulse-subtle"></span><?php endif; ?>
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-11 h-11 rounded-full flex items-center justify-center <?= $iconWrap ?> shadow-sm"><i class="fa-solid <?= $icon ?> text-base"></i></div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-800 leading-snug line-clamp-2" title="<?= htmlspecialchars($notif['title']) ?>"><?= htmlspecialchars($notif['title']) ?></h3>
                        <p class="mt-1 text-xs text-gray-600 line-clamp-3" title="<?= htmlspecialchars($notif['message']) ?>"><?= htmlspecialchars($notif['message']) ?></p>
                    </div>
                </div>
                <div class="mt-auto flex items-center justify-between pt-1">
                    <span class="text-[11px] text-gray-500 font-medium flex items-center gap-1"><i class="fa-regular fa-clock"></i> <?= $createdDisp ?></span>
                    <div class="flex gap-2">
                        <a href="view_notification.php?id=<?= $notif['notification_id'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-primary-50 text-primary-600 text-[11px] font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-eye"></i> View</a>
                        <?php if($isUnread): ?>
                        <form method="POST" action="mark_read.php" class="inline">
                            <input type="hidden" name="notif_id" value="<?= $notif['notification_id'] ?>" />
                            <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-medium hover:bg-amber-100 transition"><i class="fa-solid fa-circle-check"></i> Read</button>
                        </form>
                        <?php endif; ?>
                        <button type="button" class="btn-delete-notif inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-red-50 text-red-600 text-[11px] font-medium hover:bg-red-100 transition" data-id="<?= $notif['notification_id'] ?>" data-unread="<?= $isUnread ? '1' : '0' ?>">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="col-span-full py-12 text-center text-gray-500 text-sm">No notifications yet.</div>
            <?php endif; ?>
        </div>
        <div id="noResults" class="hidden col-span-full mt-8 text-center text-gray-500 text-sm">No notifications match your filters.</div>
        <div class="mt-8 flex justify-center">
            <a href="home-resident.php" class="inline-flex items-center gap-2 px-4 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium transition"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
    const chips=document.querySelectorAll('#notifChips .n-chip');
    const searchInput=document.getElementById('searchInput');
    const monthFilter=document.getElementById('monthFilter');
    const yearFilter=document.getElementById('yearFilter');
    const sortOrder=document.getElementById('sortOrder');
    const resetBtn=document.getElementById('resetFilters');
    const cards=[...document.querySelectorAll('.notif-card')];
    const noResults=document.getElementById('noResults');
    let filterOverride='';
    function applyFilters(){
        const q=(searchInput.value||'').toLowerCase(); const m=monthFilter.value; const y=yearFilter.value; const order=sortOrder.value; let shown=0;
        cards.forEach(c=>{
            const type=c.dataset.type||''; const unread=c.dataset.unread==='1'; const dateRaw=c.dataset.date||''; const text=c.dataset.search||''; let show=true;
            if(filterOverride){
                if(filterOverride==='unread') show=unread; else show=type===filterOverride;
            }
            if(q) show=show && text.includes(q);
            if((m||y)&&dateRaw){ const d=new Date(dateRaw); const M=('0'+(d.getMonth()+1)).slice(-2); const Y=d.getFullYear().toString(); if(m) show=show && M===m; if(y) show=show && Y===y; }
            c.style.display=show?'':'none'; if(show) shown++; });
        // sort
        const grid=document.getElementById('notificationGrid');
        const visible=cards.filter(c=>c.style.display!=='none').sort((a,b)=>{ const da=new Date(a.dataset.date); const db=new Date(b.dataset.date); return sortOrder.value==='asc'? da-db : db-da; });
        visible.forEach(el=>grid.appendChild(el));
        noResults.classList.toggle('hidden', shown>0);
    }
    chips.forEach(ch=> ch.addEventListener('click',()=>{ chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow')); ch.classList.add('active','bg-primary-600','text-white','shadow'); filterOverride=(ch.dataset.filter||''); applyFilters(); }));
    [searchInput,monthFilter,yearFilter,sortOrder].forEach(el=> el.addEventListener('input',applyFilters));
    monthFilter.addEventListener('change',applyFilters); yearFilter.addEventListener('change',applyFilters); sortOrder.addEventListener('change',applyFilters);
    resetBtn.addEventListener('click',()=>{ searchInput.value=''; monthFilter.value=''; yearFilter.value=''; sortOrder.value='desc'; filterOverride=''; chips.forEach((c,i)=>{ c.classList.remove('active','bg-primary-600','text-white','shadow'); if(i===0){ c.classList.add('active','bg-primary-600','text-white','shadow'); } }); applyFilters(); });
    applyFilters();

    // Delete notification handler  
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-delete-notif');
        if (!btn) return;
        
        const notifId = btn.dataset.id;
        if (!notifId) return;
        
        const confirmed = window.confirm('Delete this notification? This action cannot be undone.');
        if (!confirmed) return;
        
        const card = btn.closest('.notif-card');
        const wasUnread = btn.dataset.unread === '1';
        
        try {
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            
            const res = await fetch('../controllers/delete_notification.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify({id: notifId})
            });
            
            if (!res.ok) throw new Error('Network error: ' + res.status);
            
            const ct = (res.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
                const text = await res.text();
                throw new Error('Expected JSON, got: ' + text.slice(0,100));
            }
            
            const data = await res.json();
            if (!data || !data.success) {
                const details = (data?.message || data?.error || '');
                throw new Error(details || 'Server error');
            }
            
            // Animate removal
                if (card) {
                    // Collapse animation: freeze height then animate height/margin/padding to 0
                    card.classList.add('collapsing');

                    // Ensure we have an explicit height to animate from
                    const startHeight = card.offsetHeight;
                    card.style.height = startHeight + 'px';
                    card.style.overflow = 'hidden';

                    // Apply transition (inline to avoid specificity conflicts)
                    card.style.transition = 'height 320ms ease, margin 320ms ease, padding 320ms ease, opacity 320ms ease, transform 320ms ease';

                    // Force reflow so the browser registers the starting height
                    // eslint-disable-next-line no-unused-expressions
                    card.offsetHeight;

                    // Target end-state: explicitly remove top/bottom spacing so no gap remains
                    card.style.height = '0px';
                    card.style.marginTop = '0px';
                    card.style.marginBottom = '0px';
                    card.style.paddingTop = '0px';
                    card.style.paddingBottom = '0px';
                    card.style.opacity = '0';
                    card.style.minHeight = '0px';
                    card.style.boxSizing = 'border-box';
                    card.style.transform = 'none';

                    // After transition completes, remove element and update counts
                    setTimeout(() => {
                        card.remove();
                        // Update navbar badge if notification was unread
                        if (wasUnread) {
                            if (typeof window.updateResidentBadge === 'function') {
                                const badge = document.getElementById('notif-count-badge');
                                const currentCount = parseInt(badge?.textContent || '0') || 0;
                                const newCount = Math.max(0, currentCount - 1);
                                window.updateResidentBadge(newCount);
                            } else {
                                const badge = document.getElementById('notif-count-badge');
                                if (badge) {
                                    const currentCount = parseInt(badge.textContent || '0') || 0;
                                    const newCount = Math.max(0, currentCount - 1);
                                    badge.textContent = newCount;
                                    if (newCount === 0) badge.classList.add('hidden'); else badge.classList.remove('hidden');
                                }
                            }
                        }
                        // Re-apply filters after removal so grid reflows properly
                        applyFilters();
                    }, 340);
                }
            
        } catch (err) {
            console.warn('Delete failed:', err);
            alert('Failed to delete notification: ' + (err.message || err));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
        }
    });
});
</script>
    
    <div class="relative">
        <?php include('../chatbot/bpamis_case_assistant.php'); ?>
    </div>
    
</body>
</html>
