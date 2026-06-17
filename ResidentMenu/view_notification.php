<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../bpamis_website/login.php');
    exit;
}

$resident_id = (int)($_SESSION['user_id'] ?? 0);

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Notification ID is missing.');
}

$notificationId = (int)$_GET['id'];

$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$T_RESIDENT_INFO = bpamis_table($conn, 'resident_info');
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');

$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);

// Fetch notification
$stmt = $conn->prepare("SELECT * FROM {$TB_NOTIFICATIONS} WHERE notification_id = ?");
$stmt->bind_param('i', $notificationId);
$stmt->execute();
$rows = bpamis_stmt_fetch_all_assoc($stmt);
if (empty($rows)) {
    die('Notification not found.');
}
$notification = $rows[0];
$stmt->close();

// Mark as read
$conn->query("UPDATE {$TB_NOTIFICATIONS} SET is_read = 1 WHERE notification_id = $notificationId");

// Safely detect reference ID
$refId = (int)($notification['reference_id'] ?? $notification['ref_id'] ?? $notification['related_id'] ?? 0);

// ✅ If reference_id is missing, try to extract from message text
if ($refId === 0 && isset($notification['message'])) {
    if (preg_match('/Case\s*ID\s*:\s*(\d+)/i', $notification['message'], $matches)) {
        $refId = (int)$matches[1];

    }
}

// Fetch complaint if exists
$complaint = null;
if ($refId > 0) {
    $query = "SELECT ci.*, r.first_name AS First_Name, r.last_name AS Last_Name
              FROM {$TB_COMPLAINT_INFO} ci
              LEFT JOIN {$TB_RESIDENT_INFO} r ON ci.resident_id = r.resident_id
              WHERE ci.complaint_id = $refId";
    $cRes = $conn->query($query);
    if ($cRes && $cRes->num_rows > 0) {
        $complaint = $cRes->fetch_assoc();
    }
}

// Try to resolve a Case_ID for the Open Case link. Notifications may reference a complaint_id
// or a case_id. If we have a complaint, prefer the Case_ID found in CASE_INFO by Complaint_ID.
$openCaseId = 0;
if ($refId > 0) {
    // Prefer prepared statement when possible
    $caseStmt = $conn->prepare("SELECT Case_ID FROM {$TB_CASE_INFO} WHERE Complaint_ID = ? LIMIT 1");
    if ($caseStmt) {
        $caseStmt->bind_param('i', $refId);
        $caseStmt->execute();
        $cRows = bpamis_stmt_fetch_all_assoc($caseStmt);
        if (!empty($cRows)) { $openCaseId = (int)($cRows[0]['Case_ID'] ?? 0); }
        $caseStmt->close();
    } else {
        $q = $conn->query("SELECT Case_ID FROM {$TB_CASE_INFO} WHERE Complaint_ID = " . (int)$refId . " LIMIT 1");
        if ($q && $r = $q->fetch_assoc()) { $openCaseId = (int)$r['Case_ID']; }
    }
}

// Compute a single target case id to use in the UI. Prefer resolved case id, then refId.
$targetCaseId = $openCaseId > 0 ? $openCaseId : (int)$refId;

// If still not found, try to extract from common JSON payload fields or the message text.
if ($targetCaseId === 0) {
    $candidates = ['data','payload','meta','extras','extra','details'];
    foreach ($candidates as $key) {
        if (!empty($notification[$key])) {
            $decoded = json_decode($notification[$key], true);
            if (is_array($decoded)) {
                foreach (['case_id','Case_ID','caseId','caseID','Complaint_ID','complaint_id'] as $k) {
                    if (!empty($decoded[$k]) && is_numeric($decoded[$k])) { $targetCaseId = (int)$decoded[$k]; break 2; }
                }
            }
        }
    }
}

// As a last resort, try additional patterns inside the message or any URL included
if ($targetCaseId === 0 && !empty($notification['message'])) {
    $msg = $notification['message'];
    // If the notification mentions a complaint converted to a case, try to extract the new case id
    if (preg_match('/converted\s+to\s+case\s+#?(\d+)/i', $msg, $convMatch)) {
        $targetCaseId = (int)$convMatch[1];
    }
    // match ?case_id=123 or case_id=123
    if (preg_match('/[?&]case_id=(\d+)/i', $msg, $m)) { $targetCaseId = (int)$m[1]; }
    // match /case_details.php?case_id=123
    if ($targetCaseId === 0 && preg_match('/case_details\.php\?case_id=(\d+)/i', $msg, $m2)) { $targetCaseId = (int)$m2[1]; }
    // match "Case ID: 123" or "Case #123"
    if ($targetCaseId === 0 && preg_match('/Case\s*(?:ID|#)\s*[:#]?\s*(\d+)/i', $msg, $m3)) { $targetCaseId = (int)$m3[1]; }
}

