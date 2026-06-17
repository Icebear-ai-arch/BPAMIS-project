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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Hearing Calendar</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <!-- Font Awesome for Icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

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
        /* Parent-level modal (shows above iframe) - styled to match CalendarCaptain modal UI */
        #parentEventModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.45);
            z-index: 2147483646; /* very high but below browser UI */
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #parentEventModal.open { display: flex; }
        #parentEventModal .modal-card {
            position: relative;
            z-index: 2147483647;
            max-width: 900px;
            width: 100%;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.13);
            border: 1.5px solid #e0e7ef;
            backdrop-filter: blur(8px);
            overflow: hidden;
            max-height: 86vh;
        }

        /* Decorative gradient bar like CalendarCaptain */
        #parentEventModal .modal-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0281d4, #0ea5e9, #0281d4);
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }
        @keyframes gradientShift { 0%,100% { background-position:0% 50% } 50% { background-position:100% 50% } }

        #parentEventModalTitle { color:#0281d4; font-weight:700; font-size:1.3rem; margin-top:.5rem; }
        #parentEventModalContent { max-height:70vh; overflow-y:auto; padding-right:.5rem; }
        #parentEventModalContent::-webkit-scrollbar { width:6px; }
        #parentEventModalContent::-webkit-scrollbar-track { background:#f1f5f9; border-radius:3px; }
        #parentEventModalContent::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }
        #parentEventModalContent::-webkit-scrollbar-thumb:hover { background:#94a3b8; }

        #parentEventModal .close-btn{
            position: absolute; top: 10px; right: 10px; background: transparent; border: none; font-size: 1.1rem; cursor: pointer;
        }
        /* Mobile: make modal compact and compressed for small screens */
        @media (max-width: 640px) {
            #parentEventModal { padding: 0.5rem; }
            #parentEventModal .modal-card {
                border-radius: 14px;
                max-width: 560px;
                width: calc(100% - 1rem);
                padding: 0.6rem 0.75rem;
                max-height: 92vh;
                box-shadow: 0 8px 22px rgba(31,38,135,0.10);
            }
            #parentEventModalTitle { font-size: 1rem; margin-top: .25rem; }
            #parentEventModalContent { max-height: 66vh; padding-right: 0.25rem; font-size: 0.96rem; }
            #parentEventModal .close-btn{ top: 8px; right: 8px; font-size: 1rem; }
        }
    </style>
</head>
<body class="bg-blue-50">

    <!-- Navigation -->
    <?php include '../includes/barangay_official_cap_nav.php'; ?>

    <!-- Calendar Container -->
    <div class="container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <h2 class="text-2xl font-semibold text-blue-800 mb-4">Hearing Calendar</h2>
        <div class="mb-4 flex justify-end">
            <a href="appoint_hearing.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white shadow text-sm font-medium transition">
                <i class="fa fa-calendar-plus"></i> Schedule Hearing
            </a>
        </div>
        
        <iframe id="calendarFrame" src="../SecMenu/schedule/CalendarCaptain.php" style="width:100%; height:800px; border:none;"></iframe>
    </div>
    
        <!-- Parent-level modal: shown when iframe requests it (so it appears above the iframe) -->
        <div id="parentEventModal" role="dialog" aria-modal="true">
            <div class="modal-card">
                <button class="close-btn" id="parentEventModalClose" aria-label="Close">&times;</button>
                <h3 id="parentEventModalTitle" style="color:#0369a1; font-weight:700; margin-bottom:.5rem;"></h3>
                <div id="parentEventModalContent"></div>
            </div>
        </div>

        <script>
            // Expose a function for same-origin iframe calls
            window.openEventModal = function(payload){
                try{
                    var modal = document.getElementById('parentEventModal');
                    var title = document.getElementById('parentEventModalTitle');
                    var content = document.getElementById('parentEventModalContent');
                    title.innerHTML = payload && payload.title ? payload.title : 'Details';
                    content.innerHTML = payload && payload.content ? payload.content : '';
                    modal.classList.add('open');
                    // trap focus briefly for accessibility
                    document.getElementById('parentEventModalClose').focus();
                }catch(e){ console.error('openEventModal error', e); }
            };

            // Listen for postMessage from iframe
            window.addEventListener('message', function(e){
                if (!e || !e.data) return;
                var msg = e.data;
                if (msg && msg.type === 'openEventModal' && msg.payload){
                    window.openEventModal(msg.payload);
                }
            }, false);

            // Close handlers
            document.getElementById('parentEventModalClose').addEventListener('click', function(){
                document.getElementById('parentEventModal').classList.remove('open');
            });
            document.getElementById('parentEventModal').addEventListener('click', function(e){
                if (e.target === this) this.classList.remove('open');
            });
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') document.getElementById('parentEventModal').classList.remove('open'); });
        </script>
    <?php include '../chatbot/bpamis_case_assistant.php'?>
    <?php include 'sidebar_.php';?>
</body>
</html>
