<?php
// Secretary Home (Premium Glass UI)
include '../server/server.php';
include '../controllers/session_control.php';
// ===================== NUMERIC AGGREGATES ===================== //
$complaintsCount = $resolvedCount = $pendingCount = $rejectedCount = 0;
$dismissedComplaints = 0;
$casesCount = 0;
// Legacy counters (kept if referenced elsewhere)
$mediatedCount = $unresolvedCount = $resolutionCount = $settlementCount = $closedCount = $resolvedCaseCount = $openCount = 0;
// New case status counters aligned with reports
$mediationCount = $conciliationCount = $arbitrationCount = 0;
$mediationResolvedCount = $conciliationResolvedCount = $arbitrationResolvedCount = 0;
$ctfaCount = $dismissedCount = 0;
$scheduledHearings = 0;

// Complaints distribution
if ($result = $conn->query("SELECT status, COUNT(*) as count FROM complaint_info GROUP BY status")) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int) $row['count'];
        $complaintsCount += $count;
        if ($status === 'resolved') $resolvedCount = $count;
        elseif ($status === 'pending') $pendingCount = $count;
        elseif ($status === 'rejected') $rejectedCount = $count;
        elseif ($status === 'dismissed') $dismissedComplaints = $count;
        elseif ($status === 'mediation') $mediatedCount = $count;
        elseif ($status === 'open') $openCount = $count;
        elseif ($status === 'resolution') $resolutionCount = $count;
        elseif ($status === 'unresolved') $unresolvedCount = $count;
        elseif ($status === 'settlement') $settlementCount = $count;
    }
}

// Case distribution
if ($result = $conn->query("SELECT case_status as status, COUNT(*) as count FROM case_info GROUP BY case_status")) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int) $row['count'];
        $casesCount += $count;
        // Map to new buckets using substring checks so variants like
        // 'in mediation resolved' or 'mediation - resolved' are handled.
        if (strpos($status, 'mediation') !== false && strpos($status, 'resolved') === false) {
            $mediationCount = $count;
        } elseif (strpos($status, 'conciliation') !== false && strpos($status, 'resolved') === false) {
            $conciliationCount = $count;
        } elseif (strpos($status, 'arbitration') !== false && strpos($status, 'resolved') === false) {
            $arbitrationCount = $count;
        } elseif (strpos($status, 'mediation') !== false && strpos($status, 'resolved') !== false) {
            $mediationResolvedCount = $count;
        } elseif (strpos($status, 'conciliation') !== false && strpos($status, 'resolved') !== false) {
            $conciliationResolvedCount = $count;
        } elseif (strpos($status, 'arbitration') !== false && strpos($status, 'resolved') !== false) {
            $arbitrationResolvedCount = $count;
        } elseif (strpos($status, 'certificate to file action') !== false || strpos($status, 'ctfa') !== false) {
            $ctfaCount = $count;
        } elseif (strpos($status, 'dismissed') !== false) {
            $dismissedCount = $count;
        // Maintain legacy counters if used in UI
        } elseif (strpos($status, 'open') !== false) {
            $openCount = $count;
        } elseif (strpos($status, 'unresolved') !== false) {
            $unresolvedCount = $count;
        }
    }
}

// Hearing total
if ($result = $conn->query("SELECT COUNT(*) as count FROM schedule_list")) {
    if ($row = $result->fetch_assoc())
        $scheduledHearings = (int) $row['count'];
}

// Progress percentages (guard division by zero)
$complaintResolvedPercent = $complaintsCount ? round(($resolvedCount / $complaintsCount) * 100) : 0;
$complaintPendingPercent = $complaintsCount ? round(($pendingCount / $complaintsCount) * 100) : 0;
$complaintRejectedPercent = $complaintsCount ? round(($rejectedCount / $complaintsCount) * 100) : 0;
// Resolved percent = sum of conciliation and arbitration resolved types over total cases
$resolvedCaseCount = ($conciliationResolvedCount + $arbitrationResolvedCount);
$caseResolvedPercent = $casesCount ? round(($resolvedCaseCount / $casesCount) * 100) : 0;
// Treat hearing progress as proportion of cases that have at least one scheduled hearing (approximation)
$hearingPercent = $casesCount ? min(100, round(($scheduledHearings / max($casesCount, 1)) * 100)) : 0;

// ===================== RECENT ACTIVITY ===================== //
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
    $person = bpamis_stmt_get_result($stmt)->fetch_assoc();
    return $person ? trim($person['first_name'] . ' ' . ($person['middle_name'] ? substr($person['middle_name'], 0, 1) . '. ' : '') . $person['last_name']) : 'Unknown';
}

$recentActivities = [];
if ($result = $conn->query("SELECT complaint_id, resident_id, external_complainant_id, date_filed FROM complaint_info ORDER BY date_filed DESC LIMIT 6")) {
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'message' => 'New complaint filed by ' . htmlspecialchars(getComplainantName($conn, $row['resident_id'], $row['external_complainant_id'])),
            'time' => $row['date_filed']
        ];
    }
}

// ===================== MONTHLY SERIES (6 months) ===================== //
$monthlyLabels = $monthlyComplaints = $monthlyCases = [];
$monthlyMediation = $monthlyConciliation = $monthlyArbitration = [];
$monthlyMediationResolved = $monthlyConciliationResolved = $monthlyArbitrationResolved = [];
$monthlyCTFA = $monthlyDismissed = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} months"));
    $monthlyLabels[] = date('M Y', strtotime($monthKey . '-01'));
    // Complaint counts
    $val = $conn->query("SELECT COUNT(*) AS c FROM complaint_info WHERE DATE_FORMAT(date_filed,'%Y-%m')='$monthKey'");
    $monthlyComplaints[] = $val ? (int) $val->fetch_assoc()['c'] : 0;
    // Case breakdown aligned to new statuses
    $caseBreak = [
        'mediation' => 0,
        'conciliation' => 0,
        'arbitration' => 0,
        'mediation resolved' => 0,
        'conciliation resolved' => 0,
        'arbitration resolved' => 0,
        'certificate to file action' => 0,
        'dismissed' => 0,
    ];
    if ($resCase = $conn->query("SELECT case_status, COUNT(*) c FROM case_info c JOIN complaint_info ci ON c.complaint_id=ci.complaint_id WHERE DATE_FORMAT(ci.date_filed,'%Y-%m')='$monthKey' GROUP BY case_status")) {
        while ($r = $resCase->fetch_assoc()) {
            $k = strtolower($r['case_status']);
            $cVal = (int) $r['c'];
            // Use substring matching so variants like 'in mediation resolved' map correctly
            if (strpos($k, 'mediation') !== false && strpos($k, 'resolved') === false) {
                $caseBreak['mediation'] += $cVal;
            } elseif (strpos($k, 'conciliation') !== false && strpos($k, 'resolved') === false) {
                $caseBreak['conciliation'] += $cVal;
            } elseif (strpos($k, 'arbitration') !== false && strpos($k, 'resolved') === false) {
                $caseBreak['arbitration'] += $cVal;
            } elseif (strpos($k, 'mediation') !== false && strpos($k, 'resolved') !== false) {
                $caseBreak['mediation resolved'] += $cVal;
            } elseif (strpos($k, 'conciliation') !== false && strpos($k, 'resolved') !== false) {
                $caseBreak['conciliation resolved'] += $cVal;
            } elseif (strpos($k, 'arbitration') !== false && strpos($k, 'resolved') !== false) {
                $caseBreak['arbitration resolved'] += $cVal;
            } elseif (strpos($k, 'certificate to file action') !== false || strpos($k, 'ctfa') !== false) {
                $caseBreak['certificate to file action'] += $cVal;
            } elseif (strpos($k, 'dismissed') !== false) {
                $caseBreak['dismissed'] += $cVal;
            } else {
                // fallback to exact key match if present
                if (array_key_exists($k, $caseBreak)) $caseBreak[$k] = $cVal;
            }
        }
    }
    $monthlyCases[] = array_sum($caseBreak);
    $monthlyMediation[] = $caseBreak['mediation'];
    $monthlyConciliation[] = $caseBreak['conciliation'];
    $monthlyArbitration[] = $caseBreak['arbitration'];
    $monthlyMediationResolved[] = $caseBreak['mediation resolved'];
    $monthlyConciliationResolved[] = $caseBreak['conciliation resolved'];
    $monthlyArbitrationResolved[] = $caseBreak['arbitration resolved'];
    $monthlyCTFA[] = $caseBreak['certificate to file action'];
    $monthlyDismissed[] = $caseBreak['dismissed'];
}

