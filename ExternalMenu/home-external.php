<?php
include '../server/server.php';
include '../controllers/session_control.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../bpamis_website/login.php");
    exit();
}

$external_id = (int)$_SESSION['user_id'];
$full_name = "External Complainant";

// Resolve actual table names for Linux/case-sensitive hosts
$T_EXTERNAL = bpamis_table($conn, 'external_complainant');
$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$T_CASE_INFO = bpamis_table($conn, 'case_info');
$T_SCHEDULE_LIST = bpamis_table($conn, 'schedule_list');

$TB_EXTERNAL = bpamis_quote_table($T_EXTERNAL);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_SCHEDULE_LIST = bpamis_quote_table($T_SCHEDULE_LIST);

// Get full name
if ($stmt = $conn->prepare("SELECT first_name, last_name FROM {$TB_EXTERNAL} WHERE external_complaint_id = ?")) {
    $stmt->bind_param("i", $external_id);
    if ($stmt->execute()) {
        $result = bpamis_stmt_get_result($stmt);
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $full_name = ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '');
            $full_name = trim($full_name) !== '' ? trim($full_name) : $full_name;
        }
    }
    $stmt->close();
}

// Count Complaints
$complaintsCount = (int)bpamis_query_scalar($conn, "SELECT COUNT(*) AS total FROM {$TB_COMPLAINT_INFO} WHERE external_complainant_id = $external_id", 'total', 0);

// Count Cases via complaint_info
$casesCount = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id
", 'total', 0);

// Pending Complaints
$pendingComplaints = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_COMPLAINT_INFO} 
    WHERE external_complainant_id = $external_id AND LOWER(TRIM(Status)) = 'pending'
", 'total', 0);


// Resolved Cases: sum of Mediation Resolved + Conciliation Resolved + Arbitration Resolved
$mediationResolved = (int)bpamis_query_scalar(
    $conn,
    "SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND TRIM(LOWER(Case_Status)) = 'mediation resolved'",
    'total',
    0
);
$conciliationResolved = (int)bpamis_query_scalar(
    $conn,
    "SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND TRIM(LOWER(Case_Status)) = 'conciliation resolved'",
    'total',
    0
);
$arbitrationResolved = (int)bpamis_query_scalar(
    $conn,
    "SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND TRIM(LOWER(Case_Status)) = 'arbitration resolved'",
    'total',
    0
);
$resolvedCases = (int)$mediationResolved + (int)$conciliationResolved + (int)$arbitrationResolved;

// Pending Cases
$pendingCases = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND Case_Status = 'Pending'
", 'total', 0);

$resolutionCases = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND Case_Status = 'conciliation'
", 'total', 0);

$mediationCases = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND Case_Status = 'mediation'
", 'total', 0);

$settlementCases = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND Case_Status = 'arbitration'
", 'total', 0);

// Scheduled Hearings
$scheduledHearings = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_SCHEDULE_LIST} sl
    JOIN {$TB_CASE_INFO} ci ON sl.Case_ID = ci.Case_ID
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID
    WHERE co.external_complainant_id = $external_id
", 'total', 0);

