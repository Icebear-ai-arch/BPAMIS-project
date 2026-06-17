<?php
// Bootstrap DB and session before any output
require_once(__DIR__ . '/../server/server.php');
include '../controllers/session_control.php';
// Initialize all stats
$complaintsCount = $resolvedCount = $pendingCount = $rejectedCount = 0;
$casesCount = $mediatedCount = $resolutionCount = $settlementCount = $closedCount = $resolvedCaseCount = 0;
$scheduledHearings = 0;

// Complaints Count
$complaintsQuery = "SELECT status, COUNT(*) as count FROM complaint_info GROUP BY status";
$result = $conn->query($complaintsQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int) $row['count'];
        $complaintsCount += $count;

        if ($status === 'resolved')
            $resolvedCount = $count;
        elseif ($status === 'pending')
            $pendingCount = $count;
        elseif ($status === 'rejected')
            $rejectedCount = $count;
    }
}

// Cases Count
$caseQuery = "SELECT case_status as status, COUNT(*) as count FROM case_info GROUP BY case_status";
$result = $conn->query($caseQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int) $row['count'];
        $casesCount += $count;

        if ($status === 'mediation')
            $mediatedCount = $count;
        elseif ($status === 'resolution')
            $resolutionCount = $count;
        elseif ($status === 'settlement')
            $settlementCount = $count;
        elseif ($status === 'close')
            $closedCount = $count;
        elseif ($status === 'resolved')
            $resolvedCaseCount = $count;
    }
}

// Hearings Count
$hearingQuery = "SELECT COUNT(*) as count FROM schedule_list";
$result = $conn->query($hearingQuery);
if ($result && $row = $result->fetch_assoc()) {
    $scheduledHearings = (int) $row['count'];
}

// Progress percentages (guard division by zero)
$complaintResolvedPercent = $complaintsCount ? round(($resolvedCount / $complaintsCount) * 100) : 0;
$complaintPendingPercent  = $complaintsCount ? round(($pendingCount / $complaintsCount) * 100) : 0;
$complaintRejectedPercent = $complaintsCount ? round(($rejectedCount / $complaintsCount) * 100) : 0;
$caseResolvedPercent      = $casesCount ? round(($resolvedCaseCount / $casesCount) * 100) : 0;
// Approximate hearing progress relative to open cases
$hearingPercent = $casesCount ? min(100, round(($scheduledHearings / max($casesCount, 1)) * 100)) : 0;

// ========== RECENT ACTIVITY ==========
function getComplainantName($conn, $resident_id, $external_id)
{
    if (!empty($resident_id)) {
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM resident_info WHERE resident_id = ?");
        $stmt->bind_param("i", $resident_id);
    } else {
        $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM external_complainant WHERE external_complaint_id = ?");
        $stmt->bind_param("i", $external_id);
    }
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt)->fetch_assoc();
    return $result ? $result['first_name'] . ' ' . $result['middle_name'] . ' ' . $result['last_name'] : 'Unknown';
}

$recentActivities = [];
$query = "SELECT complaint_id, resident_id, external_complainant_id, date_filed FROM complaint_info ORDER BY date_filed DESC LIMIT 5";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $name = getComplainantName($conn, $row['resident_id'], $row['external_complainant_id']);
    $timeAgo = time() - strtotime($row['date_filed']);
    $hoursAgo = floor($timeAgo / 3600);
    $recentActivities[] = [
        'type' => 'complaint',
        'message' => "New complaint filed by $name",
        'time' => $row['date_filed']
    ];
}

