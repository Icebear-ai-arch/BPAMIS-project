<?php
/**
 * Appoint Hearing Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';
include './schedule/db-connect.php';

// Handle success message from session
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
// Handle error message from session
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selectedCase = (int) ($_POST['case_id'] ?? 0);
    $hearingTitle = $_POST['hearing_title'] ?? '';
    $hearingDate = $_POST['hearing_date'] ?? '';
    $hearingTime = $_POST['hearing_time'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $participants = $_POST['participants'];
    $remarks = trim($_POST['hearing_remarks'] ?? '');

    if ($remarks === '') {
        $remarks = 'N/A';
    }

    $hearingDateTime = $hearingDate . ' ' . $hearingTime . ':00';

    // Check if there's already a pending/upcoming schedule for this case
    $currentDateTime = date('Y-m-d H:i:s');
    $checkSchedule = $conn->prepare("SELECT case_id, hearingDateTime FROM schedule_list WHERE case_id = ? AND hearingDateTime >= ? AND visible = 1 ORDER BY hearingDateTime ASC LIMIT 1");
    $checkSchedule->bind_param('is', $selectedCase, $currentDateTime);
    $checkSchedule->execute();
    $scheduleResult = bpamis_stmt_get_result($checkSchedule);
    
    if ($scheduleResult && $scheduleResult->num_rows > 0) {
        $existingSchedule = $scheduleResult->fetch_assoc();
        $existingDateTime = date('F j, Y \a\t g:i A', strtotime($existingSchedule['hearingDateTime']));
        $_SESSION['error_message'] = "Cannot schedule a new hearing. This case already has a pending hearing scheduled for {$existingDateTime}. Please wait for the current schedule to be completed first.";
        $checkSchedule->close();
        header("Location: appoint_hearing.php");
        exit();
    }
    $checkSchedule->close();

    // Corrected: include visible and bind parameters in proper order/types
    $stmt = $conn->prepare("INSERT INTO schedule_list (case_id, visible, hearingTitle, hearingDateTime, place, participant, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $visible = 1;
    $stmt->bind_param("iisssss", $selectedCase, $visible, $hearingTitle, $hearingDateTime, $venue, $participants, $remarks);

        if ($stmt->execute()) {
            // After inserting first hearing, gather participants and related IDs once
        $residentQuery = "
            SELECT 
                co.Resident_ID, 
                cr.respondent_id AS Respondent_ID
            FROM case_info ci
            JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
            JOIN complaint_respondents cr ON ci.Complaint_ID = cr.Complaint_ID
            WHERE ci.Case_ID = $selectedCase
            LIMIT 1
        ";
        $residentResult = $conn->query($residentQuery);

        $resident_id = null;
        $respondent_id = null;
        if ($residentResult && $residentResult->num_rows > 0) {
            $resData = $residentResult->fetch_assoc();
            $resident_id = $resData['Resident_ID'];
            $respondent_id = $resData['Respondent_ID'];

            $notif_title = "Hearing Scheduled";
            $notif_message = "Your case (ID: $selectedCase) has been scheduled for a hearing on $hearingDate at $hearingTime.";
            $created_at = date('Y-m-d H:i:s');
            $type = 'Hearing';

            if (!empty($resident_id)) {
                $conn->query("
                    INSERT INTO notifications (resident_id, title, message, is_read, created_at, type)
                    VALUES ($resident_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }

            if (!empty($respondent_id)) {
                $conn->query("
                    INSERT INTO notifications (resident_id, title, message, is_read, created_at, type)
                    VALUES ($respondent_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }
        }
                // -------------------------
        // ADDITION: Handle External Complainants (so they’re included properly)
        // -------------------------
        $externalQuery = "
            SELECT 
                co.External_Complainant_ID, 
                cr.respondent_id AS Respondent_ID
            FROM case_info ci
            JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
            JOIN complaint_respondents cr ON ci.Complaint_ID = cr.Complaint_ID
            WHERE ci.Case_ID = $selectedCase
              AND co.External_Complainant_ID IS NOT NULL
            LIMIT 1
        ";
        $externalResult = $conn->query($externalQuery);
        $external_id = null;
        if ($externalResult && $externalResult->num_rows > 0) {
            $extData = $externalResult->fetch_assoc();
            $external_id = $extData['External_Complainant_ID'];
            $respondent_id = $extData['Respondent_ID'];

            $notif_title = "Hearing Scheduled";
            $notif_message = "Your case (ID: $selectedCase) has been scheduled for a hearing on $hearingDate at $hearingTime.";
            $created_at = date('Y-m-d H:i:s');
            $type = 'Hearing';

            // Store external complainant notification (different column name)
            $conn->query("
                INSERT INTO notifications (external_complaint_id, title, message, is_read, created_at, type)
                VALUES ($external_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
            ");

            if (!empty($respondent_id)) {
                $conn->query("
                    INSERT INTO notifications (resident_id, title, message, is_read, created_at, type)
                    VALUES ($respondent_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }
        }

        // -------------------------
        // NEW: Check if case is assigned to a Lupon (mediator_name) & notify Lupon
        // -------------------------
        $lupon_id = null;
        if (isset($_SESSION['lupon_name'])) {
            $lupon_name = $_SESSION['lupon_name'];

            $checkSql = "
                SELECT ci.case_id 
                FROM case_info ci
                LEFT JOIN mediation_info mi ON mi.case_id = ci.case_id AND mi.mediator_name = ?
                LEFT JOIN conciliation r ON r.case_id = ci.case_id AND r.mediator_name = ?
                LEFT JOIN arbitration   s ON s.case_id = ci.case_id AND s.mediator_name = ?
                WHERE ci.case_id = ?
                  AND (mi.case_id IS NOT NULL OR r.case_id IS NOT NULL OR s.case_id IS NOT NULL)
                LIMIT 1
            ";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("sssi", $lupon_name, $lupon_name, $lupon_name, $selectedCase);
            $checkStmt->execute();
            $checkResult = bpamis_stmt_get_result($checkStmt);

            if ($checkResult && $checkResult->num_rows > 0) {
                // Get Lupon's official_id
                $luponQuery = $conn->prepare("SELECT official_id FROM barangay_officials WHERE name = ? LIMIT 1");
                $luponQuery->bind_param("s", $lupon_name);
                $luponQuery->execute();
                $luponRes = bpamis_stmt_get_result($luponQuery);

                if ($luponRes && $luponData = $luponRes->fetch_assoc()) {
                    $lupon_id = $luponData['official_id'];

                    $notif_title = "New Hearing Assigned";
                    $notif_message = "A new hearing for Case ID: $selectedCase has been scheduled on $hearingDate at $hearingTime.";
                    $created_at = date('Y-m-d H:i:s');
                    $type = 'Hearing';

                    $conn->query("
                        INSERT INTO notifications (lupon_id, title, message, is_read, created_at, type)
                        VALUES ($lupon_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                    ");
                }
                $luponQuery->close();
            }
            $checkStmt->close();
        }

        // -------------------------
        // Notify Lupon Head about the scheduled hearing
        // -------------------------
        $luponHeadQuery = $conn->prepare("SELECT official_id FROM barangay_officials WHERE position = 'Lupon Head' LIMIT 1");
        if ($luponHeadQuery) {
            $luponHeadQuery->execute();
            $luponHeadRes = bpamis_stmt_get_result($luponHeadQuery);
            if ($luponHeadRes && $luponHeadData = $luponHeadRes->fetch_assoc()) {
                $luponhead_id = $luponHeadData['official_id'];
                
                $notif_title = "Hearing Scheduled";
                $notif_message = "A hearing for Case ID: $selectedCase has been scheduled on $hearingDate at $hearingTime.";
                $created_at = date('Y-m-d H:i:s');
                $type = 'Hearing';
                
                $conn->query("
                    INSERT INTO notifications (official_id, title, message, is_read, created_at, type)
                    VALUES ($luponhead_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }
            $luponHeadQuery->close();
        }

            // Store the inserted schedule id for reference if needed
            $firstScheduleId = $conn->insert_id;

            // NOTE: Automatic creation of follow-up mediation hearings (Mediation 2/3 and 3/3)
            // has been removed intentionally to prevent unexpected extra schedules.
            // If needed in the future, implement an explicit opt-in flow instead of
            // silently inserting additional hearings here.

        // --- Notify all participants (complainant, main respondent, other respondents) and assigned Lupon members ---
        $created_at = date('Y-m-d H:i:s');
        $notif_title = "Hearing Scheduled";
        $notif_message = "Your case (ID: $selectedCase) has been scheduled for a hearing on $hearingDate at $hearingTime.";
        $type = 'Hearing';

        // Gather complaint identifiers
        $complaintId = null;
        $resident_id = null;
        $external_id = null;
        $main_respondent = null;
        if ($ciStmt = $conn->prepare("SELECT Complaint_ID, Resident_ID, External_Complainant_ID, COALESCE(respondent_id, Respondent_ID) AS main_respondent FROM complaint_info WHERE Complaint_ID = (SELECT Complaint_ID FROM case_info WHERE Case_ID = ?) LIMIT 1")) {
            $ciStmt->bind_param('i', $selectedCase);
            $ciStmt->execute();
            $ciRes = bpamis_stmt_get_result($ciStmt);
            if ($ciRow = $ciRes->fetch_assoc()) {
                $complaintId = $ciRow['Complaint_ID'] ?? null;
                $resident_id = $ciRow['Resident_ID'] ?? null;
                $external_id = $ciRow['External_Complainant_ID'] ?? null;
                $main_respondent = $ciRow['main_respondent'] ?? null;
            }
            $ciStmt->close();
        }

        $sentResidentIds = [];

        // Notify resident complainant
        if (!empty($resident_id)) {
            $nid = (int)$resident_id;
            $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
            if ($notifStmt) {
                $notifStmt->bind_param('issss', $nid, $notif_title, $notif_message, $created_at, $type);
                $notifStmt->execute();
                $notifStmt->close();
                $sentResidentIds[$nid] = true;
            }
        } elseif (!empty($external_id)) {
            // Notify external complainant
            $eid = (int)$external_id;
            $notifStmt = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
            if ($notifStmt) {
                $notifStmt->bind_param('issss', $eid, $notif_title, $notif_message, $created_at, $type);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }

        // Notify main respondent
        if (!empty($main_respondent)) {
            $rid = (int)$main_respondent;
            if (empty($sentResidentIds[$rid])) {
                $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
                if ($notifStmt) {
                    $notifStmt->bind_param('issss', $rid, $notif_title, $notif_message, $created_at, $type);
                    $notifStmt->execute();
                    $notifStmt->close();
                    $sentResidentIds[$rid] = true;
                }
            }
        }

        // Notify other respondents from complaint_respondents
        if (!empty($complaintId)) {
            $respQ = $conn->prepare("SELECT Respondent_ID FROM complaint_respondents WHERE Complaint_ID = ?");
            if ($respQ) {
                $respQ->bind_param('i', $complaintId);
                $respQ->execute();
                $rres = bpamis_stmt_get_result($respQ);
                while ($rrow = $rres->fetch_assoc()) {
                    $rid = (int)$rrow['Respondent_ID'];
                    if (empty($sentResidentIds[$rid])) {
                        $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
                        if ($notifStmt) {
                            $notifStmt->bind_param('issss', $rid, $notif_title, $notif_message, $created_at, $type);
                            $notifStmt->execute();
                            $notifStmt->close();
                            $sentResidentIds[$rid] = true;
                        }
                    }
                }
                $respQ->close();
            }
        }

        // Notify assigned Lupon members (search mediator_name across related tables)
        // Mediator_Name fields may contain comma-separated lists; split them and notify each individual mediator.
    $luponNames = [];
    // Only look at conciliation and arbitration mediator assignments per request
    $tables = ['conciliation','arbitration'];
        foreach ($tables as $tbl) {
            $lstmt = $conn->prepare("SELECT Mediator_Name FROM $tbl WHERE Case_ID = ?");
            if ($lstmt) {
                $lstmt->bind_param('i', $selectedCase);
                $lstmt->execute();
                $lres = bpamis_stmt_get_result($lstmt);
                while ($lrow = $lres->fetch_assoc()) {
                    if (!empty($lrow['Mediator_Name'])) {
                        // Split by comma and add each trimmed name
                        $parts = array_map('trim', explode(',', $lrow['Mediator_Name']));
                        foreach ($parts as $pn) {
                            if ($pn !== '') $luponNames[] = $pn;
                        }
                    }
                }
                $lstmt->close();
            }
        }
        // Deduplicate mediator names
        $luponNames = array_values(array_unique($luponNames));
        foreach ($luponNames as $lname) {
            // find matching official(s) by partial name match
            $matchedAny = false;
            // Only match officials who are part of the Lupon (position contains 'Lupon' or is 'Lupon Head')
            $offStmt = $conn->prepare("SELECT official_id, position, name FROM barangay_officials WHERE name LIKE ? AND (position LIKE '%Lupon Tagapamayapa%' OR position = 'Lupon-Hepe')");
            if ($offStmt) {
                $like = '%' . $lname . '%';
                $offStmt->bind_param('s', $like);
                $offStmt->execute();
                $ores = bpamis_stmt_get_result($offStmt);
                if ($ores && $ores->num_rows > 0) {
                    $matchedAny = true;
                    while ($orow = $ores->fetch_assoc()) {
                        $oid = (int)$orow['official_id'];
                        $pos = trim((string)($orow['position'] ?? ''));
                        $matchedName = trim((string)($orow['name'] ?? ''));
                        // Defensive: ensure position indicates lupon-tagapamayapa or lupon-hepe (case-insensitive)
                        $posLower = strtolower($pos);
                        if ($pos === '' || (strpos($posLower, 'lupon') === false || (strpos($posLower, 'tagapamayapa') === false && strpos($posLower, 'hepe') === false))) {
                            error_log("[appoint_hearing] Matched official '{$matchedName}' (id={$oid}) but position '{$pos}' is not lupon-tagapamayapa/lupon-hepe; skipping notification for mediator token '{$lname}' (Case_ID={$selectedCase})");
                            continue;
                        }
                        $notifStmt = $conn->prepare("INSERT INTO notifications (official_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
                        if ($notifStmt) {
                            $notifStmt->bind_param('issss', $oid, $notif_title, $notif_message, $created_at, $type);
                            $notifStmt->execute();
                            $notifStmt->close();
                        }
                    }
                }
                $offStmt->close();
            }

            // Fallback: try matching by last name token if no full-name match found
            if (!$matchedAny) {
                $parts = preg_split('/\s+/', trim($lname));
                $last = is_array($parts) && count($parts) ? end($parts) : '';
                if ($last !== '') {
                    // Last-name fallback but still require Lupon Tagapamayapa or Lupon Hepe
                    $offStmt2 = $conn->prepare("SELECT official_id, position, name FROM barangay_officials WHERE name LIKE ? AND LOWER(position) LIKE '%lupon%' AND (LOWER(position) LIKE '%tagapamayapa%' OR LOWER(position) LIKE '%hepe%')");
                    if ($offStmt2) {
                        $like2 = '%' . $last . '%';
                        $offStmt2->bind_param('s', $like2);
                        $offStmt2->execute();
                        $ores2 = bpamis_stmt_get_result($offStmt2);
                        if ($ores2 && $ores2->num_rows > 0) {
                            $matchedAny = true;
                            while ($orow2 = $ores2->fetch_assoc()) {
                                $oid = (int)$orow2['official_id'];
                                $pos2 = trim((string)($orow2['position'] ?? ''));
                                $matchedName2 = trim((string)($orow2['name'] ?? ''));
                                $pos2Lower = strtolower($pos2);
                                if ($pos2 === '' || (strpos($pos2Lower, 'lupon') === false || (strpos($pos2Lower, 'tagapamayapa') === false && strpos($pos2Lower, 'hepe') === false))) {
                                    error_log("[appoint_hearing] Fallback matched official '{$matchedName2}' (id={$oid}) but position '{$pos2}' is not lupon-tagapamayapa/lupon-hepe; skipping notification for mediator token '{$lname}' (Case_ID={$selectedCase})");
                                    continue;
                                }
                                $notifStmt = $conn->prepare("INSERT INTO notifications (official_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
                                if ($notifStmt) {
                                    $notifStmt->bind_param('issss', $oid, $notif_title, $notif_message, $created_at, $type);
                                    $notifStmt->execute();
                                    $notifStmt->close();
                                }
                            }
                        }
                        $offStmt2->close();
                    }
                }
            }

            // If still not matched, log for debugging so we can see why a lupon member did not receive a notification
            if (!$matchedAny) {
                error_log("[appoint_hearing] No barangay_officials match for mediator token: '" . $lname . "' (Case_ID={$selectedCase})");
            }
        }

        // Notify Lupon Head as well (if not already)
        $luponHeadQuery = $conn->prepare("SELECT official_id FROM barangay_officials WHERE position = 'Lupon Head' LIMIT 1");
        if ($luponHeadQuery) {
            $luponHeadQuery->execute();
            $lhRes = bpamis_stmt_get_result($luponHeadQuery);
            if ($lhRow = $lhRes->fetch_assoc()) {
                $lh_id = (int)$lhRow['official_id'];
                $notifStmt = $conn->prepare("INSERT INTO notifications (official_id, title, message, is_read, created_at, type) VALUES (?, ?, ?, 0, ?, ?)");
                if ($notifStmt) {
                    $notifStmt->bind_param('issss', $lh_id, $notif_title, $notif_message, $created_at, $type);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
            }
            $luponHeadQuery->close();
        }

        // Success message & redirect
        $_SESSION['success_message'] = "Hearing has been scheduled.";
        header("Location: appoint_hearing.php");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <style>
        html { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        body { overflow-x: hidden; }
    </style>
    <title>Schedule Hearing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.10),0 4px 18px -2px rgba(6,90,143,.18)'} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .bg-orbs:before,.bg-orbs:after{content:"";position:absolute;border-radius:9999px;filter:blur(70px);opacity:.35}
        .bg-orbs:before{width:480px;height:480px;background:linear-gradient(135deg,#7cccfd,#0c9ced);top:-160px;left:-140px}
        .bg-orbs:after{width:420px;height:420px;background:linear-gradient(135deg,#bae2fd,#7cccfd);bottom:-140px;right:-120px}
        .glass{background:linear-gradient(145deg,rgba(255,255,255,.9),rgba(255,255,255,.7));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%)}
        .input-base{width:100%;border-radius:.65rem;border:1px solid rgba(209,213,219,.7);background:rgba(255,255,255,.65);padding:.65rem .75rem;font-size:.85rem;transition:.2s}
        .input-base:not(textarea){height:44px;line-height:1.2}
        .input-base:focus{outline:none;background:#fff;border-color:#36b3f9;box-shadow:0 0 0 4px rgba(12,156,237,.25)}
        .field-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;display:flex;gap:6px;align-items:center;color:#4b5563}
        .select-readonly{background:#e0effe!important;color:#0c9ced!important;border-color:#0281d4!important;cursor:not-allowed!important;pointer-events:none;opacity:1!important}
        
        /* Mobile optimizations: compact and compressed layout */
        @media (max-width: 640px) {
            /* Prevent horizontal scroll */
            html, body {
                overflow-x: hidden !important;
                max-width: 100vw !important;
            }
            
            body {
                position: relative !important;
            }
            
            /* Preserve sidebar font sizes */
            #sidebar, #sidebar *, 
            #sidebar p, #sidebar span, #sidebar label, #sidebar div,
            #sidebar button, #sidebar a, #sidebar h1, #sidebar h2, #sidebar h3, #sidebar h4,
            #sidebar input, #sidebar select, #sidebar textarea,
            #sidebar i.fas, #sidebar i.far, #sidebar i.fa {
                font-size: inherit !important;
            }
            
            /* Reduce background orbs */
            .bg-orbs:before {
                width: 280px !important;
                height: 280px !important;
                top: -100px !important;
                left: -80px !important;
            }
            .bg-orbs:after {
                width: 240px !important;
                height: 240px !important;
                bottom: -80px !important;
                right: -60px !important;
            }
            
            /* Header - compact */
            header.max-w-screen-2xl {
                padding-top: 1rem !important;
            }
            
            header .glass {
                padding: 0.75rem !important;
            }
            
            header h1 {
                font-size: 1.125rem !important;
            }
            
            header h1 .w-12 {
                width: 2.25rem !important;
                height: 2.25rem !important;
            }
            
            header h1 i {
                font-size: 0.875rem !important;
            }
            
            header p {
                font-size: 0.7rem !important;
                margin-top: 0.5rem !important;
            }
            
            header .flex-wrap.gap-3 {
                gap: 0.375rem !important;
            }
            
            header .px-3.py-1 {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Main content spacing */
            main.max-w-7xl {
                margin-top: 1rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-bottom: 1rem !important;
            }
            
            /* Form section */
            section.glass {
                padding: 0.75rem !important;
            }
            
            /* Alert messages */
            .border-green-300,
            .border-red-300 {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Form spacing */
            form .space-y-10 {
                gap: 1rem !important;
            }
            
            form .grid.gap-10 {
                gap: 1rem !important;
            }
            
            form .space-y-6 > * + * {
                margin-top: 0.75rem !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 3px !important;
                gap: 4px !important;
            }
            
            /* Input fields */
            .input-base {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.625rem !important;
                border-radius: 0.5rem !important;
            }
            
            .input-base:not(textarea) {
                height: 38px !important;
            }
            
            textarea.input-base {
                min-height: 80px !important;
            }
            
            /* Select dropdowns */
            select.input-base {
                font-size: 0.7rem !important;
                padding-right: 2rem !important;
            }
            
            /* Remark suggestions */
            #remark-suggestions button {
                font-size: 10px !important;
                padding: 0.375rem 0.5rem !important;
            }
            
            #remark-suggestions + .text-\[11px\] {
                font-size: 9px !important;
                margin-top: 0.25rem !important;
            }
            
            /* Form buttons */
            form button,
            form a.inline-flex {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            form .gap-3 {
                gap: 0.5rem !important;
            }
            
            form .pt-4 {
                padding-top: 0.75rem !important;
            }
            
            /* Icons */
            .fa, .fas, .far {
                font-size: 0.7rem !important;
            }
            
            header .fa {
                font-size: 0.65rem !important;
            }
            
            /* Decorative elements in glass cards */
            .glass .absolute.w-48,
            .glass .absolute.w-64,
            .glass .absolute.-top-10,
            .glass .absolute.-bottom-12 {
                display: none !important;
            }
            
            /* Reduce border radius for compact feel */
            .rounded-2xl {
                border-radius: 0.75rem !important;
            }
            
            .rounded-xl {
                border-radius: 0.5rem !important;
            }
            
            .rounded-lg {
                border-radius: 0.375rem !important;
            }
            
            /* Grid layout - single column on mobile */
            .grid.md\\:grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
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
                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-gavel text-lg"></i></span>
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Schedule Hearing</span>
                </h1>
                <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Schedule a hearing for a case and notify involved parties automatically.</p>
            </div>
            <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-bell text-primary-500"></i> Auto Notifications</div>
            </div>
        </div>
    </div>
</header>

<main class="relative z-10 max-w-7xl mx-auto px-4 md:px-8 mt-10 pb-24">
    <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 p-6 md:p-10 animate-fade-in space-y-10">
        <?php if (!empty($error_message)): ?>
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-start gap-2">
                <i class="fa fa-circle-exclamation mt-0.5"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2">
                <i class="fa fa-check-circle mt-0.5"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="space-y-10">
            <input type="hidden" name="participants" id="participantsInput" />
            <div class="grid md:grid-cols-2 gap-10">
                <div class="space-y-6">
                    <div>
                        <label for="case-id" class="field-label"><i class="fa fa-briefcase"></i> Case <span class="text-red-600">*</span></label>
                        <select id="case-id" name="case_id" class="input-base" required aria-required="true">
                            <option value="">-- Select a Case --</option>
                            <?php
                            // Build option text as: [Case_Original_ID] {Case_Status}: {Complainant} vs. {Respondent}(, et. al.)
                            // Show all cases that Lupon Head can view (same as view_cases.php)
                            // Only exclude cases that already have pending/upcoming schedules
                            $currentDateTime = date('Y-m-d H:i:s');
                            $sql = "
                                SELECT 
                                    cs.Case_ID,
                                    cs.Case_Status,
                                    cs.case_original_id,
                                    ci.Complaint_ID,
                                    CONCAT(
                                        COALESCE(res_com.First_Name, ext_com.First_Name, ''),
                                        ' ',
                                        COALESCE(res_com.Last_Name, ext_com.Last_Name, '')
                                    ) AS complainant_name,
                                    CONCAT(COALESCE(r_main.First_Name,''),' ',COALESCE(r_main.Last_Name,'')) AS main_respondent_name,
                                    GROUP_CONCAT(DISTINCT CONCAT(COALESCE(res_res.First_Name,''),' ',COALESCE(res_res.Last_Name,'')) SEPARATOR ', ') AS other_respondents,
                                    COUNT(DISTINCT res_res.Resident_ID) AS other_count
                                FROM case_info cs
                                JOIN complaint_info ci ON cs.Complaint_ID = ci.Complaint_ID
                                LEFT JOIN resident_info res_com ON ci.Resident_ID = res_com.Resident_ID
                                LEFT JOIN external_complainant ext_com ON ci.External_Complainant_ID = ext_com.External_Complaint_ID
                                LEFT JOIN resident_info r_main ON ci.Respondent_ID = r_main.Resident_ID
                                LEFT JOIN complaint_respondents cr ON ci.Complaint_ID = cr.Complaint_ID
                                LEFT JOIN resident_info res_res ON cr.Respondent_ID = res_res.Resident_ID
                                WHERE (ci.case_type IS NULL OR ci.case_type NOT IN ('Civil','Criminal','Blotter'))
                                AND cs.Case_Status IN ('Conciliation','Arbitration')
                                AND cs.Case_ID NOT IN (
                                    SELECT case_id 
                                    FROM schedule_list 
                                    WHERE hearingDateTime >= '$currentDateTime' 
                                    AND visible = 1
                                )
                                GROUP BY cs.Case_ID
                                ORDER BY cs.Case_ID DESC";

                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $complainant = trim($row['complainant_name'] ?? '');
                                    $mainResp = trim($row['main_respondent_name'] ?? '');
                                    $otherList = trim($row['other_respondents'] ?? '');
                                    $otherCount = (int)($row['other_count'] ?? 0);

                                    $firstOther = '';
                                    if ($otherList !== '') {
                                        $parts = array_map('trim', explode(',', $otherList));
                                        $firstOther = $parts[0] ?? '';
                                    }
                                    $firstResp = $mainResp !== '' ? $mainResp : ($firstOther !== '' ? $firstOther : 'Respondent N/A');
                                    $totalRespondents = ($mainResp !== '' ? 1 : 0) + $otherCount;
                                    $respDisplay = $firstResp . ($totalRespondents > 1 ? ', et. al.' : '');
                                    $status = $row['Case_Status'] ?? '';
                                    $caseOriginalId = $row['case_original_id'] ?? '';
                                    $caseIdDisplay = $caseOriginalId ? '[' . $caseOriginalId . '] ' : '';
                                    $label = trim($caseIdDisplay . ($status ? $status . ': ' : '') . ($complainant ?: 'Complainant N/A') . ' vs. ' . $respDisplay);

                                    echo '<option value="' . (int)$row['Case_ID'] . '">' . htmlspecialchars($label) . '</option>';
                                }
                            } else {
                                echo '<option disabled>No cases found</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label"><i class="fa fa-flag"></i> Status / Phase</label>
                        <input type="text" id="case-phase" class="input-base select-readonly" readonly />
                    </div>
                    <div>
                        <label for="complainant_name" class="field-label"><i class="fa fa-user"></i> Complainant</label>
                        <input type="text" id="complainant_name" name="complainant_name" class="input-base bg-white/80" readonly />
                    </div>
                    <div>
                        <label for="Respondent_Name" class="field-label"><i class="fa fa-users"></i> Respondent(s)</label>
                        <input type="text" id="Respondent_Name" name="Respondent_Name" class="input-base bg-white/80" readonly />
                    </div>
                    
                    <div>
                        <label for="hearing-title" class="field-label"><i class="fa fa-heading"></i> Title <span class="text-red-600">*</span></label>
                        <input type="text" id="hearing-title" name="hearing_title" class="input-base select-readonly" placeholder="Will be auto-filled" readonly required aria-required="true" />
                    </div>
                    
                </div>
                <div class="space-y-6">
                    <div>
                        <label for="hearing-date" class="field-label"><i class="fa fa-calendar-day"></i> Date <span class="text-red-600">*</span></label>
                        <input type="date" id="hearing-date" name="hearing_date" class="input-base" required aria-required="true" />
                    </div>
                    <div>
                        <label for="hearing-time" class="field-label"><i class="fa fa-clock"></i> Time <span class="text-red-600">*</span></label>
                        <input type="time" id="hearing-time" name="hearing_time" class="input-base" required aria-required="true" />
                    </div>
                    <div>
                        <label for="venue" class="field-label"><i class="fa fa-location-dot"></i> Venue <span class="text-red-600">*</span></label>
                        <input type="text" id="venue" name="venue" class="input-base" placeholder="Barangay Hall of Panducot" value="Barangay Hall of Panducot" required aria-required="true" />
                    </div>
                    <div>
                        <label for="hearing-remarks" class="field-label"><i class="fa fa-align-left"></i> Remarks</label>
                        <textarea id="hearing-remarks" name="hearing_remarks" rows="6" class="input-base resize-y" placeholder="Optional notes or instructions..."></textarea>
                        <div class="mt-2">
                            <div class="field-label"><i class="fa fa-lightbulb"></i> Suggested remarks</div>
                            <div id="remark-suggestions" class="flex flex-wrap gap-2"></div>
                            <div class="text-[11px] text-gray-500 mt-1">Click a suggestion to fill in the remarks. Text includes complainant, all respondents, and attendance requirement.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-dashed border-primary-200/60">
                <a href="#" onclick="goHome(event)" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                    <i class="fa fa-calendar-plus"></i> Schedule Hearing
                </button>
            </div>
        </form>
    </section>
</main>


<?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
<script>
        function goBack(event) {
        event.preventDefault();
        if (document.referrer && document.referrer !== window.location.href) {
            window.history.back();
        } else {
            // fallback URL if no referrer or same page
            window.location.href = 'home-luponhead.php';
        }
        
    }
    function goHome(event) {
        event.preventDefault();
         window.location.href = 'home-luponhead.php';
    }
    $('#case-id').on('change', function () {
        const caseId = $(this).val();
        if (caseId) {
            $.ajax({
                url: 'schedule/get_case_participants.php',
                type: 'GET',
                data: { Case_ID: caseId }, 
                success: function (response) {
                    try {
                        const data = JSON.parse(response);

                        if (data.complainant) {
                            $('#complainant_name').val(data.complainant);
                        } else {
                            $('#complainant_name').val('Not found');
                        }

                        if (data.respondents && data.respondents.length > 0) {
                            $('#Respondent_Name').val(data.respondents.join(', '));
                        } else {
                            $('#Respondent_Name').val('Not found');
                        }

                        const participantString = `Complainant: ${data.complainant ?? 'N/A'}, Respondent(s): ${data.respondents?.join(', ') || 'N/A'}`;
                        $('#participantsInput').val(participantString);

                        // Show case phase/status
                        const phase = (data.phase || data.case_status || '').toString();
                        $('#case-phase').val(phase);

                        // Auto-build title: "<Phase>: <Complainant> Vs. <Respondent(s)>"
                        const complainant = data.complainant || '';
                        const respondents = (data.respondents && data.respondents.length)
                            ? (data.respondents.length > 1 ? `${data.respondents[0]}, et. al.` : data.respondents[0])
                            : '';
                        let autoTitle = '';
                        if (phase && complainant && respondents) {
                            autoTitle = `${phase}: ${complainant} Vs. ${respondents}`;
                        } else if (complainant && respondents) {
                            autoTitle = `${complainant} Vs. ${respondents}`;
                        } else {
                            autoTitle = '';
                        }
                        $('#hearing-title').val(autoTitle).prop('readonly', true).addClass('select-readonly');

                        // Build suggested remarks
                        buildRemarkSuggestions({
                            phase,
                            complainant: data.complainant || '',
                            respondentsAll: Array.isArray(data.respondents) ? data.respondents : []
                        });

                    } catch (err) {
                        console.error('Invalid JSON:', err);
                        $('#complainant_name').val('');
                        $('#Respondent_Name').val('');
                        $('#case-phase').val('');
                        $('#hearing-title').val('').prop('readonly', true).addClass('select-readonly');
                        $('#remark-suggestions').empty();
                    }
                },
                error: function () {
                    $('#complainant_name').val('');
                    $('#Respondent_Name').val('');
                    $('#case-phase').val('');
                    $('#hearing-title').val('').prop('readonly', true).addClass('select-readonly');
                    $('#remark-suggestions').empty();
                    console.error('AJAX failed');
                }
            });
        } else {
            $('#complainant_name').val('');
            $('#Respondent_Name').val('');
            $('#case-phase').val('');
            $('#hearing-title').val('').prop('readonly', true).addClass('select-readonly');
            $('#remark-suggestions').empty();
        }
    });

    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const caseIdFromUrl = urlParams.get('id');

        if (caseIdFromUrl) {
            $('#case-id').val(caseIdFromUrl);
            $('#case-id').trigger('change');

            // Add readonly style and prevent user change
            $('#case-id').addClass('select-readonly');

            // Prevent keyboard changes
            $('#case-id').on('keydown paste input', function(e) {
                e.preventDefault();
            });
        }
    });

    // Ensure no past dates
    document.getElementById('hearing-date').min = new Date().toISOString().split('T')[0];

    // Utilities to build and render suggested remarks
    function buildRemarkSuggestions(ctx) {
        const container = document.getElementById('remark-suggestions');
        if (!container) return;
        container.innerHTML = '';

        const phase = (ctx.phase || '').toString();
        const comp = (ctx.complainant || '').toString();
        const resps = Array.isArray(ctx.respondentsAll) ? ctx.respondentsAll.filter(Boolean) : [];
        const allRespondentsList = resps.length ? resps.join(', ') : 'Respondent(s)';

        const suggestions = [];

        // Base suggestion tailored to phase
        if (phase) {
            suggestions.push(`This scheduled hearing is in the ${phase} phase for the case of ${comp} versus ${allRespondentsList}. All parties are required to attend.`);
        }
        // Attendance reminder including schedule placeholders (will be replaced on click)
        suggestions.push(`You are hereby required to attend the hearing for the case of ${comp} versus ${allRespondentsList}. Please be punctual and bring necessary documents.`);
        // Neutral scheduling note
        suggestions.push(`This notice serves to inform ${allRespondentsList} that a hearing has been set for the case filed by ${comp}. Presence is mandatory unless otherwise excused.`);

        // Render as clickable chips
        suggestions.forEach(text => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'px-3 py-1.5 rounded-md border border-primary-200 bg-white/80 hover:bg-white text-[12px] text-gray-700 shadow-sm';
            btn.textContent = text.length > 140 ? text.slice(0, 137) + '…' : text;
            btn.title = text;
            btn.addEventListener('click', () => {
                const date = (document.getElementById('hearing-date')?.value || '').toString();
                const time = (document.getElementById('hearing-time')?.value || '').toString();
                const venue = (document.getElementById('venue')?.value || '').toString();

                const whenPart = (date && time) ? ` on ${date} at ${time}` : (date ? ` on ${date}` : '');
                const venuePart = venue ? ` at ${venue}` : '';

                const full = `${text}${whenPart}${venuePart}`.trim();
                const area = document.getElementById('hearing-remarks');
                if (area) area.value = full;
            });
            container.appendChild(btn);
        });
    }
</script>
</html>
