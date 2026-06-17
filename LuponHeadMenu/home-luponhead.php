<?php
// Bootstrap DB and session before any output
require_once(__DIR__ . '/../server/server.php');
include '../controllers/session_control.php';
// Resolve Lupon Head display name by Position if not present in session
$__luponHeadName = null;
try {
    $positions = ['Lupon Tagapamayapa Head', 'Lupon Head', 'LuponHead'];
    $stmt = $conn->prepare("SELECT Name FROM barangay_officials WHERE Position = ? ORDER BY Official_ID LIMIT 1");
    foreach ($positions as $pos) {
        $stmt->bind_param('s', $pos);
        $stmt->execute();
        $res = bpamis_stmt_get_result($stmt);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $__luponHeadName = $row['Name'] ?? null;
            break;
        }
    }
} catch (Throwable $e) { /* ignore */ }

// Initialize all stats - Focus on Cases only
$casesCount = $mediatedCount = $conciliationCount = $resolutionCount = $arbitrationCount = $settlementCount = $closedCount = $resolvedCaseCount = 0;
$scheduledHearings = 0;

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
        elseif ($status === 'conciliation')
            $conciliationCount = $count;
        elseif ($status === 'resolution')
            $resolutionCount = $count;
        elseif ($status === 'arbitration')
            $arbitrationCount = $count;
        elseif ($status === 'settlement')
            $settlementCount = $count;
        elseif ($status === 'close')
            $closedCount = $count;
        elseif (strpos($status, 'resolved') !== false)
            $resolvedCaseCount += $count;
    }
}

// Hearings Count
$hearingQuery = "SELECT COUNT(*) as count FROM schedule_list";
$result = $conn->query($hearingQuery);
if ($result && $row = $result->fetch_assoc()) {
    $scheduledHearings = (int) $row['count'];
}

// Progress percentages (guard division by zero)
$mediationPercent = $casesCount ? round(($mediatedCount / $casesCount) * 100) : 0;
$conciliationPercent = $casesCount ? round(($conciliationCount / $casesCount) * 100) : 0;
$resolutionPercent = $casesCount ? round(($resolutionCount / $casesCount) * 100) : 0;
$arbitrationPercent = $casesCount ? round(($arbitrationCount / $casesCount) * 100) : 0;
$caseResolvedPercent = $casesCount ? round(($resolvedCaseCount / $casesCount) * 100) : 0;
// Approximate hearing progress relative to open cases
$hearingPercent = $casesCount ? min(100, round(($scheduledHearings / max($casesCount, 1)) * 100)) : 0;

// --- Lupon-focused global stats (all cases, not user-assigned) ---
$global_conciliation = 0;
$global_arbitration = 0;
$global_resolved_conciliation = 0;
$global_resolved_arbitration = 0;
$global_resolved_mediation = 0; // legacy/alias used in template (avoid undefined variable)
try {
    $global_conciliation = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS c FROM case_info WHERE LOWER(Case_Status) = 'conciliation'", 'c', 0);
    $global_arbitration = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS c FROM case_info WHERE LOWER(Case_Status) = 'arbitration'", 'c', 0);

    // Resolved conciliation: resolved cases that have conciliation_info entries
    $global_resolved_conciliation = (int)bpamis_query_scalar($conn, "SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN conciliation c ON ci.Case_ID = c.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%'", 'c', 0);
    // Keep legacy/template variable in sync
    $global_resolved_mediation = $global_resolved_conciliation;

    // Resolved arbitration: resolved cases that have arbitration entries
    $global_resolved_arbitration = (int)bpamis_query_scalar($conn, "SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN arbitration a ON ci.Case_ID = a.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%'", 'c', 0);
} catch (Throwable $e) { /* ignore db errors, keep zeros */ }

