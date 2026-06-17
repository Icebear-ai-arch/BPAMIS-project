<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Redirect if not logged in
if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

$allowed_statuses = ['Conciliation', 'Arbitration'];
// Get filter status from GET request (default to Conciliation). Clamp to allowed statuses only.
$status_filter = $_GET['status'] ?? 'Conciliation';
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'Conciliation';
}
$preselect_case_id = null;
if (isset($_GET['id'])) {
    // Support both numeric Case_ID and human-friendly case_original_id in the ?id= param
    $idParam = $_GET['id'];
    if (is_numeric($idParam)) {
        $preselect_case_id = intval($idParam);
        $statusStmt = $conn->prepare("SELECT Case_Status FROM case_info WHERE Case_ID = ? LIMIT 1");
        if ($statusStmt) {
            $statusStmt->bind_param('i', $preselect_case_id);
            $statusStmt->execute();
            $sr = bpamis_stmt_get_result($statusStmt);
            if ($sr && $sr->num_rows > 0) {
                $r = $sr->fetch_assoc();
                if (!empty($r['Case_Status'])) {
                    $caseStatus = $r['Case_Status'];
                    if ($caseStatus === 'Resolution') $caseStatus = 'Conciliation';
                    if (in_array($caseStatus, $allowed_statuses, true)) {
                        $status_filter = $caseStatus;
                    }
                }
            }
            $statusStmt->close();
        }
    } else {
        // treat as case_original_id and look up the numeric Case_ID
        $orig = trim($idParam);
        $lookup = $conn->prepare("SELECT Case_ID, Case_Status FROM case_info WHERE case_original_id = ? LIMIT 1");
        if ($lookup) {
            $lookup->bind_param('s', $orig);
            $lookup->execute();
            $lr = bpamis_stmt_get_result($lookup);
            if ($lr && $lr->num_rows > 0) {
                $rowL = $lr->fetch_assoc();
                $preselect_case_id = (int)$rowL['Case_ID'];
                if (!empty($rowL['Case_Status'])) {
                    $cs = $rowL['Case_Status'];
                    if ($cs === 'Resolution') $cs = 'Conciliation';
                    if (in_array($cs, $allowed_statuses, true)) $status_filter = $cs;
                }
            }
            $lookup->close();
        }
    }
}

