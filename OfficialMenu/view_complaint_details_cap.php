<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($complaint_id <= 0) { echo "Invalid complaint."; exit; }
$is_case = false;
$editing = isset($_GET['edit']);

// Check if already in CASE_INFO
$case_result = $conn->query("SELECT 1 FROM CASE_INFO WHERE Complaint_ID = $complaint_id LIMIT 1");
$is_case = $case_result && $case_result->num_rows > 0;

// Keep server-side actions (not exposed in UI here)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint']) && !$is_case) {
    $title = $conn->real_escape_string($_POST['complaint_title'] ?? '');
    $details = $conn->real_escape_string($_POST['complaint_details'] ?? '');
    $conn->query("UPDATE COMPLAINT_INFO SET Complaint_Title = '$title', Complaint_Details = '$details' WHERE Complaint_ID = $complaint_id");
    header("Location: view_complaint_details.php?id=$complaint_id"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_case']) && !$is_case) {
    $date_opened = date('Y-m-d');
    $conn->query("INSERT INTO CASE_INFO (Complaint_ID, Case_Status, Date_Opened) VALUES ($complaint_id, 'Open', '$date_opened')");
    $conn->query("UPDATE COMPLAINT_INFO SET Status = 'IN CASE' WHERE Complaint_ID = $complaint_id");
    header("Location: view_complaint_details.php?id=$complaint_id"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_complaint']) && !$is_case) {
    $conn->query("UPDATE COMPLAINT_INFO SET Status = 'Rejected' WHERE Complaint_ID = $complaint_id");
    header("Location: view_complaint_details.php?id=$complaint_id"); exit;
}

// Fetch complaint (with resident or external complainant name)
$sql = "SELECT c.*, r.First_Name AS Res_First_Name, r.Last_Name AS Res_Last_Name, 
               e.First_Name AS Ext_First_Name, e.Last_Name AS Ext_Last_Name
        FROM COMPLAINT_INFO c
        LEFT JOIN RESIDENT_INFO r ON c.Resident_ID = r.Resident_ID
        LEFT JOIN EXTERNAL_COMPLAINANT e ON c.External_Complainant_ID = e.External_Complaint_ID
        WHERE c.Complaint_ID = $complaint_id";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) { echo "Complaint not found."; exit; }
$complaint = $result->fetch_assoc();
$is_rejected = strtolower($complaint['Status']) === 'rejected';

$complainant_name = !empty($complaint['Res_First_Name'])
    ? $complaint['Res_First_Name'] . ' ' . $complaint['Res_Last_Name']
    : (!empty($complaint['Ext_First_Name']) ? $complaint['Ext_First_Name'] . ' ' . $complaint['Ext_Last_Name'] : 'Unknown');

// Respondent list (read-only)
$respondents = [];
if (!empty($complaint['Respondent_ID'])) {
    $mr = $conn->query("SELECT First_Name, Last_Name FROM RESIDENT_INFO WHERE Resident_ID=".(int)$complaint['Respondent_ID']);
    if ($mr && $mr->num_rows > 0) { $r = $mr->fetch_assoc(); $respondents[] = $r['First_Name'].' '.$r['Last_Name']; }
}
$ar = $conn->query("SELECT r.First_Name, r.Last_Name FROM COMPLAINT_RESPONDENTS cr JOIN RESIDENT_INFO r ON cr.Respondent_ID=r.Resident_ID WHERE cr.Complaint_ID=$complaint_id");
if ($ar && $ar->num_rows > 0) { while($r = $ar->fetch_assoc()){ $respondents[] = $r['First_Name'].' '.$r['Last_Name']; } }
$respondent_names = !empty($respondents) ? implode(', ', $respondents) : 'N/A';

// Build resident whitelist for Tagify (for autocomplete suggestions)
$resident_list = [];
$rr = $conn->query("SELECT First_Name, Last_Name FROM RESIDENT_INFO ORDER BY Last_Name, First_Name LIMIT 4000");
if ($rr && $rr->num_rows > 0) {
    while ($rn = $rr->fetch_assoc()) {
        $resident_list[] = trim($rn['First_Name'] . ' ' . $rn['Last_Name']);
    }
}
$resident_whitelist_json = json_encode(array_values(array_unique($resident_list)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Complaint • Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}},boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'},animation:{'fade-in':'fadeIn .4s ease-out'},keyframes:{fadeIn:{'0%':{opacity:0},'100%':{opacity:1}}}}}};</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <?php if ($editing): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
        <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <?php endif; ?>
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
        
        /* Mobile Optimization - Compressed & Compact */
        @media (max-width: 640px) {
            /* Main container padding */
            main {
                padding-top: 1.5rem !important;
                padding-bottom: 3rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            
            /* Back button row */
            .mb-8.flex.items-center.gap-3 {
                margin-bottom: 1rem !important;
            }
            
            .mb-8.flex.items-center.gap-3 a {
                font-size: 0.75rem !important;
            }
            
            .mb-8.flex.items-center.gap-3 .h-8.w-8 {
                height: 1.75rem !important;
                width: 1.75rem !important;
            }
            
            /* Main card */
            section.glass {
                padding: 1rem !important;
                border-radius: 1rem !important;
            }
            
            /* Header section - icon beside title */
            header.relative.flex {
                flex-direction: row !important;
                gap: 0.75rem !important;
                margin-bottom: 1.25rem !important;
                align-items: flex-start !important;
            }
            
            /* Icon container - smaller for inline layout */
            .w-20.h-20 {
                width: 2.5rem !important;
                height: 2.5rem !important;
                border-radius: 0.65rem !important;
                flex-shrink: 0 !important;
            }
            
            .w-20.h-20 i {
                font-size: 1.1rem !important;
            }
            
            /* Title and badges - keep in same row */
            h1.text-2xl {
                font-size: 1rem !important;
                line-height: 1.3 !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.35rem !important;
                flex-wrap: wrap !important;
            }
            
            /* Complaint number text */
            h1.text-2xl > span:first-child {
                flex-shrink: 0 !important;
            }
            
            /* Status badge - same row */
            h1 span.inline-flex.items-center {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
                flex-shrink: 0 !important;
            }
            
            /* Meta info */
            .mt-3.flex.flex-wrap {
                margin-top: 0.5rem !important;
                font-size: 0.7rem !important;
                gap: 0.5rem !important;
            }
            
            /* Content sections */
            .space-y-10 {
                gap: 1.5rem !important;
            }
            
            .space-y-10 > div {
                margin-top: 1.5rem !important;
            }
            
            .space-y-10 > div:first-child {
                margin-top: 0 !important;
            }
            
            /* Section headings */
            h2.text-sm {
                font-size: 0.7rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Respondents and info boxes */
            .rounded-xl.border.bg-white\/70 {
                padding: 0.65rem !important;
                border-radius: 0.65rem !important;
            }
            
            .rounded-xl.border.bg-white\/70 p {
                font-size: 0.8rem !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            /* Complaint information grid */
            .grid.gap-5 {
                gap: 0.65rem !important;
            }
            
            .grid.gap-5 > div {
                padding: 0.65rem !important;
            }
            
            .grid.gap-5 p.font-semibold {
                font-size: 0.8rem !important;
            }
            
            .grid.gap-5 p.text-gray-700 {
                font-size: 0.78rem !important;
                line-height: 1.4 !important;
            }
            
            /* Attachments grid */
            .grid.gap-4.sm\:grid-cols-2 {
                grid-template-columns: 1fr !important;
                gap: 0.65rem !important;
            }
            
            /* Attachment cards */
            .grid.gap-4.sm\:grid-cols-2 .flex.items-center {
                padding: 0.5rem !important;
            }
            
            .grid.gap-4.sm\:grid-cols-2 .w-16.h-12 {
                width: 3rem !important;
                height: 2.5rem !important;
            }
            
            .grid.gap-4.sm\:grid-cols-2 .text-sm {
                font-size: 0.75rem !important;
            }
            
            .grid.gap-4.sm\:grid-cols-2 .text-xs {
                font-size: 0.7rem !important;
            }
            
            /* Salaysay section */
            .mt-4 .flex.items-center.gap-3 {
                gap: 0.65rem !important;
            }
            
            .mt-4 .w-20.h-16 {
                width: 3.5rem !important;
                height: 3rem !important;
            }
            
            .mt-4 .text-sm {
                font-size: 0.75rem !important;
            }
            
            .mt-4 .text-xs {
                font-size: 0.7rem !important;
            }
            
            /* Back button */
            .pt-2.flex a {
                font-size: 0.75rem !important;
                padding: 0.6rem 0.85rem !important;
                border-radius: 0.65rem !important;
            }
            
            .pt-2.flex a i {
                font-size: 0.7rem !important;
            }
            
            /* Background orbs - reduce on mobile */
            .pointer-events-none.absolute.inset-0 > div {
                opacity: 0.15 !important;
                transform: scale(0.7);
            }
        }
        
        @media (max-width: 380px) {
            /* Extra small screens - further compression */
            main {
                padding-top: 1rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            section.glass {
                padding: 0.75rem !important;
            }
            
            h1.text-2xl {
                font-size: 0.95rem !important;
            }
            
            .w-20.h-20 {
                width: 2.25rem !important;
                height: 2.25rem !important;
            }
            
            h1 span.inline-flex.items-center {
                font-size: 8px !important;
                padding: 0.2rem 0.4rem !important;
            }
        }
    </style>
    
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    <?php $status=strtoupper(trim($complaint['Status'])); $statusStyles=['PENDING'=>'bg-amber-50 text-amber-600 border border-amber-200','IN CASE'=>'bg-sky-50 text-sky-600 border border-sky-200','REJECTED'=>'bg-rose-50 text-rose-600 border border-rose-200','RESOLVED'=>'bg-emerald-50 text-emerald-600 border border-emerald-200']; $statusClass=$statusStyles[$status]??'bg-gray-100 text-gray-600 border border-gray-200'; ?>
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="#" onclick="goBack(event)" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to Previous Page</span>
            </a>
        </div>
        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
            </div>
            <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-8">
                <div class="flex items-center">
                    <div class="w-20 h-20 rounded-2xl flex items-center justify-center bg-primary-50 ring-4 ring-primary-100 shadow-inner">
                        <i class="fa fa-file-lines text-3xl text-primary-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Complaint #<?= htmlspecialchars($complaint['Complaint_ID']) ?></span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $statusClass ?> shadow-sm"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?></span>
                    </h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-user"></i> <?= htmlspecialchars($complainant_name) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> <?= date('F d, Y', strtotime($complaint['Date_Filed'])) ?></span>
                    </div>
                </div>
            </header>
            <div class="space-y-10">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Respondents</h2>
                    <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                        <?php if ($editing): ?>
                            <?php
                                $initial_tags = [];
                                foreach ($respondents as $rname) { $initial_tags[] = ['value' => $rname]; }
                                $initial_tags_json = json_encode($initial_tags);
                            ?>
                            <input id="respondent-name" name="respondent_name" type="text" placeholder="Type to search respondents..." class="w-full border px-3 py-2 rounded" />
                            <script>
                                // placeholder - Tagify will be initialized at bottom of page
                            </script>
                        <?php else: ?>
                            <p class="text-gray-700 leading-relaxed"><?= htmlspecialchars($respondent_names) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Complaint Information</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                       
                        <div class="group rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm md:col-span-2">
                            <p class="field-label mb-1">Details</p>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($complaint['Complaint_Details'])) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <p class="field-label mb-1">Incident Date</p>
                            <p class="font-semibold text-gray-800"><?=
                                !empty($complaint['incident_date'])
                                    ? (date('F d, Y', strtotime($complaint['incident_date'])) . (!empty($complaint['incident_time']) ? ' at '.date('g:i A', strtotime($complaint['incident_time'])) : ''))
                                    : date('F d, Y', strtotime($complaint['Date_Filed']))
                             ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <p class="field-label mb-1">Status</p>
                            <p class="inline-flex items-center gap-2 font-semibold"><i class="fa fa-circle text-[8px]"></i> <?= htmlspecialchars($complaint['Status']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Show attachments (read-only) -->
                <?php
                    $attachments = [];
                    if (!empty($complaint['Attachment_Path'])) {
                        $raw = $complaint['Attachment_Path'];
                        $parts = array_filter(array_map('trim', explode(';', $raw)), function($p){ return $p !== ''; });
                        foreach ($parts as $p) {
                            $clean = str_replace('..', '', $p);
                            $clean = str_replace('\\', '/', $clean);
                            $clean = ltrim($clean, '/');
                            $url = '../' . implode('/', array_map('rawurlencode', explode('/', $clean)));
                            $attachments[] = [
                                'url' => $url,
                                'name' => basename($clean),
                                'is_image' => (bool)preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $clean),
                                'is_pdf' => (bool)preg_match('/\.pdf$/i', $clean),
                            ];
                        }
                    }

                    $salaysay_display = '';
                    if (!empty($complaint['Salaysay_Path'])) {
                        $s = str_replace('..','',$complaint['Salaysay_Path']);
                        $s = str_replace('\\','/',$s);
                        $s = ltrim($s,'/');
                        $salaysay_display = '../' . implode('/', array_map('rawurlencode', explode('/', $s)));
                        $salaysay_name = basename($s);
                        $salaysay_is_image = (bool)preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $s);
                        $salaysay_is_pdf = (bool)preg_match('/\.pdf$/i', $s);
                    }
                ?>

                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Attachments</h2>
                    <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                        <?php if (!empty($attachments)): ?>
                            <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                <?php foreach ($attachments as $att): ?>
                                    <div class="flex items-center gap-3 p-2 border rounded">
                                        <div class="w-16 h-12 bg-gray-100 flex items-center justify-center overflow-hidden rounded">
                                            <?php if ($att['is_image']): ?>
                                                <img src="<?= htmlspecialchars($att['url']) ?>" alt="<?= htmlspecialchars($att['name']) ?>" class="w-full h-full object-cover" />
                                            <?php elseif ($att['is_pdf']): ?>
                                                <i class="fa fa-file-pdf text-red-500 text-2xl"></i>
                                            <?php else: ?>
                                                <i class="fa fa-paperclip text-primary-600 text-2xl"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-800 break-all"><?= htmlspecialchars($att['name']) ?></div>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank" class="text-primary-600 hover:underline">Open</a>
                                                &nbsp;·&nbsp;
                                                <a href="<?= htmlspecialchars($att['url']) ?>" download class="text-primary-600 hover:underline">Download</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">No supporting attachments uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4">
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Salaysay (Hard Copy)</h2>
                    <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                        <?php if (!empty($salaysay_display)): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-20 h-16 bg-gray-100 flex items-center justify-center overflow-hidden rounded">
                                    <?php if (!empty($salaysay_is_image)): ?>
                                        <img src="<?= htmlspecialchars($salaysay_display) ?>" class="w-full h-full object-cover" />
                                    <?php elseif (!empty($salaysay_is_pdf)): ?>
                                        <i class="fa fa-file-pdf text-red-500 text-2xl"></i>
                                    <?php else: ?>
                                        <i class="fa fa-paperclip text-primary-600 text-2xl"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($salaysay_name ?? basename($complaint['Salaysay_Path'])) ?></div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <a href="<?= htmlspecialchars($salaysay_display) ?>" target="_blank" class="text-primary-600 hover:underline">Open</a>
                                        &nbsp;·&nbsp;
                                        <a href="<?= htmlspecialchars($salaysay_display) ?>" download class="text-primary-600 hover:underline">Download</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">No salaysay (hard copy) uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-2 flex">
                    <a href="#" onclick="goBack(event)" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back to Previous Page</a>
                </div>
            </div>
        </section>
    </main>
    <?php $conn->close(); ?>
    <script>
        function goBack(event) {
            event.preventDefault();
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'view_complaints.php';
            }
        }
    </script>
    <?php if ($editing): ?>
    <script>
        (function(){
            try{
                var residentWhitelist = <?= $resident_whitelist_json ?? '[]' ?>;
                var initialTags = <?= $initial_tags_json ?? '[]' ?>;
                var input = document.getElementById('respondent-name');
                if (input && window.Tagify) {
                    var tagify = new Tagify(input, {
                        whitelist: residentWhitelist,
                        dropdown: { enabled: 1, maxItems: 12, fuzzySearch: true, position: 'text' },
                        enforceWhitelist: false,
                        originalInputValueFormat: function(valuesArr){ return JSON.stringify(valuesArr); }
                    });
                    if (Array.isArray(initialTags) && initialTags.length) {
                        // addTags accepts array of objects or strings
                        var simple = initialTags.map(function(t){ return t.value || t; });
                        tagify.addTags(simple);
                    }
                }
            }catch(e){ console && console.warn && console.warn('Tagify init error', e); }
        })();
    </script>
    <?php endif; ?>
</body>
</html>