// ========== MONTHLY STATS ==========
$monthlyLabels = [];
$monthlyComplaints = [];
$monthlyCases = [];
$monthlyMediation = [];
$monthlyResolution = [];
$monthlySettlement = [];
$monthlyClosed = [];
$monthlyResolved = [];
$monthlyRejected = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $monthlyLabels[] = date('M Y', strtotime($month));

    // Complaints per month
    $qC = $conn->query("SELECT COUNT(*) AS count FROM complaint_info WHERE DATE_FORMAT(date_filed, '%Y-%m') = '$month'");
    $monthlyComplaints[] = $qC ? (int)$qC->fetch_assoc()['count'] : 0;

    $qRj = $conn->query("SELECT COUNT(*) AS count FROM complaint_info WHERE status = 'rejected' AND DATE_FORMAT(date_filed, '%Y-%m') = '$month'");
    $monthlyRejected[] = $qRj ? (int)$qRj->fetch_assoc()['count'] : 0;

    // Cases per month by status (based on complaint date_filed)
    $qCases = $conn->query("SELECT case_status, COUNT(*) as count FROM case_info c JOIN complaint_info ci ON c.complaint_id = ci.complaint_id WHERE DATE_FORMAT(ci.date_filed, '%Y-%m') = '$month' GROUP BY case_status");
    $totalCases = 0; $med = 0; $res = 0; $set = 0; $cls = 0; $rsd = 0;
    if ($qCases) {
        while ($r = $qCases->fetch_assoc()) {
            $st = strtolower(trim($r['case_status']));
            $ct = (int)$r['count'];
            $totalCases += $ct;
            if ($st === 'mediation') $med = $ct;
            elseif ($st === 'resolution') $res = $ct;
            elseif ($st === 'settlement') $set = $ct;
            elseif ($st === 'close') $cls = $ct;
            elseif ($st === 'resolved') $rsd = $ct;
        }
    }
    $monthlyCases[] = $totalCases;
    $monthlyMediation[] = $med; $monthlyResolution[] = $res; $monthlySettlement[] = $set; $monthlyClosed[] = $cls; $monthlyResolved[] = $rsd;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Dashboard</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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
        /* Prevent horizontal scroll caused by off-canvas elements or wide children */
        html, body { overflow-x: hidden; margin: 0; height: 100%; box-sizing: border-box; }
        body { min-height: 100vh; background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
        .orb { position: absolute; border-radius: 50%; filter: blur(40px); opacity: .55; mix-blend-mode: multiply; }
        .orb.one { width: 480px; height: 480px; background: linear-gradient(135deg, #0c9ced, #7cccfd); top: -140px; right: -120px; animation: float 14s ease-in-out infinite; }
        .orb.two { width: 360px; height: 360px; background: linear-gradient(135deg, #bae2fd, #e0effe); bottom: -120px; left: -100px; animation: float 11s ease-in-out reverse infinite; }
        .glass { backdrop-filter: blur(14px); background: linear-gradient(135deg, rgba(255, 255, 255, .65), rgba(255, 255, 255, .35)); border: 1px solid rgba(255, 255, 255, .45); box-shadow: 0 10px 40px -12px rgba(12, 156, 237, .25), 0 4px 18px -6px rgba(12, 156, 237, .18); }
        .stat-chip { display:inline-flex; align-items:center; font-size:.75rem; font-weight:600; padding:.25rem .5rem; border-radius:.5rem; background:rgba(255,255,255,.6); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,.4); box-shadow:0 1px 2px rgba(0,0,0,.05); }
        .progress-wrap { height: 12px; }
        .progress-bar { transition: width 1s cubic-bezier(.4, .0, .2, 1); box-shadow: 0 0 0 1px rgba(255,255,255,.4), 0 2px 6px -1px rgba(12,156,237,.25); }
        .section-label { font-size: .65rem; letter-spacing: .09em; font-weight: 600; text-transform: uppercase; color: #0369a1; }
        .quick-btn { position: relative; overflow: hidden; transition: transform .2s ease; }
        .quick-btn:before { content:""; position:absolute; inset:0; background: linear-gradient(120deg, rgba(255,255,255,.6), rgba(255,255,255,0)); opacity:0; transition: opacity .4s; }
        .quick-btn:hover:before { opacity:1; }
        .quick-btn:hover { transform: translateY(-4px); }
        .fade-in { animation: fade .6s ease; }
        @keyframes fade { from { opacity:0; transform: translateY(8px);} to{ opacity:1; transform:none;} }
        /* Loader */
        .loader-wrapper { position: fixed; inset: 0; background: #fff; display:flex; justify-content:center; align-items:center; z-index: 999999; transition: opacity .6s; }
        .loader { position: relative; width: 120px; height: 120px; }
        .loader-gradient { position: absolute; inset: 0; border-radius: 50%; background: conic-gradient(#60a5fa 0deg, rgba(37,100,235,.66) 120deg, rgba(30,64,175,.34) 240deg, #60a5fa 360deg); animation: spin 1.2s linear infinite; }
        .loader-inner { position: absolute; inset: 10px; background: rgba(255, 255, 255, .85); border-radius: 50%; }
        .loader-logo { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 58px; }
        @keyframes spin { to { transform: rotate(360deg);} }
        .fade-out { opacity: 0; pointer-events: none; }
        /* Mobile: keep loader perfectly centered and smaller */
        @media (max-width: 640px) {
            .loader-wrapper {
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .loader {
                width: 88px;
                height: 88px;
            }
            .loader-inner { inset: 8px; }
            .loader-logo { width: 42px; }
        }

        @media (max-width: 480px) {
            .loader { width: 72px; height: 72px; }
            .loader-inner { inset: 6px; }
            .loader-logo { width: 34px; }
        }

        /* Calendar refine */
        .calendar-container { --fc-border-color: transparent; }
        .calendar-container .fc-theme-standard th { border:none; font-size:.65rem; letter-spacing:.08em; text-transform:uppercase; color:#0c4a6e; background:transparent; }
        .calendar-container .fc-daygrid-day { background: rgba(255,255,255,.55); backdrop-filter: blur(6px); }

        /* Disable horizontal scroll on mobile */
        @media (max-width: 640px) {
            html, body { 
                overflow-x: hidden !important; 
                width: 100%; 
                height: 100%;
                overflow-y: auto !important;
            }

            /* Ensure top-level containers can't cause overflow */
            .max-w-7xl,
            .glass,
            .grid,
            .fade-in { 
                overflow-x: hidden !important;
                overflow-y: visible !important;
            }

            /* Make media scale responsively */
            img, svg, canvas, iframe { max-width: 100%; width: 100%; height: auto; }

            /* Reduce decorative orbs on small screens to avoid overflow */
            .orb.one { right: -40px; top: -80px; width: 220px; height: 220px; }
            .orb.two { left: -40px; bottom: -80px; width: 180px; height: 180px; }

            /* Ensure body uses native smooth scrolling on iOS/Android */
            body { -webkit-overflow-scrolling: touch; }
        }
    </style>
</head>

<body class="font-sans text-gray-700">

   

    <!-- Loader -->
    <div class="loader-wrapper" id="page-loader">
        <div class="loader">
            <div class="loader-gradient"></div>
            <div class="loader-inner"></div>
            <img src="../Assets/Img/logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>

    <?php include '../includes/barangay_official_cap_nav.php'; ?>

    <!-- HEADER / INTRO -->
    <div class="max-w-screen-2xl mx-auto px-5 pt-10 relative">
        <div class="glass rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 lg:p-12 overflow-visible fade-in">
            <div class="absolute inset-0 pointer-events-none overflow-hidden rounded-2xl sm:rounded-3xl">
                <div class="absolute -top-20 -right-10 w-80 h-80 bg-gradient-to-br from-primary-200/70 to-primary-400/40 rounded-full blur-3xl opacity-60"></div>
                <div class="absolute -bottom-24 -left-10 w-72 h-72 bg-gradient-to-tr from-primary-100/60 via-white/40 to-primary-300/40 rounded-full blur-3xl"></div>
            </div>
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 sm:gap-4 md:gap-6">
                    <div class="min-w-0">
                        <p class="section-label mb-1 sm:mb-2">Captain Dashboard</p>
                        <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold tracking-tight text-sky-900">Welcome back<span class="font-light">,</span></h1>
                        <p class="mt-1 sm:mt-2 text-sm sm:text-base md:text-lg text-sky-800 font-medium truncate">
                            <?= isset($_SESSION['official_name']) ? htmlspecialchars($_SESSION['official_name']) : 'Barangay Captain' ?>
                        </p>
                        <p class="mt-2 sm:mt-3 max-w-xl text-[11px] sm:text-xs md:text-sm lg:text-base text-sky-700/80">Oversee complaints, cases, and hearings with a refined, real‑time dashboard.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 md:gap-4 w-full md:w-auto shrink-0">
                        <a href="feedback_captain.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1 hover:shadow-lg">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-sky-700"><i class="fa-solid fa-square-plus text-sky-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">New</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-sky-900">Feedback</span>
                        </a>
                        <a href="view_complaints.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-emerald-700"><i class="fa-solid fa-file-lines text-emerald-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-emerald-900">Complaints</span>
                        </a>
                        <a href="view_cases.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-amber-700"><i class="fa-solid fa-gavel text-amber-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-amber-900">Cases</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="max-w-screen-2xl mx-auto px-5 mt-10 pb-8 space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-stretch">
            <!-- Left: Upcoming Hearings (wider) -->
            <div class="lg:col-span-8 w-full order-1">
                <div class="glass rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 lg:p-7 fade-in h-full flex flex-col min-h-[500px] sm:min-h-[560px] md:min-h-[600px]">
                    <div class="flex items-center justify-between mb-3 sm:mb-4 md:mb-5">
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <i class="fas fa-calendar text-primary-500 text-sm sm:text-base"></i>
                            <h2 class="text-sky-900 font-semibold tracking-tight text-sm sm:text-base">Upcoming Hearings</h2>
                        </div>
                       <a href="view_hearing_calendar_captain.php" title="Open full calendar"
                       class="ml-auto inline-flex items-center justify-center h-8 w-8 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700 hover:text-sky-900 shadow-sm transition"
                       aria-label="Open full calendar">
                         <i class="fas fa-expand"></i>
                    </a>
                    </div>
                    <div class="mt-2 flex-1 min-h-[430px] sm:min-h-[500px] md:min-h-[540px]">
                        <iframe id="dashboard-calendar-iframe" src="../SecMenu/schedule/CalendarCaptain.php" class="w-full h-full rounded-lg sm:rounded-xl border border-white/40 shadow-sm" style="background: transparent;" loading="lazy" tabindex="0"></iframe>
                    </div>
                </div>
            </div>
            <!-- Right: Statistics (narrower) -->
            <div class="lg:col-span-4 w-full order-2">
                <div class="glass rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 lg:p-7 fade-in h-full flex flex-col min-h-[400px] sm:min-h-[520px] md:min-h-[600px]">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-3 sm:mb-4 md:mb-5">
                        <i class="fa-solid fa-chart-simple text-sky-600 text-sm sm:text-base"></i>
                        <h2 class="text-sky-900 font-semibold tracking-tight text-sm sm:text-base">Statistics</h2>
                    </div>
                    <div class="space-y-4 sm:space-y-5 md:space-y-6 flex-1">
                        <!-- Complaints -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5 sm:mb-2">
                                <span class="text-[10px] sm:text-xs font-semibold text-sky-800 tracking-wide">Total Complaints</span>
                                <span class="text-[9px] sm:text-[11px] font-semibold px-1.5 py-0.5 sm:px-2 rounded-md bg-sky-100 text-sky-700"><?= $complaintsCount ?></span>
                            </div>
                            <div class="space-y-1.5 sm:space-y-2">
                                <div>
                                    <div class="flex justify-between text-[9px] sm:text-[10px] font-medium text-emerald-700 mb-0.5"><span>Resolved</span><span><?= $resolvedCount ?></span></div>
                                    <div class="w-full h-2 sm:h-2.5 rounded-full bg-emerald-50 overflow-hidden"><div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 progress-bar" style="width: <?= $complaintResolvedPercent ?>%"></div></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[9px] sm:text-[10px] font-medium text-amber-700 mb-0.5"><span>Pending</span><span><?= $pendingCount ?></span></div>
                                    <div class="w-full h-2 sm:h-2.5 rounded-full bg-amber-50 overflow-hidden"><div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 progress-bar" style="width: <?= $complaintPendingPercent ?>%"></div></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[9px] sm:text-[10px] font-medium text-rose-700 mb-0.5"><span>Rejected</span><span><?= $rejectedCount ?></span></div>
                                    <div class="w-full h-2 sm:h-2.5 rounded-full bg-rose-50 overflow-hidden"><div class="h-full bg-gradient-to-r from-rose-400 to-rose-500 progress-bar" style="width: <?= $complaintRejectedPercent ?>%"></div></div>
                                </div>
                            </div>
                        </div>
                        <!-- Cases -->
                        <div>
                            <div class="flex justify-between mb-1 sm:mb-1.5 text-[10px] sm:text-xs font-medium text-emerald-800"><span>Total Cases</span><span class="px-1.5 py-0.5 sm:px-2 rounded-md bg-emerald-100 text-emerald-700"><?= $casesCount ?></span></div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden"><div class="progress-bar bg-gradient-to-r from-emerald-400 to-emerald-500 h-full" style="width: <?= $caseResolvedPercent ?>%"></div></div>
                            <p class="text-[9px] sm:text-[10px] mt-0.5 sm:mt-1 text-emerald-800/70">Mediation: <?= $mediatedCount ?> • Resolution: <?= $resolutionCount ?> • Settlement: <?= $settlementCount ?> • Closed: <?= $closedCount ?> • Resolved: <?= $resolvedCaseCount ?></p>
                        </div>
                        <!-- Hearings -->
                        <div>
                            <div class="flex justify-between mb-1 sm:mb-1.5 text-[10px] sm:text-xs font-medium text-rose-800"><span>Scheduled Hearings</span><span class="px-1.5 py-0.5 sm:px-2 rounded-md bg-rose-100 text-rose-700"><?= $scheduledHearings ?></span></div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden"><div class="progress-bar bg-gradient-to-r from-rose-400 to-rose-500 h-full" style="width: <?= $hearingPercent ?>%"></div></div>
                            <p class="text-[9px] sm:text-[10px] mt-0.5 sm:mt-1 text-rose-800/70">Relative to open cases</p>
                        </div>
                        <!-- Summary chips -->
                        <div class="grid grid-cols-2 gap-2 sm:gap-3 pt-1 sm:pt-2">
                            <div class="glass rounded-lg sm:rounded-xl p-2.5 sm:p-3 md:p-4 flex items-start gap-2 sm:gap-3"><div class="h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600"><i class="fa-solid fa-circle-check text-xs sm:text-sm"></i></div><div><p class="text-[8px] sm:text-[9px] md:text-[10px] tracking-wide uppercase font-semibold text-emerald-700">Resolved Cases</p><p class="text-sm sm:text-base md:text-lg leading-snug font-semibold text-emerald-800"><?= $resolvedCaseCount ?></p></div></div>
                            <div class="glass rounded-lg sm:rounded-xl p-2.5 sm:p-3 md:p-4 flex items-start gap-2 sm:gap-3"><div class="h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600"><i class="fa-solid fa-hourglass-half text-xs sm:text-sm"></i></div><div><p class="text-[8px] sm:text-[9px] md:text-[10px] tracking-wide uppercase font-semibold text-amber-700">Pending Complaints</p><p class="text-sm sm:text-base md:text-lg leading-snug font-semibold text-amber-800"><?= $pendingCount ?></p></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent + Chart -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 mt-4 sm:mt-6 mb-6 sm:mb-8">
            <div class="md:col-span-5 glass rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 lg:p-7 fade-in">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-3 sm:mb-4 md:mb-5"><i class="fas fa-bell text-primary-500 text-sm sm:text-base"></i><h2 class="text-sky-900 font-semibold tracking-tight text-sm sm:text-base">Recent Activity</h2></div>
                <div class="space-y-2.5 sm:space-y-3 md:space-y-4">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $act): ?>
                            <div class="flex items-start">
                                <div class="bg-blue-100 p-1.5 sm:p-2 rounded-full mr-2 sm:mr-3 mt-0.5 sm:mt-1"><i class="fas fa-plus text-blue-500 text-[10px] sm:text-xs md:text-sm"></i></div>
                                <div>
                                    <p class="text-xs sm:text-sm font-medium"><?= htmlspecialchars($act['message']) ?></p>
                                    <p class="text-[10px] sm:text-xs text-gray-500"><?= htmlspecialchars($act['time']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs sm:text-sm text-gray-500">No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="md:col-span-7 glass rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 lg:p-7 fade-in">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-3 sm:mb-4 md:mb-5"><i class="fas fa-chart-line text-primary-500 text-sm sm:text-base"></i><h2 class="text-sky-900 font-semibold tracking-tight text-sm sm:text-base">6‑Month Trends</h2></div>
                <canvas id="statsChart" class="w-full" height="200"></canvas>
            </div>
        </div>
    </div>

    <?php include 'sidebar_.php'; ?>
    <script>
        // Sidebar submenu toggles
        document.querySelectorAll('.toggle-menu').forEach(button => {
            button.addEventListener('click', () => {
                const submenu = button.nextElementSibling;
                submenu.classList.toggle('hidden');
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Hide loader
            const loader = document.getElementById('page-loader');
            if (loader) setTimeout(() => loader.classList.add('fade-out'), 400);

            // Ensure collapsed submenus are not marked active
            document.querySelectorAll('.submenu').forEach(submenu => { if (submenu.classList.contains('hidden')) submenu.classList.remove('active'); });

            // Sidebar toggle + overlay
            const menuBtn = document.getElementById('menu-btn');
            const closeBtn = document.getElementById('close-sidebar');
            function addSidebarOverlay(){ if (!document.getElementById('sidebar-overlay')) { const overlay=document.createElement('div'); overlay.id='sidebar-overlay'; overlay.className='fixed inset-0 bg-black bg-opacity-30 z-40'; document.body.appendChild(overlay); overlay.addEventListener('click',()=>{ document.getElementById('sidebar').classList.add('-translate-x-full'); removeSidebarOverlay(); }); } }
            function removeSidebarOverlay(){ const overlay=document.getElementById('sidebar-overlay'); if (overlay) overlay.remove(); }
            if (menuBtn) menuBtn.addEventListener('click', ()=>{ const sidebar=document.getElementById('sidebar'); if(sidebar){ sidebar.classList.remove('-translate-x-full'); addSidebarOverlay(); } });
            if (closeBtn) closeBtn.addEventListener('click', ()=>{ const sidebar=document.getElementById('sidebar'); if(sidebar){ sidebar.classList.add('-translate-x-full'); removeSidebarOverlay(); } });

            // Submenu animations and states
            document.querySelectorAll('.toggle-menu').forEach(button => {
                button.addEventListener('click', function(){
                    let submenu = this.nextElementSibling;
                    submenu.classList.toggle('hidden');
                    if (!submenu.classList.contains('hidden')) { setTimeout(()=> submenu.classList.add('active'), 10); } else { submenu.classList.remove('active'); }
                    const chevron = this.querySelector('.fa-chevron-down'); if (chevron) chevron.classList.toggle('rotate-180');
                    this.classList.toggle('bg-primary-50'); this.classList.toggle('text-primary-700');
                });
            });

            // Initialize chart (real data like secretary)
            const ctx = document.getElementById('statsChart');
            if (ctx) {
                const chart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($monthlyLabels) ?>,
                        datasets: [
                            { label: 'Complaints', data: <?= json_encode($monthlyComplaints) ?>, borderColor: '#0c9ced', backgroundColor: 'rgba(12,156,237,.12)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Cases', data: <?= json_encode($monthlyCases) ?>, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.12)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Mediation', data: <?= json_encode($monthlyMediation) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.10)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Resolution', data: <?= json_encode($monthlyResolution) ?>, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.10)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Settlement', data: <?= json_encode($monthlySettlement) ?>, borderColor: '#14b8a6', backgroundColor: 'rgba(20,184,166,.10)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Closed', data: <?= json_encode($monthlyClosed) ?>, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.10)', borderWidth:2, tension:.4, fill:true },
                            { label: 'Resolved Cases', data: <?= json_encode($monthlyResolved) ?>, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.10)', borderWidth:2, tension:.4, fill:true }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { 
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    boxWidth: window.innerWidth < 640 ? 10 : 14, 
                                    usePointStyle: true,
                                    font: {
                                        size: window.innerWidth < 640 ? 9 : 11
                                    },
                                    padding: window.innerWidth < 640 ? 8 : 10
                                } 
                            } 
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true, 
                                grid: { color: 'rgba(0,0,0,.05)' },
                                ticks: {
                                    font: {
                                        size: window.innerWidth < 640 ? 9 : 11
                                    }
                                }
                            }, 
                            x: { 
                                grid: { display: false },
                                ticks: {
                                    font: {
                                        size: window.innerWidth < 640 ? 9 : 11
                                    }
                                }
                            } 
                        }
                    }
                });
            }

            // Calendar quick button opens embedded calendar section (no navigation)
            var openBtn = document.getElementById('open-calendar-btn');
            if (openBtn) {
                openBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    var container = document.querySelector('.lg\\:col-span-8');
                    if (container) {
                        container.scrollIntoView({behavior:'smooth', block:'center'});
                        // Try focusing the iframe for keyboard users
                        var iframe = document.getElementById('dashboard-calendar-iframe');
                        if (iframe) {
                            try { if (iframe.contentWindow && typeof iframe.contentWindow.focus === 'function') iframe.contentWindow.focus(); else iframe.focus(); } catch(_) { try { iframe.focus(); } catch(__){} }
                        }
                    } else {
                        // Fallback: open calendar in new tab
                        window.open('../SecMenu/schedule/CalendarCaptain.php', '_blank');
                    }
                });
            }
        });
    </script>

    <!-- Event detail modal (opened by calendar iframe via postMessage or direct call) -->
    <div id="event-detail-modal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;" role="dialog" aria-modal="true" aria-label="Event Details">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl w-full max-w-md mx-4 p-4 sm:p-6 transform transition-all duration-300 border border-gray-200" style="z-index: 100000 !important;">
            <button id="close-event-detail"
                class="absolute top-3 right-3 sm:top-4 sm:right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-base sm:text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform"
                title="Close details">&times;</button>
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-sky-400 to-blue-500 rounded-t-2xl"></div>
            <div class="mt-2" id="event-detail-content"></div>
        </div>
    </div>

    <script>
        // Allow CalendarSec (iframe) to request opening an event modal in this parent page.
        window.openEventModal = function(payload) {
            try {
                var modal = document.getElementById('event-detail-modal');
                var content = document.getElementById('event-detail-content');
                if (!modal || !content) return;
                // payload: { title, content }
                var titleHtml = payload && payload.title 
                    ? '<h3 id="modalTitle" class="text-base sm:text-xl font-semibold mb-3 sm:mb-4 text-blue-700 border-b border-gray-200 pb-2">' + (payload.title) + '</h3>' 
                    : '';
                var contentHtml = payload.content 
                    ? '<div class="text-xs sm:text-sm text-gray-700 space-y-2 sm:space-y-3 max-h-[70vh] overflow-y-auto">' + payload.content + '</div>'
                    : '';
                content.innerHTML = titleHtml + contentHtml;
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
                document.documentElement.classList.add('overflow-hidden');
                document.body.classList.add('overflow-hidden');
            } catch (e) { console.error('openEventModal error', e); }
        };

        // Listen for postMessage from iframe (CalendarSec)
        window.addEventListener('message', function(e){
            try {
                if (!e.data) return;
                if (e.data.type === 'openEventModal' && e.data.payload) {
                    // Sanitize title/content if necessary; CalendarSec already escapes HTML where appropriate
                    window.openEventModal(e.data.payload);
                }
            } catch (err) { console.error('message handler error', err); }
        }, false);

        // Close handlers
        (function(){
            var closeBtn = document.getElementById('close-event-detail');
            var modal = document.getElementById('event-detail-modal');
            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function(){ modal.classList.add('hidden'); modal.style.display='none'; document.documentElement.classList.remove('overflow-hidden'); document.body.classList.remove('overflow-hidden'); });
                modal.addEventListener('click', function(ev){ if (ev.target === modal) { modal.classList.add('hidden'); modal.style.display='none'; document.documentElement.classList.remove('overflow-hidden'); document.body.classList.remove('overflow-hidden'); } });
            }
        })();
    </script>

    <?php include '../chatbot/bpamis_case_assistant.php'; ?>

   <?php include '../includes/footer.php' ?>

</body>

</html>