// Flag whether this notification is about a complaint -> case conversion
$isConversion = false;
if (!empty($notification['message']) && preg_match('/converted\s+to\s+case/i', $notification['message'])) {
    $isConversion = true;
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
        /* Mobile compression: make the notification detail view more compact on small screens */
        @media (max-width: 640px) {
            main { padding-left: 0.75rem !important; padding-right: 0.75rem !important; padding-top: 0.5rem !important; }
            /* Reduce the glass card padding and radius */
            .glass { padding: 0.6rem !important; border-radius: 0.75rem !important; }

            /* Tighter header spacing */
            header.relative { gap: 0.5rem !important; }

            /* Smaller icon container */
            .w-20.h-20 { width: 48px !important; height: 48px !important; }
            .w-20.h-20 i { font-size: 1.05rem !important; }

            /* Reduce title sizes */
            h1.text-2xl { font-size: 1.05rem !important; }
            h1.text-2xl .inline-flex { padding: 0.2rem 0.45rem !important; font-size: 0.68rem !important; }

            /* Compress content blocks */
            .space-y-10 { gap: 0.6rem !important; }
            .relative.rounded-xl, .group.rounded-xl { padding: 0.6rem !important; }
           .group.rounded-xl .text-\[11px\] {
                font-size: 0.78rem !important;
            }


            /* Smaller body text and tighter line-height */
            .leading-relaxed { line-height: 1.2 !important; font-size: 0.92rem !important; }

            /* Compact action buttons in footer */
            .pt-4 .inline-flex { padding: 0.4rem 0.7rem !important; font-size: 0.85rem !important; }
            .pt-4 .inline-flex i { font-size: 0.9rem !important; }

            /* Reduce spacing for complaint detail cards grid */
            .grid.gap-5, .grid.gap-5.md\:grid-cols-2 { gap: 0.6rem !important; }

            /* Modal-like small adjustments for the complaint description */
            .md\:col-span-2.p-5 { padding: 0.6rem !important; }

            /* Ensure back link is compact */
            .mb-8 .group.inline-flex { gap: 0.5rem !important; }
            .mb-8 .group.inline-flex span.ml-2 { font-size: 0.9rem !important; }
                /* Add top offset for back link so it's visible under fixed nav on mobile */
                .back-mobile-offset-wrap { margin-top: 4.5rem !important; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <!-- Decorative Background Orbs -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>

    <?php include '../includes/resident_nav.php'; ?>

    <?php
    // Relative time helper (mirrors secretary version)
    function relative_time($datetime){
        $ts = is_numeric($datetime)? $datetime : strtotime($datetime);
        $diff = time()-$ts; if($diff<60) return 'just now';
        $units=[31536000=>'year',2592000=>'month',604800=>'week',86400=>'day',3600=>'hour',60=>'minute'];
        foreach($units as $secs=>$label){ if($diff>=$secs){ $val=floor($diff/$secs); return $val.' '.$label.($val>1?'s':'').' ago'; } }
        return 'just now';
    }
    $type = $notification['type'] ?? 'Notification';
    $isPriority = isset($notification['isPriority']) && (int)$notification['isPriority']===1;
    $map = [
        'Complaint'=>['icon'=>'fa-file-lines','bg'=>'bg-emerald-50','ring'=>'ring-emerald-100','accent'=>'text-emerald-600'],
        'Case'=>['icon'=>'fa-gavel','bg'=>'bg-amber-50','ring'=>'ring-amber-100','accent'=>'text-amber-600'],
        'Hearing'=>['icon'=>'fa-calendar-alt','bg'=>'bg-blue-50','ring'=>'ring-blue-100','accent'=>'text-blue-600'],
        'Unverified'=>['icon'=>'fa-user-circle','bg'=>'bg-rose-50','ring'=>'ring-rose-100','accent'=>'text-rose-600'],
        'Mediation Deadline'=>['icon'=>'fa-hourglass-half','bg'=>'bg-red-50','ring'=>'ring-red-100','accent'=>'text-red-600'],
        'Resolution Deadline'=>['icon'=>'fa-hourglass-half','bg'=>'bg-red-50','ring'=>'ring-red-100','accent'=>'text-red-600'],
        'Settlement Deadline'=>['icon'=>'fa-hourglass-half','bg'=>'bg-red-50','ring'=>'ring-red-100','accent'=>'text-red-600'],
        'Case Deadline'=>['icon'=>'fa-hourglass-half','bg'=>'bg-red-50','ring'=>'ring-red-100','accent'=>'text-red-600'],
        'Deadline Overdue'=>['icon'=>'fa-triangle-exclamation','bg'=>'bg-red-50','ring'=>'ring-red-100','accent'=>'text-red-600'],
    ];
    $style = $map[$type] ?? ['icon'=>'fa-bell','bg'=>'bg-sky-50','ring'=>'ring-sky-100','accent'=>'text-sky-600'];
    ?>

    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3 back-mobile-offset-wrap">
            <a href="notifications.php" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50">
                    <i class="fa fa-arrow-left"></i>
                </span>
                <span class="ml-2">Back to Notifications</span>
            </a>
        </div>

        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
            </div>
            <header class="relative flex flex-row flex-wrap items-start gap-4 mb-8">
                <div class="flex items-center">
                    <div class="relative">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center <?=$style['bg']?> ring-4 <?=$style['ring']?> shadow-inner">
                            <i class="fa <?=$style['icon']?> text-3xl <?=$style['accent']?>"></i>
                        </div>
                        <?php if($isPriority): ?>
                            <div class="absolute -top-2 -right-2 bg-red-600 text-white text-[10px] font-semibold px-2 py-1 rounded-full shadow uppercase tracking-wide">High</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">
                            <?= htmlspecialchars($notification['title'] ?? 'Notification') ?>
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-white/70 border border-primary-100 text-primary-700 shadow-sm">
                            <i class="fa <?=$style['icon']?>"></i>
                            <?= htmlspecialchars($type) ?>
                        </span>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-clock"></i> <?= date('F d, Y • h:i A', strtotime($notification['created_at'])) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hourglass-half"></i> <?= relative_time($notification['created_at']) ?></span>
                        <span class="inline-flex items-center gap-1 <?= $isPriority ? 'text-red-600 font-medium' : '' ?>"></span>
                    </div>
                </div>
            </header>

            <div class="space-y-10">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Message</h2>
                    <div class="relative rounded-xl border border-primary-100/60 bg-white/80 p-5 leading-relaxed text-gray-700 shadow-sm">
                        <div class="absolute -top-3 left-5 px-2 text-[10px] font-semibold tracking-wide uppercase bg-primary-100 text-primary-700 rounded-full">Content</div>
                        <p class="whitespace-pre-line"><?= nl2br(htmlspecialchars($notification['message'] ?? '')) ?></p>
                    </div>
                </div>
                <?php if($complaint):
                    $compId = $complaint['complaint_id'] ?? $complaint['Complaint_ID'] ?? null;
                    $compTitle = $complaint['complaint_title'] ?? $complaint['Complaint_Title'] ?? '';
                    $compStatus = $complaint['status'] ?? $complaint['Status'] ?? '';
                    $compDate = $complaint['date_filed'] ?? $complaint['Date_Filed'] ?? '';
                    $compDesc = $complaint['description'] ?? $complaint['Description'] ?? '';
                    $compF = $complaint['First_Name'] ?? $complaint['first_name'] ?? '';
                    $compL = $complaint['Last_Name'] ?? $complaint['last_name'] ?? '';
                ?>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase">Complaint Details</h2>
                        <span class="text-xs px-2 py-1 rounded-md bg-amber-100 text-amber-700 font-medium">Linked</span>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complaint ID</p>
                            <p class="font-semibold text-gray-800">C<?= date('Y') ?>-<?= str_pad($compId,3,'0',STR_PAD_LEFT) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complainant</p>
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars(trim($compF.' '.$compL)) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Status</p>
                            <p class="inline-flex items-center gap-1 text-sm font-semibold <?= strtolower($compStatus)==='pending' ? 'text-amber-600':'text-emerald-600' ?>"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($compStatus) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Date Filed</p>
                            <p class="font-semibold text-gray-800"><?= $compDate ? date('F d, Y', strtotime($compDate)) : '' ?></p>
                        </div>
                        <?php if(!empty($compDesc)): ?>
                        <div class="md:col-span-2 rounded-xl border bg-white/70 border-gray-200 p-5 shadow-sm">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-2">Description</p>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($compDesc)) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pt-4 border-t border-dashed border-primary-200/60 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex gap-2">
                        <a href="notifications.php" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>

                        <?php if ($isConversion && !empty($targetCaseId) && (int)$targetCaseId > 0): ?>
                            <a href="./case_details.php?case_id=<?= (int)$targetCaseId ?>" 
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white shadow-sm text-sm font-medium transition">
                                <i class="fa fa-gavel"></i> Open Case
                            </a>
                        <?php endif; ?>

                        <!-- Show Open Complaint only if NOT a case or arbitration -->
                        <?php
                                if (
                                    $complaint &&
                                    stripos($type, 'case') === false &&
                                    stripos($type, 'arbitration') === false &&
                                    isset($complaint['resident_id']) &&
                                    $complaint['resident_id'] == $resident_id
                                ):
                            ?>
                                <a href="./view_complaint_details.php?id=<?= $refId ?>" 
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition">
                                    <i class="fa fa-folder-open"></i> Open Complaint
                                </a>
                            <?php endif; ?>
                        <!-- Show Open Case when we resolved a valid case id from the notification (avoid duplicate if conversion button already shown) -->
                        <?php if (!empty($targetCaseId) && (int)$targetCaseId > 0 && !$isConversion): ?>
                            <a href="./case_details.php?case_id=<?= (int)$targetCaseId ?>" 
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
