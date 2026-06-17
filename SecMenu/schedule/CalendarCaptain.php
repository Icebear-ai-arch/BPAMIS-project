<?php
require_once('db-connect.php');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scheduling • Captain</title>
  <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <!-- FullCalendar and dependency CDN (fixed paths and correct load order) -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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
    #eventModal {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      z-index: 99999 !important;
    }
    #eventModal .relative {
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.13);
      border-radius: 22px;
      border: 1.5px solid #e0e7ef;
      backdrop-filter: blur(8px);
      position: relative;
      overflow: hidden;
      z-index: 100000 !important;
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
      /* Make outer wrapper padding compact and balanced on mobile */
      #calendar-wrapper {
        padding: 0.25rem 0.25rem 0.35rem 0.25rem;
      }
      #calendar {
        /* slightly more even inner padding on mobile */
        padding: 0.6rem 0.3rem 0.5rem 0.3rem;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
        border-radius: 16px; /* a bit tighter than desktop */
      }
  .fc .fc-toolbar-title { font-size: 0.95rem; }
  .fc .fc-button { font-size: 0.7rem; padding: 0.28rem 0.5rem; }
      /* Make day-of-week header text smaller and not bold on mobile */
      .fc .fc-col-header-cell-cushion {
        font-size: 0.7rem !important;
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

  <!-- Local event modal (used when CalendarCaptain is opened directly, not embedded in a parent) -->
  <div id="eventModalLocal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden relative shadow-2xl">
      <button id="close-eventModalLocal" class="absolute top-3 right-3 z-50 bg-white/80 hover:bg-white/100 rounded-lg w-9 h-9 flex items-center justify-center text-rose-700 shadow-sm" title="Close details"><i class="fa-solid fa-xmark"></i></button>
      <div class="p-6" id="eventModalLocalContent"></div>
    </div>
  </div>

  <div id="calendar-wrapper">
    <div id="calendar" class="relative z-10"></div>
  </div>

  <?php 
    // First, sync hearing visibility: set visible = 0 for hearings with meeting logs
    $sync_sql = "
      UPDATE schedule_list sl
      INNER JOIN MEETING_LOGS ml 
        ON ml.Case_ID = sl.case_id 
        AND DATE(ml.Hearing_Date) = DATE(sl.hearingDateTime)
        AND TIME(ml.Hearing_Time) = TIME(sl.hearingDateTime)
      SET sl.visible = 0
      WHERE sl.visible = 1
    ";
    $conn->query($sync_sql);

    // Select visible hearings for Captain, excluding hearings that already have meeting logs
    $schedules = $conn->query("\n      SELECT \n        sl.*, \n        ci.Case_ID AS Case_ID,\n        ci.Case_Status\n      FROM schedule_list sl\n      JOIN case_info ci ON ci.Case_ID = sl.case_id\n      LEFT JOIN MEETING_LOGS ml ON ml.Case_ID = sl.case_id \n        AND DATE(ml.Hearing_Date) = DATE(sl.hearingDateTime)\n        AND TIME(ml.Hearing_Time) = TIME(sl.hearingDateTime)\n      WHERE TRIM(LOWER(COALESCE(ci.Case_Status, ''))) NOT IN (\n        'mediation resolved',\n        'conciliation resolved',\n        'arbitration resolved',\n        'dismissed',\n        'certificate to file action'\n      )\n      AND sl.visible = 1\n      AND ml.Log_ID IS NULL\n    ");
    $sched_res = [];
    foreach($schedules->fetch_all(MYSQLI_ASSOC) as $row){
        $row['sdate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime']));
        $row['edate'] = date("F d, Y h:i A", strtotime($row['hearingDateTime'] . ' +1 hour'));
        $row['title'] = $row['hearingTitle'];
        $row['description'] = $row['remarks'];
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
            var when = row && row.sdate ? row.sdate : (info.event.start ? moment(info.event.start).format('MMMM DD, YYYY h:mm A') : '');
            var remarks = row && row.description ? row.description : '';
            var html = '<div class="tip-title">' + $('<div>').text(title).html() + '</div>' +
                       '<div class="tip-row"><span class="tip-label">Case:</span><span>#' + $('<div>').text(caseId).html() + '</span></div>' +
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
            // Show full title in list views, left-aligned for readability
            return { html: '<span class="block w-full font-semibold text-slate-900">' + title + '</span>' };
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

          // List view: ensure column order is [dot/graphic] → [title/type] → [time]
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
          } catch(e) { /* noop */ }

          // Make the icon clickable: open modal in parent window when the gavel icon is clicked
          var iconEl = info.el.querySelector('.hearing-icon');
          if (iconEl) {
            iconEl.addEventListener('click', function(e){
              e.preventDefault();
              e.stopPropagation();
              // Hide tooltip if visible
              const tooltipEl = document.getElementById('fc-event-tooltip');
              if (tooltipEl){
                tooltipEl.classList.remove('show');
                tooltipEl.classList.add('hidden');
              }
              var ev = scheds[info.event.id];
              if (!ev) return;
              
              // Always open links in the same tab for meeting logs / case info
              var targetAttr = '_self';
              
              // Build modal content HTML - Captain uses OfficialMenu path
              const caseLink = `../OfficialMenu/view_case_details_cap.php?id=${ev.Case_ID}`;
              const contentHTML = `
                <p class="mb-2"><strong class="text-gray-700">Hearing Title:</strong> <span class="text-indigo-600">${escapeHtml(ev.title || '')}</span></p>
                <p class="mb-2"><strong class="text-gray-700">Start:</strong> <span class="text-gray-800">${escapeHtml(ev.sdate || '')}</span></p>
                ${ev.description ? `<p class="mb-2"><strong class="text-gray-700">Remarks:</strong> <span class="text-gray-800">${escapeHtml(ev.description)}</span></p>` : ''}
                <p class="mb-2"><strong class="text-gray-700">Case:</strong> <a href="${caseLink}" target="${targetAttr}" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm underline">Open Case Info</a></p>
                <div class="flex items-center mt-4">
                  <div class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></div>
                  <span class="text-xs text-gray-500">Click outside to close</span>
                </div>`;
              
              // If page is not embedded (parent === window), open a local modal instead
              var payload = { title: ev.title || 'Schedule Details', content: contentHTML, eventId: info.event.id, caseId: ev.Case_ID };
              if (window.parent === window) {
                openLocalEventModal(payload);
                return;
              }
              // Try direct call to parent handler when same-origin for immediate response (helps on mobile),
              // otherwise fall back to postMessage. This improves reliability when embedding via iframe.
              try {
                if (window.parent && typeof window.parent.openEventModal === 'function') {
                  window.parent.openEventModal(payload);
                } else {
                  window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*');
                }
              } catch (e) {
                // If direct call fails due to cross-origin, use postMessage as a fallback, otherwise open local modal
                try { window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*'); } catch (_) { openLocalEventModal(payload); }
              }
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
          // Hide any tooltip that may have appeared on mobile
          const tooltipEl = document.getElementById('fc-event-tooltip');
          if (tooltipEl){
            tooltipEl.classList.remove('show');
            tooltipEl.classList.add('hidden');
          }
          
          const ev = scheds[info.event.id];
          if (!ev) return;
          
          // Check if mobile
          // Always open links in the same tab for meeting logs / case info
          var targetAttr = '_self';
          
          // Build modal content HTML - Captain uses OfficialMenu path
          const caseLink = `../OfficialMenu/view_case_details_cap.php?id=${ev.Case_ID}`;
          const contentHTML = `
            <p class="mb-2"><strong class="text-gray-700">Hearing Title:</strong> <span class="text-indigo-600">${escapeHtml(ev.title || '')}</span></p>
            <p class="mb-2"><strong class="text-gray-700">Start:</strong> <span class="text-gray-800">${escapeHtml(ev.sdate || '')}</span></p>
            ${ev.description ? `<p class="mb-2"><strong class="text-gray-700">Remarks:</strong> <span class="text-gray-800">${escapeHtml(ev.description)}</span></p>` : ''}
            <p class="mb-2"><strong class="text-gray-700">Case:</strong> <a href="${caseLink}" target="${targetAttr}" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm underline">Open Case Info</a></p>
            <div class="flex items-center mt-4">
              <div class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></div>
              <span class="text-xs text-gray-500">Click outside to close</span>
            </div>`;
          
          // Try direct call to parent handler when same-origin for immediate response (helps on mobile),
          // If page is not embedded (parent === window), open a local modal instead
          var payload = { title: ev.title || 'Schedule Details', content: contentHTML, eventId: info.event.id, caseId: ev.Case_ID };
          if (window.parent === window) { openLocalEventModal(payload); return; }
          // otherwise try parent handler or postMessage
          try {
            if (window.parent && typeof window.parent.openEventModal === 'function') {
              window.parent.openEventModal(payload);
            } else {
              window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*');
            }
          } catch (e) {
            try { window.parent.postMessage({ type: 'openEventModal', payload: payload }, '*'); } catch (_) { openLocalEventModal(payload); }
          }
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
  </script>
</body>

</html>
