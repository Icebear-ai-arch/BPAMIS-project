<?php
include '../controllers/session_control.php';
include '../server/server.php';


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
        $count = (int)$row['count'];
        $complaintsCount += $count;

        if ($status === 'resolved') $resolvedCount = $count;
        elseif ($status === 'pending') $pendingCount = $count;
        elseif ($status === 'rejected') $rejectedCount = $count;
    }
}

// Cases Count
$caseQuery = "SELECT case_status as status, COUNT(*) as count FROM case_info GROUP BY case_status";
$result = $conn->query($caseQuery);
$conciliationCount = 0;
$arbitrationCount = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int)$row['count'];
        $casesCount += $count;

        // Track Lupon-relevant statuses primarily
        if ($status === 'conciliation') $conciliationCount = $count;
        elseif ($status === 'arbitration') $arbitrationCount = $count;
        // Keep other categories available if needed elsewhere
        elseif ($status === 'mediation') $mediatedCount = $count;
        elseif ($status === 'resolution') $resolutionCount = $count;
        elseif ($status === 'settlement') $settlementCount = $count;
        elseif ($status === 'close') $closedCount = $count;
        elseif (strpos($status, 'resolved') !== false) $resolvedCaseCount = $count;
    }
}

// Lupon-focused totals (only conciliation + arbitration)
$luponCasesCount = $conciliationCount + $arbitrationCount;
$caseLuponPercent = $casesCount ? round(($luponCasesCount / $casesCount) * 100) : 0;

// Hearings Count
$hearingQuery = "SELECT COUNT(*) as count FROM schedule_list";
$result = $conn->query($hearingQuery);
if ($result && $row = $result->fetch_assoc()) {
    $scheduledHearings = (int)$row['count'];
}

// Pending cases count (Case_Status = 'Pending')
$pendingCaseCount = 0;
try {
    $pendingCaseCount = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS c FROM case_info WHERE LOWER(Case_Status) = 'pending'", 'c', 0);
} catch (Throwable $e) { /* ignore */ }

// Progress percentages (guard division by zero)
$complaintResolvedPercent = $complaintsCount ? round(($resolvedCount / $complaintsCount) * 100) : 0;
$complaintPendingPercent = $complaintsCount ? round(($pendingCount / $complaintsCount) * 100) : 0;
$complaintRejectedPercent = $complaintsCount ? round(($rejectedCount / $complaintsCount) * 100) : 0;
$caseResolvedPercent = $casesCount ? round(($resolvedCaseCount / $casesCount) * 100) : 0;
// Approximate hearing progress relative to open cases
$hearingPercent = $casesCount ? min(100, round(($scheduledHearings / max($casesCount, 1)) * 100)) : 0;

// --- Lupon-focused global stats (all cases, not user-assigned) ---
$global_conciliation = 0;
$global_arbitration = 0;
$global_resolved_mediation = 0;
$global_resolved_arbitration = 0;
try {
    $global_conciliation = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS c FROM case_info WHERE LOWER(Case_Status) = 'conciliation'", 'c', 0);
    $global_arbitration = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS c FROM case_info WHERE LOWER(Case_Status) = 'arbitration'", 'c', 0);

    // Resolved mediation: resolved cases that have mediation_info entries
    $global_resolved_mediation = (int)bpamis_query_scalar($conn, "SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN mediation_info mi ON ci.Case_ID = mi.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%'", 'c', 0);

    // Resolved arbitration: resolved cases that have arbitration entries
    $global_resolved_arbitration = (int)bpamis_query_scalar($conn, "SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN arbitration a ON ci.Case_ID = a.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%'", 'c', 0);
} catch (Throwable $e) { /* ignore db errors, keep zeros */ }