// Open Cases
$openCases = (int)bpamis_query_scalar($conn, "
    SELECT COUNT(*) AS total 
    FROM {$TB_CASE_INFO} ci 
    JOIN {$TB_COMPLAINT_INFO} co ON ci.Complaint_ID = co.Complaint_ID 
    WHERE co.external_complainant_id = $external_id AND Case_Status = 'open'
", 'total', 0);



// Percentages (guard divisions)
$pendingComplaintsPercent = $complaintsCount ? round(($pendingComplaints / $complaintsCount) * 100) : 0;
$caseResolvedPercent = $casesCount ? round(($resolvedCases / $casesCount) * 100) : 0;
$hearingPercent = $casesCount ? min(100, round(($scheduledHearings / max($casesCount, 1)) * 100)) : 0;

// Upcoming vs Past Hearings for Appointments (External): upcoming = tomorrow onward; past = today and earlier (limit 15)
$upcomingHearings = [];
$pastHearings = [];

if (
    $stmtUpcoming = $conn->prepare("SELECT sl.hearingID, sl.hearingTitle, sl.hearingDateTime, sl.remarks, sl.place, ci.Case_ID
    FROM schedule_list sl
    JOIN case_info ci ON sl.Case_ID = ci.Case_ID
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    WHERE co.external_complainant_id = ? AND DATE(sl.hearingDateTime) >= (CURDATE() + INTERVAL 1 DAY)
    ORDER BY sl.hearingDateTime ASC")
) {
    $stmtUpcoming->bind_param('i', $external_id);
    if ($stmtUpcoming->execute()) {
        $resUp = bpamis_stmt_get_result($stmtUpcoming);
        while ($r = $resUp->fetch_assoc()) {
            $upcomingHearings[] = $r;
        }
    }
    $stmtUpcoming->close();
}

if (
    $stmtPast = $conn->prepare("SELECT sl.hearingID, sl.hearingTitle, sl.hearingDateTime, sl.remarks, sl.place, ci.Case_ID
    FROM schedule_list sl
    JOIN case_info ci ON sl.Case_ID = ci.Case_ID
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    WHERE co.external_complainant_id = ? AND DATE(sl.hearingDateTime) <= CURDATE()
    ORDER BY sl.hearingDateTime DESC LIMIT 15")
) {
    $stmtPast->bind_param('i', $external_id);
    if ($stmtPast->execute()) {
        $resPast = bpamis_stmt_get_result($stmtPast);
        while ($r = $resPast->fetch_assoc()) {
            $pastHearings[] = $r;
        }
    }
    $stmtPast->close();
}

// External cases for Case Timeline selector (Complaint Date_Filed based)
$externalCases = [];
if (
    $stmtCases = $conn->prepare("SELECT ci.Case_ID, co.Complaint_ID, co.Complaint_Title, co.Date_Filed, ci.Case_Status, co.case_type
    FROM case_info ci
    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    WHERE co.external_complainant_id = ?
    ORDER BY co.Date_Filed DESC, ci.Case_ID DESC")
) {
    $stmtCases->bind_param('i', $external_id);
    if ($stmtCases->execute()) {
        $resCases = bpamis_stmt_get_result($stmtCases);
        while ($rc = $resCases->fetch_assoc()) {
            if (!empty($rc['Date_Filed'])) {
                $dt = new DateTime($rc['Date_Filed']);
                $rc['Date_Filed'] = $dt->format('Y-m-d H:i:s');
            }
            $externalCases[] = $rc;
        }
    }
    $stmtCases->close();
}
$serverNowIso = (new DateTime())->format('Y-m-d\TH:i:sP');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title> External Dashboard</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="tailwind.js"></script>
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

        .orb {
            position: fixed; /* keep decorative orbs fixed to the viewport to avoid parallax while scrolling */
            border-radius: 50%;
            filter: blur(40px);
            opacity: .55;
            mix-blend-mode: multiply;
            pointer-events: none; /* ensure orbs never intercept clicks */
            will-change: transform; /* hint for smoother animations */
            z-index: 0;
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

        .hero-blush {
            background: linear-gradient(135deg, rgba(242, 247, 253, 0.7), rgba(239, 246, 255, .65));
        }

        .section-label {
            font-size: .65rem;
            letter-spacing: .09em;
            font-weight: 600;
            text-transform: uppercase;
            color: #0369a1;
        }

        .progress-wrap {
            height: 12px;
        }

        .progress-bar {
            transition: width 1s cubic-bezier(.4, .0, .2, 1);
        }

        .quick-btn {
            position: relative;
            overflow: hidden;
            transition: .35s;
        }

        .quick-btn:before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .6), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity .5s;
        }

        .quick-btn:hover:before {
            opacity: 1;
        }

        .quick-btn:hover {
            transform: translateY(-4px);
        }

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

        /* Premium stat card animations (parity with resident) */
        .premium-stat-card {
            transition: transform .5s cubic-bezier(.34, 1.56, .4, 1), box-shadow .4s ease;
            will-change: transform;
        }

        .premium-stat-card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 18px 40px -15px rgba(14, 116, 144, 0.25), 0 8px 22px -8px rgba(14, 116, 144, .18);
        }

        .premium-stat-card .premium-icon-container {
            transition: transform .55s cubic-bezier(.34, 1.56, .4, 1), filter .4s;
        }

        .premium-stat-card:hover .premium-icon-container {
            transform: rotate(-6deg) scale(1.1) translateY(-3px);
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, .15));
            box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.12), 0 10px 18px -6px rgba(0, 0, 0, 0.18);
        }

        .premium-icon-light {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, .8), rgba(255, 255, 255, 0));
            opacity: .35;
            mix-blend-mode: overlay;
            pointer-events: none;
        }

        .premium-stat-inner:before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .55), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity .6s;
            pointer-events: none;
        }

        .premium-stat-card:hover .premium-stat-inner:before {
            opacity: .6;
        }

        .premium-stat-card:hover .premium-stat-inner {
            background: linear-gradient(135deg, rgba(255, 255, 255, .82), rgba(224, 242, 254, .52));
            border-color: rgba(255, 255, 255, .8);
            box-shadow: 0 16px 38px -14px rgba(14, 116, 144, .26), 0 0 0 2px rgba(59, 130, 246, .14) inset;
        }

        .premium-stat-border {
            background: linear-gradient(125deg, rgba(255, 255, 255, .85), rgba(255, 255, 255, 0) 60%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .5) inset, 0 0 0 1px rgba(255, 255, 255, .25);
        }

        .premium-stat-particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .premium-stat-particles:before,
        .premium-stat-particles:after {
            content: "";
            position: absolute;
            width: 140%;
            height: 140%;
            top: -20%;
            left: -20%;
            background: radial-gradient(circle at 20% 30%, rgba(255, 255, 255, .55) 0, rgba(255, 255, 255, 0) 55%), radial-gradient(circle at 80% 70%, rgba(255, 255, 255, .45) 0, rgba(255, 255, 255, 0) 60%), radial-gradient(circle at 40% 80%, rgba(255, 255, 255, .35) 0, rgba(255, 255, 255, 0) 65%);
            animation: drift 18s linear infinite;
            opacity: .35;
        }

        .premium-stat-particles:after {
            animation-direction: reverse;
            animation-duration: 24s;
        }

        @keyframes drift {
            to {
                transform: translate3d(8%, -6%, 0) rotate(1deg);
            }
        }

        .count-up {
            opacity: 0;
            transform: translateY(4px);
            transition: opacity .4s ease .2s, transform .55s cubic-bezier(.34, 1.56, .4, 1) .15s;
        }

        .count-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .progress-bar {
            transition: width 1s ease-in-out;
        }

        /* Modern Calendar Styles */
        .calendar-container {
            --fc-border-color: #f0f0f0;
            --fc-daygrid-event-dot-width: 6px;
            --fc-event-border-radius: 6px;
            --fc-small-font-size: 0.75rem;
        }

        .fc-daygrid-event-dot {
            /* the actual dot */
            margin-left: 50px;
        }

        .calendar-container .fc-theme-standard th {
            padding: 12px 0;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #6b7280;
            border: none;
        }

        .calendar-container .fc-theme-standard td {
            border-color: #f5f5f5;
        }

        .calendar-container .fc-col-header-cell {
            background: transparent;
        }

        .calendar-container .fc-toolbar-title {
            font-weight: 500;
            font-size: 1.1rem;
        }

        .calendar-container .fc-button {
            box-shadow: none !important;
            padding: 0.5rem 0.75rem;
            border-radius: 6px !important;
            font-weight: 500;
            transition: all 0.2s ease;
            text-transform: capitalize;
            border: 1px solid #e5e7eb !important;
        }

        .calendar-container .fc-button-primary {
            background-color: white !important;
            color: #4b5563 !important;
        }

        .calendar-container .fc-button-primary:hover {
            background-color: #f9fafb !important;
            color: #111827 !important;
        }

        .calendar-container .fc-button-primary:not(:disabled).fc-button-active,
        .calendar-container .fc-button-primary:not(:disabled):active {
            background-color: #f0f7ff !important;
            color: #0281d4 !important;
        }

        .calendar-container .fc-daygrid-day-number {
            padding: 8px;
            font-size: 0.875rem;
            color: #374151;
        }

        .calendar-container .fc-daygrid-day.fc-day-today {
            background-color: #f0f7ff !important;
        }

        .calendar-container .fc-event {
            border: none !important;
            padding: 2px 4px;
            font-size: 0.75rem !important;
            margin-top: 1px;
            transition: transform 0.2s ease;
        }

        .calendar-container .fc-event:hover {
            transform: translateY(-1px);
        }

        .calendar-container .fc-toolbar.fc-header-toolbar {
            margin-bottom: 1.25em;
            flex-wrap: wrap;
        }

        .calendar-container .fc-view-harness {
            border-radius: 8px;
            overflow: hidden;
        }

        @media (max-width: 640px) {
            .calendar-container .fc-toolbar.fc-header-toolbar {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                align-items: center;
            }

            .calendar-container .fc-toolbar-chunk {
                display: flex;
                justify-content: center;
            }
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

        /* Styled thin scrollbar for lists */
        .styled-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .styled-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .styled-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, rgba(14, 116, 144, .35), rgba(14, 116, 144, .15));
            border-radius: 20px;
        }

        .styled-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, rgba(14, 116, 144, .55), rgba(14, 116, 144, .25));
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

