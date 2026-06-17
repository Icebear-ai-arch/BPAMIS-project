<?php
include '../controllers/session_control.php';
include '../server/server.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Notification ID is missing.');
}

$notificationId = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM notifications WHERE notification_id = ?");
$stmt->bind_param("i", $notificationId);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    die('Notification not found.');
}

$notification = $result->fetch_assoc();

// Mark as read
$conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $notificationId");

// Normalize wording for official viewers: replace 'your' with 'the' (preserve capitalization)
$hasOfficial = isset($_SESSION['official_id']) && is_numeric($_SESSION['official_id']);
if ($hasOfficial) {
    $replaceYour = function($text) {
        if (!is_string($text) || $text === '') return $text;
        return preg_replace_callback('/\byour\b/i', function($m){
            $match = $m[0];
            if (ctype_upper(substr($match,0,1))) return 'The';
            return 'the';
        }, $text);
    };

    if (isset($notification['message'])) $notification['message'] = $replaceYour($notification['message']);
    if (isset($notification['title'])) $notification['title'] = $replaceYour($notification['title']);
}

// After server-side mark-as-read, emit a client-side signal so other tabs update their badges.
// We do this by embedding a small script that posts to BroadcastChannel (if available)
// and toggles a localStorage key as a fallback for tabs that don't support BroadcastChannel.
// The script also triggers an immediate poll to the notifications_summary endpoint for the lupon role.
// This runs in the client when the page loads.

?>
<script>
    (function(){
        try{
            var payload = { type: 'notif-updated', role: 'lupon', id: <?= json_encode($notificationId) ?>, ts: Date.now() };
            if ('BroadcastChannel' in window) {
                try { new BroadcastChannel('bpamis-channel').postMessage(payload); } catch(e) {}
            }
            try { localStorage.setItem('bpamis-notif-updated', JSON.stringify(payload)); } catch(e) {}

            // Also proactively refresh the local nav badge by calling the summary endpoint
            try{
                fetch('../controllers/notifications_summary.php?role=lupon', { cache: 'no-store', credentials: 'same-origin' })
                    .then(function(r){ if(!r.ok) throw new Error('Network'); return r.json(); })
                    .then(function(data){
                        var el = document.getElementById('notif-count-badge');
                        if(!el) return;
                        var count = (data && typeof data.count === 'number') ? data.count : 0;
                        if(!count || count <= 0){ el.classList.add('hidden'); el.textContent = ''; }
                        else { el.classList.remove('hidden'); el.textContent = (count>99)?'99+':String(count); }
                    }).catch(function(){});
            }catch(e){}
        }catch(e){}
    })();
</script>
<?php

// Determine related id (supports legacy 'reference_id' and newer 'related_id')
$complaint = null;
$case_present = false;
$related_id = 0;
$typeNormalized = strtolower(trim($notification['type'] ?? ''));
if (!empty($notification['related_id'])) {
    $related_id = intval($notification['related_id']);
} elseif (!empty($notification['reference_id'])) {
    $related_id = intval($notification['reference_id']);
}

// If this notification refers to a complaint, load complaint summary
if ($typeNormalized === 'complaint' && $related_id > 0) {
    $complaint_id = $related_id;
    $query = "SELECT c.*, r.First_Name, r.Last_Name
              FROM complaint_info c
              LEFT JOIN resident_info r ON c.Resident_ID = r.Resident_ID
              WHERE c.Complaint_ID = " . intval($complaint_id) . " LIMIT 1";
    $result_complaint = $conn->query($query);
    if ($result_complaint && $result_complaint->num_rows > 0) {
        $complaint = $result_complaint->fetch_assoc();
    }
}

// Determine a case id to link to and mark $case_present when found.
// Sources tried (in order): notifications.Case_ID, message parsing for Case #123,
// related_id when type is 'case', lookup CASE_INFO by Complaint_ID when message contains a complaint reference.
$caseIdForLink = 0;

