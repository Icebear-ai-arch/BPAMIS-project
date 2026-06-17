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
</style>

<div id="sidebar" class="fixed left-0 top-0 w-72 h-full bg-white shadow-lg p-5 transform -translate-x-full transition-transform duration-300 z-[100] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <a href="home-lupon.php">
                    <img src="../Assets/img/logo.png" alt="BPAMIS Logo" width="50" height="50" class="mr-3">
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
                    <p class="text-sm font-medium">Barangay Official - Lupon Tagapamayapa - Member</p>
                    <p class="text-xs text-gray-500">Adjudication Panel</p>
                </div>
            </div>
        </div>
        
        <nav>
            <ul class="space-y-1">
                <li>
                    <a href="home-lupon.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
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
                            <i class="fas fa-folder w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Assigned Cases</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">
                        <li><a href="assigned_case.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Assigned Cases</a></li>
                        
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

                        <li><a href="view_hearing_calendar_lupon.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">View Calendar</a></li>
                    </ul>
                </li>
                <li>
                    <button class="toggle-menu w-full flex items-center justify-between px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-lg transition group">
                        <div class="flex items-center">
                            <i class="fas fa-clipboard-list w-5 h-5 mr-3 text-gray-400 group-hover:text-primary-600"></i>
                            <span>Feedback</span>
                        </div>
                        <i class="fas fa-chevron-down text-sm text-gray-400"></i>
                    </button>                    <ul class="submenu hidden space-y-1 pl-12 mt-1">

                        <li><a href="feedback_lupon.php" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-700 hover:bg-primary-50 rounded-md transition">Add a Feedback</a></li>
                    </ul>
                </li>
   
            </ul>
        </nav>
        
    </div>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/30 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 z-[90]"></div>

<script>
    // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle submenu when button is clicked
        document.querySelectorAll('.toggle-menu').forEach(button => {
            button.addEventListener('click', function() {
                const submenu = this.nextElementSibling;
                const chevron = this.querySelector('.fa-chevron-down');
                
                if (submenu && submenu.classList.contains('submenu')) {
                    submenu.classList.toggle('hidden');
                    
                    if (chevron) {
                        chevron.classList.toggle('rotate-180');
                    }
                    
                    // Toggle active state on button
                    this.classList.toggle('bg-primary-50');
                    this.classList.toggle('text-primary-700');
                }
            });
        });

        // Sidebar open/close functionality
        const menuBtn = document.getElementById('menu-btn');
        const closeBtn = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('sidebar');
        
        const overlay = document.getElementById('sidebar-overlay');

        function openSidebar(){
            sidebar.classList.remove('-translate-x-full');
            if(overlay){
                overlay.classList.remove('pointer-events-none');
                overlay.classList.add('opacity-100');
            }
            document.documentElement.style.overflow='hidden';
            document.body.style.overflow='hidden';
        }
        function closeSidebar(){
            sidebar.classList.add('-translate-x-full');
            if(overlay){
                overlay.classList.add('pointer-events-none');
                overlay.classList.remove('opacity-100');
            }
            document.documentElement.style.overflow='';
            document.body.style.overflow='';
        }
        if (menuBtn && sidebar) menuBtn.addEventListener('click', e=>{ e.stopPropagation(); openSidebar(); });
        if (closeBtn && sidebar) closeBtn.addEventListener('click', e=>{ e.preventDefault(); closeSidebar(); });
        if (overlay) overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeSidebar(); });
        document.addEventListener('click', e=>{
            if(sidebar.classList.contains('-translate-x-full')) return;
            if(sidebar.contains(e.target)) return;
            if(menuBtn && menuBtn.contains(e.target)) return;
            closeSidebar();
        });
    });
</script>
