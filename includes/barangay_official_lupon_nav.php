<?php
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/db_compat.php';

// Ensure session is started (in case parent didn't start it)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$notif_count = 0; // default

if (!empty($_SESSION['official_id'])) {
    $luponId = (int)$_SESSION['official_id'];

    $T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
    $T_NOTIFICATIONS_TRASH = bpamis_table($conn, 'notifications_trash');
    $TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
    $TB_NOTIFICATIONS_TRASH = bpamis_quote_table($T_NOTIFICATIONS_TRASH);

        $sql = "SELECT COUNT(*) AS count 
            FROM {$TB_NOTIFICATIONS} n
            LEFT JOIN {$TB_NOTIFICATIONS_TRASH} t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
            WHERE n.is_read = 0 
              AND n.lupon_id = ? 
              AND n.type IN ('Case', 'Hearing')
              AND t.notification_id IS NULL";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $luponId);
        $stmt->execute();
        $rows = bpamis_stmt_fetch_all_assoc($stmt);
        if (!empty($rows)) {
            $notif_count = (int)($rows[0]['count'] ?? 0);
        }
        $stmt->close();
    } else {
        // Fallback: if prepare fails, try a direct (still filtered) query using the lupon id
        $lid = (int)$luponId;
        $fallbackSql = "SELECT COUNT(*) AS count 
                        FROM {$TB_NOTIFICATIONS} n
                        LEFT JOIN {$TB_NOTIFICATIONS_TRASH} t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
                        WHERE n.is_read = 0 AND n.lupon_id = $lid AND n.type IN ('Case','Hearing') AND t.notification_id IS NULL";
        if ($res = $conn->query($fallbackSql)) {
            if ($row = $res->fetch_assoc()) {
                $notif_count = (int)$row['count'];
            }
        }
    }
}
?>

<!-- Navigation Bar -->
<nav class="bg-white/95 backdrop-blur-md border-b border-blue-50 py-3 px-5 shadow-sm flex justify-between items-center sticky top-0 z-50">
    <div class="flex items-center space-x-3">
        <button id="menu-btn" class="text-blue-600 text-xl focus:outline-none hover:bg-blue-50 p-2 rounded-full transition-all duration-300">
            <i class="fas fa-bars"></i>
        </button>
    <a href="home-lupon.php"><img src="../SecMenu/logo.png" alt="Logo" class="w-10 h-10 rounded-full border-2 border-blue-200 drop-shadow-lg"></a>
        <div class="flex flex-col">
            <h1 class="text-xl font-bold text-blue-700">BPAMIS</h1>
            <p class="text-xs text-gray-500 leading-tight hidden sm:block">Barangay Panducot Adjudication Managment Information System</p>
        </div>
        
    </div>    
    <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2">
        <ul class="flex items-center space-x-6">
            <li>
                <a href="home-lupon.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Home">
                    <i class="fas fa-home text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Home</span>
                </a>
            </li>
            <li>
                <a href="feedback_lupon.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Write Feedback">
                    <i class="fas fa-plus-circle text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Write Feedback</span>
                </a>
            </li>
            <li>
                <a href="assigned_case.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="View Assigned Cases">
                    <i class="fas fa-gavel text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">View Assigned Cases</span>
                </a>
            </li>
            <li>
                <a href="view_hearing_calendar_lupon.php" class="tooltip relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Schedule Hearing">
                    <i class="fas fa-calendar-alt text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
                    <span class="tooltip-text absolute -bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Hearings</span>
                </a>
            </li>
        </ul>    </div>
    
    <div class="flex items-center space-x-5">
        <!-- Notifications -->
        <div class="tooltip relative group">
            <a href="notifications-lupon.php" class="relative group flex items-center justify-center p-3 rounded-full hover:bg-blue-50 transition-all duration-300" data-tooltip="Notifications">
                <div class="relative">
                    <i class="fas fa-bell text-blue-500 text-lg group-hover:scale-125 group-hover:rotate-6 transition-all duration-300"></i>
    <?php // Render badge hidden initially; client will fetch authoritative count ?>
    <span id="notif-count-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full h-4 w-4 flex items-center justify-center shadow-sm" aria-hidden="true"></span>
    <script>(function(){try{var b=document.getElementById('notif-count-badge'); if(b){ b.classList.add('hidden'); b.textContent=''; }}catch(e){} })();</script>
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
                    <p class="text-sm font-medium text-gray-800">Lupon Tagapamayapa</p>
                </div>
                <a href="profilelupon.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 transition-colors">
                    <i class="fas fa-user-circle mr-3 text-blue-500"></i>
                    <span>Your Profile</span>
                </a>
                
                <div class="border-t border-gray-100 my-1"></div>
                <a href="../controllers/logoutdb.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-red-50 group/logout transition-colors logout-link">
                    <i class="fas fa-sign-out-alt mr-3 text-red-500 group-hover/logout:translate-x-1 transition-transform"></i>
                    <span class="group-hover/logout:text-red-600 transition-colors">Sign out</span>
                </a>
            </div>        </div>
        
       
    </div>