// If redirected after successful assignment, show a flash message
if (isset($_GET['assigned']) && $_GET['assigned'] == '1') {
    $success = "Case assigned successfully.";
}

    // Load Lupon officials (for client-side selection). Include both 'Lupon Tagapamayapa' and 'Lupon-Hepe' variants.
    $luponOptions = [];
    $loSql = "SELECT Name FROM barangay_officials WHERE Position LIKE 'Lupon%' ORDER BY Name ASC";
    $loRes = $conn->query($loSql);
    if ($loRes && $loRes->num_rows > 0) {
        while ($r = $loRes->fetch_assoc()) {
            $luponOptions[] = $r['Name'];
        }
    }

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id_raw = isset($_POST['case_id']) ? trim((string)$_POST['case_id']) : null;
    $case_id = null; // numeric Case_ID to use throughout
    if ($case_id_raw !== null && $case_id_raw !== '') {
        // If numeric, use directly; otherwise treat as case_original_id and resolve to numeric Case_ID
        if (ctype_digit($case_id_raw)) {
            $case_id = intval($case_id_raw);
        } else {
            $lk = $conn->prepare("SELECT Case_ID FROM case_info WHERE case_original_id = ? LIMIT 1");
            if ($lk) {
                $lk->bind_param('s', $case_id_raw);
                $lk->execute();
                $lr = bpamis_stmt_get_result($lk);
                if ($lr && $lr->num_rows > 0) {
                    $case_id = (int)$lr->fetch_assoc()['Case_ID'];
                }
                $lk->close();
            }
        }
    }
    $lupon_names = $_POST['lupon_name'] ?? [];
    $status_type = $_POST['status_type'] ?? '';

    // Only require lupon names when assigning to Conciliation or Arbitration
    $requires_lupon = in_array($status_type, ['Conciliation', 'Arbitration'], true);

    // Server-side enforce: when Lupon required, ensure between 3 and 5 non-empty names
    $lupon_count_valid = true;
    if ($requires_lupon) {
        $validLuponCount = 0;
        foreach ($lupon_names as $n) {
            if (trim((string)$n) !== '') $validLuponCount++;
        }
        if ($validLuponCount < 3 || $validLuponCount > 5) {
            $lupon_count_valid = false;
            $error = "Please provide between 3 and 5 Lupon Tagapamayapa names.";
        }
    }

    // Additional server-side duplicate check: do not allow the same mediator name more than once (case-insensitive)
    if ($requires_lupon && $lupon_count_valid) {
        $clean = [];
        foreach ($lupon_names as $n) {
            $v = trim((string)$n);
            if ($v !== '') $clean[] = $v;
        }
        $lowerCounts = [];
        $hasDup = false;
        foreach ($clean as $v) {
            $lk = mb_strtolower($v);
            if (isset($lowerCounts[$lk])) { $hasDup = true; break; }
            $lowerCounts[$lk] = 1;
        }
        if ($hasDup) {
            $lupon_count_valid = false;
            $error = "Duplicate mediator names detected. Please remove duplicates before assigning.";
        }
    }

    if (!empty($case_id) && (!$requires_lupon || $lupon_count_valid) && !empty($status_type)) {
        $table_map = [
            'Conciliation' => 'conciliation',
            'Arbitration' => 'arbitration'
        ];

        if (!isset($table_map[$status_type])) {
            $error = "Invalid status type.";
        } else {
            // Use the table mapping directly (Conciliation -> conciliation table)
            $table_name = $table_map[$status_type];
            $names_string = implode(', ', array_map('trim', $lupon_names));

            // Prepare update statement
            $stmt = $conn->prepare("UPDATE $table_name SET mediator_name = ? WHERE case_id = ?");
            $stmt->bind_param("si", $names_string, $case_id);

            // Execute the update statement
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Normal success path: row existed and was updated
                    $success = "Case assigned successfully with multiple Lupon Tagapamayapa in $status_type stage.";
                } else {
                    // No rows updated: check whether the row exists at all
                    $chk = $conn->prepare("SELECT 1 FROM $table_name WHERE case_id = ? LIMIT 1");
                    if ($chk) {
                        $chk->bind_param('i', $case_id);
                        $chk->execute();
                        $cres = bpamis_stmt_get_result($chk);
                        $rowExists = ($cres && $cres->num_rows > 0);
                        $chk->close();
                    } else {
                        $rowExists = false;
                    }

                    if (!$rowExists) {
                        // Row missing: create a starter row using the same pattern as update_case_status.php
                        if ($table_name === 'conciliation') {
                            // conciliation table uses Resolution_Date column for backwards compatibility with earlier code
                            $insSql = "INSERT INTO conciliation (Case_ID, Resolution_Date, Deadline) VALUES (?, NOW(), NULL)";
                        } elseif ($table_name === 'resolution') {
                            // fall back if some parts of the app still use the legacy 'resolution' table
                            $insSql = "INSERT INTO resolution (Case_ID, Resolution_Date, Deadline) VALUES (?, NOW(), NULL)";
                        } else {
                            $insSql = "INSERT INTO arbitration (Case_ID, Arbitration_Date, Deadline) VALUES (?, NOW(), NULL)";
                        }
                        $ins = $conn->prepare($insSql);
                        if ($ins) {
                            $ins->bind_param('i', $case_id);
                            if ($ins->execute()) {
                                // Try updating mediator_name again
                                $stmt->close();
                                $stmt2 = $conn->prepare("UPDATE $table_name SET mediator_name = ? WHERE case_id = ?");
                                if ($stmt2) {
                                    $stmt2->bind_param('si', $names_string, $case_id);
                                    if ($stmt2->execute() && $stmt2->affected_rows > 0) {
                                        $success = "Case assigned successfully with multiple Lupon Tagapamayapa in $status_type stage.";
                                    } else {
                                        $error = "Failed to assign case after creating $table_name record.";
                                    }
                                    $stmt2->close();
                                } else {
                                    $error = "Failed to prepare mediator update after creating $table_name row: " . $conn->error;
                                }
                            } else {
                                $error = "Failed to create $table_name record: " . $conn->error;
                            }
                            $ins->close();
                        } else {
                            $error = "Failed to prepare insert for $table_name: " . $conn->error;
                        }
                    } else {
                        // Row exists but no rows affected; maybe the mediator_name is identical already
                        $curQ = $conn->prepare("SELECT mediator_name FROM $table_name WHERE case_id = ? LIMIT 1");
                        if ($curQ) {
                            $curQ->bind_param('i', $case_id);
                            $curQ->execute();
                            $cr = bpamis_stmt_get_result($curQ);
                            if ($cr && ($crow = $cr->fetch_assoc())) {
                                if (trim($crow['mediator_name'] ?? '') === trim($names_string)) {
                                    $success = "Case already assigned to the same Lupon Tagapamayapa in $status_type stage.";
                                } else {
                                    $error = "No matching case found in $status_type table.";
                                }
                            } else {
                                $error = "No matching case found in $status_type table.";
                            }
                            $curQ->close();
                        } else {
                            $error = "No matching case found in $status_type table.";
                        }
                    }
                }
            } else {
                $error = "Failed to assign case. Please try again.";
            }

            // Only proceed with lupon notifications if we have success
            if (!empty($success) && $requires_lupon) {
                // Prepare notification insert statement ONCE before the loop
                $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, is_read) VALUES (?, ?, ?, NOW(), ?, 0)");
                if (!$notifStmt) {
                    die("Notification statement prepare failed: " . $conn->error);
                }

                // Loop to find each Lupon and insert notification
                foreach ($lupon_names as $fullName) {
                    $fullName = trim($fullName);
                    if (empty($fullName)) continue;

                    // Accept Lupon Tagapamayapa and Lupon-Hepe naming variants
                    $searchSql = "SELECT Official_ID FROM barangay_officials WHERE Position LIKE 'Lupon%' AND Name = ?";
                    $searchStmt = $conn->prepare($searchSql);
                    $searchStmt->bind_param("s", $fullName);
                    $searchStmt->execute();
                    $searchResult = bpamis_stmt_get_result($searchStmt);

                    if ($searchResult && $searchResult->num_rows > 0) {
                        $luponRow = $searchResult->fetch_assoc();
                        $luponId = $luponRow['Official_ID'];

                        $title = "New Case Assigned";
                        $message = "A new case #$case_id has been assigned to you in the $status_type stage.";
                        $type = "Case";

                        $notifStmt->bind_param("sssi", $title, $message, $type, $luponId);
                        $notifStmt->execute();
                    }
                    $searchStmt->close();
                }
                $notifStmt->close();
            }

            $stmt->close();

            // Redirect after successful assignment to avoid form re-submission
            if (empty($error) && !empty($success)) {
                $redirect = 'assign_case.php?status=' . urlencode($status_type) . '&id=' . intval($case_id) . '&assigned=1';
                header('Location: ' . $redirect);
                exit;
            }
        }
    } else {
        $error = "Please fill out all required fields.";
    }

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign a Case</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
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
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/lupon_head_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    
    <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
        <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-user-plus text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Assign a Case</span>
                    </h1>
                    <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Assign cases to Lupon Tagapamayapa for Conciliation or Arbitration proceedings.</p>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-users text-primary-500"></i> Multi-Lupon</div>
                </div>
            </div>
        </div>
    </header>

    <main class="relative z-10 max-w-7xl mx-auto px-4 md:px-8 mt-10 pb-10">
        <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 p-6 md:p-10 animate-fade-in space-y-8">
            
            <?php if (!empty($success)): ?>
                <div class="rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2">
                    <i class="fa fa-check-circle mt-0.5"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="rounded-lg border border-red-300 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-start gap-2">
                    <i class="fa fa-circle-exclamation mt-0.5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Status Filter -->
            <div class="pb-6 border-b border-dashed border-primary-200/60">
                <form method="GET" class="space-y-3">
                    <label for="status" class="field-label"><i class="fa fa-filter"></i> Filter by Status</label>
                    <select id="status" name="status" class="input-base">
                        <option value="Conciliation" <?= $status_filter === 'Conciliation' ? 'selected' : '' ?>>Conciliation</option>
                        <option value="Arbitration" <?= $status_filter === 'Arbitration' ? 'selected' : '' ?>>Arbitration</option>
                    </select>
                </form>
            </div>

            <!-- Assign Form -->
            <form method="POST" class="space-y-8">
                <input type="hidden" name="status_type" value="<?= htmlspecialchars($status_filter) ?>">

                <div>
                    <label for="case_id" class="field-label"><i class="fa fa-briefcase"></i> Select Case <span class="text-red-600">*</span></label>
                    <select id="case_id" name="case_id" required class="input-base">
                        <option value="">-- Select a Case --</option>
                        <?php
            // If conciliation is selected, accept both legacy 'Resolution' and 'Conciliation' values
                if ($status_filter === 'Conciliation') {
                $sql = "SELECT ci.Case_ID,
                               COALESCE(ci.case_original_id, ci.Case_ID) AS case_original_id,
                               COALESCE(CONCAT(r.First_Name, ' ', r.Last_Name), CONCAT(e.First_Name, ' ', e.Last_Name), 'Unknown') AS complainant_name,
                               COALESCE(CONCAT(r2.First_Name, ' ', r2.Last_Name), 'N/A') AS main_respondent_name
                    FROM case_info ci
                    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                    LEFT JOIN RESIDENT_INFO r ON co.Resident_ID = r.Resident_ID
                    LEFT JOIN EXTERNAL_COMPLAINANT e ON co.External_Complainant_ID = e.External_Complaint_ID
                    LEFT JOIN RESIDENT_INFO r2 ON co.Respondent_ID = r2.Resident_ID
                    WHERE ci.Case_Status IN ('Resolution','Conciliation')
                    ORDER BY ci.Case_ID ASC";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $result = bpamis_stmt_get_result($stmt);
            } else {
                $sql = "SELECT ci.Case_ID,
                               COALESCE(ci.case_original_id, ci.Case_ID) AS case_original_id,
                               COALESCE(CONCAT(r.First_Name, ' ', r.Last_Name), CONCAT(e.First_Name, ' ', e.Last_Name), 'Unknown') AS complainant_name,
                               COALESCE(CONCAT(r2.First_Name, ' ', r2.Last_Name), 'N/A') AS main_respondent_name
                    FROM case_info ci
                    JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                    LEFT JOIN RESIDENT_INFO r ON co.Resident_ID = r.Resident_ID
                    LEFT JOIN EXTERNAL_COMPLAINANT e ON co.External_Complainant_ID = e.External_Complaint_ID
                    LEFT JOIN RESIDENT_INFO r2 ON co.Respondent_ID = r2.Resident_ID
                    WHERE ci.Case_Status = ?
                    ORDER BY ci.Case_ID ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $status_filter);
                $stmt->execute();
                $result = bpamis_stmt_get_result($stmt);
            }
                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $optCaseId = (int)$row['Case_ID'];
                                                $selected = ($preselect_case_id !== null && $optCaseId === $preselect_case_id) ? ' selected' : '';
                                                $complainant_name = $row['complainant_name'] ?? ($row['Complaint_Title'] ?? 'Unknown');
                                                $main_respondent_name = $row['main_respondent_name'] ?? 'N/A';
                                                // Prefer human-friendly case_original_id when available. If missing, fall back to 'CASE {ID}' so the frontend always receives a consistent id string
                                                $caseOriginal = isset($row['case_original_id']) && $row['case_original_id'] !== null && $row['case_original_id'] !== '' ? $row['case_original_id'] : 'CASE ' . $row['Case_ID'];
                                                $label = $caseOriginal . ' - ' . $complainant_name . ' vs. ' . $main_respondent_name;
                                                // Option value will be the human-friendly id (or 'CASE {ID}' fallback), include data-case-id for numeric mapping in JS
                                                $optValue = $caseOriginal;
                                                echo '<option value="' . htmlspecialchars($optValue) . '" data-case-id="' . htmlspecialchars($row['Case_ID']) . '"' . $selected . '>' .
                                                    htmlspecialchars($label) .
                                                    '</option>';
                                            }
                        } else {
                            echo '<option disabled>No cases found for this status</option>';
                        }
                $stmt->close();
                    ?>
                    </select>
                </div>

                <div id="lupon-container">
                    <label class="field-label"><i class="fa fa-users"></i> Lupon Tagapamayapa Name(s) <span class="text-red-600">*</span></label>
                    <p class="text-xs text-gray-500 mb-3">Assign between 3 and 5 Lupon members. No duplicates allowed.</p>
                    <!-- Rows container: individual lupon fields are rendered here -->
                    <div id="lupon-rows" class="space-y-2 mb-3"></div>
                    <div id="lupon-dup-warning" class="text-sm text-red-600 mb-3"></div>
                    <!-- Single add button controls adding new rows (max 5) -->
                    <div>
                        <button type="button" id="add-lupon" onclick="addLuponField()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium shadow-sm transition">
                            <i class="fa fa-plus"></i> Add Lupon
                        </button>
                    </div>
                </div>
                <!-- Datalist of existing Lupon officials for quick selection -->
                <datalist id="lupon-list">
                    <?php foreach ($luponOptions as $lo): ?>
                        <option value="<?= htmlspecialchars($lo) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-dashed border-primary-200/60">
                    <a href="assigned_case.php" class="btn-secondary inline-flex items-center justify-center gap-2">
                        <i class="fa fa-xmark"></i> Cancel
                    </a>
                    <button type="submit" id="assign-btn" class="btn-primary inline-flex items-center justify-center gap-2">
                        <i class="fa fa-paper-plane"></i> Assign Case
                    </button>
                </div>
            </form>

        </section>
    </main>

    <script>
