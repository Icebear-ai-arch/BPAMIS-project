<?php
include_once __DIR__ . '/../server/server.php';

$status_filter = $_GET['status'] ?? 'All';
$month_filter = $_GET['month'] ?? 'All';
$year_filter = $_GET['year'] ?? 'All';

// Query for cases with completed meeting logs (visible = 0 or has meeting logs)
$sql = "SELECT DISTINCT 
    ci.Case_ID, 
    ci.Case_Status, 
    ci.case_original_id, 
    co.case_type, 
    co.Complaint_Title, 
    co.Date_Filed,
    ml.Hearing_Date,
    ml.Hearing_Time,
    ml.Log_ID,
    sl.hearingID
FROM CASE_INFO ci
JOIN COMPLAINT_INFO co ON ci.Complaint_ID = co.Complaint_ID
INNER JOIN MEETING_LOGS ml ON ml.Case_ID = ci.Case_ID
LEFT JOIN schedule_list sl ON sl.Case_ID = ml.Case_ID 
    AND DATE(sl.HearingDateTime) = ml.Hearing_Date
    AND TIME(sl.HearingDateTime) = ml.Hearing_Time
WHERE 1=1";

// Status filter
if ($status_filter !== 'All') {
    $sql .= " AND ci.Case_Status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Month filter - filter by hearing date from meeting logs
if ($month_filter !== 'All') {
    $sql .= " AND MONTH(ml.Hearing_Date) = " . intval($month_filter);
}

// Year filter - filter by hearing date from meeting logs
if ($year_filter !== 'All') {
    $sql .= " AND YEAR(ml.Hearing_Date) = " . intval($year_filter);
}

$sql .= " ORDER BY ml.Hearing_Date DESC, ml.Hearing_Time DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['Case_Status'];
        $badge = [
            'Open' => ['text-blue-700 bg-blue-50 border-blue-200', 'fa-folder-open'],
            'Pending Hearing' => ['text-amber-700 bg-amber-50 border-amber-200', 'fa-calendar'],
            'Mediation' => ['text-purple-700 bg-purple-50 border-purple-200', 'fa-handshake'],
            'Resolution' => ['text-green-700 bg-green-50 border-green-200', 'fa-check-circle'],
            'Settlement' => ['text-pink-700 bg-pink-50 border-pink-200', 'fa-scale-balanced'],
            'Closed' => ['text-gray-700 bg-gray-50 border-gray-200', 'fa-folder'],
            'Mediation Resolved' => ['text-emerald-700 bg-emerald-50 border-emerald-200', 'fa-check-double'],
            'Conciliation Resolved' => ['text-teal-700 bg-teal-50 border-teal-200', 'fa-check-double'],
            'Arbitration Resolved' => ['text-cyan-700 bg-cyan-50 border-cyan-200', 'fa-check-double'],
        ];
        $class = $badge[$status][0] ?? 'text-gray-700 bg-gray-50 border-gray-200';
        $icon = $badge[$status][1] ?? 'fa-info-circle';

        // Use hearing date and time from meeting logs
        $hearingDate = '—';
        $hearingTime = '—';
        if (!empty($row['Hearing_Date'])) {
            $hearingDate = date("Y-m-d", strtotime($row['Hearing_Date']));
        }
        if (!empty($row['Hearing_Time'])) {
            $hearingTime = date("h:i A", strtotime($row['Hearing_Time']));
        }

        // Prefer case_original_id for display; fall back to numeric Case_ID when missing
        $displayCaseId = !empty($row['case_original_id']) ? htmlspecialchars($row['case_original_id']) : 'C' . htmlspecialchars($row['Case_ID']);
        $caseType = !empty($row['case_type']) ? htmlspecialchars($row['case_type']) : htmlspecialchars($row['Complaint_Title']);

        $rowHtml = '<tr class="border-b border-gray-100 hover:bg-gray-50 transition">';
        $rowHtml .= '<td class="p-3 text-sm text-gray-700">' . $displayCaseId . '</td>';
        $rowHtml .= '<td class="p-3 text-sm text-gray-700">' . $caseType . '</td>';
        $rowHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($row['Date_Filed']) . '</td>';
        $rowHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($hearingDate) . '</td>';
        $rowHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($hearingTime) . '</td>';
        $rowHtml .= '<td class="px-4 py-2">';
        $rowHtml .= '<span class="px-2 py-1 rounded-full text-xs border ' . $class . '">';
        $rowHtml .= '<i class="fas ' . $icon . ' mr-1"></i>' . htmlspecialchars($status);
        $rowHtml .= '</span>';
        $rowHtml .= '</td>';
    // Build meeting_cases_log link (prefer hearing_id when available)
        $meetingLink = 'meeting_cases_log.php?case_id=' . urlencode($row['Case_ID']);
        if (!empty($row['hearingID'])) { $meetingLink .= '&hearing_id=' . urlencode($row['hearingID']); }
        
    $rowHtml .= '<td class="p-3 text-center">';
        $rowHtml .= '<a href="' . $meetingLink . '" class="text-green-600 hover:text-green-800 transition p-1" title="View Log">';
        $rowHtml .= '<i class="fas fa-eye"></i>';
        $rowHtml .= '</a>';
        $rowHtml .= '</td>';
        $rowHtml .= '</tr>';
        echo $rowHtml;
    }
} else {
    echo "<tr>
            <td colspan='7' class='text-center px-4 py-4 text-gray-500'>No completed meeting logs found.</td>
          </tr>";
}

$conn->close();
?>
