<?php
// session_control.php
// Usage: include 'session_control.php'; at TOP of each protected page

// // Adopt an active, VALID role-based session if present; ignore and purge stale cookies.
// if (session_status() === PHP_SESSION_NONE) {
//     $names = ['BPAMIS_RESIDENT','BPAMIS_EXTERNAL','BPAMIS_SEC','BPAMIS_OFFICIAL','BPAMIS_LUPONHEAD','BPAMIS_APP'];
//     $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
//     $picked = null;
//     foreach ($names as $n) {
//         if (empty($_COOKIE[$n])) continue;
//         if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
//         @session_name($n);
//         @session_start();
//         $role = strtolower($_SESSION['role'] ?? '');
//         $auth = !empty($_SESSION['AUTH_VERIFIED']);
//         $valid = false;
//         if ($role === 'resident') {
//             $valid = $auth && !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
//         } elseif ($role === 'external') {
//             $valid = $auth && !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
//         } elseif ($role === 'official') {
//             $valid = $auth && !empty($_SESSION['official_id']) && !empty($_SESSION['official_position']);
//         } else {
//             $valid = $auth && (!empty($_SESSION['user_id']) || !empty($_SESSION['official_id']));
//         }
//         if ($valid) { $picked = $n; break; }
//         // Purge stale cookie session
//         $_SESSION = [];
//         @session_unset();
//         @session_destroy();
//         foreach (['/','/BPAMIS','/BPAMIS/'] as $p) { @setcookie($n, '', time()-3600, $p, '', $secure, true); }
//         if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
//     }
//     if (!$picked) {
//         // Start a default anonymous session
//         @session_start();
//     }
// }

// ==============================
// CONFIGURABLE SETTINGS
// ==============================
// Server-side inactivity limit (seconds). If you don't want the server to
// automatically destroy sessions right now, comment the line below.
// $inactive_limit = 10 ; // 20 minutes in seconds (server-side enforcement)
$warning_limit  = 300 ; // show warning after 300 seconds (handled by JS)
// After the warning is shown, wait this many seconds then auto-logout and redirect
$post_warning_logout_seconds = 30; // 30 seconds countdown after the warning

// // Force session regeneration every request to prevent tab reuse issues
// if (!isset($_SESSION['regenerated'])) {
//     session_regenerate_id(true);
//     $_SESSION['regenerated'] = time();
// }

// ==============================
// CHECK LOGIN
// ==============================
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in by looking for common session variables
$is_logged_in = (!empty($_SESSION['user_id']) || !empty($_SESSION['official_id']) || !empty($_SESSION['user']) || !empty($_SESSION['AUTH_VERIFIED']));

if (!$is_logged_in) {
    // Redirect to the centralized login page inside bpamis_website
    header('Location: /BPAMIS/bpamis_website/bpamis.php');
    exit();
}

// ==============================
// CHECK INACTIVITY AUTO LOGOUT
// ==============================
// The server-side timeout enforcement is optional. It will only run if
// $inactive_limit is defined and numeric. Comment out $inactive_limit above
// to disable server-side auto-logout temporarily.
if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];

    if (isset($inactive_limit) && is_numeric($inactive_limit) && $elapsed > (int)$inactive_limit) {
        // Kill ALL possible role-based sessions so user can re-login with any account
        $names = ['BPAMIS_RESIDENT','BPAMIS_EXTERNAL','BPAMIS_SEC','BPAMIS_OFFICIAL','BPAMIS_LUPONHEAD','BPAMIS_APP'];
        if (!in_array(session_name(), $names, true)) { $names[] = session_name(); }
        foreach (array_unique($names) as $n) {
            if (isset($_COOKIE[$n])) {
                if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
                session_name($n);
                @session_start();
                $_SESSION = [];
                @session_unset();
                @session_destroy();
                setcookie($n, '', time() - 42000, '/', '', false, true);
            }
        }
    // Notify other tabs about server-side timeout before redirecting.
    // Output a small HTML + JS payload (safe because we haven't sent any body yet).
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Session timed out</title></head><body>';
    echo '<script>';
    // remove legacy key and set our logout marker so other tabs receive storage event
    echo "try { localStorage.removeItem('bpamis_auth'); } catch(e) {}\n";
    echo "try { localStorage.setItem('bpamis-logout', Date.now().toString()); } catch(e) {}\n";
    // Broadcast across channels so both new and legacy listeners handle it
    echo "try { if ('BroadcastChannel' in window) { new BroadcastChannel('bpamis-auth').postMessage({type:'logged-out'}); new BroadcastChannel('bpamis-channel').postMessage('logout'); } } catch(e) {}\n";
    echo "window.location.href = '/BPAMIS/bpamis_website/bpamis.php?timeout=true';";
    echo '</script></body></html>';
    exit();
    }
}
$_SESSION['last_activity'] = time();