</nav>



<style>
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
    // Ensure favicon is set to SecMenu/logo.png on Lupon pages
    (function setFavicon(){
        try {
            var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
            link.type = 'image/png';
            link.rel = 'icon';
            link.href = '../SecMenu/logo.png';
            document.head.appendChild(link);
        } catch(e) {}
    })();
// Logout confirmation modal (reusable) - Premium Enhanced Design
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
        const newConfirm = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
                newConfirm.addEventListener('click', function(){
                    // Broadcast logout to all other tabs first, then proceed
                    try{ localStorage.setItem('bpamis-logout', Date.now().toString()); }catch(e){}
                    try{ localStorage.removeItem('bpamis_auth'); }catch(e){}
                    try{ if('BroadcastChannel' in window){ new BroadcastChannel('bpamis-channel').postMessage('logout'); new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); } }catch(e){}
                    // Now proceed with server logout
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

<script>
    // Toggle mobile menu with animation
    document.addEventListener('DOMContentLoaded', function() {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        menuButton.addEventListener('click', function() {
            // Toggle active class on button for animation
            this.classList.toggle('active');
            
            // Toggle mobile menu visibility with transform
            if (mobileMenu.style.transform === 'translateY(0%)' || mobileMenu.style.transform === '') {
                mobileMenu.style.transform = 'translateY(-100%)';
            } else {
                mobileMenu.style.transform = 'translateY(0%)';
            }
        });
        
        // Toggle sidebar for desktop
        const menuBtn = document.getElementById('menu-btn');
        const sidebar = document.getElementById('sidebar');
        const closeSidebar = document.getElementById('close-sidebar');
        
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
            });
        }
        
        if (closeSidebar && sidebar) {
            closeSidebar.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
            });
        }
        
        // Toggle submenu items
        const toggleMenuButtons = document.querySelectorAll('.toggle-menu');
        if (toggleMenuButtons) {
            toggleMenuButtons.forEach(button => {
                button.addEventListener('click', function() {
                    let submenu = this.nextElementSibling;
                    submenu.classList.toggle('hidden');
                });
            });
        }
    });
</script>

<script>
    // Poll for new notifications (lupon) and update badge without page refresh
    (function(){
        const BADGE_ID = 'notif-count-badge';
        const ENDPOINT = '../controllers/notifications_summary.php';
        const POLL_INTERVAL = 8000; // ms

        function renderBadge(count){
            const el = document.getElementById(BADGE_ID);
            if (!el) return;
            if (!count || count <= 0) {
                el.classList.add('hidden');
                el.textContent = '';
            } else {
                el.classList.remove('hidden');
                el.textContent = (count > 99) ? '99+' : String(count);
            }
        }

        let last = null;

        function fetchCount(){
            fetch(ENDPOINT, { cache: 'no-store', credentials: 'same-origin' })
                .then(r => { if (!r.ok) throw new Error('Network'); return r.json(); })
                .then(data => {
                    const count = (data && typeof data.count === 'number') ? data.count : 0;
                    if (last === null || last !== count) {
                        last = count;
                        renderBadge(count);
                    }
                })
                .catch(err => {
                    // ignore network errors; will retry
                });
        }

        document.addEventListener('DOMContentLoaded', function(){
            try { fetchCount(); } catch(e){}
            setInterval(fetchCount, POLL_INTERVAL);
            // Cross-tab notification updates
            try{
                if ('BroadcastChannel' in window) {
                    const ch = new BroadcastChannel('bpamis-channel');
                    ch.onmessage = function(ev){ try{ var d = ev && ev.data ? ev.data : null; if(d && d.type === 'notif-updated' && (!d.role || d.role === 'lupon')) fetchCount(); }catch(e){} };
                }
            }catch(e){}
            window.addEventListener('storage', function(e){ try{ if(!e||!e.key) return; if(e.key==='bpamis-notif-updated'){ var v=null; try{ v=JSON.parse(e.newValue);}catch(_){}; if(v && (!v.role || v.role==='lupon')) fetchCount(); } }catch(e){} });
        });
    })();
</script>
<?php include_once(__DIR__ . '/push_client.php'); ?>