// 1) explicit Case_ID column on the notification
if (!empty($notification['Case_ID'])) {
    $caseIdForLink = intval($notification['Case_ID']);
}

// 2) try to parse a "Case #123" from the message, or patterns like "(ID: 3)" or "case ... ID: 3"
if ($caseIdForLink <= 0 && !empty($notification['message'])) {
    $msg = $notification['message'];
    if (preg_match('/Case\s*#?\s*(\d+)/i', $msg, $m)) {
        $caseIdForLink = intval($m[1]);
    }

    // pattern like (ID: 3)
    if ($caseIdForLink <= 0 && preg_match('/\(\s*ID\s*[:#]?\s*(\d+)\s*\)/i', $msg, $m2)) {
        $caseIdForLink = intval($m2[1]);
    }

    // pattern like "case ... ID: 3"
    if ($caseIdForLink <= 0 && preg_match('/case[^\d\n]{0,12}ID\s*[:#]?\s*(\d+)/i', $msg, $m3)) {
        $caseIdForLink = intval($m3[1]);
    }
}

// 3) if the notification is explicitly a 'case' type and related_id is present, use it
if ($caseIdForLink <= 0 && $typeNormalized === 'case' && $related_id > 0) {
    $caseIdForLink = $related_id;
}

// 4) if still not found and message may reference a complaint, try to parse complaint id and lookup CASE_INFO
if ($caseIdForLink <= 0 && !empty($notification['message'])) {
    $msg = $notification['message'];
    $complaintIdCandidate = 0;

    // patterns like "Complaint 123", "Complaint #123", "Complaint ID: 123",
    // and phrases like "complaint with ID #8"
    if (preg_match('/Complaint(?:\s+with)?[^\d]{0,20}(?:ID\s*:?\s*)?#?\s*(\d+)/i', $msg, $mc)) {
        $complaintIdCandidate = intval($mc[1]);
    }

    // pattern like C2025-001 or C2025-1 -> capture the numeric suffix
    if ($complaintIdCandidate <= 0 && preg_match('/\bC\s?(\d{4})[-_ ]?(\d{1,})\b/i', $msg, $mc2)) {
        $complaintIdCandidate = intval($mc2[2]);
    }

    if ($complaintIdCandidate > 0) {
        $res = $conn->query("SELECT Case_ID FROM CASE_INFO WHERE Complaint_ID = " . intval($complaintIdCandidate) . " LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $caseIdForLink = intval($row['Case_ID']);
        }
    }
}

// 5) if notification itself is a complaint and complaint has been linked to a case, use that
if ($caseIdForLink <= 0 && $typeNormalized === 'complaint' && $related_id > 0) {
    $res = $conn->query("SELECT Case_ID FROM CASE_INFO WHERE Complaint_ID = " . intval($related_id) . " LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $caseIdForLink = intval($row['Case_ID']);
    }
}

