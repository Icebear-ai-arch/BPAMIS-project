<?php
include '../server/server.php';

$status = $_POST['status'] ?? 'all';
$range = $_POST['range'] ?? 'this_month';

$where = "1=1";

// Filter by Case Status (broad matching to support UI labels)
if ($status !== 'all') {
    $safeStatus = strtolower($conn->real_escape_string($status));
    switch ($safeStatus) {
        case 'pending hearing':
            // Pending hearing: ensure it has schedules
            $where .= " AND c.Case_ID IN (SELECT DISTINCT Case_ID FROM schedule_list WHERE Case_ID IS NOT NULL)";
            break;
        case 'mediation':
            $where .= " AND LOWER(c.Case_Status) LIKE '%mediation%'";
            break;
        case 'conciliation':
            // Conciliation maps to resolution/conciliation/resolved variants
            $where .= " AND (LOWER(c.Case_Status) LIKE '%conciliation%' OR LOWER(c.Case_Status) LIKE '%resolution%' OR LOWER(c.Case_Status) LIKE '%resolved%')";
            break;
        case 'arbitration':
            $where .= " AND LOWER(c.Case_Status) LIKE '%arbitration%'";
            break;
        case 'mediation resolved':
        case 'mediation_resolved':
        case 'conciliation resolved':
        case 'conciliation_resolved':
        case 'arbitration resolved':
        case 'arbitration_resolved':
            // Resolved/Closed bucket
            $where .= " AND LOWER(c.Case_Status) IN ('resolved','closed')";
            break;
        case 'certificate_to_file_action':
            $where .= " AND LOWER(c.Case_Status) LIKE '%certificate to file action%'";
            break;
        case 'certificate to file action':
            $where .= " AND LOWER(c.Case_Status) LIKE '%certificate to file action%'";
            break;
        case 'dismissed':
            $where .= " AND LOWER(c.Case_Status) LIKE '%dismissed%'";
            break;
        default:
            // Fallback to exact match
            $where .= " AND LOWER(c.Case_Status) = '$safeStatus'";
            break;
    }
}

// Filter by Date Range (use Date_Filed from complaint_info to match UI)
switch ($range) {
    case 'this_month':
        $where .= " AND MONTH(ci.Date_Filed) = MONTH(CURRENT_DATE()) 
                    AND YEAR(ci.Date_Filed) = YEAR(CURRENT_DATE())";
        break;
    case 'last_month':
        $where .= " AND MONTH(ci.Date_Filed) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
                    AND YEAR(ci.Date_Filed) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $where .= " AND YEAR(ci.Date_Filed) = YEAR(CURRENT_DATE())";
        break;
    case 'last_year':
        $where .= " AND YEAR(ci.Date_Filed) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR))";
        break;
    case 'q1':
        $where .= " AND MONTH(ci.Date_Filed) BETWEEN 1 AND 4 
                    AND YEAR(ci.Date_Filed) = YEAR(CURRENT_DATE())";
        break;
    case 'q2':
        $where .= " AND MONTH(ci.Date_Filed) BETWEEN 5 AND 8 
                    AND YEAR(ci.Date_Filed) = YEAR(CURRENT_DATE())";
        break;
    case 'q3':
        $where .= " AND MONTH(ci.Date_Filed) BETWEEN 9 AND 12 
                    AND YEAR(ci.Date_Filed) = YEAR(CURRENT_DATE())";
        break;
    case 'all':
    default:
        
        break;
}



// Query to fetch filtered case data
$query = "SELECT 
    c.Case_ID,
    c.case_original_id,
    ci.case_type,
    ci.Date_Filed,
    c.Case_Status,
    ri.First_Name AS resident_fname,
    ri.Last_Name AS resident_lname,
    eci.first_name AS external_fname,
    eci.last_name AS external_lname
FROM case_info c
JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID
LEFT JOIN resident_info ri ON ci.Resident_ID = ri.Resident_ID
LEFT JOIN external_complainant eci ON c.Complaint_ID = eci.external_Complaint_ID
WHERE $where
ORDER BY ci.Date_Filed DESC";

$result = $conn->query($query);

$tableHtml = '';
$statusCount = [];
$monthlyCount = [];
$total = $active = $closed = $totalDays = $countForAvg = 0;

