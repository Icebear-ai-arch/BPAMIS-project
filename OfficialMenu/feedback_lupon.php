<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Redirect if not logged in
if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

$official_id = $_SESSION['official_id'];
$official_name = $_SESSION['official_name'] ?? 'Unknown';

// Resolve Lupon full name from DB to match assignment text (more reliable than session)
$luponName = $official_name;
if ($stmt = $conn->prepare("SELECT Name FROM barangay_officials WHERE Official_ID = ?")) {
    $stmt->bind_param('i', $official_id);
    if ($stmt->execute()) {
        $res = bpamis_stmt_get_result($stmt);
        if ($res && $row = $res->fetch_assoc()) {
            $luponName = trim((string)($row['Name'] ?? $luponName));
        }
    }
    $stmt->close();
}

$success = '';
$error = '';
$cases = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
    $message = trim($_POST['message'] ?? '');

    if ($case_id > 0 && $message !== '') {
        // Validate that this case is assigned to this Lupon
        $isAssigned = false;
        $checkSql = "
            SELECT 1
            FROM case_info ci
            LEFT JOIN mediation_info mi ON mi.case_id = ci.Case_ID
            LEFT JOIN conciliation r ON r.case_id = ci.Case_ID
            LEFT JOIN arbitration s ON s.case_id = ci.Case_ID
            WHERE ci.Case_ID = ? AND (
                LOWER(mi.mediator_name) LIKE CONCAT('%', LOWER(?), '%') OR
                FIND_IN_SET(REPLACE(REPLACE(LOWER( ? ), ' ', ''), '_', ''), REPLACE(REPLACE(LOWER(mi.mediator_name), ' ', ''), '_', '')) > 0 OR
                LOWER(r.mediator_name) LIKE CONCAT('%', LOWER(?), '%') OR
                FIND_IN_SET(REPLACE(REPLACE(LOWER( ? ), ' ', ''), '_', ''), REPLACE(REPLACE(LOWER(r.mediator_name), ' ', ''), '_', '')) > 0 OR
                LOWER(s.mediator_name) LIKE CONCAT('%', LOWER(?), '%') OR
                FIND_IN_SET(REPLACE(REPLACE(LOWER( ? ), ' ', ''), '_', ''), REPLACE(REPLACE(LOWER(s.mediator_name), ' ', ''), '_', '')) > 0 OR
                LOWER(ci.lupon_assign) LIKE CONCAT('%', LOWER(?), '%') OR
                FIND_IN_SET(REPLACE(REPLACE(LOWER( ? ), ' ', ''), '_', ''), REPLACE(REPLACE(LOWER(ci.lupon_assign), ' ', ''), '_', '')) > 0
            )
            LIMIT 1
        ";
        if ($cs = $conn->prepare($checkSql)) {
            $cs->bind_param('issssssss', $case_id, $luponName, $luponName, $luponName, $luponName, $luponName, $luponName, $luponName, $luponName);
            $cs->execute();
            $cr = bpamis_stmt_get_result($cs);
            $isAssigned = $cr && $cr->num_rows > 0;
            $cs->close();
        }

        if ($isAssigned) {
            $stmt = $conn->prepare("
                INSERT INTO feedback (official_id, official_name, case_id, message, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("isis", $official_id, $official_name, $case_id, $message);
            if ($stmt->execute()) {
                $success = "Feedback successfully submitted.";
            } else {
                $error = "Error inserting feedback: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "You can only submit feedback for cases assigned to you.";
        }
    } else {
        $error = "Please select a case and enter your feedback.";
    }
}
?>
<?php include 'sidebar_lupon.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Write Feedback</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,0.10), 0 4px 18px -2px rgba(6,90,143,0.20)'}, keyframes:{fadeIn:{'0%':{opacity:0,transform:'translateY(4px)'},'100%':{opacity:1,transform:'translateY(0)'}},pulseSoft:{'0%,100%':{opacity:1},'50%':{opacity:.55}}}, animation:{'fade-in':'fadeIn .5s ease-out','pulse-soft':'pulseSoft 3s ease-in-out infinite'} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .bg-orbs:before, .bg-orbs:after { content:""; position:absolute; border-radius:9999px; filter:blur(70px); opacity:.35; }
        .bg-orbs:before { width:480px; height:480px; background:linear-gradient(135deg,#7cccfd,#0c9ced); top:-160px; left:-140px; }
        .bg-orbs:after { width:420px; height:420px; background:linear-gradient(135deg,#bae2fd,#7cccfd); bottom:-140px; right:-120px; }
        .glass { background:linear-gradient(145deg,rgba(255,255,255,.88),rgba(255,255,255,.65)); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); }
        .input-base { width:100%; border-radius:0.5rem; border:1px solid rgba(209,213,219,.7); background:rgba(255,255,255,.7); padding:.625rem .75rem; font-size:.875rem; transition:.2s; }
        .input-base:not(textarea){ height:44px; line-height:1.2; }
        .input-base:focus { outline:none; background:#fff; border-color:#36b3f9; box-shadow:0 0 0 4px rgba(12,156,237,.25); }
        .field-label { font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; display:flex; gap:4px; align-items:center; color:#4b5563; }
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            .glass { padding: 1rem !important; }
            .input-base { font-size: 0.8125rem; padding: 0.5rem 0.625rem; }
            .input-base:not(textarea) { height: 38px; }
            .field-label { font-size: 10px; margin-bottom: 3px; }
        }
    </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>

    <!-- Page Heading (mirrors secretary add complaints) -->
    <header class="relative max-w-6xl mx-auto px-3 sm:px-4 md:px-8 pt-4 sm:pt-6 md:pt-8 animate-fade-in">
        <div class="relative glass rounded-xl sm:rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-4 py-4 sm:px-6 sm:py-6 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-3 sm:gap-4 md:gap-6">
                <div>
                    <h1 class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-2 sm:gap-3">
                        <span class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12 rounded-lg sm:rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-comment-dots text-sm sm:text-base md:text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Write Feedback</span>
                    </h1>
                    <p class="mt-2 sm:mt-3 text-xs sm:text-sm md:text-base text-gray-600 max-w-prose">Share your observations and recommendations regarding the case proceedings.</p>
                </div>
                <div class="flex items-center gap-2 text-[10px] sm:text-xs text-gray-500">
                    <div class="px-2 py-1 sm:px-3 rounded-full bg-white/70 border border-primary-100 flex items-center gap-1 sm:gap-2"><i class="fa fa-shield-halved text-primary-500"></i> <span class="hidden sm:inline">Secure Form</span></div>
                    <div class="px-2 py-1 sm:px-3 rounded-full bg-white/70 border border-primary-100 flex items-center gap-1 sm:gap-2"><i class="fa fa-comments text-primary-500"></i> <span class="hidden sm:inline">Case Feedback</span></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Form Section (mirrors secretary add complaints) -->
    <main class="relative z-10 max-w-5xl mx-auto px-3 sm:px-4 md:px-8 mt-4 sm:mt-6 md:mt-10 pb-12 sm:pb-16 md:pb-24">
        <section class="glass rounded-xl sm:rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 p-4 sm:p-6 md:p-10 animate-fade-in">
            <div class="mb-4 sm:mb-6 md:mb-8 flex items-center justify-between flex-wrap gap-3 sm:gap-4">
                <h2 class="text-base sm:text-lg md:text-xl font-semibold text-gray-800 flex items-center gap-2"><i class="fa fa-pen-to-square text-primary-500 text-sm sm:text-base"></i> <span>Feedback Details</span></h2>
                <a href="home-lupon.php" class="inline-flex items-center gap-2 text-xs sm:text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="mb-4 sm:mb-6 rounded-lg border border-green-300 bg-green-50 text-green-700 px-3 py-2 sm:px-4 sm:py-3 text-xs sm:text-sm flex items-start gap-2"><i class="fa fa-check-circle mt-0.5"></i><span><?= htmlspecialchars($success) ?></span></div>
            <?php elseif (!empty($error)): ?>
                <div class="mb-4 sm:mb-6 rounded-lg border border-red-300 bg-red-50 text-red-700 px-3 py-2 sm:px-4 sm:py-3 text-xs sm:text-sm flex items-start gap-2"><i class="fa fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 sm:space-y-6 md:space-y-8">
                <div class="grid md:grid-cols-2 gap-4 sm:gap-6">
                    <div>
                        <label for="case-id" class="field-label"><i class="fa fa-folder-open"></i> Select Case</label>
                        <select id="case-id" name="case_id" class="input-base" required>
                            <option value="">-- Select a Case --</option>
                            <?php
                            // Use Complaint_Description if available; fallback to Complaint_Title
                            $hasDesc = false;
                            if ($chk = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'Complaint_Description'")) {
                                $hasDesc = $chk->num_rows > 0;
                                $chk->close();
                            }
                            $descField = $hasDesc ? 'co.Complaint_Description' : 'co.Complaint_Title';

                            // Select only assigned cases that have at least one scheduled hearing (visible + hearingDateTime)
                            // Format: case_original_id: case_status, hearing_title
                            $sql = "
                                SELECT
                                    ci.Case_ID,
                                    COALESCE(ci.case_original_id, ci.Case_ID) AS case_original_id,
                                    COALESCE(ci.Case_Status, '') AS case_status,
                                    (
                                        SELECT sl.hearingTitle
                                        FROM schedule_list sl
                                        WHERE sl.Case_ID = ci.Case_ID AND sl.visible = 1 AND sl.HearingDateTime IS NOT NULL
                                        ORDER BY sl.HearingDateTime ASC
                                        LIMIT 1
                                    ) AS hearing_title
                                FROM case_info ci
                                JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                                LEFT JOIN mediation_info mi ON mi.case_id = ci.Case_ID
                                LEFT JOIN conciliation r ON r.case_id = ci.Case_ID
                                LEFT JOIN arbitration s ON s.Case_Id = ci.Case_ID
                                WHERE (
                                    mi.mediator_name LIKE CONCAT('%', ?, '%') OR
                                    FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(mi.mediator_name, ' ', '')) > 0 OR
                                    r.mediator_name LIKE CONCAT('%', ?, '%') OR
                                    FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(r.mediator_name, ' ', '')) > 0 OR
                                    s.mediator_name LIKE CONCAT('%', ?, '%') OR
                                    FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(s.mediator_name, ' ', '')) > 0 OR
                                    ci.lupon_assign LIKE CONCAT('%', ?, '%') OR
                                    FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(ci.lupon_assign, ' ', '')) > 0
                                )
                                /* Only show cases that are currently in Conciliation or Arbitration
                                 * Accept status variants that contain the words 'conciliation' or 'arbitration'
                                 * but exclude any that include 'resolved' (case-insensitive).
                                 */
                                AND (
                                    (
                                        LOWER(ci.Case_Status) LIKE '%conciliation%' AND
                                        LOWER(ci.Case_Status) NOT LIKE '%resolved%'
                                    )
                                    OR
                                    (
                                        LOWER(ci.Case_Status) LIKE '%arbitration%' AND
                                        LOWER(ci.Case_Status) NOT LIKE '%resolved%'
                                    )
                                )
                                -- Include cases even if they don't yet have a scheduled hearing; showing unscheduled assigned cases helps Lupon give feedback
                                GROUP BY ci.Case_ID
                                ORDER BY ci.Case_ID ASC
                            ";

                            if ($st = $conn->prepare($sql)) {
                                $st->bind_param('ssssssss', $luponName, $luponName, $luponName, $luponName, $luponName, $luponName, $luponName, $luponName);
                                $st->execute();
                                $rs = bpamis_stmt_get_result($st);
                                if ($rs && $rs->num_rows > 0) {
                                    while ($row = $rs->fetch_assoc()) {
                                        $cid = (int)$row['Case_ID'];
                                        $orig = trim((string)($row['case_original_id'] ?? $cid));
                                        $cstatus = trim((string)($row['case_status'] ?? '')) ?: 'Unknown';
                                        $htitle = trim((string)($row['hearing_title'] ?? '')) ?: 'No title';
                                        $label = sprintf('%s: %s, %s', $orig, $cstatus, $htitle);
                                        echo '<option value="' . $cid . '" data-case-original="' . htmlspecialchars($orig) . '" data-case-status="' . htmlspecialchars($cstatus) . '" data-hearing-title="' . htmlspecialchars($htitle) . '">' . htmlspecialchars($label) . '</option>';
                                    }
                                } else {
                                    echo '<option disabled>No assigned scheduled cases found</option>';
                                }
                                $st->close();
                            } else {
                                echo '<option disabled>Unable to load assigned cases</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <!-- Right-side Case Details Panel -->
                    <div>
                        <label class="field-label"><i class="fa fa-circle-info"></i> Case Details</label>
                        <div id="case-details" class="rounded-lg border border-gray-200 bg-white/70 p-3 sm:p-4 min-h-[140px] sm:min-h-[160px] md:min-h-[180px]">
                            <div class="text-xs sm:text-sm text-gray-500">Select a case to view details here.</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="message" class="field-label"><i class="fa fa-align-left"></i> Your Feedback</label>
                    <textarea id="message" name="message" rows="5" required class="input-base resize-y" placeholder="Enter your feedback here..."></textarea>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 pt-3 sm:pt-4 border-t border-dashed border-primary-200/60">
                    <a href="home-lupon.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-xs sm:text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-5 py-2 sm:px-6 sm:py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                        <i class="fa fa-paper-plane"></i> Submit Feedback
                    </button>
                </div>
            </form>
        </section>
    </main>

    <?php include '../chatbot/bpamis_case_assistant.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const sel = document.getElementById('case-id');
        const panel = document.getElementById('case-details');
        function loadCaseDetails(id){
            if(!id){ panel.innerHTML = '<div class="text-sm text-gray-500">Select a case to view details here.</div>'; return; }
            const opt = sel.querySelector('option[value="'+id+'"]');
            if(!opt){ panel.innerHTML = '<div class="text-sm text-red-600">Unable to load case details.</div>'; return; }
            const caseOriginal = opt.dataset.caseOriginal || '';
            const caseStatus = opt.dataset.caseStatus || '—';
            const hearingTitle = opt.dataset.hearingTitle || '—';
            panel.innerHTML = '<div class="space-y-2">'
                + '<div class="flex gap-3 text-sm"><div class="w-32 text-gray-500 font-medium">Case #</div><div class="flex-1 text-gray-800">' + escapeHtml(caseOriginal) + '</div></div>'
                + '<div class="flex gap-3 text-sm"><div class="w-32 text-gray-500 font-medium">Status</div><div class="flex-1 text-gray-800">' + escapeHtml(caseStatus) + '</div></div>'
                + '<div class="flex gap-3 text-sm"><div class="w-32 text-gray-500 font-medium">Next Hearing</div><div class="flex-1 text-gray-800">' + escapeHtml(hearingTitle) + '</div></div>'
                + '</div>';
        }
        sel?.addEventListener('change', (e)=> loadCaseDetails(e.target.value));
        // If a case is pre-selected by server postback, load its details
        if(sel && sel.value){ loadCaseDetails(sel.value); }

        // Small helper to avoid inserting raw HTML from data attributes
        function escapeHtml(str){
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
    </script>
</body>
</html>
