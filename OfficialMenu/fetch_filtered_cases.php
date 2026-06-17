<?php
include '../server/server.php';

$status = $_POST['status'] ?? 'all';
$range = $_POST['range'] ?? 'this_month';

$where = "1";
if ($status !== 'all') {
    $safeStatus = strtolower($conn->real_escape_string($status));

   if ($safeStatus === 'pending hearing') {
    $where .= " AND c.Case_ID IN (
        SELECT DISTINCT Case_ID FROM schedule_list WHERE Case_ID IS NOT NULL
    )";
}

}


// Handle date range
switch ($range) {
    case 'this_month':
        $where .= " AND MONTH(ci.Date_Filed) = MONTH(CURDATE()) AND YEAR(ci.Date_Filed) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $where .= " AND MONTH(ci.Date_Filed) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(ci.Date_Filed) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        break;
    case 'this_year':
        $where .= " AND YEAR(ci.Date_Filed) = YEAR(CURDATE())";
        break;
}

// Query with LEFT JOIN for both residents and external complainants
$query = "SELECT 
    c.Case_ID,
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

$tableHtml = '';
$statusCount = [];
$monthlyCount = [];
$total = $active = $closed = $totalDays = $countForAvg = 0;

if ($result->num_rows > 0) {
    while ($case = $result->fetch_assoc()) {
        $total++;
        $status = ucfirst(strtolower(trim($case['Case_Status'])));
        $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;

        $filedMonth = (int)date('n', strtotime($case['Date_Filed']));
        $monthlyCount[$filedMonth] = ($monthlyCount[$filedMonth] ?? 0) + 1;

        if (in_array(strtolower($status), ['open', 'pending hearing', 'mediation'])) {
            $active++;
        } elseif (in_array(strtolower($status), ['resolved', 'closed'])) {
            $closed++;
        }

        // Determine complainant name
        $complainantName = !empty($case['resident_fname']) 
            ? $case['resident_fname'] . ' ' . $case['resident_lname']
            : $case['external_fname'] . ' ' . $case['external_lname'];

        // Status icon & badge class
        $statusClass = '';
        $statusIcon = '';
        switch ($case['Case_Status']) {
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

        // Build HTML row
        $tableHtml .= '<tr class="border-b border-gray-100 hover:bg-gray-50 transition">';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($case['Case_ID']) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($case['Complaint_Title']) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($complainantName) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($case['Date_Filed']) . '</td>';
        $tableHtml .= '<td class="p-3 text-sm"><span class="px-2 py-1 rounded-full text-xs border ' . $statusClass . '">' . $statusIcon . htmlspecialchars($status) . '</span></td>';
        $tableHtml .= '<td class="p-3 text-sm text-gray-700">N/A</td>';
        $tableHtml .= '<td class="p-3 text-center">
                        <a href="case_report_details.php?id=' . urlencode($case['Case_ID']) . '" 
                        class="card-hover bg-primary-500 text-white px-3 py-1.5 rounded-lg hover:bg-primary-600 transition inline-flex items-center text-sm">
                        <i class="fas fa-file-alt mr-1.5"></i> View Report
                        </a>
                      </td>';
        $tableHtml .= '</tr>';

        $start = new DateTime($case['Date_Filed']);
        $end = new DateTime();
        $totalDays += $start->diff($end)->days;
        $countForAvg++;
    }
}

ksort($monthlyCount);
$monthLabelsFinal = [];
$monthCountsFinal = [];

for ($i = 1; $i <= 12; $i++) {
    $monthLabelsFinal[] = date('M', mktime(0, 0, 0, $i, 10));
    $monthCountsFinal[] = $monthlyCount[$i] ?? 0;
}

echo json_encode([
    'tableHtml' => $tableHtml ?: '<tr><td colspan="7" class="p-4 text-center text-gray-500">No cases found.</td></tr>',
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