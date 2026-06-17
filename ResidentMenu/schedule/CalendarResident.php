<?php
session_start();
require_once('db-connect.php');
include('../../server/server.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: ../bpamis_website/login.php");
  exit();
}

$Resident_ID = $_SESSION['user_id'];

$val = $conn->query("SELECT * FROM barangay_case_management.resident_info WHERE Resident_ID = $Resident_ID");
if ($val->num_rows == 0) {
  echo "<script>alert('Resident not found.'); window.location.href='login.php';</script>";
  exit;
}

$events = [];
$hearings_list = [];
$hearing_stmt = $conn->prepare("
  SELECT DISTINCT sl.hearingID, sl.hearingTitle, sl.hearingDateTime, sl.remarks, ci.Case_ID
  FROM barangay_case_management.schedule_list sl
  JOIN barangay_case_management.case_info ci ON sl.Case_ID = ci.Case_ID
  JOIN barangay_case_management.complaint_info co ON ci.Complaint_ID = co.Complaint_ID
  LEFT JOIN barangay_case_management.complaint_respondents cr ON co.Complaint_ID = cr.Complaint_ID
  WHERE (
    co.Resident_ID = ? 
    OR COALESCE(co.respondent_id, co.Respondent_ID) = ?
    OR cr.Respondent_ID = ?
  )
");
$hearing_stmt->bind_param("iii", $Resident_ID, $Resident_ID, $Resident_ID);
$hearing_stmt->execute();
$hearing_res = bpamis_stmt_get_result($hearing_stmt);
while ($r = $hearing_res->fetch_assoc()) {
  $sdate = date("F d, Y h:i A", strtotime($r['hearingDateTime']));
  $events[] = [
    'id' => 'hearing_' . $r['hearingID'],
    'title' => $r['hearingTitle'],
    'start' => $r['hearingDateTime'],
    'type' => 'hearing',
    'description' => $r['remarks'],
    'sdate' => $sdate,
    'color' => 'rgba(124, 58, 237, 0.7)', // purple for hearing
    'case_id' => $r['Case_ID']
  ];
  $hearings_list[] = [
    'hearing_id' => $r['hearingID'],
    'hearing_title' => $r['hearingTitle'],
    'hearing_datetime' => $r['hearingDateTime'],
    'case_id' => $r['Case_ID'],
    'sdate' => $sdate
  ];
}
$hearing_stmt->close();

$cases = [];
$case_stmt = $conn->prepare("
    SELECT ci.Case_ID, ci.Case_Status, ci.Date_Opened, co.Complaint_Title
    FROM barangay_case_management.case_info ci
    JOIN barangay_case_management.complaint_info co ON ci.Complaint_ID = co.Complaint_ID
    WHERE co.Resident_ID = ?
    ORDER BY ci.Date_Opened DESC
");
$case_stmt->bind_param("i", $Resident_ID);
$case_stmt->execute();
$case_res = bpamis_stmt_get_result($case_stmt);

while ($c = $case_res->fetch_assoc()) {
  $dateOpenedRaw = $c['Date_Opened'];
  $dateOpened = date("F d, Y", strtotime($dateOpenedRaw));
  $days_passed = (new DateTime())->diff(new DateTime($dateOpenedRaw))->days;
  $days_left = max(0, 45 - $days_passed);

  $cases[] = [
    'Case_ID' => $c['Case_ID'],
    'Complaint_Title' => $c['Complaint_Title'],
    'Case_Status' => $c['Case_Status'],
    'Date_Opened_Raw' => $dateOpenedRaw,
    'Date_Opened' => $dateOpened,
    'Days_Passed' => $days_passed,
    'Days_Left' => $days_left
  ];

  // Removed adding case-span events to the calendar; only hearings will be shown
}
$case_stmt->close();

$conn->close();

$events_json = json_encode($events);
$cases_json = json_encode($cases);
$hearings_json = json_encode($hearings_list);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Calendar — Cases & Hearings</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <link href="./fullcalendar/lib/main.min.css" rel="stylesheet" />
  <link href="./fullcalendar/lib/main.css" rel="stylesheet" />
  <script src="./js/jquery-3.6.0.min.js"></script>
  <script src="./fullcalendar/lib/main.min.js"></script>
  <script src="./fullcalendar/lib/main.js"></script>

  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    #calendar-wrapper {
      display: flex;
      padding: 0.8rem 0.8rem 0.5rem 0.5rem;
    }

    /* Modern glassmorphism calendar container */
    #calendar {
      background: rgba(248, 250, 252, 0.85);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
      border-radius: 22px;
      border: 1.5px solid #e0e7ef;
      padding: 1.25rem 1rem .75rem 1rem;
      transition: box-shadow 0.3s, transform 0.3s;
      backdrop-filter: blur(8px);
    }

    #calendar:hover {
      box-shadow: 0 16px 48px 0 rgba(31, 38, 135, 0.18);
      transform: translateY(-4px) scale(1.01);
    }

    /* Compact toolbar styling (aligned with Secretary calendar) */
    .fc .fc-toolbar-title {
      font-weight: 700;
      font-size: 1.05rem;
      color: #0281d4;
      letter-spacing: .4px;
      display: flex;
      align-items: center;
      gap: .4em;
    }

    .fc .fc-button {
      box-shadow: none !important;
      padding: .30rem .65rem;
      border-radius: 7px !important;
      font-weight: 600;
      font-size: .78rem;
      line-height: 1;
      transition: all .18s;
      text-transform: capitalize;
      border: 1px solid #b6e1f7 !important;
      background: linear-gradient(120deg, #e8f7ff 0%, #f3fbff 100%) !important;
      color: #0276c4 !important;
      margin: 0 .15rem;
      display: flex;
      align-items: center;
      gap: .25em;
      min-height: 30px;
    }

    .fc .fc-prev-button,
    .fc .fc-next-button {
      padding: .25rem .5rem !important;
      width: 32px;
      justify-content: center;
    }

    .fc .fc-button-primary:hover {
      background: linear-gradient(120deg, #d5eefc 0%, #e8f7ff 100%) !important;
      color: #0369a1 !important;
      transform: translateY(-2px);
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
      background: #e4f6ff !important;
      color: #0b6fa2 !important;
      box-shadow: inset 0 0 0 1px #9ed3ed;
    }

    .fc .fc-daygrid-day-number {
      padding: 12px;
      font-size: 1rem;
      color: #0281d4;
      font-weight: 600;
    }

    .fc .fc-daygrid-day.fc-day-today {
      background: linear-gradient(90deg, rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
      border-radius: 12px;
      box-shadow: 0 2px 8px 0 rgba(2, 129, 212, 0.08);
    }

    /* Highlight days that have any hearings (distinct from current day) */
    .fc .fc-daygrid-day.has-hearing:not(.fc-day-today) {
      background: linear-gradient(90deg, rgba(216, 180, 254, 0.35) 0%, rgba(243, 232, 255, 0.65) 100%) !important; /* purple tint */
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(124, 58, 237, 0.12);
    }
    .fc .fc-daygrid-day.has-hearing:not(.fc-day-today) .fc-daygrid-day-number {
      color: #7c3aed; /* purple day number on hearing days */
    }

    /* Scope event card styling to grid/time views only (not list views) */
    .fc .fc-daygrid-event,
    .fc .fc-timegrid-event {
      border: none !important;
      padding: 10px 18px 10px 16px;
      font-size: 1.08rem !important;
      font-weight: 700;
      margin-top: 6px;
      border-radius: 14px !important;
      background: rgba(255, 255, 255, 0.95) !important;
      color: #1e293b !important;
      box-shadow: 0 4px 16px 0 rgba(2, 129, 212, 0.13);
      display: flex;
      align-items: center;
      gap: 0.7em;
      position: relative;
      transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
      backdrop-filter: blur(2px);
      overflow: hidden;
      animation: eventFadeIn 0.5s;
      line-height: 1.3;
      white-space: nowrap;
      text-overflow: ellipsis;
      border: 1px solid rgba(2, 129, 212, 0.2) !important;
    }

    .fc .fc-daygrid-event:hover,
    .fc .fc-daygrid-event:focus,
    .fc .fc-timegrid-event:hover,
    .fc .fc-timegrid-event:focus {
      transform: scale(1.06) translateY(-3px);
      box-shadow: 0 10px 28px 0 rgba(2, 129, 212, 0.22);
      z-index: 2;
      background: rgba(255, 255, 255, 1) !important;
      color: #0f172a !important;
      border-color: rgba(2, 129, 212, 0.4) !important;
    }

    /* Icon-only hearing event styling (Month/Week views) */
    .fc .evt-hearing { 
      padding: 4px 6px !important; 
      min-height: 0 !important; 
      display: inline-flex; 
      align-items: center; 
      justify-content: center; 
    }
    .fc .evt-hearing .hearing-icon { 
      display: inline-flex; 
      align-items: center; 
      justify-content: center; 
      width: 22px; 
      height: 22px; 
      border-radius: 9999px; 
      background: #eef2ff; 
      color: #7c3aed; 
      border: 1px solid #c7d2fe; 
      box-shadow: inset 0 0 0 1px rgba(124,58,237,.08);
      font-size: 12px;
    }
    /* Ensure default title/time are hidden if present */
    .fc .evt-hearing .fc-event-time, 
    .fc .evt-hearing .fc-event-title { display: none !important; }

    @keyframes eventFadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Modal glassmorphism style */
    #eventModal .relative {
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.13);
      border-radius: 22px;
      border: 1.5px solid #e0e7ef;
      backdrop-filter: blur(8px);
      position: relative;
      overflow: hidden;
    }

    #eventModal .relative::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #0281d4, #0ea5e9, #0281d4);
      background-size: 200% 100%;
      animation: gradientShift 3s ease-in-out infinite;
    }

    @keyframes gradientShift {

      0%,
      100% {
        background-position: 0% 50%;
      }

      50% {
        background-position: 100% 50%;
      }
    }

    #modalTitle {
      color: #0281d4;
      font-weight: 700;
      font-size: 1.3rem;
      margin-top: 0.5rem;
    }

    #modalContent {
      max-height: 70vh;
      overflow-y: auto;
      padding-right: 0.5rem;
    }

    #modalContent::-webkit-scrollbar {
      width: 6px;
    }

    #modalContent::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 3px;
    }

    #modalContent::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 3px;
    }

    #modalContent::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }

    /* Responsive tweaks */
    @media (max-width: 640px) {
      #calendar {
        padding: 0.5rem 0.1rem 0.5rem 0.1rem;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
      }

      .fc .fc-toolbar-title {
        font-size: 1.05rem;
        text-align: center;
        word-break: break-word;
      }

      .fc .fc-daygrid-day-number {
        font-size: 0.92rem;
        padding: 6px;
      }

      .fc .fc-event {
        font-size: 0.95rem !important;
        padding: 8px 4px 8px 8px;
        flex-direction: row;
        align-items: center;
        min-width: 0;
        max-width: 100%;
        white-space: nowrap;
        text-overflow: ellipsis;
        background: rgba(255, 255, 255, 0.98) !important;
      }

      .fc .fc-event-title {
        color: #1e293b !important;
        font-size: 0.95rem;
        font-weight: 700 !important;
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.9) !important;
      }

      .fc .fc-event-time {
        color: #475569 !important;
        font-weight: 600 !important;
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.9) !important;
      }
    }

    /* Ensure event time and title are black in week and list views */
    .fc-timeGridWeek-view .fc-event-time,
    .fc-timeGridWeek-view .fc-event-title,
    .fc-listWeek-view .fc-event-time,
    .fc-listWeek-view .fc-event-title {
      color: #111 !important;
    }

    .fc-list .fc-event-time,
    .fc-list .fc-event-title {
      color: #111 !important;
    }
    /* Fix list view: ensure titles wrap and don't overlap/pile */
    .fc .fc-list .fc-list-event-title a,
    .fc .fc-list .fc-list-event-title span {
      white-space: normal !important;
      overflow: visible !important;
      text-overflow: unset !important;
    }
    .fc .fc-list .fc-list-event-title a { display: block; }

    /* Enhanced event text readability */
    .fc-event-title {
      color: #1e293b !important;
      font-weight: 700 !important;
      text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8) !important;
    }

    .fc-event-time {
      color: #475569 !important;
      font-weight: 600 !important;
      text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8) !important;
    }

    /* Ensure event text is readable in all views */
    .fc-daygrid-event .fc-event-title,
    .fc-timegrid-event .fc-event-title,
    .fc-list-event .fc-event-title {
      color: #1e293b !important;
      font-weight: 700 !important;
      text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8) !important;
    }

    /* ===== Enhanced List View Styling ===== */
    /* Remove default borders and give list a clean canvas */
    .fc-theme-standard .fc-list,
    .fc-theme-standard .fc-list-table,
    .fc-theme-standard td,
    .fc-theme-standard th { border: none; }

    /* Day header: pill card with subtle gradient and shadow */
    .fc .fc-list-day-cushion {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: linear-gradient(90deg, rgba(199, 229, 248, 0.5) 0%, #f0f9ff 100%);
      color: #0f4c75;
      font-weight: 800;
      letter-spacing: .3px;
      padding: .75rem 1rem;
      margin: .75rem 1rem .25rem 1rem;
      border: 1px solid #e0e7ef;
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(2, 129, 212, 0.12);
    }

    /* Event rows: airy spacing and card-like hover */
    .fc .fc-list-event td {
      padding: .70rem 1rem;
    }
    .fc .fc-list-event:hover td {
      background: #ffffff;
      box-shadow: 0 8px 20px rgba(2, 129, 212, 0.14);
      transition: box-shadow .2s ease, background .2s ease;
    }

    /* Title link styling for better prominence */
    .fc .fc-list-event-title a {
      color: #0f172a !important;
      font-weight: 800 !important;
    }

    /* Time badge: compact pill */
    .fc .fc-list-event-time {
      background: #e6f4ff;
      color: #0369a1;
      border: 1px solid #b6e1f7;
      border-radius: 9999px;
      padding: .20rem .6rem;
      font-weight: 700;
      font-size: .80rem;
    }

    /* Graphic dot: consistent brand color */
    .fc .fc-list-event-graphic .fc-list-event-dot {
      border-color: #7c3aed; /* purple dot for hearings */
      border-width: 6px;
    }

    /* Subtle zebra effect for readability */
    .fc .fc-list-table tbody tr:nth-child(even) td {
      background: rgba(248, 250, 252, 0.60);
    }

    /* Empty state for list view (copied from External) */
    .fc .fc-list-empty td { border: none; padding: 0; }
    .fc .fc-list-empty .fc-list-empty-cushion {
      margin: 12px;
      padding: 20px 18px;
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
      color: #64748b;
      text-align: center;
      font-weight: 700;
      letter-spacing: .2px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      box-shadow: 0 6px 18px rgba(2,129,212,0.08);
    }
    .fc .fc-list-empty .fc-list-empty-cushion::before {
      content: '\1F5D3'; /* calendar emoji 🗓 (fallback) */
      font-size: 1.2rem;
      color: #0281d4;
      opacity: .7;
      display: inline-block;
      transform: translateY(1px);
    }

    /* Highlight current day in week list view */
    .fc-listWeek-view .fc-list-day.fc-day-today {
      background: linear-gradient(90deg, rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
    }

    /* In Week view, subtly highlight the header of days that have hearings (distinct from today) */
    .fc-timeGridWeek-view .fc-col-header-cell.has-hearing-header:not(.fc-day-today) {
      background: linear-gradient(90deg, rgba(216, 180, 254, 0.35) 0%, rgba(243, 232, 255, 0.65) 100%) !important;
      border-radius: 10px;
      box-shadow: 0 1px 4px rgba(124, 58, 237, 0.12);
    }

    /* Highlight current day column in timeGrid views */
    .fc-timeGridWeek-view .fc-col-today,
    .fc-timeGridDay-view .fc-col-today,
    .fc-timeGridWeek-view .fc-timegrid-col.fc-day-today,
    .fc-timeGridDay-view .fc-timegrid-col.fc-day-today,
    .fc-timeGridWeek-view .fc-timegrid-slot-label.fc-day-today,
    .fc-timeGridDay-view .fc-timegrid-slot-label.fc-day-today {
      background: linear-gradient(90deg, rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
    }

    /* Remove grid and cell lines in Week view for a clean look */
    .fc-timeGridWeek-view .fc-scrollgrid,
    .fc-timeGridWeek-view .fc-scrollgrid thead tr,
    .fc-timeGridWeek-view .fc-scrollgrid tbody tr,
    .fc-timeGridWeek-view td,
    .fc-timeGridWeek-view th { border: 0 !important; }
    .fc-timeGridWeek-view .fc-col-header,
    .fc-timeGridWeek-view .fc-col-header-cell { border: 0 !important; }
    .fc-timeGridWeek-view .fc-timegrid-slot,
    .fc-timeGridWeek-view .fc-timegrid-axis,
    .fc-timeGridWeek-view .fc-timegrid-divider,
    .fc-timeGridWeek-view .fc-timegrid-slot-label { border: 0 !important; }

    /* Custom filter button styling */
    .fc-button.custom-filter {
      background: linear-gradient(90deg, #e0f2fe 0%, #f0f9ff 100%) !important;
      color: #0281d4 !important;
      border: 1.5px solid #bae6fd !important;
      margin: 0 0.5rem !important;
      padding: 0.75rem 1.5rem !important;
      border-radius: 12px !important;
      font-weight: 700 !important;
      font-size: 1rem !important;
      transition: all 0.3s ease !important;
      box-shadow: 0 2px 8px rgba(2, 129, 212, 0.15) !important;
      min-width: 80px !important;
      text-align: center !important;
    }

    .fc-button.custom-filter:hover {
      background: linear-gradient(90deg, #bae6fd 0%, #e0f2fe 100%) !important;
      color: #0369a1 !important;
      transform: translateY(-2px) scale(1.02) !important;
      box-shadow: 0 6px 20px rgba(2, 129, 212, 0.25) !important;
    }

    .fc-button.custom-filter.active-filter {
      background: linear-gradient(90deg, #bae6fd 0%, #e0f2fe 100%) !important;
      color: #0369a1 !important;
      transform: scale(1.05) !important;
      box-shadow: 0 4px 15px rgba(2, 129, 212, 0.3) !important;
      border-color: #0281d4 !important;
    }

    /* Filter button container styling */
    .fc-toolbar-chunk.fc-right {
      display: flex !important;
      gap: 1rem !important;
      align-items: center !important;
      flex-wrap: wrap !important;
      justify-content: center !important;
      margin-top: 0.5rem !important;
    }

    /* Responsive filter button layout */
    @media (max-width: 768px) {
      .fc-toolbar-chunk.fc-right {
        gap: 0.75rem !important;
        margin-top: 1rem !important;
        width: 100% !important;
        justify-content: center !important;
      }

      .fc-button.custom-filter {
        margin: 0 0.25rem !important;
        padding: 0.6rem 1.2rem !important;
        font-size: 0.9rem !important;
        min-width: 70px !important;
      }
    }

    /* Calendar container styling */
    #calendarContainer {
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
      border-radius: 22px;
      border: 1.5px solid #e0e7ef;
      backdrop-filter: blur(8px);
      transition: all 0.3s ease;
    }

    #calendarContainer:hover {
      box-shadow: 0 16px 48px 0 rgba(31, 38, 135, 0.18);
      transform: translateY(-2px);
    }

    /* Status indicator styling */
    .status-indicator {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid #e0e7ef;
      border-radius: 12px;
      backdrop-filter: blur(4px);
      transition: all 0.3s ease;
    }

    .status-indicator:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    /* Hearing selector removed */

    /* Phase indicator styling */
    .phase-indicator {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 0.75rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s ease;
    }

    .phase-mediation {
      background-color: rgba(250, 204, 21, 0.15);
      color: #ca8a04;
      border: 1px solid rgba(250, 204, 21, 0.3);
    }

    .phase-resolution {
      background-color: rgba(34, 197, 94, 0.15);
      color: #16a34a;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .phase-settlement {
      background-color: rgba(168, 85, 247, 0.15);
      color: #9333ea;
      border: 1px solid rgba(168, 85, 247, 0.3);
    }

    /* Modal close button styling */
    #modalClose {
      background: rgba(255, 255, 255, 1);
      border: 2px solid #e5e7eb;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      backdrop-filter: blur(8px);
      cursor: pointer;
      z-index: 70;
      position: absolute;
      top: 16px;
      right: 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      font-size: 1.125rem;
      font-weight: bold;
      color: #6b7280;
      user-select: none;
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
    }

    #modalClose:hover {
      background: rgba(239, 68, 68, 0.1);
      border-color: #ef4444;
      color: #dc2626;
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
    }

    /* Phase indicators styling */
    .flex.flex-wrap.gap-3>div {
      transition: all 0.3s ease;
      cursor: pointer;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      animation: fadeIn 0.6s ease-out forwards;
      opacity: 0;
    }

    .flex.flex-wrap.gap-3>div:nth-child(1) {
      animation-delay: 0.1s;
    }

    .flex.flex-wrap.gap-3>div:nth-child(2) {
      animation-delay: 0.2s;
    }

    .flex.flex-wrap.gap-3>div:nth-child(3) {
      animation-delay: 0.3s;
    }

    .flex.flex-wrap.gap-3>div:nth-child(4) {
      animation-delay: 0.4s;
    }

    .flex.flex-wrap.gap-3>div:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(8px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* --- Toolbar Layout: single row 3-column (left nav, center title, right view buttons) --- */
    .fc .fc-toolbar.fc-header-toolbar { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; column-gap:.75rem; width:100%; }
    .fc .fc-toolbar { flex-wrap:nowrap; }
    /* Chunk order: FullCalendar outputs chunks in order left, center, right already */
    .fc .fc-toolbar-chunk:nth-child(1){ justify-self:start; display:flex; align-items:center; }
    .fc .fc-toolbar-chunk:nth-child(2){ justify-self:center; text-align:center; }
    .fc .fc-toolbar-chunk:nth-child(3){ justify-self:end; display:flex; justify-content:flex-end; align-items:center; }
    .fc .fc-prev-button,.fc .fc-next-button{ position:relative; }
    .fc .fc-toolbar-chunk:nth-child(3) .fc-button-group{ flex-wrap:nowrap; }
    /* Narrow mobile fallback: stack title on own row */
    @media (max-width:480px){
      .fc .fc-toolbar.fc-header-toolbar { grid-template-columns:1fr 1fr; grid-auto-rows:auto; row-gap:.4rem; }
      .fc .fc-toolbar-chunk:nth-child(1){ order:1; }
      .fc .fc-toolbar-chunk:nth-child(2){ order:2; grid-column:1 / span 2; }
      .fc .fc-toolbar-chunk:nth-child(3){ order:3; }
    }

    /* Three-dot menu for mobile view buttons */
    #viewMenuBtn {
      display: none;
      position: relative;
      background: linear-gradient(120deg, #e8f7ff 0%, #f3fbff 100%) !important;
      color: #0276c4 !important;
      border: 1px solid #b6e1f7 !important;
      border-radius: 7px !important;
      padding: .30rem .65rem;
      font-weight: 600;
      font-size: .78rem;
      cursor: pointer;
      transition: all .18s;
      min-height: 30px;
      width: 32px;
      justify-content: center;
      align-items: center;
      margin-left: 0.5rem;
    }
    #viewMenuBtn:hover {
      background: linear-gradient(120deg, #d5eefc 0%, #e8f7ff 100%) !important;
      color: #0369a1 !important;
    }
    #viewMenuDropdown {
      display: none;
      position: absolute;
      top: calc(100% + 0.25rem);
      right: 0;
      background: rgba(255, 255, 255, 0.98);
      border: 1px solid #e0e7ef;
      border-radius: 10px;
      box-shadow: 0 8px 24px rgba(2, 129, 212, 0.18);
      backdrop-filter: blur(8px);
      z-index: 1000;
      min-width: 120px;
      overflow: hidden;
    }
    #viewMenuDropdown.show {
      display: block;
      animation: dropdownFadeIn 0.2s ease-out;
    }
    @keyframes dropdownFadeIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    #viewMenuDropdown button {
      display: block;
      width: 100%;
      text-align: left;
      padding: 0.6rem 1rem;
      background: transparent !important;
      color: #0276c4 !important;
      border: none !important;
      font-weight: 600;
      font-size: 0.85rem;
      transition: all 0.15s;
      cursor: pointer;
      margin: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
    }
    #viewMenuDropdown button:hover {
      background: linear-gradient(120deg, #e8f7ff 0%, #f3fbff 100%) !important;
      color: #0369a1 !important;
    }
    #viewMenuDropdown button.active-view {
      background: #e4f6ff !important;
      color: #0b6fa2 !important;
      font-weight: 700;
    }

    @media (max-width: 768px) {
      #viewMenuBtn { display: inline-flex; }
      .fc .fc-toolbar-chunk:nth-child(3) .fc-button-group { display: none !important; }
      /* Position the menu button in the left chunk with prev/next buttons */
      .fc .fc-toolbar-chunk:nth-child(1) { gap: 0.5rem; }
    }

    /* Day header cells */
    .fc .fc-col-header-cell-cushion {
      font-weight: 600;
      font-size: .85rem;
      padding: .55rem .25rem;
      letter-spacing: .5px;
      text-transform: uppercase;
      color: #0f4c75;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
    }

    @media (min-width:860px) {
      .fc .fc-col-header-cell-cushion {
        font-size: .8rem;
      }
    }

    @media (min-width:1024px) {
      .fc .fc-col-header-cell-cushion {
        font-size: .78rem;
        padding: .6rem .25rem;
      }
    }

    @media (max-width:768px) {
      .fc .fc-col-header-cell-cushion {
        font-size: .75rem;
        padding: .45rem 0;
      }
    }

    @media (max-width:560px) {
      .fc .fc-col-header-cell-cushion {
        font-size: .70rem;
        letter-spacing: .3px;
      }
    }

    @media (max-width:420px) {
      .fc .fc-col-header-cell-cushion {
        font-size: .68rem;
      }
    }

    @media (max-width: 900px) {
      .fc .fc-toolbar-title {
        font-size: 1.35rem;
      }

      .fc .fc-button {
        font-size: 1rem;
        padding: .45rem .9rem;
      }
    }

    @media (max-width: 768px) {
      .fc .fc-toolbar {
        flex-direction: column;
        align-items: stretch;
      }

      .fc .fc-toolbar-title {
        font-size: 1.2rem;
        text-align: center;
        width: 100%;
      }

      .fc .fc-button-group,
      .fc .fc-toolbar-chunk {
        justify-content: center;
      }

      .fc .fc-prev-button,
      .fc .fc-next-button {
        padding: .4rem .65rem;
        font-size: .9rem;
      }
    }

    @media (max-width: 560px) {
      .fc .fc-toolbar-title {
        font-size: 1.05rem;
      }

      .fc .fc-prev-button,
      .fc .fc-next-button {
        padding: .35rem .55rem;
        border-radius: 8px !important;
      }

      .fc .fc-button {
        font-size: .85rem;
      }
    }

    @media (max-width: 420px) {
      .fc .fc-toolbar-title {
        font-size: .95rem;
        font-weight: 700;
      }

      .fc .fc-button {
        padding: .35rem .5rem;
      }
    }

    /* Provide subtle icon-only feel on very small screens */
    @media (max-width: 380px) {
      .fc .fc-button:not(.fc-prev-button):not(.fc-next-button):not(.fc-today-button) {
        letter-spacing: -.5px;
      }
    }
  </style>
