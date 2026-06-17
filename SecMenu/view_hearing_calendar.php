
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
                margin-top: 0 !important;
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
        }

        /* Desktop styles */
        @media (min-width: 641px) {
            .calendar-container {
                margin-top: 2.5rem !important;
            }
        }

        /* Event Modal Styles */
        .event-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .event-modal.active {
            display: flex;
        }

        .event-modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        /* Modal scrollbar styling */
        #eventDetailsContent::-webkit-scrollbar {
            width: 6px;
        }

        #eventDetailsContent::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        #eventDetailsContent::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        #eventDetailsContent::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body class="bg-blue-50">

    <!-- Navigation -->
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Calendar Container -->
    <div class="calendar-container container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="calendar-header text-2xl font-semibold text-blue-800">Hearing Calendar</h2>

            <button onclick="window.history.back()" class="back-button flex items-center space-x-2 text-blue-600 hover:text-blue-800 transition-colors">
                <span class="back-icon inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back</span>
            </button>
        </div>
        
        <div class="calendar-actions mb-4 flex justify-between">
            <div>
                <a href="appoint_hearing.php" class="bg-green-500 text-white p-2 px-4 rounded-lg hover:bg-green-600">
                    <i class="fas fa-plus"></i> Schedule New Hearing
                </a>
            </div>
            <div>
                <a href="reschedule_hearing.php" class="bg-yellow-500 text-white p-2 px-4 rounded-lg hover:bg-yellow-600">
                    <i class="fas fa-calendar-alt"></i> Reschedule Hearing
                </a>
            </div>
        </div>
        
        <iframe id="calendarFrame" src="./schedule/CalendarSec.php" style="width:100%; height:800px; border:none;"></iframe>
    </div>

    <!-- Event Details Modal (Outside container) -->
    <div id="eventModal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 transform transition-all duration-300 border border-gray-200 sm:p-6 p-4" style="z-index: 100000 !important;">
            <button id="modalClose"
                class="absolute top-3 right-3 sm:top-4 sm:right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-base sm:text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform"
                onclick="closeEventModal();">&times;</button>
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-sky-400 to-blue-500 rounded-t-2xl"></div>
            <h3 id="modalTitle" class="text-base sm:text-xl font-semibold mb-3 sm:mb-4 text-blue-700 border-b border-gray-200 pb-2 mt-2">Event Details</h3>
            <div id="eventDetailsContent" class="text-xs sm:text-sm text-gray-700 space-y-2 sm:space-y-3 max-h-[70vh] overflow-y-auto">
                <!-- Event details will be populated here -->
            </div>
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
                    onclick="closeEventModal();">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Listen for messages from iframe. Pass event.source/origin so we can ACK back to the sender.
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'openEventModal' && event.data.payload) {
                openEventModal(event.data.payload, event.source, event.origin);
            }
        });

        function openEventModal(payload, srcWindow, srcOrigin) {
            const modal = document.getElementById('eventModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('eventDetailsContent');
            const rescheduleBtn = document.getElementById('reschedule');
            const deleteBtn = document.getElementById('delete');
            
            // Set title
            title.textContent = payload.title || 'Event Details';
            
            // Use the HTML content sent from the calendar
            content.innerHTML = payload.content || '<p class="text-gray-500">No details available.</p>';
            
            // Set event ID for reschedule and delete buttons
            if (payload.eventId) {
                rescheduleBtn.setAttribute('data-id', payload.eventId);
                deleteBtn.setAttribute('data-id', payload.eventId);
                // If payload includes the case id, store it on the reschedule button so we can pass it when redirecting
                if (payload.caseId) {
                    rescheduleBtn.setAttribute('data-case', payload.caseId);
                    deleteBtn.setAttribute('data-case', payload.caseId);
                }
            }
            
            // Show modal
            modal.classList.remove('hidden');

            // If the payload included a requestId, ACK back to the iframe so it won't open its local modal.
            try {
                if (payload && payload.requestId) {
                    var ack = { type: 'openEventModalAck', requestId: payload.requestId, eventId: payload.eventId };
                    // If we have the source window from the message event, use it.
                    if (srcWindow && typeof srcWindow.postMessage === 'function') {
                        try { srcWindow.postMessage(ack, srcOrigin || '*'); } catch (_) {}
                    } else {
                        // Fallback: broadcast to all child iframes (covers the direct same-origin call path where
                        // the iframe invoked window.parent.openEventModal(payload) and we don't have an event.source).
                        var iframes = document.querySelectorAll('iframe');
                        for (var i = 0; i < iframes.length; i++) {
                            try { iframes[i].contentWindow.postMessage(ack, '*'); } catch (_) {}
                        }
                    }
                }
            } catch (err) { /* noop */ }
        }

        function closeEventModal() {
            const modal = document.getElementById('eventModal');
            modal.classList.add('hidden');
        }

        // Close modal when clicking backdrop
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventModal();
            }
        });

        // Reschedule button handler
        document.getElementById('reschedule').addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            const caseId = this.getAttribute('data-case');
            if (eventId) {
                // Include case_id if available so the reschedule page knows which case is selected
                // `reschedule_hearing.php` expects `id` as the hearing identifier (see home-secretary usage)
                const url = caseId ? `reschedule_hearing.php?id=${eventId}&case_id=${caseId}` : `reschedule_hearing.php?id=${eventId}`;
                window.location.href = url;
            }
        });

        // Delete button handler
        document.getElementById('delete').addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            if (eventId && confirm('Are you sure you want to delete this hearing?')) {
                // Add your delete logic here
                console.log('Delete event:', eventId);
                // You can implement the delete functionality or redirect to a delete handler
            }
        });
    </script>

    <?php include '../chatbot/bpamis_case_assistant.php'?>
    <?php include 'sidebar_.php';?>
</body>
</html>