// --- Assigned-to-current-user stats ---
$assigned_conciliation = 0;
$assigned_arbitration = 0;
$assigned_resolved_mediation = 0;
$assigned_resolved_arbitration = 0;
$luponName = trim((string)($_SESSION['official_name'] ?? ''));
if ($luponName !== '') {
    $like = '%' . $luponName . '%';
    try {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci LEFT JOIN conciliation c ON ci.Case_ID = c.Case_ID WHERE LOWER(ci.Case_Status) = 'conciliation' AND (c.mediator_name LIKE ? OR ci.lupon_assign LIKE ?)");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $assigned_conciliation = (int)(bpamis_stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci LEFT JOIN arbitration a ON ci.Case_ID = a.Case_ID WHERE LOWER(ci.Case_Status) = 'arbitration' AND (a.mediator_name LIKE ? OR ci.lupon_assign LIKE ?)");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $assigned_arbitration = (int)(bpamis_stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        // Assigned resolved mediation: resolved cases with mediation_info where mediator matches
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN mediation_info mi ON ci.Case_ID = mi.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%' AND (mi.mediator_name LIKE ? OR ci.lupon_assign LIKE ?)");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $assigned_resolved_mediation = (int)(bpamis_stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        // Assigned resolved arbitration
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN arbitration a ON ci.Case_ID = a.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%' AND (a.mediator_name LIKE ? OR ci.lupon_assign LIKE ?)");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $assigned_resolved_arbitration = (int)(bpamis_stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
        $stmt->close();
    } catch (Throwable $e) { /* ignore */ }
}

