<?php
include '../server/server.php'; // Ensure connection is available

$notif_count = 0;
// Prefer secretary-specific unread flag if present
$hasSecCol = false;
if ($res = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read_secretary'")) {
    $hasSecCol = $res->num_rows > 0;
}
$sqlNotif = $hasSecCol
    ? "SELECT COUNT(*) AS count FROM notifications WHERE is_read_secretary = 0"
    : "SELECT COUNT(*) AS count FROM notifications WHERE is_read = 0";
$resultNotif = $conn->query($sqlNotif);
if ($resultNotif && $row = $resultNotif->fetch_assoc()) {
    $notif_count = (int)$row['count'];
}
?>

<!-- Navigation Bar -->
<nav class="relative bg-white/95 backdrop-blur-md border-b border-blue-50 py-3 px-5 shadow-sm flex justify-between items-center sticky top-0 z-50">
    <div class="flex items-center space-x-3">
        <button id="menu-btn" class="text-blue-600 text-xl focus:outline-none hover:bg-blue-50 p-2 rounded-full transition-all duration-300">
            <i class="fas fa-bars"></i>
        </button>
    <a href="../SecMenu/home-secretary.php"><img src="../SecMenu/logo.png" alt="Logo" class="w-10 h-10 rounded-full border-2 border-blue-200 drop-shadow-lg"></a>
        <div class="flex flex-col">
            <h1 class="text-xl font-bold text-blue-700">BPAMIS</h1>
            <p class="text-xs text-gray-500 leading-tight hidden sm:block">Barangay Panducot Adjudication Managment Information System</p>
        </div>
    </div>    
    <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2">
        <ul class="flex items-center justify-center space-x-6">
            <li>
                <a href="home-secretary.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Home">
                    <i class="fas fa-home text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Home</span>
                </a>
            </li>
            <li>
                <a href="add_complaints.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Add Complaint">
                    <i class="fas fa-plus-circle text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Add Complaint</span>
                </a>
            </li>
            <li>
                <a href="view_complaints.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="View Complaints">
                    <i class="fas fa-clipboard-list text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">View Complaints</span>
                </a>
            </li>
            <li>
                <a href="view_cases.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="View Cases">
                    <i class="fas fa-gavel text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">View Cases</span>
                </a>
            </li>
            
            <li>
                <a href="appoint_hearing.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Schedule Hearing">
                    <i class="fas fa-calendar-alt text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Schedule Hearing</span>
                </a>
            </li>
           
        </ul>    </div>
    
    <div class="flex items-center space-x-5">
        <!-- Notifications -->
        <div class="tooltip relative group">
            <a href="notifications-secretary.php" class="relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Notifications">
                <div class="relative">
                    <i class="fas fa-bell text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <?php if ($notif_count > 0): ?>
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center shadow-sm">
                <?= $notif_count ?>
            </span>
                    <?php endif; ?>
                </div>
                <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Notifications</span>
            </a>
        </div>
        
        <!-- User Account Menu -->
        <div class="relative group">
            <button class="flex items-center space-x-2 text-gray-700 hover:text-blue-600 transition-colors duration-300 rounded-full px-3 py-1 hover:bg-blue-50">
                <div class="flex items-center justify-center rounded-full overflow-hidden border-2 border-blue-100 group-hover:border-blue-300 transition-colors">
                    <img src="../Assets/Img/secretary.gif" alt="User" class="w-8 h-8 rounded-full object-cover">
                </div>
                <span class="hidden md:inline text-sm font-medium">Barangay Official</span>
                <i class="fas fa-chevron-down text-xs opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
            </button>
            <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg py-2 border border-gray-100 invisible opacity-0 translate-y-1 group-hover:visible group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 ease-in-out">
                <div class="px-4 py-2 border-b border-gray-100">
                    <p class="text-xs text-gray-500">Signed in as</p>
                    <p class="text-sm font-medium text-gray-800">Barangay Secretary</p>
                </div>
                <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 transition-colors">
                    <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                    <span>Your Profile</span>
                </a>
                
                <div class="border-t border-gray-100 my-1"></div>
                <a href="../controllers/logoutdb.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-red-50 group/logout transition-colors logout-link">
                    <i class="fas fa-sign-out-alt mr-3 text-red-500 group-hover/logout:translate-x-1 transition-transform"></i>
                    <span class="group-hover/logout:text-red-600 transition-colors">Sign out</span>
                </a>
            </div>        </div>
        
        <!-- Mobile menu button with animation -->
    </div>
