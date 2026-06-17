<?php
require_once('db-connect.php');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scheduling</title>
  <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="./fullcalendar/lib/main.min.css" rel="stylesheet" />
  <link href="./SecMenu/schedule/fullcalendar/lib/main.css" rel="stylesheet" />
  <script src="./js/jquery-3.6.0.min.js"></script>
  <script src="./fullcalendar/lib/main.min.js"></script>
  <script src="./fullcalendar/lib/main.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <style>
    html, body { height:100%; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin:0;
      padding:0;
      display:flex;
      flex-direction:column;
   
    }
    /* Modern glassmorphism calendar container */
    #calendar-wrapper { 
      flex:1 1 auto; 
      display:flex; 
      padding:0.8rem 0.8rem 0.5rem 0.5rem;
      position: relative;
      overflow: visible;
    }
    #calendar {
      width:100%;
      height:100%;
      background:rgba(255,255,255,0.7);
      box-shadow:0 6px 28px -10px rgba(12,110,175,0.25),0 2px 10px -4px rgba(12,110,175,0.18);
      border-radius:20px;
      border:1px solid rgba(180,205,225,0.55);
      padding:1.25rem 1rem .75rem 1rem;
      transition:box-shadow .3s, transform .3s;
      backdrop-filter:blur(10px);
      position: relative;
      overflow: visible;
    }
  #calendar:hover {
      
      transform: translateY(-4px) scale(1.01);
    }
    .fc .fc-toolbar-title {
      font-weight: 700;
      font-size: 1.15rem; /* reduced from 1.5rem */
      color: #0281d4;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 0.4em;
    }
    .fc .fc-button {
      box-shadow: none !important;
      padding: 0.32rem 0.60rem; /* smaller padding */
      border-radius: 7px !important; /* slightly tighter */
      font-weight: 600;
      font-size: 0.78rem; /* reduced font size */
      line-height: 1;
      transition: all 0.18s;
      text-transform: capitalize;
      border: 1px solid #b6e1f7 !important;
      background: linear-gradient(120deg, #e8f7ff 0%, #f3fbff 100%) !important;
      color: #0276c4 !important;
      margin: 0 0.15rem;
      display: flex;
      align-items: center;
      gap: 0.25em;
      min-height: 30px;
    }
    .fc .fc-button .fc-icon { font-size: 0.9rem; }
    /* Compact arrow buttons */
    .fc .fc-prev-button, .fc .fc-next-button { padding: 0.25rem 0.5rem !important; width: 32px; }
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
      background: linear-gradient(90deg,rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
      border-radius: 12px;
      box-shadow: 0 2px 8px 0 rgba(2, 129, 212, 0.08);
    }
    /* Highlight days that have hearings (distinct from current day; no border) */
    .fc .fc-daygrid-day.has-hearing:not(.fc-day-today) {
      background: linear-gradient(90deg, rgba(216, 180, 254, 0.35) 0%, rgba(243, 232, 255, 0.65) 100%) !important;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(124, 58, 237, 0.12);
    }
    .fc .fc-daygrid-day.has-hearing:not(.fc-day-today) .fc-daygrid-day-number { color: #7c3aed; }
    /* Scope event card styling to grid/time views only (not list views) */
    .fc .fc-daygrid-event,
    .fc .fc-timegrid-event {
      border: none !important;
      padding: 10px 18px 10px 16px;
      font-size: 1.08rem !important;
      font-weight: 700;
      margin-top: 6px;
      border-radius: 14px !important;
      background: rgba(214, 214, 214, 0.28) !important;
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
    }
    .fc .fc-daygrid-event:hover, .fc .fc-daygrid-event:focus,
    .fc .fc-timegrid-event:hover, .fc .fc-timegrid-event:focus {
      transform: scale(1.06) translateY(-3px);
      box-shadow: 0 10px 28px 0 rgba(2, 129, 212, 0.22);
      z-index: 2;
      background: rgba(255,255,255,0.38) !important;
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
      cursor: pointer;
    }
    .fc .evt-hearing .fc-event-time, 
    .fc .evt-hearing .fc-event-title { display: none !important; }
    @keyframes eventFadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    /* Modal glassmorphism style (Resident parity) */
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
      position: absolute; top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #0281d4, #0ea5e9, #0281d4);
      background-size: 200% 100%;
      animation: gradientShift 3s ease-in-out infinite;
    }
    @keyframes gradientShift { 0%,100% {background-position:0% 50%} 50% {background-position:100% 50%} }
    #modalTitle { color:#0281d4; font-weight:700; font-size:1.3rem; margin-top:.5rem; }
    #modalContent { max-height:70vh; overflow-y:auto; padding-right:.5rem; }
    #modalContent::-webkit-scrollbar { width:6px; }
    #modalContent::-webkit-scrollbar-track { background:#f1f5f9; border-radius:3px; }
    #modalContent::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }
    #modalContent::-webkit-scrollbar-thumb:hover { background:#94a3b8; }
    /* Responsive tweaks */
    @media (max-width: 640px) {
      #calendar {
        padding: 0.5rem 0.1rem 0.5rem 0.1rem;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
      }
  .fc .fc-toolbar-title { font-size: 0.95rem; }
  .fc .fc-button { font-size: 0.7rem; padding: 0.28rem 0.5rem; }
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
      }
      .fc .fc-event-title {
        color: #1e293b !important;
        font-size: 0.95rem;
        
      }
    }
    /* Ensure event time and title are black in week and list views */
    .fc-timeGridWeek-view .fc-event-time, 
    .fc-timeGridWeek-view .fc-event-title,
    .fc-listWeek-view .fc-event-time, 
    .fc-listWeek-view .fc-event-title {
      color: #111 !important;
    }
    .fc-list .fc-event-time, .fc-list .fc-event-title {
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
    /* ===== Enhanced List View Styling (parity with Resident) ===== */
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
    .fc .fc-list-event td { padding: .70rem 1rem; }
    .fc .fc-list-event:hover td {
      background: #ffffff;
      box-shadow: 0 8px 20px rgba(2, 129, 212, 0.14);
      transition: box-shadow .2s ease, background .2s ease;
    }
  /* Title link styling for better prominence */
  .fc .fc-list-event-title a { color: #0f172a !important; font-weight: 800 !important; }
  /* Center the hearing title in list view */
  .fc .fc-list-event-title { text-align: center; }
  .fc .fc-list-event-title a, .fc .fc-list-event-title span { display: block; width: 100%; }
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
    .fc .fc-list-event-graphic .fc-list-event-dot { border-color: #7c3aed; border-width: 6px; }
    /* Subtle zebra effect for readability */
    .fc .fc-list-table tbody tr:nth-child(even) td { background: rgba(248, 250, 252, 0.60); }
    /* Empty state for list view */
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
      content: '\1F5D3';
      font-size: 1.2rem;
      color: #0281d4;
      opacity: .7;
      display: inline-block;
      transform: translateY(1px);
    }

    /* ===== Remove grid/cell lines in calendar (clean look) ===== */
    .fc-theme-standard .fc-scrollgrid,
    .fc-theme-standard .fc-scrollgrid thead tr,
    .fc-theme-standard .fc-scrollgrid tbody tr,
    .fc-theme-standard td,
    .fc-theme-standard th { border: 0 !important; }
    .fc .fc-scrollgrid { border: 0 !important; }
    .fc .fc-col-header, .fc .fc-col-header-cell { border: 0 !important; }
    .fc .fc-daygrid-day,
    .fc .fc-daygrid-day-frame,
    .fc .fc-daygrid-day-top,
    .fc .fc-daygrid-day-bg { border: 0 !important; }
    .fc .fc-timegrid-slot,
    .fc .fc-timegrid-axis,
    .fc .fc-timegrid-divider,
    .fc .fc-timegrid-slot-label { border: 0 !important; }
    /* Keep today highlight visible despite removed lines */
    .fc .fc-daygrid-day.fc-day-today { box-shadow: 0 2px 8px 0 rgba(2, 129, 212, 0.08); }
    /* Highlight current day in week list view */
    .fc-listWeek-view .fc-list-day.fc-day-today {
      background: linear-gradient(90deg,rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
    }
    /* Highlight current day column in timeGrid views */
    .fc-timeGridWeek-view .fc-col-today,
    .fc-timeGridDay-view .fc-col-today,
    .fc-timeGridWeek-view .fc-timegrid-col.fc-day-today,
    .fc-timeGridDay-view .fc-timegrid-col.fc-day-today,
    .fc-timeGridWeek-view .fc-timegrid-slot-label.fc-day-today,
    .fc-timeGridDay-view .fc-timegrid-slot-label.fc-day-today {
      background: linear-gradient(90deg,rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
    }
    /* In Week view, subtly highlight headers for days with hearings (distinct from today) */
    .fc-timeGridWeek-view .fc-col-header-cell.has-hearing-header:not(.fc-day-today) {
      background: linear-gradient(90deg, rgba(216, 180, 254, 0.35) 0%, rgba(243, 232, 255, 0.65) 100%) !important;
      border-radius: 10px;
      box-shadow: 0 1px 4px rgba(124, 58, 237, 0.12);
    }
    /* Tooltip for event hover (match Lupon behavior) */
    #fc-event-tooltip {
      position: fixed;
      z-index: 9999;
      pointer-events: none;
      background: rgba(255,255,255,0.96);
      border: 1px solid rgba(180,205,225,0.55);
      border-radius: 12px;
      box-shadow: 0 10px 28px rgba(2, 129, 212, 0.18), 0 4px 12px rgba(12, 110, 175, 0.12);
      padding: 10px 12px;
      font-size: 0.85rem;
      color: #0f172a;
      opacity: 0;
      transform: translateY(4px);
      transition: opacity .12s ease, transform .12s ease;
      max-width: 280px;
      backdrop-filter: blur(6px);
    }
    #fc-event-tooltip.show { opacity: 1; transform: translateY(0); }
    #fc-event-tooltip .tip-title { font-weight: 700; color:#0281d4; margin-bottom: 2px; }
    #fc-event-tooltip .tip-row { display:flex; gap:.35rem; align-items:center; }
    #fc-event-tooltip .tip-label { color:#0369a1; font-weight:600; font-size:.78rem; }

    /* Mobile view-switch dropdown (for three-dot button) */
    #fc-mobile-menu {
      position: fixed;
      z-index: 9999;
      display: none;
      min-width: 150px;
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      box-shadow: 0 10px 28px rgba(2, 129, 212, 0.14), 0 4px 12px rgba(12, 110, 175, 0.10);
      padding: 6px;
    }
    #fc-mobile-menu.open { display: block; }
    #fc-mobile-menu button {
      display: block;
      width: 100%;
      text-align: left;
      padding: 0.4rem 0.6rem;
      font-size: 0.85rem;
      border-radius: 8px;
      color: #0f172a;
    }
    #fc-mobile-menu button:hover { background: #f1f5f9; }
  </style>
</head>

<body>
  <div id="fc-event-tooltip" class="hidden"></div>
  <div id="calendar-wrapper">
    <div id="calendar" class="relative z-10"></div>
  </div>

  <!-- Resident-style Event Modal -->
  <div id="eventModal" class="fixed inset-0 hidden flex items-center justify-center z-50">
    <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm"></div>
    <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl w-full max-w-md mx-auto p-6 z-60 transform transition-all duration-300 border border-gray-200">
      <button id="modalClose"
        class="absolute top-4 right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-8 h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform"
        onclick="document.getElementById('eventModal').classList.add('hidden');">&times;</button>
      <h3 id="modalTitle" class="text-xl font-semibold mb-4 text-blue-700 border-b border-gray-200 pb-2"></h3>
      <div id="modalContent" class="text-sm text-gray-700 space-y-3"></div>
      <div class="mt-4 flex items-center justify-end gap-2">
        <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors"
          onclick="document.getElementById('eventModal').classList.add('hidden');">Close</button>
      </div>
    </div>
  </div>

    <?php 
    // Build schedule payloads with party resolution and lupon assignment
    $schedules = $conn->query("SELECT * FROM `schedule_list` WHERE visible = 1");
    $sched_res = [];
    foreach(($schedules ? $schedules->fetch_all(MYSQLI_ASSOC) : []) as $row){
      $row['sdate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime']));
      $row['edate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime'] . ' +1 hour'));

      $caseId = isset($row['Case_ID']) ? (int)$row['Case_ID'] : (isset($row['case_id']) ? (int)$row['case_id'] : 0);
      $complainant = '';
      $respondents = [];
      $complaintId = 0;
      $luponName = '';

      if ($caseId) {
        // Try to fetch complainant/main respondent from linked complaint/case records
        $partySql = "SELECT cs.case_original_id AS case_original_id, ci.Complaint_ID,
                  COALESCE(comp.first_name, ext_comp.first_name) AS comp_first,
                  COALESCE(comp.last_name, ext_comp.last_name) AS comp_last,
                  resp.first_name AS resp_first,
                  resp.last_name AS resp_last
                FROM case_info cs
                LEFT JOIN complaint_info ci ON cs.Complaint_ID = ci.Complaint_ID
                LEFT JOIN resident_info comp ON ci.Resident_ID = comp.Resident_ID
                LEFT JOIN external_complainant ext_comp ON ci.external_complainant_id = ext_comp.external_complaint_id
                LEFT JOIN resident_info resp ON ci.Respondent_ID = resp.Resident_ID
                WHERE cs.Case_ID = ? LIMIT 1";
        if ($ps = $conn->prepare($partySql)) {
          $ps->bind_param('i', $caseId);
          $ps->execute();
          $prs = bpamis_stmt_get_result($ps);
          if ($prow = $prs->fetch_assoc()) {
            $complaintId = isset($prow['Complaint_ID']) ? (int)$prow['Complaint_ID'] : 0;
            $complainant = trim((($prow['comp_first'] ?? '') . ' ' . ($prow['comp_last'] ?? '')));
            if (!empty($prow['resp_first']) || !empty($prow['resp_last'])) {
              $respondents[] = trim((($prow['resp_first'] ?? '') . ' ' . ($prow['resp_last'] ?? '')));
            }
            // Propagate original case id to the schedule payload so JS can use it
            $row['case_original_id'] = isset($prow['case_original_id']) ? $prow['case_original_id'] : null;
          }
          $ps->close();
        }

        // Additional respondents
        if (!empty($complaintId)) {
          $addRespSql = "SELECT ri.first_name, ri.last_name FROM complaint_respondents cr JOIN resident_info ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?";
          if ($ars = $conn->prepare($addRespSql)) {
            $ars->bind_param('i', $complaintId);
            $ars->execute();
            $arsr = bpamis_stmt_get_result($ars);
            while ($ar = $arsr->fetch_assoc()) {
              $respondents[] = trim((($ar['first_name'] ?? '') . ' ' . ($ar['last_name'] ?? '')));
            }
            $ars->close();
          }
        }

        // Determine lupon/mediator assigned for this case (try multiple possible tables/columns)
        $luponSql = "SELECT COALESCE(mi.Mediator_Name, conc.Mediator_Name, res.Mediator_Name, setl.Mediator_Name, arb.Mediator_Name, cs.lupon_assign) AS lupon_name
               FROM case_info cs
               LEFT JOIN mediation_info mi ON cs.Case_ID = mi.Case_ID
               LEFT JOIN conciliation conc ON cs.Case_ID = conc.Case_ID
               LEFT JOIN resolution res ON cs.Case_ID = res.Case_ID
               LEFT JOIN settlement setl ON cs.Case_ID = setl.Case_ID
               LEFT JOIN arbitration arb ON cs.Case_ID = arb.Case_ID
               WHERE cs.Case_ID = ? LIMIT 1";
        if ($ls = $conn->prepare($luponSql)) {
          $ls->bind_param('i', $caseId);
          $ls->execute();
          $lsr = bpamis_stmt_get_result($ls);
          if ($lr = $lsr->fetch_assoc()) {
            $luponName = trim($lr['lupon_name'] ?? '');
          }
          $ls->close();
        }
      }

      // Build title in the requested format: "Complainant Vs. Respondent" (use 'et al' if multiple respondents)
      $cmp = $complainant ?: ($row['hearingComplainant'] ?? ($row['hearingTitle'] ?? 'Complainant'));
      $uniqueResps = array_values(array_unique(array_filter($respondents)));

      // Determine if there is any respondent information available
      $hasRespondent = !empty($uniqueResps) || (!empty($row['hearingRespondent']) && trim((string)$row['hearingRespondent']) !== '');

      if (!$hasRespondent) {
        // When there is no respondent, show the specified format
        $row['title'] = trim($cmp) . ' [No Respondent]';
      } else {
        // Build respondent display (single name or "et al")
        if (!empty($uniqueResps)) {
          if (count($uniqueResps) === 1) {
            $respDisplay = $uniqueResps[0];
          } else {
            $respDisplay = $uniqueResps[0] . ' et al';
          }
        } else {
          $respDisplay = ($row['hearingRespondent'] ?? ($row['hearingTitle'] ?? 'Respondent'));
        }

        // Prefer explicit complainant/respondent rendering when we have either
        if (!empty($complainant) || !empty($uniqueResps)) {
          $row['title'] = $cmp . ' Vs. ' . $respDisplay;
        } else {
          $row['title'] = !empty($row['hearingTitle']) ? $row['hearingTitle'] : ($cmp . ' Vs. ' . $respDisplay);
        }
      }

      $row['description'] = $row['remarks'];
      $row['lupon_assigned'] = !empty($luponName) ? $luponName : 'Not Yet Assigned';
      $sched_res[$row['hearingID']] = $row;
    }
    if(isset($conn)) $conn->close();
    ?>
  
  <script>
    var scheds = <?= json_encode($sched_res) ?>;
  </script>

  <script>
    var calendar;
    var Calendar = FullCalendar.Calendar;
    var events = [];

    $(function () {
      function isMobile(){
        return window.matchMedia('(max-width: 640px)').matches;
      }

      // Toggle/open the mobile view selection menu
      function toggleFcMobileMenu(){
        var btn = document.querySelector('.fc-more-button');
        if (!btn) return;
        var menu = document.getElementById('fc-mobile-menu');
        if (!menu){
          menu = document.createElement('div');
          menu.id = 'fc-mobile-menu';
          menu.innerHTML = [
            '<button type="button" data-view="dayGridMonth">Month</button>',
            '<button type="button" data-view="timeGridWeek">Week</button>',
            '<button type="button" data-view="listMonth">List</button>'
          ].join('');
          document.body.appendChild(menu);
          menu.addEventListener('click', function(e){
            var target = e.target.closest('button[data-view]');
            if (!target) return;
            var view = target.getAttribute('data-view');
            try { calendar.changeView(view); } catch(e){}
            menu.classList.remove('open');
          });
          // Close when clicking outside
          document.addEventListener('click', function(e){
            var btnEl = document.querySelector('.fc-more-button');
            if (!menu.classList.contains('open')) return;
            if ((btnEl && btnEl.contains(e.target)) || menu.contains(e.target)) return;
            menu.classList.remove('open');
          });
          // Close on Escape
          document.addEventListener('keydown', function(e){
            if (e.key === 'Escape'){
              var m = document.getElementById('fc-mobile-menu');
              if (m) m.classList.remove('open');
            }
          });
        }
        var rect = btn.getBoundingClientRect();
        var left = Math.max(8, rect.left + window.scrollX - 100); // nudge left so menu aligns nicely
        var top = rect.bottom + window.scrollY + 8;
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.classList.toggle('open');
      }

      if (!!scheds) {
        Object.keys(scheds).map(k => {
          var row = scheds[k];
          events.push({
            id: k,
            title: row.title,
            start: row.hearingDateTime,
            end: moment(row.hearingDateTime).add(1, 'hour').format('YYYY-MM-DDTHH:mm:ss')
          });
        });
      }

      // Build a set of dates (YYYY-MM-DD) with at least one hearing
      var hearingDateSet = new Set();
      (events || []).forEach(function(ev){
        if (ev && ev.start) {
          var d = new Date(ev.start);
          var y = d.getFullYear();
          var m = String(d.getMonth() + 1).padStart(2, '0');
          var day = String(d.getDate()).padStart(2, '0');
          hearingDateSet.add(y + '-' + m + '-' + day);
        }
      });

      calendar = new Calendar(document.getElementById('calendar'), {
        noEventsText: 'No hearings scheduled for this period',
        customButtons: {
          more: { text: '⋯', click: toggleFcMobileMenu }
        },
        headerToolbar: (isMobile()
          ? { left: 'prev,next', center: 'title', right: 'more' }
          : { left: 'prev,next', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' }
        ),
        height: '100%',
        expandRows: true,
        selectable: true,
        themeSystem: 'standard',
        events: events,
        initialView: 'dayGridMonth',
        eventMouseEnter: function(info){
          // On mobile, don't show hover tooltip; open modal via click instead
          if (typeof isMobile === 'function' && isMobile()) {
            const tooltipEl = document.getElementById('fc-event-tooltip');
            if (tooltipEl){
              tooltipEl.classList.remove('show');
              tooltipEl.classList.add('hidden');
            }
            return;
          }
          try{
            var row = scheds && scheds[info.event.id] ? scheds[info.event.id] : null;
            var title = (row && row.title) ? row.title : info.event.title || 'Hearing';
            var caseId = row && row.Case_ID ? row.Case_ID : info.event.id;
            var caseOriginalId = row && row.case_original_id ? row.case_original_id : (row && row.Case_ID ? row.Case_ID : info.event.id);
            var when = row && row.sdate ? row.sdate : (info.event.start ? moment(info.event.start).format('MMMM DD, YYYY h:mm A') : '');
            var remarks = row && row.description ? row.description : '';
            var html = '<div class="tip-title">' + $('<div>').text(title).html() + '</div>' +
                       '<div class="tip-row"><span class="tip-label">Case:</span><span>#' + $('<div>').text(caseOriginalId ).html() + '</span></div>' +
                       (when ? '<div class="tip-row"><span class="tip-label">When:</span><span>' + $('<div>').text(when).html() + '</span></div>' : '') +
                       (remarks ? '<div class="tip-row"><span class="tip-label">Note:</span><span>' + $('<div>').text(remarks).html() + '</span></div>' : '');
            const tooltipEl = document.getElementById('fc-event-tooltip');
            if (!tooltipEl) return;
            tooltipEl.innerHTML = html;
            tooltipEl.style.left = Math.min(info.jsEvent.clientX + 16, window.innerWidth - 320) + 'px';
            tooltipEl.style.top = Math.min(info.jsEvent.clientY + 16, window.innerHeight - 120) + 'px';
            tooltipEl.classList.add('show');
            tooltipEl.classList.remove('hidden');
            // track mouse move to follow cursor
            const move = function(e){
              tooltipEl.style.left = Math.min(e.clientX + 16, window.innerWidth - 320) + 'px';
              tooltipEl.style.top = Math.min(e.clientY + 16, window.innerHeight - 120) + 'px';
            };
            info.el.__tipMove = move;
            document.addEventListener('mousemove', move);
          }catch(e){ /* noop */ }
        },
        eventMouseLeave: function(info){
          const tooltipEl = document.getElementById('fc-event-tooltip');
          if (tooltipEl){
            tooltipEl.classList.remove('show');
            setTimeout(()=> tooltipEl && tooltipEl.classList.add('hidden'), 120);
          }
          if (info && info.el && info.el.__tipMove){
            document.removeEventListener('mousemove', info.el.__tipMove);
            delete info.el.__tipMove;
          }
        },
        eventTimeFormat: { // Show AM/PM in all views
          hour: 'numeric',
          minute: '2-digit',
          meridiem: 'short'
        },
        eventClassNames: function(arg){
          // Tag hearing events in non-list views for icon-only styling
          if (!arg.view.type.startsWith('list')) return ['evt-hearing'];
          return [];
        },
        eventContent: function(arg) {
          var viewType = arg.view.type;
          var title = escapeHtml(arg.event.title || 'Hearing');
          if (viewType === 'listWeek' || viewType === 'listMonth' || viewType === 'listDay') {
            // Show full title in list views, centered
            return { html: '<span class="block w-full text-center font-semibold text-slate-900">' + title + '</span>' };
          }
          if (viewType === 'timeGridWeek' || viewType === 'timeGridDay' || viewType === 'dayGridMonth') {
            // Icon-only in Month/Week with hover tooltip
            return { html: '<span class="hearing-icon" title="' + title + '" aria-label="' + title + '"><i class="fas fa-gavel"></i></span>' };
          }
          // Default rendering for other views
        },
  eventDidMount: function(info){
          // Hide time in Month, keep in Week/List
          var isMonth = info.view && info.view.type && info.view.type.indexOf('dayGrid') === 0;
          var timeEl = info.el.querySelector('.fc-event-time');
          if (timeEl) timeEl.style.display = isMonth ? 'none' : '';

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

          // Make the icon clickable: open Resident-style modal when the gavel icon is clicked
          var iconEl = info.el.querySelector('.hearing-icon');
          if (iconEl) {
            iconEl.addEventListener('click', function(e){
              e.preventDefault();
              e.stopPropagation();
              var ev = scheds[info.event.id];
              if (!ev) return;
              document.getElementById('modalTitle').textContent = ev.title || 'Schedule Details';
              const content = document.getElementById('modalContent');
              const caseLink = `/BPAMIS/LuponHeadMenu/view_case_details.php?id=${ev.Case_ID}`;
              const feedbackLink = `/BPAMIS/LuponHeadMenu/feedback_luponhead.php?case_id=${ev.Case_ID}`;
              const caseLinkLocal = `${caseLink}`;
              const feedbackLinkLocal = `${feedbackLink}`;
              const contentHTML = `
                <p class="mb-2"><strong class="text-gray-700">Hearing Title:</strong> <span class="text-indigo-600">${escapeHtml(ev.title || '')}</span></p>
                <p class="mb-2"><strong class="text-gray-700">Start:</strong> <span class="text-gray-800">${escapeHtml(ev.sdate || '')}</span></p>
                ${ev.description ? `<p class=\"mb-2\"><strong class=\"text-gray-700\">Remarks:</strong> <span class=\"text-gray-800\">${escapeHtml(ev.description)}</span></p>` : ''}
                <p class="mb-2"><strong class="text-gray-700">Case:</strong> 
                  <a href="${caseLinkLocal}" target="_self" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm underline">Open Case Info</a>
                </p>
                <p class="mt-3"><strong class="text-gray-700">Feedback:</strong> <a href="${feedbackLinkLocal}" target="_self" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm underline">Write Feedback</a></p>
                <div class="flex items-center mt-4">
                  <div class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></div>
                  <span class="text-xs text-gray-500">Tap anywhere outside to close</span>
                </div>`;

              // Use a handshake-aware opener: send a requestId and wait briefly for an ACK from parent.
              // If no ACK arrives, show the local in-iframe modal. This guarantees visibility on mobile
              // while avoiding duplicate modals when the parent successfully opens one.
              (function(){
                var payload = {
                  title: ev.title || 'Schedule Details',
                  content: contentHTML,
                  eventId: info.event.id,
                  caseId: ev.Case_ID
                };
                openEventWithHandshake(payload);
              })();
            });
          }
        },
        dayCellClassNames: function(arg){
          var d = arg.date;
          var key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
          if (hearingDateSet.has(key)) return ['has-hearing'];
          return [];
        },
        dayHeaderClassNames: function(arg){
          var d = arg.date; if (!d) return [];
          var key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
          if (hearingDateSet.has(key)) return ['has-hearing-header'];
          return [];
        },
        eventClick: function (info) {
          const ev = scheds[info.event.id];
          if (!ev) return;
          document.getElementById('modalTitle').textContent = ev.title || 'Schedule Details';
          const content = document.getElementById('modalContent');
          const caseLink = `/BPAMIS/LuponHeadMenu/view_case_details.php?id=${ev.Case_ID}`;
          const feedbackLink = `/BPAMIS/LuponHeadMenu/feedback_luponhead.php?case_id=${ev.Case_ID}`;
          const caseLinkLocal = `${caseLink}`;
          const feedbackLinkLocal = `${feedbackLink}`;
          const contentHTML = `
            <p class="mb-2"><strong class="text-gray-700">Hearing Title:</strong> <span class="text-indigo-600">${escapeHtml(ev.title || '')}</span></p>
            <p class="mb-2"><strong class="text-gray-700">Start:</strong> <span class="text-gray-800">${escapeHtml(ev.sdate || '')}</span></p>
            ${ev.description ? `<p class=\"mb-2\"><strong class=\"text-gray-700\">Remarks:</strong> <span class=\"text-gray-800\">${escapeHtml(ev.description)}</span></p>` : ''}
            <p class="mb-2"><strong class="text-gray-700">Case:</strong> 
              <a href="${caseLinkLocal}" target="_self" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm underline">Open Case Info</a>
            </p>
            <p class="mt-3"><a href="${feedbackLinkLocal}" target="_self" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-primary-600 text-white hover:bg-primary-700 text-xs font-semibold"><i class="fas fa-comment-dots"></i> Write Feedback</a></p>
            <div class="flex items-center mt-4">
              <div class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></div>
              <span class="text-xs text-gray-500">Tap anywhere outside to close</span>
            </div>`;

          // Try direct call to parent handler when same-origin for immediate response (helps on mobile),
          // otherwise fall back to postMessage.
          // Use handshake helper so iframe will fallback to local modal if parent doesn't respond.
          (function(){
            var payload = {
              title: ev.title || 'Schedule Details',
              content: contentHTML,
              eventId: info.event.id,
              caseId: ev.Case_ID
            };
            openEventWithHandshake(payload);
          })();
        },
        editable: false
      });

  calendar.render();
  // Force full height recalculation after initial paint
  setTimeout(()=>calendar.updateSize(), 50);

      // Adapt header toolbar on resize (switch between buttons and three-dot menu)
      window.addEventListener('resize', function(){
        var toolbar = isMobile()
          ? { left: 'prev,next', center: 'title', right: 'more' }
          : { left: 'prev,next', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' };
        try { calendar.setOption('headerToolbar', toolbar); } catch(e){}
        var menu = document.getElementById('fc-mobile-menu');
        if (menu) menu.classList.remove('open');
      });

      // Secretary: reschedule/delete disabled
    });

    function closeModal() {
      document.getElementById('eventModal').classList.add('hidden');
    }

      // Handshake helpers: keep track of pending open requests and provide a safe fallback.
      var pendingOpenRequests = {};

      // Show the in-iframe modal (centralized helper) — safer for mobile when parent handler isn't present
      function showLocalEventModal(title, htmlContent, eventId, caseId) {
        try {
          var modal = document.getElementById('eventModal');
          var titleEl = document.getElementById('modalTitle');
          var contentEl = document.getElementById('modalContent');
          var rescheduleBtn = document.getElementById('reschedule');
          var deleteBtn = document.getElementById('delete');
          if (!modal || !contentEl) {
            console.debug('CalendarLuponHead: local modal elements not found');
            return;
          }
          if (titleEl) titleEl.textContent = title || 'Schedule Details';
          contentEl.innerHTML = htmlContent || '<p class="text-gray-500">No details available.</p>';
          // Store ids if buttons exist
          if (rescheduleBtn && typeof eventId !== 'undefined') rescheduleBtn.setAttribute('data-id', eventId);
          if (deleteBtn && typeof caseId !== 'undefined') deleteBtn.setAttribute('data-case', caseId);
          modal.classList.remove('hidden');
          console.debug('CalendarLuponHead: shown local modal for event', eventId, caseId);
        } catch (err) {
          console.debug('CalendarLuponHead: failed to show local modal', err);
        }
      }
      
      // Open event helper with handshake: send requestId, wait for ACK, otherwise show local modal.
      function openEventWithHandshake(payload) {
        try {
          var requestId = 'req_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
          payload.requestId = requestId;

          // Try direct call first (same-origin fast path). If that throws, fall back to postMessage.
          if (window.parent && typeof window.parent.openEventModal === 'function') {
            try {
              window.parent.openEventModal(payload);
            } catch (err) {
              try { window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*'); } catch (_) {}
            }
          } else {
            try { window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*'); } catch (_) {}
          }

          // If we don't get an ACK in this interval, show the local modal.
          var timeoutId = setTimeout(function () {
            if (pendingOpenRequests[requestId]) {
              delete pendingOpenRequests[requestId];
              showLocalEventModal(payload.title, payload.content, payload.eventId, payload.caseId);
            }
          }, 400);
          pendingOpenRequests[requestId] = timeoutId;
        } catch (err) {
          // On any failure, fall back to showing local modal immediately.
          try { showLocalEventModal(payload.title, payload.content, payload.eventId, payload.caseId); } catch (_) {}
        }
      }

      // Listen for ACKs from parent (so we don't show the local modal unnecessarily).
      window.addEventListener('message', function (e) {
        try {
          var data = e.data || {};
          if (data && data.type === 'openEventModalAck' && data.requestId) {
            var rid = data.requestId;
            if (pendingOpenRequests[rid]) {
              clearTimeout(pendingOpenRequests[rid]);
              delete pendingOpenRequests[rid];
            }
            // If the iframe has already shown a local modal, close it so only the parent's modal remains.
            try { closeModal(); } catch (_) {}
          }
        } catch (err) { /* noop */ }
      });
    function escapeHtml(unsafe) {
      if (unsafe === null || unsafe === undefined) return '';
      return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // Close modal when clicking on dimmed overlay (outside the white card)
    document.addEventListener('click', function(e){
      const modal = document.getElementById('eventModal');
      if (!modal || modal.classList.contains('hidden')) return;
      const card = modal.querySelector('div.relative');
      if (card && !card.contains(e.target)) {
        closeModal();
      }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeModal();
    });
  </script>
</body>

</html>
