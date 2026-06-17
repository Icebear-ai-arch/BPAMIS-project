<?php
// Robust autoload detection (local vendor then parent)
$autoloadLocal = __DIR__ . '/vendor/autoload.php';
$autoloadRoot  = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadLocal)) {
    require $autoloadLocal;
} elseif (file_exists($autoloadRoot)) {
    require $autoloadRoot;
}
include '../server/server.php';
use Dompdf\Dompdf;
use Dompdf\Options;
$status = $_POST['status'] ?? 'all';
$range = $_POST['range'] ?? 'all';
$download = !empty($_POST['download']);


$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Embed the logo as base64
$logoPath = realpath('../assets/img/logo.png');
$logoBase64 = '';
if ($logoPath && file_exists($logoPath)) {
    $logoBase64 = base64_encode(file_get_contents($logoPath));
    $logoMime = mime_content_type($logoPath);
    $logoTag = '<img src="data:' . $logoMime . ';base64,' . $logoBase64 . '" width="80">';
} else {
    $logoTag = '<strong>Barangay Logo</strong>';
}
$where = "1=1";

// Filter by status (case-insensitive)
if ($status !== 'all') {
    $safeStatus = $conn->real_escape_string($status);
    $where .= " AND LOWER(c.Case_Status) = LOWER('{$safeStatus}')";
}

// Filter by date range
$currentDate = date('Y-m-d');

switch ($range) {
    case 'this_month':
        $where .= " AND MONTH(c.Date_Opened) = MONTH(CURRENT_DATE()) 
                    AND YEAR(c.Date_Opened) = YEAR(CURRENT_DATE())";
        break;
    case 'last_month':
        $where .= " AND MONTH(c.Date_Opened) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                    AND YEAR(c.Date_Opened) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $where .= " AND YEAR(c.Date_Opened) = YEAR(CURRENT_DATE())";
        break;
    case 'last_year':
        $where .= " AND YEAR(c.Date_Opened) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR))";
        break;
    case 'q1':
        $where .= " AND MONTH(c.Date_Opened) BETWEEN 1 AND 4 
                    AND YEAR(c.Date_Opened) = YEAR(CURRENT_DATE())";
        break;
    case 'q2':
        $where .= " AND MONTH(c.Date_Opened) BETWEEN 5 AND 8 
                    AND YEAR(c.Date_Opened) = YEAR(CURRENT_DATE())";
        break;
    case 'q3':
        $where .= " AND MONTH(c.Date_Opened) BETWEEN 9 AND 12 
                    AND YEAR(c.Date_Opened) = YEAR(CURRENT_DATE())";
        break;
}



$query = "SELECT 
    c.Case_ID,
    c.case_original_id,
    ci.case_type,
    ci.Complaint_Title,
    ci.Date_Filed, 
    c.Case_Status,
    c.Date_Opened,
    c.Date_Closed,
    c.Next_Hearing_Date,
    ri.First_Name AS resident_fname,
    ri.Last_Name AS resident_lname,
    eci.first_name AS external_fname,
    eci.last_name AS external_lname
FROM case_info c
JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID
LEFT JOIN resident_info ri ON ci.Resident_ID = ri.Resident_ID
LEFT JOIN external_complainant eci ON c.Complaint_ID = eci.external_Complaint_ID
WHERE $where
ORDER BY c.Date_Opened DESC";


$result = $conn->query($query);

$html = '
<style>
    body { font-family: sans-serif; font-size: 12px; }
    .header { text-align: center; margin-bottom: 20px; }
    .header img { margin-bottom: 10px; }
    .case-block { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    .case-title { font-weight: bold; margin-bottom: 5px; }
    .sub { margin-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #999; padding: 6px; font-size: 11px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>
<div class="header">
    ' . $logoTag . '
    <h2>Barangay Panducot<br>Case Report</h2>
</div>
';

if ($result->num_rows > 0) {
    while ($case = $result->fetch_assoc()) {
        $complainant = $case['resident_fname'] 
            ? $case['resident_fname'] . ' ' . $case['resident_lname'] 
            : $case['external_fname'] . ' ' . $case['external_lname'];

        $displayId = trim($case['case_original_id'] ?? '') !== '' ? $case['case_original_id'] : $case['Case_ID'];
        $typeDisplay = trim($case['case_type'] ?? '') !== '' ? ucwords($case['case_type']) : 'Unspecified Case Type';
        $html .= '
        <div class="case-block">
            <div class="case-title">Case No: ' . htmlspecialchars($displayId) . ' - ' . htmlspecialchars($typeDisplay) . '</div>
            <div class="sub">Case Type: ' . htmlspecialchars(ucwords($case['case_type'] ?? '')) . '</div>
            <div class="sub">Filed On: ' . htmlspecialchars($case['Date_Filed']) . '</div>
            <div class="sub">Complainant: ' . htmlspecialchars($complainant) . '</div>
            <div class="sub">Status: ' . htmlspecialchars($case['Case_Status']) . '</div>
            <div class="sub">Date Opened: ' . htmlspecialchars($case['Date_Opened']) . '</div>
            <div class="sub">Date Closed: ' . ($case['Date_Closed'] ?? 'N/A') . '</div>
            <div class="sub">Next Hearing: ' . ($case['Next_Hearing_Date'] ?? 'N/A') . '</div>
        ';

        // Get hearing logs for the current case
        $caseId = $case['Case_ID'];
        $hearingQuery = "SELECT hearingTitle, hearingDateTime, place, participant, remarks 
                         FROM schedule_list 
                         WHERE Case_ID = $caseId 
                         ORDER BY hearingDateTime ASC";
        $hearingResult = $conn->query($hearingQuery);

        if ($hearingResult->num_rows > 0) {
            $html .= '<table><thead><tr>
                        <th>Title</th>
                        <th>Date & Time</th>
                        <th>Place</th>
                        <th>Participants</th>
                        <th>Remarks</th>
                      </tr></thead><tbody>';
            while ($h = $hearingResult->fetch_assoc()) {
                $html .= '<tr>
                            <td>' . htmlspecialchars($h['hearingTitle']) . '</td>
                            <td>' . htmlspecialchars($h['hearingDateTime']) . '</td>
                            <td>' . htmlspecialchars($h['place']) . '</td>
                            <td>' . htmlspecialchars($h['participant']) . '</td>
                            <td>' . htmlspecialchars($h['remarks']) . '</td>
                          </tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="sub">No hearings scheduled for this case.</div>';
        }

        $html .= '</div>'; // Close case-block
    }
} else {
    $html .= '<div style="text-align: center; font-weight: bold; margin-top: 30px;">No cases found for the selected status and date range.</div>';

}

// Load to Dompdf and render
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream the file to browser (inline view or forced download)
$dompdf->stream("barangay_case_report.pdf", ["Attachment" => $download]);
exit;
