<?php
require_once('db-connect.php');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


// Default values
$lupon_name = '';
$notif_count = 0;

// Check if official is logged in
if (!empty($_SESSION['official_id'])) {
    $luponId = $_SESSION['official_id'];

    // Get lupon name from DB
    $sqlLupon = "SELECT name AS lupon_name FROM barangay_officials WHERE official_id = ?";
    if ($stmt = $conn->prepare($sqlLupon)) {
        $stmt->bind_param("i", $luponId);
        $stmt->execute();
        $resultLupon = bpamis_stmt_get_result($stmt);
    if ($resultLupon && $row = $resultLupon->fetch_assoc()) {
            $_SESSION['lupon_name'] = $row['lupon_name'];
            $lupon_name = $row['lupon_name'];
        }
        $stmt->close();
    }
  // Fallback to session official_name if direct lookup didn't return a name
  if (empty($lupon_name) && !empty($_SESSION['official_name'])) {
    $lupon_name = trim((string)$_SESSION['official_name']);
  }
}



?>
<?php include '../../OfficialMenu/sidebar_lupon.php'; ?>
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
      /* Subtle premium-like background */
      background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%);
    }
    /* Modern glassmorphism calendar container */
    #calendar-wrapper { flex:1 1 auto; display:flex; padding:0.8rem 0.8rem 0.5rem 0.5rem; }
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
    }
    #calendar:hover { transform: translateY(-4px) scale(1.01); }
    .fc .fc-toolbar-title {
      font-weight: 700;
      font-size: 1.15rem;
      color: #0281d4;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 0.4em;
    }
    .fc .fc-button {
      box-shadow: none !important;
      padding: 0.32rem 0.60rem;
      border-radius: 7px !important;
      font-weight: 600;
      font-size: 0.78rem;
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
    /* Three-dot menu button styling */
    .fc .fc-viewMenuButton-button {
      font-size: 1.4rem !important;
      line-height: 1 !important;
      padding: 0.2rem 0.6rem !important;
      font-weight: 700 !important;
      min-width: 36px !important;
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
      background: linear-gradient(90deg,rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
      border-radius: 12px;
      box-shadow: 0 2px 8px 0 rgba(2, 129, 212, 0.08);
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
    @keyframes eventFadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    /* Modal glassmorphism style */
    #event-details-modal .bg-white {
      background: rgba(255,255,255,0.95);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.13);
      border-radius: 22px;
      border: 1.5px solid #e0e7ef;
      backdrop-filter: blur(8px);
    }
    #event-details-modal h5 {
      color: #0281d4;
      font-weight: 700;
      font-size: 1.3rem;
    }
    #event-details-modal dt {
      color: #0281d4;
      font-weight: 600;
    }
    #event-details-modal dd {
      font-size: 1.08rem;
    }
    #event-details-modal button {
      transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    }
    #event-details-modal button:focus {
      outline: 2px solid #0281d4;
      outline-offset: 2px;
    }
    /* Three-dot menu button - hidden by default, shown on mobile */
    .fc .fc-viewMenuButton-button { 
      position: relative; 
      display: none !important; 
    }
    .view-menu-dropdown {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      margin-top: 0.25rem;
      background: rgba(255,255,255,0.98);
      border: 1px solid rgba(180,205,225,0.7);
      border-radius: 10px;
      box-shadow: 0 8px 24px rgba(2,129,212,0.2);
      backdrop-filter: blur(10px);
      z-index: 1000;
      min-width: 140px;
      overflow: hidden;
    }
    .view-menu-dropdown.active { display: block; }
    .view-menu-item {
      padding: 0.65rem 1rem;
      cursor: pointer;
      color: #0276c4;
      font-weight: 600;
      font-size: 0.85rem;
      transition: all 0.15s;
      border-bottom: 1px solid rgba(180,205,225,0.3);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .view-menu-item:last-child { border-bottom: none; }
    .view-menu-item:hover {
      background: linear-gradient(120deg, #e8f7ff 0%, #f3fbff 100%);
      color: #0369a1;
    }
    .view-menu-item.active {
      background: #e4f6ff;
      color: #0b6fa2;
    }

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
      
      /* Remove bold from day names on mobile */
      .fc .fc-col-header-cell-cushion {
        font-weight: 400 !important;
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
      }
      .fc .fc-event-title {
        color: #1e293b !important;
        font-size: 0.95rem;
      }
      
      /* Hide default view buttons on mobile */
      .fc .fc-dayGridMonth-button,
      .fc .fc-timeGridWeek-button,
      .fc .fc-timeGridDay-button,
      .fc .fc-listMonth-button {
        display: none !important;
      }
      
      /* Show three-dot menu button ONLY on mobile */
      .fc .fc-viewMenuButton-button {
        display: inline-flex !important;
      }
    }
    /* Ensure event time and title are black in week and list views */
    .fc-timeGridWeek-view .fc-event-time, 
    .fc-timeGridWeek-view .fc-event-title,
    .fc-listWeek-view .fc-event-time, 
    .fc-listWeek-view .fc-event-title { color: #111 !important; }
    .fc-list .fc-event-time, .fc-list .fc-event-title { color: #111 !important; }
    /* Fix list view: ensure titles wrap and don't overlap/pile */
    .fc .fc-list .fc-list-event-title a,
    .fc .fc-list .fc-list-event-title span {
      white-space: normal !important;
      overflow: visible !important;
      text-overflow: unset !important;
    }
    .fc .fc-list .fc-list-event-title a { display: block; }
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

    /* Remove calendar grid lines (borders) across views */
    .fc-theme-standard { --fc-border-color: transparent; }
    .fc-theme-standard td, .fc-theme-standard th { border: none !important; }
    .fc .fc-scrollgrid, .fc .fc-scrollgrid-section, .fc .fc-scrollgrid-sync-table, .fc .fc-scrollgrid-liquid { border: none !important; }
    .fc .fc-daygrid-day, .fc .fc-daygrid-day-frame, .fc .fc-daygrid-day-top, .fc .fc-daygrid-body, .fc .fc-daygrid { border: none !important; }
    .fc .fc-timegrid-slot, .fc .fc-timegrid-axis, .fc .fc-timegrid-slot-lane, .fc .fc-timegrid-divider { border: none !important; }
    .fc .fc-list, .fc .fc-list-table, .fc .fc-list-table tr, .fc .fc-list-table th, .fc .fc-list-table td { border: none !important; }

    /* Event hover tooltip */
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

    /* Chip-style event content with icon (to match secretary style) */
  .event-chip { display:flex; align-items:center; gap:.5rem; padding:.3rem .5rem; border-radius:12px; background: rgba(255,255,255,0.7); border:1px solid rgba(180,205,225,0.55); box-shadow: 0 2px 8px rgba(2,129,212,0.12); }
    .event-chip .event-chip-main { font-weight:700; color:#0f172a; font-size:.9rem; }
    .event-chip .event-chip-sub { color:#334155; font-size:.78rem; opacity:.85; }
    /* Compact for month cells */
    .fc-daygrid-event .event-chip { padding:.2rem .4rem; }
    .fc-daygrid-event .event-chip .event-chip-main { font-size:.85rem; }
    .fc-daygrid-event .event-chip .event-chip-sub { display:none; }

    /* Day highlight for days with schedules (like today highlight) */
    .fc .fc-daygrid-day.has-sched {
      background: linear-gradient(90deg,rgba(199, 229, 248, 0.85) 0%, #f0f9ff 100%) !important;
      border-radius: 12px;
      box-shadow: 0 2px 8px 0 rgba(2, 129, 212, 0.08);
    }
    /* Ensure the frame is positioned for badge placement */
    .fc .fc-daygrid-day-frame { position: relative; }
    .fc .fc-daygrid-day .sched-day-icon {
      position: absolute;
      left: 8px;
      bottom: 6px;
      font-size: 12px;
      color: #7c3aed;
      text-shadow: 0 0 0 1px rgba(255,255,255,0.95), 0 2px 6px rgba(12,156,237,0.25);
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
  </style>
</head>

<body>
  <div id="fc-event-tooltip" class="hidden"></div>
  
  <!-- View Menu Dropdown -->
  <div id="view-menu-dropdown" class="view-menu-dropdown">
    <div class="view-menu-item active" data-view="dayGridMonth">
      <i class="fas fa-calendar"></i> Month
    </div>
    <div class="view-menu-item" data-view="timeGridWeek">
      <i class="fas fa-calendar-week"></i> Week
    </div>
    <div class="view-menu-item" data-view="timeGridDay">
      <i class="fas fa-calendar-day"></i> Day
    </div>
    <div class="view-menu-item" data-view="listMonth">
      <i class="fas fa-list"></i> List
    </div>
  </div>
  
  <div id="calendar-wrapper">
    <div id="calendar" class="relative z-10"></div>
  </div>

  <!-- Event Details Modal -->
  <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center hidden" id="event-details-modal">
    <div class="bg-white rounded-lg w-full max-w-md">
      <div class="border-b px-6 py-4 flex justify-between items-center">
        <h5 class="text-lg font-semibold">Schedule Details</h5>
        <button class="text-gray-500 hover:text-gray-800" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="px-6 py-4 space-y-3">
      <div>
          <dt class="text-gray-500 text-sm">Case Number</dt>
          <dd id="case-id" class="text-xl font-bold"></dd>
        </div>
        <div>
          <dt class="text-gray-500 text-sm">Title</dt>
          <dd id="title" class="text-xl font-bold"></dd>
        </div>
        <div>
          <dt class="text-gray-500 text-sm">Date & Time</dt>
          <dd id="start" class="text-gray-700"></dd>
        </div>
        <div>
          <dt class="text-gray-500 text-sm">View Details</dt>
            <dd>
            <a id="view-details-link" href="#" class="text-gray-500 text-sm underline">Go to Case Info</a>
          </dd>
        </div>
      </div>
      <div class="border-t px-6 py-4 flex justify-end space-x-2">
       
        <button onclick="closeModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">Back</button>
      </div>
    </div>
  </div>

 <?php 

// Build schedules ONLY for this lupon: match mediator_name or assigned lupon field
$sched_res = [];
if (!empty($lupon_name)) {
  $like = "%" . $lupon_name . "%";
  $sql = "
    SELECT sl.*
    FROM schedule_list sl
    INNER JOIN case_info ci ON ci.case_id = sl.case_id
    LEFT JOIN mediation_info mi ON mi.case_id = ci.case_id
    LEFT JOIN conciliation r ON r.case_id = ci.case_id
    LEFT JOIN arbitration s ON s.case_id = ci.case_id
    WHERE (
      mi.mediator_name LIKE ?
      OR r.mediator_name LIKE ?
      OR s.mediator_name LIKE ?
      OR ci.lupon_assign LIKE ?
    )
    AND ci.Case_Status IN ('Conciliation','Arbitration')
  ";
    if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    while ($row = $result->fetch_assoc()) {
      // Pretty dates
      $row['sdate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime']));
      $row['edate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime'] . ' +1 hour'));

      // Derive parties: Complainant and Respondent(s)
      $caseId = isset($row['case_id']) ? (int)$row['case_id'] : (isset($row['Case_ID']) ? (int)$row['Case_ID'] : 0);
      $complainant = '';
      $respondents = [];

      if ($caseId) {
        // Detect external complainant FK column in complaint_info (varies across installs)
        $possibleExtCols = ['External_Complaint_ID','External_Complainant_ID','external_complaint_id','external_complainant_id'];
        $foundExtCol = null;
        foreach ($possibleExtCols as $c) {
          $chk = $conn->query("SHOW COLUMNS FROM complaint_info LIKE '" . $conn->real_escape_string($c) . "'");
          if ($chk && $chk->num_rows > 0) { $foundExtCol = $c; break; }
        }

        // Detect PK column name in external_complainant table
        $possibleExtPk = ['External_Complaint_ID','external_complaint_id'];
        $foundExtPk = null;
        foreach ($possibleExtPk as $c) {
          $chk = $conn->query("SHOW COLUMNS FROM external_complainant LIKE '" . $conn->real_escape_string($c) . "'");
          if ($chk && $chk->num_rows > 0) { $foundExtPk = $c; break; }
        }

        // Build party SQL using detected column names where available
        $extJoinOn = '';
        if ($foundExtCol && $foundExtPk) {
          $extJoinOn = "ON ci.`" . $foundExtCol . "` = ext_comp.`" . $foundExtPk . "`";
        } elseif ($foundExtCol) {
          // try common external_complaint_id on ext_comp
          $extJoinOn = "ON ci.`" . $foundExtCol . "` = ext_comp.External_Complaint_ID";
        } else {
          // fallback join that won't match (prevents SQL error) — use a false condition
          $extJoinOn = "ON 1=0";
        }

        $partySql = "SELECT ci.Complaint_ID,
                          COALESCE(comp.First_Name, ext_comp.First_Name) AS comp_first,
                          COALESCE(comp.Last_Name, ext_comp.Last_Name) AS comp_last,
                          resp.First_Name AS resp_first,
                          resp.Last_Name AS resp_last
                      FROM case_info cs
                      LEFT JOIN complaint_info ci ON cs.Complaint_ID = ci.Complaint_ID
                      LEFT JOIN resident_info comp ON ci.Resident_ID = comp.Resident_ID
                      LEFT JOIN external_complainant ext_comp " . $extJoinOn . "
                      LEFT JOIN resident_info resp ON ci.Respondent_ID = resp.Resident_ID
                      WHERE cs.Case_ID = ? LIMIT 1";
        if ($ps = $conn->prepare($partySql)) {
          $ps->bind_param('i', $caseId);
          $ps->execute();
          $prs = bpamis_stmt_get_result($ps);
          if ($prow = $prs->fetch_assoc()) {
            $complainant = trim((($prow['comp_first'] ?? '') . ' ' . ($prow['comp_last'] ?? '')));
            if (!empty($prow['resp_first']) || !empty($prow['resp_last'])) {
              $respondents[] = trim((($prow['resp_first'] ?? '') . ' ' . ($prow['resp_last'] ?? '')));
            }
            $complaintId = isset($prow['Complaint_ID']) ? (int)$prow['Complaint_ID'] : 0;
          }
          $ps->close();
        }

        // Additional respondents from COMPLAINT_RESPONDENTS (if any)
        if (!empty($complaintId)) {
          $addRespSql = "SELECT ri.First_Name, ri.Last_Name FROM COMPLAINT_RESPONDENTS cr JOIN RESIDENT_INFO ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?";
          if ($ars = $conn->prepare($addRespSql)) {
            $ars->bind_param('i', $complaintId);
            $ars->execute();
            $arsr = bpamis_stmt_get_result($ars);
            while ($ar = $arsr->fetch_assoc()) {
              $respondents[] = trim((($ar['First_Name'] ?? '') . ' ' . ($ar['Last_Name'] ?? '')));
            }
            $ars->close();
          }
        }

        // Determine lupon/mediator assigned for this case (compat across tables)
        $luponName = '';
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

      // Build title as "Complainant Vs. Respondent(s)" — fallback to hearingTitle when parties missing
      $cmp = $complainant ?: (isset($row['hearingComplainant']) ? $row['hearingComplainant'] : 'Complainant');
      $respDisplay = !empty($respondents) ? implode(', ', array_unique($respondents)) : (isset($row['hearingRespondent']) ? $row['hearingRespondent'] : 'Respondent');
      if (!empty($complainant) || !empty($respondents)) {
        $row['title'] = $cmp . ' Vs. ' . $respDisplay;
      } else {
        $row['title'] = !empty($row['hearingTitle']) ? $row['hearingTitle'] : ($cmp . ' Vs. ' . $respDisplay);
      }

      $row['description'] = $row['remarks'];
      $row['lupon_assigned'] = !empty($luponName) ? $luponName : 'Not Yet Assigned';
      $sched_res[$row['hearingID']] = $row;
    }
    $stmt->close();
  }
}

if (isset($conn)) $conn->close();

?>

  
  <script>
    var scheds = <?= json_encode($sched_res) ?>;
  </script>

  <script>
    var calendar;
    var Calendar = FullCalendar.Calendar;
    var events = [];

    $(function () {
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

      const tooltipEl = document.getElementById('fc-event-tooltip');

      function showTooltip(html, x, y){
        if(!tooltipEl) return;
        tooltipEl.innerHTML = html;
        tooltipEl.style.left = Math.min(x + 16, window.innerWidth - 320) + 'px';
        tooltipEl.style.top = Math.min(y + 16, window.innerHeight - 120) + 'px';
        tooltipEl.classList.add('show');
        tooltipEl.classList.remove('hidden');
      }
      function moveTooltip(x, y){
        if(!tooltipEl || tooltipEl.classList.contains('hidden')) return;
        tooltipEl.style.left = Math.min(x + 16, window.innerWidth - 320) + 'px';
        tooltipEl.style.top = Math.min(y + 16, window.innerHeight - 120) + 'px';
      }
      function hideTooltip(){
        if(!tooltipEl) return;
        tooltipEl.classList.remove('show');
        setTimeout(()=> tooltipEl && tooltipEl.classList.add('hidden'), 120);
      }
      let tooltipMoveHandler = null;

      // Build a set of dates (YYYY-MM-DD) that have schedules
      var eventDates = new Set(events.map(e => moment(e.start).format('YYYY-MM-DD')));

      calendar = new Calendar(document.getElementById('calendar'), {
        headerToolbar: {
          left: 'prev,next',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth viewMenuButton'
        },
        customButtons: {
          viewMenuButton: {
            text: '⋮',
            click: function() {
              const dropdown = document.getElementById('view-menu-dropdown');
              dropdown.classList.toggle('active');
            }
          }
        },
        height: '100%',
        expandRows: true,
        selectable: true,
        themeSystem: 'standard',
        events: events,
        initialView: 'dayGridMonth',
        dayCellDidMount: function(arg){
          // Only decorate for month grid
          if (arg.view && arg.view.type === 'dayGridMonth') {
            var dstr = moment(arg.date).format('YYYY-MM-DD');
            if (eventDates.has(dstr)) {
              arg.el.classList.add('has-sched');
              var frame = arg.el.querySelector('.fc-daygrid-day-frame') || arg.el;
              if (frame && !frame.querySelector('.sched-day-icon')) {
                var icon = document.createElement('i');
                icon.className = 'sched-day-icon fas fa-calendar-check';
                frame.appendChild(icon);
              }
            }
          }
        },
        eventTimeFormat: { // Show AM/PM in all views
          hour: 'numeric',
          minute: '2-digit',
          meridiem: 'short'
        },
        eventClassNames: function(arg){
          // Use icon-only in non-list views
          if (!arg.view.type.startsWith('list')) return ['evt-hearing'];
          return [];
        },
        eventMouseEnter: function(info){
          // Disable tooltip on mobile devices (screen width <= 640px)
          if (window.innerWidth <= 640) return;
          
          try{
            var row = scheds && scheds[info.event.id] ? scheds[info.event.id] : null;
            var title = (row && row.title) ? row.title : info.event.title || 'Schedule';
            var caseId = row && row.Case_ID ? row.Case_ID : info.event.id;
            var when = row && row.sdate ? row.sdate : (info.event.start ? moment(info.event.start).format('MMMM DD, YYYY h:mm A') : '');
            var remarks = row && row.description ? row.description : '';
            var html = '<div class="tip-title">' + $('<div>').text(title).html() + '</div>' +
                       '<div class="tip-row"><span class="tip-label">Case:</span><span>#' + $('<div>').text(caseId).html() + '</span></div>' +
                       (when ? '<div class="tip-row"><span class="tip-label">When:</span><span>' + $('<div>').text(when).html() + '</span></div>' : '') +
                       (remarks ? '<div class="tip-row"><span class="tip-label">Note:</span><span>' + $('<div>').text(remarks).html() + '</span></div>' : '');
            showTooltip(html, info.jsEvent.clientX, info.jsEvent.clientY);
            // attach document mousemove to update tooltip position
            tooltipMoveHandler = function(e){ moveTooltip(e.clientX, e.clientY); };
            document.addEventListener('mousemove', tooltipMoveHandler);
          }catch(e){ /* noop */ }
        },
        eventMouseLeave: function(){ 
          // Disable tooltip on mobile devices (screen width <= 640px)
          if (window.innerWidth <= 640) return;
          
          hideTooltip();
          if(tooltipMoveHandler){ document.removeEventListener('mousemove', tooltipMoveHandler); tooltipMoveHandler = null; }
        },
        eventContent: function(arg) {
          var viewType = arg.view.type;
          var title = $('<div>').text(arg.event.title || 'Schedule').html();
          if (viewType === 'listWeek' || viewType === 'listMonth' || viewType === 'listDay') {
            // Centered title in list views
            return { html: '<span class="block w-full text-center font-semibold text-slate-900">' + title + '</span>' };
          }
          // Icon-only in Month/Week/Day grid
          return { html: '<span class="hearing-icon" title="' + title + '" aria-label="' + title + '"><i class="fas fa-gavel"></i></span>' };
        },
        eventDidMount: function(info){
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
        },
        
        eventClick: function (info) {
          let event = scheds[info.event.id];
          
          // Check if we're in an iframe (mobile view in home-lupon)
            if (window.parent !== window) {
            // Send event data to parent window (include lupon assignment)
            window.parent.postMessage({
              type: 'showEventModal',
              data: {
                caseId: event.Case_ID,
                title: event.title,
                start: event.sdate,
                lupon: event.lupon_assigned || '',
                detailsLink: '../../OfficialMenu/view_case_details_lupon.php?id=' + event.Case_ID
              }
            }, '*');
          } else {
            // Show modal in current window (desktop/standalone view)
            $('#case-id').text(event.Case_ID);
            $('#title').text(event.title);
            $('#start').text(event.sdate);
            $('#view-details-link').attr('href', '../../OfficialMenu/view_case_details_lupon.php?id=' + event.Case_ID);
            $('#event-details-modal').removeClass('hidden');
          }
        },
        editable: false
      });

      calendar.render();
      // Ensure full height after initial paint
      setTimeout(()=>calendar.updateSize(),50);

      // Add view menu functionality
      const viewMenuItems = document.querySelectorAll('.view-menu-item');
      const viewMenuDropdown = document.getElementById('view-menu-dropdown');
      
      viewMenuItems.forEach(item => {
        item.addEventListener('click', function() {
          const viewType = this.getAttribute('data-view');
          calendar.changeView(viewType);
          
          // Update active state
          viewMenuItems.forEach(i => i.classList.remove('active'));
          this.classList.add('active');
          
          // Close dropdown
          viewMenuDropdown.classList.remove('active');
        });
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        const menuButton = document.querySelector('.fc-viewMenuButton-button');
        if (viewMenuDropdown && !viewMenuDropdown.contains(e.target) && 
            (!menuButton || !menuButton.contains(e.target))) {
          viewMenuDropdown.classList.remove('active');
        }
      });

      // Update active menu item when view changes
      calendar.on('viewDidMount', function(info) {
        const currentView = info.view.type;
        viewMenuItems.forEach(item => {
          if (item.getAttribute('data-view') === currentView) {
            item.classList.add('active');
          } else {
            item.classList.remove('active');
          }
        });
      });

    });

    function closeModal() {
      $('#event-details-modal').addClass('hidden');
    }
  </script>

  
</body>

</html>
