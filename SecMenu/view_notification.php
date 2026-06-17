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
// Set the global is_read flag and, if the current session belongs to the secretary,
// also set the role-specific is_read_secretary flag so the secretary's unread state is tracked.
// Use prepared statements and check whether the secretary-specific column exists before updating it.
if ($st = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?")) {
    $st->bind_param('i', $notificationId);
    $st->execute();
    $st->close();
}

// If current session appears to be a barangay secretary, try to set is_read_secretary = 1
$isSecretary = false;
if (!empty($_SESSION['role']) && strtolower($_SESSION['role']) === 'official' && !empty($_SESSION['official_position']) && stripos($_SESSION['official_position'], 'secretary') !== false) {
    $isSecretary = true;
}

if ($isSecretary) {
    // Confirm the column exists to avoid SQL errors in older schemas
    $colRes = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read_secretary'");
    if ($colRes && $colRes->num_rows > 0) {
        if ($st2 = $conn->prepare("UPDATE notifications SET is_read_secretary = 1 WHERE notification_id = ?")) {
            $st2->bind_param('i', $notificationId);
            $st2->execute();
            $st2->close();
        }
    }
}

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
              FROM COMPLAINT_INFO c
              LEFT JOIN RESIDENT_INFO r ON c.Resident_ID = r.Resident_ID
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

    // pattern like (ID: 3) which often appears in messages: "The status of your case (ID: 3) ..."
    if ($caseIdForLink <= 0 && preg_match('/\(\s*ID\s*[:#]?\s*(\d+)\s*\)/i', $msg, $m2)) {
        $caseIdForLink = intval($m2[1]);
    }

    // pattern like "case ... ID: 3" or "case ID: 3"
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
    // and phrases like "complaint with ID #8" (so allow an optional 'with' or small words between)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
    </style>
    <style>
        /* Mobile adjustments: reduce typography, paddings and button sizes */
        @media (max-width:640px) {
            main { padding-left: 0.75rem !important; padding-right: 0.75rem !important; padding-top: 1.5rem !important; }
            .glass { padding: 1rem !important; }
            header h1 { font-size: 1rem !important; }
            header h1 span { font-size: 1rem !important; }
            header .w-20 { width: 40px !important; height: 40px !important; }
            header .w-20 i { font-size: 0.9rem !important; }
            .divider-dot:before, .divider-dot:after { height: 1px !important; }
            .space-y-10 > div h2 { font-size: 0.78rem !important; }
            .relative.rounded-xl.p-5 { padding: 0.75rem !important; }
            .pt-4.border-t { padding-top: 0.75rem !important; }
            .inline-flex.px-4.py-2 { padding-left: 0.6rem !important; padding-right: 0.6rem !important; padding-top: 0.4rem !important; padding-bottom: 0.4rem !important; }
            .inline-flex text-sm, .text-sm { font-size: 0.85rem !important; }
            .text-2xl { font-size: 1rem !important; }
            .text-3xl { font-size: 1rem !important; }
            .leading-relaxed { line-height: 1.25 !important; }
            .grid.gap-5.md\:grid-cols-2 { gap: 0.75rem !important; }
            .group.rounded-xl.p-4 { padding: 0.75rem !important; }
            /* Make action buttons smaller */
            a.inline-flex.items-center.gap-2.px-4.py-2,
            a.inline-flex.items-center.gap-2.px-4.py-2 i,
            .inline-flex.items-center.gap-2.px-4.py-2 { padding: 0.4rem 0.55rem !important; font-size: 0.8rem !important; }
            /* Reduce message box font size slightly */
            .relative.rounded-xl.border.bg-white\/80.p-5 p { font-size: 0.88rem !important; }
            /* Make "Back to Notifications" button smaller */
            .mb-8 a { font-size: 0.8rem !important; }
            .mb-8 a .h-8.w-8 { height: 1.75rem !important; width: 1.75rem !important; }
            .mb-8 a i { font-size: 0.75rem !important; }
            /* Make date/time section smaller */
            .mt-3.flex.flex-wrap { font-size: 0.75rem !important; gap: 0.5rem !important; }
            .mt-3.flex.flex-wrap span { font-size: 0.75rem !important; }
            .mt-3.flex.flex-wrap i { font-size: 0.7rem !important; }
            /* Make header layout inline on mobile */
            header.relative.flex { flex-direction: row !important; gap: 0.75rem !important; }
            header .flex-1 { flex: 1 !important; }
        }

        @media (max-width:380px) {
            main { padding-left: 0.5rem !important; padding-right: 0.5rem !important; padding-top: 1rem !important; }
            .glass { padding: 0.75rem !important; }
            header h1 { font-size: 0.9rem !important; }
            header h1 span { font-size: 0.9rem !important; }
            header .w-20 { width: 36px !important; height: 36px !important; }
            header .w-20 i { font-size: 0.85rem !important; }
            .space-y-10 > div h2 { font-size: 0.72rem !important; }
            .relative.rounded-xl.p-5 { padding: 0.6rem !important; }
            .pt-4.border-t { padding-top: 0.6rem !important; }
            a.inline-flex.items-center.gap-2.px-4.py-2 { padding: 0.3rem 0.45rem !important; font-size: 0.75rem !important; }
            .relative.rounded-xl.border.bg-white\/80.p-5 p { font-size: 0.85rem !important; }
            .grid.gap-5.md\:grid-cols-2 { gap: 0.5rem !important; }
            /* Make "Back to Notifications" button smaller */
            .mb-8 a { font-size: 0.75rem !important; }
            .mb-8 a .h-8.w-8 { height: 1.5rem !important; width: 1.5rem !important; }
            .mb-8 a i { font-size: 0.7rem !important; }
            /* Make date/time section smaller */
            .mt-3.flex.flex-wrap { font-size: 0.7rem !important; gap: 0.4rem !important; }
            .mt-3.flex.flex-wrap span { font-size: 0.7rem !important; }
            .mt-3.flex.flex-wrap i { font-size: 0.65rem !important; }
            /* Make header layout inline on mobile */
            header.relative.flex { gap: 0.5rem !important; }
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

    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>

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
            <a href="notifications-secretary.php"
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
                        <?php $__msg = preg_replace('/^(\s*)Your\b/', '$1The', $notification['message'] ?? '', 1); ?>
                        <p class="whitespace-pre-line"><?= nl2br(htmlspecialchars($__msg)) ?></p>
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
                            <?php if (!empty($complaint['Complaint_Details'])): ?>
                                <div class="md:col-span-2 rounded-xl border bg-white/70 border-gray-200 p-5 shadow-sm">
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-2">Description
                                    </p>
                                    <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($complaint['Complaint_Details'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div
                    class="pt-4 border-t border-dashed border-primary-200/60 flex flex-wrap items-center justify-between gap-4">

                    <div class="flex gap-2">
                        <a href="notifications-secretary.php"
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