// --- Assigned-to-current-user stats ---
$assigned_conciliation = 0;
$assigned_arbitration = 0;
$assigned_resolved_conciliation = 0;
$assigned_resolved_arbitration = 0;
$assigned_resolved_mediation = 0; // legacy/template alias (avoid undefined variable in template)
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

        // Assigned resolved conciliation: resolved cases with conciliation entries where mediator matches
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT ci.Case_ID) AS c FROM case_info ci JOIN conciliation c ON ci.Case_ID = c.Case_ID WHERE LOWER(ci.Case_Status) LIKE '%resolved%' AND (c.mediator_name LIKE ? OR ci.lupon_assign LIKE ?)");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $assigned_resolved_conciliation = (int)(bpamis_stmt_get_result($stmt)->fetch_assoc()['c'] ?? 0);
        // keep legacy/template variable in sync
        $assigned_resolved_mediation = $assigned_resolved_conciliation;
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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <title>Official Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
        body { background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
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
        .loader-wrapper {
            position: fixed;
            inset: 0;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999; /* Ensure loader overlays nav and all content */
            transition: opacity .6s;
        }

        .loader {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .loader-gradient {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(#60a5fa 0deg, rgba(37, 100, 235, .66) 120deg, rgba(30, 64, 175, .34) 240deg, #60a5fa 360deg);
            animation: spin 1.2s linear infinite;
        }

        .loader-inner {
            position: absolute;
            inset: 10px;
            background: rgba(255, 255, 255, .85);
            border-radius: 50%;
        }

        .loader-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 58px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .fade-out {
            opacity: 0;
            pointer-events: none;
        }
        /* Calendar refine */
        .calendar-container { --fc-border-color: transparent; }
        .calendar-container .fc-theme-standard th { border:none; font-size:.65rem; letter-spacing:.08em; text-transform:uppercase; color:#0c4a6e; background:transparent; }
        .calendar-container .fc-daygrid-day { background: rgba(255,255,255,.55); backdrop-filter: blur(6px); }

        /* Mobile Optimization - Compressed and Compact */
        @media (max-width: 640px) {
            /* Mobile: keep loader perfectly centered and smaller */
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
            
            /* Prevent horizontal scroll */
            html, body { 
                overflow-x: hidden !important; 
                width: 100%; 
                max-width: 100vw;
            }
            
            body { overflow-y: auto !important; }
            
            .max-w-7xl,
            .glass,
            .grid { 
                overflow-x: hidden;
                max-width: 100%;
            }
            
            img, svg, canvas, iframe { max-width: 100%; height: auto; }
            
            /* Hero Section */
            .max-w-7xl.mx-auto.px-5.pt-10 {
                padding: 0.5rem !important;
            }
            
            .max-w-7xl > .glass.rounded-3xl {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
            }
            
            .max-w-7xl > .glass.rounded-3xl h1 {
                font-size: 1.125rem !important;
                line-height: 1.3 !important;
            }
            
            .section-label {
                font-size: 0.55rem !important;
                margin-bottom: 0.35rem !important;
            }
            
            .max-w-7xl > .glass.rounded-3xl p:nth-of-type(2) {
                font-size: 0.8rem !important;
                margin-top: 0.35rem !important;
            }
            
            .max-w-7xl > .glass.rounded-3xl p:nth-of-type(3) {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
                margin-top: 0.5rem !important;
            }
            
            /* Quick Action Buttons */
            .max-w-7xl > .glass.rounded-3xl .grid {
                gap: 0.4rem !important;
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .quick-btn {
                padding: 0.5rem !important;
                border-radius: 0.65rem !important;
                gap: 0.25rem !important;
            }
            
            .quick-btn .text-xs {
                font-size: 0.55rem !important;
            }
            
            .quick-btn .text-\[13px\] {
                font-size: 0.65rem !important;
                line-height: 1.2 !important;
            }
            
            .quick-btn i {
                font-size: 0.7rem !important;
            }
            
            .quick-btn .gap-2 {
                gap: 0.35rem !important;
            }
            
            /* Main Grid Section */
            .max-w-7xl.mx-auto.px-5.mt-10 {
                padding: 0.5rem !important;
                margin-top: 0.5rem !important;
            }
            
            .max-w-7xl.mx-auto.px-5.mt-10.space-y-10 {
                gap: 0.75rem !important;
            }
            
            /* Cards */
            .glass.rounded-2xl {
                padding: 0.65rem !important;
                border-radius: 1rem !important;
            }
            
            /* Calendar Card */
            .lg\:col-span-8 .glass {
                min-height: 550px !important;
            }
            
            .lg\:col-span-8 .glass h2 {
                font-size: 0.75rem !important;
            }
            
            .lg\:col-span-8 .glass .mb-5 {
                margin-bottom: 0.5rem !important;
            }
            
            .lg\:col-span-8 .glass i {
                font-size: 0.7rem !important;
            }
            
            .lg\:col-span-8 .glass .h-8.w-8 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }
            
            .lg\:col-span-8 .glass .min-h-\[460px\] {
                min-height: 490px !important;
            }
            
            .lg\:col-span-8 .glass iframe {
                height: 490px !important;
            }
            
            /* Statistics Card */
            .lg\:col-span-4 .glass {
                min-height: auto !important;
                margin-bottom: 1rem !important;
            }
            
            .lg\:col-span-4 .glass h2 {
                font-size: 0.75rem !important;
            }
            
            .lg\:col-span-4 .glass .mb-5 {
                margin-bottom: 0.65rem !important;
            }
            
            .lg\:col-span-4 .glass i {
                font-size: 0.7rem !important;
            }
            
            .lg\:col-span-4 .glass .space-y-6 {
                gap: 0.75rem !important;
            }
            
            /* Statistics Labels and Values */
            .lg\:col-span-4 .glass .text-xs {
                font-size: 0.65rem !important;
            }
            
            .lg\:col-span-4 .glass .text-\[11px\] {
                font-size: 0.6rem !important;
            }
            
            .lg\:col-span-4 .glass .text-\[10px\] {
                font-size: 0.55rem !important;
            }
            
            .lg\:col-span-4 .glass .px-2.py-0\.5 {
                padding: 0.2rem 0.4rem !important;
                font-size: 0.6rem !important;
            }
            
            /* Progress Bars */
            .progress-wrap {
                height: 0.5rem !important;
            }
            
            .lg\:col-span-4 .glass .h-2\.5 {
                height: 0.5rem !important;
            }
            
            /* Summary Chips */
            .lg\:col-span-4 .glass .grid-cols-2 {
                gap: 0.5rem !important;
            }
            
            .lg\:col-span-4 .glass .rounded-xl.p-4 {
                padding: 0.5rem !important;
                border-radius: 0.65rem !important;
            }
            
            .lg\:col-span-4 .glass .h-9.w-9 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }
            
            .lg\:col-span-4 .glass .h-9.w-9 i {
                font-size: 0.7rem !important;
            }
            
            .lg\:col-span-4 .glass .gap-3 {
                gap: 0.4rem !important;
            }
            
            .lg\:col-span-4 .glass .text-lg {
                font-size: 0.875rem !important;
            }
            
            /* Orbs - hide on mobile to reduce clutter */
            .orb {
                display: none !important;
            }
            
            /* Decorative backgrounds */
            .glass .absolute.inset-0 > div {
                transform: scale(0.7) !important;
                opacity: 0.4 !important;
            }
        }
        
        @media (max-width: 480px) {
            .loader { width: 72px; height: 72px; }
            .loader-inner { inset: 6px; }
            .loader-logo { width: 34px; }
        }
        
        @media (max-width: 380px) {
            .loader { width: 72px; height: 72px; }
            .loader-inner { inset: 6px; }
            .loader-logo { width: 34px; }
            
            .max-w-7xl > .glass.rounded-3xl {
                padding: 0.65rem !important;
            }
            
            .max-w-7xl > .glass.rounded-3xl h1 {
                font-size: 1rem !important;
            }
            
            .section-label {
                font-size: 0.5rem !important;
            }
            
            .quick-btn {
                padding: 0.45rem !important;
            }
            
            .quick-btn .text-xs {
                font-size: 0.5rem !important;
            }
            
            .quick-btn .text-\[13px\] {
                font-size: 0.6rem !important;
            }
            
            .glass.rounded-2xl {
                padding: 0.55rem !important;
            }
            
            .lg\:col-span-8 .glass {
                min-height: 480px !important;
            }
            
            .lg\:col-span-8 .glass .min-h-\[460px\] {
                min-height: 420px !important;
            }
            
            .lg\:col-span-8 .glass iframe {
                height: 420px !important;
            }
            
            .lg\:col-span-4 .glass .text-xs {
                font-size: 0.6rem !important;
            }
            
            .lg\:col-span-4 .glass .rounded-xl.p-4 {
                padding: 0.45rem !important;
            }
        }
    </style>
</head>

<body class="font-sans text-gray-700">
    <div class="orb one"></div>
    <div class="orb two"></div>

    <!-- Loader -->
    <div class="loader-wrapper">
        <div class="loader">
            <div class="loader-gradient"></div>
            <div class="loader-inner"></div>
            <img src="../Assets/Img/logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>

    <?php include '../includes/lupon_head_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>

    <!-- HEADER / INTRO -->
    <div class="max-w-7xl mx-auto px-5 pt-10 relative">
        <div class="glass rounded-3xl p-8 md:p-12 overflow-hidden fade-in">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-20 -right-10 w-80 h-80 bg-gradient-to-br from-primary-200/70 to-primary-400/40 rounded-full blur-3xl opacity-60"></div>
                <div class="absolute -bottom-24 -left-10 w-72 h-72 bg-gradient-to-tr from-primary-100/60 via-white/40 to-primary-300/40 rounded-full blur-3xl"></div>
            </div>
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
                    <div>
                        <p class="section-label mb-2">Lupon Tagapamayapa Hepe Dashboard</p>
                        <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-sky-900">Welcome back<span class="font-light">,</span></h1>
                        <p class="mt-2 text-sky-800 text-lg font-medium">
                            <?php 
                                $displayName = $_SESSION['official_name'] ?? ($__luponHeadName ?: 'LuponHead');
                                echo htmlspecialchars($displayName);
                            ?>
                        </p>
                        <p class="mt-3 max-w-xl text-sm md:text-base text-sky-700/80">Oversee complaints, cases, and hearings with a refined, real‑time dashboard.</p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4 w-full md:w-auto">
                        <a href="assign_case.php" class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1 hover:shadow-lg">
                            <div class="flex items-center gap-2 text-sky-700"><i class="fa-solid fa-square-plus text-sky-600"></i><span class="text-xs font-semibold tracking-wide uppercase">Assign</span></div>
                            <span class="text-[13px] font-medium text-sky-900">Case to Lupon</span>
                        </a>

                        <a href="feedback_luponhead.php" class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1 hover:shadow-lg">
                            <div class="flex items-center gap-2 text-rose-700"><i class="fa-solid fa-clipboard text-rose-600"></i><span class="text-xs font-semibold tracking-wide uppercase">Write</span></div>
                            <span class="text-[13px] font-medium text-sky-900">Feedback</span>
                        </a>
                        
                        <a href="assigned_case.php" class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1 hover:shadow-lg">
                            <div class="flex items-center gap-2 text-indigo-700"><i class="fa-solid fa-clipboard-list text-indigo-600"></i><span class="text-xs font-semibold tracking-wide uppercase">Assigned</span></div>
                            <span class="text-[13px] font-medium text-sky-900">Cases</span>
                        </a>
                       
                        <a href="view_cases.php" class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1">
                            <div class="flex items-center gap-2 text-amber-700"><i class="fa-solid fa-gavel text-amber-600"></i><span class="text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[13px] font-medium text-amber-900">Cases</span>
                        </a>
                      
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="max-w-7xl mx-auto px-5 mt-10 pb-16 space-y-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            <!-- Left: Upcoming Hearings (wider) -->
            <div class="lg:col-span-8 w-full order-1">
                <div class="glass rounded-2xl p-6 md:p-7 fade-in h-full flex flex-col min-h-[520px] md:min-h-[600px]">
                    <div class="flex items-center gap-2 mb-5"><i class="fas fa-calendar text-primary-500"></i><h2 class="text-sky-900 font-semibold tracking-tight">Upcoming Hearings</h2>
                <a href="view_hearing_calendar.php" title="Open full calendar"
                       class="ml-auto inline-flex items-center justify-center h-8 w-8 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700 hover:text-sky-900 shadow-sm transition"
                       aria-label="Open full calendar">
                         <i class="fas fa-expand"></i>
                    </a>
                </div>
                    <div class="mt-2 flex-1 min-h-[460px] md:min-h-[540px]">
                        
                        <iframe src="../SecMenu/schedule/CalendarLuponHead.php" class="w-full h-full rounded-xl border border-white/40 shadow-sm" style="background: transparent;" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <!-- Right: Statistics (narrower) -->
            <div class="lg:col-span-4 w-full order-2">
                <div class="glass rounded-2xl p-6 md:p-7 fade-in h-full flex flex-col min-h-[520px] md:min-h-[600px]">
                    <div class="flex items-center gap-2 mb-5">
                        <i class="fa-solid fa-chart-simple text-sky-600"></i>
                        <h2 class="text-sky-900 font-semibold tracking-tight">Statistics</h2>
                    </div>
                    <div class="space-y-6 flex-1">
                        <!-- Lupon Case Statistics: Global and Assigned -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-sky-800 tracking-wide">Total Cases for Conciliation and Arbitration</span>
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
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span>Resolved (conciliation)</span><span><?= $global_resolved_mediation ?></span></div>
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
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-sky-50 text-sky-700"><?= $assigned_conciliation + $assigned_arbitration + $assigned_resolved_conciliation + $assigned_resolved_arbitration ?></span>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-indigo-700 mb-0.5"><span class="font-medium">Conciliation</span><span class="font-medium"><?= $assigned_conciliation ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-purple-700 mb-0.5"><span class="font-medium">Arbitration</span><span class="font-medium"><?= $assigned_arbitration ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span class="font-medium">Resolved (Conciliation)</span><span><?= $assigned_resolved_conciliation ?></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5"><span class="font-medium">Resolved (Arbitration)</span><span><?= $assigned_resolved_arbitration ?></span></div>
                                </div>
                            </div>
                        </div>
                        <!-- Hearings -->
                        <div>
                            <div class="flex justify-between mb-1.5 text-xs font-medium text-rose-800"><span>Scheduled Hearings</span><span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-700"><?= $scheduledHearings ?></span></div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden"><div class="progress-bar bg-gradient-to-r from-rose-400 to-rose-500 h-full" style="width: <?= $hearingPercent ?>%"></div></div>
                            <p class="text-[10px] mt-1 text-rose-800/70">Relative to total cases</p>
                        </div>
                        <!-- Summary chips -->
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="glass rounded-xl p-4 flex items-start gap-3 border border-indigo-100"><div class="h-9 w-9 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600"><i class="fa-solid fa-handshake"></i></div><div><p class="text-[10px] tracking-wide uppercase font-semibold text-indigo-700">Conciliation</p><p class="text-lg leading-snug font-semibold text-indigo-800"><?= $conciliationCount ?></p></div></div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3 border border-purple-100"><div class="h-9 w-9 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600"><i class="fa-solid fa-scale-balanced"></i></div><div><p class="text-[10px] tracking-wide uppercase font-semibold text-purple-700">Arbitration</p><p class="text-lg leading-snug font-semibold text-purple-800"><?= $arbitrationCount ?></p></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
    </div>

    
    <script>
        // Sidebar submenu toggles
        document.querySelectorAll('.toggle-menu').forEach(button => {
            button.addEventListener('click', () => {
                const submenu = button.nextElementSibling;
                submenu.classList.toggle('hidden');
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Lock scrolling while loader is visible
            document.body.classList.add('overflow-hidden');

            // Keep loader centered and the only visible element; fade it out after delay
            setTimeout(() => {
                const loader = document.querySelector('.loader-wrapper');
                if (!loader) return;
                loader.classList.add('fade-out');

                setTimeout(() => {
                    // Remove overlay and restore scrolling
                    if (loader && loader.parentNode) loader.remove();
                    document.body.classList.remove('overflow-hidden');
                }, 600);
            }, 400);

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

            // Initialize chart with server data
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
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, usePointStyle: true } } },
                        scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } }
                    }
                });
            }
        });
    </script>

    <!-- Event Modal for Calendar (displays on top of entire page) -->
    <div id="eventModal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 transform transition-all duration-300 border border-gray-200 sm:p-6 p-4" style="z-index: 100000 !important;">
            <button id="modalClose"
                class="absolute top-3 right-3 sm:top-4 sm:right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-base sm:text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform"
                onclick="document.getElementById('eventModal').classList.add('hidden');">&times;</button>
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-sky-400 to-blue-500 rounded-t-2xl"></div>
            <h3 id="modalTitle" class="text-base sm:text-xl font-semibold mb-3 sm:mb-4 text-blue-700 border-b border-gray-200 pb-2 mt-2"></h3>
            
            <div id="modalContent" class="text-xs sm:text-sm text-gray-700 space-y-2 sm:space-y-3 max-h-[70vh] overflow-y-auto"></div>
            <div class="mt-3 sm:mt-4 flex items-center justify-end gap-1.5 sm:gap-2 flex-wrap">
                <button id="reschedule" data-id="" class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors inline-flex items-center gap-1 sm:gap-2 hidden">
                    <i class="far fa-calendar-plus text-xs sm:text-sm"></i>
                    <span class="hidden sm:inline">Reschedule</span>
                    <span class="sm:hidden">Resched</span>
                </button>
                <button id="delete" data-id="" class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors inline-flex items-center gap-1 sm:gap-2 hidden">
                    <i class="far fa-trash-alt text-xs sm:text-sm"></i>
                    <span>Delete</span>
                </button>
                <button class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors"
                    onclick="document.getElementById('eventModal').classList.add('hidden');">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Parent listener: open event modal and ACK the iframe (handshake)
        window.addEventListener('message', function(event) {
            // Optional origin check: uncomment if you want to restrict origins
            // if (event.origin !== window.location.origin) return;
            try {
                if (!event.data) return;
                if (event.data.type === 'openEventModal') {
                    const payload = event.data.payload || {};
                    const modal = document.getElementById('eventModal');
                    const title = document.getElementById('modalTitle');
                    const content = document.getElementById('modalContent');
                    const rescheduleBtn = document.getElementById('reschedule');
                    const deleteBtn = document.getElementById('delete');

                    if (title) title.textContent = payload.title || 'Schedule Details';
                    if (content) content.innerHTML = payload.content || '';
                    // First use any lupon value included in the iframe payload (support multiple property names)
                    try {
                        var luponName = payload.lupon || payload.lupon_assigned || payload.luponAssigned || payload.lupon_tagapamayapa || '';
                        var luponEl = document.getElementById('modalLuponName');
                        if (luponEl) luponEl.textContent = luponName ? luponName : 'Not Yet Assigned';
                    } catch (e) { /* noop */ }

                    // If the iframe provided an eventId, fetch authoritative remarks and lupon from server
                    (async function(){
                        try {
                            var evtId = payload.eventId || payload.id || payload.scheduleId || payload.event_id || null;
                            if (!evtId && payload.eventId === 0) evtId = 0; // keep explicit zero if used
                            if (evtId) {
                                var res = await fetch('../SecMenu/schedule/get_schedule_details.php?id=' + encodeURIComponent(evtId), { credentials: 'same-origin' });
                                if (res && res.ok) {
                                    var data = await res.json();
                                    if (data && data.success) {
                                        try {
                                            var remarksEl = document.getElementById('modalRemarksText');
                                            if (remarksEl) {
                                                var txt = data.remarks || '';
                                                // escape HTML then convert newlines to <br>
                                                function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
                                                remarksEl.innerHTML = txt ? escapeHtml(txt).replace(/\n/g, '<br>') : 'No remarks';
                                            }
                                            if (data.lupon) {
                                                var luponEl2 = document.getElementById('modalLuponName');
                                                if (luponEl2) luponEl2.textContent = data.lupon;
                                            }
                                        } catch (e) { /* noop */ }
                                    }
                                }
                            }
                        } catch (err) { /* ignore fetch errors */ }
                    })();
                    if (rescheduleBtn) rescheduleBtn.setAttribute('data-id', payload.eventId || '');
                    if (deleteBtn) deleteBtn.setAttribute('data-id', payload.eventId || '');

                    if (modal) modal.classList.remove('hidden');

                    // Send ACK back to iframe when requestId is present so iframe won't show local modal.
                    try {
                        var rid = payload.requestId;
                        if (rid && event.source && typeof event.source.postMessage === 'function') {
                            event.source.postMessage({ type: 'openEventModalAck', requestId: rid, eventId: payload.eventId || null }, event.origin || '*');
                        }
                    } catch (err) { /* ignore */ }
                }
            } catch (err) { /* ignore malformed message */ }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('eventModal');
            if (!modal || modal.classList.contains('hidden')) return;
            const card = modal.querySelector('div.relative');
            if (card && !card.contains(e.target) && e.target === modal) {
                modal.classList.add('hidden');
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const m = document.getElementById('eventModal'); if (m) m.classList.add('hidden');
            }
        });

        // Reschedule/Delete handlers
        document.addEventListener('DOMContentLoaded', function() {
            const resBtn = document.getElementById('reschedule');
            if (resBtn) resBtn.addEventListener('click', function() { const id = this.getAttribute('data-id'); if (id) window.location.href = `reschedule_hearing.php?id=${id}`; });
            const delBtn = document.getElementById('delete');
            if (delBtn) delBtn.addEventListener('click', function() { const id = this.getAttribute('data-id'); if (id && confirm('Are you sure you want to delete this hearing?')) window.location.href = `schedule/delete_schedule.php?id=${id}`; });
        });
    </script>

    <?php include '../chatbot/bpamis_case_assistant.php'; ?>

    <?php include '../includes/footer.php' ?>

</body>

</html>