// Prepare optional cross-tab login seed info (so login.php in another tab can detect active session)
$__sc_is_logged = (!empty($_SESSION['user_id']) || !empty($_SESSION['official_id']) || !empty($_SESSION['user']));
$__sc_display = '';
$__sc_redirect = '/BPAMIS/bpamis_website/bpamis.php';
if ($__sc_is_logged) {
    $__sc_display = $_SESSION['username'] ?? $_SESSION['official_name'] ?? $_SESSION['user'] ?? '';
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role === 'resident') { $__sc_redirect = '/ResidentMenu/home-resident.php'; }
    elseif ($role === 'external') { $__sc_redirect = '/ExternalMenu/home-external.php'; }
    elseif ($role === 'official') {
        $pos = strtolower($_SESSION['official_position'] ?? '');
        if (strpos($pos, 'barangay secretary') !== false) $__sc_redirect = '/SecMenu/home-secretary.php';
        elseif (strpos($pos, 'lupon-hepe') !== false || strpos($pos, 'lupon head') !== false) $__sc_redirect = '/LuponHeadMenu/home-luponhead.php';
        elseif (strpos($pos, 'lupon tagapamayapa') !== false) $__sc_redirect = '/OfficialMenu/home-lupon.php';
        elseif (strpos($pos, 'barangay captain') !== false) $__sc_redirect = '/OfficialMenu/home-captain.php';
        else $__sc_redirect = '/SecMenu/home-secretary.php';
    }
}

