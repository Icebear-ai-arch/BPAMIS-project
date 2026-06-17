<?php
// emits a JS snippet usable on any page path under /BPAMIS_01 (or /BPAMIS on ngrok)
// Start session if possible. If headers are already sent we skip starting to avoid PHP warnings
// (headers may be sent by templates/includes or accidental whitespace/BOM). Skipping here
// preserves existing behavior when a session is already active; it only avoids noisy
// warnings when starting sessions after output has begun.
if (session_status() === PHP_SESSION_NONE) {
  if (!headers_sent()) {
    session_start();
  } else {
    // Log for diagnostics; this indicates some output was sent before session start.
    error_log("push_client.php: session_start skipped because headers were already sent for " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
  }
}
$userKey = 'guest';
if (!empty($_SESSION['official_id'])) $userKey = 'official_' . (int)$_SESSION['official_id'];
if (!empty($_SESSION['user_id']))     $userKey = 'user_'     . (int)$_SESSION['user_id'];
?>
<script>
(function(){
  // Derive app root dynamically.
  // If the app is deployed under a folder (e.g. /BPAMIS/SecMenu/...), APP_ROOT = '/BPAMIS'.
  // If deployed at domain root (e.g. /SecMenu/...), APP_ROOT = '' (root).
  const pathParts = location.pathname.split('/').filter(Boolean);
  const KNOWN_TOP_LEVEL = new Set([
    'SecMenu','LuponHeadMenu','OfficialMenu','ResidentMenu','ExternalMenu',
    'controllers','includes','bpamis_website','Assets','assets','uploads','uploads_id',
    'vendor','src','server','sql','tools','chatbot','phpmailer','PhpSpreadsheet'
  ]);
  const firstSeg = pathParts.length ? pathParts[0] : '';
  const APP_ROOT = (firstSeg && !KNOWN_TOP_LEVEL.has(firstSeg)) ? ('/' + firstSeg) : '';

  function withRoot(p){
    // p must start with '/'
    return (APP_ROOT || '') + p;
  }

  // Register SW at root so scope can be APP_ROOT + '/'
  const SW_VERSION = 'v3'; // bump to invalidate cached worker & script when UI logic changes
  const SW_PATH  = withRoot('/sw.js?v=' + SW_VERSION);
  const SW_SCOPE = withRoot('/');

  const PUSH_URL = location.origin + withRoot('/controllers/push_notifications.php');
  const ICON_URL = withRoot('/SecMenu/logo.png');

  // Derive role hint from current path to help server route global official notifications
  let ROLE_HINT = '';
  try {
    const p = location.pathname;
    if (p.includes('/SecMenu/')) ROLE_HINT = 'secretary';
    else if (p.includes('/LuponHeadMenu/')) ROLE_HINT = 'luponhead';
    else if (p.includes('/OfficialMenu/')) ROLE_HINT = 'official';
    else if (p.includes('/ResidentMenu/')) ROLE_HINT = 'resident';
    else if (p.includes('/ExternalMenu/')) ROLE_HINT = 'external';
  } catch {}

  // Per-user de-dup keys
  const KEY_SUFFIX = '::<?php echo htmlspecialchars($userKey, ENT_QUOTES); ?>';
  const KEY_LAST_ID = 'bpamis_push_last_id' + KEY_SUFFIX;
  const KEY_SEEN    = 'bpamis_push_seen_set' + KEY_SUFFIX;

  // Register SW (root scope)
  if ('serviceWorker' in navigator) {
    const tryRoot = async () => {
      try {
        const reg = await navigator.serviceWorker.register(SW_PATH, { scope: SW_SCOPE });
        console.log('[push_client] SW registered', reg.scope);
        return true;
      } catch (err) {
        console.warn('[push_client] root SW failed, trying includes/sw.php', err);
        try {
          const incPath = withRoot('/includes/sw.php');
          const reg2 = await navigator.serviceWorker.register(incPath, { scope: SW_SCOPE });
          console.log('[push_client] SW registered via sw.php', reg2.scope);
          return true;
        } catch (e2) {
          console.error('[push_client] SW registration failed:', e2);
          return false;
        }
      }
    };
    tryRoot();
  } else {
    console.warn('[push_client] Service Worker not supported');
  }

  // Permission helpers
  async function ensureNotifPermission(){
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    try { const p = await Notification.requestPermission(); return p === 'granted'; } catch { return false; }
  }
  async function warmPermission(){
    try{
      const ok = await ensureNotifPermission();
      console.log('[push_client] notification permission:', Notification.permission, 'granted:', ok);
    }catch(e){ console.error('[push_client] permission error', e); }
  }
  setTimeout(()=> { if (window.Notification && Notification.permission !== 'granted') console.log('[push_client] Notification.permission =', Notification.permission); }, 500);

  // Resolve deep-link for notification click
  function resolveNotifUrl(id){
    const base = APP_ROOT || '';
    const p = location.pathname;
    if (p.includes('/ResidentMenu/'))  return base + '/ResidentMenu/view_notification.php?id='  + encodeURIComponent(id);
    if (p.includes('/ExternalMenu/'))  return base + '/ExternalMenu/view_notification.php?id='  + encodeURIComponent(id);
    if (p.includes('/LuponHeadMenu/')) return base + '/LuponHeadMenu/view_notification.php?id=' + encodeURIComponent(id);
    if (p.includes('/OfficialMenu/'))  {
      // Differentiate between captain and lupon pages
      if (p.includes('captain')) {
        return base + '/OfficialMenu/view_notification.php?id=' + encodeURIComponent(id);
      } else if (p.includes('lupon')) {
        return base + '/OfficialMenu/view_notification_lupon.php?id=' + encodeURIComponent(id);
      }
      // Fallback to generic official notification page
      return base + '/OfficialMenu/view_notification.php?id='  + encodeURIComponent(id);
    }
    if (p.includes('/SecMenu/'))       return base + '/SecMenu/view_notification.php?id='       + encodeURIComponent(id);
    return base + '/bpamis_website/login.php';
  }

  // Play notification sound
  function playNotificationSound(){
    try {
      const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBDGH0fPTgjMGHm7A7+OZRQ0PVKzn77BdGAg+ltrzxnMnBSh+zPLaizsIGGS56+idUBAKTKPh8bllHAU2j9bx0H0rBSd1xu7hlUUODlOr5vCtYBkIP5bZ88p1KAUnfMry2Ys7CBdju+vjnlERC0ug4PCzZhsENI/V8tGALgYocMLu45ZFDg5Tq+bwrmEaBECW2vLJdikEKHvJ8dmLOwgXY7nq5J9SDAo+n9/ws2YaBDOS1fLTgC8HKG/B7eSXRg4MUqvl8K9iGgVAl9nyynUrBSl6yPHYizsIF2K56+OgUQ0LP5/f8bNlGgU0kdX00oAuBydrwO3lmEcODFKq5PC');
      audio.volume = 0.3;
      audio.play().catch(e => console.log('[push_client] sound play failed (user interaction may be required)', e));
    } catch(e) {
      console.log('[push_client] sound creation failed', e);
    }
  }

  // Native notification (for background tab only)
  async function showNativeNotification(item){
    // Only show notification if tab is not visible
    if (!document.hidden) {
      console.log('[push_client] tab is active, playing sound instead of showing notification for:', item.title);
      playNotificationSound();
      return;
    }
    
    if (!('serviceWorker' in navigator)) return;
    const ok = await ensureNotifPermission();
    if (!ok) { console.warn('[push_client] notification permission not granted'); return; }
    try {
      const reg = await navigator.serviceWorker.ready;
      if (reg && reg.active) {
        // Single unified path: always message SW to display (no direct showNotification fallback)
        reg.active.postMessage({
          type: 'notify',
          payload: {
            id: item.id,
            title: item.title || 'Notification',
            message: item.message || '',
            body: item.message || '',
            url: resolveNotifUrl(item.id),
            icon: ICON_URL,
            tag: 'bpamis-' + item.id,
            data: { ts: Date.now(), id: item.id }
          }
        });
      }
    } catch (e) {
      console.error('[push_client] postMessage to SW failed', e);
    }
  }

  // Remove custom toast/beep: rely only on Service Worker native notifications

  // LocalStorage helpers
  function getLastId(){ return parseInt(localStorage.getItem(KEY_LAST_ID)||'0',10)||0; }
  function setLastId(v){ localStorage.setItem(KEY_LAST_ID, String(v||0)); }
  function getSeenSet(){ try{ return new Set(JSON.parse(localStorage.getItem(KEY_SEEN)||'[]')); }catch{ return new Set(); } }
  function addSeen(ids){
    const set = getSeenSet();
    ids.forEach(id=> set.add(id));
    localStorage.setItem(KEY_SEEN, JSON.stringify(Array.from(set).slice(-500)));
  }

  // SW click → navigate
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (ev)=>{
      const data = ev.data || {};
      if (data.type === 'open' && data.url) { try { location.href = data.url; } catch(e){} }
    });
  }

  // Polling
  let polling = false;
  let pollingDisabled = false;
  let pollIntervalId = null;
  async function poll(){
    if (pollingDisabled) return;
    if (polling) return;
    polling = true;
    try {
  const since = getLastId();
  const url = new URL(PUSH_URL);
  url.searchParams.set('since_id', String(since));
  if (ROLE_HINT) url.searchParams.set('role_hint', ROLE_HINT);
  const resp = await fetch(url.toString(), { credentials:'same-origin' });
      const ct = resp.headers.get('content-type') || '';
      if (!resp.ok) {
        console.error('[push_client] push endpoint http error', resp.status);
        // try to log body for diagnostics
        const txt = await resp.text().catch(()=> '');
        if (txt) console.error('[push_client] body:', txt.slice(0,500));

        // If we get an auth/forbidden/redirect-like error on shared hosting, stop polling
        // to avoid spamming the console and triggering host defenses.
        if (resp.status === 401 || resp.status === 403 || resp.status === 404) {
          pollingDisabled = true;
          if (pollIntervalId) { try { clearInterval(pollIntervalId); } catch {} }
          console.warn('[push_client] polling disabled due to http status', resp.status);
        }
        polling = false; return;
      }
      let payload = null;
      if (ct.includes('application/json')) {
        payload = await resp.json();
      } else {
        const txt = await resp.text();
        console.error('[push_client] non-JSON response:', txt.slice(0,500));

        // Hosts sometimes inject an HTML challenge/redirect page; stop polling for this load.
        pollingDisabled = true;
        if (pollIntervalId) { try { clearInterval(pollIntervalId); } catch {} }
        console.warn('[push_client] polling disabled due to non-JSON response');
        polling = false; return;
      }
      if (!payload || !payload.success) { polling = false; return; }

      console.log('[push_client] poll got', payload.data ? payload.data.length : 0, 'items');

      const items = Array.isArray(payload.data) ? payload.data : [];
      if (items.length) {
        const seen = getSeenSet();
        const fresh = items.filter(n=> !seen.has(n.id));
        if (fresh.length) {
          // Always inform SW of batch (allows future offline logic)
          try {
            const reg = await navigator.serviceWorker.ready;
            if (reg && reg.active) {
              reg.active.postMessage({
                  type: 'batch',
                  items: fresh.map(n=> ({
                    id: n.id,
                    title: n.title || 'Notification',
                    message: n.message || '',
                    url: resolveNotifUrl(n.id),
                    tag: 'bpamis-' + n.id,
                    icon: ICON_URL,
                    // include a timestamp so the service worker can decide which notification to close
                    ts: Date.now()
                  }))
                });
            }
          } catch(e) { console.warn('[push_client] batch postMessage failed', e); }

          // Always show via Service Worker native notifications only
          for (const n of fresh) await showNativeNotification(n);
          addSeen(fresh.map(n=> n.id));
        }
        if (payload.max_id && payload.max_id > since) setLastId(payload.max_id);
      }
    } catch(e) {
      console.error('[push_client] poll error', e);
    } finally { polling = false; }
  }

  // Start
  let started = false;
  function start(){
    if (started) return;
    started = true;
    poll();
    pollIntervalId = setInterval(poll, 10000);
  }
  start();
  ['click','keydown','scroll','mousemove','touchstart'].forEach(evt=>{
    window.addEventListener(evt, ()=>{ warmPermission(); start(); }, { once:true, passive:true });
  });
  document.addEventListener('visibilitychange', ()=> { if (!document.hidden) poll(); });
})();
</script>