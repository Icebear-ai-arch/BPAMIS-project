<?php
include '../controllers/session_control.php';
include '../server/server.php';

if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

// Only Lupon Head should use this - simple check (position string may vary)
$pos = strtolower($_SESSION['official_position'] ?? '');
if (strpos($pos, 'lupon') === false && strpos($pos, 'head') === false) {
    // allow but show warning
}

$errors = [];
$success = '';

// Handle reassign POST (operate per case: mark all declines for a case reassigned)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_id']) && isset($_POST['new_lupon_id'])) {
    $case_id = (int)$_POST['case_id'];
    $new_lupon = (int)$_POST['new_lupon_id'];

    // Load all open declines for this case
    $dStmt = $conn->prepare("SELECT * FROM lupon_declines WHERE case_id = ? AND reassigned = 0");
    $declineRows = [];
    if ($dStmt) {
        $dStmt->bind_param('i', $case_id);
        $dStmt->execute();
        $dres = bpamis_stmt_get_result($dStmt);
        if ($dres) {
            while ($rr = $dres->fetch_assoc()) $declineRows[] = $rr;
        }
        $dStmt->close();
    }

    if (empty($declineRows)) {
        $errors[] = 'No active decline records found for that case.';
    } else {
        $old_lupons = array_values(array_unique(array_map(function($r){ return (int)$r['lupon_id']; }, $declineRows)));

        // Mark all declines for this case as reassigned
        $u = $conn->prepare("UPDATE lupon_declines SET reassigned = 1, reassigned_at = NOW(), reassigned_to_lupon_id = ? WHERE case_id = ? AND reassigned = 0");
        if ($u) {
            $u->bind_param('ii', $new_lupon, $case_id);
            $u->execute();
            $u->close();
        }

        // Determine case stage
        $stage = '';
        $s = $conn->prepare("SELECT Case_Status FROM case_info WHERE Case_ID = ? LIMIT 1");
        if ($s) {
            $s->bind_param('i', $case_id);
            $s->execute();
            $sr = bpamis_stmt_get_result($s);
            if ($sr && $r = $sr->fetch_assoc()) $stage = $r['Case_Status'];
            $s->close();
        }

        // Map stage names to candidate tables and candidate column names
        $map = [
            'Mediation' => ['mediation_info', ['mediator_name','Mediator_Name','mediator']],
            'Conciliation' => ['conciliation', ['mediator_name','Mediator_Name','mediator']],
            'Resolution' => ['resolution', ['mediator_name','Mediator_Name','mediator']],
            'Settlement' => ['settlement', ['mediator_name','Mediator_Name','mediator']],
            'Arbitration' => ['arbitration', ['mediator_name','Mediator_Name','mediator']],
        ];

        $updated = false;
        $stageKey = trim((string)$stage);
        if (isset($map[$stageKey])) {
            $tbl = $map[$stageKey][0];
            $cols = $map[$stageKey][1];

            // Resolve old decliner names for accurate replacement
            $old_names = [];
            if (!empty($old_lupons)) {
                $placeholders = implode(',', array_fill(0, count($old_lupons), '?'));
                $types = str_repeat('i', count($old_lupons));
                $sqlNames = "SELECT Official_ID, Name FROM barangay_officials WHERE Official_ID IN ($placeholders)";
                $stmtN = $conn->prepare($sqlNames);
                if ($stmtN) {
                    // bind params dynamically
                    $bindParams = array_merge([$types], $old_lupons);
                    // mysqli_stmt::bind_param requires references
                    $refs = [];
                    $refs[] = &$bindParams[0];
                    for ($i = 1; $i < count($bindParams); $i++) { $refs[] = &$bindParams[$i]; }
                    call_user_func_array([$stmtN, 'bind_param'], $refs);
                    $stmtN->execute();
                    $resN = bpamis_stmt_get_result($stmtN);
                    if ($resN) {
                        while ($rn = $resN->fetch_assoc()) {
                            $old_names[(int)$rn['Official_ID']] = trim($rn['Name']);
                        }
                    }
                    $stmtN->close();
                }
            }

            // Lookup new lupon name once
            $new_name = '';
            if ($g = $conn->prepare("SELECT Name FROM barangay_officials WHERE Official_ID = ? LIMIT 1")) {
                $g->bind_param('i', $new_lupon);
                $g->execute();
                $gr = bpamis_stmt_get_result($g);
                if ($gr && $rr = $gr->fetch_assoc()) $new_name = trim($rr['Name']);
                $g->close();
            }

            foreach ($cols as $c) {
                // Read current value of this column for the case
                $col = $conn->real_escape_string($c);
                $qGet = "SELECT " . $col . " AS current_val FROM " . $conn->real_escape_string($tbl) . " WHERE Case_ID = ? LIMIT 1";
                $getStmt = $conn->prepare($qGet);
                if (!$getStmt) continue;
                $getStmt->bind_param('i', $case_id);
                $getStmt->execute();
                $gr = bpamis_stmt_get_result($getStmt);
                $current = '';
                if ($gr && $rowc = $gr->fetch_assoc()) { $current = trim((string)$rowc['current_val']); }
                $getStmt->close();

                if ($current === '') {
                    // If empty, set to new lupon name only if there were decliners (safe default)
                    if (!empty($old_names) && $new_name !== '') {
                        $qUpd = "UPDATE " . $conn->real_escape_string($tbl) . " SET " . $col . " = ? WHERE Case_ID = ?";
                        $u = $conn->prepare($qUpd);
                        if ($u) { $u->bind_param('si', $new_name, $case_id); $u->execute(); if ($u->affected_rows>0) $updated=true; $u->close(); }
                    }
                    if ($updated) break;
                    continue;
                }

                // Split current by common separators and normalize
                $parts = preg_split('/[;,]+/', $current);
                $parts = array_map('trim', $parts);
                $replaced = false;
                foreach ($parts as $i => $p) {
                    foreach ($old_names as $oid => $oname) {
                        if ($oname === '') continue;
                        // match exact name (case-insensitive)
                        if (mb_strtolower($p) === mb_strtolower($oname)) {
                            $parts[$i] = $new_name;
                            $replaced = true;
                        }
                    }
                }

                if ($replaced) {
                    $newVal = implode(', ', array_unique(array_filter($parts, function($x){ return $x !== ''; })));
                    $qUpd = "UPDATE " . $conn->real_escape_string($tbl) . " SET " . $col . " = ? WHERE Case_ID = ?";
                    $u = $conn->prepare($qUpd);
                    if ($u) { $u->bind_param('si', $newVal, $case_id); $u->execute(); if ($u->affected_rows>0) $updated=true; $u->close(); }
                }
                if ($updated) break;
            }
        }

        // Also update case_info.lupon_assign field if present so the assigned_case view picks up the new lupon
        // Read current lupon_assign value and replace any old decliner names with the new lupon name
        $qCI = $conn->prepare("SELECT lupon_assign FROM case_info WHERE Case_ID = ? LIMIT 1");
        if ($qCI) {
            $qCI->bind_param('i', $case_id);
            $qCI->execute();
            $resCI = bpamis_stmt_get_result($qCI);
            $luponAssignCurrent = '';
            if ($resCI && $rci = $resCI->fetch_assoc()) {
                $luponAssignCurrent = trim((string)$rci['lupon_assign']);
            }
            $qCI->close();

            if ($luponAssignCurrent === '') {
                if (!empty($old_names) && $new_name !== '') {
                    $uCI = $conn->prepare("UPDATE case_info SET lupon_assign = ? WHERE Case_ID = ?");
                    if ($uCI) { $uCI->bind_param('si', $new_name, $case_id); $uCI->execute(); if ($uCI->affected_rows>0) $updated=true; $uCI->close(); }
                }
            } else {
                $parts = preg_split('/[;,]+/', $luponAssignCurrent);
                $parts = array_map('trim', $parts);
                $repl = false;
                foreach ($parts as $pi => $pval) {
                    foreach ($old_names as $oid => $oname) {
                        if ($oname === '') continue;
                        if (mb_strtolower($pval) === mb_strtolower($oname)) {
                            $parts[$pi] = $new_name;
                            $repl = true;
                        }
                    }
                }
                if ($repl) {
                    $newAssignVal = implode(', ', array_unique(array_filter($parts, function($x){ return $x !== ''; })));
                    $uCI = $conn->prepare("UPDATE case_info SET lupon_assign = ? WHERE Case_ID = ?");
                    if ($uCI) { $uCI->bind_param('si', $newAssignVal, $case_id); $uCI->execute(); if ($uCI->affected_rows>0) $updated=true; $uCI->close(); }
                }
            }
        }

        // Notify new lupon
        $titleNew = "You have been reassigned: Case #{$case_id}";
        $msgNew = "You have been reassigned to Case #{$case_id} by Lupon Head.";
        $insNew = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, official_id, is_read) VALUES (?, ?, 'Case', NOW(), ?, ?, 0)");
        if ($insNew) {
            $insNew->bind_param('ssii', $titleNew, $msgNew, $new_lupon, $new_lupon);
            $insNew->execute();
            $insNew->close();
        }

        // Notify all original decliners
        $titleOld = "Case re-assigned: Case #{$case_id}";
        $msgOld = "The case you declined (Case #{$case_id}) has been reassigned to another Lupon.";
        $insOld = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, official_id, is_read) VALUES (?, ?, 'Case', NOW(), ?, ?, 0)");
        if ($insOld) {
            foreach ($old_lupons as $ol) {
                $insOld->bind_param('ssii', $titleOld, $msgOld, $ol, $ol);
                $insOld->execute();
            }
            $insOld->close();
        }

        $success = 'Case reassigned successfully.';
    }
}