</nav>

<script>
    // Ensure favicon uses SecMenu/logo.png on all secretary pages
    (function setFavicon(){
        try {
            var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
            link.type = 'image/png';
            link.rel = 'icon';
            // From SecMenu pages, this relative path resolves to /BPAMIS/SecMenu/logo.png
            link.href = '../SecMenu/logo.png';
            document.head.appendChild(link);
        } catch(e) { /* no-op */ }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar logic (existing)
        const menuButton = document.getElementById('menu-btn');
        const sidebar = document.getElementById('sidebar');
        if (menuButton && sidebar) {
            menuButton.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
            });
        }
        document.addEventListener('click', function(event) {
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        // User Account Menu Toggle: Desktop (hover), Mobile (click)
        const userMenuWrappers = document.querySelectorAll('.flex.items-center.space-x-5 .relative.group');
        const userMenuWrapper = userMenuWrappers[userMenuWrappers.length - 1]; // Get the last one (user menu)
        const userMenuBtn = userMenuWrapper ? userMenuWrapper.querySelector('button') : null;
        const userMenuDropdown = userMenuBtn ? userMenuBtn.nextElementSibling : null;
        let userMenuOpen = false;

        function isMobile() {
            return window.innerWidth < 768;
        }

        function openUserMenu() {
            if (userMenuDropdown) {
                userMenuDropdown.classList.remove('invisible', 'opacity-0', 'translate-y-1');
                userMenuDropdown.classList.add('visible', 'opacity-100', 'translate-y-0');
                userMenuOpen = true;
            }
        }
        function closeUserMenu() {
            if (userMenuDropdown) {
                userMenuDropdown.classList.add('invisible', 'opacity-0', 'translate-y-1');
                userMenuDropdown.classList.remove('visible', 'opacity-100', 'translate-y-0');
                userMenuOpen = false;
            }
        }

        if (userMenuBtn && userMenuDropdown) {
            // Mobile: click to toggle
            userMenuBtn.addEventListener('click', function(e) {
                if (isMobile()) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (userMenuOpen) {
                        closeUserMenu();
                    } else {
                        openUserMenu();
                    }
                }
            });
            
            // Mobile: close on menu item click
            userMenuDropdown.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (isMobile()) closeUserMenu();
                });
            });
            
            // Mobile: close on outside click
            document.addEventListener('click', function(e) {
                if (isMobile() && userMenuOpen) {
                    if (!userMenuWrapper.contains(e.target)) {
                        closeUserMenu();
                    }
                }
            });
            
            // On resize, always close menu if switching to desktop
            window.addEventListener('resize', function() {
                if (!isMobile()) closeUserMenu();
            });
        }
    });
</script>

<style>

#mobile-menu::-webkit-scrollbar {
  display: none;
}
#mobile-menu {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
    /* Tooltip styles */
    .tooltip-text {
        z-index: 100;
        pointer-events: none;
    }
    
    /* Hover float effect */
    .hover-float:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes wiggle {
      0%, 100% { transform: rotate(0); }
      25% { transform: rotate(-15deg); }
      50% { transform: rotate(0); }
      75% { transform: rotate(15deg); }
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
    }
    
    .animate-wiggle:hover {
      animation: wiggle 0.4s ease-in-out 2;
    }
    
    .hover-float:hover {
      animation: float 2s ease-in-out infinite;
    }
    
    .hover-pulse:hover {
      animation: pulse 1s ease-in-out infinite;
    }
