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
if (!function_exists('getResidentId')) {
    function getResidentId($conn, $full_name) {
        $full_name = preg_replace('/\s+/', ' ', trim($full_name));
        if ($full_name === '') return null;
        $needle = mb_strtolower($full_name);

        // try exact matches first
        $sql = "SELECT Resident_ID, First_Name, Middle_Name, Last_Name FROM RESIDENT_INFO";
        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $a = mb_strtolower(trim($row['First_Name'] . ' ' . ($row['Middle_Name'] ?? '') . ' ' . $row['Last_Name']));
                $b = mb_strtolower(trim($row['First_Name'] . ' ' . $row['Last_Name']));
                if ($needle === $a || $needle === $b) {
                    return (int)$row['Resident_ID'];
                }
            }
            $result->close();
        }

        // fallback: try matching by first and last name parts
        $parts = preg_split('/\s+/', $full_name);
        if (count($parts) >= 2) {
            $first = $conn->real_escape_string($parts[0]);
            $last = $conn->real_escape_string(end($parts));
            $q = $conn->prepare("SELECT Resident_ID FROM RESIDENT_INFO WHERE First_Name LIKE ? AND Last_Name LIKE ? LIMIT 1");
            if ($q) {
                $likeFirst = $first;
                $likeLast = $last;
                $q->bind_param('ss', $likeFirst, $likeLast);
                $q->execute();
                $r = bpamis_stmt_get_result($q);
                if ($r && $r->num_rows > 0) {
                    $id = (int)$r->fetch_assoc()['Resident_ID'];
                    $q->close();
                    return $id;
                }
                $q->close();
            }
        }

        // last resort: LIKE search on concatenated name
        $likePattern = '%' . str_replace(' ', '%', $full_name) . '%';
        $q = $conn->prepare("SELECT Resident_ID FROM RESIDENT_INFO WHERE CONCAT(First_Name,' ',COALESCE(Middle_Name,''),' ',Last_Name) LIKE ? OR CONCAT(First_Name,' ',Last_Name) LIKE ? LIMIT 1");
        if ($q) {
            $q->bind_param('ss', $likePattern, $likePattern);
            $q->execute();
            $r = bpamis_stmt_get_result($q);
            if ($r && $r->num_rows > 0) {
                $id = (int)$r->fetch_assoc()['Resident_ID'];
                $q->close();
                return $id;
            }
            $q->close();
        }
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint']) && !$is_case) {
    $title = $conn->real_escape_string($_POST['complaint_title'] ?? '');
    $details = $conn->real_escape_string($_POST['complaint_details'] ?? '');
    // Process respondents (supports Tagify JSON or comma list or array)
    $rawRespondents = $_POST['respondent_name'] ?? '';
    $respondent_names = [];
    if (is_string($rawRespondents) && strlen(ltrim($rawRespondents)) && $rawRespondents[0] === '[') {
        $decoded = json_decode($rawRespondents, true) ?? [];
        foreach ($decoded as $it) { if (!empty($it['value'])) $respondent_names[] = trim($it['value']); }
    } elseif (is_array($rawRespondents)) {
        foreach ($rawRespondents as $it) { $it = trim($it); if ($it !== '') $respondent_names[] = $it; }
    } else {
        $respondent_names = array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)$rawRespondents)));
    }

    // dedupe
    $seen = []; $unique = [];
    foreach ($respondent_names as $rnm) { $k = mb_strtolower(preg_replace('/\s+/', ' ', $rnm)); if ($k === '') continue; if (!isset($seen[$k])) { $seen[$k]=true; $unique[]=$rnm; } }
    $respondent_names = $unique;

    // Update complaint title/details
    $conn->query("UPDATE COMPLAINT_INFO SET Complaint_Title = '$title', Complaint_Details = '$details' WHERE Complaint_ID = $complaint_id");

    // Clear existing respondents then insert new ones
    $conn->query("DELETE FROM COMPLAINT_RESPONDENTS WHERE Complaint_ID = $complaint_id");
    $primary_id = null;
    if (!empty($respondent_names)) {
        foreach ($respondent_names as $idx => $rnm) {
            $rid = getResidentId($conn, $rnm);
            if (!$rid) continue;
            if ($idx === 0) {
                // set main Respondent_ID on COMPLAINT_INFO if column exists
                if ($res = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Respondent_ID'")) {
                    if ($res->num_rows > 0) {
                        $conn->query("UPDATE COMPLAINT_INFO SET Respondent_ID = $rid WHERE Complaint_ID = $complaint_id");
                    }
                    $res->close();
                }
                $primary_id = $rid;
            } else {
                $ins = $conn->prepare('INSERT INTO COMPLAINT_RESPONDENTS (Complaint_ID, Respondent_ID) VALUES (?, ?)');
                if ($ins) { $ins->bind_param('ii', $complaint_id, $rid); $ins->execute(); $ins->close(); }
            }
        }
    }

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

