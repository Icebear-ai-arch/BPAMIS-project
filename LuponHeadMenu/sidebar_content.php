<?php
// Standalone sidebar content — intentionally isolated (used inside an iframe)
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
  <title>Sidebar</title>
  <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
  <style>
    html,body{height:100%;margin:0;background:transparent}
    .sidebar-inner{height:100%;box-sizing:border-box;padding:16px;background:#ffffff;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial}
    .sidebar-footer{position:absolute;left:0;right:0;bottom:0;padding:12px;border-top:1px solid #eef2f7;background:transparent}
    .nav-item{display:flex;align-items:center;gap:.75rem;padding:.6rem .75rem;border-radius:.5rem;color:#334155}
    .nav-item:hover{background:#f8fafc;color:#0f172a}
    .section-title{padding:.5rem .75rem;color:#94a3b8;font-size:.75rem;font-weight:600;text-transform:uppercase}
  </style>
</head>
<body>
  <div class="sidebar-inner relative">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <img src="../Assets/Img/logo.png" alt="Logo" width="42" height="42">
        <div>
          <div class="text-lg font-semibold text-sky-700">BPAMIS</div>
          <div class="text-xs text-slate-500">Case Management</div>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <div class="text-sm font-medium text-slate-600">Lupon Tagapamayapa Head</div>
      <div class="text-xs text-slate-400">Adjudication Panel</div>
    </div>

    <nav>
      <ul class="space-y-1">
        <li><a class="nav-item" href="home-luponhead.php"><i class="fas fa-home text-slate-400"></i><span>Dashboard</span></a></li>
        <li class="section-title">Case Management</li>
        <li><a class="nav-item" href="assign_case.php"><i class="fas fa-user-plus text-slate-400"></i><span>Assign Case</span></a></li>
        <li><a class="nav-item" href="assigned_case.php"><i class="fas fa-clipboard-list text-slate-400"></i><span>Assigned Cases</span></a></li>
        <li><a class="nav-item" href="view_cases.php"><i class="fas fa-folder text-slate-400"></i><span>View Cases</span></a></li>
        <li><a class="nav-item" href="appoint_hearing.php"><i class="fas fa-calendar-alt text-slate-400"></i><span>Appoint Hearing</span></a></li>
        <li><a class="nav-item" href="view_hearing_calendar.php"><i class="fas fa-calendar-days text-slate-400"></i><span>View Hearing Calendar</span></a></li>
        <li><a class="nav-item" href="feedback_luponhead.php"><i class="fas fa-comments text-slate-400"></i><span>Feedback</span></a></li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <!-- Add data-confirm attribute so script can show a confirmation before logging out -->
        <a id="logout-link" data-confirm="Are you sure you want to logout?" class="flex items-center gap-2 text-slate-600" href="../controllers/logoutdb.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
  </div>

  <script>
    // Handle internal link clicks to navigate parent frame when needed
    // Also support data-confirm on links (useful for logout confirmation)
    document.querySelectorAll('a').forEach(a=>{
      a.addEventListener('click', function(e){
        var href = this.getAttribute('href');
        if (!href) return;
        // If this link has a confirmation message, show it and cancel if user declines
        var confirmMsg = this.dataset && this.dataset.confirm;
        if (confirmMsg) {
            var ok = confirm(confirmMsg); if (!ok) { e.preventDefault(); return; }
            // If this is the logout link, perform AJAX logout then redirect parent
            if (this.id === 'logout-link' || /controllers\/logoutdb\.php$/.test(href)) {
              e.preventDefault();
              fetch(href, { method:'POST', credentials:'same-origin' })
                .then(r=> r.ok ? r.json() : null)
                .then(data => { var target = (data && data.redirect) ? data.redirect : '../bpamis_website/bpamis.php?logged_out=1'; if (window.parent && window.parent !== window) { window.parent.location.href = target; } else { window.location.href = target; } })
                .catch(()=>{ var target = '../bpamis_website/bpamis.php?logged_out=1'; if (window.parent && window.parent !== window) { window.parent.location.href = target; } else { window.location.href = target; } });
              return;
            }
        }
        // If parent exists, navigate parent; otherwise navigate self
        try {
          if (window.parent && window.parent !== window) {
            window.parent.location.href = href;
            e.preventDefault();
          }
        } catch(err) { /* cross-origin? fallback */ }
      });
    });
  </script>
</body>
</html>
