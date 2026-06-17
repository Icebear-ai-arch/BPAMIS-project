<?php
include_once __DIR__ . '/../server/server.php';

$status_filter = $_GET['status'] ?? 'All';
$month_filter = $_GET['month'] ?? 'All';
$day_filter = $_GET['day'] ?? 'All';
$year_filter = $_GET['year'] ?? 'All';

 $sql = "SELECT ci.Case_ID, ci.Case_Status, ci.case_original_id, co.case_type, co.Complaint_Title, co.Date_Filed, sl.hearingDateTime, sl.hearingID
     FROM CASE_INFO ci
     JOIN COMPLAINT_INFO co ON ci.Complaint_ID = co.Complaint_ID
     -- require an appointed hearing (hearingDateTime present) and visible schedule
     JOIN schedule_list sl ON ci.Case_ID = sl.Case_ID AND sl.visible = 1 AND sl.hearingDateTime IS NOT NULL
     LEFT JOIN MEETING_LOGS ml ON ml.Case_ID = ci.Case_ID 
         AND DATE(ml.Hearing_Date) = DATE(sl.hearingDateTime)
         AND TIME(ml.Hearing_Time) = TIME(sl.hearingDateTime)
     -- only show cases whose status is mediation, conciliation or arbitration
     WHERE TRIM(LOWER(ci.Case_Status)) IN ('mediation','conciliation','arbitration')";

// Status filter
if ($status_filter !== 'All') {
    $sql .= " AND ci.Case_Status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Month filter (apply to hearing date/time)
if ($month_filter !== 'All') {
    $sql .= " AND MONTH(sl.hearingDateTime) = " . intval($month_filter);
}

// Day filter (apply to hearing date/time)
if ($day_filter !== 'All') {
    $sql .= " AND DAY(sl.hearingDateTime) = " . intval($day_filter);
}

// Year filter (apply to hearing date/time)
if ($year_filter !== 'All') {
    $sql .= " AND YEAR(sl.hearingDateTime) = " . intval($year_filter);
}

$sql .= " ORDER BY (sl.hearingDateTime >= NOW()) DESC, ABS(TIMESTAMPDIFF(SECOND, NOW(), sl.hearingDateTime)) ASC";
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
        ];
        $class = $badge[$status][0] ?? 'text-gray-700 bg-gray-50 border-gray-200';
        $icon = $badge[$status][1] ?? 'fa-info-circle';

        // Split date & time
        $hearingDate = '—';
        $hearingTime = '—';
        if (!empty($row['hearingDateTime'])) {
            $timestamp = strtotime($row['hearingDateTime']);
            $hearingDate = date("Y-m-d", $timestamp);  // Example: 2025-08-11
            $hearingTime = date("h:i A", $timestamp);  // Example: 02:45 PM
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
    $rowHtml .= '<a href="' . $meetingLink . '" class="text-yellow-600 hover:text-yellow-800 transition p-1" title="Fill Log">';
    $rowHtml .= '<i class="fas fa-edit"></i>';
    $rowHtml .= '</a>';
    $rowHtml .= '</td>';
    $rowHtml .= '</tr>';
    echo $rowHtml;
    }
} else {
    echo "<tr>
            <td colspan='7' class='text-center px-4 py-4 text-gray-500'>No cases found.</td>
          </tr>";
}

$conn->close();
?>
