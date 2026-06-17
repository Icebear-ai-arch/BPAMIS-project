<?php
// Secretary Update Case Status - Premium UI (clean file)
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';
date_default_timezone_set('Asia/Manila');
if (!isset($_GET['id'])) {
    header('Location: view_cases.php');
    exit;
}
$caseId = intval($_GET['id']);

// Resolve real table names for case-sensitive Linux hosts
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');
$T_COMPLAINT_INFO = bpamis_table($conn, 'COMPLAINT_INFO');
$T_RESIDENT_INFO = bpamis_table($conn, 'RESIDENT_INFO');
$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');
$T_BARANGAY_OFFICIALS = bpamis_table($conn, 'barangay_officials');
$T_MEDIATION_INFO = bpamis_table($conn, 'mediation_info');
$T_RESOLUTION = bpamis_table($conn, 'resolution');
$T_SETTLEMENT = bpamis_table($conn, 'settlement');
$T_ARBITRATION = bpamis_table($conn, 'arbitration');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);
$TB_BARANGAY_OFFICIALS = bpamis_quote_table($T_BARANGAY_OFFICIALS);
$TB_MEDIATION_INFO = bpamis_quote_table($T_MEDIATION_INFO);
$TB_RESOLUTION = bpamis_quote_table($T_RESOLUTION);
$TB_SETTLEMENT = bpamis_quote_table($T_SETTLEMENT);
$TB_ARBITRATION = bpamis_quote_table($T_ARBITRATION);

// Optional columns
$hasComplaintCaseType = bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'case_type');
$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID','respondent_id']);

$complaintDetailsExpr = 'ci.Complaint_Details';
if (!bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Details')) {
    if (bpamis_table_has_column($conn, $T_COMPLAINT_INFO, 'Complaint_Description')) {
        $complaintDetailsExpr = 'ci.Complaint_Description';
    } else {
        $complaintDetailsExpr = 'ci.Complaint_Title';
    }
}

// Fetch core case info (guard prepare and provide fallback)
$sql =
    "SELECT cs.Case_ID, cs.Case_Status, cs.Date_Opened,\n" .
    "        ci.Complaint_ID, ci.Complaint_Title, {$complaintDetailsExpr} AS Complaint_Details, ci.Date_Filed, " .
    ($hasComplaintCaseType ? "ci.case_type" : "NULL") . " AS case_type,\n" .
    "        comp.First_Name AS Complainant_First, comp.Last_Name AS Complainant_Last,\n" .
    ($respondentIdCol ? "        resp.First_Name AS Respondent_First, resp.Last_Name AS Respondent_Last\n" : "        NULL AS Respondent_First, NULL AS Respondent_Last\n") .
    "    FROM {$TB_CASE_INFO} cs\n" .
    "    LEFT JOIN {$TB_COMPLAINT_INFO} ci ON cs.Complaint_ID = ci.Complaint_ID\n" .
    "    LEFT JOIN {$TB_RESIDENT_INFO} comp ON ci.Resident_ID = comp.Resident_ID\n" .
    ($respondentIdCol ? ("    LEFT JOIN {$TB_RESIDENT_INFO} resp ON ci." . bpamis_quote_ident($respondentIdCol) . " = resp.Resident_ID\n") : "") .
    "    WHERE cs.Case_ID = ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $caseId);
    $stmt->execute();
    $rows = bpamis_stmt_fetch_all_assoc($stmt);
    $stmt->close();
} else {
    // Fallback: inline ID into query as integer to avoid prepare fatal; log error
    error_log('update_case_status.php prepare failed: ' . $conn->error);
    $fallbackSql = str_replace('WHERE cs.Case_ID = ?', 'WHERE cs.Case_ID = ' . $caseId, $sql);
    $result = $conn->query($fallbackSql);
    $rows = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) $rows[] = $r;
        $result->free();
    }
}

if (empty($rows)) {
    echo "<p class='text-center text-red-600'>Case not found or query error.</p>";
    echo "<p class='text-center text-xs text-gray-500'>" . htmlspecialchars($conn->error) . "</p>";
    exit;
}
$case = $rows[0];
// Capture complaint id for updates
$complaintId = isset($case['Complaint_ID']) ? intval($case['Complaint_ID']) : 0;

