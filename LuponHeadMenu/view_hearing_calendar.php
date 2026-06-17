<?php
/**
 * View Hearing Calendar Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';
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
    <title>View Hearing Calendar</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <!-- Font Awesome for Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }
        #calendar {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            background: white;
        }
        .fc-event {
            cursor: pointer;
        }

        /* Mobile responsive styles */
        @media (max-width: 640px) {
            .calendar-header {
                font-size: 1rem !important;
            }

            .calendar-actions a {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.75rem !important;
            }

            .calendar-actions i {
                font-size: 0.65rem !important;
            }

            .calendar-container {
                margin-top: 0.5rem !important;
                padding: 0.75rem !important;
            }

            .back-button {
                font-size: 0.7rem !important;
            }

            .back-button .back-icon {
                height: 1.5rem !important;
                width: 1.5rem !important;
            }

            .back-button .back-icon i {
                font-size: 0.65rem !important;
            }

            .mb-4 {
                margin-bottom: 0.5rem !important;
            }

            #calendarFrame {
                height: 550px !important;
            }
        }

        /* Extra small mobile screens */
        @media (max-width: 480px) {
            .calendar-header {
                font-size: 0.9rem !important;
            }

            .calendar-actions {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            .calendar-actions a {
                font-size: 0.65rem !important;
                padding: 0.35rem 0.65rem !important;
                width: 100%;
                text-align: center;
            }

            .calendar-container {
                padding: 0.65rem !important;
            }

            .back-button span:not(.back-icon) {
                display: none !important;
            }

            #calendarFrame {
                height: 500px !important;
            }
        }

        /* Desktop styles */
        @media (min-width: 641px) {
            .calendar-container {
                margin-top: 2.5rem !important;
            }
        }
    </style>
</head>
<body class="bg-blue-50">

    <!-- Navigation -->
    <?php include '../includes/lupon_head_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>

    <!-- Calendar Container -->
    <div class="calendar-container container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="calendar-header text-2xl font-semibold text-blue-800">Hearing Calendar</h2>

            <button onclick="window.history.back()" class="back-button flex items-center space-x-2 text-blue-600 hover:text-blue-800 transition-colors">
                <span class="back-icon inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back</span>
            </button>
        </div>

        <!-- <div class="calendar-actions mb-4 flex justify-between">
            <div>
                <a href="appoint_hearing.php" class="bg-green-500 text-white p-2 px-4 rounded-lg hover:bg-green-600">
                    <i class="fas fa-plus"></i> Schedule New Hearing
                </a>
            </div>
        </div> -->
        <!-- Display CalendarLuponHead -->
        <iframe id="calendarFrame" src="../SecMenu/schedule/CalendarLuponHead.php" style="width:100%; height:800px; border:none;" title="Lupon Head Hearing Calendar"></iframe>
    </div>

        <!-- Parent modal to show schedule details (renders on top of iframe) -->
        <div id="eventModal" class="fixed inset-0 hidden flex items-center justify-center z-50">
            <div class="absolute inset-0 bg-black opacity-40"></div>
            <div class="relative bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 p-6 z-60" style="border:1px solid #e5e7eb;">
                <button id="modalClose" class="absolute top-3 right-3 text-gray-600 hover:text-red-600 bg-white rounded-full w-8 h-8 flex items-center justify-center" aria-label="Close">&times;</button>
                <h3 id="modalTitle" class="text-lg font-semibold mb-2 text-blue-700 border-b border-gray-100 pb-2"></h3>
                <div id="modalContent" class="text-sm text-gray-700 space-y-3 max-h-[60vh] overflow-y-auto"></div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button id="modalCloseBtn" class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Close</button>
                </div>
            </div>
        </div>

        <script>
            // Listen for openEventModal messages from the calendar iframe and show a parent modal
            window.addEventListener('message', function(event) {
                try {
                    if (!event.data) return;
                    if (event.data.type === 'openEventModal') {
                        var payload = event.data.payload || {};
                        var modal = document.getElementById('eventModal');
                        var title = document.getElementById('modalTitle');
                        var content = document.getElementById('modalContent');
                        var remarksEl = document.getElementById('modalRemarksText');
                        var luponEl = document.getElementById('modalLuponName');

                        if (title) title.textContent = payload.title || 'Schedule Details';
                        if (content) content.innerHTML = payload.content || '';
                        try {
                            var luponName = payload.lupon || payload.lupon_assigned || payload.luponAssigned || payload.lupon_tagapamayapa || '';
                            if (luponEl) luponEl.textContent = luponName ? luponName : 'Not Yet Assigned';
                        } catch (e) { /* noop */ }

                        // If the iframe provided an eventId, fetch authoritative remarks and lupon from server
                        (async function(){
                            try {
                                var evtId = payload.eventId || payload.id || payload.scheduleId || payload.event_id || null;
                                if (!evtId && payload.eventId === 0) evtId = 0;
                                if (evtId) {
                                    var res = await fetch('../SecMenu/schedule/get_schedule_details.php?id=' + encodeURIComponent(evtId), { credentials: 'same-origin' });
                                    if (res && res.ok) {
                                        var data = await res.json();
                                        if (data && data.success) {
                                            try {
                                                if (remarksEl) {
                                                    var txt = data.remarks || '';
                                                    function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
                                                    remarksEl.innerHTML = txt ? escapeHtml(txt).replace(/\n/g, '<br>') : 'No remarks';
                                                }
                                                if (data.lupon) {
                                                    if (luponEl) luponEl.textContent = data.lupon;
                                                }
                                            } catch (e) { /* noop */ }
                                        }
                                    }
                                }
                            } catch (err) { /* ignore fetch errors */ }
                        })();

                        // Show modal
                        if (modal) modal.classList.remove('hidden');

                        // Send ACK back to iframe so it won't show its local modal
                        try {
                            var rid = payload.requestId;
                            if (rid && event.source && typeof event.source.postMessage === 'function') {
                                event.source.postMessage({ type: 'openEventModalAck', requestId: rid, eventId: payload.eventId || null }, event.origin || '*');
                            }
                        } catch (err) { /* ignore */ }
                    }
                } catch (err) { /* ignore malformed message */ }
            });

            // Close button handlers
            document.getElementById('modalClose')?.addEventListener('click', function(){ document.getElementById('eventModal')?.classList.add('hidden'); });
            document.getElementById('modalCloseBtn')?.addEventListener('click', function(){ document.getElementById('eventModal')?.classList.add('hidden'); });

            // Close when clicking outside the modal card
            document.addEventListener('click', function(e){
                var modal = document.getElementById('eventModal');
                if (!modal || modal.classList.contains('hidden')) return;
                var card = modal.querySelector('div.relative');
                if (card && !card.contains(e.target) && e.target === modal) modal.classList.add('hidden');
            });

            // Close on Escape
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { var m = document.getElementById('eventModal'); if (m) m.classList.add('hidden'); } });
        </script>
    <?php include '../chatbot/bpamis_case_assistant.php'?>

</body>
</html>
