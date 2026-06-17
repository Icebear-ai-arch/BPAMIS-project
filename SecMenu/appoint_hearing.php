
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

    // Corrected: include visible and bind parameters in proper order/types
    $stmt = $conn->prepare("INSERT INTO schedule_list (case_id, visible, hearingTitle, hearingDateTime, place, participant, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $visible = 1;
    $stmt->bind_param("iisssss", $selectedCase, $visible, $hearingTitle, $hearingDateTime, $venue, $participants, $remarks);

        if ($stmt->execute()) {
            // After inserting first hearing, gather participants and related IDs once
            // Fetch complaint and case meta plus all respondents for comprehensive notifications
            $participantSql = "
                SELECT
                    ci.Case_ID,
                    ci.Case_Status,
                    ci.Complaint_ID,
                    co.Resident_ID AS Complainant_Resident_ID,
                    co.External_Complainant_ID
                FROM case_info ci
                LEFT JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                WHERE ci.Case_ID = ?
                LIMIT 1
            ";

            $participantStmt = $conn->prepare($participantSql);
            $participantStmt->bind_param('i', $selectedCase);
            $participantStmt->execute();
            $participantRes = bpamis_stmt_get_result($participantStmt);

            $complainantResidentId = null;
            $externalComplainantId = null;
            $caseStatus = null;
            $complaintId = null;
            if ($participantRes && $participantRes->num_rows > 0) {
                $p = $participantRes->fetch_assoc();
                $complainantResidentId = $p['Complainant_Resident_ID'];
                $externalComplainantId = $p['External_Complainant_ID'];
                $caseStatus = $p['Case_Status'];
                $complaintId = $p['Complaint_ID'];
            }
            $participantStmt->close();

            // Fetch all respondent IDs for the complaint (may be multiple)
            $respondents = [];
            if (!empty($complaintId)) {
                $respSql = "SELECT respondent_id FROM complaint_respondents WHERE Complaint_ID = ?";
                $respStmt = $conn->prepare($respSql);
                $respStmt->bind_param('i', $complaintId);
                $respStmt->execute();
                $respRes = bpamis_stmt_get_result($respStmt);
                if ($respRes) {
                    while ($r = $respRes->fetch_assoc()) {
                        if (!empty($r['respondent_id'])) $respondents[] = $r['respondent_id'];
                    }
                }
                $respStmt->close();
            }

            // Prepare notification payloads
            $notif_title = "Hearing Scheduled";
            $notif_message = "Your case (ID: $selectedCase) has been scheduled for a hearing on $hearingDate at $hearingTime.";
            $created_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            $type = 'Hearing';

            // Notify complainant resident if present
            if (!empty($complainantResidentId)) {
                $safeTitle = $conn->real_escape_string($notif_title);
                $safeMessage = $conn->real_escape_string($notif_message);
                $conn->query("INSERT INTO notifications (resident_id, title, message, is_read, created_at, type) VALUES ($complainantResidentId, '$safeTitle', '$safeMessage', 0, '$created_at', '$type')");
            }

            // Notify all respondent residents
            foreach ($respondents as $respId) {
                if (!empty($respId)) {
                    $safeTitle = $conn->real_escape_string($notif_title);
                    $safeMessage = $conn->real_escape_string($notif_message);
                    $conn->query("INSERT INTO notifications (resident_id, title, message, is_read, created_at, type) VALUES ($respId, '$safeTitle', '$safeMessage', 0, '$created_at', '$type')");
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
            $created_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
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
                LEFT JOIN arbitration s ON s.case_id = ci.case_id AND s.mediator_name = ?
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
                    $created_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
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
                $created_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
                $type = 'Hearing';
                
                $conn->query("
                    INSERT INTO notifications (official_id, title, message, is_read, created_at, type)
                    VALUES ($luponhead_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }
            $luponHeadQuery->close();
        }

            // No automatic follow-up hearings are created anymore.
            // Only the hearing explicitly scheduled by the user is inserted and notified above.

        // Before redirect: notify mediators (Lupon) listed for this case across arbitration/mediation/conciliation
        // Parse comma/semicolon/newline-separated names from mediator_name fields and notify each resolved official
        $mediatorNames = [];

        // Check arbitration table for mediator_name
        $medQ = $conn->prepare("SELECT mediator_name FROM arbitration WHERE Case_ID = ? LIMIT 1");
        if ($medQ) {
            $medQ->bind_param('i', $selectedCase);
            $medQ->execute();
            $medRes = bpamis_stmt_get_result($medQ);
            if ($medRes && $mrow = $medRes->fetch_assoc()) {
                if (!empty(trim($mrow['mediator_name'] ?? ''))) {
                    $parts = preg_split('/[;,\n]+/', $mrow['mediator_name']);
                    foreach ($parts as $p) {
                        $n = trim($p);
                        if ($n !== '' && !in_array($n, $mediatorNames)) $mediatorNames[] = $n;
                    }
                }
            }
            $medQ->close();
        }

        // Also check mediation_info and conciliation for mediator_name fields (if present)
        foreach (['mediation_info','conciliation'] as $tbl) {
            if ($stmt2 = $conn->prepare("SELECT mediator_name FROM $tbl WHERE case_id = ? LIMIT 1")) {
                $stmt2->bind_param('i', $selectedCase);
                $stmt2->execute();
                $r = bpamis_stmt_get_result($stmt2);
                if ($r && $row2 = $r->fetch_assoc()) {
                    if (!empty(trim($row2['mediator_name'] ?? ''))) {
                        $parts = preg_split('/[;,\n]+/', $row2['mediator_name']);
                        foreach ($parts as $p) {
                            $n = trim($p);
                            if ($n !== '' && !in_array($n, $mediatorNames)) $mediatorNames[] = $n;
                        }
                    }
                }
                $stmt2->close();
            }
        }

        if (!empty($mediatorNames)) {
            // Prepare lookup for official id by exact name match (trimmed)
            $getOff = $conn->prepare("SELECT official_id FROM barangay_officials WHERE TRIM(name) = ? LIMIT 1");
            foreach ($mediatorNames as $mName) {
                if (!$getOff) break;
                $trimName = $mName;
                $getOff->bind_param('s', $trimName);
                $getOff->execute();
                $offRes = bpamis_stmt_get_result($getOff);
                if ($offRes && $offRow = $offRes->fetch_assoc()) {
                    $lupon_id = (int)$offRow['official_id'];

                    $notif_title = "New Hearing Assigned";
                    $notif_message = "A new hearing for Case ID: $selectedCase has been scheduled on $hearingDate at $hearingTime.";
                    $created_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
                    $type = 'Hearing';

                    $safeTitle = $conn->real_escape_string($notif_title);
                    $safeMsg = $conn->real_escape_string($notif_message);

                    $conn->query("INSERT INTO notifications (lupon_id, title, message, is_read, created_at, type) VALUES ($lupon_id, '$safeTitle', '$safeMsg', 0, '$created_at', '$type')");
                }
            }
            if ($getOff) $getOff->close();
        }

        // Success message & redirect
        $_SESSION['success_message'] = "Hearing has been scheduled.";
        header("Location: appoint_hearing.php");
        exit();
    }

    $stmt->close();
    $conn->close();
        // -------------------------
        // NEW: Notify mediators (Lupon) listed for this case across arbitration/mediation/conciliation
        // Parse comma/semicolon/newline-separated names from mediator_name fields and notify each resolved official
        // -------------------------
        $mediatorNames = [];

        // Check arbitration table for mediator_name
        $medQ = $conn->prepare("SELECT mediator_name FROM arbitration WHERE Case_ID = ? LIMIT 1");
        if ($medQ) {
            $medQ->bind_param('i', $selectedCase);
            $medQ->execute();
            $medRes = bpamis_stmt_get_result($medQ);
            if ($medRes && $mrow = $medRes->fetch_assoc()) {
                if (!empty(trim($mrow['mediator_name'] ?? ''))) {
                    $parts = preg_split('/[;,\n]+/', $mrow['mediator_name']);
                    foreach ($parts as $p) {
                        $n = trim($p);
                        if ($n !== '' && !in_array($n, $mediatorNames)) $mediatorNames[] = $n;
                    }
                }
            }
            $medQ->close();
        }

        // Also check mediation_info and conciliation for mediator_name fields (if present)
        foreach (['mediation_info','conciliation'] as $tbl) {
            if ($stmt2 = $conn->prepare("SELECT mediator_name FROM $tbl WHERE case_id = ? LIMIT 1")) {
                $stmt2->bind_param('i', $selectedCase);
                $stmt2->execute();
                $r = bpamis_stmt_get_result($stmt2);
                if ($r && $row2 = $r->fetch_assoc()) {
                    if (!empty(trim($row2['mediator_name'] ?? ''))) {
                        $parts = preg_split('/[;,\n]+/', $row2['mediator_name']);
                        foreach ($parts as $p) {
                            $n = trim($p);
                            if ($n !== '' && !in_array($n, $mediatorNames)) $mediatorNames[] = $n;
                        }
                    }
                }
                $stmt2->close();
            }
        }

        if (!empty($mediatorNames)) {
            // Prepare lookup for official id by exact name match (trimmed)
            $getOff = $conn->prepare("SELECT official_id FROM barangay_officials WHERE TRIM(name) = ? LIMIT 1");
            foreach ($mediatorNames as $mName) {
                if (!$getOff) break;
                $trimName = $mName;
                $getOff->bind_param('s', $trimName);
                $getOff->execute();
                $offRes = bpamis_stmt_get_result($getOff);
                if ($offRes && $offRow = $offRes->fetch_assoc()) {
                    $lupon_id = (int)$offRow['official_id'];

                    $notif_title = "New Hearing Assigned";
                    $notif_message = "A new hearing for Case ID: $selectedCase has been scheduled on $hearingDate at $hearingTime.";
                    $created_at = date('Y-m-d H:i:s');
                    $type = 'Hearing';

                    $safeTitle = $conn->real_escape_string($notif_title);
                    $safeMsg = $conn->real_escape_string($notif_message);

                    $conn->query("INSERT INTO notifications (lupon_id, title, message, is_read, created_at, type) VALUES ($lupon_id, '$safeTitle', '$safeMsg', 0, '$created_at', '$type')");
                }
            }
            if ($getOff) $getOff->close();
        }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Appoint Hearing</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
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
            
            /* Success message */
            .border-green-300 {
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
<?php include '../includes/barangay_official_sec_nav.php'; ?>

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

<main class="relative z-10 max-w-7xl mx-auto px-4 md:px-8 mt-10 pb-6">
    <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 p-6 md:p-10 animate-fade-in space-y-10">
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
                            // Build a richer option label: [Case_ID/Complaint_ID]- [Case Status/Complaint Type]: [Complainant] Vs. [Respondent]
                            $sql = "
                                SELECT
                                    ci.Case_ID,
                                    ci.Complaint_ID,
                                    ci.Case_Status,
                                    ci.case_original_id,
                                    COALESCE(co.case_type, co.Complaint_Type, '') AS complaint_type,
                                    COALESCE(
                                        NULLIF(CONCAT_WS(' ', r.First_Name, r.Middle_Name, r.Last_Name), ''),
                                        NULLIF(CONCAT_WS(' ', e.First_Name, e.Middle_Name, e.Last_Name), ''),
                                        ''
                                    ) AS complainant,
                                    TRIM(BOTH ', ' FROM (
                                        CASE
                                            WHEN NULLIF(CONCAT_WS(' ', mr.First_Name, mr.Middle_Name, mr.Last_Name), '') <> ''
                                                 AND IFNULL(GROUP_CONCAT(DISTINCT CONCAT_WS(' ', rr.First_Name, rr.Middle_Name, rr.Last_Name) SEPARATOR ', '), '') <> ''
                                                 AND FIND_IN_SET(
                                                     NULLIF(CONCAT_WS(' ', mr.First_Name, mr.Middle_Name, mr.Last_Name), ''),
                                                     REPLACE(IFNULL(GROUP_CONCAT(DISTINCT CONCAT_WS(' ', rr.First_Name, rr.Middle_Name, rr.Last_Name) SEPARATOR ', '), ''), ', ', ',')
                                                 ) > 0
                                            THEN IFNULL(GROUP_CONCAT(DISTINCT CONCAT_WS(' ', rr.First_Name, rr.Middle_Name, rr.Last_Name) SEPARATOR ', '), '')
                                            ELSE CONCAT_WS(
                                                ', ',
                                                NULLIF(CONCAT_WS(' ', mr.First_Name, mr.Middle_Name, mr.Last_Name), ''),
                                                IFNULL(GROUP_CONCAT(DISTINCT CONCAT_WS(' ', rr.First_Name, rr.Middle_Name, rr.Last_Name) SEPARATOR ', '), '')
                                            )
                                        END
                                    )) AS respondents
                                FROM case_info ci
                                JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                                LEFT JOIN resident_info r ON co.Resident_ID = r.Resident_ID
                                LEFT JOIN resident_info mr ON co.Respondent_ID = mr.Resident_ID
                                LEFT JOIN external_complainant e ON co.External_Complainant_ID = e.External_Complaint_ID
                                LEFT JOIN complaint_respondents cr ON co.Complaint_ID = cr.Complaint_ID
                                LEFT JOIN resident_info rr ON cr.respondent_id = rr.Resident_ID
                                WHERE TRIM(LOWER(COALESCE(ci.Case_Status, ''))) NOT IN (
                                    'mediation resolved',
                                    'conciliation resolved',
                                    'arbitration resolved',
                                    'dismissed',
                                    'certificate to file action'
                                )
                                GROUP BY ci.Case_ID, ci.Complaint_ID, ci.Case_Status, complaint_type, complainant
                                ORDER BY ci.Case_ID DESC
                            ";
                            $result = $conn->query($sql);
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $caseId = $row['Case_ID'];
                                    $complaintId = $row['Complaint_ID'];
                                    $caseStatus = $row['Case_Status'] ?? '';
                                    $caseOriginalId = $row['case_original_id'] ?? '';
                                    $complaintType = $row['complaint_type'] ?? '';
                                    $complainant = trim($row['complainant'] ?? '');
                                    $respondents = trim($row['respondents'] ?? '');
                                    if ($complainant === '') $complainant = 'N/A';
                                    if ($respondents === '') $respondents = 'N/A';

                                    // Show only the complaint ID if the matter is still a complaint,
                                    // otherwise show the case original ID. We use Case_Status to determine
                                    // whether it's still in the 'Complaint' phase.
                                    $isComplaintPhase = false;
                                    if (empty($caseStatus) || stripos($caseStatus, 'complaint') !== false) {
                                        $isComplaintPhase = true;
                                    }

                                    if ($isComplaintPhase) {
                                        // Display complaint id only
                                        $label = sprintf('[%s] - [%s]: %s Vs. %s',
                                            $complaintId,
                                            $complaintType,
                                            $complainant,
                                            $respondents
                                        );
                                    } else {
                                        // Display case_original_id (or fall back to Case_ID if not available)
                                        $displayId = $caseOriginalId ?: $caseId;
                                        $label = sprintf('[%s] - [%s/%s]: %s Vs. %s',
                                            $displayId,
                                            $caseStatus,
                                            $complaintType,
                                            $complainant,
                                            $respondents
                                        );
                                    }

                                    echo '<option value="'.htmlspecialchars($caseId).'">'.htmlspecialchars($label).'</option>';
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

<?php include 'sidebar_.php';?>
<?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
<script>
        function goBack(event) {
        event.preventDefault();
        if (document.referrer && document.referrer !== window.location.href) {
            window.history.back();
        } else {
            // fallback URL if no referrer or same page
            window.location.href = 'home-captain.php';
        }
        
    }
    function goHome(event) {
        event.preventDefault();
        // Instead of navigating to home-secretary, reset the appoint hearing form
        // and reload the appoint_hearing page so the user remains on this page.
        try {
            const form = document.querySelector('form');
            if (form) {
                form.reset();
            }

            // Clear commonly populated fields set by AJAX
            const idsToClear = ['participantsInput','complainant_name','Respondent_Name','case-phase','hearing-title','hearing-remarks','venue','hearing-date','hearing-time'];
            idsToClear.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') el.value = '';
            });

            // Reset the case selector and remove readonly styling/handlers added when prefilled
            const caseSelect = document.getElementById('case-id');
            if (caseSelect) {
                caseSelect.value = '';
                caseSelect.classList.remove('select-readonly');
                // Unbind any preventDefault handlers added earlier
                $(caseSelect).off('keydown paste input');
            }

            // Clear remark suggestions area
            const rem = document.getElementById('remark-suggestions');
            if (rem) rem.innerHTML = '';

        } catch (e) {
            // ignore
        }

        // reload current page (appoint_hearing.php) to return to a clean state
        window.location.href = 'appoint_hearing.php';
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
                        const respondentsDisplay = (data.respondents && data.respondents.length)
                            ? (data.respondents.length > 1 ? `${data.respondents[0]} et. al` : data.respondents[0])
                            : '';
                        let autoTitle = '';
                        if (phase && complainant && respondentsDisplay) {
                            autoTitle = `[${phase}: ${complainant} Vs. ${respondentsDisplay}]`;
                        } else if (complainant && respondentsDisplay) {
                            autoTitle = `[${complainant} Vs. ${respondentsDisplay}]`;
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