// ==============================
// OPTIONAL: Prevent back navigation after logout
// ==============================
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ==============================
// IDLE TIMEOUT SCRIPT (buffered to avoid header issues)
// ==============================
// Store the script in a variable to output later, preventing "headers already sent" errors
$_sc_suppress = defined('SC_SUPPRESS_SCRIPT') ? (bool)constant('SC_SUPPRESS_SCRIPT') : false;
if (!$_sc_suppress) {
    ob_start();
?>

<script>
// Optional idle warning popup + post-warning auto-logout and redirect
let warningSeconds = <?php echo (int)$warning_limit; ?>;
let logoutSeconds  = <?php echo isset($inactive_limit) ? (int)$inactive_limit : 'Infinity'; ?>;
let postWarningSeconds = <?php echo (int)$post_warning_logout_seconds; ?>;
let idleTime = 0;
let postWarnTimer = null;
let postWarnRemaining = 0;
let warningShown = false;
let _sc_remoteLogoutHandled = false;
let _sc_forceLogoutStarted = false; // ensure we only trigger once

// Helper: try multiple candidate base paths to find a working bpamis.php for redirect.
// This avoids 404s when deployment path differs (e.g., Cloudflare tunnel rewrites).
function resolveBpamisRedirect(query){
    const candidates = [];
    // Relative first (from typical protected pages under root folders)
    candidates.push('../bpamis_website/bpamis.php' + query);
    candidates.push('../../bpamis_website/bpamis.php' + query); // in case we are one level deeper
    // Absolute variants
    candidates.push('/bpamis_website/bpamis.php' + query);
    candidates.push('/BPAMIS/bpamis_website/bpamis.php' + query);
    candidates.push('/bpamis.php' + query); // fallback to possible root landing
    candidates.push('/BPAMIS/bpamis.php' + query);
    // Deduplicate while preserving order
    const seen = new Set();
    const ordered = candidates.filter(c=>{ if(seen.has(c)) return false; seen.add(c); return true; });

    // Attempt a fast HEAD/GET fetch chain to find first 200; fallback immediately if fetch blocked.
    return new Promise(resolve => {
        let idx = 0;
        function next(){
            if(idx >= ordered.length){ resolve(ordered[0]); return; }
            const url = ordered[idx++];
            // Use GET with 'no-cache' to avoid cached 404s.
            fetch(url, {method:'GET', cache:'no-store'}).then(r => {
                if(r.ok){ resolve(url); } else { next(); }
            }).catch(()=> next());
        }
        // If fetch unavailable, just use first candidate.
        try { next(); } catch(e){ resolve(ordered[0]); }
    });
}

// Helper: resolve a working logout endpoint across possible base paths
function resolveLogoutEndpoint(){
    const candidates = [];
    // Relative first (most common when included from role folders)
    candidates.push('../controllers/logoutdb.php');
    candidates.push('../../controllers/logoutdb.php');
    // Absolute variants
    candidates.push('/controllers/logoutdb.php');
    candidates.push('/BPAMIS/controllers/logoutdb.php');
    candidates.push('/bpamis/controllers/logoutdb.php');
    // Deduplicate
    const seen = new Set();
    const ordered = candidates.filter(c=>{ if(seen.has(c)) return false; seen.add(c); return true; });

    return new Promise(resolve => {
        let idx = 0;
        function next(){
            if(idx >= ordered.length){ resolve(ordered[0]); return; }
            const url = ordered[idx++];
            fetch(url, { method:'HEAD', cache:'no-store' })
              .then(r => { if(r.ok){ resolve(url); } else { next(); } })
              .catch(()=> next());
        }
        try { next(); } catch(e) { resolve(ordered[0]); }
    });
}

// Create a simple warning element (hidden by default)
function createWarningElement(){
    if (document.getElementById('idle-warning')) return;
    
    // Add premium styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUpWarning {
            from { opacity: 0; transform: translate(-50%, -50%) translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translate(-50%, -50%) translateY(0) scale(1); }
        }
        @keyframes pulseCountdown {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .idle-warning-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 16px rgba(251, 191, 36, 0.3);
        }
        .idle-warning-icon svg {
            width: 32px;
            height: 32px;
            color: #ffffff;
        }
        .idle-countdown-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 4px 12px;
            border-radius: 20px;
            min-width: 32px;
            height: 32px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            animation: pulseCountdown 2s infinite;
        }
        .idle-warning-btn {
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
        .idle-warning-btn:active {
            transform: scale(0.98);
        }
        .idle-continue-btn {
            background: #ffffff;
            color: #374151;
            border: 1.5px solid #e5e7eb;
            margin-right: 12px;
        }
        .idle-continue-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .idle-logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .idle-logout-btn:hover {
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
            transform: translateY(-1px);
        }
        @media (max-width: 480px) {
            #idle-warning {
                width: 90vw !important;
                top: 50% !important;
                padding: 28px 24px !important;
            }
            .idle-warning-icon {
                width: 56px;
                height: 56px;
                margin-bottom: 16px;
            }
            .idle-warning-icon svg {
                width: 28px;
                height: 28px;
            }
            .idle-warning-btn {
                font-size: 13px;
                padding: 10px 20px;
            }
            .idle-countdown-badge {
                font-size: 14px;
                padding: 3px 10px;
                height: 28px;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Create backdrop overlay
    const backdrop = document.createElement('div');
    backdrop.id = 'idle-warning-backdrop';
    backdrop.style.position = 'fixed';
    backdrop.style.inset = '0';
    backdrop.style.background = 'rgba(0, 0, 0, 0.5)';
    backdrop.style.backdropFilter = 'blur(4px)';
    backdrop.style.zIndex = '99998';
    backdrop.style.display = 'none';
    backdrop.style.animation = 'fadeInBackdrop 0.3s ease-out';
    document.body.appendChild(backdrop);
    
    // Create modal
    const div = document.createElement('div');
    div.id = 'idle-warning';
    div.style.position = 'fixed';
    div.style.left = '50%';
    div.style.top = '50%';
    div.style.transform = 'translate(-50%, -50%)';
    div.style.zIndex = '99999';
    div.style.background = 'linear-gradient(135deg, #ffffff 0%, #f8fafc 100%)';
    div.style.border = '1px solid rgba(148, 163, 184, 0.2)';
    div.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05)';
    div.style.padding = '32px 28px';
    div.style.borderRadius = '16px';
    div.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    div.style.display = 'none';
    div.style.width = '420px';
    div.style.maxWidth = '90vw';
    div.style.textAlign = 'center';
    div.style.animation = 'slideUpWarning 0.4s ease-out';
    div.innerHTML = `
        <div class="idle-warning-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h3 style="font-weight: 700; margin: 0 0 12px 0; color: #111827; font-size: 22px; letter-spacing: -0.02em;">Inactivity Detected</h3>
        <p style="font-size: 15px; color: #6b7280; line-height: 1.6; margin: 0 0 20px 0;">
            You will be logged out automatically in <span class="idle-countdown-badge" id="idle-countdown">${postWarningSeconds}</span> seconds due to inactivity.
        </p>
        <div style="display: flex; justify-content: center; gap: 12px; margin-top: 24px;">
            <button id="idle-continue" class="idle-warning-btn idle-continue-btn">
                <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg style="width: 16px; height: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Stay Logged In
                </span>
            </button>
            <button id="idle-logout" class="idle-warning-btn idle-logout-btn">
                <span style="display: inline-flex; align-items: center; gap: 6px;">
                    <svg style="width: 16px; height: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout Now
                </span>
            </button>
        </div>
    `;
    document.body.appendChild(div);
    document.getElementById('idle-continue').addEventListener('click', () => {
        hideWarning(); resetIdle();
    });
    document.getElementById('idle-logout').addEventListener('click', () => {
        performLogout();
    });
}

