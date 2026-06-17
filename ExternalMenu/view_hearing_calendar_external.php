<?php
// External full-page Hearing Calendar view
// Ensure session is started and DB/server bootstrap is available before running session checks
include '../server/server.php';
include '../controllers/session_control.php';
// Align auth guard with other External pages: allow if either user_id or external_id is present
if (!isset($_SESSION['user_id']) && !isset($_SESSION['external_id'])) {
    header('Location: ../bpamis_website/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>External Hearing Calendar</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Ensure the iframe takes full available height minus header
        function resizeIframe() {
            const header = document.getElementById('pageHeader');
            const iframe = document.getElementById('calendarFrame');
            const h = window.innerHeight - (header?.offsetHeight || 0);
            iframe.style.height = h + 'px';
        }
        window.addEventListener('load', resizeIframe);
        window.addEventListener('resize', resizeIframe);
    </script>
    <style>
        body { background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
        .glass { backdrop-filter: blur(14px); background: linear-gradient(135deg, rgba(255,255,255,.65), rgba(255,255,255,.35)); border: 1px solid rgba(255,255,255,.45); box-shadow: 0 10px 40px -12px rgba(12,156,237,.25), 0 4px 18px -6px rgba(12,156,237,.18); }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 to-white min-h-screen">
<?php include '../includes/external_nav.php'; ?>

    <div class="max-w-7xl mx-auto px-5 py-8">
        <div class="glass rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-sky-900 font-semibold tracking-tight flex items-center gap-2"><i class="fa-solid fa-calendar-days text-sky-600"></i> Hearing Calendar</h1>
                <a href="home-external.php" class="inline-flex items-center gap-2 text-[12px] px-3 py-1.5 rounded-lg bg-white/60 hover:bg-white/80 border border-white/60 text-sky-700"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
            <iframe src="../SecMenu/schedule/CalendarExternal.php" class="w-full h-[660px] rounded-xl border border-white/50 bg-white/60 z-50"></iframe>
        </div>
    </div>

    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
