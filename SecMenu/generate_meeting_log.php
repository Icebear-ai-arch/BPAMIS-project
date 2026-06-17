<?php
// Generate printable meeting log document
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
$download = isset($_GET['download']);
$log = null;
$case_id = 0;

if ($log_id > 0) {
    // Fetch meeting log with related case + complaint + participants
    $stmt = $conn->prepare("SELECT 
        m.Log_ID,
        m.Case_ID,
        m.Hearing_Date,
        m.Hearing_Time,
        m.Hearing_End_Time,
        m.Hearing_Details,
        m.Attendance,
        m.Reason_Incompliance,
        m.Complainant_Status,
        m.Respondent_Status,
        ci.Complaint_ID,
        ci.Complaint_Title,
        ci.Complaint_Details,
        ci.Date_Filed,
        COALESCE(ci.respondent_id, ci.Respondent_ID) AS main_respondent,
        CONCAT(COALESCE(res_com.First_Name, ext_com.First_Name, ''), ' ', COALESCE(res_com.Last_Name, ext_com.Last_Name, '')) AS complainant_name,
        CONCAT(COALESCE(main_res.First_Name,''),' ',COALESCE(main_res.Last_Name,'')) AS main_respondent_name,
        cs.case_original_id,
        cs.Case_Status
    FROM MEETING_LOGS m
    JOIN CASE_INFO cs ON m.Case_ID = cs.Case_ID
    LEFT JOIN COMPLAINT_INFO ci ON cs.Complaint_ID = ci.Complaint_ID
    LEFT JOIN RESIDENT_INFO res_com ON ci.Resident_ID = res_com.Resident_ID
    LEFT JOIN external_complainant ext_com ON ci.External_Complainant_ID = ext_com.External_Complaint_ID
    LEFT JOIN RESIDENT_INFO main_res ON COALESCE(ci.respondent_id, ci.Respondent_ID) = main_res.Resident_ID
    WHERE m.Log_ID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $log_id);
        $stmt->execute();
        $res = bpamis_stmt_get_result($stmt);
        if ($row = $res->fetch_assoc()) { $log = $row; $case_id = (int)$row['Case_ID']; }
        $stmt->close();
    }
}

