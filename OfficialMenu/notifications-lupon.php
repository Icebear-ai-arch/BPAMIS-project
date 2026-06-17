<?php
include '../controllers/session_control.php';
include '../server/server.php';

if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

$luponId = $_SESSION['official_id'];

// Optional: fetch Lupon name for fallback matching
$luponName = '';
if ($stn = $conn->prepare("SELECT Name FROM barangay_officials WHERE Official_ID = ?")) {
        $stn->bind_param('i', $luponId);
        $stn->execute();
        $rn = bpamis_stmt_get_result($stn);
        if ($rn && $r = $rn->fetch_assoc()) { $luponName = trim((string)($r['Name'] ?? '')); }
        $stn->close();
}

// Fetch notifications only for this Lupon and of relevant types (primary by lupon_id, exclude trashed)
$sql = "SELECT n.* FROM notifications n
                LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                WHERE (n.lupon_id = ? OR n.official_id = ?) 
                    AND n.type IN ('Unverified', 'Hearing', 'Complaint', 'Case') 
                    AND t.notification_id IS NULL
                ORDER BY n.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // bind lupon id to both parameters (some notifications use official_id instead)
    $stmt->bind_param("ii", $luponId, $luponId);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    $stmt->close();
} else {
    // Fallback: log error and run safe direct query (luponId is from session; cast to int)
    error_log("notifications-lupon prepare failed: " . $conn->error);
    $luponIdInt = (int)$luponId;
    $fallbackSql = "SELECT n.* FROM notifications n
                     LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                     WHERE (n.lupon_id = $luponIdInt OR n.official_id = $luponIdInt) 
                       AND n.type IN ('Unverified','Hearing','Complaint','Case')
                       AND t.notification_id IS NULL
                     ORDER BY n.created_at DESC";
    $result = $conn->query($fallbackSql);
}