</head>

<body>

  
    <div class="max-w-7xl mb-12 p-3 sm:p-3 lg:p-0">
      <div id="calendar" class="relative z-50"></div>
    </div>

    <script>
      // Compact mode flag (hide hearing selector/status when embedded on resident home)
      const isCompact = <?php echo (isset($_GET['compact']) && $_GET['compact'] == '1') ? 'true' : 'false'; ?>;

  document.addEventListener('DOMContentLoaded', function () {
        const rawEvents = <?= $events_json ?>;
        const casesData = <?= $cases_json ?>;
  const hearingsData = <?= $hearings_json ?>;

        // Build a set of dates (YYYY-MM-DD) that have at least one hearing
        const hearingDateSet = new Set();
        (rawEvents || []).forEach(ev => {
          if (ev && ev.type === 'hearing' && ev.start) {
            const d = new Date(ev.start);
            // Normalize to local date string in YYYY-MM-DD
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            hearingDateSet.add(`${y}-${m}-${day}`);
          }
        });

        const fcEvents = rawEvents
          .filter(ev => ev.type === 'hearing')
          .map(ev => {
          const base = {
            id: ev.id,
            title: ev.title,
            start: ev.start,
            end: ev.end || null,
            color: ev.color || undefined,
            extendedProps: {}
          };
          base.extendedProps = {
            type: 'hearing',
            description: ev.description || '',
            sdate: ev.sdate || '',
            case_id: ev.case_id || null,
            hearing_id: ev.id ? ev.id.replace('hearing_','') : null
          };
          if (ev.end) base.end = ev.end;
          base.display = 'block';
          return base;
        });

        // calendar header
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
          noEventsText: 'No hearings scheduled for this period',
          eventClassNames: function(arg){
            // Add a class for hearing events for targeted styling
            if (arg.event.extendedProps && arg.event.extendedProps.type === 'hearing') {
              // Only tag in non-list views to keep list view full titles
              if (!arg.view.type.startsWith('list')) return ['evt-hearing'];
            }
            return [];
          },
          eventContent: function(arg){
            // Replace content with icon-only for hearing events in Month/Week views
            const isList = arg.view.type.startsWith('list');
            if (arg.event.extendedProps && arg.event.extendedProps.type === 'hearing' && !isList) {
              const title = escapeHtml(arg.event.title || 'Hearing');
              return { html: `<span class="hearing-icon" title="${title}" aria-label="${title}"><i class="fas fa-gavel"></i></span>` };
            }
            // default rendering for other types or list view
            return undefined;
          },
          eventDidMount: function (info) {
            // In list views, ensure order: [dot/graphic] → [title/type] → [time]
            try {
              if (info.view && info.view.type && info.view.type.indexOf('list') === 0) {
                var tr = info.el.closest('tr');
                if (tr) {
                  var tdGraphic = tr.querySelector('td.fc-list-event-graphic');
                  var tdTime = tr.querySelector('td.fc-list-event-time');
                  var tdTitle = tr.querySelector('td.fc-list-event-title');
                  if (tdGraphic && tdTitle && tdTime) {
                    if (!(tr.children[0] === tdGraphic && tr.children[1] === tdTitle && tr.children[2] === tdTime)) {
                      tr.appendChild(tdGraphic);
                      tr.appendChild(tdTitle);
                      tr.appendChild(tdTime);
                    }
                  }
                }
              }
            } catch (e) { /* noop */ }
            if (info.event.extendedProps.type === 'hearing') {
              // Hide the time for hearing events in the calendar display
              const timeEl = info.el.querySelector('.fc-event-time');
              if (timeEl) timeEl.style.display = 'none';
            }

            // Add animation to each event as it mounts
            info.el.style.opacity = '0';
            setTimeout(() => {
              info.el.style.opacity = '1';
              info.el.style.transform = 'translateY(0)';
            }, Math.random() * 300);
          },
          initialView: 'dayGridMonth',
          dayCellClassNames: function(arg){
            // arg.date is a Date; mark Month view cells that have hearings
            const d = arg.date;
            const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            if (hearingDateSet.has(key)) return ['has-hearing'];
            return [];
          },
          dayHeaderClassNames: function(arg){
            // For Week view headers, highlight days having hearings
            const d = arg.date;
            if (!d) return [];
            const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            if (hearingDateSet.has(key)) return ['has-hearing-header'];
            return [];
          },
          headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
          },
          views: {
            dayGridMonth: { buttonText: 'Month' },
            timeGridWeek: { buttonText: 'Week' },
            listMonth: { buttonText: 'List' }
          },
          events: fcEvents,
          height: 650,
          eventClick: function (info) {
            const e = info.event;
            const p = e.extendedProps;
            
            let contentHtml = '';
            let titleText = e.title;

            if (p.type === 'case') {
              contentHtml = `
                     <p class="mb-2"><strong class="text-gray-700">Complaint Title:</strong> <span class="text-indigo-600">${escapeHtml(p.case_title)}</span></p>
                     <p class="mb-2"><strong class="text-gray-700">Status:</strong> <span class="px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(p.case_status)}">${escapeHtml(p.case_status)}</span></p>
                     <p class="mb-2"><strong class="text-gray-700">Date Opened:</strong> <span class="text-gray-800">${escapeHtml(p.date_opened)}</span></p>
                     
                     <div class="mb-3 p-3 rounded-lg border border-gray-200 bg-gray-50">
                         <div class="flex items-center gap-2 mb-2">
                             ${getCurrentPhaseIcon(p.current_phase)}
                             <div>
                                 <strong class="text-gray-700">Current Phase:</strong> 
                                 <span class="px-2 py-1 rounded-full text-xs font-medium capitalize" style="background-color: ${getPhaseColor(p.current_phase)}20; color: ${getPhaseColor(p.current_phase)};">${escapeHtml(p.current_phase || 'Mediation')}</span>
                             </div>
                         </div>
                         <p class="text-sm text-gray-600">Phase Days Left: <strong>${p.phase_days_left || '15'}</strong> days</p>
                     </div>
                     
                     <div class="flex justify-between items-center mt-3 border-t pt-3 border-gray-100">
                         <div>
                             <strong class="text-gray-700">Days Passed:</strong> 
                             <span class="text-gray-800">${p.days_passed}</span>
                         </div>
                         <div>
                             <strong class="text-gray-700">Days Left:</strong> 
                             <span class="text-gray-800">${p.days_left}</span>
                         </div>
                     </div>
                     <div class="w-full bg-gray-200 rounded-full h-2 mt-2 overflow-hidden">
                         <div class="bg-indigo-500 h-2 rounded-full" style="width: ${Math.min(100, (p.days_passed / 45) * 100)}%"></div>
                     </div>
                 `;
            } else {
              contentHtml = `
                    <p class="mb-2"><strong class="text-gray-700">Hearing Title:</strong> <span class="text-indigo-600">${escapeHtml(e.title)}</span></p>
                    <p class="mb-2"><strong class="text-gray-700">Start:</strong> <span class="text-gray-800">${moment(e.start).format('MMMM D, YYYY h:mm A')}</span></p>
                    <div class="flex items-center mt-4">
                        <div class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></div>
                        <span class="text-xs text-gray-500">Tap anywhere outside to close</span>
                    </div>
                `;
            }

            // Send message to parent window to show modal
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({
                type: 'showEventModal',
                title: titleText,
                content: contentHtml
              }, '*');
            }
          }
        });

        // No custom top filter buttons; show all events by default
        function applyCompositeFilter() {
          if (!calendar) return;
          calendar.removeAllEventSources();
          calendar.addEventSource(fcEvents);
        }

        // Add active class for filter buttons
        setTimeout(() => {
          // Initial filter: show all events
          applyCompositeFilter();
        }, 100);

        calendar.render();

        // Three-dot menu for mobile view switching
        // Create and insert the menu button with prev/next buttons
        const viewMenuBtn = document.createElement('div');
        viewMenuBtn.id = 'viewMenuBtn';
        viewMenuBtn.className = 'fc-button';
        viewMenuBtn.innerHTML = `
          <i class="fas fa-ellipsis-v"></i>
          <div id="viewMenuDropdown">
            <button data-view="dayGridMonth">Month</button>
            <button data-view="timeGridWeek">Week</button>
            <button data-view="listMonth">List</button>
          </div>
        `;
        
        // Insert the button into the left toolbar chunk (with prev/next buttons)
        const leftChunk = document.querySelector('.fc-toolbar-chunk:nth-child(1)');
        if (leftChunk) {
          leftChunk.appendChild(viewMenuBtn);
        }
        
        const viewMenuDropdown = document.getElementById('viewMenuDropdown');
        
        if (viewMenuBtn && viewMenuDropdown) {
          // Toggle dropdown
          viewMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            viewMenuDropdown.classList.toggle('show');
          });

          // Close dropdown when clicking outside
          document.addEventListener('click', function(e) {
            if (!viewMenuBtn.contains(e.target)) {
              viewMenuDropdown.classList.remove('show');
            }
          });

          // Handle view button clicks
          viewMenuDropdown.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              const view = this.getAttribute('data-view');
              if (view && calendar) {
                calendar.changeView(view);
                // Update active state
                viewMenuDropdown.querySelectorAll('button').forEach(b => b.classList.remove('active-view'));
                this.classList.add('active-view');
                // Close dropdown
                viewMenuDropdown.classList.remove('show');
              }
            });
          });

          // Set initial active view
          const initialView = calendar.view.type;
          const activeBtn = viewMenuDropdown.querySelector(`button[data-view="${initialView}"]`);
          if (activeBtn) activeBtn.classList.add('active-view');

          // Update active view when calendar view changes
          calendar.on('datesSet', function() {
            const currentView = calendar.view.type;
            viewMenuDropdown.querySelectorAll('button').forEach(btn => {
              if (btn.getAttribute('data-view') === currentView) {
                btn.classList.add('active-view');
              } else {
                btn.classList.remove('active-view');
              }
            });
          });
        }

        // Dynamic responsive adjustments for toolbar title and nav buttons (beyond CSS)
        function adjustCalendarToolbar() {
          const w = window.innerWidth;
          const titleEl = document.querySelector('.fc-toolbar-title');
          if (titleEl) {
            // Always derive the fresh full title from FullCalendar's current view
            const full = (calendar && calendar.view && calendar.view.title)
              ? calendar.view.title
              : titleEl.textContent.trim();
            titleEl.dataset.full = full;
            if (w < 420) {
              // aggressively truncate long titles
              titleEl.textContent = full.length > 20 ? full.slice(0, 17) + '…' : full;
            } else if (w < 560) {
              titleEl.textContent = full.length > 28 ? full.slice(0, 25) + '…' : full;
            } else {
              titleEl.textContent = full; // restore
            }
          }
          const navBtns = document.querySelectorAll('.fc-prev-button, .fc-next-button');
          navBtns.forEach(btn => {
            if (w < 420) {
              btn.style.padding = '.20rem .45rem';
              btn.style.fontSize = '.65rem';
            } else if (w < 560) {
               btn.style.padding = '.24rem .5rem';
              btn.style.fontSize = '.7rem';
            } else if (w < 768) {
              btn.style.padding = '.26rem .55rem';
              btn.style.fontSize = '.72rem';
            } else {
              btn.style.padding = '.28rem .6rem';
              btn.style.fontSize = '.75rem';
            }
          });
        }

        // Run after initial render and on significant events
        adjustCalendarToolbar();
        window.addEventListener('resize', () => {
          if (window.__fcTbRaf) cancelAnimationFrame(window.__fcTbRaf);
          window.__fcTbRaf = requestAnimationFrame(adjustCalendarToolbar);
        });
        calendar.on('datesSet', adjustCalendarToolbar);

        // Abbreviate / restore day headers responsively
        function adjustDayHeaders() {
          const w = window.innerWidth;
          document.querySelectorAll('.fc-col-header-cell-cushion').forEach(el => {
            const full = el.dataset.full || el.textContent.trim();
            if (!el.dataset.full) el.dataset.full = full; // store once
            if (w < 400) {
              // single letter
              el.textContent = full.substring(0, 1);
            } else if (w < 640) {
              // 3-letter abbreviation
              el.textContent = full.substring(0, 3);
            } else {
              el.textContent = full; // restore
            }
          });
        }
        adjustDayHeaders();
        window.addEventListener('resize', () => {
          if (window.__fcDhRaf) cancelAnimationFrame(window.__fcDhRaf);
          window.__fcDhRaf = requestAnimationFrame(adjustDayHeaders);
        });
        calendar.on('datesSet', adjustDayHeaders);
        // No additional toolbar extras (hearing selector/status) for resident calendar

        function getStatusClass(status) {
          if (!status) return 'bg-gray-100 text-gray-800';
          const s = status.toString().toLowerCase();
          if (s === 'resolved') return 'bg-green-100 text-green-800';
          if (s === 'closed') return 'bg-red-100 text-red-800';
          if (s === 'pending') return 'bg-yellow-100 text-yellow-800';
          if (s === 'mediation') return 'bg-yellow-100 text-orange-800';
          if (s === 'open') return 'bg-blue-100 text-blue-800';
          return 'bg-gray-100 text-gray-800';
        }

        function getCurrentPhaseIcon(phase) {
          if (!phase) return '<i class="fas fa-handshake text-amber-500 text-xl"></i>';

          const p = phase.toString().toLowerCase();
          if (p.includes('mediation'))
            return '<i class="fas fa-handshake text-amber-500 text-xl"></i>';
          if (p.includes('conciliation'))
            return '<i class="fas fa-balance-scale text-emerald-600 text-xl"></i>';
          if (p.includes('arbitration'))
            return '<i class="fas fa-file-signature text-blue-600 text-xl"></i>';
          if (p.includes('hearing'))
            return '<i class="fas fa-gavel text-purple-600 text-xl"></i>';

          return '<i class="fas fa-handshake text-amber-500 text-xl"></i>';
        }

        function getPhaseColor(phase) {
          if (!phase) return '#f59e0b'; // amber default for mediation

          const p = phase.toString().toLowerCase();
          if (p.includes('mediation')) return '#f59e0b'; // amber
          if (p.includes('conciliation')) return '#10b981'; // emerald
          if (p.includes('arbitration')) return '#3b82f6'; // blue
          if (p.includes('hearing')) return '#8b5cf6'; // purple

          return '#f59e0b'; // default amber
        }

        function statusBgColor(status) {
          if (!status) return 'bg-gray-100 text-gray-800';
          const s = status.toString().toLowerCase();
          if (s === 'resolved') return 'bg-green-100 text-green-800';
          if (s === 'closed') return 'bg-red-100 text-red-800';
          if (s === 'pending') return 'bg-yellow-100 text-yellow-800';
          if (s === 'mediation') return 'bg-yellow-100 text-orange-800';
          if (s === 'open') return 'bg-blue-100 text-blue-800';
          return 'bg-gray-100 text-gray-800';
        }

        // Initialize filter for both compact and regular layouts (no hearing selector)
        applyCompositeFilter();

        function escapeHtml(unsafe) {
          if (unsafe === null || unsafe === undefined) return '';
          return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
        }
      });
    </script>
  </body>

</html>