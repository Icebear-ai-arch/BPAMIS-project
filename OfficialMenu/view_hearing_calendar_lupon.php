<?php include '../controllers/session_control.php';?>
<?php include 'sidebar_lupon.php'; ?>
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
        
        /* Disable horizontal scroll on mobile */
        @media (max-width: 640px) {
            html, body { 
                overflow-x: hidden !important; 
                width: 100%; 
                height: 100%;
                overflow-y: auto !important;
            }
        }
    </style>
</head>
<body class="bg-blue-50">

    <!-- Navigation -->
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>
    <?php include 'sidebar_lupon.php'; ?>    
    <!-- Calendar Container -->
    <div class="container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base sm:text-xl md:text-2xl font-semibold text-blue-800">Hearing Calendar</h2>
            <a href="home-lupon.php" class="inline-flex items-center gap-1 sm:gap-2 px-2 py-1.5 sm:px-4 sm:py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200 shadow-sm hover:shadow-md text-xs sm:text-base">
                <i class="fas fa-arrow-left text-[10px] sm:text-sm"></i>
                <span class="font-medium">Back</span>
            </a>
        </div>
        
        <iframe id="calendarFrame" src="../SecMenu/schedule/CalendarLupon.php" style="width:100%; height:800px; border:none;"></iframe>
    </div>
    
    <!-- Event Details Modal (for mobile calendar clicks) -->
    <div id="lupon-event-modal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm" onclick="closeLuponEventModal()"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-sm sm:max-w-md mx-3 sm:mx-4 p-3 sm:p-6 transform transition-all duration-300 border border-gray-200" style="z-index: 100000 !important;">
            <button onclick="closeLuponEventModal()"
                class="absolute top-2 right-2 sm:top-4 sm:right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border border-gray-200 rounded-full w-6 h-6 sm:w-8 sm:h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-sm sm:text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform">&times;</button>
            <div class="absolute top-0 left-0 right-0 h-0.5 sm:h-1 bg-gradient-to-r from-blue-500 via-sky-400 to-blue-500 rounded-t-xl sm:rounded-t-2xl"></div>
            <h3 id="lupon-modal-title" class="text-sm sm:text-xl font-semibold mb-2 sm:mb-4 text-blue-700 border-b border-gray-200 pb-1.5 sm:pb-2 mt-1 sm:mt-2 pr-6 sm:pr-0"></h3>
            <div class="text-[11px] sm:text-sm text-gray-700 space-y-1.5 sm:space-y-3 max-h-[65vh] sm:max-h-[70vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-blue-50 to-sky-50 rounded-lg p-2 sm:p-4 border border-blue-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-hashtag text-blue-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-blue-700 uppercase tracking-wide">Case Number</span>
                    </div>
                    <div id="lupon-modal-case-id" class="text-base sm:text-2xl font-bold text-blue-900"></div>
                </div>
                <div class="bg-gradient-to-r from-gray-50 to-slate-50 rounded-lg p-2 sm:p-4 border border-gray-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-calendar-alt text-gray-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-gray-700 uppercase tracking-wide">Scheduled Date & Time</span>
                    </div>
                    <div id="lupon-modal-start" class="text-[11px] sm:text-base font-medium text-gray-900"></div>
                </div>
                <div class="bg-gradient-to-r from-white to-gray-50 rounded-lg p-2 sm:p-4 border border-gray-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-users text-gray-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-gray-700 uppercase tracking-wide">Lupon Assigned</span>
                    </div>
                    <div id="lupon-modal-lupon" class="text-[11px] sm:text-sm font-medium text-gray-900">Not Yet Assigned</div>
                </div>
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-lg p-2 sm:p-4 border border-emerald-100">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1 sm:mb-2">
                        <i class="fas fa-link text-emerald-600 text-[10px] sm:text-sm"></i>
                        <span class="text-[9px] sm:text-xs font-semibold text-emerald-700 uppercase tracking-wide">Actions</span>
                    </div>
                    <a id="lupon-modal-details-link" href="#" class="inline-flex items-center gap-1.5 sm:gap-2 text-emerald-700 hover:text-emerald-800 font-medium text-[11px] sm:text-sm underline decoration-2 underline-offset-2 transition-colors">
                        <i class="fas fa-external-link-alt text-[9px] sm:text-xs"></i>
                        <span>View Full Case Details</span>
                    </a>
                    <a id="lupon-modal-feedback-link" href="#" class="inline-flex items-center gap-1.5 sm:gap-2 mt-2 text-primary-700 hover:text-primary-800 font-medium text-[11px] sm:text-sm underline decoration-2 underline-offset-2 transition-colors">
                        <i class="fas fa-pen text-[9px] sm:text-xs"></i>
                        <span>Write Feedback</span>
                    </a>
                </div>
            </div>
            <div class="mt-2 sm:mt-4 flex items-center justify-end gap-1.5 sm:gap-2">
                <button onclick="closeLuponEventModal()" class="px-3 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-sm bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors font-medium">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Function to close lupon event modal
        function closeLuponEventModal() {
            const modal = document.getElementById('lupon-event-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        // Listen for messages from calendar iframe
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'showEventModal') {
                const data = event.data.data;
                
                // Populate modal with event data (title & start)
                document.getElementById('lupon-modal-title').textContent = data.title;
                document.getElementById('lupon-modal-start').textContent = data.start;

                // Resolve and display canonical case number (`case_original_id`) if available.
                (async function(){
                    const displayedEl = document.getElementById('lupon-modal-case-id');
                    const rawCaseId = data.caseId || data.case_id || data.id || '';
                    if (!rawCaseId) {
                        displayedEl.textContent = '';
                        return;
                    }

                    try {
                        const resp = await fetch('../controllers/get_case_original_id.php?case_id=' + encodeURIComponent(rawCaseId), { credentials: 'same-origin' });
                        if (resp.ok) {
                            const j = await resp.json();
                            if (j && j.success && j.original_id) {
                                displayedEl.textContent = j.original_id;
                                return;
                            }
                        }
                    } catch (e) {
                        // ignore and fall back
                    }

                    // Fallback to the provided id from the iframe
                    displayedEl.textContent = rawCaseId;
                })();
                // Populate Lupon assigned (if provided by iframe)
                try {
                    var luponText = data.lupon || data.lupon_assigned || data.luponAssigned || '';
                    if (luponText) document.getElementById('lupon-modal-lupon').textContent = luponText;
                } catch (e) { /* noop */ }
                // Prefer Lupon-specific details page: construct link to `view_case_details_lupon.php` using caseId.
                // Fallback to provided detailsLink if caseId is not present.
                (function() {
                    var caseId = data.caseId || data.case_id || data.id || '';
                    var detailsUrl = '';
                    if (caseId) {
                        detailsUrl = 'view_case_details_lupon.php?id=' + encodeURIComponent(caseId);
                    } else if (data.detailsLink) {
                        detailsUrl = data.detailsLink;
                    } else {
                        detailsUrl = '#';
                    }
                    document.getElementById('lupon-modal-details-link').href = detailsUrl;

                    var feedbackUrl = caseId ? ('feedback_lupon.php?id=' + encodeURIComponent(caseId)) : (data.feedbackLink || 'feedback_lupon.php');
                    try { document.getElementById('lupon-modal-feedback-link').href = feedbackUrl; } catch (e) {}
                })();
                
                // Show modal with smooth transition
                const modal = document.getElementById('lupon-event-modal');
                modal.classList.remove('hidden');
            }
        });

        // Close modal when clicking outside or pressing Escape
        document.getElementById('lupon-event-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLuponEventModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLuponEventModal();
            }
        });
        
        // Sidebar toggle functionality for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const menuBtn = document.getElementById('menu-btn');
            const sidebar = document.getElementById('sidebar');
            const closeSidebar = document.getElementById('close-sidebar');
            
            // Create overlay for sidebar
            function addSidebarOverlay() {
                if (!document.getElementById('sidebar-overlay')) {
                    const overlay = document.createElement('div');
                    overlay.id = 'sidebar-overlay';
                    overlay.className = 'fixed inset-0 bg-black bg-opacity-30 z-40';
                    document.body.appendChild(overlay);
                    overlay.addEventListener('click', () => {
                        sidebar.classList.add('-translate-x-full');
                        removeSidebarOverlay();
                    });
                }
            }
            
            function removeSidebarOverlay() {
                const overlay = document.getElementById('sidebar-overlay');
                if (overlay) overlay.remove();
            }
            
            // Open sidebar
            if (menuBtn && sidebar) {
                menuBtn.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                    addSidebarOverlay();
                });
            }
            
            // Close sidebar
            if (closeSidebar && sidebar) {
                closeSidebar.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    removeSidebarOverlay();
                });
            }
            
            // Toggle submenu items
            document.querySelectorAll('.toggle-menu').forEach(button => {
                button.addEventListener('click', function() {
                    let submenu = this.nextElementSibling;
                    submenu.classList.toggle('hidden');
                    
                    // Add/remove active class for animations
                    if (!submenu.classList.contains('hidden')) {
                        setTimeout(() => submenu.classList.add('active'), 10);
                    } else {
                        submenu.classList.remove('active');
                    }
                    
                    // Rotate chevron icon
                    const chevron = this.querySelector('.fa-chevron-down');
                    if (chevron) chevron.classList.toggle('rotate-180');
                    
                    // Toggle active background
                    this.classList.toggle('bg-primary-50');
                    this.classList.toggle('text-primary-700');
                });
            });
        });
    </script>
    
    <?php include '../chatbot/bpamis_case_assistant.php'?>
    
</body>
</html>
