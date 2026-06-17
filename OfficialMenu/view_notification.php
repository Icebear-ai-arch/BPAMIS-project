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

// Mark as read for Captain context: prefer is_read_captain, fallback to is_read
function ensure_column(mysqli $conn, string $table, string $column, string $definition): bool {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows > 0) return true;
    return (bool)$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

$useCaptainFlag = ensure_column($conn, 'notifications', 'is_read_captain', 'TINYINT(1) NOT NULL DEFAULT 0');
if ($useCaptainFlag) {
    $stmt2 = $conn->prepare("UPDATE notifications SET is_read_captain = 1 WHERE notification_id = ?");
    $stmt2->bind_param("i", $notificationId);
    $stmt2->execute();
    $stmt2->close();
} else {
    // Fallback to legacy is_read if captain-specific column cannot be created
    $stmt2 = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt2->bind_param("i", $notificationId);
    $stmt2->execute();
    $stmt2->close();
}

// Fetch complaint info if this is a complaint-type notification
$complaint = null;

if ($notification['type'] === 'Complaint' && !empty($notification['reference_id'])) {
    $complaint_id = intval($notification['reference_id']);
    $query = "SELECT c.*, r.First_Name, r.Last_Name
              FROM complaint_info c
              JOIN resident_info r ON c.Resident_ID = r.Resident_ID
              WHERE c.Complaint_ID = $complaint_id";
    $result_complaint = $conn->query($query);
    if ($result_complaint && $result_complaint->num_rows > 0) {
        $complaint = $result_complaint->fetch_assoc();
    }
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
    <!-- Font Awesome CSS for icons -->
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
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .glass { padding: 1rem !important; }
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

    <?php include '../includes/barangay_official_cap_nav.php'; ?>
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

    <main class="relative z-10 max-w-5xl mx-auto px-3 sm:px-4 md:px-8 pt-6 sm:pt-8 md:pt-10 pb-16 sm:pb-20 md:pb-24 animate-fade-in">
        <div class="mb-4 sm:mb-6 md:mb-8 flex items-center gap-2 sm:gap-3">
            <a href="notifications-captain.php"
                class="group inline-flex items-center text-xs sm:text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span
                    class="inline-flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50">
                    <i class="fa fa-arrow-left text-xs sm:text-sm"></i>
                </span>
                <span class="ml-1.5 sm:ml-2">Back to Notifications</span>
            </a>
        </div>

        <section
            class="relative glass shadow-glow rounded-xl sm:rounded-2xl p-4 sm:p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40">
                </div>
            </div>

            <header class="relative flex flex-col md:flex-row md:items-start gap-4 sm:gap-5 md:gap-6 mb-6 sm:mb-7 md:mb-8">
                <div class="flex items-center">
                    <div class="relative">
                        <div
                            class="w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-xl sm:rounded-2xl flex items-center justify-center <?php echo $style['bg']; ?> ring-2 sm:ring-3 md:ring-4 <?php echo $style['ring']; ?> shadow-inner">
                            <i class="fa <?php echo $style['icon']; ?> text-xl sm:text-2xl md:text-3xl <?php echo $style['accent']; ?>"></i>
                        </div>
                        <?php if ($isPriority): ?>
                            <div
                                class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 bg-red-600 text-white text-[9px] sm:text-[10px] font-semibold px-1.5 py-0.5 sm:px-2 sm:py-1 rounded-full shadow uppercase tracking-wide">
                                High</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1
                        class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-2 sm:gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500 break-words">
                            <?= htmlspecialchars($notification['title']) ?: 'Notification' ?>
                        </span>
                        <span
                            class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-medium px-2 py-0.5 sm:px-3 sm:py-1 rounded-full bg-white/70 border border-primary-100 text-primary-700 shadow-sm">
                            <i class="fa <?php echo $style['icon']; ?>"></i>
                            <span class="hidden sm:inline"><?= htmlspecialchars($notification['type']) ?></span>
                        </span>
                    </h1>
                    <div class="mt-2 sm:mt-3 flex flex-wrap items-center gap-2 sm:gap-3 md:gap-4 text-xs sm:text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-clock text-[10px] sm:text-xs"></i>
                            <span class="hidden sm:inline"><?= date("F d, Y • h:i A", strtotime($notification['created_at'])) ?></span>
                            <span class="sm:hidden"><?= date("M d, Y", strtotime($notification['created_at'])) ?></span>
                        </span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hourglass-half text-[10px] sm:text-xs"></i>
                            <?= relative_time($notification['created_at']) ?></span>
                        <span
                            class="inline-flex items-center gap-1 <?php echo $isPriority ? 'text-red-600 font-medium' : ''; ?>">

                        </span>

                    </div>
                </div>
            </header>

            <div class="space-y-6 sm:space-y-8 md:space-y-10">
                <div>
                    <h2 class="text-xs sm:text-sm font-semibold tracking-wider text-gray-500 uppercase mb-2 sm:mb-3">Message</h2>
                    <div
                        class="relative rounded-lg sm:rounded-xl border border-primary-100/60 bg-white/80 p-3 sm:p-4 md:p-5 leading-relaxed text-xs sm:text-sm text-gray-700 shadow-sm">
                        <div
                            class="absolute -top-2 sm:-top-3 left-3 sm:left-5 px-1.5 sm:px-2 text-[9px] sm:text-[10px] font-semibold tracking-wide uppercase bg-primary-100 text-primary-700 rounded-full">
                            Content</div>
                        <p class="whitespace-pre-line"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                    </div>
                </div>

                <?php if ($complaint): ?>
                    <div>
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <h2 class="text-xs sm:text-sm font-semibold tracking-wider text-gray-500 uppercase">Complaint Details</h2>
                            <span class="text-[10px] sm:text-xs px-1.5 py-0.5 sm:px-2 sm:py-1 rounded-md bg-amber-100 text-amber-700 font-medium">Linked</span>
                        </div>
                        <div class="grid gap-3 sm:gap-4 md:gap-5 md:grid-cols-2">
                            <div
                                class="group rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-3 sm:p-4 shadow-sm">
                                <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complaint ID
                                </p>
                                <p class="text-sm sm:text-base font-semibold text-gray-800">
                                    C<?= date('Y') ?>-<?= str_pad($complaint['Complaint_ID'], 3, '0', STR_PAD_LEFT) ?></p>
                            </div>
                            <div
                                class="group rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-3 sm:p-4 shadow-sm">
                                <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Complainant
                                </p>
                                <p class="text-sm sm:text-base font-semibold text-gray-800 truncate">
                                    <?= htmlspecialchars($complaint['First_Name'] . ' ' . $complaint['Last_Name']) ?></p>
                            </div>
                            <div
                                class="group rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-3 sm:p-4 shadow-sm">
                                <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Title</p>
                                <p class="text-sm sm:text-base font-semibold text-gray-800 line-clamp-2">
                                    <?= htmlspecialchars($complaint['Complaint_Title']) ?></p>
                            </div>
                            <div
                                class="group rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-3 sm:p-4 shadow-sm">
                                <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Status</p>
                                <p
                                    class="inline-flex items-center gap-1 text-xs sm:text-sm font-semibold <?php echo strtolower($complaint['Status']) === 'pending' ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                    <i class="fa fa-circle text-[6px] sm:text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?>
                                </p>
                            </div>
                            <div
                                class="group rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-3 sm:p-4 shadow-sm md:col-span-2">
                                <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-1">Date Filed</p>
                                <p class="text-sm sm:text-base font-semibold text-gray-800">
                                    <?= date("F d, Y", strtotime($complaint['Date_Filed'])) ?></p>
                            </div>
                            <?php if (!empty($complaint['Description'])): ?>
                                <div class="md:col-span-2 rounded-lg sm:rounded-xl border bg-white/70 border-gray-200 p-3 sm:p-4 md:p-5 shadow-sm">
                                    <p class="text-[10px] sm:text-[11px] uppercase tracking-wide text-gray-500 font-medium mb-2">Description
                                    </p>
                                    <p class="text-xs sm:text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($complaint['Description'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div
                    class="pt-3 sm:pt-4 border-t border-dashed border-primary-200/60 flex flex-wrap items-center justify-between gap-3 sm:gap-4">

                    <div class="flex gap-2">
                        <a href="notifications-captain.php"
                            class="inline-flex items-center gap-1.5 sm:gap-2 px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-xs sm:text-sm font-medium transition">
                            <i class="fa fa-arrow-left text-xs sm:text-sm"></i> <span>Back</span>
                        </a>
                        <?php if ($complaint): ?>
                            <a href="view_complaint_details.php?id=<?= $complaint['Complaint_ID'] ?>"
                                class="inline-flex items-center gap-1.5 sm:gap-2 px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-xs sm:text-sm font-medium transition">
                                <i class="fa fa-folder-open text-xs sm:text-sm"></i> <span class="hidden sm:inline">Open Complaint</span><span class="sm:hidden">Open</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