// Fetch pending declines
$declines = [];
$qr = $conn->query("SELECT d.id, d.case_id, d.lupon_id, d.reason, d.created_at, bo.Name AS lupon_name FROM lupon_declines d LEFT JOIN barangay_officials bo ON d.lupon_id = bo.Official_ID WHERE d.reassigned = 0 ORDER BY d.created_at DESC");
if ($qr && $qr->num_rows > 0) {
    while ($r = $qr->fetch_assoc()) $declines[] = $r;
}

// Group declines by case_id so we show one card per case with all decliners listed
$cases = [];
foreach ($declines as $d) {
    $cid = (int)$d['case_id'];
    if (!isset($cases[$cid])) {
        $cases[$cid] = [
            'case_id' => $cid,
            'decliners' => [],
            'reasons' => [],
            'first_submitted' => $d['created_at']
        ];
    }
    $cases[$cid]['decliners'][] = ['id' => (int)$d['id'], 'lupon_id' => (int)$d['lupon_id'], 'name' => $d['lupon_name'] ?: 'Unknown'];
    $cases[$cid]['reasons'][] = $d['reason'];
    // keep earliest submitted timestamp
    if (strtotime($d['created_at']) < strtotime($cases[$cid]['first_submitted'])) {
        $cases[$cid]['first_submitted'] = $d['created_at'];
    }
}
// Re-index cases for iteration
$cases = array_values($cases);