<body class="font-sans text-gray-700 relative overflow-x-hidden">
    <div class="orb one"></div>
    <div class="orb two"></div>

    <?php include_once('../includes/external_nav.php'); ?>

    <!-- HERO (Refactored: Left welcome + quick actions, Right stats) -->
    <div class="max-w-screen-2xl mx-auto px-0 md:px-5 pt-6 relative">
        <div class="glass hero-blush rounded-3xl p-8 md:p-10 overflow-hidden fade-in">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-20 -right-10 w-80 h-80 bg-gradient-to-br from-primary-200/70 to-primary-400/40 rounded-full blur-3xl opacity-60">
                </div>
                <div
                    class="absolute -bottom-24 -left-10 w-72 h-72 bg-gradient-to-tr from-primary-100/60 via-white/40 to-primary-300/40 rounded-full blur-3xl">
                </div>
            </div>
            <div class="relative z-10 grid lg:grid-cols-2 gap-10 items-start">
                <!-- Left Column: welcome + quick actions -->
                <div class="flex flex-col gap-8">
                    <div>
                        <p class="section-label mb-2">External Dashboard</p>
                        <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-sky-900">Welcome<span
                                class="font-light">,</span></h1>
                        <p class="mt-2 text-sky-800 text-lg font-medium"><?= htmlspecialchars($full_name) ?></p>
                        <p class="mt-3 max-w-xl text-sm md:text-base text-sky-700/80">Monitor your complaints, hearings
                            and settlement progress with real‑time updates.</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide uppercase text-sky-700 mb-2">Action Buttons
                        </p>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 md:gap-4">
                            <!-- New Complaint -->
                            <a href="submit_complaints.php" role="button" aria-label="New Complaint"
                                class="quick-btn glass group rounded-xl px-3.5 py-2.5 min-h-[48px] border border-white/60 bg-white/50 hover:bg-white/70 shadow-sm hover:shadow-lg transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60">
                                <span
                                    class="relative h-12 w-12 sm:h-9 sm:w-9 shrink-0 aspect-square rounded-full bg-gradient-to-br from-sky-500 to-sky-600 text-white flex items-center justify-center shadow-inner ring-1 ring-white/30">
                                    <i class="fa-solid fa-square-plus text-2xl sm:text-base drop-shadow-[0_1px_1px_rgba(0,0,0,0.3)]"></i>
                                </span>
                                <span class="text-[12.4px] sm:text-sm font-semibold text-sky-900">New Complaint</span>
                                <i
                                    class="fa-solid fa-chevron-right ml-auto text-sky-600 opacity-0 -translate-x-0.5 group-hover:opacity-100 group-hover:translate-x-0 transition"></i>
                            </a>

                            <!-- View Complaints -->
                            <a href="view_complaints.php" role="button" aria-label="View Complaints"
                                class="quick-btn glass group rounded-xl px-3.5 py-2.5 min-h-[48px] border border-white/60 bg-white/50 hover:bg-white/70 shadow-sm hover:shadow-lg transition flex items-center gap-3 overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300/60">
                                <span
                                    class="relative h-12 w-12 sm:h-9 sm:w-9 shrink-0 aspect-square rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center shadow-inner ring-1 ring-white/30">
                                    <i class="fa-solid fa-clipboard-list text-2xl sm:text-base drop-shadow-[0_1px_1px_rgba(0,0,0,0.3)]"></i>
                                </span>
                                <span class="text-[12.2px] sm:text-sm font-semibold text-emerald-900 break-words leading-snug">View Complaints</span>
                                <i
                                    class="fa-solid fa-chevron-right ml-auto text-emerald-600 opacity-0 -translate-x-0.5 group-hover:opacity-100 group-hover:translate-x-0 transition"></i>
                            </a>

                            <!-- View Cases -->
                            <a href="view_cases.php" role="button" aria-label="View Cases"
                                class="quick-btn glass group rounded-xl px-3.5 py-2.5 min-h-[48px] border border-white/60 bg-white/50 hover:bg-white/70 shadow-sm hover:shadow-lg transition flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300/60">
                                <span
                                    class="relative h-11 w-11 sm:h-9 sm:w-9 shrink-0 aspect-square rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-inner ring-1 ring-white/30">
                                    <i class="fa-solid fa-gavel text-xl sm:text-base drop-shadow-[0_1px_1px_rgba(0,0,0,0.3)]"></i>
                                </span>
                                <span class="text-[13px] sm:text-sm font-semibold text-indigo-900">View Cases</span>
                                <i
                                    class="fa-solid fa-chevron-right ml-auto text-indigo-600 opacity-0 -translate-x-0.5 group-hover:opacity-100 group-hover:translate-x-0 transition"></i>
                            </a>
                        </div>

                    </div>
                </div>
                <!-- Right Column: Premium Stat Cards -->
                <div>
                    <div class="relative">
                        <div class="relative z-10 mb-6 flex items-center justify-between">
                            <h2 class="text-sky-900 font-semibold tracking-tight flex items-center gap-2"><i
                                    class="fa-solid fa-chart-line text-sky-600"></i> Overview</h2>
                            <span
                                class="px-2 py-1 rounded-md text-[10px] font-medium bg-white/40 text-sky-700 border border-white/50">Updated
                                <?= date('H:i') ?></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <!-- Cases -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-sky-400 to-sky-600 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-gavel"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                                Cases</p>
                                            <p class="text-lg font-semibold text-sky-900 leading-none count-up"
                                                data-target="<?= $casesCount ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-sky-700/70">Open: <span
                                            class="font-medium text-sky-800"><?= $openCases ?></span></p>
                                </div>
                            </div>
                            <!-- Complaints -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-file-circle-plus"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                                Complaints</p>
                                            <p class="text-lg font-semibold text-amber-900 leading-none count-up"
                                                data-target="<?= $complaintsCount ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-amber-700/70">Pending: <span
                                            class="font-medium text-amber-800"><?= $pendingComplaints ?></span></p>
                                </div>
                            </div>
                            <!-- Resolved -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Resolved</p>
                                            <p class="text-lg font-semibold text-emerald-900 leading-none count-up"
                                                data-target="<?= $resolvedCases ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-emerald-700/70">Mediation · Conciliation · Arbitration (Resolved)</p>
                                </div>
                            </div>
                            <!-- Hearings -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-rose-400 to-rose-600 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-calendar-day"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-700">
                                                Hearings</p>
                                            <p class="text-lg font-semibold text-rose-900 leading-none count-up"
                                                data-target="<?= $scheduledHearings ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-rose-700/70">Scheduled sessions</p>
                                </div>
                            </div>
                            <!-- Mediation -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-yellow-300 to-yellow-500 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-handshake"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-wide text-yellow-700">
                                                Mediation</p>
                                            <p class="text-lg font-semibold text-yellow-800 leading-none count-up"
                                                data-target="<?= $mediationCases ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-yellow-700/70">Ongoing phase</p>
                                </div>
                            </div>
                            <!-- Settlement -->
                            <div class="premium-stat-card relative group">
                                <div
                                    class="premium-stat-border absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition">
                                </div>
                                <div
                                    class="premium-stat-inner relative rounded-2xl p-4 bg-white/55 border border-white/60 flex flex-col gap-2 overflow-hidden">
                                    <div class="premium-stat-particles"></div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="premium-icon-container relative h-11 w-11 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 text-white flex items-center justify-center shadow-inner">
                                            <i class="fa-solid fa-scale-balanced"></i>
                                            <div class="premium-icon-light rounded-full"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">
                                                Arbitration</p>
                                            <p class="text-lg font-semibold text-indigo-900 leading-none count-up"
                                                data-target="<?= $settlementCases ?>">0</p>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-indigo-700/70">Finalized forms</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID: Upcoming Hearings full width -->
    <div class="max-w-screen-2xl mx-auto px-0 md:px-5 pt-6 relative">
        <div class="grid lg:grid-cols-12 gap-8 items-start">
            <!-- Upcoming Hearings (Full Width on LG) -->
            <div
                class="lg:col-span-7 glass rounded-2xl p-6 bg-gradient-to-br from-white/70 via-white/60 to-sky-50/60 backdrop-blur-xl border border-white/50 shadow-[0_10px_35px_-10px_rgba(14,116,144,0.18)] overflow-hidden">
                <div class="flex items-center gap-2 mb-5">
                    <i class="fa-solid fa-calendar-days text-sky-600"></i>
                    <h2 class="text-sky-900 font-semibold tracking-tight">Hearing Calendar</h2>
                    <a href="view_hearing_calendar_external.php" title="Open full calendar"
                       class="ml-auto inline-flex items-center justify-center h-8 w-8 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700 hover:text-sky-900 shadow-sm transition"
                       aria-label="Open full calendar">
                        <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                    </a>
                </div>

                <iframe src="../SecMenu/schedule/CalendarExternal.php"
                    class="w-full max-w-full rounded-xl border border-white/40 h-[360px] sm:h-[420px] md:h-[640px] bg-white/50"
                    title="Hearing Calendar" loading="lazy" referrerpolicy="no-referrer"></iframe>

                
            </div>
            <div class="lg:col-span-5 space-y-6">
                <!-- Case Timeline Card -->
                <div
                    class="relative rounded-2xl p-4 lg:p-6 bg-gradient-to-br from-white/70 via-white/60 to-sky-50/60 backdrop-blur-xl border border-white/50 shadow-[0_10px_35px_-10px_rgba(14,116,144,0.18)] overflow-hidden">
                    <div class="absolute inset-0 pointer-events-none">
                        <div
                            class="absolute -top-14 -left-10 w-48 h-48 bg-gradient-to-tr from-sky-300/30 to-sky-500/20 rounded-full blur-3xl">
                        </div>
                        <div
                            class="absolute -bottom-16 -right-14 w-56 h-56 bg-gradient-to-br from-sky-100/40 via-white/40 to-sky-200/30 rounded-full blur-2xl">
                        </div>
                    </div>
                    <div class="relative z-10">
                        <div class="flex flex-wrap items-start justify-between mb-3 gap-2 sm:gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <h3
                                    class="text-sky-900 font-semibold tracking-tight flex items-center gap-2 whitespace-nowrap">
                                    <i class="fa-solid fa-timeline text-sky-600"></i>Case Timeline
                                </h3>
                                <span
                                    class="hidden sm:inline px-2 py-1 rounded-lg text-[10px] font-medium bg-sky-600/10 text-sky-700 border border-white/50">LGC
                                    1991 · Secs. 399–422</span>
                            </div>
                            <div class="ml-0 sm:ml-auto w-full sm:w-auto flex items-center gap-2 min-w-0">
                                <label for="case-select" class="text-[11px] text-sky-700 whitespace-nowrap">Select
                                    case:</label>
                                <select id="case-select"
                                    class="w-full sm:w-40 text-[12px] px-2 py-1 rounded-md bg-white/70 border border-white/60 text-sky-900 focus:outline-none focus:ring-2 focus:ring-sky-300">
                                    <!-- options injected by JS -->
                                </select>
                            </div>
                        </div>
                        <div id="case-phase-summary" class="mb-3 text-[11px] text-sky-700/90 break-words"></div>
                        <div class="space-y-4 relative">
                            <div class="absolute left-2 sm:left-3 top-1 bottom-1 w-px bg-sky-200/70"></div>
                            <!-- Filing -->
                            <div class="relative pl-7 sm:pl-8 timeline-step" data-step="filing">
                                <div
                                    class="absolute left-0 top-0 w-6 h-6 rounded-full bg-purple-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                                    <i class="fa-solid fa-file-circle-plus text-[11px]"></i>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/60 p-2 sm:p-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-sky-900">Filing of Complaint</p>
                                        <span id="badge-filing"
                                            class="ml-0 sm:ml-auto mt-1 sm:mt-0 shrink-0 px-2 py-0.5 rounded-md text-[10px] bg-purple-500/15 text-purple-700 border border-purple-500/20">Day
                                            0</span>
                                    </div>
                                    <p class="mt-1 text-[12px] text-sky-700/80">Starts when complaint is filed. <span
                                            id="date-filing" class="font-medium text-sky-800"></span></p>
                                </div>
                            </div>
                            <!-- Mediation -->
                            <div class="relative pl-7 sm:pl-8 timeline-step" data-step="mediation">
                                <div
                                    class="absolute left-0 top-0 w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                                    <i class="fa-solid fa-handshake text-[11px]"></i>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/60 p-2 sm:p-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-sky-900">Mediation by Punong Barangay
                                        </p>
                                        <span id="badge-mediation"
                                            class="ml-0 sm:ml-auto mt-1 sm:mt-0 shrink-0 px-2 py-0.5 rounded-md text-[10px] bg-amber-500/15 text-amber-700 border border-amber-500/20">Up
                                            to 15 days</span>
                                    </div>
                                    <p class="mt-1 text-[12px] text-sky-700/80">Attempt to amicably settle within 15
                                        days. <span id="range-mediation" class="font-medium text-sky-800"></span></p>
                                </div>
                            </div>
                            <!-- Pangkat -->
                            <div class="relative pl-7 sm:pl-8 timeline-step" data-step="pangkat">
                                <div
                                    class="absolute left-0 top-0 w-6 h-6 rounded-full bg-sky-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                                    <i class="fa-solid fa-people-group text-[11px]"></i>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/60 p-2 sm:p-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-sky-900">Pangkat ng Tagapagkasundo
                                            (Conciliation)</p>
                                        <span id="badge-pangkat"
                                            class="ml-0 sm:ml-auto mt-1 sm:mt-0 shrink-0 px-2 py-0.5 rounded-md text-[10px] bg-sky-500/15 text-sky-700 border border-sky-500/20">15–30
                                            days</span>
                                    </div>
                                    <p class="mt-1 text-[12px] text-sky-700/80">Conciliation within 15 days; may extend
                                        another 15. <span id="range-pangkat" class="font-medium text-sky-800"></span>
                                    </p>
                                </div>
                            </div>
                            <!-- Arbitration -->
                            <div class="relative pl-7 sm:pl-8 timeline-step" data-step="arbitration">
                                <div
                                    class="absolute left-0 top-0 w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                                    <i class="fa-solid fa-scale-balanced text-[11px]"></i>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/60 p-2 sm:p-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-sky-900">Arbitration (if agreed)</p>
                                        <span id="badge-arbitration"
                                            class="ml-0 sm:ml-auto mt-1 sm:mt-0 shrink-0 px-2 py-0.5 rounded-md text-[10px] bg-indigo-500/15 text-indigo-700 border border-indigo-500/20">Within
                                            10 days</span>
                                    </div>
                                    <p class="mt-1 text-[12px] text-sky-700/80">If parties agree, award is rendered
                                        within 10 days. <span id="note-arbitration"
                                            class="text-[11px] italic text-sky-600/80"></span></p>
                                </div>
                            </div>
                            <!-- Finality -->
                            <div class="relative pl-7 sm:pl-8 timeline-step" data-step="finality">
                                <div
                                    class="absolute left-0 top-0 w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow ring-2 ring-white">
                                    <i class="fa-solid fa-file-signature text-[11px]"></i>
                                </div>
                                <div class="rounded-xl border border-white/60 bg-white/60 p-2 sm:p-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-sky-900">Execution of Settlement/Award
                                        </p>
                                        <span id="badge-finality"
                                            class="ml-0 sm:ml-auto mt-1 sm:mt-0 shrink-0 px-2 py-0.5 rounded-md text-[10px] bg-emerald-500/15 text-emerald-700 border border-emerald-500/20">Final
                                            in 10 days</span>
                                    </div>
                                    <p class="mt-1 text-[12px] text-sky-700/80">Becomes final after 10 days unless
                                        repudiated. <span id="note-finality"
                                            class="text-[11px] italic text-sky-600/80"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments removed -->
            </div>
        </div>
    </div>



    <script>        document.addEventListener('DOMContentLoaded', function () {
            var calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: {
                        left: 'prev,next',
                        center: 'title',
                        right: 'today'
                    },
                    buttonText: {
                        today: 'Today'
                    },
                    dayHeaderFormat: { weekday: 'short' },
                    eventTimeFormat: {
                        hour: 'numeric',
                        minute: '2-digit',
                        meridiem: 'short'
                    },
                    eventOrder: 'start',
                    eventDisplay: 'block',
                    displayEventTime: true,
                    events: [
                        {
                            title: 'Noise Complaint',
                            start: '2025-05-20T10:00:00',
                            backgroundColor: 'rgba(79, 70, 229, 0.8)',
                            borderColor: 'rgba(79, 70, 229, 0)',
                            textColor: '#ffffff',
                            extendedProps: {
                                type: 'hearing'
                            }
                        },
                        {
                            title: 'Property Dispute',
                            start: '2025-05-22T14:00:00',
                            backgroundColor: 'rgba(79, 70, 229, 0.8)',
                            borderColor: 'rgba(79, 70, 229, 0)',
                            textColor: '#ffffff',
                            extendedProps: {
                                type: 'hearing'
                            }
                        },
                        {
                            title: 'Mediation Session',
                            start: '2025-05-18T09:00:00',
                            backgroundColor: 'rgba(3, 105, 161, 0.8)',
                            borderColor: 'rgba(3, 105, 161, 0)',
                            textColor: '#ffffff',
                            extendedProps: {
                                type: 'mediation'
                            }
                        }
                    ],
                    eventClassNames: function (arg) {
                        return ['shadow-sm'];
                    },
                    eventDidMount: function (info) {
                        // Add tooltip with improved formatting
                        const eventType = info.event.extendedProps.type === 'hearing' ? 'Hearing' : 'Mediation';
                        info.el.setAttribute('title',
                            eventType + ': ' + info.event.title + '\n' +
                            'Time: ' + info.event.start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
                        );

                        // Add subtle hover effect
                        info.el.addEventListener('mouseover', function () {
                            this.style.transform = 'translateY(-2px)';
                            this.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
                        });

                        info.el.addEventListener('mouseout', function () {
                            this.style.transform = '';
                            this.style.boxShadow = '';
                        });
                    }
                });
                calendar.render();
            }

            // Show animations on load (guard if function exists)
            if (typeof animateStatistics === 'function') {
                animateStatistics();
            }
        });

        // Count-up animation for premium stat cards (parity with resident)
        document.addEventListener('DOMContentLoaded', () => {
            const counters = document.querySelectorAll('.count-up');
            const duration = 1300; // ms
            const easeOutCubic = t => 1 - Math.pow(1 - t, 3);

            function animateCount(el) {
                const target = parseInt(el.getAttribute('data-target') || '0', 10);
                if (isNaN(target)) return;
                const startTime = performance.now();
                function frame(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(1, elapsed / duration);
                    const eased = easeOutCubic(progress);
                    const value = Math.floor(eased * target);
                    el.textContent = value.toLocaleString();
                    if (progress < 1) requestAnimationFrame(frame); else el.textContent = target.toLocaleString();
                }
                requestAnimationFrame(frame);
            }

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const el = entry.target;
                            el.classList.add('visible');
                            animateCount(el);
                            obs.unobserve(el);
                        }
                    });
                }, { threshold: 0.4 });
                counters.forEach(c => observer.observe(c));
            } else {
                counters.forEach(c => { c.classList.add('visible'); animateCount(c); });
            }
        });


        // Guarded metrics updates (elements may not exist on this page)
        (function () {
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            const setWidth = (id, pct) => { const el = document.getElementById(id); if (el) el.style.width = pct + '%'; };
            setText('complaints-count', <?= $complaintsCount ?>);
            setText('cases-count', <?= $casesCount ?>);
            setText('hearings-count', <?= $pendingComplaints ?>);
            setText('resolved-count', <?= $resolvedCases ?>);
            setText('pending-count', <?= $pendingCases ?>);
            setText('mediated-count', <?= $scheduledHearings ?>);
            const totalComplaints = <?= $complaintsCount ?> || 1;
            const totalCases = <?= $casesCount ?> || 1;
            const pendingPercent = Math.min(100, (<?= $pendingComplaints ?> / totalComplaints) * 100);
            const casesPercent = Math.min(100, (<?= $casesCount ?> / totalComplaints) * 100);
            setWidth('complaints-progress', pendingPercent);
            setWidth('cases-progress', casesPercent);
            setWidth('hearings-progress', (<?= $pendingCases ?> / totalCases) * 100);
        })();



        // Initialize animation on page load
        // Guard window onload binding if function exists
        if (typeof animateStatistics === 'function') {
            window.onload = animateStatistics;
        }

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function () {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            if (menuButton && mobileMenu) {
                menuButton.addEventListener('click', function () {
                    this.classList.toggle('active');
                    if (mobileMenu.style.transform === 'translateY(0%)') {
                        mobileMenu.style.transform = 'translateY(-100%)';
                    } else {
                        mobileMenu.style.transform = 'translateY(0%)';
                    }
                });
            }
        });

        // Hearings list toggle for Appointments
        document.addEventListener('DOMContentLoaded', () => {
            const btnUpcoming = document.getElementById('btn-upcoming');
            const btnPast = document.getElementById('btn-past');
            const listUpcoming = document.getElementById('hearings-upcoming-list');
            const listPast = document.getElementById('hearings-past-list');
            if (!btnUpcoming || !btnPast || !listUpcoming || !listPast) return;
            function setActive(tab) {
                const active = ['bg-sky-600', 'text-white', 'shadow'];
                const inactive = ['text-sky-700'];
                if (tab === 'upcoming') {
                    listUpcoming.classList.remove('hidden');
                    listPast.classList.add('hidden');
                    btnUpcoming.classList.add(...active);
                    btnUpcoming.classList.remove(...inactive);
                    btnPast.classList.remove(...active);
                    btnPast.classList.add(...inactive);
                } else {
                    listUpcoming.classList.add('hidden');
                    listPast.classList.remove('hidden');
                    btnPast.classList.add(...active);
                    btnPast.classList.remove(...inactive);
                    btnUpcoming.classList.remove(...active);
                    btnUpcoming.classList.add(...inactive);
                }
            }
            btnUpcoming.addEventListener('click', () => setActive('upcoming'));
            btnPast.addEventListener('click', () => setActive('past'));
            setActive('upcoming');
        });

        // Case Timeline logic (External)
        window.__externalCases = <?php echo json_encode($externalCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.__serverNowIso = '<?php echo $serverNowIso; ?>';
        document.addEventListener('DOMContentLoaded', () => {
            const cases = Array.isArray(window.__externalCases) ? window.__externalCases : [];
            const now = new Date(window.__serverNowIso || Date.now());
            const select = document.getElementById('case-select');
            const summary = document.getElementById('case-phase-summary');
            if (!select || !summary) return;

            if (cases.length === 0) {
                select.innerHTML = '<option value="">No cases available</option>';
                summary.textContent = 'No case selected. Your timeline will appear here once you have a case.';
                return;
            } else {
                select.innerHTML = cases.map(c => {
                    const rawType = (c.case_type || c.Case_Type || '').toString().trim();
                    const type = (rawType || 'N/A').replace(/[&<>"']/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[s]));
                    return `<option value="${c.Case_ID}">Case #${c.Case_ID} — ${type}</option>`;
                }).join('');
            }

            const steps = {
                filing: document.querySelector('.timeline-step[data-step="filing"]'),
                mediation: document.querySelector('.timeline-step[data-step="mediation"]'),
                pangkat: document.querySelector('.timeline-step[data-step="pangkat"]'),
                arbitration: document.querySelector('.timeline-step[data-step="arbitration"]'),
                finality: document.querySelector('.timeline-step[data-step="finality"]')
            };
            const spans = {
                filing: document.getElementById('date-filing'),
                mediation: document.getElementById('range-mediation'),
                pangkat: document.getElementById('range-pangkat'),
                arbNote: document.getElementById('note-arbitration'),
                finNote: document.getElementById('note-finality')
            };
            function addDays(date, days) { const d = new Date(date); d.setDate(d.getDate() + days); return d; }
            function fmt(d) { try { return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); } catch { return d.toISOString().split('T')[0]; } }
            function daysBetween(a, b) { return Math.floor((b.getTime() - a.getTime()) / (1000 * 60 * 60 * 24)); }
            function clearMark() { Object.values(steps).forEach(el => { if (!el) return; el.classList.remove('opacity-60', 'ring-2', 'ring-sky-400', 'ring-emerald-400'); }); }
            function mark(el, type) { if (!el) return; if (type === 'current') el.classList.add('ring-2', 'ring-sky-400'); if (type === 'completed') el.classList.add('ring-2', 'ring-emerald-400'); if (type === 'upcoming') el.classList.add('opacity-60'); }
            function computePhase(dateFiledStr) { const filed = new Date(dateFiledStr); if (isNaN(filed)) return { phase: 'unknown', days: 0, filed: null }; const days = daysBetween(filed, now); if (days <= 0) return { phase: 'filing', days, filed }; if (days <= 15) return { phase: 'mediation', days, filed }; if (days <= 30) return { phase: 'pangkat', days, filed, ext: false }; if (days <= 45) return { phase: 'pangkat', days, filed, ext: true }; return { phase: 'beyond', days, filed }; }
            function updateTimeline(selected) {
                if (!selected || !selected.Date_Filed) return; const f = new Date(selected.Date_Filed); const medEnd = addDays(f, 15); const pangStart = addDays(medEnd, 0); const pangEnd = addDays(f, 30); const pangMax = addDays(f, 45); if (spans.filing) spans.filing.textContent = `Filed: ${fmt(f)}`; if (spans.mediation) spans.mediation.textContent = `${fmt(f)} → ${fmt(medEnd)} (15d)`; if (spans.pangkat) spans.pangkat.textContent = `${fmt(pangStart)} → ${fmt(pangEnd)} (15d) · Max: ${fmt(pangMax)} (45d)`; if (spans.arbNote) spans.arbNote.textContent = 'Optional phase — only if both parties agree'; if (spans.finNote) spans.finNote.textContent = 'Applies once a settlement/award is reached'; clearMark(); const status = computePhase(selected.Date_Filed); let phaseLabel = 'Unknown'; if (status.phase === 'filing') { mark(steps.filing, 'current'); mark(steps.mediation, 'upcoming'); mark(steps.pangkat, 'upcoming'); mark(steps.arbitration, 'upcoming'); mark(steps.finality, 'upcoming'); phaseLabel = 'Filing of Complaint'; } else if (status.phase === 'mediation') { mark(steps.filing, 'completed'); mark(steps.mediation, 'current'); mark(steps.pangkat, 'upcoming'); mark(steps.arbitration, 'upcoming'); mark(steps.finality, 'upcoming'); phaseLabel = 'Mediation by Punong Barangay'; } else if (status.phase === 'pangkat') { mark(steps.filing, 'completed'); mark(steps.mediation, 'completed'); mark(steps.pangkat, 'current'); mark(steps.arbitration, 'upcoming'); mark(steps.finality, 'upcoming'); phaseLabel = status.ext ? 'Pangkat Conciliation (Extended)' : 'Pangkat Conciliation'; } else if (status.phase === 'beyond') { mark(steps.filing, 'completed'); mark(steps.mediation, 'completed'); mark(steps.pangkat, 'completed'); mark(steps.arbitration, 'upcoming'); mark(steps.finality, 'upcoming'); phaseLabel = 'Beyond 45 days — escalate or finalize'; }
                const filedStr = fmt(f); const dayN = Math.max(0, computePhase(selected.Date_Filed).days); summary.innerHTML = `Selected: <span class="font-semibold">Case #${selected.Case_ID}</span> · Filed <span class="font-medium">${filedStr}</span> · <span class="font-semibold">Day ${dayN}</span> · Current phase: <span class="font-semibold">${phaseLabel}</span>`;
            }
            const initial = cases[0]; updateTimeline(initial); select.value = initial ? String(initial.Case_ID) : ''; select.addEventListener('change', () => { const id = select.value; const found = cases.find(c => String(c.Case_ID) === id); updateTimeline(found); });
        });
    </script>
    <div class="relative">
        <?php include('../chatbot/bpamis_case_assistant.php'); ?>
    </div>

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
                if (rescheduleBtn) rescheduleBtn.setAttribute('data-id', data.eventId || '');
                if (deleteBtn) deleteBtn.setAttribute('data-id', data.eventId || '');
                
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
        // Reschedule/Delete: forward action to iframe (CalendarExternal) which will handle confirmation and navigation.
        (function(){
            const iframe = document.querySelector('iframe[title="Hearing Calendar"]');
            const postAction = (action, id) => {
                if (!id) return;
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage({ type: 'modalAction', action: action, eventId: id }, '*');
                } else {
                    // fallback: navigate in parent if iframe not available
                    if (action === 'reschedule') window.location.href = `reschedule_hearing.php?id=${id}`;
                    if (action === 'delete' && confirm('Are you sure you want to delete this hearing?')) window.location.href = `schedule/delete_schedule.php?id=${id}&return=bpamis/SecMenu/home-secretary.php`;
                }
            };

            const resBtn = document.getElementById('reschedule');
            const delBtn = document.getElementById('delete');
            if (resBtn) resBtn.addEventListener('click', function(){ postAction('reschedule', this.getAttribute('data-id')); });
            if (delBtn) delBtn.addEventListener('click', function(){ postAction('delete', this.getAttribute('data-id')); });
        })();
    </script>

    <?php include '../includes/footer.php' ?>
    
</body>

</html>