if ($caseIdForLink > 0) {
    $case_present = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <title>Notification • Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
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
                            900: '#0a4b76',
                        }
                    },
                    boxShadow: {
                        'glow': '0 0 0 1px rgba(12,156,237,0.08), 0 4px 20px -2px rgba(6,90,143,0.18)',
                    },
                    backdropBlur: {
                        xs: '2px'
                    },
                    animation: {
                        'fade-in': 'fadeIn .4s ease-out',
                        'scale-in': 'scaleIn .35s ease-out'
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                        scaleIn: { '0%': { opacity: 0, transform: 'scale(.98)' }, '100%': { opacity: 1, transform: 'scale(1)' } }
                    }
                }
            }
        }
    </script>
    <!-- Correct Font Awesome include (previous integrity attribute was invalid, causing icons to fail) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .gradient-bg {
            background: radial-gradient(circle at 20% 20%, #e0effe, #f7fbff 60%);
        }

        .glass {
            background: linear-gradient(140deg, rgba(255, 255, 255, .85), rgba(255, 255, 255, .65));
            backdrop-filter: blur(10px) saturate(140%);
            -webkit-backdrop-filter: blur(10px) saturate(140%);
        }

        .divider-dot:before,
        .divider-dot:after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, transparent, #cfe6f7);
        }

        .divider-dot:after {
            background: linear-gradient(to left, transparent, #cfe6f7);
        }
        
        /* Mobile Optimization - Compressed & Compact */
        @media (max-width: 640px) {
            /* Main container padding */
            main {
                padding-top: 1.5rem !important;
                padding-bottom: 3rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            /* Back button row */
            .mb-8.flex.items-center.gap-3 {
                margin-bottom: 1rem !important;
            }
            
            .mb-8.flex.items-center.gap-3 a {
                font-size: 0.75rem !important;
            }
            
            .mb-8.flex.items-center.gap-3 .h-8.w-8 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }
            
            /* Main card */
            section.glass {
                padding: 1rem !important;
                border-radius: 1rem !important;
            }
            
            /* Header section - make icon and title side by side */
            header.relative.flex {
                flex-direction: row !important;
                gap: 0.75rem !important;
                margin-bottom: 1.25rem !important;
                align-items: flex-start !important;
            }
            
            /* Icon container - smaller for inline layout */
            .w-20.h-20 {
                width: 2.5rem !important;
                height: 2.5rem !important;
                border-radius: 0.65rem !important;
                flex-shrink: 0 !important;
            }
            
            .w-20.h-20 i {
                font-size: 1.1rem !important;
            }
            
            /* Priority badge */
            .absolute.-top-2.-right-2 {
                top: -0.25rem !important;
                right: -0.25rem !important;
                font-size: 8px !important;
                padding: 0.15rem 0.35rem !important;
            }
            
            /* Title - adjust for inline layout */
            h1.text-2xl {
                font-size: 1rem !important;
                line-height: 1.3 !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.4rem !important;
            }
            
            /* Type badge - move below title */
            h1 span.inline-flex.items-center {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Meta info */
            .mt-3.flex.flex-wrap {
                margin-top: 0.5rem !important;
                font-size: 0.7rem !important;
                gap: 0.5rem !important;
            }
            
            /* Content sections */
            .space-y-10 {
                gap: 1.5rem !important;
            }
            
            .space-y-10 > div {
                margin-top: 1.5rem !important;
            }
            
            .space-y-10 > div:first-child {
                margin-top: 0 !important;
            }
            
            /* Section headings */
            h2.text-sm {
                font-size: 0.7rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Message box - reduce font size */
            .rounded-xl.border.border-primary-100 {
                padding: 0.75rem !important;
                font-size: 0.72rem !important;
                line-height: 1.5 !important;
                border-radius: 0.75rem !important;
            }
            
            .absolute.-top-3.left-5 {
                font-size: 8px !important;
                padding: 0.1rem 0.4rem !important;
            }
            
            /* Complaint details grid */
            .grid.gap-5 {
                gap: 0.65rem !important;
            }
            
            .grid.gap-5 > div {
                padding: 0.65rem !important;
                border-radius: 0.65rem !important;
            }
            
            .grid.gap-5 p.text-\[11px\] {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            .grid.gap-5 p.font-semibold {
                font-size: 0.8rem !important;
            }
            
            /* Linked badge */
            .flex.items-center.justify-between.mb-4 {
                margin-bottom: 0.65rem !important;
            }
            
            .flex.items-center.justify-between.mb-4 span {
                font-size: 9px !important;
                padding: 0.2rem 0.4rem !important;
            }
            
            /* Description box */
            .md\:col-span-2.rounded-xl {
                padding: 0.75rem !important;
            }
            
            .md\:col-span-2.rounded-xl p.text-gray-700 {
                font-size: 0.78rem !important;
                line-height: 1.4 !important;
            }
            
            /* Bottom action buttons - keep in same row */
            .pt-4.border-t {
                padding-top: 0.75rem !important;
                gap: 0.65rem !important;
            }
            
            .flex.gap-2 {
                gap: 0.4rem !important;
                width: 100% !important;
                flex-direction: row !important;
                flex-wrap: wrap !important;
            }
            
            .flex.gap-2 a {
                font-size: 0.7rem !important;
                padding: 0.55rem 0.7rem !important;
                border-radius: 0.65rem !important;
                flex: 1 1 auto !important;
                min-width: fit-content !important;
                justify-content: center !important;
            }
            
            .flex.gap-2 a i {
                font-size: 0.65rem !important;
            }
            
            /* Background orbs - reduce on mobile */
            .pointer-events-none.absolute.inset-0 > div {
                opacity: 0.15 !important;
                transform: scale(0.7);
            }
        }
        
        @media (max-width: 380px) {
            /* Extra small screens - further compression */
            main {
                padding-top: 1rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            section.glass {
                padding: 0.75rem !important;
            }
            
            h1.text-2xl {
                font-size: 1rem !important;
            }
            
            .w-20.h-20 {
                width: 2.5rem !important;
                height: 2.5rem !important;
            }
            
            .w-20.h-20 i {
                font-size: 1.1rem !important;
            }
            
            .rounded-xl.border.border-primary-100 {
                padding: 0.6rem !important;
                font-size: 0.75rem !important;
            }
            
            .grid.gap-5 p.font-semibold {
                font-size: 0.75rem !important;
            }
        }
    </style>
</head>

<body
    class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <!-- Decorative Background Orbs -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl">
        </div>
    </div>

    <?php include '../includes/lupon_head_nav.php'; ?>


    <?php
    // Helper: relative time
    function relative_time($datetime)
    {
        $ts = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $ts;
        if ($diff < 60)
            return 'just now';
        $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
        foreach ($units as $secs => $label) {
            if ($diff >= $secs) {
                $val = floor($diff / $secs);
                return $val . ' ' . $label . ($val > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }

    $type = $notification['type'];
    $isPriority = isset($notification['isPriority']) && (int) $notification['isPriority'] === 1;
    $map = [
        'Complaint' => ['icon' => 'fa-file-lines', 'color' => 'emerald', 'bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-100', 'accent' => 'text-emerald-600'],
        'Case' => ['icon' => 'fa-gavel', 'color' => 'amber', 'bg' => 'bg-amber-50', 'ring' => 'ring-amber-100', 'accent' => 'text-amber-600'],
        'Hearing' => ['icon' => 'fa-calendar-alt', 'color' => 'blue', 'bg' => 'bg-blue-50', 'ring' => 'ring-blue-100', 'accent' => 'text-blue-600'],
        'Unverified' => ['icon' => 'fa-user-circle', 'color' => 'rose', 'bg' => 'bg-rose-50', 'ring' => 'ring-rose-100', 'accent' => 'text-rose-600'],
        'Mediation Deadline' => ['icon' => 'fa-hourglass-half', 'color' => 'red', 'bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'accent' => 'text-red-600'],
        'Resolution Deadline' => ['icon' => 'fa-hourglass-half', 'color' => 'red', 'bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'accent' => 'text-red-600'],
        'Settlement Deadline' => ['icon' => 'fa-hourglass-half', 'color' => 'red', 'bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'accent' => 'text-red-600'],
        'Case Deadline' => ['icon' => 'fa-hourglass-half', 'color' => 'red', 'bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'accent' => 'text-red-600'],
        'Deadline Overdue' => ['icon' => 'fa-triangle-exclamation', 'color' => 'red', 'bg' => 'bg-red-50', 'ring' => 'ring-red-100', 'accent' => 'text-red-600'],
    ];
    $style = $map[$type] ?? ['icon' => 'fa-bell', 'color' => 'sky', 'bg' => 'bg-sky-50', 'ring' => 'ring-sky-100', 'accent' => 'text-sky-600'];
    ?>

    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="notifications-luponhead.php"
                class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50">
                    <i class="fa fa-arrow-left"></i>
                </span>
                <span class="ml-2">Back to Notifications</span>
            </a>
        </div>

        <section
            class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40">
                </div>
            </div>

            <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-8">
                <div class="flex items-center">
                    <div class="relative">
                        <div
                            class="w-20 h-20 rounded-2xl flex items-center justify-center <?php echo $style['bg']; ?> ring-4 <?php echo $style['ring']; ?> shadow-inner">
                            <i class="fa <?php echo $style['icon']; ?> text-3xl <?php echo $style['accent']; ?>"></i>
                        </div>
                        <?php if ($isPriority): ?>
                            <div
                                class="absolute -top-2 -right-2 bg-red-600 text-white text-[10px] font-semibold px-2 py-1 rounded-full shadow uppercase tracking-wide">
                                High</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1">
                    <h1
                        class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">
                            <?= htmlspecialchars($notification['title']) ?: 'Notification' ?>
                        </span>
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-white/70 border border-primary-100 text-primary-700 shadow-sm">
                            <i class="fa <?php echo $style['icon']; ?>"></i>
                            <?= htmlspecialchars($notification['type']) ?>
                        </span>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-clock"></i>
                            <?= date("F d, Y • h:i A", strtotime($notification['created_at'])) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hourglass-half"></i>
                            <?= relative_time($notification['created_at']) ?></span>
                        <span
                            class="inline-flex items-center gap-1 <?php echo $isPriority ? 'text-red-600 font-medium' : ''; ?>">

                        </span>

                    </div>
                </div>
            </header>

            <div class="space-y-10">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Message</h2>
                    <div
                        class="relative rounded-xl border border-primary-100/60 bg-white/80 p-5 leading-relaxed text-gray-700 shadow-sm">
                        <div
                            class="absolute -top-3 left-5 px-2 text-[10px] font-semibold tracking-wide uppercase bg-primary-100 text-primary-700 rounded-full">
                            Content</div>
                        <p class="whitespace-pre-line"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                    </div>
                </div>

                <?php if ($complaint): ?>
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase">Complaint Details</h2>
                            <span class="text-xs px-2 py-1 rounded-md bg-amber-100 text-amber-700 font-medium">Linked</span>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div
                                class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complaint ID
                                </p>
                                <p class="font-semibold text-gray-800">
                                    C<?= date('Y') ?>-<?= str_pad($complaint['Complaint_ID'], 3, '0', STR_PAD_LEFT) ?></p>
                            </div>
                            <div
                                class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complainant
                                </p>
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($complaint['First_Name'] . ' ' . $complaint['Last_Name']) ?></p>
                            </div>
                            <div
                                class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Title</p>
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($complaint['Complaint_Title']) ?></p>
                            </div>
                            <div
                                class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Status</p>
                                <p
                                    class="inline-flex items-center gap-1 text-sm font-semibold <?php echo strtolower($complaint['Status']) === 'pending' ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                    <i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?>
                                </p>
                            </div>
                            <div
                                class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm md:col-span-2">
                                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Date Filed</p>
                                <p class="font-semibold text-gray-800">
                                    <?= date("F d, Y", strtotime($complaint['Date_Filed'])) ?></p>
                            </div>
                            <?php if (!empty($complaint['Description'])): ?>
                                <div class="md:col-span-2 rounded-xl border bg-white/70 border-gray-200 p-5 shadow-sm">
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-2">Description
                                    </p>
                                    <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($complaint['Description'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div
                    class="pt-4 border-t border-dashed border-primary-200/60 flex flex-wrap items-center justify-between gap-4">

                    <div class="flex gap-2">
                        <a href="notifications-luponhead.php"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                        <?php if ($complaint): ?>
                            <a href="view_complaint_details.php?id=<?= $complaint['Complaint_ID'] ?>"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition">
                                <i class="fa fa-folder-open"></i> Open Complaint
                            </a>
                        <?php endif; ?>

                        <?php if ($case_present && isset($caseIdForLink) && $caseIdForLink > 0): ?>
                            <a href="view_case_details.php?id=<?= $caseIdForLink ?>"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white shadow text-sm font-medium transition">
                                <i class="fa fa-gavel"></i> Open Case
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>

</html>