// Fetch respondents list
$respondents = [];
if ($log && !empty($log['Complaint_ID'])) {
    $rst = $conn->prepare("SELECT cr.Respondent_ID, CONCAT(COALESCE(r.First_Name,''),' ',COALESCE(r.Last_Name,'')) AS name
                           FROM COMPLAINT_RESPONDENTS cr 
                           LEFT JOIN RESIDENT_INFO r ON cr.Respondent_ID = r.Resident_ID
                           WHERE cr.Complaint_ID = ? ORDER BY cr.Respondent_ID ASC");
    if ($rst) {
        $rst->bind_param("i", $log['Complaint_ID']);
        $rst->execute();
        $rres = bpamis_stmt_get_result($rst);
        while ($rrow = $rres->fetch_assoc()) { $respondents[] = $rrow; }
        $rst->close();
    }
}

// Ensure main respondent first
if ($log && !empty($log['main_respondent'])) {
    $mainId = (int)$log['main_respondent'];
    $foundIndex = null;
    foreach ($respondents as $i => $r) { if ((int)$r['Respondent_ID'] === $mainId) { $foundIndex = $i; break; } }
    if ($foundIndex === null) {
        // fetch name
        $nm = null; $ms = $conn->prepare("SELECT CONCAT(COALESCE(First_Name,''),' ',COALESCE(Last_Name,'')) AS name FROM RESIDENT_INFO WHERE Resident_ID = ?");
        if ($ms) { $ms->bind_param("i", $mainId); $ms->execute(); $mr = bpamis_stmt_get_result($ms); if ($mrow = $mr->fetch_assoc()) { $nm = trim($mrow['name']); } $ms->close(); }
        if (!$nm) $nm = 'Respondent ' . $mainId;
        array_unshift($respondents, ['Respondent_ID' => $mainId, 'name' => $nm]);
    } else if ($foundIndex !== 0) {
        $mainObj = $respondents[$foundIndex];
        unset($respondents[$foundIndex]);
        array_unshift($respondents, $mainObj);
    }
}

// Fetch Lupon Tagapamayapa / Mediator
$lupon_name = null;
if ($case_id) {
    $lupon_stmt = $conn->prepare("SELECT 
        CASE 
            WHEN cs.Case_Status = 'Mediation' THEN mi.Mediator_Name
            WHEN cs.Case_Status = 'Conciliation' THEN ci.Mediator_Name
            WHEN cs.Case_Status = 'Resolution' THEN ri.Mediator_Name
            WHEN cs.Case_Status = 'Settlement' THEN si.Mediator_Name
            WHEN cs.Case_Status = 'Arbitration' THEN ai.Mediator_Name
            ELSE NULL END AS lupon_tagapamayapa
        FROM CASE_INFO cs
        LEFT JOIN mediation_info mi ON cs.Case_ID = mi.Case_ID
        LEFT JOIN conciliation ci ON cs.Case_ID = ci.Case_ID
        LEFT JOIN resolution ri ON cs.Case_ID = ri.Case_ID
        LEFT JOIN settlement si ON cs.Case_ID = si.Case_ID
        LEFT JOIN arbitration ai ON cs.Case_ID = ai.Case_ID
        WHERE cs.Case_ID = ? LIMIT 1");
    if ($lupon_stmt) { $lupon_stmt->bind_param("i", $case_id); $lupon_stmt->execute(); $lres = bpamis_stmt_get_result($lupon_stmt); if ($lrow = $lres->fetch_assoc()) { $lupon_name = $lrow['lupon_tagapamayapa']; } $lupon_stmt->close(); }
}
if (empty($lupon_name)) $lupon_name = 'Not Yet Assigned';

// Fetch Barangay Secretary and Captain names
function fetch_official($conn, $position) {
    $name = null;
    $st = $conn->prepare("SELECT name FROM barangay_officials WHERE position = ? LIMIT 1");
    if ($st) { $st->bind_param("s", $position); $st->execute(); $r = bpamis_stmt_get_result($st); if ($rw = $r->fetch_assoc()) { $name = $rw['name']; } $st->close(); }
    return $name ?: $position;
}
$secretary_name = fetch_official($conn, 'Secretary');
$captain_name   = fetch_official($conn, 'Barangay Captain');

// Parse respondent statuses
$respondent_statuses = [];
if ($log && !empty($log['Respondent_Status'])) {
    $respondent_statuses = preg_split('/\s*,\s*/', $log['Respondent_Status']);
}
// Align statuses with respondents array order
foreach ($respondents as $idx => &$r) {
    $st = isset($respondent_statuses[$idx]) ? $respondent_statuses[$idx] : 'Present';
    $r['status_label'] = (strtolower($st) === 'unattended') ? 'Failure to Appear' : $st;
}
unset($r);

$complainant_status_label = ($log && strtolower($log['Complainant_Status']) === 'unattended') ? 'Failure to Appear' : ($log['Complainant_Status'] ?? 'Present');

// Format times
$formatted_start = $log ? date('g:i A', strtotime($log['Hearing_Time'])) : '';
$formatted_end   = ($log && !empty($log['Hearing_End_Time'])) ? date('g:i A', strtotime($log['Hearing_End_Time'])) : '';
$time_range = $formatted_start . ($formatted_end ? ' - ' . $formatted_end : '');

// Build HTML fragment for PDF (only if we have a log)
$pdfFragment = '';
if ($log) {
        ob_start();
        ?>
        <div style="font-family:Arial, sans-serif; font-size:12px;">
                <h2 style="text-align:center; margin:0 0 8px; color:#0c9ced;">Minutes of the Hearing</h2>
                <p style="text-align:center; margin:0 0 16px; color:#555;">Case #<?= htmlspecialchars($log['case_original_id']) ?> · Hearing Date: <?= htmlspecialchars($log['Hearing_Date']) ?> · Time: <?= htmlspecialchars($time_range) ?></p>
                <h3 style="margin:12px 0 6px;">Case Information</h3>
                <table width="100%" cellpadding="4" cellspacing="0" style="border:1px solid #ccc; border-collapse:collapse;">
                        <tr><td style="border:1px solid #ddd;">Case Original ID</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($log['case_original_id']) ?></td></tr>
                        <tr><td style="border:1px solid #ddd;">Status</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($log['Case_Status']) ?></td></tr>
                        <tr><td style="border:1px solid #ddd;">Date Filed</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($log['Date_Filed']) ?></td></tr>
                        <tr><td style="border:1px solid #ddd;">Complaint Title</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($log['Complaint_Title']) ?></td></tr>
                        <tr><td style="border:1px solid #ddd;">Complainant</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($log['complainant_name']) ?> (<?= htmlspecialchars($complainant_status_label) ?>)</td></tr>
                        <tr><td style="border:1px solid #ddd;">Mediator / Lupon</td><td style="border:1px solid #ddd;"><?= htmlspecialchars($lupon_name) ?></td></tr>
                </table>
                <h3 style="margin:14px 0 6px;">Respondents</h3>
                <table width="100%" cellpadding="4" cellspacing="0" style="border:1px solid #ccc; border-collapse:collapse;">
                        <tr style="background:#e0effe;"><th style="border:1px solid #ddd; text-align:left;">Name</th><th style="border:1px solid #ddd; text-align:left;">Status</th></tr>
                        <?php if(count($respondents)): foreach ($respondents as $r): ?>
                        <tr><td style="border:1px solid #ddd;"><?= htmlspecialchars($r['name']) ?></td><td style="border:1px solid #ddd;"><?= htmlspecialchars($r['status_label']) ?></td></tr>
                        <?php endforeach; else: ?>
                        <tr><td style="border:1px solid #ddd;" colspan="2">No respondents listed.</td></tr>
                        <?php endif; ?>
                </table>
                <h3 style="margin:14px 0 6px;">Attendance Summary</h3>
                <?php if (!empty($log['Attendance'])): ?>
                    <ul style="margin:0 0 4px 16px; padding:0;">
                        <?php foreach (explode(' | ', $log['Attendance']) as $e): ?>
                         <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin:0 0 4px; color:#666;">No attendance recorded.</p>
                <?php endif; ?>
                <p style="margin:4px 0 14px;"><strong>Reason(s) of Non-Compliance:</strong> <?= htmlspecialchars($log['Reason_Incompliance'] ?: 'N/A') ?></p>
                <h3 style="margin:14px 0 6px;">Hearing Details / Minutes</h3>
                <div style="border:1px solid #ccc; padding:8px; min-height:80px;"><?= nl2br(htmlspecialchars($log['Hearing_Details'])) ?></div>
                <h3 style="margin:14px 0 6px;">Signatures</h3>
                <table width="100%" cellpadding="4" cellspacing="0" style="border:0;">
                    <tr>
                        <td style="width:50%; padding:12px 8px;">
                            <div style="border-bottom:1px solid #333; height:34px;"></div>
                            <div style="text-align:center; font-size:11px; margin-top:4px;">Complainant: <?= htmlspecialchars($log['complainant_name']) ?></div>
                        </td>
                        <td style="width:50%; padding:12px 8px;">
                            <div style="border-bottom:1px solid #333; height:34px;"></div>
                            <div style="text-align:center; font-size:11px; margin-top:4px;">Lupon / Mediator: <?= htmlspecialchars($lupon_name) ?></div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width:50%; padding:12px 8px;">
                            <div style="border-bottom:1px solid #333; height:34px;"></div>
                            <div style="text-align:center; font-size:11px; margin-top:4px;">Barangay Secretary: <?= htmlspecialchars($secretary_name) ?></div>
                        </td>
                        <td style="width:50%; padding:12px 8px;">
                            <div style="border-bottom:1px solid #333; height:34px;"></div>
                            <div style="text-align:center; font-size:11px; margin-top:4px;">Barangay Captain: <?= htmlspecialchars($captain_name) ?></div>
                        </td>
                    </tr>
                </table>
                <?php if(count($respondents)): ?>
                <h4 style="margin:10px 0 4px;">Respondent Signatures</h4>
                <table width="100%" cellpadding="4" cellspacing="0" style="border:0;">
                    <?php foreach ($respondents as $r): ?>
                        <tr>
                            <td style="width:50%; padding:10px 8px;">
                                <div style="border-bottom:1px solid #333; height:28px;"></div>
                                <div style="text-align:center; font-size:11px; margin-top:4px;">Respondent: <?= htmlspecialchars($r['name']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
                <p style="text-align:center; font-size:10px; margin-top:18px; color:#666;">Generated on <?= date('F d, Y g:i A') ?> · Barangay Case Management Information System</p>
        </div>
        <?php
        $pdfFragment = ob_get_clean();
}

// If download requested and we have a log, attempt PDF generation via Dompdf
if ($download && $log) {
        $autoloadLocal = __DIR__ . '/vendor/autoload.php';
        $autoloadRoot  = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($autoloadLocal)) {
                require_once $autoloadLocal;
        } elseif (file_exists($autoloadRoot)) {
                require_once $autoloadRoot;
        }
        if (class_exists('Dompdf\\Dompdf')) {
                try {
                        $dompdf = new Dompdf\Dompdf();
                        $dompdf->loadHtml($pdfFragment);
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();
                        $dompdf->stream('meeting_log_' . $log_id . '.pdf', ['Attachment' => true]);
                        exit;
                } catch (Exception $e) {
                        // Fallback to plain HTML download
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="meeting_log_' . $log_id . '.html"');
                        echo $pdfFragment;
                        exit;
                }
        } else {
                // Dompdf not available: fallback raw HTML
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="meeting_log_' . $log_id . '.html"');
                echo $pdfFragment;
                exit;
        }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
<title>Generate Meeting Log</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}}}}</script>
<style>
@media print { .no-print { display:none !important;} body { background:white; } }
.signature-line { border-bottom:1px solid #4b5563; height:38px; }
</style>
</head>
<body class="bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen p-6">
<div class="max-w-4xl mx-auto bg-white shadow-md rounded-xl p-8 relative">
    <div class="no-print absolute top-4 right-4 flex gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"><i class="fa fa-print"></i> Print</button>
        <a href="meeting_cases_log.php?id=<?php echo $case_id; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">Back</a>
    </div>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-semibold text-primary-700">Minutes of the Hearing</h1>
        <?php if ($log): ?>
            <p class="text-sm text-gray-600 mt-1">Case #<?php echo htmlspecialchars($log['case_original_id']); ?> &middot; Hearing Date: <?php echo htmlspecialchars($log['Hearing_Date']); ?> &middot; Time: <?php echo htmlspecialchars($time_range); ?></p>
        <?php endif; ?>
    </div>

    <?php if(!$log): ?>
        <div class="p-6 bg-red-50 border border-red-200 rounded">
            <p class="text-red-700">Meeting log not found. Please go back and select a valid hearing.</p>
        </div>
    <?php else: ?>
    <section class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Case Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="bg-primary-50 p-4 rounded border border-primary-100">
                <p><span class="font-semibold">Case Original ID:</span> <?php echo htmlspecialchars($log['case_original_id']); ?></p>
                <p><span class="font-semibold">Status:</span> <?php echo htmlspecialchars($log['Case_Status']); ?></p>
                <p><span class="font-semibold">Date Filed:</span> <?php echo htmlspecialchars($log['Date_Filed']); ?></p>
            </div>
            <div class="bg-primary-50 p-4 rounded border border-primary-100">
                <p><span class="font-semibold">Complaint Title:</span> <?php echo htmlspecialchars($log['Complaint_Title']); ?></p>
                <p><span class="font-semibold">Complainant:</span> <?php echo htmlspecialchars($log['complainant_name']); ?> (<?php echo htmlspecialchars($complainant_status_label); ?>)</p>
                <p><span class="font-semibold">Mediator / Lupon:</span> <?php echo htmlspecialchars($lupon_name); ?></p>
            </div>
        </div>
    </section>

    <section class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Respondents</h2>
        <table class="w-full text-sm border border-gray-200 rounded overflow-hidden">
            <thead class="bg-primary-100 text-primary-800">
                <tr>
                    <th class="py-2 px-3 text-left">Name</th>
                    <th class="py-2 px-3 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($respondents as $r): ?>
                    <tr class="border-t border-gray-200">
                        <td class="py-2 px-3"><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="py-2 px-3">
                            <?php echo htmlspecialchars($r['status_label']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!count($respondents)): ?>
                    <tr><td colspan="2" class="py-2 px-3 text-gray-500">No respondents listed.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Attendance Summary</h2>
        <div class="bg-primary-50 p-4 rounded border border-primary-100 text-sm">
            <?php 
            if (!empty($log['Attendance'])) {
                $entries = explode(' | ', $log['Attendance']);
                echo '<ul class="list-disc pl-5 space-y-1">';
                foreach ($entries as $e) { echo '<li>' . htmlspecialchars($e) . '</li>'; }
                echo '</ul>'; 
            } else { echo '<p class="text-gray-500">No attendance recorded.</p>'; }
            ?>
            <p class="mt-3"><span class="font-semibold">Reason(s) of Non-Compliance:</span> <?php echo htmlspecialchars($log['Reason_Incompliance'] ?: 'N/A'); ?></p>
        </div>
    </section>

    <section class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Hearing Details / Minutes</h2>
        <div class="bg-white border border-gray-200 rounded p-4 text-sm whitespace-pre-wrap min-h-[120px]">
            <?php echo nl2br(htmlspecialchars($log['Hearing_Details'])); ?>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Signatures</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div>
                <div class="signature-line"></div>
                <p class="mt-1 text-center">Complainant: <?php echo htmlspecialchars($log['complainant_name']); ?></p>
            </div>
            <div>
                <div class="signature-line"></div>
                <p class="mt-1 text-center">Lupon / Mediator: <?php echo htmlspecialchars($lupon_name); ?></p>
            </div>
            <div>
                <div class="signature-line"></div>
                <p class="mt-1 text-center">Barangay Secretary: <?php echo htmlspecialchars($secretary_name); ?></p>
            </div>
            <div>
                <div class="signature-line"></div>
                <p class="mt-1 text-center">Barangay Captain: <?php echo htmlspecialchars($captain_name); ?></p>
            </div>
            <?php if(count($respondents)): ?>
                <div class="md:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-<?php echo min(3, max(1,count($respondents))); ?> gap-6">
                        <?php foreach ($respondents as $r): ?>
                            <div>
                                <div class="signature-line"></div>
                                <p class="mt-1 text-center">Respondent: <?php echo htmlspecialchars($r['name']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="text-center text-xs text-gray-500 mt-8">
        Generated on <?php echo date('F d, Y g:i A'); ?> &middot; Barangay Case Management Information System
    </footer>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</body>
</html>