function showWarning(){
    createWarningElement();
    const el = document.getElementById('idle-warning');
    const backdrop = document.getElementById('idle-warning-backdrop');
    if (!el) return;
    if (backdrop) backdrop.style.display = 'block';
    document.getElementById('idle-countdown').textContent = postWarningSeconds;
    el.style.display = 'block';
    warningShown = true;
    // start countdown
    postWarnRemaining = postWarningSeconds;
    postWarnTimer = setInterval(() => {
        // decrement first so we show 10,9,...,1 and then sign out (do not show 0)
        postWarnRemaining--;
        if (postWarnRemaining <= 0) {
            clearInterval(postWarnTimer); postWarnTimer = null;
            performLogout();
            return;
        }
        const cd = document.getElementById('idle-countdown'); if (cd) cd.textContent = postWarnRemaining;
    }, 1000);
}

function hideWarning(){
    const el = document.getElementById('idle-warning');
    const backdrop = document.getElementById('idle-warning-backdrop');
    if (el) el.style.display = 'none';
    if (backdrop) backdrop.style.display = 'none';
    warningShown = false;
    if (postWarnTimer) { clearInterval(postWarnTimer); postWarnTimer = null; }
}

function getBasePrefix(){
    try {
        const parts = (location.pathname || '').split('/').filter(Boolean);
        return parts.includes('BPAMIS') ? '/BPAMIS' : '';
    } catch(e) { return ''; }
}