$notifications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Fallback: if no direct lupon_id matches and we have a name, try matching name in title or message (legacy rows)
if (empty($notifications) && $luponName !== '') {
    $sql2 = "SELECT * FROM notifications 
             WHERE type IN ('Unverified','Hearing','Complaint','Case')
               AND (title LIKE CONCAT('%', ?, '%') OR message LIKE CONCAT('%', ?, '%'))
             ORDER BY created_at DESC";
    if ($st2 = $conn->prepare($sql2)) {
        $st2->bind_param('ss', $luponName, $luponName);
        $st2->execute();
        $res2 = bpamis_stmt_get_result($st2);
        if ($res2 && $res2->num_rows > 0) {
            while ($row = $res2->fetch_assoc()) { $notifications[] = $row; }
        }
        $st2->close();
    } else {
        $n = $conn->real_escape_string($luponName);
        $fallback2 = "SELECT * FROM notifications WHERE type IN ('Unverified','Hearing','Complaint','Case') AND (title LIKE '%$n%' OR message LIKE '%$n%') ORDER BY created_at DESC";
        $res2 = $conn->query($fallback2);
        if ($res2 && $res2->num_rows > 0) {
            while ($row = $res2->fetch_assoc()) { $notifications[] = $row; }
        }
    }
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
    <!-- Use proper Font Awesome CSS so icons display reliably -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
        .unread-indicator {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #0c9ced;
            top: 22px;
            right: 22px;
        }
        
        /* Notification item styles */
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
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        
        /* Empty state animation */
        .empty-icon-container {
            animation: float 4s ease-in-out infinite;
        }
        /* Standardized card animation like secretary page */
        .s-notif-card {
            transition: transform .28s ease, opacity .28s ease, box-shadow .28s ease;
            will-change: transform, opacity;
        }
        .s-notif-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px -6px rgba(2, 129, 212, 0.18);
        }
        /* Slide-out on delete animation */
        .s-notif-card.slide-out-left {
            transform: translateX(-24px);
            opacity: 0;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .gradient-bg { padding: 1rem !important; }
            .s-notif-card { padding: 0.875rem !important; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once ('../includes/barangay_official_lupon_nav.php'); ?>
    <?php include_once ('../chatbot/bpamis_case_assistant.php'); ?>
    <!-- Premium Hero (aligned with secretary UI) -->
    <div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
        <div class="relative gradient-bg max-w-7xl mx-auto rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-6 md:p-8 lg:p-10 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-20 -mt-20 opacity-70 animate-float"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-10 -mb-10 opacity-60 animate-float"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6">
                <div class="max-w-2xl">
                    <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-light text-primary-800 tracking-tight">Lupon <span class="font-semibold">Notifications</span></h2>
                    <p class="mt-2 sm:mt-3 md:mt-4 text-xs sm:text-sm md:text-base text-gray-600 max-w-xl leading-relaxed">Monitor your assigned cases, hearings, and complaint updates. Use filters to quickly find what matters.</p>
                    <div class="mt-3 sm:mt-4 md:mt-5 flex flex-wrap gap-1.5 sm:gap-2 text-[10px] sm:text-xs text-primary-700/80 font-medium">
                        <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-bell text-primary-500"></i> <span class="hidden sm:inline">Real-time</span></span>
                        <span class="px-2 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white/70 border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-user-shield text-primary-500"></i> <span class="hidden sm:inline">Scoped to You</span></span>
                    </div>
                </div>
                <div class="hidden md:flex flex-col gap-3 min-w-[220px]">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">All</span><span id="heroAllCount" class="mt-1 text-lg font-semibold text-blue-700"><?php echo isset($notifications) ? count($notifications) : 0; ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">Unread</span><span id="heroUnreadCount" class="mt-1 text-lg font-semibold text-amber-700"><?php echo isset($notifications) ? count(array_filter($notifications, function($n){ return (int)($n['is_read'] ?? 0) === 0; })) : 0; ?></span></div>
                    </div>
                    <div class="text-[11px] text-primary-700/70 text-center">Overview</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Advanced Filters Card (aligned with secretary UI) -->
    <div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4">
        <div class="relative bg-white max-w-7xl mx-auto rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-5 md:p-6 lg:p-7 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
            <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
            <div class="relative z-10 space-y-3 sm:space-y-4 md:space-y-5">
                <div class="flex items-center justify-between gap-2 sm:gap-3">
                    <div class="flex items-center gap-2 sm:gap-3 text-primary-700/80 text-xs sm:text-sm font-medium">
                        <span class="inline-flex items-center gap-1.5 sm:gap-2 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500 text-xs sm:text-sm"></i> <span class="sm:inline">Refine Notifications</span></span>
                    </div>
                    <button id="markAllReadBtn" class="group inline-flex items-center gap-1 sm:gap-1.5 px-2 py-1.5 sm:px-4 sm:py-2.5 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white text-[10px] sm:text-xs font-semibold shadow-sm hover:shadow-md transition-all">
                        <i class="fa-solid fa-check-double text-xs sm:text-sm"></i>
                        <span class="sm:inline">Mark All Read</span>
                    </button>
                </div>
                <!-- Chips -->
                <div class="flex flex-wrap gap-1.5 sm:gap-2" id="notifChips">
                    <button type="button" data-filter="" class="s-chip active px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                    <button type="button" data-filter="unread" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Unread</button>
                    <button type="button" data-filter="hearing" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Hearings</button>
                    <button type="button" data-filter="complaint" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-cyan-50 text-cyan-600 border border-cyan-100 hover:bg-cyan-100 transition">Complaints</button>
                    <button type="button" data-filter="case" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Cases</button>
                    <button type="button" data-filter="unverified" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Unverified</button>
                    <button type="button" data-filter="assigned" class="s-chip px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-medium rounded-full bg-sky-50 text-sky-700 border border-sky-100 hover:bg-sky-100 transition">Assigned</button>
                </div>
                <!-- Controls -->
                <div class="space-y-3">
                    <!-- Search Field - Full Width Row -->
                    <div class="relative group">
                        <input id="searchInput" type="text" placeholder="Search notifications..." class="w-full pl-9 sm:pl-11 pr-3 sm:pr-4 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-xs sm:text-sm transition" />
                        <i class="fa-solid fa-search absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition text-xs sm:text-sm"></i>
                    </div>
                    
                    <!-- Month, Year, Sort, Reset - One Row -->
                    <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-12 pb-3 sm:pb-0">
                        <div class="relative md:col-span-3">
                            <select id="monthFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); $mv=str_pad((string)$m,2,'0',STR_PAD_LEFT); ?>
                                    <option value="<?= $mv ?>"><?= $mn ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                        </div>
                        <div class="relative md:col-span-3">
                            <select id="yearFilter" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Years</option>
                                <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                        </div>
                        <div class="relative md:col-span-4">
                            <select id="sortOrder" class="w-full pl-2 sm:pl-3 pr-7 sm:pr-8 py-2 sm:py-3 rounded-lg sm:rounded-xl border border-gray-200 bg-white/70 text-xs sm:text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="desc">Newest first</option>
                                <option value="asc">Oldest first</option>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 text-primary-400 text-xs sm:text-sm"></i>
                        </div>
                        <div class="flex md:col-span-2">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1 sm:gap-1.5 px-3 py-2 sm:px-4 sm:py-3 rounded-lg sm:rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-xs sm:text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List (refined cards) -->
    <div class="w-full mt-4 sm:mt-6 md:mt-8 px-3 sm:px-4 pb-10">
        <div class="relative bg-white max-w-7xl mx-auto rounded-xl sm:rounded-2xl shadow-sm p-4 sm:p-5 md:p-6 lg:p-7 overflow-hidden">
            <div id="notificationList" class="space-y-3 sm:space-y-4">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $row): ?>
                        <?php
                            // Choose icon and colors based on type
                            $icon = 'fa-bell';
                            $iconWrap = 'bg-gray-100 text-gray-600';
                            $rawType = $row['type'];
                            switch ($rawType) {
                                case 'Hearing':
                                    $icon = 'fa-calendar-alt';
                                    $iconWrap = 'bg-purple-50 text-purple-600';
                                    break;
                                case 'Case':
                                    $icon = 'fa-gavel';
                                    $iconWrap = 'bg-green-50 text-green-600';
                                    break;
                                case 'Complaint':
                                    $icon = 'fa-file-alt';
                                    $iconWrap = 'bg-cyan-50 text-cyan-600';
                                    break;
                                case 'Unverified':
                                    $icon = 'fa-user-shield';
                                    $iconWrap = 'bg-indigo-50 text-indigo-600';
                                    break;
                            }

                            $isUnread = ((int)($row['is_read'] ?? 0)) === 0;
                            $createdAtRaw = $row['created_at'] ?? '';
                            $createdDisp = $createdAtRaw ? date('M j, Y g:i A', strtotime($createdAtRaw)) : '';
                            $baseType = strtolower($rawType ?? '');
                            $searchStr = strtolower(trim(($row['title'] ?? '').' '.($row['message'] ?? '')));
                            $isAssigned = (stripos(($row['title'] ?? ''), 'assigned') !== false) || (stripos(($row['message'] ?? ''), 'assigned') !== false);
                            $notifId = isset($row['notification_id']) ? $row['notification_id'] : (isset($row['id']) ? $row['id'] : '');
                        ?>
                        <div class="s-notif-card relative group bg-white/90 backdrop-blur rounded-lg sm:rounded-xl border border-gray-100 p-3 sm:p-4 md:p-5 flex flex-col gap-2 sm:gap-3" data-type="<?= htmlspecialchars($baseType) ?>" data-base="<?= htmlspecialchars($baseType) ?>" data-date="<?= htmlspecialchars($createdAtRaw ? date('Y-m-d H:i:s', strtotime($createdAtRaw)) : '1970-01-01 00:00:00') ?>" data-unread="<?= $isUnread ? '1' : '0' ?>" data-search="<?= htmlspecialchars($searchStr) ?>" data-assigned="<?= $isAssigned ? '1' : '0' ?>">
                            <?php if ($isUnread): ?><span class="absolute top-2 sm:top-3 right-2 sm:right-3 inline-flex w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-amber-500 shadow animate-pulse-subtle"></span><?php endif; ?>
                            <div class="flex items-start gap-2 sm:gap-3 md:gap-4">
                                <div class="shrink-0 w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12 rounded-full flex items-center justify-center <?= $iconWrap ?> shadow-sm"><i class="fa-solid <?= $icon ?> text-xs sm:text-sm md:text-base"></i></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 sm:gap-2">
                                        <p class="text-xs sm:text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($row['title'] ?? 'Notification') ?></p>
                                        <?php if ($isAssigned): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.5 sm:px-2 rounded-full bg-sky-100 text-sky-700 text-[9px] sm:text-[10px] font-semibold">Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1"><?= htmlspecialchars($row['message'] ?? '') ?></p>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-[10px] sm:text-xs text-gray-500"><?= $createdDisp ?></span>
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <?php if(!empty($notifId)): ?>
                                                <a href="view_notification_lupon.php?id=<?= htmlspecialchars($notifId) ?>" class="text-primary-600 hover:text-primary-700 text-[10px] sm:text-xs font-medium">View</a>
                                                <button type="button" class="btn-delete-notif inline-flex items-center gap-1 px-1.5 py-0.5 sm:px-2 sm:py-1 rounded text-[10px] sm:text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 transition" title="Delete" data-id="<?= htmlspecialchars($notifId) ?>">
                                                    <i class="fa-solid fa-trash"></i>
                                                    <span class="hidden sm:inline">Delete</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="py-10 text-center text-gray-500 text-sm">No notifications found.</div>
                <?php endif; ?>
            </div>
            <div id="noResults" class="hidden mt-6 text-center text-gray-500 text-sm">No notifications match your filters.</div>
        </div>
    </div>

    <!-- Simple back CTA -->
    
    <div class="mt-6 flex justify-center">
        <button onclick="window.location.href='home-lupon.php'" class="px-4 py-2 text-gray-500 hover:text-gray-700 flex items-center transition-colors">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </button>
    </div>

    <!-- No notifications state (hidden by default) -->
    <div id="no-notifications" class="hidden w-full mt-10 px-4 pb-10">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
            <div class="flex justify-center mb-4">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center">
                    <i class="fas fa-bell-slash text-gray-300 text-3xl"></i>
                </div>
            </div>
            <h3 class="text-lg font-medium text-gray-700 mb-2">No notifications yet</h3>
            <p class="text-gray-500 max-w-md mx-auto">When you receive new notifications about your cases or complaints, they will appear here.</p>
            <div class="mt-6">
                <button onclick="window.location.href='home-lupon.php'" class="px-4 py-2 bg-primary-50 text-primary-600 rounded-lg text-sm font-medium hover:bg-primary-100 transition-colors">
                    Return to Dashboard
                </button>
            </div>
        </div>
    </div>
    <?php include 'sidebar_lupon.php';?>

    <script>
    document.addEventListener('DOMContentLoaded',()=>{
        const chips=document.querySelectorAll('#notifChips .s-chip');
        const searchInput=document.getElementById('searchInput');
        const monthFilter=document.getElementById('monthFilter');
        const yearFilter=document.getElementById('yearFilter');
        const sortOrder=document.getElementById('sortOrder');
        const resetBtn=document.getElementById('resetFilters');
        const list=document.getElementById('notificationList');
        const noResults=document.getElementById('noResults');
        let cards=[...document.querySelectorAll('.s-notif-card')];
        let filterOverride='';

        function updateHeroCounts(){
            const allCards = document.querySelectorAll('.s-notif-card');
            const unread = Array.from(allCards).filter(c=> c.dataset.unread==='1');
            const heroAll = document.getElementById('heroAllCount');
            const heroUnread = document.getElementById('heroUnreadCount');
            if (heroAll) heroAll.textContent = allCards.length.toString();
            if (heroUnread) heroUnread.textContent = unread.length.toString();
        }

        function applyFilters(){
            cards = [...document.querySelectorAll('.s-notif-card')];
            const q=(searchInput?.value||'').toLowerCase();
            const m=monthFilter?.value||''; const y=yearFilter?.value||''; let shown=0;
            cards.forEach(c=>{
                const type=c.dataset.type||''; const base=c.dataset.base||''; const unread=c.dataset.unread==='1'; const dateRaw=c.dataset.date||''; const text=(c.dataset.search||'').toLowerCase(); const assigned=c.dataset.assigned==='1';
                let show=true;
                if(filterOverride){
                    if(filterOverride==='unread') show=unread;
                    else if(filterOverride==='assigned') show=assigned;
                    else show = (type===filterOverride || base===filterOverride);
                }
                if(q) show = show && text.includes(q);
                if((m||y) && dateRaw && dateRaw!=='1970-01-01 00:00:00'){
                    const d=new Date(dateRaw.replace(' ','T'));
                    if(!isNaN(d.getTime())){
                        const M=('0'+(d.getMonth()+1)).slice(-2);
                        const Y=d.getFullYear().toString();
                        if(m) show=show && M===m; if(y) show=show && Y===y;
                    }
                }
                c.style.display=show?'':'none'; if(show) shown++;
            });
            const visible=cards.filter(c=>c.style.display!=='none').sort((a,b)=>{
                const da=new Date((a.dataset.date||'').replace(' ','T'));
                const db=new Date((b.dataset.date||'').replace(' ','T'));
                return (sortOrder?.value||'desc')==='asc' ? da-db : db-da;
            });
            visible.forEach(el=> list.appendChild(el));
            noResults?.classList.toggle('hidden', shown>0);
        }

        chips.forEach(ch=> ch.addEventListener('click',()=>{
            chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow'));
            ch.classList.add('active','bg-primary-600','text-white','shadow');
            filterOverride=(ch.dataset.filter||'');
            applyFilters();
        }));
        [searchInput,monthFilter,yearFilter,sortOrder].forEach(el=> el && el.addEventListener('input',applyFilters));
        monthFilter?.addEventListener('change',applyFilters); yearFilter?.addEventListener('change',applyFilters); sortOrder?.addEventListener('change',applyFilters);
        resetBtn?.addEventListener('click',()=>{
            if(searchInput) searchInput.value=''; if(monthFilter) monthFilter.value=''; if(yearFilter) yearFilter.value=''; if(sortOrder) sortOrder.value='desc';
            filterOverride=''; chips.forEach((c,i)=>{ c.classList.remove('active','bg-primary-600','text-white','shadow'); if(i===0){ c.classList.add('active','bg-primary-600','text-white','shadow'); } });
            applyFilters();
        });

        // Mark all read
        const markAll=document.getElementById('markAllReadBtn');
        markAll?.addEventListener('click', async ()=>{
            try{
                const res = await fetch('../controllers/mark_all_notifications_read.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({scope:'lupon'})});
                const data = await res.json();
                if(data && data.success){
                    document.querySelectorAll('.s-notif-card[data-unread="1"]').forEach(card=>{
                        card.dataset.unread='0';
                        const dot = card.querySelector('.animate-pulse-subtle');
                        if(dot) dot.remove();
                    });
                    updateHeroCounts();
                }
            }catch(e){ console.warn('Failed to mark all as read on server:', e); }
        });

        // Initial render
        updateHeroCounts();
        applyFilters();

        // Mobile menu toggle (if present)
        const menuButton=document.getElementById('mobile-menu-button');
        const mobileMenu=document.getElementById('mobile-menu');
        if(menuButton && mobileMenu){
            menuButton.addEventListener('click',function(){
                this.classList.toggle('active');
                mobileMenu.style.transform=(mobileMenu.style.transform==='translateY(0%)')? 'translateY(-100%)':'translateY(0%)';
            });
        }

        // Delete a single notification (delegated)
        list.addEventListener('click', async (e)=>{
            const btn = e.target.closest('.btn-delete-notif');
            if (!btn) return;
            const id = btn.getAttribute('data-id');
            if (!id) return;
            const card = btn.closest('.s-notif-card');
            const confirmed = window.confirm('Delete this notification? This action cannot be undone.');
            if (!confirmed) return;
            try {
                const res = await fetch('../controllers/delete_notification.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(id, 10) })
                });
                const data = await res.json();
                if (data && data.success) {
                    if (card) {
                        card.style.pointerEvents = 'none';
                        card.classList.add('slide-out-left');
                        let removed = false;
                        const finalizeRemoval = () => {
                            if (removed) return;
                            removed = true;
                            card.remove();
                            updateHeroCounts();
                            applyFilters();
                        };
                        const onEnd = () => {
                            card.removeEventListener('transitionend', onEnd);
                            finalizeRemoval();
                        };
                        card.addEventListener('transitionend', onEnd);
                        setTimeout(finalizeRemoval, 450);
                    }
                } else {
                    alert('Failed to delete notification.');
                }
            } catch (err) {
                console.warn('Delete failed', err);
                alert('An error occurred while deleting.');
            }
        });
    });
    </script>
</body>
</html>