// Build resident whitelist for Tagify (like add_complaints.php)
$__names = [];
$__dbn = $conn->query("SELECT First_Name, Middle_Name, Last_Name FROM RESIDENT_INFO ORDER BY Last_Name, First_Name LIMIT 4000");
if ($__dbn && $__dbn->num_rows > 0) {
    while ($rn = $__dbn->fetch_assoc()) {
        $full = preg_replace('/\s+/', ' ', trim($rn['First_Name'].' '.($rn['Middle_Name']??'').' '.$rn['Last_Name']));
        if ($full !== '') $__names[] = $full;
    }
}
usort($__names, 'strcasecmp');
$resident_whitelist_json = json_encode(array_values(array_unique($__names)));

// Prepare initial Tagify tags for current respondents
$initial_tags = [];
foreach ($respondents as $rnm) { $initial_tags[] = ['value' => $rnm]; }
$initial_tags_json = json_encode($initial_tags);

// Detect if attachments column exists and parse attachments
$hasAttachmentCol = false;
if ($chk = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Attachment_Path'")) {
    $hasAttachmentCol = $chk->num_rows > 0;
    $chk->close();
}

// Helper to safely encode each segment of a path for URLs
if (!function_exists('encode_path_segments')) {
    function encode_path_segments(string $path): string {
        $path = str_replace('\\', '/', trim($path));
        $segs = array_values(array_filter(explode('/', $path), function($s){ return $s !== '' && $s !== '.'; }));
        $enc = array_map(function($s){ return rawurlencode($s); }, $segs);
        return implode('/', $enc);
    }
}