function performLogout(){
    // Notify other tabs and call centralized logout endpoint.
    try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
    try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-out'}); } catch(e) {}
    resolveLogoutEndpoint().then((logoutUrl)=>{
        return fetch(logoutUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: 'ajax=1',
            credentials: 'same-origin'
        });
    })
    .then(r => r && r.ok ? r.json().catch(()=>null) : null)
    .then(data => {
        const fallback = '../bpamis_website/bpamis.php?logged_out=1';
        const target = (data && data.redirect) ? data.redirect : fallback;
        window.location.href = target;
    })
    .catch(()=>{
        window.location.href = '../bpamis_website/bpamis.php?logged_out=1';
    });
}

// Immediate forced logout when warning threshold is reached
function forceLogoutOnWarning(){
    if (_sc_forceLogoutStarted) return;
    _sc_forceLogoutStarted = true;
    // Best-effort: clear local markers and broadcast
    try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
    try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-out'}); } catch(e) {}
    // Call centralized logout to destroy ALL role-based sessions server-side,
    // then redirect explicitly to the requested page.
    resolveLogoutEndpoint().then((logoutUrl)=>{
        return fetch(logoutUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: 'ajax=1',
            credentials: 'same-origin'
        });
    })
    .then(r => r && r.ok ? r.json().catch(()=>null) : null)
    .then(data => {
        const fallback = '../bpamis_website/bpamis.php?logged_out=1';
        const target = (data && data.redirect) ? data.redirect : fallback;
        window.location.href = target;
    })
    .catch(()=>{
        window.location.href = '../bpamis_website/bpamis.php?logged_out=1';
    });
}

// Handle remote logout coming from another tab
function handleRemoteLogout(){
    if (_sc_remoteLogoutHandled) return;
    _sc_remoteLogoutHandled = true;
    try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
    // Use absolute path to avoid relative issues across nested folders
    resolveBpamisRedirect('?logged_out=1').then(u => { window.location.replace(u); });
}

// tick every second
setInterval(() => {
    idleTime++;

    // Show warning once when threshold is hit; we'll trigger server logout when countdown ends
    if (!warningShown && idleTime === warningSeconds) {
        showWarning();
    }

    if (idleTime >= logoutSeconds) {
        // server-side fallback: ensure logout if JS failed earlier
        performLogout();
    }
}, 1000);

// reset idle and cancel any running warning countdown
function resetIdle(){
    idleTime = 0;
    if (warningShown) hideWarning();
}

document.addEventListener('mousemove', resetIdle);
document.addEventListener('keydown', resetIdle);

// Listen for cross-tab logout broadcasts
try {
    const bc = new BroadcastChannel('bpamis-auth');
    bc.onmessage = (ev) => {
        if (ev && ev.data && ev.data.type === 'logged-out') {
            handleRemoteLogout();
        }
    };
} catch(e) {}

// Fallback: listen for localStorage changes (e.g., another tab cleared bpamis_auth)
window.addEventListener('storage', (e) => {
    try {
        if (e.key === 'bpamis_auth' && (e.newValue === null || e.newValue === '')) {
            handleRemoteLogout();
        }
    } catch(err) {}
});

// Seed cross-tab login status so newly opened login tabs can detect existing session
<?php if ($__sc_is_logged): ?>
try {
    localStorage.setItem('bpamis_auth', JSON.stringify({
        logged_in: true,
        redirect: '<?php echo $__sc_redirect; ?>',
        user: '<?php echo htmlspecialchars($__sc_display, ENT_QUOTES); ?>',
        ts: Date.now()
    }));
} catch(e) {}
try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-in', redirect:'<?php echo $__sc_redirect; ?>', user:'<?php echo htmlspecialchars($__sc_display, ENT_QUOTES); ?>'}); } catch(e) {}
<?php endif; ?>
</script>
<?php
    $GLOBALS['_session_control_script'] = ob_get_clean();

    // Auto-output the script at shutdown if not manually printed
    register_shutdown_function(function() {
        if (isset($GLOBALS['_session_control_script']) && !empty($GLOBALS['_session_control_script'])) {
            echo $GLOBALS['_session_control_script'];
            unset($GLOBALS['_session_control_script']);
        }
    });
}
?>