// Removed: Secretary does not assign Lupon Tagapamayapa

// Resolve case type display from COMPLAINT_INFO.case_type if present, else fallback to CASE_INFO.Case_Type
$caseType = '';
if (isset($case['case_type'])) {
    $caseType = trim((string)($case['case_type'] ?? ''));
}
if ($caseType === '') {
    if (bpamis_table_has_column($conn, $T_CASE_INFO, 'Case_Type')) {
        if ($__st = $conn->prepare("SELECT Case_Type FROM {$TB_CASE_INFO} WHERE Case_ID = ?")) {
            $__st->bind_param('i', $caseId);
            $__st->execute();
            $__rows = bpamis_stmt_fetch_all_assoc($__st);
            if (!empty($__rows)) {
                $caseType = trim((string)($__rows[0]['Case_Type'] ?? ''));
            }
            $__st->close();
        } else {
            $tmp = $conn->query("SELECT Case_Type FROM {$TB_CASE_INFO} WHERE Case_ID = $caseId");
            if ($tmp && $tmp->num_rows > 0) {
                $row = $tmp->fetch_assoc();
                $caseType = trim((string)($row['Case_Type'] ?? ''));
            }
        }
    }
}

$current = $case['Case_Status'];
$transitions = [
    'Open' => ['Mediation', 'Resolved', 'Closed'],
    'Mediation' => ['Resolution', 'Resolved', 'Closed'],
    'Resolution' => ['Arbitration', 'Settlement', 'Resolved', 'Closed'],
    'Arbitration' => ['Resolved', 'Closed'],
    'Settlement' => ['Resolved', 'Closed'],
    'Resolved' => ['Closed'],
    'Closed' => []
];
$available = $transitions[$current] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $caseTypeUpdated = false;

    // Optional: update complaint case_type when provided
    $postedType = isset($_POST['complaint_case_type']) ? strtolower(trim($_POST['complaint_case_type'])) : '';
    if ($postedType !== '' && $hasComplaintCaseType && $complaintId > 0) {
        if ($postedType === 'civil' || $postedType === 'civil case') { $postedType = 'civil case'; }
        elseif ($postedType === 'criminal' || $postedType === 'criminal case') { $postedType = 'criminal case'; }
        elseif ($postedType === 'blotter') { $postedType = 'blotter'; }
        else { $postedType = ''; }

        if ($postedType !== '') {
            if ($updCt = $conn->prepare("UPDATE {$TB_COMPLAINT_INFO} SET case_type = ? WHERE Complaint_ID = ?")) {
                $updCt->bind_param('si', $postedType, $complaintId);
                if (!$updCt->execute()) { error_log('updCt execute error: '.$updCt->error); }
                $updCt->close();
                $caseTypeUpdated = true;
            } else {
                $res = $conn->query("UPDATE {$TB_COMPLAINT_INFO} SET case_type='" . $conn->real_escape_string($postedType) . "' WHERE Complaint_ID=" . $complaintId);
                if (!$res) { error_log('fallback update COMPLAINT_INFO failed: '.$conn->error); }
                $caseTypeUpdated = true;
            }
        }
    }

    // Ensure the requested transition is allowed
    if (!in_array($newStatus, $available, true)) {
        error_log("Invalid status transition attempted for Case_ID $caseId: $newStatus from $current");
        header('Location: view_cases.php?status_updated=0&error=invalid_transition');
        exit;
    }

    // Update case status
    if ($upd = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Case_Status=? WHERE Case_ID=?")) {
        $upd->bind_param('si', $newStatus, $caseId);
        if (!$upd->execute()) { error_log('Update CASE_INFO error: '.$upd->error); }
        $upd->close();
    } else {
        $res = $conn->query("UPDATE {$TB_CASE_INFO} SET Case_Status='" . $conn->real_escape_string($newStatus) . "' WHERE Case_ID=$caseId");
        if (!$res) { error_log('fallback update CASE_INFO failed: '.$conn->error); }
    }

    // Notify complainant(s) (resident / external) — use prepared statements if possible
    $notifSql = "SELECT co.Resident_ID, co.External_Complainant_ID FROM {$TB_CASE_INFO} cs JOIN {$TB_COMPLAINT_INFO} co ON cs.Complaint_ID=co.Complaint_ID WHERE cs.Case_ID=?";
    $ns = null;
    $nrows = [];
    if ($ns = $conn->prepare($notifSql)) {
        $ns->bind_param('i', $caseId);
        $ns->execute();
        $nrows = bpamis_stmt_fetch_all_assoc($ns);
    } else {
        $nres = $conn->query(str_replace('WHERE cs.Case_ID=?', 'WHERE cs.Case_ID=' . $caseId, $notifSql));
        if (!$nres) {
            error_log('notifSql fallback failed: '.$conn->error);
        } else {
            while ($r = $nres->fetch_assoc()) $nrows[] = $r;
            $nres->free();
        }
    }

    if (!empty($nrows)) {
        $row = $nrows[0];
        $resident_id = $row['Resident_ID'];
        $external_id = $row['External_Complainant_ID'];
        $title = 'Case Status Updated';
        $message = "The status of your case (ID: $caseId) has been updated to \"$newStatus\".";
        $created = date('Y-m-d H:i:s');

        // Prepared insert for resident notification
        if (!empty($resident_id) && $ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
            $ins->bind_param('isss', $resident_id, $title, $message, $created);
            if (!$ins->execute()) { error_log('Insert resident notification failed: '.$ins->error); }
            $ins->close();
        } elseif (!empty($resident_id)) {
            $res = $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (resident_id,title,message,is_read,created_at) VALUES (" . intval($resident_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created) . "')");
            if (!$res) { error_log('fallback resident notification failed: '.$conn->error); }
        }

        // External complainant
        if (!empty($external_id) && $ins2 = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
            $ins2->bind_param('isss', $external_id, $title, $message, $created);
            if (!$ins2->execute()) { error_log('Insert external notification failed: '.$ins2->error); }
            $ins2->close();
        } elseif (!empty($external_id)) {
            $res = $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id,title,message,is_read,created_at) VALUES (" . intval($external_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created) . "')");
            if (!$res) { error_log('fallback external notification failed: '.$conn->error); }
        }
    }
    if ($ns) { $ns->close(); }

    // Deadlines & creating mediation/resolution/settlement/arbitration rows
    $today = date('Y-m-d');
    $deadline = date('Y-m-d', strtotime('+15 days'));

    if ($newStatus === 'Mediation') {
        $check = $conn->query("SELECT 1 FROM {$TB_MEDIATION_INFO} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_MEDIATION_INFO} (Case_ID,Mediation_Date,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert mediation_info failed: '.$conn->error);
            } else {
                $case_deadline = date('Y-m-d', strtotime('+45 days'));
                $deadline_overdue = date('Y-m-d', strtotime('+60 days'));
                $hasCaseDeadline = bpamis_table_has_column($conn, $T_CASE_INFO, 'case_deadline');
                $hasDeadlineOverdue = bpamis_table_has_column($conn, $T_CASE_INFO, 'deadline_overdue');
                if ($hasCaseDeadline || $hasDeadlineOverdue) {
                    $sets = [];
                    if ($hasCaseDeadline) $sets[] = "case_deadline='".$conn->real_escape_string($case_deadline)."'";
                    if ($hasDeadlineOverdue) $sets[] = "deadline_overdue='".$conn->real_escape_string($deadline_overdue)."'";
                    $q = "UPDATE {$TB_CASE_INFO} SET " . implode(', ', $sets) . " WHERE Case_ID=$caseId";
                    if (!$conn->query($q)) {
                        error_log('Update case_info deadlines failed: '.$conn->error);
                    }
                }
            }
        }
    } elseif ($newStatus === 'Resolution') {
        $check = $conn->query("SELECT 1 FROM {$TB_RESOLUTION} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_RESOLUTION} (Case_ID,Resolution_Date,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert resolution failed: '.$conn->error);
            }
        }

        // If status changed from Mediation -> Resolution, notify Lupon Head(s) to assign members
        if (strtolower(trim($current)) === 'mediation') {
            $notifTitle = "Case Needs Assignment (Resolution)";
            $notifMsg = "Case ID: $caseId is now in Resolution. Please assign Lupon Tagapamayapa members.";
            $notifType = "Resolution";
            $createdAt = date('Y-m-d H:i:s');

            // Fetch Lupon Head officials
            $headRows = [];
            if ($qh = $conn->prepare("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE Position LIKE ? OR Position LIKE ?")) {
                $posA = '%LuponHead%';
                $posB = '%Lupon Head%';
                $qh->bind_param('ss', $posA, $posB);
                if ($qh->execute()) {
                    $headRows = bpamis_stmt_fetch_all_assoc($qh);
                } else {
                    error_log('LuponHead lookup execute failed: '. $qh->error);
                }
                $qh->close();
            } else {
                $heads = $conn->query("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE Position LIKE '%LuponHead%' OR Position LIKE '%Lupon Head%'");
                if (!$heads) {
                    error_log('LuponHead lookup fallback failed: '.$conn->error);
                } else {
                    while ($r = $heads->fetch_assoc()) $headRows[] = $r;
                    $heads->free();
                }
            }

            if (!empty($headRows)) {
                // Prepare insert once
                $ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (official_id, type, title, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                if ($ins) {
                    foreach ($headRows as $h) {
                        $oid = intval($h['Official_ID'] ?? 0);
                        if ($oid <= 0) continue;
                        $ins->bind_param('issss', $oid, $notifType, $notifTitle, $notifMsg, $createdAt);
                        if (!$ins->execute()) {
                            error_log('Insert LuponHead notification failed: '. $ins->error);
                        }
                    }
                    $ins->close();
                } else {
                    // Fallback simple insert per head
                    foreach ($headRows as $h) {
                        $oid = intval($h['Official_ID'] ?? 0);
                        if ($oid <= 0) continue;
                        $q = "INSERT INTO {$TB_NOTIFICATIONS} (official_id, type, title, message, is_read, created_at) VALUES ($oid, '".$conn->real_escape_string($notifType)."', '".$conn->real_escape_string($notifTitle)."', '".$conn->real_escape_string($notifMsg)."', 0, '".$conn->real_escape_string($createdAt)."')";
                        if (!$conn->query($q)) { error_log('Fallback LuponHead notif insert failed: '.$conn->error); }
                    }
                }
            } else {
                error_log("No Lupon Head found to notify for Case_ID $caseId");
            }
        }
    } elseif ($newStatus === 'Settlement') {
        $check = $conn->query("SELECT 1 FROM {$TB_SETTLEMENT} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("INSERT INTO {$TB_SETTLEMENT} (Case_ID,Date_Agreed,Deadline) VALUES ($caseId,'$today','$deadline')")) {
                error_log('Insert settlement failed: '.$conn->error);
            }
        }
    } elseif ($newStatus === 'Arbitration') {
        // Create arbitration record if missing
        $check = $conn->query("SELECT 1 FROM {$TB_ARBITRATION} WHERE Case_ID=$caseId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            $arbDeadline = date('Y-m-d', strtotime('+10 days'));
            if (!$conn->query("INSERT INTO {$TB_ARBITRATION} (Case_ID, Arbitration_Date, Deadline) VALUES ($caseId, '$today', '$arbDeadline')")) {
                error_log('Insert arbitration failed: '.$conn->error);
            }
        }

      // 🔔 Notify Barangay Captain — prepared statement version
$notifTitle = "New Case Moved to Arbitration";
$notifMsg = "Case ID: $caseId has progressed to the Arbitration stage. Please review the case details.";
$notifType = "Arbitration";
$createdAt = date('Y-m-d H:i:s');

// Fetch Barangay Captain
$captainQuery = $conn->prepare("SELECT Official_ID FROM {$TB_BARANGAY_OFFICIALS} WHERE Position = ? LIMIT 1");
if ($captainQuery) {
    $position = 'Barangay Captain';
    $captainQuery->bind_param('s', $position);
    $captainQuery->execute();
    $capRows = bpamis_stmt_fetch_all_assoc($captainQuery);

    if (!empty($capRows)) {
        $captainId = intval($capRows[0]['Official_ID'] ?? 0);

        // ✅ Insert notification with type = 'Arbitration'
        $insCap = $conn->prepare("
            INSERT INTO {$TB_NOTIFICATIONS} (official_id, type, title, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        if ($insCap) {
            $insCap->bind_param('issss', $captainId, $notifType, $notifTitle, $notifMsg, $createdAt);
            if (!$insCap->execute()) {
                error_log('❌ Insert captain arbitration notification failed: ' . $insCap->error);
            }
            $insCap->close();
        } else {
            error_log('❌ Prepare insert notification failed: ' . $conn->error);
        }
    } else {
        error_log("⚠️ No Barangay Captain found in barangay_officials for Case_ID $caseId");
    }
    $captainQuery->close();
} else {
    error_log('❌ Prepare barangay_officials query failed: ' . $conn->error);
}

    }

    // Final redirect once everything done
    header('Location: view_cases.php?status_updated=1');
    exit;
}



$statusUpper = strtoupper($current);
$statusStyles = ['OPEN' => 'bg-sky-50 text-sky-600 border border-sky-200', 'MEDIATION' => 'bg-amber-50 text-amber-600 border border-amber-200', 'RESOLUTION' => 'bg-indigo-50 text-indigo-600 border border-indigo-200', 'SETTLEMENT' => 'bg-fuchsia-50 text-fuchsia-600 border border-fuchsia-200', 'RESOLVED' => 'bg-emerald-50 text-emerald-600 border border-emerald-200', 'CLOSED' => 'bg-gray-100 text-gray-700 border border-gray-300'];
$statusClass = $statusStyles[$statusUpper] ?? 'bg-primary-50 text-primary-600 border border-primary-200';

// // ✅ Check if the status is 'Arbitration'
// if (strtolower($new_status) === 'arbitration') {
//     // Create a notification for the captain
//     $title = "Case #$case_id is now in Arbitration";
//     $message = "The case with ID #$case_id has entered arbitration. Please review and take appropriate action.";

//     $insertNotif = $conn->prepare("
//         INSERT INTO notifications (title, message, type, target_role, status, created_at)
//         VALUES (?, ?, 'Arbitration', 'Captain', 'Unread', NOW())
//     ");
//     $insertNotif->bind_param("ss", $title, $message);
//     $insertNotif->execute();
// }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Case • Update Status</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: { 50: '#f0f7ff', 100: '#e0effe', 200: '#bae2fd', 300: '#7cccfd', 400: '#36b3f9', 500: '#0c9ced', 600: '#0281d4', 700: '#026aad', 800: '#065a8f', 900: '#0a4b76' } }, boxShadow: { glow: '0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)' }, animation: { 'fade-in': 'fadeIn .4s ease-out' }, keyframes: { fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } } } } } };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        .glass {
            background: linear-gradient(140deg, rgba(255, 255, 255, .92), rgba(255, 255, 255, .68));
            backdrop-filter: blur(14px) saturate(140%);
            -webkit-backdrop-filter: blur(14px) saturate(140%);
        }

        .field-label {
            font-size: 11px;
            letter-spacing: .05em;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
        }
    </style>
