// Fast activate so new worker takes control immediately
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

// Handle notification clicks consistently across clients
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification && event.notification.data && event.notification.data.url) || self.registration.scope;
  event.waitUntil((async () => {
    const all = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    // Prefer a client already under our scope
    for (const client of all) {
      if (client && 'focus' in client) {
        try { await client.focus(); } catch(e) {}
        try { client.postMessage({ type: 'open', url }); } catch(e) {}
        return;
      }
    }
    // No existing window; for security open the login/root page instead of an internal deep-link
    // This prevents unauthenticated navigation to internal pages when the user clicks a push.
    const loginUrl = new URL('bpamis_website/login.php', self.registration.scope).toString();
    await clients.openWindow(loginUrl);
  })());
});

// Optional: Handle Web Push payloads if server sends them (future-proof)
// Optional: Handle Web Push payloads if server sends them (future-proof)
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; }
  catch (e) { try { data = { title: 'Notification', body: event.data && event.data.text ? event.data.text() : '' }; } catch(_) {} }

  // Derive stable tag from notification id when available to prevent duplicates
  const notifId = (data.id || data.notification_id || data.nid || null);
  const title = data.title || 'Notification';
  const url = data.url || self.registration.scope;
  // Use shared app icon path within scope
  const icon = data.icon || new URL('SecMenu/logo.png', self.registration.scope).toString();

  const tag = (data.tag || (notifId ? ('bpamis-' + notifId) : ('bpamis-' + Date.now())));

  const opts = {
    body: data.body || data.message || '',
    icon,
    badge: data.badge || icon,
    tag,
    renotify: false,
    data: { url, id: notifId, ...data.data },
    vibrate: data.vibrate || [120, 80, 120]
  };
  event.waitUntil((async () => {
    try {
      // Fetch all currently displayed notifications under this registration
      const existingAll = await self.registration.getNotifications();

      // If a notification with the same tag already exists, skip showing a duplicate
      if (existingAll.some(n => n && n.tag === tag)) {
        return; // duplicate already visible
      }

      // Allow up to 3 notifications at once. If >= 3 shown, close the oldest to make room.
      if (existingAll.length >= 3) {
        // Determine oldest by timestamp in notification.data.ts when available
        let oldest = existingAll[0];
        let oldestTs = (oldest && oldest.data && oldest.data.ts) ? oldest.data.ts : Date.now();
        for (const n of existingAll) {
          const ts = (n && n.data && n.data.ts) ? n.data.ts : Date.now();
          if (ts < oldestTs) { oldest = n; oldestTs = ts; }
        }
        try { if (oldest) oldest.close(); } catch(e) {}
      }

    } catch (e) {
      // If any inspection fails, fall back to showing notification (best-effort)
      console.warn('sw push handling error while managing concurrency/duplicates', e);
    }
    await self.registration.showNotification(title, opts);
  })());
});

// Bridge: allow pages to request the SW to show a notification (optional)
self.addEventListener('message', (event) => {
  const msg = event.data || {};
  if (msg && msg.type === 'notify' && msg.payload) {
    const p = msg.payload;
    const id = p.id || p.notification_id || null;
    const title = p.title || 'Notification';
    const url = p.url || (p.data && p.data.url) || self.registration.scope;
    const icon = p.icon || new URL('SecMenu/logo.png', self.registration.scope).toString();
    const tag = p.tag || (id ? ('bpamis-' + id) : ('bpamis-' + Date.now()));
    const opts = {
      body: p.body || p.message || '',
      icon,
      badge: p.badge || icon,
      tag,
      renotify: false,
      data: { url, id, ...(p.data||{}) },
      vibrate: p.vibrate || [120, 80, 120]
    };
    event.waitUntil((async ()=>{
      try {
        const existingAll = await self.registration.getNotifications();

        // Skip if same tag is already visible
        if (existingAll.some(n => n && n.tag === tag)) return;

        // Keep at most 3 visible; if >=3 close the oldest
        if (existingAll.length >= 3) {
          let oldest = existingAll[0];
          let oldestTs = (oldest && oldest.data && oldest.data.ts) ? oldest.data.ts : Date.now();
          for (const n of existingAll) {
            const ts = (n && n.data && n.data.ts) ? n.data.ts : Date.now();
            if (ts < oldestTs) { oldest = n; oldestTs = ts; }
          }
          try { if (oldest) oldest.close(); } catch(e){}
        }
      } catch(e) {
        console.warn('sw message handling concurrency/dup check failed', e);
      }
      await self.registration.showNotification(title, opts);
    })());
  }
});