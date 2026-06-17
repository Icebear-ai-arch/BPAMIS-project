
<script src="https://cdn.tailwindcss.com"></script>
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
    
<div id="sidebar" class="fixed left-0 top-0 bottom-0 w-72 bg-white shadow-lg p-5 transform -translate-x-full transition-transform duration-300 z-[100] overflow-y-auto max-h-screen">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <a href="home-secretary.php">
                    <img src="logo.png" alt="BPAMIS Logo" width="50" height="50" class="mr-3">
                </a>
                <h2 class="text-lg font-bold text-primary-700">BPAMIS</h2>
            </div>
            <button id="close-sidebar" class="text-gray-500 hover:text-primary-600 text-xl p-2 rounded-full hover:bg-gray-100 transition focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="border-b border-gray-200 mb-4 pb-4">
            <div class="flex items-center">
                <div class="bg-primary-100 rounded-full p-3">
                    <i class="fas fa-user-shield text-primary-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium">Barangay Official - Secretary</p>
                    <p class="text-xs text-gray-500">Adjudication Panel</p>
                </div>
            </div>
        </div>
        
        <nav>
            <ul class="space-y-1">
                <li>
                    <a href="home-secretary.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <i class="fas fa-home w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="pt-2">
                    <p class="px-4 py-1 text-xs font-medium text-gray-400 uppercase tracking-wider">Case Management</p>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Complaints</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="add_complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Add Complaint</a></li>
                        <li><a href="view_complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Complaint</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-folder w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Cases</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Add Case</a></li>
                        <li><a href="view_cases.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Cases</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-clipboard-list w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Blotter</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_blotter.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Blotter Case</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Schedule</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="appoint_hearing.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Appoint Hearing</a></li>
                        <li><a href="reschedule_hearing.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Reschedule Hearing</a></li>
                        <li><a href="view_hearing_calendar.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Calendar</a></li>
                    </ul>
                </li>
                
                <li class="pt-2">
                    <p class="px-4 py-1 text-xs font-medium text-gray-400 uppercase tracking-wider">Additional Features</p>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-chart-bar w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Reports</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                    <li><a href="view_complaints_report.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Complaints Report</a></li>
                    <li><a href="view_case_reports.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Case Reports</a></li>
                    <li><a href="view_blotter_report.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Blotter Report</a></li>
                        <!-- <li><a href="dilg_report.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">DILG Report</a></li> -->
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-file w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>KP Forms</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <!-- <li><a href="view_kp_forms.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Templates</a></li> -->
                        <li><a href="print_kp_forms.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Print Form</a></li>
                    </ul>
                </li>
                <li class="pt-2">
                    <p class="px-4 py-1 text-xs font-medium text-gray-400 uppercase tracking-wider">Create Account</p>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-user-plus w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>View and Account Creation</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>
                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="add_external_user.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Create & View External Accounts</a></li>
                        <li><a href="add_official_account.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Create & View Official Account</a></li>
                        <li><a href="add_resident.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Create & View Resident Accounts</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
<!-- Overlay for sidebar (click to close) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 z-[90]"></div>
<script>
// Sidebar toggle for submenu
const toggles = document.querySelectorAll('#sidebar .toggle-menu');
toggles.forEach(btn => {
    btn.addEventListener('click', function() {
        // Find the next .submenu sibling
        const submenu = this.nextElementSibling;
        if (submenu && submenu.classList.contains('submenu')) {
            submenu.classList.toggle('hidden');
            // Optionally rotate chevron
            const chevron = this.querySelector('.fa-chevron-down');
            if (chevron) chevron.classList.toggle('rotate-180');
        }
    });
});
</script>
<script>
// Open/close sidebar behavior (mobile + desktop)
document.addEventListener('DOMContentLoaded', function(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const closeBtn = document.getElementById('close-sidebar');
    const menuBtn = document.getElementById('menu-btn');

    function openSidebar(){
        if (!sidebar) return;
        sidebar.classList.remove('-translate-x-full');
        if (overlay){
            overlay.classList.remove('pointer-events-none');
            overlay.classList.add('opacity-100');
        }
        // Prevent background scroll on mobile
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar(){
        if (!sidebar) return;
        sidebar.classList.add('-translate-x-full');
        if (overlay){
            overlay.classList.add('pointer-events-none');
            overlay.classList.remove('opacity-100');
        }
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
    }

    // Wire up buttons
    if (menuBtn){
        // Ensure overlay shows when opening via navbar button
        menuBtn.addEventListener('click', function(e){
            e.stopPropagation();
            openSidebar();
        });
    }
    if (closeBtn){
        closeBtn.addEventListener('click', function(e){
            e.preventDefault();
            closeSidebar();
        });
    }
    if (overlay){
        overlay.addEventListener('click', closeSidebar);
    }

    // Close on Esc
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeSidebar();
    });

    // Close when clicking outside the sidebar (but ignore clicks on the menu button)
    document.addEventListener('click', function(e){
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        if (!isOpen) return;
        if (sidebar.contains(e.target)) return;
        if (menuBtn && menuBtn.contains(e.target)) return;
        closeSidebar();
    });
});
</script>