</head>

<body
    class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden">
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-96 h-96 bg-primary-200 opacity-30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[30rem] h-[30rem] bg-primary-300 opacity-20 rounded-full blur-3xl">
        </div>
    </div>
    <?php include '../includes/lupon_head_nav.php'; ?>
 
    <main class="relative z-10 max-w-4xl mx-auto px-4 md:px-8 pt-10 pb-24 animate-fade-in">
        <div class="mb-8 flex items-center gap-3"><a href="view_cases.php"
                class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition"><span
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i
                        class="fa fa-arrow-left"></i></span><span class="ml-2">Back to Cases</span></a></div>
        <section
            class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
            <div class="absolute inset-0 pointer-events-none">
                <div
                    class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40">
                </div>
            </div>
            <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-8">
                <div class="flex items-center">
                    <div
                        class="w-20 h-20 rounded-2xl flex items-center justify-center bg-primary-50 ring-4 ring-primary-100 shadow-inner">
                        <i class="fa fa-scale-balanced text-3xl text-primary-600"></i></div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1
                        class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span
                            class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Case
                            #<?= htmlspecialchars($case['Case_ID']) ?></span><span
                            class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full <?= $statusClass ?> shadow-sm"><i
                                class="fa fa-circle text-[8px]"></i>
                            <?= htmlspecialchars($case['Case_Status']) ?></span></h1>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500"><span
                            class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> Opened
                            <?= date('F d, Y', strtotime($case['Date_Opened'])) ?></span><span
                            class="inline-flex items-center gap-1"><i class="fa fa-file-lines"></i> Filed
                            <?= date('F d, Y', strtotime($case['Date_Filed'])) ?></span></div>
                </div>
            </header>
            <div class="space-y-10">
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Case Context</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Complainant</p>
                            <p class="font-semibold text-gray-800">
                                <?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></p>
                        </div>
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Respondent</p>
                            <p class="text-gray-700">
                                <?= htmlspecialchars(trim($case['Respondent_First'] . ' ' . $case['Respondent_Last'])) ?>
                            </p>
                        </div>
                        <div
                            class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm md:col-span-2">
                            <p class="field-label mb-1">Complaint Description</p>
                            <div class="text-gray-800 whitespace-pre-line break-words">
                                <?= nl2br(htmlspecialchars($case['Complaint_Details'] ?? '')) ?>
                            </div>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Case Type</p>
                            <?php 
                                $ct = $caseType; 
                                $ctLower = strtolower(trim($ct));
                                // Normalize values like 'civil', 'civil case', 'criminal case', 'blotter'
                                if ($ctLower === 'civil case') $ctLower = 'civil';
                                if ($ctLower === 'criminal case') $ctLower = 'criminal';
                                $badge = 'bg-gray-100 text-gray-700 border border-gray-200';
                                if ($ctLower==='civil') $badge='bg-sky-50 text-sky-700 border border-sky-200';
                                elseif ($ctLower==='criminal') $badge='bg-rose-50 text-rose-700 border border-rose-200';
                                elseif ($ctLower==='blotter') $badge='bg-slate-50 text-slate-700 border border-slate-200';
                            ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[12px] font-semibold <?= $badge ?>">
                                <i class="fa fa-tag"></i> <?= htmlspecialchars($ct !== '' ? ($ctLower==='civil' ? 'Civil Case' : ($ctLower==='criminal' ? 'Criminal Case' : ($ctLower==='blotter' ? 'Blotter' : ucfirst($ctLower)))) : 'Not set') ?>
                            </span>
                        </div>
                    </div>
                    
                </div>
                <!-- Lupon Tagapamayapa assignment UI removed for Secretary -->
                <div>
                    <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Update Status</h2>
                    <form method="POST" class="space-y-5">
                        <?php if ($hasComplaintCaseType && trim($caseType) === ''): ?>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <label class="field-label mb-2 block">Case Type</label>
                                    <select name="complaint_case_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium" required>
                                        <option value="" disabled selected>Select case type</option>
                                        <option value="civil">Civil</option>
                                        <option value="criminal">Criminal</option>
                                        <option value="blotter">Blotter</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">Required: choose the case type to be saved to the complaint record.</p>
                                    <?php if (!empty($_GET['case_type_updated'])): ?>
                                        <p class="mt-1 text-xs text-emerald-700 font-medium">Case type saved.</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($available): ?>
                                        <label class="field-label mb-2 block">Select New Status</label>
                                        <select name="status"
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium">
                                            <?php foreach ($available as $s): ?>
                                                <option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-red-600 font-medium">This case is closed. No further updates permitted.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($available): ?>
                                <div>
                                    <label class="field-label mb-2 block">Select New Status</label>
                                    <select name="status"
                                            class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white/80 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition text-sm font-medium">
                                        <?php foreach ($available as $s): ?>
                                            <option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="rounded-xl border border-gray-200 bg-white/70 p-4 text-sm text-red-600 font-medium">This case is closed. No further updates permitted.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <a href="view_cases.php"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i
                                    class="fa fa-arrow-left"></i> Cancel</a>
                            <button type="submit" <?= !$available ? 'disabled' : '' ?>
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition disabled:opacity-60 disabled:cursor-not-allowed"><i
                                    class="fa fa-save"></i> Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
    <?php $conn->close(); ?>
</body>

</html>