</style>
<script>
// Logout confirmation modal for sign-out links - Premium Enhanced Design
(function(){
    function createLogoutModal(){
        if (document.getElementById('logout-modal')) return;
        
        // Add premium styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInModal {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideUpModal {
                from { opacity: 0; transform: translateY(20px) scale(0.95); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            @keyframes slideDownModal {
                from { opacity: 1; transform: translateY(0) scale(1); }
                to { opacity: 0; transform: translateY(20px) scale(0.95); }
            }
            @keyframes fadeOutModal {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            .logout-modal-icon {
                width: 64px;
                height: 64px;
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                box-shadow: 0 8px 16px rgba(251, 191, 36, 0.3);
            }
            .logout-modal-icon svg {
                width: 32px;
                height: 32px;
                color: #d97706;
            }
            .logout-modal-btn {
                font-size: 14px;
                font-weight: 600;
                padding: 12px 24px;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                border: none;
                outline: none;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .logout-modal-btn:active {
                transform: scale(0.98);
            }
            .logout-cancel-btn {
                background: #ffffff;
                color: #374151;
                border: 1.5px solid #e5e7eb;
                margin-right: 12px;
            }
            .logout-cancel-btn:hover {
                background: #f9fafb;
                border-color: #d1d5db;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            .logout-confirm-btn {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            }
            .logout-confirm-btn:hover {
                box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
                transform: translateY(-1px);
            }
            @media (max-width: 480px) {
                .logout-modal-icon {
                    width: 56px;
                    height: 56px;
                    margin-bottom: 16px;
                }
                .logout-modal-icon svg {
                    width: 28px;
                    height: 28px;
                }
                .logout-modal-btn {
                    font-size: 13px;
                    padding: 10px 20px;
                }
            }
        `;
        document.head.appendChild(style);
        
        const overlay = document.createElement('div');
        overlay.id = 'logout-modal';
        overlay.style.position = 'fixed';
        overlay.style.inset = '0';
        overlay.style.background = 'rgba(0, 0, 0, 0.5)';
        overlay.style.backdropFilter = 'blur(4px)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '100000';
        overlay.style.animation = 'fadeInModal 0.3s ease-out';
        overlay.innerHTML = `
            <div style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); padding: 32px 28px; border-radius: 16px; max-width: 90vw; width: 420px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05); border: 1px solid rgba(148, 163, 184, 0.2); animation: slideUpModal 0.4s ease-out; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-align: center;">
                <div class="logout-modal-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 style="margin: 0 0 12px 0; font-size: 22px; color: #111827; font-weight: 700; letter-spacing: -0.02em;">Sign Out?</h3>
                <p style="margin: 0 0 24px 0; color: #6b7280; font-size: 15px; line-height: 1.6;">You will be returned to the BPAMIS public homepage. Any unsaved changes will be lost.</p>
                <div style="display: flex; justify-content: center; gap: 12px;">
                    <button id="logout-cancel" class="logout-modal-btn logout-cancel-btn">Cancel</button>
                    <button id="logout-confirm" class="logout-modal-btn logout-confirm-btn">
                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg style="width: 16px; height: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sign Out
                        </span>
                    </button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        document.getElementById('logout-cancel').addEventListener('click', function(){ 
            const modalCard = overlay.querySelector('div');
            modalCard.style.animation = 'slideDownModal 0.3s ease-out';
            overlay.style.animation = 'fadeOutModal 0.3s ease-out';
            setTimeout(function(){ overlay.style.display = 'none'; }, 300);
        });
        // confirm handler will be attached dynamically when shown
    }

    function showLogoutConfirm(href){
        createLogoutModal();
        const overlay = document.getElementById('logout-modal');
        overlay.style.display = 'flex';
        overlay.style.animation = 'fadeInModal 0.3s ease-out';
        const modalCard = overlay.querySelector('div');
        modalCard.style.animation = 'slideUpModal 0.4s ease-out';
    const confirmBtn = document.getElementById('logout-confirm');
        const cancelBtn = document.getElementById('logout-cancel');
        // remove previous listeners by cloning
        const newConfirm = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
                newConfirm.addEventListener('click', function(){
                        fetch(href, { method:'POST', credentials:'same-origin' })
                            .then(r=> r.ok ? r.json() : null)
                            .then(data => { window.location.href = (data && data.redirect) ? data.redirect : '../bpamis_website/bpamis.php?logged_out=1'; })
                            .catch(()=>{ window.location.href = '../bpamis_website/bpamis.php?logged_out=1'; });
                });
        cancelBtn.addEventListener('click', function(){ 
            modalCard.style.animation = 'slideDownModal 0.3s ease-out';
            overlay.style.animation = 'fadeOutModal 0.3s ease-out';
            setTimeout(function(){ overlay.style.display = 'none'; }, 300);
        });
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('a.logout-link').forEach(function(a){
            a.addEventListener('click', function(e){
                e.preventDefault();
                const href = a.getAttribute('href') || '../controllers/logoutdb.php';
                showLogoutConfirm(href);
            });
        });
    });
})();
</script>
<?php include_once(__DIR__ . '/push_client.php'); ?>