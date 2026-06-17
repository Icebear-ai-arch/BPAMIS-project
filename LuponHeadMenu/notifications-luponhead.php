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
                    AND n.type IN ('Unverified', 'Hearing', 'Complaint', 'Case', 'Resolution', 'Conciliation', 'Arbitration') 
                    AND t.notification_id IS NULL
                ORDER BY n.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
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
                       AND n.type IN ('Unverified','Hearing','Complaint','Case','Resolution')
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
                         WHERE type IN ('Unverified','Hearing','Complaint','Case','Resolution')
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
        $fallback2 = "SELECT * FROM notifications WHERE type IN ('Unverified','Hearing','Complaint','Case','Resolution') AND (title LIKE '%$n%' OR message LIKE '%$n%') ORDER BY created_at DESC";
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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <title>Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
        
        /* Mobile / small-screen hero & filter adjustments */
        @media (max-width:640px) {
            /* Make hero tighter on mobile */
            .gradient-bg { padding: 0.6rem !important; }
            /* Reduce outer top margin for notification sections on mobile (hero, filters, list) */
            .w-full.mt-6, .w-full.mt-8 { margin-top: 0.3rem !important; }
            .gradient-bg .relative.z-10 { gap: 6px; }
            .gradient-bg h2 { font-size: 1.05rem !important; }
            .gradient-bg h2 span.font-semibold { font-size: 1.05rem !important; }
            .gradient-bg p { font-size: 0.86rem !important; line-height: 1.2 !important; margin-top: 0.25rem; }

            /* Counters */
            #heroAllCount, #heroUnreadCount { font-size: 0.85rem !important; }
            .max-w-2xl { max-width: 100% !important; }
            /* Allow the right column to shrink more on small screens */
            .min-w-\[220px\] { min-width: 0 !important; }

            /* On mobile hide the counters grid but keep the small "Overview" text visible */
            .min-w-\[220px\] .grid.grid-cols-2 { display: none !important; }
            .min-w-\[220px\] .text-\[11px\] { display: none !important; text-align: center; }

            /* Filters card padding & control sizes */
            .relative.bg-white { padding: 0.65rem !important; }
            
            /* Move Mark All Read button beside Refine Notifications on mobile */
            .flex.flex-col.md\:flex-row.md\:items-center.md\:justify-between.gap-4 {
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.5rem !important;
            }
            
            .flex.flex-col.md\:flex-row.md\:items-center.md\:justify-between.gap-4 .flex.items-center.gap-3 {
                flex: 0 1 auto !important;
            }
            
            #markAllReadBtn {
                flex-shrink: 0 !important;
                white-space: nowrap !important;
            }
            
            #searchInput, #monthFilter, #yearFilter, #sortOrder { font-size: 0.82rem !important; padding: 0.45rem 0.6rem !important; }
            /* Ensure the search input keeps enough left padding so the placeholder clears the search icon */
            #searchInput { padding-left: 2.75rem !important; }
            .s-chip { font-size: 0.72rem !important; padding: 0.28rem 0.5rem !important; }
            #markAllReadBtn { padding: 0.36rem 0.5rem !important; font-size: 0.72rem !important; }
            .grid.grid-cols-2 [class*="text-[10px]"] {font-size: 8px !important;}

            /* Make overview counters smaller and tighter on mobile */
            #heroAllCount, #heroUnreadCount { font-size: 0.82rem !important; }
            .grid.grid-cols-2 > div { padding: 0.35rem !important; }

            /* Chips row: compress chips to fit into one row (no horizontal scroll) */
            .flex.flex-wrap.gap-2 { gap: 0.2rem; }
            #notifChips { 
                -webkit-overflow-scrolling: touch; 
                overflow-x: auto; 
                white-space: nowrap; 
                display: flex; 
                gap: 0.25rem !important;
                flex-wrap: nowrap !important;
                padding-bottom: 0.2rem;
            }
            #notifChips .s-chip { 
                display: inline-flex; 
                white-space: nowrap; 
                padding: 0.3rem 0.5rem !important; 
                font-size: 0.65rem !important;
                flex-shrink: 0;
                margin: 0 !important;
            }
            /* Ensure Mark All Read sits at the far right */
            .flex.flex-col.md\:flex-row.md\:items-center.md\:justify-between { display:flex; align-items:center; justify-content:space-between; }

            /* Layout tweak: put the search input above the compact controls (month/year/sort/reset) */
            .grid.grid-cols-1.md\:grid-cols-12 { 
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* Search input takes full width on first row */
            .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-5 { 
                grid-column: 1 / -1 !important;
            }
            
            /* Create compact row for month/year/sort/reset - they share the second row */
            .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-2:nth-child(2) {
                grid-column: 1 / 2 !important;
                grid-row: 2 !important;
            }
            
            .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-2:nth-child(3) {
                grid-column: 2 / 3 !important;
                grid-row: 2 !important;
            }
            
            .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-2:nth-child(4) {
                grid-column: 3 / 4 !important;
                grid-row: 2 !important;
            }
            
            .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-1:nth-child(5) {
                grid-column: 4 / 5 !important;
                grid-row: 2 !important;
            }
            
            /* Adjust grid to 4 columns for the filter controls row */
            .grid.grid-cols-1.md\:grid-cols-12 {
                grid-template-columns: repeat(4, 1fr) !important;
            }

            /* Make all filter controls equal width and compact */
            #monthFilter, #yearFilter, #sortOrder, #resetFilters { 
                font-size: 0.72rem !important; 
                padding: 0.4rem 0.3rem !important;
                width: 100% !important;
            }
            
            /* Ensure reset button matches width of dropdowns */
            #resetFilters {
                padding: 0.4rem 0.3rem !important;
                font-size: 0.72rem !important;
            }
            
            /* Compress dropdown carets */
            .grid.grid-cols-1.md\:grid-cols-12 .fa-caret-down {
                right: 0.3rem !important;
                font-size: 0.65rem !important;
            }

            /* Add left padding for the search input so the placeholder/text doesn't sit flush */
            #searchInput { padding-left: 0.9rem !important; }

            /* Slightly tone down decorative absolute elements so hero feels lighter */
            .gradient-bg > .absolute { transform: scale(0.86); opacity: 0.75; }

            /* Notification Cards */
            .s-notif-card {
                padding: 0.75rem !important;
                gap: 0.5rem !important;
            }

            /* Icon container */
            .s-notif-card .shrink-0.w-12 {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }

            .s-notif-card .shrink-0.w-12 i {
                font-size: 0.7rem !important;
            }

            /* Unread indicator dot */
            .s-notif-card .absolute.top-3.right-3 {
                top: 0.5rem !important;
                right: 0.5rem !important;
                width: 0.5rem !important;
                height: 0.5rem !important;
            }

            /* Title */
            .s-notif-card .text-sm.font-medium {
                font-size: 0.75rem !important;
                line-height: 1.2 !important;
            }

            /* Type badges */
            .s-notif-card .px-2.py-0\.5 {
                padding: 0.2rem 0.4rem !important;
                font-size: 8px !important;
            }

            /* Message text */
            .s-notif-card p.text-sm {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
                margin-top: 0.35rem !important;
            }

            /* DateTime */
            .s-notif-card .text-xs {
                font-size: 9px !important;
            }

            /* Action buttons */
            .s-notif-card .text-xs.font-medium {
                padding: 0.35rem 0.5rem !important;
                font-size: 9px !important;
            }

            .s-notif-card button i,
            .s-notif-card a i {
                font-size: 8px !important;
            }

            /* Gap between icon and content */
            .s-notif-card .flex.items-start.gap-4 {
                gap: 0.65rem !important;
            }

            /* Gap between title elements */
            .s-notif-card .flex.items-center.gap-2 {
                gap: 0.35rem !important;
                margin-bottom: 0.25rem !important;
            }

            /* Bottom action row */
            .s-notif-card .mt-2.flex.items-center.justify-between {
                margin-top: 0.5rem !important;
                gap: 0.5rem !important;
                flex-direction: column !important;
                align-items: flex-start !important;
            }

            /* Make action buttons full width on mobile */
            .s-notif-card .flex.items-center.gap-3 {
                width: 100%;
                gap: 0.4rem !important;
            }

            .s-notif-card .flex.items-center.gap-3 a,
            .s-notif-card .flex.items-center.gap-3 button {
                flex: 1;
                justify-content: center;
            }

            /* Space between notification cards */
            #notificationList {
                gap: 0.65rem !important;
            }
            
            /* Premium Back Button Mobile Styles */
            .w-full.mt-8.px-4.pb-8 {
                margin-top: 1rem !important;
                padding-bottom: 1.5rem !important;
            }
            
            .w-full.mt-8.px-4.pb-8 button {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0.85rem 1.2rem !important;
                font-size: 0.82rem !important;
                justify-content: center !important;
            }
            
            .w-full.mt-8.px-4.pb-8 button i {
                font-size: 0.75rem !important;
            }
        }

        @media (max-width:380px) {
            .gradient-bg { padding: 0.45rem !important; }
            .gradient-bg h2 { font-size: 0.98rem !important; }
            .gradient-bg p { font-size: 0.78rem !important; }
            #heroAllCount, #heroUnreadCount { font-size: 0.78rem !important; }
            .relative.bg-white { padding: 0.45rem !important; }
            #searchInput, #monthFilter, #yearFilter, #sortOrder { font-size: 0.72rem !important; padding: 0.36rem 0.5rem !important; }
            /* Slightly smaller left padding on extra-small screens but still clear the icon */
            #searchInput { padding-left: 2.25rem !important; }
            .s-chip { font-size: 0.62rem !important; padding: 0.2rem 0.38rem !important; }
            #markAllReadBtn { padding: 0.28rem 0.4rem !important; font-size: 0.64rem !important; }
            #notifChips .s-chip { margin-right: 0.28rem; }
            .gradient-bg > .absolute { transform: scale(0.78); opacity: 0.7; }
        }
        
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once ('../includes/lupon_head_nav.php'); ?>
    <?php include_once ('../chatbot/bpamis_case_assistant.php'); ?>
    <?php include_once ('sidebar_.php'); ?>
    <!-- Premium Hero (aligned with secretary UI) -->
    <div class="w-full mt-6 px-4">
        <div class="relative gradient-bg max-w-7xl mx-auto rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-20 -mt-20 opacity-70 animate-float"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-10 -mb-10 opacity-60 animate-float"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="max-w-2xl">
                    <h2 class="text-3xl md:text-4xl font-light text-primary-800 tracking-tight">Lupon <span class="font-semibold">Notifications</span></h2>
                    <p class="mt-3 text-gray-600 max-w-xl">Monitor your assigned cases, hearings, and complaint updates. Use filters to quickly find what matters.</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-bell text-primary-500"></i> Real-time</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-user-shield text-primary-500"></i> Scoped to You</span>
                    </div>
                </div>
                <div class="flex flex-col gap-3 min-w-[220px]">
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
    <div class="w-full mt-6 px-4">
        <div class="relative bg-white max-w-7xl mx-auto rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
            <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
            <div class="relative z-10 space-y-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Notifications</span>
                    </div>
                    <button id="markAllReadBtn" class="group inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold shadow-sm hover:shadow-md transition-all">
                        <i class="fa-solid fa-check-double"></i>
                        <span>Mark All Read</span>
                    </button>
                </div>
                <!-- Chips -->
                <div class="flex flex-wrap gap-2" id="notifChips">
                    <button type="button" data-filter="" class="s-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                    <button type="button" data-filter="unread" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Unread</button>
                    <button type="button" data-filter="hearing" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Hearings</button>
                    <button type="button" data-filter="complaint" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-cyan-50 text-cyan-600 border border-cyan-100 hover:bg-cyan-100 transition">Complaints</button>
                    <button type="button" data-filter="case" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Cases</button>
                    <button type="button" data-filter="assigned" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-sky-50 text-sky-700 border border-sky-100 hover:bg-sky-100 transition">Assigned</button>
                </div>
                <!-- Controls -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-5 relative group">
                        <input id="searchInput" type="text" placeholder="Search notifications..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                        <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                    </div>
                    <div class="md:col-span-2 relative">
                        <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Months</option>
                            <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); $mv=str_pad((string)$m,2,'0',STR_PAD_LEFT); ?>
                                <option value="<?= $mv ?>"><?= $mn ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                    </div>
                    <div class="md:col-span-2 relative">
                        <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="">All Years</option>
                            <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                    </div>
                    <div class="md:col-span-2 relative">
                        <select id="sortOrder" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                            <option value="desc">Newest first</option>
                            <option value="asc">Oldest first</option>
                        </select>
                        <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                    </div>
                    <div class="md:col-span-1 flex gap-2 justify-end md:justify-start">
                        <button id="resetFilters" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List (refined cards) -->
    <div class="w-full mt-6 px-4 pb-10">
        <div class="relative bg-white max-w-7xl mx-auto rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
            <div id="notificationList" class="space-y-4">
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
                                case 'Resolution':
                                    $icon = 'fa-scale-balanced';
                                    $iconWrap = 'bg-blue-50 text-blue-600';
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
                        <div class="s-notif-card relative group bg-white/90 backdrop-blur rounded-xl border border-gray-100 p-5 flex flex-col gap-3" data-type="<?= htmlspecialchars($baseType) ?>" data-base="<?= htmlspecialchars($baseType) ?>" data-date="<?= htmlspecialchars($createdAtRaw ? date('Y-m-d H:i:s', strtotime($createdAtRaw)) : '1970-01-01 00:00:00') ?>" data-unread="<?= $isUnread ? '1' : '0' ?>" data-search="<?= htmlspecialchars($searchStr) ?>" data-assigned="<?= $isAssigned ? '1' : '0' ?>">
                            <?php if ($isUnread): ?><span class="absolute top-3 right-3 inline-flex w-2.5 h-2.5 rounded-full bg-amber-500 shadow animate-pulse-subtle"></span><?php endif; ?>
                            <div class="flex items-start gap-4">
                                <div class="shrink-0 w-12 h-12 rounded-full flex items-center justify-center <?= $iconWrap ?> shadow-sm"><i class="fa-solid <?= $icon ?> text-base"></i></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($row['title'] ?? 'Notification') ?></p>
                                        <?php if ($isAssigned): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-semibold">Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($row['message'] ?? '') ?></p>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs text-gray-500"><?= $createdDisp ?></span>
                                        <div class="flex items-center gap-3">
                                            <?php if(!empty($notifId)): ?>
                                                <a href="view_notification.php?id=<?= htmlspecialchars($notifId) ?>" class="text-primary-600 hover:text-primary-700 text-xs font-medium">View</a>
                                                <button type="button" class="btn-delete-notif inline-flex items-center gap-1 px-2 py-1 rounded text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 transition" title="Delete" data-id="<?= htmlspecialchars($notifId) ?>">
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

    <!-- Premium Back to Dashboard CTA -->
    <div class="w-full mt-8 px-4 pb-8">
        <div class="max-w-7xl mx-auto flex justify-center">
            <button onclick="window.location.href='home-luponhead.php'" class="group relative inline-flex items-center gap-2.5 px-6 py-3 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 text-gray-700 font-medium shadow-sm hover:shadow-md hover:from-primary-50 hover:to-primary-100 hover:border-primary-200 hover:text-primary-700 transition-all duration-300 overflow-hidden">
                <span class="absolute inset-0 bg-gradient-to-r from-primary-500/0 to-primary-600/0 group-hover:from-primary-500/5 group-hover:to-primary-600/5 transition-all duration-300"></span>
                <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform duration-300 relative z-10"></i>
                <span class="relative z-10 text-sm font-semibold tracking-wide">Back to Dashboard</span>
                <div class="absolute inset-0 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" style="background: radial-gradient(circle at center, rgba(12, 156, 237, 0.03) 0%, transparent 70%);"></div>
            </button>
        </div>
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


