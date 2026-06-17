<?php
require_once(__DIR__ . '/../server/server.php');
include '../controllers/session_control.php';
if (!isset($_GET['id'])) {
    echo "<p class='text-center text-red-500'>Case ID not provided.</p>";
    exit;
}

$caseId = intval($_GET['id']);
$success = '';
$error = '';

// Detect optional lupon_assign column
$hasLuponAssign = false;
if ($res = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'lupon_assign'")) {
    $hasLuponAssign = $res->num_rows > 0;
}

// Fetch Lupon Tagapamayapa names for suggestions
$luponNames = [];
$luponQ = $conn->query("SELECT name FROM barangay_officials WHERE LOWER(TRIM(Position)) = 'lupon tagapamayapa' ORDER BY name ASC");
if ($luponQ && $luponQ->num_rows > 0) {
    while ($r = $luponQ->fetch_assoc()) { $luponNames[] = trim($r['name']); }
}

// Fetch minimal case info and current assignment
$sql = "SELECT cs.Case_ID, cs.Case_Status, cs.Date_Opened, " .
       ($hasLuponAssign ? "cs.lupon_assign" : "NULL AS lupon_assign") . ",\n" .
       "       ci.Complaint_ID, ci.Complaint_Title, ci.Complaint_Details, ci.Date_Filed, ci.case_type,\n" .
       "       comp.First_Name AS Complainant_First, comp.Last_Name AS Complainant_Last,\n" .
       "       resp.First_Name AS Respondent_First, resp.Last_Name AS Respondent_Last\n" .
       "  FROM CASE_INFO cs\n" .
       "  LEFT JOIN COMPLAINT_INFO ci ON cs.Complaint_ID = ci.Complaint_ID\n" .
       "  LEFT JOIN RESIDENT_INFO comp ON ci.Resident_ID = comp.Resident_ID\n" .
       "  LEFT JOIN RESIDENT_INFO resp ON ci.Respondent_ID = resp.Resident_ID\n" .
       " WHERE cs.Case_ID = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='text-center text-red-500'>Failed to prepare query.</p>";
    exit;
}
$stmt->bind_param('i', $caseId);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);
if (!$result || $result->num_rows === 0) {
    echo "<p class='text-center text-red-500'>Case not found.</p>";
    exit;
}
$case = $result->fetch_assoc();
$stmt->close();

// Respondents display
$respondent_names = [];
if (!empty($case['Respondent_First']) || !empty($case['Respondent_Last'])) {
    $respondent_names[] = trim(($case['Respondent_First'] ?? '') . ' ' . ($case['Respondent_Last'] ?? ''));
}
$complaintId = (int)($case['Complaint_ID'] ?? 0);
if ($complaintId > 0) {
    $addResSql = "SELECT ri.First_Name, ri.Last_Name FROM COMPLAINT_RESPONDENTS cr JOIN RESIDENT_INFO ri ON cr.Respondent_ID = ri.Resident_ID WHERE cr.Complaint_ID = ?";
    if ($rs = $conn->prepare($addResSql)) {
        $rs->bind_param('i', $complaintId);
        $rs->execute();
        $res2 = bpamis_stmt_get_result($rs);
        while ($r = $res2->fetch_assoc()) {
            $respondent_names[] = trim(($r['First_Name'] ?? '') . ' ' . ($r['Last_Name'] ?? ''));
        }
        $rs->close();
    }
}
$respondents_display = !empty($respondent_names) ? implode(', ', array_filter($respondent_names)) : 'N/A';

