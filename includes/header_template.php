<?php
/**
 * Header Template
 * Barangay Panducot Adjudication Management Information System
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'BPAMIS'; ?></title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .progress-bar {
            transition: width 1s ease-in-out;
        }
        .stat-card {
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* Modern Calendar Styles */
        .calendar-container {
            --fc-border-color: #f0f0f0;
            --fc-daygrid-event-dot-width: 6px;
            --fc-event-border-radius: 6px;
            --fc-small-font-size: 0.75rem;
        }
        
        .calendar-container .fc-theme-standard th {
            padding: 12px 0;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #6b7280;
            border: none;
        }
        
        .calendar-container .fc-theme-standard td {
            border-color: #f5f5f5;
        }
        
        .calendar-container .fc-col-header-cell {
            background: transparent;
        }
        
        .calendar-container .fc-toolbar-title {
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .calendar-container .fc-button {
            box-shadow: none !important;
            padding: 0.5rem 0.75rem;
            border-radius: 6px !important;
            font-weight: 500;
            transition: all 0.2s ease;
            text-transform: capitalize;
            border: 1px solid #e5e7eb !important;
        }
        
        .calendar-container .fc-button-primary {
            background-color: white !important;
            color: #4b5563 !important;
        }
    </style>
<script>
// Cross-tab logout handling for BPAMIS
(function(){
    function showLogoutMessageAndRedirect(){
        try{
            // reuse existing message UI if present; otherwise create a simple modal
            var existing = document.getElementById('bpamis-logout-overlay');
            if(existing) return;
            var overlay = document.createElement('div');
            overlay.id = 'bpamis-logout-overlay';
            overlay.style.position = 'fixed';
            overlay.style.left = '0'; overlay.style.top = '0';
            overlay.style.width = '100%'; overlay.style.height = '100%';
            overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
            overlay.style.background = 'rgba(0,0,0,0.4)'; overlay.style.zIndex = '99999';

            var box = document.createElement('div');
            box.style.background = '#fff'; box.style.padding = '24px'; box.style.borderRadius = '8px';
            box.style.maxWidth = '520px'; box.style.boxShadow = '0 8px 24px rgba(0,0,0,0.2)';

            var msg = document.createElement('p');
            msg.style.fontSize = '15px'; msg.style.color = '#6b7280'; msg.style.lineHeight = '1.6';
            msg.style.margin = '0 0 24px 0';
            msg.textContent = 'You were logged out due to inactivity for security purposes. Please log in again to continue.';

            var ok = document.createElement('button');
            ok.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;">Got It</span>';
            ok.style.padding = '8px 12px'; ok.style.border = '1px solid #ddd'; ok.style.background = '#f9fafb'; ok.style.cursor = 'pointer';
            ok.onclick = function(){
                // Broadcast logout to all other tabs before redirecting
                try{ localStorage.setItem('bpamis-logout', Date.now().toString()); }catch(e){}
                try{ localStorage.removeItem('bpamis_auth'); }catch(e){}
                try{ if('BroadcastChannel' in window){ new BroadcastChannel('bpamis-channel').postMessage('logout'); new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); } }catch(e){}
                try{ localStorage.removeItem('bpamis-logout'); }catch(e){}
                window.location.href = '/BPAMIS/bpamis_website/bpamis.php';
            };

            // Auto-redirect after a short delay so tabs that receive the logout
            // event will immediately navigate away without requiring a click.
            setTimeout(function(){
                try{ localStorage.removeItem('bpamis-logout'); }catch(e){}
                window.location.replace('/BPAMIS/bpamis_website/bpamis.php');
            }, 1200);

            box.appendChild(msg);
            box.appendChild(ok);
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }catch(e){
            // fallback: just redirect
            window.location.href = '/BPAMIS/bpamis_website/bpamis.php';
        }
    }

        // Listen for storage events from other tabs (compatibility with existing session_control keys)
        window.addEventListener('storage', function(e){
            if(!e) return;
            // support both the old key used in session_control ('bpamis_auth') and our 'bpamis-logout'
            if(e.key === 'bpamis-logout' || e.key === 'bpamis_auth'){
                showLogoutMessageAndRedirect();
            }
        });

    // Make function globally accessible so polling script can use it
    window.showLogoutMessageAndRedirect = showLogoutMessageAndRedirect;

    // Optionally listen to BroadcastChannel if supported (faster)
        try{
            if('BroadcastChannel' in window){
                var ch = new BroadcastChannel('bpamis-channel');
                ch.onmessage = function(ev){ if(ev && ev.data === 'logout') showLogoutMessageAndRedirect(); };
                // Also listen to existing channel used by session_control for backward compatibility
                try{
                    var ch2 = new BroadcastChannel('bpamis-auth');
                    ch2.onmessage = function(ev){ if(ev && ev.data && (ev.data.type === 'logged-out' || ev.data === 'logout')) showLogoutMessageAndRedirect(); };
                }catch(e){}
            }
        }catch(e){}

    // Helper used by logout links
    window.broadcastLogoutAndGo = function(logoutUrl){
            try{ localStorage.setItem('bpamis-logout', Date.now().toString()); }catch(e){}
            // Also remove/set the legacy bpamis_auth key and broadcast to legacy channel so session_control handlers fire
            try{ localStorage.removeItem('bpamis_auth'); }catch(e){}
            try{ if('BroadcastChannel' in window){ new BroadcastChannel('bpamis-channel').postMessage('logout'); new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); } }catch(e){}
        // navigate to server logout endpoint
        window.location.href = logoutUrl;
    };

    // Attach handler to existing logout links (class "logout-link") after DOM ready
    function attachLogoutLinkHandlers(){
        var links = document.querySelectorAll('a.logout-link');
        links.forEach(function(a){
            // avoid double-binding
            if(a.dataset.bpamisLogoutBound) return;
            a.dataset.bpamisLogoutBound = '1';
            a.addEventListener('click', function(ev){
                // if it's a normal link to controllers/logoutdb.php, intercept and broadcast
                var href = a.getAttribute('href') || '../controllers/logoutdb.php';
                if(href.indexOf('logoutdb.php') !== -1){
                    ev.preventDefault();
                    window.broadcastLogoutAndGo(href);
                }
            });
        });
    }

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attachLogoutLinkHandlers);
    else attachLogoutLinkHandlers();

    // Delegate clicks for any anchor that points to logoutdb.php (covers anchors without the class)
    // Attach immediately (not inside DOMContentLoaded) so early clicks are caught
    document.addEventListener('click', function(ev){
        try{
            var el = ev.target;
            // Walk up to find the anchor
            while(el && el.tagName !== 'A') el = el.parentElement;
            if(!el || !el.href) return;
            var href = el.getAttribute('href');
            if(href && href.indexOf('logoutdb.php') !== -1){
                // Intercept and broadcast logout so other tabs react
                ev.preventDefault();
                ev.stopPropagation();
                window.broadcastLogoutAndGo(href);
            }
        }catch(e){}
    }, true);

    // Intercept form submissions targeting logoutdb.php (if any)
    document.addEventListener('submit', function(ev){
        try{
            var form = ev.target;
            if(!form || !form.action) return;
            if(form.action.indexOf('logoutdb.php') !== -1){
                // Let the form submit normally but broadcast logout immediately so other tabs are notified
                try{ localStorage.setItem('bpamis-logout', Date.now().toString()); }catch(e){}
                try{ localStorage.removeItem('bpamis_auth'); }catch(e){}
                try{ if('BroadcastChannel' in window){ new BroadcastChannel('bpamis-channel').postMessage('logout'); new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); } }catch(e){}
                // allow normal submit to proceed
            }
        }catch(e){}
    }, true);

})();
</script>
</head>
<script>
// Periodic session-check to detect server-side session invalidation (polling fallback)
(function(){
    // Candidate paths to session_check.php to handle various include depths
    const candidates = [
        '../controllers/session_check.php',
        '../../controllers/session_check.php',
        '/controllers/session_check.php',
        '/BPAMIS/controllers/session_check.php'
    ];

    function tryCandidates(cb){
        let i = 0;
        function next(){
            if(i >= candidates.length) return cb(candidates[0]);
            const url = candidates[i++];
            fetch(url, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
                .then(r => { if(r.ok) cb(url); else next(); })
                .catch(()=> next());
        }
        next();
    }

    function checkSession(sessionUrl){
        fetch(sessionUrl, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
            .then(r => r.json().catch(()=>({logged_in:false})))
            .then(data => {
                if(!data || data.logged_in === false){
                    // Trigger the same UI as cross-tab logout
                    try { localStorage.setItem('bpamis-logout', Date.now().toString()); } catch(e) {}
                    try { if('BroadcastChannel' in window) new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); } catch(e) {}
                    // Show modal/redirect
                    try { if(typeof showLogoutMessageAndRedirect === 'function') showLogoutMessageAndRedirect(); else window.location.replace('/BPAMIS/bpamis_website/bpamis.php'); } catch(e){ window.location.replace('/BPAMIS/bpamis_website/bpamis.php'); }
                }
            }).catch(()=>{});
    }

    // Resolve a working session_check endpoint then poll every 30s
    tryCandidates(function(resolved){
        if(!resolved) return;
        // initial check on load
        checkSession(resolved);
        // poll interval
        setInterval(function(){ checkSession(resolved); }, 30000);
    });
})();
</script>
</head>
<body class="bg-gray-50 font-sans">
    <?php include '../includes/barangay_official_nav.php'; ?>
