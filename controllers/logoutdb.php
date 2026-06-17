<?php
// controllers/logoutdb.php
// Comprehensive logout: destroy ALL possible role-based sessions and their cookies,
// then redirect to the public homepage so the user can log into another account.

$possible_names = [
    'BPAMIS_RESIDENT',
    'BPAMIS_EXTERNAL',
    'BPAMIS_SEC',
    'BPAMIS_OFFICIAL',
    'BPAMIS_LUPONHEAD',
    'BPAMIS_APP',
    'PHPSESSID' // legacy / default namespace
];

function bpamis_kill_session($name) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name($name);
    @session_start();
    $_SESSION = [];
    @session_unset();
    @session_destroy();
    // Attempt multiple path variants to ensure deletion. Use secure/httponly/SameSite when possible
    // Try a broader set of path variants to ensure cookie deletion across deployments
    $paths = ['/', '/BPAMIS', '/BPAMIS/', '/bpamis', '/bpamis/', '/bpamis_website', '/bpamis_website/'];
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $httponly = true;
    // If PHP supports options array (>=7.3) use it for SameSite
    $supports_options = (function_exists('phpversion') && version_compare(phpversion(), '7.3.0', '>='));
    // Derive domain without port if available
    $domain = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $domain = explode(':', $_SERVER['HTTP_HOST'])[0];
    }
    foreach ($paths as $p) {
        if ($supports_options) {
            $opts = ['expires' => time() - 42000, 'path' => $p, 'secure' => $secure, 'httponly' => $httponly, 'samesite' => 'Lax'];
            if (!empty($domain)) $opts['domain'] = $domain;
            setcookie($name, '', $opts);
        } else {
            // Best-effort fallback for older PHP versions
            if (!empty($domain)) {
                setcookie($name, '', time() - 42000, $p, $domain, $secure, $httponly);
                setcookie($name, '', time() - 42000, $p . '/', $domain, $secure, $httponly);
            } else {
                setcookie($name, '', time() - 42000, $p, '', $secure, $httponly);
                // Also try to set with leading slash variant
                setcookie($name, '', time() - 42000, $p . '/', '', $secure, $httponly);
            }
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentName = session_name();
if (!in_array($currentName, $possible_names, true)) {
    $possible_names[] = $currentName;
}

foreach (array_unique($possible_names) as $sname) {
    if (isset($_COOKIE[$sname])) {
        bpamis_kill_session($sname);
    } else {
        // Proactively attempt to kill even if cookie not visible in this path scope
        bpamis_kill_session($sname);
    }
}

// Extra defense: ensure current runtime session is gone
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    @session_unset();
    @session_destroy();
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_POST['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false));

// Ensure responses are not cached by intermediaries or the client
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Default redirect target (public homepage)
$target = '/BPAMIS/bpamis_website/bpamis.php?logged_out=1';
if (!empty($_GET['redirect'])) {
    $candidate = $_GET['redirect'];
    if (strpos($candidate, '/BPAMIS/') === 0) {
        $target = $candidate;
    }
}

// Broadcast logout intent to other tabs via a tiny HTML/JS shim if not AJAX
if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => $target]);
        exit;
}

echo "<!doctype html><html><head><meta charset='utf-8'><title>Logging out...</title></head><body>
<script>
 (function(){
  try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
  try { localStorage.setItem('bpamis-logout', Date.now().toString()); } catch(e) {}
  try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-out'}); } catch(e) {}
  try { const bc2 = new BroadcastChannel('bpamis-channel'); bc2.postMessage('logout'); } catch(e) {}
  // Defensive: clear any remaining session cookies client-side (best-effort)
  ['BPAMIS_RESIDENT','BPAMIS_EXTERNAL','BPAMIS_SEC','BPAMIS_OFFICIAL','BPAMIS_LUPONHEAD','BPAMIS_APP','PHPSESSID'].forEach(function(n){
      try {
          document.cookie = n + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
          document.cookie = n + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/BPAMIS';
          document.cookie = n + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/BPAMIS/';
      } catch(e) {}
  });

  // Resolve a working redirect target to avoid 404 across different base paths
  var initial = '{$target}';
  var candidates = [initial,
     '/bpamis_website/bpamis.php?logged_out=1',
     '/BPAMIS/bpamis_website/bpamis.php?logged_out=1',
     '/bpamis.php?logged_out=1',
     '/BPAMIS/bpamis.php?logged_out=1'
  ];
  var seen = {};
  candidates = candidates.filter(function(u){ if(seen[u]) return false; seen[u]=true; return true; });
  var i = 0;
  function tryNext(){
     if(i>=candidates.length){ window.location.replace(candidates[0]); return; }
     var url = candidates[i++];
     fetch(url, {method:'GET', cache:'no-store'})
       .then(function(r){ if(r.ok){ window.location.replace(url); } else { tryNext(); } })
       .catch(function(){ tryNext(); });
  }
  try { tryNext(); } catch(e) { window.location.replace(candidates[0]); }
 })();
</script>
</body></html>";
exit;
?>