// Fetch available Lupon list
$lupons = [];
$lr = $conn->query("SELECT Official_ID, Name FROM barangay_officials WHERE Position LIKE '%Lupon%' ORDER BY Name ASC");
if ($lr && $lr->num_rows > 0) {
    while ($l = $lr->fetch_assoc()) $lupons[] = $l;
}

// For each case, compute assigned lupon IDs from stage tables so we can
// exclude already-assigned lupons from the reassign dropdown.
if (!empty($cases)) {
    $stageMap = [
        'Mediation' => ['mediation_info', ['mediator_name','Mediator_Name']],
        'Conciliation' => ['conciliation', ['mediator_name','Mediator_Name']],
        'Resolution' => ['resolution', ['mediator_name','Mediator_Name']],
        'Settlement' => ['settlement', ['mediator_name','Mediator_Name']],
        'Arbitration' => ['arbitration', ['mediator_name','Mediator_Name']],
    ];

    foreach ($cases as $idx => $case) {
        $case_id = (int)$case['case_id'];
        $assigned_ids = [];

        // get case status
        $cs = $conn->prepare("SELECT Case_Status FROM case_info WHERE Case_ID = ? LIMIT 1");
        $caseStatus = '';
        if ($cs) {
            $cs->bind_param('i', $case_id);
            $cs->execute();
            $cres = bpamis_stmt_get_result($cs);
            if ($cres && $crow = $cres->fetch_assoc()) $caseStatus = trim((string)$crow['Case_Status']);
            $cs->close();
        }

        if ($caseStatus !== '' && isset($stageMap[$caseStatus])) {
            $tbl = $stageMap[$caseStatus][0];
            $cols = $stageMap[$caseStatus][1];

            foreach ($cols as $col) {
                $colEsc = $conn->real_escape_string($col);
                $tblEsc = $conn->real_escape_string($tbl);
                $q = "SELECT " . $colEsc . " AS current_val FROM " . $tblEsc . " WHERE Case_ID = ? LIMIT 1";
                $g = $conn->prepare($q);
                if (!$g) continue;
                $g->bind_param('i', $case_id);
                $g->execute();
                $gr = bpamis_stmt_get_result($g);
                $current = '';
                if ($gr && $r = $gr->fetch_assoc()) $current = trim((string)$r['current_val']);
                $g->close();

                if ($current === '') continue;

                $parts = preg_split('/[;,]+/', $current);
                $parts = array_map('trim', $parts);
                foreach ($parts as $p) {
                    if ($p === '') continue;
                    // normalize and try to map to Official_ID
                    $p_clean = trim(str_replace(',', '', $p));
                    $p_lower = mb_strtolower($p_clean);
                    $foundId = 0;

                    // exact match
                    $s = $conn->prepare("SELECT Official_ID FROM barangay_officials WHERE LOWER(Name) = ? LIMIT 1");
                    if ($s) {
                        $s->bind_param('s', $p_lower);
                        $s->execute();
                        $sr = bpamis_stmt_get_result($s);
                        if ($sr && $rr = $sr->fetch_assoc()) $foundId = (int)$rr['Official_ID'];
                        $s->close();
                    }

                    // token-based LIKE match
                    if (!$foundId) {
                        $tokens = preg_split('/\s+/', $p_clean);
                        $tokens = array_filter(array_map('trim', $tokens));
                        if (!empty($tokens)) {
                            $likes = [];
                            foreach ($tokens as $t) $likes[] = "LOWER(Name) LIKE ?";
                            $sql = "SELECT Official_ID FROM barangay_officials WHERE " . implode(' AND ', $likes) . " LIMIT 1";
                            $stmt = $conn->prepare($sql);
                            if ($stmt) {
                                $bindVals = [];
                                foreach ($tokens as $t) $bindVals[] = '%' . mb_strtolower($t) . '%';
                                $types = str_repeat('s', count($bindVals));
                                $refs = [];
                                $refs[] = &$types;
                                for ($i = 0; $i < count($bindVals); $i++) { $refs[] = & $bindVals[$i]; }
                                call_user_func_array([$stmt, 'bind_param'], $refs);
                                $stmt->execute();
                                $sr2 = bpamis_stmt_get_result($stmt);
                                if ($sr2 && $r2 = $sr2->fetch_assoc()) $foundId = (int)$r2['Official_ID'];
                                $stmt->close();
                            }
                        }
                    }

                    if ($foundId) $assigned_ids[] = $foundId;
                }
            }
        }

        $cases[$idx]['assigned_ids'] = array_values(array_unique($assigned_ids));
    }
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reassign Declined Cases</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.10),0 4px 18px -2px rgba(6,90,143,.18)'}, animation:{'fade-in':'fadeIn .4s ease-out'}, keyframes:{fadeIn:{'0%':{opacity:0},'100%':{opacity:1}}} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .bg-orbs:before,.bg-orbs:after{content:"";position:absolute;border-radius:9999px;filter:blur(70px);opacity:.35}
        .bg-orbs:before{width:480px;height:480px;background:linear-gradient(135deg,#7cccfd,#0c9ced);top:-160px;left:-140px}
        .bg-orbs:after{width:420px;height:420px;background:linear-gradient(135deg,#bae2fd,#7cccfd);bottom:-140px;right:-120px}
        .glass{background:linear-gradient(145deg,rgba(255,255,255,.9),rgba(255,255,255,.7));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%)}
        .input-base{width:100%;border-radius:.65rem;border:1px solid rgba(209,213,219,.7);background:rgba(255,255,255,.65);padding:.65rem .75rem;font-size:.85rem;transition:.2s}
        .input-base:not(textarea){height:44px;line-height:1.2}
        .input-base:focus{outline:none;background:#fff;border-color:#36b3f9;box-shadow:0 0 0 4px rgba(12,156,237,.25)}
        .field-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;display:flex;gap:6px;align-items:center;color:#4b5563}
        .btn-primary{background:linear-gradient(135deg,#0c9ced,#0281d4);color:#fff;padding:.65rem 1.5rem;border-radius:.65rem;font-weight:600;font-size:.875rem;box-shadow:0 4px 14px -2px rgba(12,156,237,.4);transition:.2s}
        .btn-primary:hover{background:linear-gradient(135deg,#0281d4,#026aad);box-shadow:0 6px 20px -2px rgba(12,156,237,.5)}
        .btn-secondary{background:rgba(255,255,255,.7);color:#4b5563;border:1px solid rgba(209,213,219,.7);padding:.65rem 1.25rem;border-radius:.65rem;font-weight:500;font-size:.875rem;transition:.2s}
        .btn-secondary:hover{background:#fff;border-color:#9ca3af}
        
        @media (max-width: 640px) {
            html, body { overflow-x: hidden !important; max-width: 100vw !important; position: relative !important; }
            * { box-sizing: border-box !important; }
            header:not(nav *), main, section, div:not(nav *, nav) { max-width: 100% !important; }
            body > *:not(nav) { overflow-x: hidden !important; }
            .max-w-screen-2xl, .max-w-7xl { max-width: 100vw !important; }
            .bg-orbs:before { width:280px !important; height:280px !important; top:-100px !important; left:-80px !important; }
            .bg-orbs:after { width:240px !important; height:240px !important; bottom:-80px !important; right:-60px !important; }
            body > header { padding-top: 1rem !important; padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
            body > header .glass { padding: 1rem !important; }
            body > header h1 { font-size: 1.125rem !important; line-height: 1.4 !important; }
            body > header h1 .w-12 { width: 2.25rem !important; height: 2.25rem !important; font-size: 0.875rem !important; }
            body > header p { font-size: 0.7rem !important; margin-top: 0.5rem !important; }
            body > header .flex.flex-wrap.gap-3 { gap: 0.375rem !important; font-size: 0.6rem !important; }
            body > header .flex.flex-wrap.gap-3 > div { padding: 0.25rem 0.5rem !important; }
            body > header .absolute.-top-10 { width: 6rem !important; height: 6rem !important; top: -1.5rem !important; right: -1.5rem !important; }
            body > header .absolute.-bottom-12 { width: 8rem !important; height: 8rem !important; bottom: -2rem !important; left: -2rem !important; }
            main { padding-left: 0.75rem !important; padding-right: 0.75rem !important; margin-top: 1.5rem !important; padding-bottom: 3rem !important; }
            main > section { padding: 1rem !important; }
            main section .space-y-8 { gap: 1rem !important; }
            main .pb-6 { padding-bottom: 1rem !important; }
            main .space-y-3 { gap: 0.5rem !important; }
            .field-label { font-size: 9px !important; margin-bottom: 3px !important; gap: 3px !important; }
            .input-base { height: 38px !important; padding: 0.5rem 0.625rem !important; font-size: 0.7rem !important; border-radius: 0.375rem !important; }
            .input-base:not(textarea) { line-height: 1.1 !important; }
            #lupon-rows { gap: 0.5rem !important; margin-bottom: 0.5rem !important; }
            #lupon-rows .flex.gap-2 { gap: 0.375rem !important; }
            #lupon-rows input { font-size: 0.7rem !important; height: 38px !important; }
            #lupon-rows button { width: 2.5rem !important; height: 38px !important; font-size: 0.75rem !important; }
            #lupon-dup-warning { font-size: 0.65rem !important; margin-bottom: 0.5rem !important; }
            #lupon-container p.text-xs { font-size: 0.65rem !important; margin-bottom: 0.5rem !important; }
            #lupon-hint { font-size: 0.65rem !important; }
            #add-lupon { padding: 0.5rem 0.75rem !important; font-size: 0.7rem !important; border-radius: 0.375rem !important; gap: 0.25rem !important; }
            #add-lupon i { font-size: 0.65rem !important; }
            .btn-primary, .btn-secondary { padding: 0.5rem 0.875rem !important; font-size: 0.7rem !important; border-radius: 0.375rem !important; gap: 0.375rem !important; }
            main form .flex.flex-col.sm\:flex-row { gap: 0.5rem !important; padding-top: 0.75rem !important; }
            .rounded-lg.border { padding: 0.625rem 0.75rem !important; font-size: 0.7rem !important; gap: 0.375rem !important; }
            .rounded-lg.border i { font-size: 0.75rem !important; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
<?php include '../includes/lupon_head_nav.php'; ?>
<?php include 'sidebar_.php'; ?>
<div class="max-w-6xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-primary-200 to-primary-400 flex items-center justify-center text-white shadow">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div>
                <h1 class="text-2xl font-semibold">Reassign Declined Cases</h1>
                <p class="text-sm text-gray-500">Assign declined cases to an available Lupon</p>
            </div>
        </div>
    </div>
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): foreach($errors as $e): ?>
        <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; endif; ?>

    <?php if (empty($declines)): ?>
        <div class="bg-white p-6 rounded shadow text-gray-600">No declined cases awaiting reassignment.</div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach($cases as $c): ?>
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
                <div class="sm:flex sm:items-start sm:justify-between gap-6">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Case <span class="font-medium text-gray-700">#<?= htmlspecialchars($c['case_id']) ?></span></div>
                                <div class="text-xs text-gray-400">First submitted: <?= htmlspecialchars($c['first_submitted']) ?></div>
                            </div>
                            <div class="ml-4 text-sm text-gray-600">Declined by:
                                <div class="inline-block ml-2 text-sm font-medium text-primary-700"><?= htmlspecialchars(implode(', ', array_map(function($x){ return $x['name']; }, $c['decliners']))) ?></div>
                            </div>
                        </div>

                        <div class="mt-3 text-sm text-gray-700">
                            <div class="font-semibold mb-1">Reasons</div>
                            <div class="prose-sm max-h-32 overflow-auto pr-2 text-gray-700">
                                <ul class="list-disc ml-5"><?php foreach($c['reasons'] as $r): ?><li><?= nl2br(htmlspecialchars($r)) ?></li><?php endforeach; ?></ul>
                            </div>
                        </div>
                    </div>

                    <div class="w-full sm:w-72 mt-4 sm:mt-0">
                        <form id="form-<?= (int)$c['case_id'] ?>" method="POST">
                            <input type="hidden" name="case_id" value="<?= (int)$c['case_id'] ?>">
                            <?php
                                // Build array of lupon IDs who declined this case so we can exclude them
                                $blocked = array_map(function($x){ return (int)$x['lupon_id']; }, $c['decliners']);
                                // Also exclude lupon who are already assigned to this case (if detected)
                                if (!empty($c['assigned_ids'])) {
                                    $blocked = array_merge($blocked, array_map('intval', $c['assigned_ids']));
                                }
                                $blocked = array_values(array_unique($blocked, SORT_NUMERIC));

                                // Build human-readable excluded names list (decliners + assigned)
                                $excluded_names = array_map(function($x){ return $x['name']; }, $c['decliners']);
                                if (!empty($c['assigned_ids'])) {
                                    // fetch assigned names
                                    $place = implode(',', array_fill(0, count($c['assigned_ids']), '?'));
                                    $types = str_repeat('i', count($c['assigned_ids']));
                                    $sql = "SELECT Official_ID, Name FROM barangay_officials WHERE Official_ID IN ($place)";
                                    $st = $conn->prepare($sql);
                                    if ($st) {
                                        $bindParams = array_merge([$types], $c['assigned_ids']);
                                        $refs = [];
                                        $refs[] = &$bindParams[0];
                                        for ($i = 1; $i < count($bindParams); $i++) { $refs[] = &$bindParams[$i]; }
                                        call_user_func_array([$st, 'bind_param'], $refs);
                                        $st->execute();
                                        $resn = bpamis_stmt_get_result($st);
                                        if ($resn) {
                                            while ($rn = $resn->fetch_assoc()) $excluded_names[] = $rn['Name'];
                                        }
                                        $st->close();
                                    }
                                }
                                $excluded_names = array_values(array_unique(array_filter(array_map('trim', $excluded_names))));
                            ?>

                            <label class="field-label">Assign to</label>
                            <select id="select-<?= (int)$c['case_id'] ?>" name="new_lupon_id" required class="input-base mb-2" aria-label="Select Lupon for case <?= (int)$c['case_id'] ?>">
                                <option value="">Select Lupon to assign</option>
                                <?php $hasOptions = false; foreach($lupons as $lp):
                                    $oid = (int)$lp['Official_ID'];
                                    if (in_array($oid, $blocked, true)) continue; // skip original decliner / assigned
                                    $hasOptions = true;
                                ?>
                                    <option value="<?= $oid ?>"><?= htmlspecialchars($lp['Name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (empty($hasOptions)): ?>
                                <div class="text-xs text-gray-500 mb-3">No available Lupon to assign (all Lupon either declined or already assigned).</div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400 mb-2">Excluded: <?= htmlspecialchars(implode(', ', $excluded_names) ?: '—') ?></div>
                            <?php endif; ?>

                            <button type="button" onclick="openConfirm(<?= (int)$c['case_id'] ?>)" class="btn-primary w-full <?= empty($hasOptions) ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= empty($hasOptions) ? 'disabled' : '' ?>>Reassign Case</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

<!-- Confirmation modal & scripts -->
<div id="confirm-modal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-40 z-50">
    <div class="bg-white rounded-lg shadow-xl w-11/12 max-w-md p-6">
        <h3 class="text-lg font-semibold mb-2">Confirm Reassignment</h3>
        <p id="confirm-text" class="text-sm text-gray-700 mb-4">Are you sure you want to reassign this case?</p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeConfirm()" class="btn-secondary">Cancel</button>
            <button id="confirm-action" class="btn-primary">Confirm</button>
        </div>
    </div>
</div>

<script>
function openConfirm(caseId){
    const sel = document.getElementById('select-'+caseId);
    if(!sel || !sel.value) return alert('Please select a Lupon to assign.');
    const name = sel.options[sel.selectedIndex].text;
    document.getElementById('confirm-text').textContent = `Reassign case #${caseId} to ${name}?`;
    document.getElementById('confirm-modal').classList.remove('hidden');
    document.getElementById('confirm-modal').classList.add('flex');
    const btn = document.getElementById('confirm-action');
    btn.onclick = function(){
        // submit the corresponding form
        document.getElementById('form-'+caseId).submit();
    };
}
function closeConfirm(){
    document.getElementById('confirm-modal').classList.remove('flex');
    document.getElementById('confirm-modal').classList.add('hidden');
}
</script>