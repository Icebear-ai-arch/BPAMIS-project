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
    
<style>
    .fa-chevron-down {
        transition: transform 0.3s ease;
    }
    .submenu {
        transition: max-height 0.3s ease, opacity 0.3s ease;
    }
    /* Sidebar logo sizing and responsive adjustments */
    #sidebar .sidebar-logo {
        width: 50px;
        height: 50px;
        margin-right: 0.75rem;
        object-fit: contain;
    }
    /* Consistent sizing and box model to avoid page-to-page inconsistencies */
    #sidebar { box-sizing: border-box; --sidebar-width: 18rem; width: var(--sidebar-width); }

    /* Backdrop for mobile slide-over */
    #sidebar-backdrop { display: none; }

    /* Compact logo for small screens */
    @media (max-width: 640px) {
        /* On small screens show a larger, scrollable slide-over sidebar so labels remain visible
           Use a percentage with a max to avoid overflowing very small/large screens */
        #sidebar {
            width: min(80%, 320px) !important;
            padding: 0.75rem !important;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.15);
            max-height: 100vh;
            overflow-y: auto;
        }
        #sidebar .sidebar-logo {
            width: 40px;
            height: 40px;
            margin-right: 0.5rem;
        }
        #sidebar h2 {
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #sidebar .flex.items-center {
            gap: 0.5rem;
        }
        /* Slightly reduce item paddings so long labels wrap gracefully */
        #sidebar a, #sidebar button.toggle-menu {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            font-size: 0.95rem !important;
        }
        /* Ensure submenu indentation doesn't push content off-screen */
        #sidebar .submenu { padding-left: 1.5rem !important; }
    }
</style>

<div id="sidebar" class="fixed left-0 top-0 w-72 h-full bg-white shadow-lg p-5 transform -translate-x-full transition-transform duration-300 z-50 overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <a href="home-captain.php">
                    <img src="../Assets/img/logo.png" alt="BPAMIS Logo" class="sidebar-logo mr-3">
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
                    <p class="text-sm font-medium">Barangay Official - Captain</p>
                    <p class="text-xs text-gray-500">Adjudication Panel</p>
                </div>
            </div>
        </div>
        
        <nav>
            <ul class="space-y-1">
                <li>
                    <a href="home-captain.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
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
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_complaints.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Complaints</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-clipboard-list w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Blotter Case</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_blotter.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Blotter Case</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-folder w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Cases</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_cases.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Cases</a></li>
 
                        <!-- <li><a href="assign_case.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Assign Case</a></li> -->
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Schedule</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_hearing_calendar_captain.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Calendar</a></li>
                        <li><a href="appoint_hearing.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Schedule Hearing</a></li>
                    </ul>
                </li>
                <li class="pt-2">
                    <p class="px-4 py-1 text-xs font-medium text-gray-400 uppercase tracking-wider">Reports & Forms</p>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-chart-bar w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Reports</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="view_complaints_report_captain.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Complaints Report</a></li>
                        <li><a href="view_case_reports_captain.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Case Reports</a></li>
                    </ul>
                </li>
                
                
            </ul>
        </nav>
    </div>
</div>

<!-- Backdrop for mobile sidebar -->
<div id="sidebar-backdrop" class="hidden fixed inset-0 bg-black/40 z-40"></div>

<script>
    // Sidebar toggle functionality (consistent across pages)
    document.addEventListener('DOMContentLoaded', function() {
        // Submenu toggles
        document.querySelectorAll('.toggle-menu').forEach(button => {
            button.addEventListener('click', function() {
                const submenu = this.nextElementSibling;
                const chevron = this.querySelector('.fa-chevron-down');
                if (submenu && submenu.classList.contains('submenu')) {
                    submenu.classList.toggle('hidden');
                    if (chevron) chevron.classList.toggle('rotate-180');
                    this.classList.toggle('bg-primary-50');
                    this.classList.toggle('text-primary-700');
                }
            });
        });

        const menuBtn = document.getElementById('menu-btn');
        const closeBtn = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');

        function showSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('-translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
            if (backdrop) backdrop.classList.add('block');
            document.body.style.overflow = 'hidden';
        }

        function hideSidebar() {
            if (!sidebar) return;
            sidebar.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
            if (backdrop) backdrop.classList.remove('block');
            document.body.style.overflow = '';
        }

        if (menuBtn) menuBtn.addEventListener('click', function(e) { e.stopPropagation(); showSidebar(); });
        if (closeBtn) closeBtn.addEventListener('click', function(e) { e.stopPropagation(); hideSidebar(); });
        if (backdrop) backdrop.addEventListener('click', hideSidebar);

        // Close sidebar on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideSidebar();
        });

        // Clicking outside: only close if sidebar is open
        document.addEventListener('click', function(e) {
            if (!sidebar) return;
            if (sidebar.classList.contains('-translate-x-full')) return; // already hidden
            if (!sidebar.contains(e.target) && !e.target.closest('#menu-btn')) {
                hideSidebar();
            }
        });

        // On resize, ensure backdrop is removed if viewport is wide
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                if (backdrop) { backdrop.classList.add('hidden'); backdrop.classList.remove('block'); }
                document.body.style.overflow = '';
            }
        });
    });
</script>