// Handle submit: Assign Lupon Tagapamayapa only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lupon_assign']) && $hasLuponAssign) {
    $assignInput = trim((string)$_POST['lupon_assign']);
    // Normalize comma-separated list, dedupe, and enforce max 20 selections
    $names = array_values(array_filter(array_map(function($x){ return trim(preg_replace('/\s+/', ' ', $x)); }, explode(',', $assignInput))));
    // Deduplicate while preserving order
    $seen = [];
    $uniqueNames = [];
    foreach ($names as $nm) {
        $key = mb_strtolower($nm);
        if ($nm !== '' && !isset($seen[$key])) { $seen[$key] = true; $uniqueNames[] = $nm; }
        if (count($uniqueNames) >= 20) { break; }
    }
    $names = $uniqueNames;
    $assignStr = implode(', ', $names);
    $okAssign = false;
    if ($m = $conn->prepare('UPDATE CASE_INFO SET lupon_assign = ? WHERE Case_ID = ?')) {
        $m->bind_param('si', $assignStr, $caseId);
        $okAssign = $m->execute();
        $m->close();
    } else {
        $okAssign = (bool)$conn->query("UPDATE CASE_INFO SET lupon_assign='".$conn->real_escape_string($assignStr)."' WHERE Case_ID=".$caseId);
    }

    if ($okAssign) {
        // Send notifications to each assigned Lupon (match by Name -> Official_ID)
        if (!empty($names)) {
            $findStmt = $conn->prepare("SELECT Official_ID FROM barangay_officials WHERE Position = 'Lupon Tagapamayapa' AND Name = ? LIMIT 1");
            $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, is_read) VALUES (?, ?, ?, NOW(), ?, 0)");
            $title = 'New Case Assignment';
            $messageTpl = 'You have been assigned to Case #' . $caseId . ' as part of the Lupon Tagapamayapa.';
            if ($findStmt && $notifStmt) {
                foreach ($names as $nm) {
                    if ($nm === '') continue;
                    $findStmt->bind_param('s', $nm);
                    $findStmt->execute();
                    $fres = bpamis_stmt_get_result($findStmt);
                    if ($fres && $fres->num_rows > 0) {
                        $oid = (int)$fres->fetch_assoc()['Official_ID'];
                        $t = $title; $msg = $messageTpl; $type = 'Case';
                        $notifStmt->bind_param('sssi', $t, $msg, $type, $oid);
                        $notifStmt->execute();
                    }
                }
                $findStmt->close();
                $notifStmt->close();
            }
        }
        $success = 'Lupon assignment updated successfully.';
        // Refresh case variable to show new assignment
        if ($st = $conn->prepare("SELECT lupon_assign FROM CASE_INFO WHERE Case_ID = ?")) {
            $st->bind_param('i', $caseId);
            $st->execute();
            $r = bpamis_stmt_get_result($st);
            if ($r && $row = $r->fetch_assoc()) { $case['lupon_assign'] = $row['lupon_assign']; }
            $st->close();
        }
    } else {
        $error = 'Failed to update Lupon assignment.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case • Assign Lupon</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: {50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'} }, boxShadow: { glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)' }, animation: { 'fade-in':'fadeIn .4s ease-out', 'float':'float 6s ease-in-out infinite' }, keyframes: { fadeIn: { '0%':{opacity:0}, '100%':{opacity:1} }, float: { '0%,100%':{ transform:'translateY(0)' }, '50%':{ transform:'translateY(-8px)' } } } } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
    </style>
    <datalist id="luponOptions">
        <?php foreach ($luponNames as $nm): ?>
            <option value="<?= htmlspecialchars($nm) ?>"></option>
        <?php endforeach; ?>
    </datalist>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl"></div>
    </div>
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3">
            <a href="view_cases.php" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                <span class="ml-2">Back to Cases</span>
            </a>
        </div>
        <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
            </div>
            <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-6">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center bg-primary-50 ring-4 ring-primary-100 shadow-inner">
                    <i class="fa fa-gavel text-3xl text-primary-600"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Assign Lupon • Case #<?= htmlspecialchars($case['Case_ID']) ?></span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-white/80 border border-primary-200 text-primary-700 shadow-sm"><i class="fa fa-circle"></i> <?= htmlspecialchars($case['Case_Status']) ?></span>
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-user"></i> <?= htmlspecialchars(trim(($case['Complainant_First'] ?? '') . ' ' . ($case['Complainant_Last'] ?? ''))) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> <?= date('F d, Y', strtotime($case['Date_Filed'])) ?></span>
                    </div>
                </div>
            </header>

            <?php if (!empty($success)): ?>
                <div class="mb-6 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-check-circle mt-0.5"></i><span><?= htmlspecialchars($success) ?></span></div>
            <?php elseif (!empty($error)): ?>
                <div class="mb-6 rounded-lg border border-rose-300 bg-rose-50 text-rose-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-circle-exclamation mt-0.5"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <div class="space-y-8">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Context</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="group rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <p class="field-label mb-1">Complaint Details</p>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($case['Complaint_Details'] ?? '')) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <p class="field-label mb-1">Respondents</p>
                            <p class="text-gray-700 leading-relaxed"><?= htmlspecialchars($respondents_display) ?></p>
                        </div>
                    </div>
                </div>

                <?php if ($hasLuponAssign): ?>
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Assign Lupon Tagapamayapa</h2>
                    <form method="POST" class="space-y-3" onsubmit="return confirm('Save Lupon assignment?');">
                        <div>
                            <label class="field-label mb-2 block" for="luponInput"><i class="fa fa-users"></i> Add Lupon (max 20)</label>
                            <div id="luponChips" class="flex flex-wrap gap-2 mb-2"></div>
                            <input type="hidden" id="luponAssignHidden" name="lupon_assign" value="<?= htmlspecialchars($case['lupon_assign'] ?? '') ?>" />
                            <input list="luponOptions" id="luponInput" class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/80" placeholder="Type a Lupon name and press Enter" />
                            <p class="mt-1 text-xs text-gray-500">Type a name and press Enter to add. Pick from suggestions. Click × on a chip to remove.</p>
                        </div>
                        <div class="pt-2 flex justify-end gap-2">
                            <a href="view_cases.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition"><i class="fa fa-save"></i> Save</button>
                        </div>
                    </form>
                    <script>
                        (function () {
                            const chipsEl = document.getElementById('luponChips');
                            const hiddenEl = document.getElementById('luponAssignHidden');
                            const inputEl = document.getElementById('luponInput');
                            if (!chipsEl || !hiddenEl || !inputEl) return;

                            // Initialize from hidden value -> array of names
                            function parseHidden() {
                                return (hiddenEl.value || '')
                                    .split(',')
                                    .map(s => s.trim())
                                    .filter(Boolean);
                            }

                            let selected = Array.from(new Set(parseHidden()));

                            function syncHidden() {
                                hiddenEl.value = selected.join(', ');
                            }

                            function render() {
                                chipsEl.innerHTML = '';
                                selected.forEach((name) => {
                                    const chip = document.createElement('span');
                                    chip.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 text-primary-700 border border-primary-200 text-xs font-medium';
                                    const txt = document.createElement('span');
                                    txt.textContent = name;
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'ml-1 inline-flex items-center justify-center h-5 w-5 rounded-full bg-primary-100 hover:bg-primary-200 text-primary-700';
                                    btn.setAttribute('aria-label', 'Remove ' + name);
                                    btn.innerHTML = '<i class="fa fa-times text-[10px]"></i>';
                                    btn.addEventListener('click', function () {
                                        selected = selected.filter(n => n !== name);
                                        render();
                                        syncHidden();
                                    });
                                    chip.appendChild(txt);
                                    chip.appendChild(btn);
                                    chipsEl.appendChild(chip);
                                });
                            }

                            function addFromInput(raw) {
                                if (!raw) return;
                                const parts = String(raw).split(',').map(s => s.trim()).filter(Boolean);
                                for (const p of parts) {
                                    if (selected.length >= 20) break; // enforce max 20
                                    if (p && !selected.some(x => x.toLowerCase() === p.toLowerCase())) {
                                        selected.push(p);
                                    }
                                }
                                render();
                                syncHidden();
                            }

                            inputEl.addEventListener('keydown', function (e) {
                                if (e.key === 'Enter' || e.key === ',') {
                                    e.preventDefault();
                                    const val = inputEl.value.replace(/,+$/, '').trim();
                                    addFromInput(val);
                                    inputEl.value = '';
                                }
                            });
                            inputEl.addEventListener('change', function () {
                                const val = inputEl.value.trim();
                                if (val) {
                                    addFromInput(val);
                                    inputEl.value = '';
                                }
                            });

                            render();
                            syncHidden();
                        })();
                    </script>
                </div>
                <?php else: ?>
                    <div class="rounded-xl border bg-amber-50 border-amber-200 p-4 text-amber-700">This system does not have a <code>CASE_INFO.lupon_assign</code> column. Lupon assignment is not available.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php include 'sidebar_.php'; ?>
</body>
</html>