document.addEventListener('DOMContentLoaded', function(){
    // Toast / top-right notification helper (modal-like but non-blocking)
    function ensureToastContainer(){
        let c = document.getElementById('toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'toast-container';
            c.style.position = 'fixed';
            c.style.top = '1rem';
            c.style.right = '1rem';
            c.style.zIndex = '99999';
            c.style.display = 'flex';
            c.style.flexDirection = 'column';
            c.style.gap = '0.5rem';
            document.body.appendChild(c);
        }
        return c;
    }

    function showToast(message, type = 'info', timeout = 5000){
        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = 'bp-toast';
        toast.style.minWidth = '240px';
        toast.style.maxWidth = '360px';
        toast.style.padding = '0.75rem 1rem';
        toast.style.borderRadius = '0.75rem';
        toast.style.boxShadow = '0 6px 18px rgba(12, 156, 237, 0.12)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'flex-start';
        toast.style.justifyContent = 'space-between';
        toast.style.gap = '0.75rem';
        toast.style.color = '#0f172a';
        toast.style.fontSize = '0.95rem';
        toast.style.background = type === 'error' ? '#fee2e2' : (type === 'success' ? '#ecfdf5' : '#eff6ff');
        toast.style.border = type === 'error' ? '1px solid #fecaca' : (type === 'success' ? '1px solid #bbf7d0' : '1px solid #bfdbfe');

        const content = document.createElement('div');
        content.style.flex = '1';
        content.style.marginRight = '0.5rem';
        content.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.style.background = 'transparent';
        closeBtn.style.border = 'none';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.color = '#475569';
        closeBtn.style.fontSize = '1rem';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => { toast.remove(); });

        toast.appendChild(content);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        // Auto-hide after timeout
        setTimeout(() => {
            try { toast.remove(); } catch (e) {}
        }, timeout);
    }
    // If the page was loaded after a successful assignment (redirect with ?assigned=1),
    // suppress the 'no mediators assigned' informational toast to avoid confusing the user.
    const suppressEmptyMediatorsToast = (new URLSearchParams(window.location.search).get('assigned') === '1');
    const statusSelect = document.getElementById('status');
    const luponContainer = document.getElementById('lupon-container');
    const caseSelect = document.getElementById('case_id');

    // Helper: fetch and parse JSON, but return clear errors for non-JSON or non-OK responses
    function safeFetchJSON(url){
        // use credentials:'include' to ensure session cookie is sent in all environments
        return fetch(url, {credentials: 'include'}).then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error(text || response.statusText || 'Server error'); });
            }
            return response.text().then(text => {
                try {
                    return text === '' ? {} : JSON.parse(text);
                } catch (e) {
                    // return server body as error to show more useful info
                    throw new Error(text || 'Invalid JSON response from server');
                }
            });
        });
    }

    // Populate case select safely, retrying a few times if element is not yet in the DOM
    function populateCaseSelect(casesArray, attempt = 0){
        const sel = document.getElementById('case_id');
        if (!sel) {
            if (attempt < 5) {
                // try again shortly (element may be re-rendered by other code)
                return setTimeout(() => populateCaseSelect(casesArray, attempt+1), 150);
            }
            console.error('Case select element not found when trying to populate options');
            return;
        }
        sel.innerHTML = '<option value="">-- Select a Case --</option>';
        (casesArray || []).forEach(c => {
            const opt = document.createElement('option');
            // value: prefer human-friendly original id if present, otherwise numeric id
            opt.value = c.case_original_id ? c.case_original_id : c.case_id;
            // always expose the numeric case id for client-side calls
            opt.dataset.caseId = c.case_id;
            if (c.label) opt.textContent = c.label;
            else if (c.case_original_id) opt.textContent = `${c.case_original_id} - ${c.title || ''}`;
            else opt.textContent = `${c.case_id} - ${c.title || ''}`;
            sel.appendChild(opt);
        });
    }

    function createLuponField(value = '', isExisting = false){
        const div = document.createElement('div');
        div.className = 'flex gap-2';
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'lupon_name[]';
        input.setAttribute('list','lupon-list');
        input.className = 'input-base flex-1';
        input.placeholder = 'Enter the Lupon Tagapamayapa name';
        input.value = value;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'inline-flex items-center justify-center w-11 h-11 rounded-lg bg-red-500 hover:bg-red-600 text-white shadow-sm transition remove-lupon';
        btn.innerHTML = '<i class="fa fa-minus"></i>';
        btn.addEventListener('click', function(){ removeLuponField(this); });
        if (isExisting) {
            // mark existing DB mediators so they are visible but not removable
            input.dataset.existing = '1';
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.title = 'Existing mediator (cannot remove)';
        }

        div.appendChild(input);
        div.appendChild(btn);
        // validate duplicates on input change
        input.addEventListener('input', validateDuplicates);
        return div;
    }

    function validateDuplicates(){
        const inputs = Array.from(document.querySelectorAll('#lupon-rows input[name="lupon_name[]"]'));
        const values = inputs.map(i => i.value.trim().toLowerCase()).filter(v => v !== '');
        const counts = {};
        let dupFound = false;
        values.forEach(v => { counts[v] = (counts[v] || 0) + 1; if (counts[v] > 1) dupFound = true; });
        const warn = document.getElementById('lupon-dup-warning');
        const assignBtn = document.getElementById('assign-btn');
        if (dupFound){
            if (warn) warn.textContent = 'Duplicate mediator names found. Please remove duplicates before assigning.';
            if (assignBtn) assignBtn.disabled = true;
        } else {
            if (warn) warn.textContent = '';
            if (assignBtn) assignBtn.disabled = false;
        }
    }

    function updateAddRemoveState(){
        const rows = document.querySelectorAll('#lupon-rows input[name="lupon_name[]"]');
        const count = rows.length;
        // disable/enable remove buttons
        document.querySelectorAll('#lupon-rows .remove-lupon').forEach(b => {
            // do not re-enable buttons that were disabled because the mediator exists in DB
            if (b.previousSibling && b.previousSibling.dataset && b.previousSibling.dataset.existing === '1') {
                b.disabled = true;
                b.style.opacity = '0.6';
                b.style.cursor = 'not-allowed';
                return;
            }
            b.disabled = count <= 3;
            b.style.opacity = count <= 3 ? '0.6' : '1';
            b.style.cursor = count <= 3 ? 'not-allowed' : 'pointer';
        });
        // enable/disable add button
        const addBtn = document.getElementById('add-lupon');
        if (addBtn) {
            addBtn.disabled = count >= 5;
            addBtn.style.opacity = count >= 5 ? '0.6' : '1';
            addBtn.style.cursor = count >= 5 ? 'not-allowed' : 'pointer';
        }
        // update hint text if present
        const hint = document.getElementById('lupon-hint');
        if (hint) {
            const existingCount = document.querySelectorAll('#lupon-rows input[data-existing="1"]').length;
            const remaining = Math.max(0, 5 - existingCount - (Array.from(rows).filter(i=>!i.dataset.existing).length));
            hint.textContent = existingCount > 0 ? `Existing mediators: ${existingCount}. You may add up to ${Math.max(0,5-existingCount)} total (you can add ${Math.max(0,5-existingCount - Array.from(rows).filter(i=>!i.dataset.existing).length)} more).` : '';
        }
    }

    function addLuponField(){
        const rowsDiv = document.getElementById('lupon-rows');
        const count = rowsDiv.querySelectorAll('input[name="lupon_name[]"]').length;
        if (count >= 5) { alert('You can add up to 5 Lupon names only.'); return; }
        rowsDiv.appendChild(createLuponField(''));
        updateAddRemoveState();
    }

    function removeLuponField(button){
        const rowsDiv = document.getElementById('lupon-rows');
        const inputs = rowsDiv.querySelectorAll('input[name="lupon_name[]"]');
        if (inputs.length <= 3) { alert('At least 3 Lupon names are required.'); return; }
        button.parentElement.remove();
        updateAddRemoveState();
    }

    function updateLuponVisibility(){
        const val = (statusSelect.value || '').toLowerCase();
        const rowsDiv = document.getElementById('lupon-rows');
        if (val === 'conciliation' || val === 'arbitration'){
            luponContainer.style.display = '';
            // ensure at least 3 inputs exist
            while (rowsDiv.querySelectorAll('input[name="lupon_name[]"]').length < 3) {
                rowsDiv.appendChild(createLuponField(''));
            }
            rowsDiv.querySelectorAll('input[name="lupon_name[]"]')
                .forEach(i => i.required = true);
        } else {
            luponContainer.style.display = 'none';
            rowsDiv.querySelectorAll('input[name="lupon_name[]"]').forEach(i => i.required = false);
        }
        updateAddRemoveState();
        validateDuplicates();
    }

    // expose add function for inline button compatibility
    window.addLuponField = addLuponField;

    if (statusSelect){
        // When status changes, fetch the matching cases and update the case dropdown.
        statusSelect.addEventListener('change', function(){
            const status = this.value;
            // update hidden input
            if (statusTypeInput) statusTypeInput.value = status;

            // fetch cases for this status
            safeFetchJSON(`get_cases.php?status=${encodeURIComponent(status)}`)
                .then(data => {
                    if (!data || data.error){ alert(data && data.error ? data.error : 'Failed to load cases for selected status.'); return; }
                    // repopulate case select (safe, may retry if element temporarily missing)
                    populateCaseSelect(data.cases || []);
                    // after updating cases, ensure lupon visibility/padding
                    updateLuponVisibility();
                })
                .catch(err => { console.error('get_cases error:', err); alert('Failed to load cases: ' + err.message); });

            // also call visibility update right away
            updateLuponVisibility();
        });

        // run initial visibility and (if a status is already selected) trigger loading
        updateLuponVisibility();
        // if the page was loaded with a preselected case id, we keep it selected; otherwise fetch initial cases
        <?php if ($preselect_case_id === null): ?>
        // load cases for the initial status on page load
        safeFetchJSON(`get_cases.php?status=${encodeURIComponent(statusSelect.value)}`)
            .then(data => {
                if (!data || data.error) return;
                const sel = document.getElementById('case_id');
                sel.innerHTML = '<option value="">-- Select a Case --</option>';
                (data.cases || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.case_original_id ? c.case_original_id : c.case_id;
                    opt.dataset.caseId = c.case_id;
                    if (c.label) opt.textContent = c.label;
                    else if (c.case_original_id) opt.textContent = `${c.case_original_id} - ${c.title || ''}`;
                    else opt.textContent = `${c.case_id} - ${c.title || ''}`;
                    sel.appendChild(opt);
                });
            })
            .catch(err => { console.error('initial get_cases error:', err); });
        <?php else: ?>
        // If a preselected case exists (from ?id=), ensure the server-rendered selection remains.
        <?php endif; ?>
    }

    // Keep hidden input in sync with status select so POST carries chosen status even when JS runs
    const statusTypeInput = document.querySelector('input[name="status_type"]');
    if (statusSelect && statusTypeInput) {
        statusSelect.addEventListener('change', function(){ statusTypeInput.value = this.value; });
        // ensure initial sync
        statusTypeInput.value = statusSelect.value;
    }

    if (caseSelect){
            caseSelect.addEventListener('change', function(){
            // selected value may be a human-friendly case_original_id; numeric mapping stored in data-case-id
            const selectedOpt = this.options[this.selectedIndex];
            // prefer numeric dataset.caseId; if missing, try to parse value as integer
            let caseId = null;
            if (selectedOpt && selectedOpt.dataset && selectedOpt.dataset.caseId) {
                caseId = selectedOpt.dataset.caseId;
            } else if (this.value && !isNaN(parseInt(this.value))) {
                caseId = parseInt(this.value);
            } else {
                // fallback: send the raw value (server will attempt to resolve by original id)
                caseId = this.value;
            }
            const status = statusSelect.value || '<?= htmlspecialchars($status_filter) ?>';

            // If no case selected, ensure the rows container exists and show a single empty field
            if (!caseId){
                let rowsDiv = document.getElementById('lupon-rows');
                if (!rowsDiv) {
                    // create the rows container without wiping the whole luponContainer
                    rowsDiv = document.createElement('div');
                    rowsDiv.id = 'lupon-rows';
                    rowsDiv.className = 'mb-2';
                    const labelEl = luponContainer.querySelector('label');
                    if (labelEl && labelEl.nextSibling) labelEl.parentNode.insertBefore(rowsDiv, labelEl.nextSibling);
                    else luponContainer.insertBefore(rowsDiv, luponContainer.firstChild);
                } else {
                    rowsDiv.innerHTML = '';
                }
                rowsDiv.appendChild(createLuponField(''));
                updateLuponVisibility();
                return;
            }

            // Show a temporary loading indicator but keep the existing inputs intact until new data arrives
            let loadingEl = document.getElementById('lupon-loading');
            if (!loadingEl) {
                loadingEl = document.createElement('div');
                loadingEl.id = 'lupon-loading';
                loadingEl.className = 'text-sm text-gray-500 mb-2';
                loadingEl.textContent = 'Loading mediators...';
                // insert after label if present, otherwise append
                const labelEl = luponContainer.querySelector('label');
                if (labelEl && labelEl.nextSibling) labelEl.parentNode.insertBefore(loadingEl, labelEl.nextSibling);
                else luponContainer.appendChild(loadingEl);
            }

            safeFetchJSON(`get_mediators.php?case_id=${caseId}&status=${encodeURIComponent(status)}`)
            .then(data => {
                const le = document.getElementById('lupon-loading'); if (le) le.remove();
                if (!data) {
                    console.error('get_mediators error: empty response');
                    showToast('Failed to fetch mediators. Please try again later.', 'error');
                    return;
                }
                if (data.error) {
                    // Do not use blocking alerts; show a top-right notification instead.
                    // If the server returns an 'Unauthorized' error, show it as an error toast.
                    showToast(data.error || 'Failed to fetch mediators', 'error');
                    return;
                }
                const mediators = Array.isArray(data.mediators) ? data.mediators : [];

                // If there are no mediators assigned for this case, show an informative top-right notification
                // but do NOT show it when the page was loaded after a successful assignment (assigned=1)
                if (mediators.length === 0 && !suppressEmptyMediatorsToast) {
                    showToast('No mediators (Lupon Tagapamayapa) assigned for this case.', 'info');
                }

                // Rebuild the rows only on successful fetch. Ensure the rows container exists first.
                let rowsDiv = document.getElementById('lupon-rows');
                if (!rowsDiv) {
                    rowsDiv = document.createElement('div');
                    rowsDiv.id = 'lupon-rows';
                    rowsDiv.className = 'mb-2';
                    const labelEl = luponContainer.querySelector('label');
                    if (labelEl && labelEl.nextSibling) labelEl.parentNode.insertBefore(rowsDiv, labelEl.nextSibling);
                    else luponContainer.appendChild(rowsDiv);
                } else {
                    rowsDiv.innerHTML = '';
                }
                // limit to max 5
                const limitMediators = mediators.slice(0,5).map(m => (m||'').toString());
                limitMediators.forEach(name => {
                    if (name.trim() === '') return; // skip empty mediator entries
                    rowsDiv.appendChild(createLuponField(name, true));
                });

                // ensure minimum 3 fields when Lupon is required
                while (rowsDiv.querySelectorAll('input[name="lupon_name[]"]').length < 3){
                    rowsDiv.appendChild(createLuponField(''));
                }
                // add or update hint element that shows how many existing mediators and remaining slots
                let hint = document.getElementById('lupon-hint');
                if (!hint) {
                    hint = document.createElement('div');
                    hint.id = 'lupon-hint';
                    hint.className = 'text-sm text-gray-600 mb-2';
                    rowsDiv.parentNode.appendChild(hint);
                }
                updateLuponVisibility();
            })
            .catch(err => {
                const le = document.getElementById('lupon-loading'); if (le) le.remove();
                console.error('get_mediators error:', err);
                // Network or server error while fetching mediators: show a descriptive error toast.
                if (!suppressEmptyMediatorsToast) {
                    showToast('Failed to fetch mediators: ' + (err && err.message ? err.message : 'Please check your network or try again.'), 'error');
                } else {
                    // If suppressed (redirect after assignment), quietly log instead of bothering the user
                    console.debug('get_mediators error suppressed after assignment redirect:', err);
                }
            });
        });
        // If a case is already selected on page load (e.g., ?id=...), trigger change to load mediators
        if (caseSelect.value) {
            caseSelect.dispatchEvent(new Event('change'));
        }
    }

});
</script>

<?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>