// ===================== SECRETARY CASES FOR TIMELINE ===================== //
$secretaryCases = [];
if ($stmt = $conn->prepare("SELECT ci.Case_ID, co.Complaint_ID, co.Complaint_Title, co.Date_Filed, ci.Case_Status, co.case_type,
                                    co.resident_id, co.external_complainant_id
                             FROM case_info ci
                             JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                             ORDER BY co.Date_Filed DESC, ci.Case_ID DESC")) {
    if ($stmt->execute()) {
        $res = bpamis_stmt_get_result($stmt);
        while ($row = $res->fetch_assoc()) {
            // Get complainant name
            if (!empty($row['resident_id'])) {
                $compStmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM resident_info WHERE resident_id = ?");
                if ($compStmt) {
                    $compStmt->bind_param("i", $row['resident_id']);
                    $compStmt->execute();
                    $compResult = bpamis_stmt_get_result($compStmt);
                    $complainant = $compResult->fetch_assoc();
                    $row['complainant_name'] = $complainant ? trim($complainant['first_name'] . ' ' . $complainant['last_name']) : 'Unknown';
                    $compStmt->close();
                } else {
                    $row['complainant_name'] = 'Unknown';
                }
            } else {
                $compStmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM external_complainant WHERE external_complaint_id = ?");
                if ($compStmt) {
                    $compStmt->bind_param("i", $row['external_complainant_id']);
                    $compStmt->execute();
                    $compResult = bpamis_stmt_get_result($compStmt);
                    $complainant = $compResult->fetch_assoc();
                    $row['complainant_name'] = $complainant ? trim($complainant['first_name'] . ' ' . $complainant['last_name']) : 'Unknown';
                    $compStmt->close();
                } else {
                    $row['complainant_name'] = 'Unknown';
                }
            }
            
            // Get respondent names
            // Primary source: complaint_respondents (many rows). There may also be a main respondent id stored in complaint_info.respondent_id — include it first.
            $respondents = [];

            // Try to read all respondent rows from complaint_respondents joined to resident_info
            $seenIds = [];
            $crStmt = $conn->prepare("SELECT r.resident_id, r.first_name, r.middle_name, r.last_name
                                        FROM complaint_respondents cr
                                        JOIN resident_info r ON cr.respondent_id = r.resident_id
                                        WHERE cr.complaint_id = ?");
            if ($crStmt) {
                $crStmt->bind_param("i", $row['Complaint_ID']);
                $crStmt->execute();
                $crRes = bpamis_stmt_get_result($crStmt);
                while ($r = $crRes->fetch_assoc()) {
                    $seenIds[$r['resident_id']] = trim($r['first_name'] . ' ' . $r['last_name']);
                }
                $crStmt->close();
            }

            // Fetch a main respondent id from complaint_info if present (column may be named respondent_id)
            $mainRespId = null;
            $mainStmt = $conn->prepare("SELECT respondent_id FROM complaint_info WHERE Complaint_ID = ?");
            if ($mainStmt) {
                $mainStmt->bind_param("i", $row['Complaint_ID']);
                $mainStmt->execute();
                $m = bpamis_stmt_get_result($mainStmt)->fetch_assoc();
                if ($m && !empty($m['respondent_id'])) $mainRespId = (int)$m['respondent_id'];
                $mainStmt->close();
            }

            // If main respondent exists and is present in seenIds, place them first; otherwise fetch main respondent directly
            if ($mainRespId) {
                if (isset($seenIds[$mainRespId])) {
                    $respondents[] = $seenIds[$mainRespId];
                    unset($seenIds[$mainRespId]);
                } else {
                    $rStmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM resident_info WHERE resident_id = ?");
                    if ($rStmt) {
                        $rStmt->bind_param("i", $mainRespId);
                        $rStmt->execute();
                        $rp = bpamis_stmt_get_result($rStmt)->fetch_assoc();
                        if ($rp) $respondents[] = trim($rp['first_name'] . ' ' . $rp['last_name']);
                        $rStmt->close();
                    }
                }
            }

            // append the rest (preserve any order returned by DB)
            foreach ($seenIds as $name) $respondents[] = $name;

            if (count($respondents) > 1) {
                $row['respondent_name'] = $respondents[0] . ' et al.';
            } elseif (count($respondents) === 1) {
                $row['respondent_name'] = $respondents[0];
            } else {
                $row['respondent_name'] = 'Unknown';
            }

            $secretaryCases[] = $row;
        }
    }
    $stmt->close();
}
$serverNowIso = (new DateTime())->format('Y-m-d\TH:i:sP');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard</title>
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
        body {
            background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%);
        }

        /* Ensure no horizontal scrolling by default */
        html { overflow-x: hidden; }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: .55;
            mix-blend-mode: multiply;
        }

        .orb.one {
            width: 480px;
            height: 480px;
            background: linear-gradient(135deg, #0c9ced, #7cccfd);
            top: -140px;
            right: -120px;
            animation: float 14s ease-in-out infinite;
        }

        .orb.two {
            width: 360px;
            height: 360px;
            background: linear-gradient(135deg, #bae2fd, #e0effe);
            bottom: -120px;
            left: -100px;
            animation: float 11s ease-in-out reverse infinite;
        }

        .glass {
            backdrop-filter: blur(14px);
            background: linear-gradient(135deg, rgba(255, 255, 255, .65), rgba(255, 255, 255, .35));
            border: 1px solid rgba(255, 255, 255, .45);
            box-shadow: 0 10px 40px -12px rgba(12, 156, 237, .25), 0 4px 18px -6px rgba(12, 156, 237, .18);
        }

        .stat-chip {
            @apply inline-flex items-center text-xs font-medium px-2 py-1 rounded-md bg-white/60 backdrop-blur border border-white/40 shadow-sm;
        }

        .progress-wrap {
            height: 12px; /* enlarged for better visibility */
        }
        .progress-wrap .progress-bar { box-shadow: 0 0 0 1px rgba(255,255,255,.4), 0 2px 6px -1px rgba(12,156,237,.25); }
        /* Equal height cards */
        .dashboard-equal-row { display:flex; flex-wrap:wrap; gap:0.5rem; }
        .dashboard-equal-row > .card-flex { display:flex; flex-direction:column; }
        @media (min-width:1024px){
            .dashboard-equal-row > .card-flex { flex:1 1 0; }
            .dashboard-equal-row { align-items:stretch; }
            .card-flex .card-body-grow { flex:1 1 auto; display:flex; flex-direction:column; }
        }

        .progress-bar {
            transition: width 1s cubic-bezier(.4, .0, .2, 1);
        }

        .section-label {
            font-size: .65rem;
            letter-spacing: .09em;
            font-weight: 600;
            text-transform: uppercase;
            color: #0369a1;
        }

        .quick-btn {
            position: relative;
            overflow: hidden;
        }

        .quick-btn:before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .6), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity .4s;
        }

        .quick-btn:hover:before {
            opacity: 1;
        }

        .quick-btn:hover {
            transform: translateY(-4px);
        }

        .fade-in {
            animation: fade .6s ease;
        }

        @keyframes fade {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loader retained */
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

        .fade-out {
            opacity: 0;
            pointer-events: none;
        }

        /* Calendar refine */
        .calendar-container {
            --fc-border-color: transparent;
        }

        .calendar-container .fc-theme-standard th {
            border: none;
            font-size: .65rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #0c4a6e;
            background: transparent;
        }

        .calendar-container .fc-daygrid-day {
            background: rgba(255, 255, 255, .55);
            backdrop-filter: blur(6px);
        }

        /* Disable horizontal scroll on mobile */
        @media (max-width: 640px) {
            html, body { 
                overflow-x: hidden !important; 
                width: 100%; 
                height: 100%;
                overflow-y: auto !important;
            }
            .max-w-screen-2xl,
            .glass,
            .grid,
            .dashboard-equal-row { 
                overflow-x: hidden;
                overflow-y: visible !important;
            }
            img, svg, canvas, iframe { max-width: 100%; height: auto; }
            
            /* Remove any nested scrolling containers */
            .card-body-grow,
            .fade-in,
            .space-y-6,
            .space-y-8 {
                overflow-y: visible !important;
                max-height: none !important;
            }
            
            /* Case select dropdown mobile optimization */
            #sec-case-select {
                width: 100% !important;
                max-width: 200px !important;
                font-size: 0.65rem !important;
            }
            
            /* Case Timeline mobile optimization */
            .lg\:col-span-5.glass h2 {
                font-size: 0.75rem !important;
            }
            .lg\:col-span-5.glass .hidden.sm\:inline {
                display: none !important;
            }
            .lg\:col-span-5.glass label {
                font-size: 0.6rem !important;
            }
            .lg\:col-span-5.glass .mb-3 {
                margin-bottom: 0.5rem !important;
            }
            .lg\:col-span-5.glass .gap-3 {
                gap: 0.5rem !important;
            }
            .lg\:col-span-5.glass .p-6 {
                padding: 0.75rem !important;
            }
            /* Move case selection below title on mobile */
            .lg\:col-span-5.glass > div:first-child {
                flex-direction: column !important;
                align-items: flex-start !important;
            }
            .lg\:col-span-5.glass > div:first-child > div:last-child {
                margin-left: 0 !important;
                width: 100% !important;
                margin-top: 0.5rem !important;
            }
            .lg\:col-span-5.glass > div:first-child > div:last-child label {
                display: none !important;
            }
            #sec-case-phase-summary {
                font-size: 0.65rem !important;
                line-height: 1.2 !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 0.25rem !important;
            }
            #sec-case-phase-summary span {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.4rem !important;
                display: inline-block !important;
            }
            .timeline-step .text-\[13px\] {
                font-size: 0.7rem !important;
            }
            .timeline-step .text-\[12px\] {
                font-size: 0.65rem !important;
            }
            .timeline-step .text-\[10px\],
            .timeline-step span[id*="badge"],
            .timeline-step span[id*="note"] {
                font-size: 0.6rem !important;
                padding: 0.15rem 0.35rem !important;
            }
            .timeline-step .rounded-xl {
                padding: 0.5rem !important;
            }
            .timeline-step .w-6.h-6 {
                width: 1.25rem !important;
                height: 1.25rem !important;
            }
            .timeline-step .w-6.h-6 i {
                font-size: 0.55rem !important;
            }
            .timeline-step.relative.pl-8 {
                padding-left: 1.75rem !important;
            }
            .timeline-step .gap-2 {
                gap: 0.35rem !important;
            }
            .timeline-step .flex.items-center {
                flex-wrap: wrap !important;
            }
            .space-y-4 {
                gap: 0.75rem !important;
            }
            .absolute.left-3 {
                left: 0.5rem !important;
            }
            
            /* 6-Month Trends mobile optimization */
            .lg\:col-span-7.glass.rounded-2xl.p-6 {
                padding: 0.75rem !important;
            }
            .lg\:col-span-7.glass.rounded-2xl h2 {
                font-size: 0.75rem !important;
            }
            .lg\:col-span-7.glass.rounded-2xl .mb-5 {
                margin-bottom: 0.5rem !important;
            }
            .lg\:col-span-7.glass.rounded-2xl i {
                font-size: 0.7rem !important;
            }
            #statsChart {
                max-height: 280px !important;
            }
            
            /* Hero section mobile optimization */
            .max-w-screen-2xl > .glass.rounded-3xl {
                padding: 1rem 1rem 0.5rem 1rem !important;
                overflow: visible !important;
                max-height: none !important;
                margin-bottom: 0 !important;
                height: auto !important;
            }
            .max-w-screen-2xl.mx-auto.px-5.pt-10 {
                padding-top: 0.5rem !important;
                overflow: visible !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl h1 {
                font-size: 1.25rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl p:nth-of-type(1) {
                font-size: 0.65rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl p:nth-of-type(2) {
                font-size: 0.875rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl p:nth-of-type(3) {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .grid {
                gap: 0.5rem !important;
                margin-bottom: 0 !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .quick-btn {
                padding: 0.5rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .quick-btn .text-xs {
                font-size: 0.6rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .quick-btn .text-\[13px\] {
                font-size: 0.65rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .quick-btn i {
                font-size: 0.7rem !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .gap-6 {
                gap: 0.75rem !important;
                margin-bottom: 0 !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl > div {
                overflow: visible !important;
                height: auto !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .flex.flex-col {
                padding-bottom: 0 !important;
                margin-bottom: 0 !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .absolute.inset-0 {
                overflow: visible !important;
            }
            .max-w-screen-2xl > .glass.rounded-3xl .relative.z-10 {
                overflow: visible !important;
            }
            
            /* Adjust main grid spacing on mobile */
            .max-w-screen-2xl.mx-auto.px-5.mt-10 {
                margin-top: 0.5rem !important;
            }
            
            /* Summary chips mobile optimization */
            .glass.rounded-xl.p-4 {
                padding: 0.5rem !important;
            }
            .glass.rounded-xl.p-4 .h-9.w-9 {
                height: 1.75rem !important;
                width: 1.75rem !important;
                font-size: 0.7rem !important;
            }
            .glass.rounded-xl.p-4 .text-\[10px\] {
                font-size: 0.5rem !important;
            }
            .glass.rounded-xl.p-4 .text-lg {
                font-size: 0.875rem !important;
            }
            .glass.rounded-xl.p-4 .gap-3 {
                gap: 0.5rem !important;
            }
            
            /* Calendar mobile refinements - better date cell padding and spacing */
            .fc .fc-daygrid-day {
                padding: 0.25rem !important;
            }
            .fc .fc-daygrid-day-number {
                padding: 0.375rem !important;
                font-size: 0.8rem !important;
            }
            .fc .fc-daygrid-day-top {
                flex-direction: column;
                align-items: center;
            }
            .fc .fc-col-header-cell {
                padding: 0.5rem 0.25rem !important;
                font-size: 0.7rem !important;
            }
            .fc .fc-daygrid-event {
                font-size: 0.65rem !important;
                padding: 0.25rem 0.25rem !important;
                margin: 0.25rem 0 !important;
            }
            
            /* Calendar container mobile padding - refined for better balance */
            .card-flex.lg\:col-span-7.glass {
                padding: 0.5rem 0.625rem 0.75rem !important; /* top/right-left/bottom */
                width: 100%; /* ensure full-width card on mobile */
            }
            
            /* Calendar heading and text - smaller and not bold */
            .card-flex.lg\:col-span-7.glass h2 {
                font-size: 0.7rem !important;
                font-weight: 400 !important;
            }
            
            .card-flex.lg\:col-span-7.glass i {
                font-size: 0.7rem !important;
            }
            
            .card-flex.lg\:col-span-7.glass .mb-5 {
                margin-bottom: 0.5rem !important; /* tighter header spacing */
            }
            
            .card-flex.lg\:col-span-7.glass iframe {
                height: 520px !important; /* a touch taller for better month visibility */
                display: block;
            }

            
        }
    </style>
</head>

<body class="font-sans text-gray-700">
   
    <!-- Add this at the very top of the body -->
    <div class="loader-wrapper">
        <div class="loader">
            <div class="loader-gradient"></div>
            <div class="loader-inner"></div>
            <img src="logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>

    <script>
        // Fail-safe: ensure loader is removed even if other scripts throw errors
        (function () {
            function removeLoaderSoon() {
                try {
                    const loader = document.querySelector('.loader-wrapper');
                    if (!loader) return;
                    // quick fade then remove
                    loader.classList.add('fade-out');
                    setTimeout(() => {
                        try {
                            if (loader && loader.parentNode) loader.remove();
                        } catch (e) { /* ignore */ }
                        try { document.body.classList.remove('overflow-hidden'); } catch (e) { /* ignore */ }
                    }, 600);
                } catch (err) {
                    console && console.error && console.error('Loader cleanup error', err);
                    const loader = document.querySelector('.loader-wrapper');
                    if (loader && loader.parentNode) loader.remove();
                    try { document.body.classList.remove('overflow-hidden'); } catch (e) { }
                }
            }

            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                removeLoaderSoon();
            } else {
                document.addEventListener('DOMContentLoaded', removeLoaderSoon, { once: true });
                window.addEventListener('load', removeLoaderSoon, { once: true });
            }

            // If a global script error occurs before DOMContentLoaded fires, still try to remove the loader
            window.addEventListener('error', function () { setTimeout(removeLoaderSoon, 50); });
            window.addEventListener('unhandledrejection', function () { setTimeout(removeLoaderSoon, 50); });
        })();
    </script>

    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- HEADER / INTRO -->
    <div class="max-w-screen-2xl mx-auto px-5 pt-10 relative">
        <div class="glass rounded-3xl p-8 md:p-12 fade-in">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-20 -right-10 w-80 h-80 bg-gradient-to-br from-primary-200/70 to-primary-400/40 rounded-full blur-3xl opacity-60">
                </div>
                <div
                    class="absolute -bottom-24 -left-10 w-72 h-72 bg-gradient-to-tr from-primary-100/60 via-white/40 to-primary-300/40 rounded-full blur-3xl">
                </div>
            </div>
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
                    <div>
                        <p class="section-label mb-2">Secretary Dashboard</p>
                        <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-sky-900">Welcome back<span
                                class="font-light">,</span></h1>
                        <p class="mt-2 text-sky-800 text-lg font-medium">
                            <?= isset($_SESSION['official_name']) ? htmlspecialchars($_SESSION['official_name']) : 'Barangay Secretary' ?>
                        </p>
                        <p class="mt-3 max-w-xl text-sm md:text-base text-sky-700/80">Manage complaints, guide disputes
                            through mediation, and keep community justice moving with real‑time intelligence.</p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4 w-full md:w-auto">
                        <a href="add_complaints.php"
                            class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1 hover:shadow-lg transition">
                            <div class="flex items-center gap-2 text-sky-700"><i
                                    class="fa-solid fa-square-plus text-sky-600"></i><span
                                    class="text-xs font-semibold tracking-wide uppercase">New</span></div>
                            <span class="text-[13px] font-medium text-sky-900">Complaint</span>
                        </a>
                        <a href="view_cases.php"
                            class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1">
                            <div class="flex items-center gap-2 text-emerald-700"><i
                                    class="fa-solid fa-gavel text-emerald-600"></i><span
                                    class="text-xs font-semibold tracking-wide uppercase">View</span></div>
                            <span class="text-[13px] font-medium text-emerald-900">Cases</span>
                        </a>
                        <a href="meeting_log.php"
                            class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1">
                            <div class="flex items-center gap-2 text-indigo-700"><i
                                    class="fa-solid fa-file-lines text-indigo-600"></i><span
                                    class="text-xs font-semibold tracking-wide uppercase">Logs</span></div>
                            <span class="text-[13px] font-medium text-indigo-900">Meetings</span>
                        </a>
                        <a href="appoint_hearing.php"
                            class="quick-btn glass group rounded-xl px-4 py-3 flex flex-col items-start gap-1">
                            <div class="flex items-center gap-2 text-rose-700"><i
                                    class="fa-solid fa-calendar-days text-rose-600"></i><span
                                    class="text-xs font-semibold tracking-wide uppercase">Set</span></div>
                            <span class="text-[13px] font-medium text-rose-900">Hearing</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="max-w-screen-2xl mx-auto px-5 mt-10 pb-8 space-y-10">
        <div class="dashboard-equal-row">
            <!-- Left Column: KPIs -->
            <div class="card-flex lg:col-span-5 space-y-8 w-full">
                <div class="glass rounded-2xl p-6 md:p-7 fade-in card-body-grow">
                    <div class="flex items-center gap-2 mb-5">
                        <i class="fa-solid fa-chart-simple text-sky-600"></i>
                        <h2 class="text-sky-900 font-semibold tracking-tight">Statistics</h2>
                    </div>
                    <div class="space-y-6">
                        <!-- Complaints (Multi-bar by Status) -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-sky-800 tracking-wide">Total Complaints</span>
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-sky-100 text-sky-700"><?= $complaintsCount ?></span>
                            </div>
                            <div class="space-y-2">
                                <!-- Resolved -->
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-emerald-700 mb-0.5">
                                        <span>Resolved</span><span><?= $resolvedCount ?> </span></div>
                                    <div class="w-full h-2.5 rounded-full bg-emerald-50 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-500 progress-bar"
                                            style="width: <?= $complaintResolvedPercent ?>%"></div>
                                    </div>
                                </div>
                                <!-- Pending -->
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-amber-700 mb-0.5">
                                        <span>Pending</span><span><?= $pendingCount ?> </span></div>
                                    <div class="w-full h-2.5 rounded-full bg-amber-50 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 progress-bar"
                                            style="width: <?= $complaintPendingPercent ?>%"></div>
                                    </div>
                                </div>
                                <!-- Rejected -->
                                <div>
                                    <div class="flex justify-between text-[10px] font-medium text-rose-700 mb-0.5">
                                        <span>Rejected</span><span><?= $rejectedCount ?> </span></div>
                                    <div class="w-full h-2.5 rounded-full bg-rose-50 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-rose-400 to-rose-500 progress-bar"
                                            style="width: <?= $complaintRejectedPercent ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Cases -->
                        <div>
                            <div class="flex justify-between mb-1.5 text-xs font-medium text-emerald-800"><span>Total
                                    Cases</span><span
                                    class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700"><?= $casesCount ?></span>
                            </div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-emerald-400 to-emerald-500 h-full"
                                    style="width: <?= $caseResolvedPercent ?>%"></div>
                            </div>
                            <p class="text-[10px] mt-1 text-emerald-800/70">
                                Mediation: <?= $mediationCount ?> •
                                Conciliation: <?= $conciliationCount ?> •
                                Arbitration: <?= $arbitrationCount ?> •
                                Mediation Resolved: <?= $mediationResolvedCount ?> •
                                Conciliation Resolved: <?= $conciliationResolvedCount ?> •
                                Arbitration Resolved: <?= $arbitrationResolvedCount ?> •
                                Certificate to File Action: <?= $ctfaCount ?> •
                                Dismissed: <?= $dismissedCount ?>
                            </p>
                        </div>
                        <!-- Hearings -->
                        <div>
                            <div class="flex justify-between mb-1.5 text-xs font-medium text-rose-800"><span>Scheduled
                                    Hearings</span><span
                                    class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-700"><?= $scheduledHearings ?></span>
                            </div>
                            <div class="w-full bg-white/60 rounded-full progress-wrap overflow-hidden">
                                <div class="progress-bar bg-gradient-to-r from-rose-400 to-rose-500 h-full"
                                    style="width: <?= $hearingPercent ?>%"></div>
                            </div>
                            <p class="text-[10px] mt-1 text-rose-800/70">Relative to open cases</p>
                        </div>
                        <!-- Summary Chips -->
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="fa-solid fa-folder-open"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-blue-700">Mediation
                                    </p>
                                    <p class="text-lg leading-snug font-semibold text-blue-800"><?= $mediationCount ?>
                                    </p>
                                </div>
                            </div>

                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                                    <i class="fa-solid fa-circle-check"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-emerald-700">
                                        Resolved Cases</p>
                                    <p class="text-lg leading-snug font-semibold text-emerald-800">
                                        <?= $resolvedCaseCount ?></p>
                                    <p class="text-[10px] mt-1 text-emerald-700/80">Conciliation Resolved: <span class="font-semibold text-emerald-800"><?= $conciliationResolvedCount ?></span> • Arbitration Resolved: <span class="font-semibold text-emerald-800"><?= $arbitrationResolvedCount ?></span></p>
                                </div>
                            </div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">
                                    <i class="fa-solid fa-hourglass-half"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-amber-700">Pending
                                        Complaints</p>
                                    <p class="text-lg leading-snug font-semibold text-amber-800"><?= $pendingCount ?>
                                    </p>
                                </div>
                            </div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600">
                                    <i class="fa-solid fa-handshake"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-indigo-700">
                                        Mediation Resolved</p>
                                    <p class="text-lg leading-snug font-semibold text-indigo-800"><?= $mediationResolvedCount ?>
                                    </p>
                                </div>
                            </div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                                    <i class="fa-solid fa-balance-scale"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-emerald-700"> Conciliation
                                        </p>
                                    <p class="text-lg leading-snug font-semibold text-emerald-800"><?= $conciliationCount ?>
                                    </p>
                                </div>
                            </div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-pink-100 flex items-center justify-center text-pink-600">
                                    <i class="fa-solid fa-file-alt"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-pink-700">Arbitration
                                    </p>
                                    <p class="text-lg leading-snug font-semibold text-pink-800"><?= $arbitrationCount ?>
                                    </p>
                                </div>
                            </div>

                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-rose-100 flex items-center justify-center text-rose-600">
                                    <i class="fa-solid fa-ban"></i></div>
                                <div>
                                            <p class="text-[10px] tracking-wide uppercase font-semibold text-rose-700">Dismissed
                                                </p>
                                                <p class="text-lg leading-snug font-semibold text-rose-800">
                                                <?= $dismissedComplaints ?> <span class="text-xs text-rose-600 font-medium">complaints</span>
                                                &nbsp;&bull;&nbsp;
                                                <?= $dismissedCount ?> <span class="text-xs text-rose-600 font-medium">cases</span>
                                                </p>
                                </div>
                            </div>
                            <div class="glass rounded-xl p-4 flex items-start gap-3">
                                <div
                                    class="h-9 w-9 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">
                                    <i class="fa-solid fa-ban"></i></div>
                                <div>
                                    <p class="text-[10px] tracking-wide uppercase font-semibold text-gray-700">Certificate to File Action
                                    </p>
                                    <p class="text-lg leading-snug font-semibold text-gray-800"><?= $ctfaCount ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column: Calendar -->
            <div class="card-flex lg:col-span-7 glass rounded-2xl p-6 md:p-7 fade-in card-body-grow">
                <div class="flex items-center gap-2 mb-5">
                    <i class="fa-solid fa-calendar-days text-sky-600"></i>
                    <h2 class="text-sky-900 font-semibold tracking-tight">Upcoming Hearings</h2>
                    <a href="view_hearing_calendar.php" title="Open full calendar"
                       class="ml-auto inline-flex items-center justify-center h-8 w-8 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700 hover:text-sky-900 shadow-sm transition"
                       aria-label="Open full calendar">
                         <i class="fas fa-expand"></i>
                    </a>
                </div>
                <iframe src="./schedule/CalendarSec.php"
                    class="w-full rounded-xl border border-white/40 h-[640px] bg-white/50"></iframe>
            </div>
        </div>
        <!-- Activity & Trends -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Case Timeline (copied style from Resident/External) -->
            <div class="lg:col-span-5 glass rounded-2xl p-6 md:p-7 fade-in">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-sky-900 font-semibold tracking-tight flex items-center gap-2 whitespace-nowrap">
                            <i class="fa-solid fa-timeline text-sky-600"></i>Case Timeline
                        </h2>
                        <span class="hidden sm:inline px-2 py-1 rounded-lg text-[10px] font-medium bg-sky-600/10 text-sky-700 border border-white/50">LGC 1991 · Secs. 399–422</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2 min-w-0">
                        <label for="sec-case-select" class="text-[11px] text-sky-700 whitespace-nowrap">Select case:</label>
                        <select id="sec-case-select" class="w-64 text-[12px] px-2 py-1 rounded-md bg-white/70 border border-white/60 text-sky-900 focus:outline-none focus:ring-2 focus:ring-sky-300"></select>
                    </div>
                </div>
                <div id="sec-case-phase-summary" class="mb-3 text-[11px] text-sky-700/90"></div>
                <div class="space-y-4 relative">
                    <div class="absolute left-3 top-1 bottom-1 w-px bg-sky-200/70"></div>
                    <!-- Filing -->
                    <div class="relative pl-8 timeline-step" data-step="filing">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-purple-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                            <i class="fa-solid fa-file-circle-plus text-[11px]"></i>
                        </div>
                        <div class="rounded-xl border border-white/60 bg-white/60 p-3">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-sky-900">Filing of Complaint</p>
                                <span id="sec-badge-filing" class="ml-auto px-2 py-0.5 rounded-md text-[10px] bg-purple-500/15 text-purple-700 border border-purple-500/20">Day 0</span>
                            </div>
                            <p class="mt-1 text-[12px] text-sky-700/80">Starts when complaint is filed. <span id="sec-date-filing" class="font-medium text-sky-800"></span></p>
                        </div>
                    </div>
                    <!-- Mediation -->
                    <div class="relative pl-8 timeline-step" data-step="mediation">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                            <i class="fa-solid fa-handshake text-[11px]"></i>
                        </div>
                        <div class="rounded-xl border border-white/60 bg-white/60 p-3">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-sky-900">Mediation by Punong Barangay</p>
                                <span id="sec-badge-mediation" class="ml-auto px-2 py-0.5 rounded-md text-[10px] bg-amber-500/15 text-amber-700 border border-amber-500/20">Up to 15 days</span>
                            </div>
                            <p class="mt-1 text-[12px] text-sky-700/80">Attempt to amicably settle within 15 days. <span id="sec-range-mediation" class="font-medium text-sky-800"></span></p>
                        </div>
                    </div>
                    <!-- Pangkat -->
                    <div class="relative pl-8 timeline-step" data-step="pangkat">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-sky-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                            <i class="fa-solid fa-people-group text-[11px]"></i>
                        </div>
                        <div class="rounded-xl border border-white/60 bg-white/60 p-3">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-sky-900">Pangkat ng Tagapagkasundo (Conciliation)</p>
                                <span id="sec-badge-pangkat" class="ml-auto px-2 py-0.5 rounded-md text-[10px] bg-sky-500/15 text-sky-700 border border-sky-500/20">15–30 days</span>
                            </div>
                            <p class="mt-1 text-[12px] text-sky-700/80">Conciliation within 15 days; may extend another 15. <span id="sec-range-pangkat" class="font-medium text-sky-800"></span></p>
                        </div>
                    </div>
                    <!-- Arbitration -->
                    <div class="relative pl-8 timeline-step" data-step="arbitration">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                            <i class="fa-solid fa-scale-balanced text-[11px]"></i>
                        </div>
                        <div class="rounded-xl border border-white/60 bg-white/60 p-3">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-sky-900">Arbitration (if agreed)</p>
                                <span id="sec-badge-arbitration" class="ml-auto px-2 py-0.5 rounded-md text-[10px] bg-indigo-500/15 text-indigo-700 border border-indigo-500/20">Within 10 days</span>
                            </div>
                            <p class="mt-1 text-[12px] text-sky-700/80">If parties agree, award is rendered within 10 days. <span id="sec-note-arbitration" class="text-[11px] italic text-sky-600/80"></span></p>
                        </div>
                    </div>
                    <!-- Finality -->
                    <div class="relative pl-8 timeline-step" data-step="finality">
                        <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                            <i class="fa-solid fa-file-signature text-[11px]"></i>
                        </div>
                        <div class="rounded-xl border border-white/60 bg-white/60 p-3">
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] font-semibold text-sky-900">Execution of Settlement/Award</p>
                                <span id="sec-badge-finality" class="ml-auto px-2 py-0.5 rounded-md text-[10px] bg-emerald-500/15 text-emerald-700 border border-emerald-500/20">Final in 10 days</span>
                            </div>
                            <p class="mt-1 text-[12px] text-sky-700/80">Becomes final after 10 days unless repudiated. <span id="sec-note-finality" class="text-[11px] italic text-sky-600/80"></span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 glass rounded-2xl p-6 md:p-7 fade-in">
                <div class="flex items-center gap-2 mb-5">
                    <i class="fa-solid fa-chart-line text-sky-600"></i>
                    <h2 class="text-sky-900 font-semibold tracking-tight">6‑Month Trends</h2>
                </div>
                <canvas id="statsChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Expose cases for Case Timeline (Secretary)
        window.__secCases = <?= json_encode($secretaryCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.__secServerNowIso = '<?= $serverNowIso ?>';

        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [
                    { label: 'Complaints', data: <?= json_encode($monthlyComplaints) ?>, borderColor: '#0c9ced', backgroundColor: 'rgba(12,156,237,.12)', fill: true, tension: .4 },
                    { label: 'Cases', data: <?= json_encode($monthlyCases) ?>, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.12)', fill: true, tension: .4 },
                    { label: 'Mediation', data: <?= json_encode($monthlyMediation) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.10)', fill: true, tension: .4 },
                    { label: 'Conciliation', data: <?= json_encode($monthlyConciliation) ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.10)', fill: true, tension: .4 },
                    { label: 'Arbitration', data: <?= json_encode($monthlyArbitration) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.10)', fill: true, tension: .4 },
                    { label: 'Mediation Resolved', data: <?= json_encode($monthlyMediationResolved) ?>, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.10)', fill: true, tension: .4 },
                    { label: 'Conciliation Resolved', data: <?= json_encode($monthlyConciliationResolved) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.10)', fill: true, tension: .4 },
                    { label: 'Arbitration Resolved', data: <?= json_encode($monthlyArbitrationResolved) ?>, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.10)', fill: true, tension: .4 },
                    { label: 'Certificate to File Action', data: <?= json_encode($monthlyCTFA) ?>, borderColor: '#5f5757', backgroundColor: 'rgba(95,87,87,.10)', fill: true, tension: .4 },
                    { label: 'Dismissed', data: <?= json_encode($monthlyDismissed) ?>, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.10)', fill: true, tension: .4 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, usePointStyle: true } } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } }
            }
        });
    </script>

    <?php include 'sidebar_.php'; ?>
    <script>

        document.addEventListener('DOMContentLoaded', function () {
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
                }, 5000);
            }, 3500);
        });

        // Also ensure loader is hidden once all content is fully loaded (failsafe)
        window.addEventListener('load', function () {
            const loader = document.querySelector('.loader-wrapper');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    if (loader && loader.parentNode) loader.remove();
                    document.body.classList.remove('overflow-hidden');
                }, 5000);
            }
        });

        document.querySelectorAll('.toggle-menu').forEach(button => {
            button.addEventListener('click', () => {
                const submenu = button.nextElementSibling;
                submenu.classList.toggle('hidden');
            });
        });
        document.addEventListener('DOMContentLoaded', function () {

            document.querySelectorAll('.submenu').forEach(submenu => {
                if (submenu.classList.contains('hidden')) {
                    submenu.classList.remove('active');
                }
            });



            document.getElementById('close-sidebar').addEventListener('click', function () {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.add('-translate-x-full');
                // Remove overlay when sidebar is closed
                removeSidebarOverlay();
            });            // Toggle submenu items with animation
            document.querySelectorAll('.toggle-menu').forEach(button => {
                button.addEventListener('click', function () {
                    let submenu = this.nextElementSibling;

                    // Use both hidden and active classes for better animation control
                    submenu.classList.toggle('hidden');

                    // Add a slight delay before adding/removing active class
                    if (!submenu.classList.contains('hidden')) {
                        setTimeout(() => {
                            submenu.classList.add('active');
                        }, 10);
                    } else {
                        submenu.classList.remove('active');
                    }

                    // Rotate chevron icon when clicked
                    const chevron = this.querySelector('.fa-chevron-down');
                    if (chevron) {
                        chevron.classList.toggle('rotate-180');
                    }

                    // Add active state to the clicked menu item
                    this.classList.toggle('bg-primary-50');
                    this.classList.toggle('text-primary-700');
                });
            });

            // Function to add overlay when sidebar is open
            function addSidebarOverlay() {
                // Check if overlay already exists
                if (!document.getElementById('sidebar-overlay')) {
                    const overlay = document.createElement('div');
                    overlay.id = 'sidebar-overlay';
                    overlay.className = 'fixed inset-0 bg-black bg-opacity-30 z-40';
                    document.body.appendChild(overlay);

                    // Close sidebar when overlay is clicked
                    overlay.addEventListener('click', function () {
                        document.getElementById('sidebar').classList.add('-translate-x-full');
                        removeSidebarOverlay();
                    });
                }
            }

            // Function to remove overlay
            function removeSidebarOverlay() {
                const overlay = document.getElementById('sidebar-overlay');
                if (overlay) {
                    overlay.remove();
                }
            }


        });



        // Removed legacy dummy chart init & loadStatistics call (not needed)
    </script>
    <script>
        // Case Timeline logic (Secretary)
        document.addEventListener('DOMContentLoaded', () => {
            const cases = Array.isArray(window.__secCases) ? window.__secCases : [];
            const now = new Date(window.__secServerNowIso || Date.now());
            const select = document.getElementById('sec-case-select');
            const summary = document.getElementById('sec-case-phase-summary');
            const fmt = (d) => new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            const addDays = (date, days) => { const dt = new Date(date); dt.setDate(dt.getDate() + days); return dt; };
            const daysBetween = (a, b) => Math.floor((new Date(b) - new Date(a)) / 86400000);

            // Step elements for highlighting
            const steps = {
                filing: document.querySelector('.timeline-step[data-step="filing"]'),
                mediation: document.querySelector('.timeline-step[data-step="mediation"]'),
                pangkat: document.querySelector('.timeline-step[data-step="pangkat"]'),
                arbitration: document.querySelector('.timeline-step[data-step="arbitration"]'),
                finality: document.querySelector('.timeline-step[data-step="finality"]')
            };

            function clearStepClasses() {
                Object.values(steps).forEach(el => {
                    if (!el) return;
                    el.classList.remove('opacity-60', 'ring-2', 'ring-sky-400', 'ring-emerald-400');
                });
            }
            function mark(el, type) {
                if (!el) return;
                if (type === 'current') el.classList.add('ring-2', 'ring-sky-400');
                if (type === 'completed') el.classList.add('ring-2', 'ring-emerald-400');
                if (type === 'upcoming') el.classList.add('opacity-60');
            }
            // Determine phase from the server-side Case_Status field (preferred over elapsed days)
            function determinePhaseFromStatus(statusStr) {
                const s = (statusStr || '').toString().toLowerCase().trim();
                // default
                let phase = 'filing';
                // resolved/closed-like statuses -> finality
                if (s.includes('resolved') || s.includes('dismissed') || s.includes('certificate to file action') || s.includes('ctfa')) {
                    phase = 'finality';
                } else if (s.includes('arbitration') && !s.includes('resolved')) {
                    phase = 'arbitration';
                } else if (s.includes('conciliation') || s.includes('pangkat')) {
                    phase = 'pangkat';
                } else if (s.includes('mediation') && !s.includes('resolved')) {
                    phase = 'mediation';
                } else if (s === '' || s.includes('complaint') || s.includes('filed') || s.includes('open')) {
                    phase = 'filing';
                }
                return { phase };
            }

            function escapeHtml(unsafe) {
                if (unsafe === null || unsafe === undefined) return '';
                return String(unsafe)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function updateTimeline(c) {
                if (!c) {
                    summary.innerHTML = '<span class="text-[12px] text-sky-700/70">No case selected.</span>';
                    ['filing','mediation','pangkat','arbitration','finality'].forEach(step => {
                        // clear badges
                        const ids = {
                            filing: ['sec-badge-filing','sec-date-filing'],
                            mediation: ['sec-badge-mediation','sec-range-mediation'],
                            pangkat: ['sec-badge-pangkat','sec-range-pangkat'],
                            arbitration: ['sec-badge-arbitration','sec-note-arbitration'],
                            finality: ['sec-badge-finality','sec-note-finality']
                        }[step];
                        if (!ids) return;
                        ids.forEach(id => { const el = document.getElementById(id); if (el) el.textContent = ''; });
                    });
                    clearStepClasses();
                    return;
                }
                const filed = c.Date_Filed;
                const caseType = escapeHtml(c.case_type || c.Case_Type || '');
                const caseId = escapeHtml(c.Case_ID);
                const status = escapeHtml(c.Case_Status || '');
                const complainant = escapeHtml(c.complainant_name || 'Unknown');
                const respondent = escapeHtml(c.respondent_name || 'Unknown');

                // Phase windows (based on Resident timeline semantics)
                const filingDate = new Date(filed);
                const mediationEnd = addDays(filed, 15);
                const pangkatStart = addDays(filed, 15);
                const pangkatEnd = addDays(filed, 30); // extendable to 30
                const arbitrationEnd = addDays(filed, 40); // indicative window

                // Determine current phase based on server-side Case_Status and compute day count for display
                const statusPhase = determinePhaseFromStatus(status).phase;
                let phaseLabel = 'Unknown';
                if (statusPhase === 'filing') phaseLabel = 'Filing of Complaint';
                else if (statusPhase === 'mediation') phaseLabel = 'Mediation by Punong Barangay';
                else if (statusPhase === 'pangkat') phaseLabel = 'Pangkat ng Tagapagkasundo';
                else if (statusPhase === 'arbitration') phaseLabel = 'Arbitration (if agreed)';
                else if (statusPhase === 'finality') phaseLabel = 'Finalized / Resolved';

                const dayN = Math.max(0, daysBetween(filed, now));

                // Top summary - new format: [CaseNumber]-[Status]: Complainant vs. Respondent
                summary.innerHTML = `
                    <span class="px-2 py-1 rounded-md bg-white/60 border border-white/60 text-[11px] text-sky-800 font-medium">${caseId}-${status}: ${complainant} vs. ${respondent}</span>
                    <span class="ml-2 text-[11px] text-sky-700/80">· Day <span class="font-semibold">${dayN}</span> · Phase: <span class="font-semibold">${phaseLabel}</span></span>
                `;

                // Filing
                const badgeFiling = document.getElementById('sec-badge-filing');
                const dateFiling = document.getElementById('sec-date-filing');
                if (badgeFiling) badgeFiling.textContent = 'Day 0';
                if (dateFiling) dateFiling.textContent = fmt(filed);

                // Mediation (0-15 days)
                const badgeMed = document.getElementById('sec-badge-mediation');
                const rangeMed = document.getElementById('sec-range-mediation');
                if (badgeMed) badgeMed.textContent = 'Up to 15 days';
                if (rangeMed) rangeMed.textContent = `${fmt(filed)} – ${fmt(mediationEnd)}`;

                // Pangkat (15–30 days)
                const badgeP = document.getElementById('sec-badge-pangkat');
                const rangeP = document.getElementById('sec-range-pangkat');
                if (badgeP) badgeP.textContent = '15–30 days';
                if (rangeP) rangeP.textContent = `${fmt(pangkatStart)} – ${fmt(pangkatEnd)}`;

                // Arbitration (if agreed) – indicative (within 10 days)
                const badgeA = document.getElementById('sec-badge-arbitration');
                const noteA = document.getElementById('sec-note-arbitration');
                if (badgeA) badgeA.textContent = 'Within 10 days';
                if (noteA) noteA.textContent = 'Optional – applies only if parties agree to arbitrate.';

                // Finality – 10 days after award/settlement (display guidance)
                const badgeF = document.getElementById('sec-badge-finality');
                const noteF = document.getElementById('sec-note-finality');
                if (badgeF) badgeF.textContent = 'Final in 10 days';
                if (noteF) noteF.textContent = 'Final and executory 10 days after settlement/award unless repudiated.';

                // Highlight phases (completed/current/upcoming) based on Case_Status-derived phase
                clearStepClasses();
                if (statusPhase === 'filing') {
                    mark(steps.filing, 'current');
                    mark(steps.mediation, 'upcoming');
                    mark(steps.pangkat, 'upcoming');
                    mark(steps.arbitration, 'upcoming');
                    mark(steps.finality, 'upcoming');
                } else if (statusPhase === 'mediation') {
                    mark(steps.filing, 'completed');
                    mark(steps.mediation, 'current');
                    mark(steps.pangkat, 'upcoming');
                    mark(steps.arbitration, 'upcoming');
                    mark(steps.finality, 'upcoming');
                } else if (statusPhase === 'pangkat') {
                    mark(steps.filing, 'completed');
                    mark(steps.mediation, 'completed');
                    mark(steps.pangkat, 'current');
                    mark(steps.arbitration, 'upcoming');
                    mark(steps.finality, 'upcoming');
                } else if (statusPhase === 'arbitration') {
                    mark(steps.filing, 'completed');
                    mark(steps.mediation, 'completed');
                    mark(steps.pangkat, 'completed');
                    mark(steps.arbitration, 'current');
                    mark(steps.finality, 'upcoming');
                } else if (statusPhase === 'finality') {
                    mark(steps.filing, 'completed');
                    mark(steps.mediation, 'completed');
                    mark(steps.pangkat, 'completed');
                    mark(steps.arbitration, 'completed');
                    mark(steps.finality, 'current');
                }
            }

            if (select) {
                if (!cases.length) {
                    select.innerHTML = '<option value="">No cases found</option>';
                } else {
                    select.innerHTML = cases.map(c => {
                        const caseNum = c.Case_ID || 'N/A';
                        const status = (c.Case_Status || '').toString().trim();
                        const complainant = (c.complainant_name || 'Unknown').toString().trim();
                        const respondent = (c.respondent_name || 'Unknown').toString().trim();
                        const label = `${caseNum}-${status}: ${complainant} vs. ${respondent}`;
                        return `<option value="${c.Case_ID}">${escapeHtml(label)}</option>`;
                    }).join('');
                    const initial = cases[0];
                    updateTimeline(initial);
                    select.value = initial ? String(initial.Case_ID) : '';
                    select.addEventListener('change', () => {
                        const id = select.value;
                        const found = cases.find(c => String(c.Case_ID) === id);
                        updateTimeline(found);
                    });
                }
            }
        });
    </script>

    <?php include '../chatbot/bpamis_case_assistant.php' ?>
    
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
                <button id="reschedule" data-id="" class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors inline-flex items-center gap-1 sm:gap-2">
                    <i class="far fa-calendar-plus text-xs sm:text-sm"></i>
                    <span class="hidden sm:inline">Reschedule</span>
                    <span class="sm:hidden">Resched</span>
                </button>
                <button id="delete" data-id="" class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors inline-flex items-center gap-1 sm:gap-2">
                    <i class="far fa-trash-alt text-xs sm:text-sm"></i>
                    <span>Delete</span>
                </button>
                <button class="px-2.5 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors"
                    onclick="document.getElementById('eventModal').classList.add('hidden');">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Listen for messages from the calendar iframe
        window.addEventListener('message', function(event) {
            // Security check - verify origin if needed
            // if (event.origin !== window.location.origin) return;
            
            if (event.data.type === 'openEventModal') {
                const modal = document.getElementById('eventModal');
                const title = document.getElementById('modalTitle');
                const content = document.getElementById('modalContent');
                const rescheduleBtn = document.getElementById('reschedule');
                const deleteBtn = document.getElementById('delete');
                
                const data = event.data.payload;
                
                title.textContent = data.title || 'Schedule Details';
                content.innerHTML = data.content || '';
                rescheduleBtn.setAttribute('data-id', data.eventId || '');
                deleteBtn.setAttribute('data-id', data.eventId || '');
                
                modal.classList.remove('hidden');
            }
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
                document.getElementById('eventModal').classList.add('hidden');
            }
        });

        // Reschedule button handler
        document.getElementById('reschedule').addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (id) {
                window.location.href = `reschedule_hearing.php?id=${id}`;
            }
        });

        // Delete button handler
        document.getElementById('delete').addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            if (id) {
                if (confirm('Are you sure you want to delete this hearing?')) {
                    // Include a return parameter so the delete script can redirect back to this page
                    // Use the site-root relative path (no leading slash) which the delete script will normalize.
                    window.location.href = `schedule/delete_schedule.php?id=${id}&return=bpamis/SecMenu/home-secretary.php`;
                }
            }
        });
    </script>

    <?php include '../includes/footer.php' ?>
    
    </body>

</html>