// ========== RECENT ACTIVITY ==========
function getComplainantName($conn, $resident_id, $external_id) {
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
$stats = [];
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

    // Complaints
    $complaintsQuery = $conn->query("SELECT COUNT(*) AS count FROM complaint_info WHERE DATE_FORMAT(date_filed, '%Y-%m') = '$month'");
    $monthlyComplaints[] = $complaintsQuery ? (int)$complaintsQuery->fetch_assoc()['count'] : 0;

    $rejectedQuery = $conn->query("SELECT COUNT(*) AS count FROM complaint_info WHERE status = 'rejected' AND DATE_FORMAT(date_filed, '%Y-%m') = '$month'");
    $monthlyRejected[] = $rejectedQuery ? (int)$rejectedQuery->fetch_assoc()['count'] : 0;

    // Cases (all & by type)
    $caseQuery = $conn->query("SELECT case_status, COUNT(*) as count FROM case_info c JOIN complaint_info ci ON c.complaint_id = ci.complaint_id WHERE DATE_FORMAT(ci.date_filed, '%Y-%m') = '$month' GROUP BY case_status");
    $totalCases = 0;
    $mediation = $resolution = $settlement = $closed = $resolved = 0;
    if ($caseQuery) {
        while ($row = $caseQuery->fetch_assoc()) {
            $status = strtolower(trim($row['case_status']));
            $count = (int)$row['count'];
            $totalCases += $count;
            if ($status === 'mediation') $mediation = $count;
            elseif ($status === 'resolution') $resolution = $count;
            elseif ($status === 'settlement') $settlement = $count;
            elseif ($status === 'close') $closed = $count;
            elseif ($status === 'resolved') $resolved = $count;
        }
    }
    $monthlyCases[] = $totalCases;
    $monthlyMediation[] = $mediation;
    $monthlyResolution[] = $resolution;
    $monthlySettlement[] = $settlement;
    $monthlyClosed[] = $closed;
    $monthlyResolved[] = $resolved;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
        body {
            background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%);
        }

        /* Ensure no horizontal scrolling by default */
        html { overflow-x: hidden; }

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
            .max-w-7xl,
            .glass,
            .grid { 
                overflow-x: hidden;
                overflow-y: visible !important;
            }
            img, svg, canvas, iframe { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body class="font-sans text-gray-700 relative overflow-x-hidden">


    <!-- Loader -->
    <div class="loader-wrapper" id="page-loader">
        <div class="loader">
            <div class="loader-gradient"></div>
            <div class="loader-inner"></div>
            <img src="../Assets/Img/logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>

    <?php include '../includes/barangay_official_lupon_nav.php'; ?>

    <!-- HEADER / INTRO -->
    <div class="max-w-7xl mx-auto px-3 sm:px-5 pt-6 sm:pt-10 relative">
        <div class="glass rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 lg:p-12 overflow-visible fade-in">
            <div class="absolute inset-0 pointer-events-none overflow-hidden rounded-2xl sm:rounded-3xl">
                <div class="absolute -top-20 -right-10 w-80 h-80 bg-gradient-to-br from-primary-200/70 to-primary-400/40 rounded-full blur-3xl opacity-60"></div>
                <div class="absolute -bottom-24 -left-10 w-72 h-72 bg-gradient-to-tr from-primary-100/60 via-white/40 to-primary-300/40 rounded-full blur-3xl"></div>
            </div>
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 sm:gap-4 md:gap-6">
                    <div class="min-w-0">
                        <p class="section-label mb-1 sm:mb-2">Lupon Tagapamayapa - Member Dashboard</p>
                        <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold tracking-tight text-sky-900">Welcome back<span class="font-light">,</span></h1>
                        <p class="mt-1 sm:mt-2 text-sm sm:text-base md:text-lg text-sky-800 font-medium truncate">
                            <?= isset($_SESSION['official_name']) ? htmlspecialchars($_SESSION['official_name']) : 'Lupon Tagapamayapa' ?>
                        </p>
                        <p class="mt-2 sm:mt-3 max-w-xl text-[11px] sm:text-xs md:text-sm lg:text-base text-sky-700/80">Manage conciliation, assigned cases, and hearings efficiently with real‑time insights.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 md:gap-4 w-full md:w-auto shrink-0">
                        <a href="feedback_lupon.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1 hover:shadow-lg">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-sky-700"><i class="fa-solid fa-square-plus text-sky-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">New</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-sky-900">Feedback</span>
                        </a>
                        <a href="assigned_case.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-emerald-700"><i class="fa-solid fa-user-check text-emerald-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-emerald-900">Assigned Cases</span>
                        </a>
                        <a href="view_hearing_calendar_lupon.php" class="quick-btn glass group rounded-lg sm:rounded-xl px-2 py-1.5 sm:px-3 sm:py-2 md:px-4 md:py-3 flex flex-col items-start gap-0.5 sm:gap-1">
                            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-2 text-rose-700"><i class="fa-solid fa-calendar-days text-rose-600 text-[10px] sm:text-xs md:text-base"></i><span class="text-[8px] sm:text-[10px] md:text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[9px] sm:text-[11px] md:text-[13px] font-medium text-rose-900">Hearings</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- MAIN GRID -->
    <div class="max-w-7xl mx-auto px-5 mt-6 sm:mt-8 md:mt-10 pb-12 sm:pb-16 space-y-6 sm:space-y-8 md:space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 items-stretch">
            <!-- Left: Upcoming Hearings (wider) -->
            <div class="lg:col-span-8 w-full order-1">
                <div class="glass rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 lg:p-7 fade-in h-full flex flex-col min-h-[500px] sm:min-h-[560px] md:min-h-[600px]">
                    <div class="flex items-center justify-between mb-3 sm:mb-4 md:mb-5">
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <i class="fas fa-calendar text-primary-500 text-sm sm:text-base"></i>
                            <h2 class="text-sky-900 font-semibold tracking-tight text-sm sm:text-base">Upcoming Hearings</h2>
                        </div>
                        <a href="view_hearing_calendar_lupon.php" title="Open full calendar" aria-label="Open full calendar"
                           class="inline-flex items-center justify-center p-1.5 sm:p-2 rounded-lg border border-white/50 bg-white/70 text-primary-600 hover:bg-primary-50 hover:text-primary-700 shadow-sm transition">
                            <i class="fas fa-expand text-xs sm:text-sm"></i>
                        </a>
                    </div>
                    <div class="mt-2 flex-1 min-h-[430px] sm:min-h-[500px] md:min-h-[540px]">
                        <iframe src="../SecMenu/schedule/CalendarLupon.php" class="w-full h-full rounded-lg sm:rounded-xl border border-white/40 shadow-sm" style="background: transparent;" loading="lazy"></iframe>
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
                        <!-- Lupon Case Statistics: Global and Assigned -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-sky-800 tracking-wide">Total Cases [Conciliation/Arbitration]</span>
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-sky-100 text-sky-700"><?= $global_conciliation + $global_arbitration + $global_resolved_mediation + $global_resolved_arbitration ?></span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-indigo-700 mb-0.5"><span class="font-bold">Conciliation</span><span class="font-bold"><?= $global_conciliation ?></span></div>
                                    <div class="w-full h-2.5 rounded-full bg-indigo-50 overflow-hidden border border-indigo-200"><div class="h-full bg-gradient-to-r from-indigo-400 to-indigo-600 progress-bar shadow-sm" style="width: <?= $global_conciliation && ($global_conciliation + $global_arbitration) ? round(($global_conciliation / max(1, ($global_conciliation + $global_arbitration))) * 100) : 0 ?>%"></div></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-purple-700 mb-0.5"><span class="font-bold">Arbitration</span><span class="font-bold"><?= $global_arbitration ?></span></div>
                                    <div class="w-full h-2.5 rounded-full bg-purple-50 overflow-hidden border border-purple-200"><div class="h-full bg-gradient-to-r from-purple-400 to-purple-600 progress-bar shadow-sm" style="width: <?= $global_arbitration && ($global_conciliation + $global_arbitration) ? round(($global_arbitration / max(1, ($global_conciliation + $global_arbitration))) * 100) : 0 ?>%"></div></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span>Resolved (Mediation)</span><span><?= $global_resolved_mediation ?></span></div>
                                    <div class="w-full h-2.5 rounded-full bg-emerald-50 overflow-hidden"><div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 progress-bar" style="width: <?= $global_resolved_mediation && ($global_resolved_mediation + $global_resolved_arbitration) ? round(($global_resolved_mediation / max(1, ($global_resolved_mediation + $global_resolved_arbitration))) * 100) : 0 ?>%"></div></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span>Resolved (Arbitration)</span><span><?= $global_resolved_arbitration ?></span></div>
                                    <div class="w-full h-2.5 rounded-full bg-emerald-50 overflow-hidden"><div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 progress-bar" style="width: <?= $global_resolved_arbitration && ($global_resolved_mediation + $global_resolved_arbitration) ? round(($global_resolved_arbitration / max(1, ($global_resolved_mediation + $global_resolved_arbitration))) * 100) : 0 ?>%"></div></div>
                                </div>
                            </div>

                            <hr class="my-3 border-t border-gray-100">

                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-sky-800 tracking-wide">Assigned Cases</span>
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-sky-50 text-sky-700"><?= $assigned_conciliation + $assigned_arbitration + $assigned_resolved_mediation + $assigned_resolved_arbitration ?></span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-indigo-700 mb-0.5"><span class="font-medium">Conciliation</span><span class="font-medium"><?= $assigned_conciliation ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-purple-700 mb-0.5"><span class="font-medium">Arbitration</span><span class="font-medium"><?= $assigned_arbitration ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span class="font-medium">Resolved (Mediation)</span><span><?= $assigned_resolved_mediation ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span class="font-medium">Resolved (Arbitration)</span><span><?= $assigned_resolved_arbitration ?></span></div>
                                </div>
                            </div>
                        </div>
                        <!-- Cases -->
                        <div>
                            <div class="flex justify-between mb-1 sm:mb-1.5 text-[10px] sm:text-xs font-medium text-emerald-800"><span>Total Lupon Cases</span><span class="px-1.5 py-0.5 sm:px-2 rounded-md bg-emerald-100 text-emerald-700"><?= $luponCasesCount ?></span></div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden"><div class="progress-bar bg-gradient-to-r from-emerald-400 to-emerald-500 h-full" style="width: <?= $caseLuponPercent ?>%"></div></div>
                            <p class="text-[9px] sm:text-[10px] mt-0.5 sm:mt-1 text-emerald-800/70">Conciliation: <?= $conciliationCount ?> • Arbitration: <?= $arbitrationCount ?></p>
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
                            <div class="glass rounded-lg sm:rounded-xl p-2.5 sm:p-3 md:p-4 flex items-start gap-2 sm:gap-3"><div class="h-7 w-7 sm:h-8 sm:w-8 md:h-9 md:w-9 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600"><i class="fa-solid fa-hourglass-half text-xs sm:text-sm"></i></div><div><p class="text-[8px] sm:text-[9px] md:text-[10px] tracking-wide uppercase font-semibold text-amber-700">Pending Cases</p><p class="text-sm sm:text-base md:text-lg leading-snug font-semibold text-amber-800"><?= $pendingCaseCount ?></p></div></div>
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
    <?php include 'sidebar_lupon.php'; ?>
    
    <!-- Event Details Modal (for mobile calendar clicks) -->
    <div id="lupon-event-modal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm" onclick="closeLuponEventModal()"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-sm sm:max-w-md mx-3 sm:mx-4 p-3 sm:p-6 transform transition-all duration-300 border border-gray-200" style="z-index: 100000 !important;">
            <button onclick="closeLuponEventModal()"
                class="absolute top-2 right-2 sm:top-4 sm:right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-6 h-6 sm:w-8 sm:h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-sm sm:text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform">&times;</button>
            <div class="absolute top-0 left-0 right-0 h-0.5 sm:h-1 bg-gradient-to-r from-blue-500 via-sky-400 to-blue-500 rounded-t-xl sm:rounded-t-2xl"></div>
            <h3 id="lupon-modal-title" class="text-sm sm:text-xl font-semibold mb-2 sm:mb-4 text-blue-700 border-b border-gray-200 pb-1.5 sm:pb-2 mt-1 sm:mt-2 pr-6 sm:pr-0"></h3>
            <div class="text-[11px] sm:text-sm text-gray-700 space-y-1.5 sm:space-y-3 max-h-[65vh] sm:max-h-[70vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-blue-50 to-sky-50 rounded-lg p-2 sm:p-4 border border-blue-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-hashtag text-blue-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-blue-700 uppercase tracking-wide">Case Number</span>
                    </div>
                    <div id="lupon-modal-case-id" class="text-base sm:text-2xl font-bold text-blue-900"></div>
                </div>
                <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-lg p-2 sm:p-4 border border-gray-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-calendar-alt text-gray-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-gray-700 uppercase tracking-wide">Scheduled Date & Time</span>
                    </div>
                    <div id="lupon-modal-start" class="text-[11px] sm:text-base font-medium text-gray-900"></div>
                </div>
                <div class="bg-gradient-to-r from-white to-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-users text-gray-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-gray-700 uppercase tracking-wide">Lupon Assigned</span>
                    </div>
                    <div id="lupon-modal-lupon" class="text-[11px] sm:text-sm font-medium text-gray-900">Not Yet Assigned</div>
                </div>
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-lg p-2 sm:p-4 border border-emerald-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-link text-emerald-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-emerald-700 uppercase tracking-wide">Actions</span>
                    </div>
                    <a id="lupon-modal-details-link" href="#" class="inline-flex items-center gap-1.5 sm:gap-2 text-emerald-700 hover:text-emerald-800 font-medium text-[11px] sm:text-sm underline decoration-2 underline-offset-2 transition-colors">
                        <i class="fas fa-external-link-alt text-[9px] sm:text-xs"></i>
                        <span>View Full Case Details</span>
                    </a>
                    <a id="lupon-modal-feedback-link" href="#" class="inline-flex items-center gap-1.5 sm:gap-2 mt-2 text-primary-700 hover:text-primary-800 font-medium text-[11px] sm:text-sm underline decoration-2 underline-offset-2 transition-colors">
                        <i class="fas fa-pen text-[9px] sm:text-xs"></i>
                        <span>Write Feedback</span>
                    </a>
                </div>
            </div>
            <div class="mt-2 sm:mt-4 flex items-center justify-end gap-1.5 sm:gap-2">
                <button onclick="closeLuponEventModal()" class="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-sm bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors font-medium">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Function to close lupon event modal
        function closeLuponEventModal() {
            const modal = document.getElementById('lupon-event-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        // Listen for messages from calendar iframe
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'showEventModal') {
                const data = event.data.data;
                
                // Populate modal with event data
                document.getElementById('lupon-modal-title').textContent = data.title;
                document.getElementById('lupon-modal-start').textContent = data.start;

                // Resolve and display the canonical case number (case_original_id) when possible.
                (async function(){
                    const displayedEl = document.getElementById('lupon-modal-case-id');
                    const rawCaseId = data.caseId || data.case_id || data.id || '';
                    if (!rawCaseId) {
                        displayedEl.textContent = '';
                        return;
                    }

                    // Try fetching the canonical case_original_id from the server.
                    try {
                        const resp = await fetch('../controllers/get_case_original_id.php?case_id=' + encodeURIComponent(rawCaseId), { credentials: 'same-origin' });
                        if (resp.ok) {
                            const j = await resp.json();
                            if (j && j.success && j.original_id) {
                                displayedEl.textContent = j.original_id;
                                return;
                            }
                        }
                    } catch (e) {
                        // ignore and fallback
                    }

                    // Fallback to whatever the iframe provided (likely internal Case_ID)
                    displayedEl.textContent = rawCaseId;
                })();
                // Populate Lupon assigned (if provided by iframe)
                try {
                    var luponText = data.lupon || data.lupon_assigned || data.luponAssigned || '';
                    document.getElementById('lupon-modal-lupon').textContent = luponText ? luponText : 'Not Yet Assigned';
                } catch (e) { /* noop */ }
                // Always point to the Lupon case details page for this installation
                try {
                    var caseId = data.caseId || data.case_id || data.id || '';
                    var detailsUrl = caseId ? ('view_case_details_lupon.php?id=' + encodeURIComponent(caseId)) : (data.detailsLink || '#');
                    document.getElementById('lupon-modal-details-link').href = detailsUrl;

                    var feedbackUrl = caseId ? ('feedback_lupon.php?id=' + encodeURIComponent(caseId)) : (data.feedbackLink || 'feedback_lupon.php');
                    var fbEl = document.getElementById('lupon-modal-feedback-link');
                    if (fbEl) fbEl.href = feedbackUrl;
                } catch (e) {
                    // Fallback to any provided detailsLink from the iframe
                    document.getElementById('lupon-modal-details-link').href = data.detailsLink || '#';
                    try { document.getElementById('lupon-modal-feedback-link').href = data.feedbackLink || 'feedback_lupon.php'; } catch (e) {}
                }
                
                // Show modal with smooth transition
                const modal = document.getElementById('lupon-event-modal');
                modal.classList.remove('hidden');
            }
        });

        // Close modal when clicking outside or pressing Escape
        document.getElementById('lupon-event-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLuponEventModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLuponEventModal();
            }
        });

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

            // Calendar is embedded via iframe (see Upcoming Hearings card)

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
        });
    </script>
        <?php include '../chatbot/bpamis_case_assistant.php'; ?>

    <?php include '../includes/footer.php' ?>

</body>
</html>
