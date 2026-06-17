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

$success = '';
$error = '';
$cases = [];

// Preselected case from calendar link (accept either numeric Case_ID or case_original_id)
$preselect_case_param = isset($_GET['case_id']) ? (string)$_GET['case_id'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = $_POST['case_id'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if (!empty($case_id) && !empty($message)) {
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
        $error = "Please select a case and enter your feedback.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Write Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
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
        
        @media (max-width: 640px) {
            .bg-orbs:before { width:280px !important; height:280px !important; top:-100px !important; left:-80px !important; }
            .bg-orbs:after { width:240px !important; height:240px !important; bottom:-80px !important; right:-60px !important; }
            body > header { padding-top: 1rem !important; padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
            body > header .glass { padding: 1rem !important; }
            body > header h1 { font-size: 1.125rem !important; line-height: 1.4 !important; }
            body > header h1 .w-12 { width: 2.25rem !important; height: 2.25rem !important; font-size: 0.875rem !important; }
            body > header p { font-size: 0.7rem !important; margin-top: 0.5rem !important; }
            body > header .flex.items-center.gap-3 { gap: 0.375rem !important; font-size: 0.6rem !important; }
            body > header .flex.items-center.gap-3 > div { padding: 0.25rem 0.5rem !important; }
            body > header .absolute.-top-10 { width: 6rem !important; height: 6rem !important; top: -1.5rem !important; right: -1.5rem !important; }
            body > header .absolute.-bottom-12 { width: 8rem !important; height: 8rem !important; bottom: -2rem !important; left: -2rem !important; }
            main { padding-left: 0.75rem !important; padding-right: 0.75rem !important; margin-top: 1.5rem !important; padding-bottom: 3rem !important; }
            main > section { padding: 1rem !important; }
            main h2 { font-size: 0.95rem !important; }
            main .mb-8 { margin-bottom: 1rem !important; gap: 0.5rem !important; }
            main .mb-8 a { font-size: 0.7rem !important; }
            main form { gap: 1rem !important; }
            main form .grid { gap: 1rem !important; grid-template-columns: 1fr !important; }
            main form .space-y-8 { gap: 1rem !important; }
            .field-label { font-size: 9px !important; margin-bottom: 3px !important; gap: 3px !important; }
            .input-base { height: 38px !important; padding: 0.5rem 0.625rem !important; font-size: 0.7rem !important; border-radius: 0.375rem !important; }
            .input-base:not(textarea) { line-height: 1.1 !important; }
            textarea.input-base { padding: 0.5rem 0.625rem !important; font-size: 0.7rem !important; }
            #case-details { padding: 0.625rem !important; min-height: 120px !important; font-size: 0.7rem !important; }
            #case-details .space-y-2 > div { gap: 0.5rem !important; font-size: 0.65rem !important; }
            #case-details .w-32 { width: 5rem !important; font-size: 0.6rem !important; }
            main form button, main form a.inline-flex { padding: 0.5rem 0.875rem !important; font-size: 0.7rem !important; border-radius: 0.375rem !important; gap: 0.375rem !important; }
            main form .flex.flex-col.sm\:flex-row { gap: 0.5rem !important; padding-top: 0.75rem !important; }
            .mb-6.rounded-lg { padding: 0.625rem 0.75rem !important; font-size: 0.7rem !important; gap: 0.375rem !important; }
            .mb-6.rounded-lg i { font-size: 0.75rem !important; }
        }
    </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/lupon_head_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>

    <!-- Page Heading (mirrors secretary add complaints) -->
    <header class="relative max-w-6xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
        <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-comment-dots text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Write Feedback</span>
                    </h1>
                    <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Provide guidance and feedback on barangay cases and proceedings.</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-comments text-primary-500"></i> Case Feedback</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Form Section (mirrors secretary add complaints) -->
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 mt-10 pb-24">
        <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 p-6 md:p-10 animate-fade-in">
            <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center gap-2"><i class="fa fa-pen-to-square text-primary-500"></i> Feedback Details</h2>
                <a href="home-luponhead.php" class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="mb-6 rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-check-circle mt-0.5"></i><span><?= htmlspecialchars($success) ?></span></div>
            <?php elseif (!empty($error)): ?>
                <div class="mb-6 rounded-lg border border-red-300 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label for="case-id" class="field-label"><i class="fa fa-folder-open"></i> Select Case</label>
                        <select id="case-id" name="case_id" class="input-base" required>
                            <option value="">-- Select a Case --</option>
                            <?php
                            // Display all open/non-resolved cases with compact label: CaseOriginalID - Complainant vs Respondent
                            $sql = "SELECT ci.Case_ID, ci.case_original_id, ci.case_status, ci.Complaint_ID,
                                           COALESCE(rcomp.first_name, ext_comp.first_name, '') AS comp_first,
                                           COALESCE(rcomp.last_name, ext_comp.last_name, '') AS comp_last,
                                           rresp.first_name AS resp_first, rresp.last_name AS resp_last
                                    FROM case_info ci
                                    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                                    LEFT JOIN resident_info rcomp ON co.Resident_ID = rcomp.Resident_ID
                                    LEFT JOIN external_complainant ext_comp ON co.external_complainant_id = ext_comp.external_complaint_id
                                    LEFT JOIN resident_info rresp ON co.Respondent_ID = rresp.Resident_ID
                                    WHERE LOWER(ci.case_status) NOT LIKE '%resolved%' AND LOWER(ci.case_status) NOT LIKE '%certificate%'
                                    ORDER BY ci.Case_ID ASC";
                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $cid = (int)$row['Case_ID'];
                                    $orig = trim((string)($row['case_original_id'] ?? ''));
                                    $complaintId = isset($row['Complaint_ID']) ? (int)$row['Complaint_ID'] : 0;

                                    // Build complainant name (resident or external)
                                    $complainant = trim(($row['comp_first'] ?? '') . ' ' . ($row['comp_last'] ?? ''));
                                    if ($complainant === '') $complainant = 'Unknown';

                                    // Build respondents list: include main respondent and any extra respondents
                                    $respondents = [];
                                    $mainResp = trim((($row['resp_first'] ?? '') . ' ' . ($row['resp_last'] ?? '')));
                                    if ($mainResp !== '') $respondents[] = $mainResp;
                                    if ($complaintId) {
                                        $ars = $conn->prepare("SELECT ri.first_name, ri.last_name FROM complaint_respondents cr JOIN resident_info ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?");
                                        if ($ars) {
                                            $ars->bind_param('i', $complaintId);
                                            $ars->execute();
                                            $arsr = bpamis_stmt_get_result($ars);
                                            while ($ar = $arsr->fetch_assoc()) {
                                                $rname = trim((($ar['first_name'] ?? '') . ' ' . ($ar['last_name'] ?? '')));
                                                if ($rname !== '') $respondents[] = $rname;
                                            }
                                            $ars->close();
                                        }
                                    }

                                    $uniqueResps = array_values(array_unique(array_filter($respondents)));
                                    if (empty($uniqueResps)) {
                                        $respDisplay = '[No Respondent]';
                                    } else {
                                        if (count($uniqueResps) === 1) $respDisplay = $uniqueResps[0];
                                        else $respDisplay = $uniqueResps[0] . ' et al';
                                    }

                                    $value = $orig !== '' ? $orig : (string)$cid; // option value uses original id when available
                                    $labelId = $orig !== '' ? $orig : $cid;
                                    $label = $labelId . ' - ' . $complainant . ' vs ' . $respDisplay;

                                    $sel = '';
                                    if ($preselect_case_param !== '') {
                                        if ($preselect_case_param === $value || $preselect_case_param === (string)$cid) $sel = ' selected';
                                    }

                                    echo '<option value="' . htmlspecialchars($value) . '" data-case-id="' . $cid . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                }
                            } else {
                                echo '<option disabled>No cases found</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <!-- Right-side Case Details Panel -->
                    <div>
                        <label class="field-label"><i class="fa fa-circle-info"></i> Case Details</label>
                        <div id="case-details" class="rounded-lg border border-gray-200 bg-white/70 p-4 min-h-[180px]">
                            <div class="text-sm text-gray-500">Select a case to view details here.</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="message" class="field-label"><i class="fa fa-align-left"></i> Your Feedback</label>
                    <textarea id="message" name="message" rows="6" required class="input-base resize-y" placeholder="Enter your feedback here..."></textarea>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-dashed border-primary-200/60">
                    <a href="home-luponhead.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                        <i class="fa fa-paper-plane"></i> Submit Feedback
                    </button>
                </div>
            </form>
        </section>
    </main>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const sel = document.getElementById('case-id');
        const panel = document.getElementById('case-details');
        async function loadCaseDetails(id){
            if(!id){ panel.innerHTML = '<div class="text-sm text-gray-500">Select a case to view details here.</div>'; return; }
            panel.innerHTML = '<div class="text-sm text-gray-500">Loading case details…</div>';
            try{
                const res = await fetch('../controllers/get_case_brief.php?case_id='+encodeURIComponent(id), { headers:{'Accept':'application/json'} });
                const data = await res.json();
                if(data && data.success){
                    const c = data.case || {};
                    const rows = [
                        {label:'Case #', value: c.case_original_id ?? c.case_id ?? ''},
                        {label:'Case Type', value: c.case_type ?? '—'},
                        {label:'Status', value: c.case_status ?? '—'},
                        {label:'Details', value: c.complaint ?? '—'},
                        {label:'Next Hearing', value: c.next_hearing ?? '—'}
                    ];
                    panel.innerHTML = '<div class="space-y-2">'+ rows.map(r=>`<div class=\"flex gap-3 text-sm\"><div class=\"w-32 text-gray-500 font-medium\">${r.label}</div><div class=\"flex-1 text-gray-800\">${r.value}</div></div>`).join('') + '</div>';
                } else {
                    panel.innerHTML = '<div class="text-sm text-red-600">Unable to load case details.</div>';
                }
            }catch(e){
                panel.innerHTML = '<div class="text-sm text-red-600">Failed to load case details.</div>';
            }
        }
        sel?.addEventListener('change', (e)=> {
            const opt = e.target.options[e.target.selectedIndex];
            const numericId = opt?.dataset?.caseId || e.target.value;
            loadCaseDetails(numericId);
        });
        // If page opened with case_id in URL, ensure details load
        if(sel){
            const initialParam = (new URLSearchParams(location.search)).get('case_id');
            if(initialParam){
                // try to select the option with matching value (original id) or matching numeric case id
                for (const o of sel.options) {
                    if (o.value === initialParam || o.dataset.caseId === initialParam) {
                        sel.value = o.value;
                        const numericId = o.dataset.caseId || o.value;
                        loadCaseDetails(numericId);
                        break;
                    }
                }
            } else if (sel.value) {
                // If a default value is set on the select, load its details
                const o = sel.options[sel.selectedIndex];
                const numericId = o?.dataset?.caseId || sel.value;
                if (numericId) loadCaseDetails(numericId);
            }
        }
    });
    </script>
</body>
</html>