if ($result && $result->num_rows > 0) {
    while ($case = $result->fetch_assoc()) {
        $total++;
        $statusText = ucfirst(strtolower(trim($case['Case_Status'])));
        $statusCount[$statusText] = ($statusCount[$statusText] ?? 0) + 1;

        $filedMonth = (int)date('n', strtotime($case['Date_Filed']));
        $monthlyCount[$filedMonth] = ($monthlyCount[$filedMonth] ?? 0) + 1;

        if (in_array(strtolower($statusText), ['open', 'pending hearing', 'mediation'])) {
            $active++;
        } elseif (in_array(strtolower($statusText), ['resolved', 'closed'])) {
            $closed++;
        }

        // Determine complainant name
        $complainantName = !empty($case['resident_fname']) 
            ? $case['resident_fname'] . ' ' . $case['resident_lname']
            : $case['external_fname'] . ' ' . $case['external_lname'];

        // Status icon and color classes
        $statusClass = '';
        $statusIcon = '';
        switch ($statusText) {
            case 'Open':
                $statusClass = 'text-blue-700 bg-blue-50 border-blue-200';
                $statusIcon = '<i class="fas fa-folder-open mr-1"></i>';
                break;
            case 'Pending Hearing':
                $statusClass = 'text-amber-700 bg-amber-50 border-amber-200';
                $statusIcon = '<i class="fas fa-calendar mr-1"></i>';
                break;
            case 'Mediation':
                $statusClass = 'text-purple-700 bg-purple-50 border-purple-200';
                $statusIcon = '<i class="fas fa-handshake mr-1"></i>';
                break;
            case 'Resolved':
                $statusClass = 'text-green-700 bg-green-50 border-green-200';
                $statusIcon = '<i class="fas fa-check-circle mr-1"></i>';
                break;
            case 'Closed':
                $statusClass = 'text-gray-700 bg-gray-50 border-gray-200';
                $statusIcon = '<i class="fas fa-folder mr-1"></i>';
                break;
            default:
                $statusClass = 'text-gray-700 bg-gray-50 border-gray-200';
                $statusIcon = '<i class="fas fa-info-circle mr-1"></i>';
        }

        // Build table row
                $tableHtml .= '<tr class="border-b border-gray-100 hover:bg-gray-50 transition">';
                // Prefer original textual ID for display
                $displayId = trim($case['case_original_id'] ?? '') !== '' ? $case['case_original_id'] : $case['Case_ID'];
                $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($displayId) . '</td>';
                $typeDisplay = trim($case['case_type'] ?? '') !== '' ? ucwords($case['case_type']) : 'Unspecified Case Type';
                $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($typeDisplay) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($complainantName) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($case['Date_Filed']) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm"><span class="px-2 py-1 rounded-full text-xs border ' . $statusClass . '">' . $statusIcon . htmlspecialchars($statusText) . '</span></td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">N/A</td>';
                $tableHtml .= '<td class="p-3 text-center">'
                                         . '<a href="view_case_details.php?id=' . urlencode($case['Case_ID']) . '" '
                                         . 'class="card-hover bg-primary-500 text-white px-3 py-1.5 rounded-lg hover:bg-primary-600 transition inline-flex items-center text-sm">'
                                         . '<i class="fas fa-file-alt mr-1.5"></i> View Report'
                                         . '</a>'
                                         . '</td>';
        $tableHtml .= '</tr>';

        // For average resolution time
    $start = new DateTime($case['Date_Filed']);
        $end = new DateTime();
        $totalDays += $start->diff($end)->days;
        $countForAvg++;
    }
} else {
    switch ($range) {
        case 'this_month':
            $label = 'this month';
            break;
        case 'last_month':
            $label = 'last month';
            break;
        case 'this_year':
            $label = 'this year';
            break;
        case 'last_year':
            $label = 'last year';
            break;
        case 'q1':
            $label = 'Quarter 1 (Jan–Apr)';
            break;
        case 'q2':
            $label = 'Quarter 2 (May–Aug)';
            break;
        case 'q3':
            $label = 'Quarter 3 (Sep–Dec)';
            break;
        default:
            $label = 'the selected period';
    }

$tableHtml = '<tr><td colspan="7" class="p-4 text-center text-gray-500">No cases currently within ' . $label . '.</td></tr>';

}

// Prepare month chart data
ksort($monthlyCount);
$monthLabelsFinal = [];
$monthCountsFinal = [];

for ($i = 1; $i <= 12; $i++) {
    $monthLabelsFinal[] = date('M', mktime(0, 0, 0, $i, 1));
    $monthCountsFinal[] = $monthlyCount[$i] ?? 0;
}

// Output JSON
echo json_encode([
    'tableHtml' => $tableHtml,
    'statusLabels' => array_keys($statusCount),
    'statusData' => array_values($statusCount),
    'monthLabels' => $monthLabelsFinal,
    'monthCounts' => $monthCountsFinal,
    'stats' => [
        'total' => $total,
        'active' => $active,
        'closed' => $closed,
        'avg_resolution' => $countForAvg > 0 ? round($totalDays / $countForAvg, 1) : 0
    ]
]);
?>