$attachments = [];
if ($hasAttachmentCol && !empty($complaint['Attachment_Path'])) {
    $raw = (string)$complaint['Attachment_Path'];
    // Split by semicolon or comma, tolerate extra spaces
    $parts = preg_split('/[;,]+/',$raw);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $norm = str_replace('\\', '/', $p);
        $encoded = encode_path_segments($norm);
        $ext = strtolower(pathinfo($norm, PATHINFO_EXTENSION));
        $attachments[] = [
            'raw' => $norm,
            'encoded' => $encoded,
            'ext' => $ext,
            'name' => basename($norm)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Complaint • Details</title>
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}},boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'},animation:{'fade-in':'fadeIn .4s ease-out'},keyframes:{fadeIn:{'0%':{opacity:0},'100%':{opacity:1}}}}}};</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <?php if ($editing): ?>
        <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
        <style>
            /* Dropdown styling to match screenshot: white box, blue selection, scrollbar */
            .tagify__dropdown {
                border: 1px solid rgba(148,163,184,.35);
                background: #fff;
                box-shadow: 0 8px 28px rgba(6,90,143,.08);
                border-radius: 6px;
            }
            .tagify__dropdown .tagify__dropdown__content {
                max-height: 260px;
                overflow-y: auto;
                padding: 6px 0;
            }
            .tagify__dropdown .tagify__dropdown__item {
                padding: 10px 14px;
                cursor: pointer;
                color: #111827;
                font-size: 14px;
            }
            .tagify__dropdown .tagify__dropdown__item--active,
            .tagify__dropdown .tagify__dropdown__item.active {
                background: #2b9cf0 !important;
                color: #fff !important;
            }
            /* ensure dropdown is above other content and full width of input */
            .tagify__dropdown { z-index: 11000; }
            .tagify__dropdown::after { display: none; }
        </style>
    <?php endif; ?>
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
    </style>
    
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>
    <?php include 'sidebar_lupon.php'; ?>
    <?php $status=strtoupper(trim($complaint['Status'])); $statusStyles=['PENDING'=>'bg-amber-50 text-amber-600 border border-amber-200','IN CASE'=>'bg-sky-50 text-sky-600 border border-sky-200','REJECTED'=>'bg-rose-50 text-rose-600 border border-rose-200','RESOLVED'=>'bg-emerald-50 text-emerald-600 border border-emerald-200']; $statusClass=$statusStyles[$status]??'bg-gray-100 text-gray-600 border border-gray-200'; ?>
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="assigned_case.php" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to Assigned Cases</span>
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
                <?php if ($editing): ?>
                    <form method="POST" id="editForm">
                        <input type="hidden" name="update_complaint" value="1" />
                <?php endif; ?>
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Respondents</h2>
                    <div class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                        <?php if ($editing): ?>
                            <?php // prepare initial tags in JS-friendly format ?>
                            <input id="respondent-name" name="respondent_name" type="text" placeholder="Type to search respondents..." class="w-full border px-3 py-2 rounded" />
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
                            <?php if ($editing): ?>
                                <textarea name="complaint_details" rows="6" class="w-full border rounded p-3"><?= htmlspecialchars($complaint['Complaint_Details']) ?></textarea>
                                <input type="hidden" name="complaint_title" value="<?= htmlspecialchars(substr($complaint['Complaint_Details'],0,60)) ?>" />
                            <?php else: ?>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($complaint['Complaint_Details'])) ?></p>
                            <?php endif; ?>
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
                <?php if ($hasAttachmentCol): ?>
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Attachments</h2>
                    <?php if (empty($attachments)): ?>
                        <p class="text-sm text-gray-500 italic">No attachments provided.</p>
                    <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach ($attachments as $att): $isImg = in_array($att['ext'], ['jpg','jpeg','png','gif','webp']); $isPdf = $att['ext'] === 'pdf'; $encodedPath = $att['encoded']; $fname = $att['name']; ?>
                            <div class="rounded-xl border bg-white/70 border-gray-200 shadow-sm overflow-hidden group">
                                <?php if ($isImg): ?>
                                    <div class="w-full h-40 bg-gray-100 overflow-hidden">
                                        <img src="../<?= htmlspecialchars($encodedPath) ?>" alt="Attachment" class="w-full h-full object-cover object-center group-hover:scale-105 transition" onerror="this.src='https://via.placeholder.com/300x180?text=Missing';" />
                                    </div>
                                    <div class="p-3 flex items-center justify-between text-sm text-gray-700">
                                        <span class="truncate" title="<?= htmlspecialchars($fname) ?>"><?= htmlspecialchars($fname) ?></span>
                                        <a class="text-primary-600 hover:text-primary-800" href="../<?= htmlspecialchars($encodedPath) ?>" target="_blank" rel="noopener">View</a>
                                    </div>
                                <?php elseif ($isPdf): ?>
                                    <div class="p-4 flex items-center gap-3">
                                        <i class="fa-regular fa-file-pdf text-rose-600 text-3xl"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-800 truncate" title="<?= htmlspecialchars($fname) ?>"><?= htmlspecialchars($fname) ?></div>
                                            <a class="text-xs text-primary-600 hover:text-primary-800 underline" href="../<?= htmlspecialchars($encodedPath) ?>" target="_blank" rel="noopener">Open PDF</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 flex items-center gap-3">
                                        <i class="fa-regular fa-file text-slate-600 text-3xl"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-800 truncate" title="<?= htmlspecialchars($fname) ?>"><?= htmlspecialchars($fname) ?></div>
                                            <a class="text-xs text-primary-600 hover:text-primary-800 underline" href="../<?= htmlspecialchars($encodedPath) ?>" target="_blank" rel="noopener">Download</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="pt-2 flex">
                    <?php if ($editing): ?>
                        <div class="ml-auto flex gap-3">
                            <a href="view_complaint_details.php?id=<?= (int)$complaint_id ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                            <button type="submit" form="editForm" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition"><i class="fa fa-floppy-disk"></i> Save Changes</button>
                        </div>
                        </form>
                    <?php else: ?>
                        <a href="assigned_case.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back to Assigned Cases</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <?php $conn->close(); ?>
    <?php if ($editing): ?>
    <script>
        (function(){
            try{
                var names = <?= $resident_whitelist_json ?? '[]' ?>;
                var initial = <?= $initial_tags_json ?? '[]' ?>;
                var input = document.getElementById('respondent-name');
                if (!input) return;
                function initTagifyForInput(inputEl){
                    var options = {
                        whitelist: names,
                        dropdown: { classname: 'tags-look', enabled: 1, maxItems: 100, closeOnSelect: false, fuzzySearch: true },
                        enforceWhitelist: false,
                        editTags: 1,
                        originalInputValueFormat: valuesArr => JSON.stringify(valuesArr.map(v => ({ value: v.value })))
                    };
                    if (window.Tagify) {
                        var tagify = new Tagify(inputEl, options);
                        // preload tags
                        if (Array.isArray(initial) && initial.length) {
                            var simple = initial.map(function(t){ return t.value || t; });
                            tagify.addTags(simple);
                        }
                        // position dropdown nicely
                        function positionDropdown(){ var dd = tagify.DOM.dropdown; if(!dd) return; var rect = tagify.DOM.scope.getBoundingClientRect(); dd.style.position='fixed'; dd.style.left=Math.round(rect.left)+'px'; dd.style.top=Math.round(rect.bottom+2)+'px'; dd.style.width=Math.round(rect.width)+'px'; dd.style.zIndex='10000'; dd.style.transform='none'; }
                        tagify.on('dropdown:show', positionDropdown);
                        window.addEventListener('resize', positionDropdown);
                        window.addEventListener('orientationchange', ()=> setTimeout(positionDropdown,200));
                    } else {
                        var s = document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/@yaireo/tagify'; s.onload = function(){ initTagifyForInput(inputEl); }; document.head.appendChild(s);
                    }
                }
                initTagifyForInput(input);
            } catch(e){ console && console.warn && console.warn('Tagify init error', e); }
        })();
    </script>
    <?php endif; ?>
</body>
</html>
