<?php
include '../controllers/session_control.php';
include '../server/server.php';

// ✅ Ensure logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../bpamis_website/login.php");
    exit();
}

$external_user_id = $_SESSION['user_id'];
date_default_timezone_set('Asia/Manila');
$today = new DateTime();

// ⚙️ Adjust to your actual column name for external complainant foreign key in notifications table
// Common options: external_user_id, external_complainant_id, external_complaint_id
$externalColumn = 'external_complaint_id'; 

// ✅ STEP 1: Find all active cases with resolution deadlines
$checkQuery = $conn->prepare("
    SELECT 
        c.Case_ID,
        r.Deadline AS Resolution_Deadline,
        c.Case_Status,
        ci.external_complainant_id AS external_complainant_id
    FROM case_info c
    INNER JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID
    INNER JOIN resolution r ON r.Case_ID = c.Case_ID
    WHERE c.Case_Status != 'Resolved'
      AND r.Deadline IS NOT NULL
");
$checkQuery->execute();
$casesResult = bpamis_stmt_get_result($checkQuery);

while ($case = $casesResult->fetch_assoc()) {
    $deadline = new DateTime($case['Resolution_Deadline']);
    $interval = $today->diff($deadline)->days;
    $isUpcoming = ($deadline > $today && $interval <= 10);

    if ($isUpcoming) {
        $parties = [];

        // ✅ Add external complainant if exists
        if (!empty($case['external_complainant_id'])) {
            $parties[] = [
                'id' => $case['external_complainant_id'],
                'type' => 'external'
            ];
        }

        // ✅ Add all respondents (if residents)
        $respQuery = $conn->prepare("
            SELECT respondent_id 
            FROM complaint_respondents 
            WHERE complaint_id = (
                SELECT Complaint_ID FROM case_info WHERE Case_ID = ?
            )
        ");
        $respQuery->bind_param("i", $case['Case_ID']);
        $respQuery->execute();
        $respResult = bpamis_stmt_get_result($respQuery);

        while ($resp = $respResult->fetch_assoc()) {
            if (!empty($resp['respondent_id'])) {
                $parties[] = [
                    'id' => $resp['respondent_id'],
                    'type' => 'resident'
                ];
            }
        }

        // ✅ Send notification for each participant
        foreach ($parties as $p) {
            $id = $p['id'];
            $type = $p['type'];

            if (!$id) continue;

            $notifTitle = "Resolution Deadline Approaching";
            $notifMessage = "Case ID: {$case['Case_ID']} is nearing its resolution deadline. You may now agree to move this case to 'Arbitration' if both parties consent.";
            $notifType = "case";
            $createdAt = date('Y-m-d H:i:s');

            if ($type === 'resident') {
                // 🧩 Resident notification
                $checkNotif = $conn->prepare("
                    SELECT 1 FROM notifications
                    WHERE resident_id = ?
                      AND title = ?
                      AND message LIKE ?
                      LIMIT 1
                ");
                $pattern = "%Case ID: {$case['Case_ID']}%";
                $checkNotif->bind_param("iss", $id, $notifTitle, $pattern);
                $checkNotif->execute();
                $exists = bpamis_stmt_get_result($checkNotif)->num_rows > 0;
                $checkNotif->close();

                if (!$exists) {
                    $insertNotif = $conn->prepare("
                        INSERT INTO notifications (resident_id, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, 0, ?)
                    ");
                    $insertNotif->bind_param("issss", $id, $notifTitle, $notifMessage, $notifType, $createdAt);
                    $insertNotif->execute();
                    $insertNotif->close();
                }
            } elseif ($type === 'external') {
                // 🌐 External complainant notification
                $checkNotif = $conn->prepare("
                    SELECT 1 FROM notifications
                    WHERE $externalColumn = ?
                      AND title = ?
                      AND message LIKE ?
                      LIMIT 1
                ");
                $pattern = "%Case ID: {$case['Case_ID']}%";
                $checkNotif->bind_param("iss", $id, $notifTitle, $pattern);
                $checkNotif->execute();
                $exists = bpamis_stmt_get_result($checkNotif)->num_rows > 0;
                $checkNotif->close();

                if (!$exists) {
                    $insertNotif = $conn->prepare("
                        INSERT INTO notifications ($externalColumn, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, 0, ?)
                    ");
                    $insertNotif->bind_param("issss", $id, $notifTitle, $notifMessage, $notifType, $createdAt);
                    $insertNotif->execute();
                    $insertNotif->close();
                }
            }
        }
    }
}

// ✅ Fetch notifications for the current external user (exclude trashed)
$sql = "SELECT n.* FROM notifications n
        LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
        WHERE n.$externalColumn = ? 
          AND t.notification_id IS NULL
        ORDER BY n.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $external_user_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// ✅ STEP 3: Count notifications by type
$allCount = count($notifications);
$unreadCount = 0;
$hearingCount = 0;
$complaintCount = 0;
$caseCount = 0;

foreach ($notifications as $n) {
    if (($n['is_read'] ?? 1) == 0) $unreadCount++;
    $t = strtolower($n['type'] ?? '');
    if ($t === 'hearing') $hearingCount++;
    elseif ($t === 'complaint') $complaintCount++;
    elseif ($t === 'case') $caseCount++;
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
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include_once('../includes/external_nav.php'); ?>
    <!-- Floating Orb Background -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <!-- Premium Hero -->
    <div class="w-full mt-8 px-4">
        <div class="relative gradient-bg rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden max-w-screen-2xl mx-auto">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-24 -mt-24 opacity-70 animate-[float_8s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-14 -mb-14 opacity-60 animate-[float_6s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Your <span class="font-semibold">Notifications</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Stay informed on complaint updates, case developments, and upcoming hearings. Use the smart filters to focus only on what matters to you.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-bell text-primary-500"></i> Real-time</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-filter text-primary-500"></i> Filterable</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-magnifying-glass text-primary-500"></i> Searchable</span>
                    </div>
                </div>
                <div class="flex flex-col gap-3 min-w-[250px]">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">All</span><span class="mt-1 text-lg font-semibold text-blue-700"><?= $allCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">Unread</span><span class="mt-1 text-lg font-semibold text-amber-700"><?= $unreadCount ?></span></div>
                    </div>
                    
                    <div class="text-[11px] text-primary-700/70 text-center">Overview summary</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filters Card -->
    <div class="w-full mt-8 px-4">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-3 text-primary-700/80 text-sm font-medium">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Notifications</span>
                        </div>
                        <form method="POST" action="mark_all_read.php" class="flex items-center gap-2">
                            <button type="submit" class="group relative inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold shadow-sm hover:shadow-md transition-all">
                                <i class="fa-solid fa-check-double"></i>
                                <span>Mark All Read</span>
                            </button>
                        </form>
                    </div>
                    <!-- Filter Chips -->
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
                        <div class="md:col-span-2 relative">
                            <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); ?>
                                    <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>"><?= $mn ?></option>
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
                                <option value="desc">Newest First</option>
                                <option value="asc">Oldest First</option>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-1 flex">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Grid -->
    <div id="notificationSection" class="w-full mt-8 px-4 pb-20">
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
                <a href="home-external.php" class="inline-flex items-center gap-2 px-4 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium transition"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
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
        const q=(searchInput.value||'').toLowerCase(); const m=monthFilter.value; const y=yearFilter.value; let shown=0;
        cards.forEach(c=>{
            const type=c.dataset.type||''; const unread=c.dataset.unread==='1'; const dateRaw=c.dataset.date||''; const text=c.dataset.search||''; let show=true;
            if(filterOverride){ if(filterOverride==='unread') show=unread; else show=type===filterOverride; }
            if(q) show=show && text.includes(q);
            if((m||y)&&dateRaw){ const d=new Date(dateRaw); const M=('0'+(d.getMonth()+1)).slice(-2); const Y=d.getFullYear().toString(); if(m) show=show && M===m; if(y) show=show && Y===y; }
            c.style.display=show?'':'none'; if(show) shown++; });
        // sort visible
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
                card.classList.add('slide-out-left');
                setTimeout(() => {
                    card.remove();
                    // Update navbar badge if notification was unread
                    if (wasUnread) {
                        // Try using the global badge update function first
                        if (typeof window.updateExternalBadge === 'function') {
                            const badge = document.getElementById('notif-count-badge');
                            const currentCount = parseInt(badge?.textContent || '0') || 0;
                            const newCount = Math.max(0, currentCount - 1);
                            window.updateExternalBadge(newCount);
                        } else {
                            // Fallback to direct badge manipulation (note: external nav doesn't show badges currently)
                            const badge = document.getElementById('notif-count-badge');
                            if (badge && !badge.classList.contains('hidden')) {
                                const currentCount = parseInt(badge.textContent) || 0;
                                const newCount = Math.max(0, currentCount - 1);
                                badge.textContent = newCount;
                                if (newCount === 0) {
                                    badge.classList.add('hidden');
                                }
                            }
                        }
                    }
                    // Re-apply filters after removal
                    applyFilters();
                }, 300);
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
                else if (message.includes('notification') || message.includes('notifications')) {
                    return 'You can view all your notifications on this page. These include updates about your cases, scheduled hearings, and other important information.';
                }
                else {
                    return 'I\'m not sure I understand. Could you please rephrase your question? You can ask about case status, hearings, complaints, or contact information.';
                }
            }
        });
    </script>
</body>
</html>
