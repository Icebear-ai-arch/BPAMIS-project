<?php
/**
 * Resident View Hearing Calendar (Full-page)
 * Embeds the resident-specific CalendarResident view.
 */
include '../controllers/session_control.php';
include '../server/server.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../bpamis_website/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Resident Hearing Calendar</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <style>
        body { background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
        .glass { backdrop-filter: blur(14px); background: linear-gradient(135deg, rgba(255,255,255,.65), rgba(255,255,255,.35)); border: 1px solid rgba(255,255,255,.45); box-shadow: 0 10px 40px -12px rgba(12,156,237,.25), 0 4px 18px -6px rgba(12,156,237,.18); }
        
        @media (max-width: 768px) {
            .max-w-7xl {
                margin-top: 5rem !important;
            }
        }

        /* Modal styling for hearing schedule */
        #eventModal #modalContent::-webkit-scrollbar {
            width: 6px;
        }

        #eventModal #modalContent::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        #eventModal #modalContent::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        #eventModal #modalContent::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile-optimized modal text sizes */
        @media (max-width: 640px) {
            #eventModal .relative {
                max-width: 95% !important;
                margin: 0 0.5rem !important;
                padding: 1rem !important;
            }

            #modalTitle {
                font-size: 1rem !important;
                margin-bottom: 0.75rem !important;
                padding-bottom: 0.5rem !important;
            }

            #modalContent {
                font-size: 0.75rem !important;
                line-height: 1.4 !important;
                max-height: 60vh !important;
            }

            #modalContent p {
                font-size: 0.75rem !important;
                margin-bottom: 0.5rem !important;
            }

            #modalContent strong {
                font-size: 0.75rem !important;
            }

            #modalContent span {
                font-size: 0.70rem !important;
            }

            #modalContent .text-xs {
                font-size: 0.65rem !important;
            }

            #modalContent .rounded-full {
                padding: 0.15rem 0.4rem !important;
                font-size: 0.65rem !important;
            }

            #modalClose {
                width: 28px !important;
                height: 28px !important;
                font-size: 1rem !important;
                top: 0.5rem !important;
                right: 0.5rem !important;
            }

            #eventModal .mt-4.text-right button {
                font-size: 0.75rem !important;
                padding: 0.4rem 0.75rem !important;
            }
        }
    </style>
</head>
<body class="font-sans text-gray-700">
    <?php include '../includes/resident_nav.php'; ?>

    <div class="max-w-7xl mx-auto px-5 py-8">
        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-sky-900 font-semibold tracking-tight flex items-center gap-2"><i class="fa-solid fa-calendar-days text-sky-600"></i> Hearing Calendar</h1>
                <a href="home-resident.php" class="inline-flex items-center gap-2 text-[12px] px-3 py-1.5 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
            <iframe src="../SecMenu/schedule/CalendarResident.php" class="w-full h-[660px] rounded-xl border border-white/50 bg-white/60 z-50"></iframe>
        </div>
    </div>

    <!-- Hearing Schedule Modal (outside iframe for mobile display) -->
    <div id="eventModal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99999 !important;">
        <div class="absolute inset-0 bg-black opacity-40 backdrop-blur-sm"></div>
        <div class="relative bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 transform transition-all duration-300 border border-gray-200">
            <button id="modalClose"
                class="absolute top-4 right-4 text-gray-600 hover:text-red-600 transition-colors duration-200 bg-white hover:bg-red-50 border-2 border-gray-200 rounded-full w-8 h-8 flex items-center justify-center backdrop-blur-sm shadow-lg text-lg font-bold cursor-pointer transform hover:scale-110 transition-transform"
                onclick="document.getElementById('eventModal').classList.add('hidden');">&times;</button>
            <h3 id="modalTitle" class="text-xl font-semibold mb-4 text-blue-700 border-b border-gray-200 pb-2"></h3>
            <div id="modalContent" class="text-sm text-gray-700 space-y-3 max-h-[70vh] overflow-y-auto pr-2"></div>
            <div class="mt-4 text-right">
                <button class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
                    onclick="document.getElementById('eventModal').classList.add('hidden');">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Listen for messages from the calendar iframe to show modal
        window.addEventListener('message', function(event) {
            // Basic security: check origin if needed (adjust based on your setup)
            // if (event.origin !== window.location.origin) return;
            
            if (event.data && event.data.type === 'showEventModal') {
                const modal = document.getElementById('eventModal');
                const title = document.getElementById('modalTitle');
                const content = document.getElementById('modalContent');
                
                if (!modal || !title || !content) return;
                
                title.textContent = event.data.title || 'Event Details';
                content.innerHTML = event.data.content || '';
                
                // Show modal with animation
                modal.classList.remove('hidden');
                const modalContent = modal.querySelector('div.relative');
                if (modalContent) {
                    modalContent.style.transform = 'scale(0.9)';
                    modalContent.style.opacity = '0';
                    setTimeout(() => {
                        modalContent.style.transform = 'scale(1)';
                        modalContent.style.opacity = '1';
                    }, 50);
                }
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            const modal = document.getElementById('eventModal');
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
            }
        });
    </script>

    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
