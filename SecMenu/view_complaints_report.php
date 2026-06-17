
<?php

include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

// Summary statistics
$totalComplaints = 0;
$pendingCount = 0;
$resolvedCount = 0;
$monthlyTrends = [];
$statusChart = [];

$sql = "SELECT Status, Date_Filed FROM complaint_info";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $totalComplaints = $result->num_rows;
    while ($row = $result->fetch_assoc()) {
        $status = $row['Status'];
        $month = date('M', strtotime($row['Date_Filed']));

        // Count status
        if (!isset($statusChart[$status])) $statusChart[$status] = 0;
        $statusChart[$status]++;

        // Count monthly
        if (!isset($monthlyTrends[$month])) $monthlyTrends[$month] = 0;
        $monthlyTrends[$month]++;

        // Count specific statuses
        if ($status === 'Pending') $pendingCount++;
        if ($status === 'Resolved') $resolvedCount++;
    }
}

// Prepare quarterly line chart for complaints
$quarters = ['Q1','Q2','Q3','Q4'];
$quarterCounts = [0,0,0,0];
$complaintQuarterQuery = "SELECT Date_Filed FROM complaint_info";
$complaintQuarterResult = $conn->query($complaintQuarterQuery);
if ($complaintQuarterResult && $complaintQuarterResult->num_rows > 0) {
    while ($row = $complaintQuarterResult->fetch_assoc()) {
        $month = (int)date('n', strtotime($row['Date_Filed']));
        if ($month >= 1 && $month <= 3) $quarterCounts[0]++;
        elseif ($month >= 4 && $month <= 6) $quarterCounts[1]++;
        elseif ($month >= 7 && $month <= 9) $quarterCounts[2]++;
        elseif ($month >= 10 && $month <= 12) $quarterCounts[3]++;
    }
}

// Prepare status chart for complaints with normalized labels:
// Labels: In Case, Pending, Record Purposes, Dismissed
$statusLabels = ['In Case', 'Pending', 'Record Purposes', 'Dismissed'];
$statusCounts = [0, 0, 0, 0];
// Use UPPER comparison so we catch variants like 'IN CASE' or 'In Case', and include older values like 'Rejected' which we map to 'Record Purposes'
$statusQuery = "SELECT UPPER(Status) AS ust, COUNT(*) as count FROM complaint_info WHERE UPPER(Status) IN ('IN CASE','PENDING','REJECTED','DISMISSED') GROUP BY UPPER(Status)";
$statusResult = $conn->query($statusQuery);
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $ust = $row['ust'];
        $cnt = (int)$row['count'];
        if ($ust === 'IN CASE') $statusCounts[0] = $cnt;
        if ($ust === 'PENDING') $statusCounts[1] = $cnt;
        if ($ust === 'REJECTED') $statusCounts[2] = $cnt; // map REJECTED -> Record Purposes
        if ($ust === 'DISMISSED') $statusCounts[3] = $cnt;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Complaints Report</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f0f7ff 0%, #e0effe 100%); }
        .premium-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); border-radius: 1.5rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10); border: 1px solid rgba(255,255,255,0.18); transition: box-shadow 0.3s, transform 0.3s; }
        .premium-card:hover { box-shadow: 0 16px 40px 0 rgba(31, 38, 135, 0.18); transform: translateY(-4px) scale(1.01); }
        .premium-gradient { background: linear-gradient(135deg, #bae2fd 0%, #7cccfd 100%); }
        .premium-header { background: linear-gradient(90deg, #e0effe 0%, #bae2fd 100%); border-radius: 1.5rem 1.5rem 0 0; }
    .premium-table { table-layout: fixed; width: 100%; }
    .premium-table th { background: #e0effe; color: #065a8f; font-weight: 600; }
    /* Normalize padding so header and cells align visually */
    .premium-table th, .premium-table td { padding: 0.75rem 1rem; vertical-align: middle; box-sizing: border-box; text-align: left; }
        /* Specific rule to right-align the Action column (last column) */
        .premium-table th:last-child, .premium-table td:last-child { text-align: right; }
        /* Action button appearance: render inline, center contents and keep small fixed size */
        .premium-table td .premium-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.35rem 0.7rem;
            min-width: 72px;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .premium-table td .premium-btn i {
            margin: 0 0.35rem 0 0;
            line-height: 1;
        }
        /* Allow text wrap inside other cells when table-layout: fixed is used */
        .premium-table td { overflow-wrap: anywhere; }
        /* Make status column text smaller and wrap/ellipsize to avoid layout break for long values (e.g. BLOTTER_CASE) */
        .premium-table td[data-label="Status"] {
            font-size: 0.85rem !important;
            line-height: 1 !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
            max-width: 10.5rem; /* constrain width so it doesn't push layout */
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
        }
        /* Blotter status: match the normal status sizing and overflow behavior */
        .premium-table td.blotter-status {
            font-size: 0.85rem !important;
            line-height: 1 !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
            max-width: 10.5rem; /* same constraint as other status cells */
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            letter-spacing: normal;
        }
        .premium-table tbody tr { transition: background 0.2s; cursor: pointer; }
        .premium-table tbody tr:hover { background: #f0f7ff; }
        .premium-stats { background: linear-gradient(135deg, #f0f7ff 0%, #e0effe 100%); border-radius: 1.5rem; }
        .premium-btn { transition: all 0.2s; box-shadow: 0 2px 8px rgba(12,156,237,0.08); }
        .premium-btn:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 6px 18px rgba(12,156,237,0.13); }
        .premium-icon { background: #dbeafe; color: #2563eb; border-radius: 9999px; width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-right: 0.75rem; }
        
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
            
            /* Container padding */
            .container {
                padding: 0.75rem !important;
            }
            
            /* Premium header - compact */
            .premium-header {
                padding: 0.75rem !important;
                margin-bottom: 1rem !important;
                border-radius: 1rem !important;
            }
            
            .premium-header h1 {
                font-size: 1.125rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .premium-header p {
                font-size: 0.7rem !important;
            }
            
            .premium-header {
                flex-direction: row !important;
                align-items: flex-start !important;
            }
            
            .premium-header > div:first-child {
                flex: 1 !important;
            }
            
            .premium-header .flex.gap-2 {
                flex-shrink: 0 !important;
            }
            
            /* Buttons - compact */
            .premium-btn {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            .premium-btn i {
                font-size: 0.7rem !important;
                margin-right: 0.25rem !important;
            }
            
            /* Premium cards - compact */
            .premium-card {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .premium-card h3 {
                font-size: 0.875rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Premium icon - smaller */
            .premium-icon {
                width: 1.75rem !important;
                height: 1.75rem !important;
                font-size: 0.875rem !important;
                margin-right: 0.5rem !important;
            }
            
            /* Grid layout - stack on mobile */
            .grid {
                gap: 1rem !important;
            }
            
            /* Filter form - compact */
            form .flex-wrap {
                gap: 0.75rem !important;
            }
            
            form label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            form select {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }
            
            /* Table - compact with horizontal scroll */
            .overflow-x-auto {
                margin: 0 -0.75rem !important;
                padding: 0 0.75rem !important;
            }
            
            .premium-table {
                font-size: 0.7rem !important;
            }
            
            .premium-table th,
            .premium-table td {
                padding: 0.5rem 0.625rem !important;
            .premium-table td {
                padding: 0.35rem 0 !important;
                border: none !important;
            }
            .premium-table td:before {
                content: attr(data-label);
                display: block;
                font-size: 0.72rem;
                color: #6b7280;
                font-weight: 600;
                margin-bottom: 0.25rem;
            }
            .premium-table td:last-child { text-align: right !important; }
            .premium-table td .premium-btn { margin-left: 0 !important; float: right; }
                white-space: nowrap !important;
            }
            
            .premium-table th {
                font-size: 9px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.025em !important;
            }
            
            /* Table action buttons */
            .premium-table .premium-btn {
                padding: 0.375rem 0.5rem !important;
                font-size: 9px !important;
            }
            
            .premium-table .premium-btn i {
                font-size: 9px !important;
            }
            
            /* Statistics cards - compact */
            .premium-stats {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
            }
            
            .premium-stats h4 {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            .premium-stats p {
                font-size: 1rem !important;
                font-weight: 700 !important;
            }
            
            .premium-stats .premium-icon {
                width: 1.5rem !important;
                height: 1.5rem !important;
                font-size: 0.75rem !important;
                margin-right: 0 !important;
                margin-bottom: 0.25rem !important;
            }
            
            /* Statistics grid - single row on mobile */
            .grid.grid-cols-1.md\:grid-cols-3 {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 0.5rem !important;
            }
            
            /* Chart containers */
            canvas {
                max-height: 180px !important;
            }
            
            /* Spacing adjustments */
            .py-8 {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }
            
            .mb-8 {
                margin-bottom: 0.5rem !important;
            }
            
            .mt-8 {
                margin-top: 0.5rem !important;
            }
            
            .gap-8 {
                gap: 1rem !important;
            }
            
            .gap-4 {
                gap: 0.75rem !important;
            }
            
            .mb-6 {
                margin-bottom: 0.75rem !important;
            }
            
            .p-6 {
                padding: 0.75rem !important;
            }
            
            /* Hide hover effects on mobile */
            .premium-card:hover {
                transform: none !important;
            }
            
            .premium-btn:hover {
                transform: none !important;
            }
        }
        /* Horizontal scroll for the filter + table section only */
        .scrollable-section {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            -ms-overflow-style: -ms-autohiding-scrollbar;
        }
        .scrollable-section::-webkit-scrollbar {
            height: 8px;
        }
        .scrollable-section::-webkit-scrollbar-thumb {
            background: rgba(37,99,235,0.6);
            border-radius: 6px;
        }
        /* Make the filter header and table expand horizontally on small screens so the
           scroll appears only inside this section */
        @media (max-width: 640px) {
            .scrollable-section .premium-table { min-width: 760px; }
            .scrollable-section .filter-row { min-width: 760px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <div class="container mx-auto py-8 px-2 sm:px-6 lg:px-8">
        <div class="premium-header p-8 mb-8 shadow-md flex flex-row items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-primary-800 mb-2">Complaints Report</h1>
                <p class="text-gray-600">Analytics and statistics for all complaints</p>
            </div>
            <div class="flex gap-2">
                <button id="printReport" class="premium-btn bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center"><i class="fas fa-print mr-2"></i>Print</button>
             
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i class="fas fa-chart-pie"></i></span>Complaints by Status</h3>
                <canvas id="statusChart" height="200"></canvas>
            </div>
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i class="fas fa-chart-line"></i></span>Quarterly Case Trends</h3>
                <canvas id="quarterlyLineChart" height="300"></canvas>
            </div>
        </div>
        <div class="premium-card p-6 mb-8">
            <div class="scrollable-section">
                <div class="flex flex-wrap gap-4 mb-6 filter-row">
                    <form method="GET" class="flex flex-wrap gap-4 mb-6">
                        <div>
                            <label for="dateRange" class="block text-sm font-medium text-gray-700">Date Range</label>
                            <select name="dateRange" id="dateRange" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                <option value="all" <?= ($_GET['dateRange'] ?? '') === 'all' ? 'selected' : '' ?>>All Time</option>
                                <option value="this_month" <?= ($_GET['dateRange'] ?? '') === 'this_month' ? 'selected' : '' ?>>This Month</option>
                                <option value="last_month" <?= ($_GET['dateRange'] ?? '') === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                                <option value="this_year" <?= ($_GET['dateRange'] ?? '') === 'this_year' ? 'selected' : '' ?>>This Year</option>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                                <option value="all" <?= ($_GET['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="Pending" <?= ($_GET['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="In Case" <?= ($_GET['status'] ?? '') === 'In Case' ? 'selected' : '' ?>>In Case</option>
                                <option value="Record Purposes" <?= ($_GET['status'] ?? '') === 'Record Purposes' ? 'selected' : '' ?>>Record Purposes</option>
                                <option value="Dismissed" <?= ($_GET['status'] ?? '') === 'Dismissed' ? 'selected' : '' ?>>Dismissed</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="table-wrap">
                    <table class="premium-table w-full mt-4 border-collapse rounded-lg overflow-hidden">
                    <colgroup>
                        <col style="width:12%">
                        <col style="width:28%">
                        <col style="width:25%">
                        <col style="width:14%">
                        <col style="width:10%">
                        <col style="width:15%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Complaint Type</th>
                            <th class="text-left">Complainant</th>
                            <th>Date Filed</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

$where = [];
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $selected = $_GET['status'];
    // Map the user-facing status values to database values (be resilient to DB variants)
    if ($selected === 'In Case') {
        $where[] = "UPPER(Status) = 'IN CASE'";
    } elseif ($selected === 'Record Purposes') {
        // Some rows may still use 'Rejected' -- include both
        $where[] = "UPPER(Status) IN ('REJECTED', 'RECORD PURPOSES')";
    } elseif ($selected === 'Dismissed') {
        $where[] = "UPPER(Status) = 'DISMISSED'";
    } else {
        // For simple statuses like 'Pending' or others, match directly (escaped)
        $status = $conn->real_escape_string($selected);
        $where[] = "Status = '$status'";
    }
}

if (!empty($_GET['dateRange']) && $_GET['dateRange'] !== 'all') {
    $today = date('Y-m-d');
    if ($_GET['dateRange'] === 'this_month') {
        $firstDay = date('Y-m-01');
        $where[] = "Date_Filed BETWEEN '$firstDay' AND '$today'";
    } elseif ($_GET['dateRange'] === 'last_month') {
        $firstDay = date('Y-m-01', strtotime('first day of last month'));
        $lastDay = date('Y-m-t', strtotime('last month'));
        $where[] = "Date_Filed BETWEEN '$firstDay' AND '$lastDay'";
    } elseif ($_GET['dateRange'] === 'this_year') {
        $firstDay = date('Y-01-01');
        $where[] = "Date_Filed BETWEEN '$firstDay' AND '$today'";
    }
}

 $sql = "SELECT ci.Complaint_ID, ci.Resident_ID, ci.Complaint_Title, ci.case_type AS case_type, ci.Date_Filed, ci.Status,
        ri.First_Name AS resident_first, ri.Middle_Name AS resident_middle, ri.Last_Name AS resident_last,
        e.First_Name AS external_first, e.Last_Name AS external_last
    FROM COMPLAINT_INFO ci
    LEFT JOIN RESIDENT_INFO ri ON ci.Resident_ID = ri.Resident_ID
    LEFT JOIN external_complainant e ON ci.External_Complainant_ID = e.External_Complaint_ID";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY Date_Filed DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
    $complaintID = 'C' . date('Y') . '-' . str_pad($row['Complaint_ID'], 3, '0', STR_PAD_LEFT);
    // Use the exact case_type value from complaint_info
    $caseType = trim($row['case_type'] ?? '');
        // Prefer external complainant name when present, otherwise resident full name
        $externalFull = trim(($row['external_first'] ?? '') . ' ' . ($row['external_last'] ?? ''));
        $residentFull = trim(($row['resident_first'] ?? '') . ' ' . ($row['resident_last'] ?? ''));
        if (!empty($externalFull)) {
            $complainant = $externalFull;
        } elseif (!empty($residentFull)) {
            $complainant = $residentFull;
        } else {
            // Fallback to resident id if no names available
            $complainant = !empty($row['Resident_ID']) ? 'Resident #' . $row['Resident_ID'] : 'N/A';
        }
        echo '<tr>';
        echo '<td data-label="Complaint ID">' . $complaintID . '</td>';
        echo '<td data-label="Complaint Type">' . htmlspecialchars($caseType) . '</td>';
        echo '<td class="text-left" data-label="Complainant">' . htmlspecialchars($complainant) . '</td>';
        echo '<td data-label="Date Filed">' . $row['Date_Filed'] . '</td>';
        // Normalize status for display so the dropdown and client-side filter can match
        $rawStatus = $row['Status'];
        $ust = strtoupper(trim($rawStatus));
        // If the DB status contains 'BLOTTER' (e.g. BLOTTER_CASE), display simply as 'Blotter'
        if (stripos($rawStatus, 'BLOTTER') !== false) {
            $displayStatus = 'Blotter';
        } elseif ($ust === 'IN CASE') {
            $displayStatus = 'In Case';
        } elseif ($ust === 'REJECTED') {
            // Map older 'Rejected' label to the new 'Record Purposes'
            $displayStatus = 'Record Purposes';
        } elseif ($ust === 'DISMISSED') {
            $displayStatus = 'Dismissed';
        } else {
            // Preserve other statuses (Pending, Resolved, Scheduled for Hearing, etc.)
            $displayStatus = $rawStatus;
        }

        // Determine color class based on normalized display status
        $statusClass = '';
        switch (strtoupper($displayStatus)) {
            case 'PENDING': $statusClass = 'text-orange-600'; break;
            case 'IN CASE': $statusClass = 'text-blue-600'; break;
            case 'RECORD PURPOSES': $statusClass = 'text-red-600'; break;
            case 'DISMISSED': $statusClass = 'text-red-600'; break;
            case 'RESOLVED': $statusClass = 'text-green-600'; break;
            default: $statusClass = ''; break;
        }

        // Add a special class for BLOTTER_* statuses so we can style that text smaller
        $extraClass = '';
        if (stripos($rawStatus, 'BLOTTER') !== false) {
            $extraClass = ' blotter-status';
        }

        echo '<td class="' . $statusClass . $extraClass . '" data-label="Status">' . htmlspecialchars($displayStatus) . '</td>';
        echo '<td data-label="Action"><a href="view_complaint_details.php?id=' . $row['Complaint_ID'] . '" class="premium-btn bg-primary-500 text-white px-3 py-1 rounded hover:bg-primary-600"><i class="fas fa-eye"></i> View</a></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" class="p-4 text-center text-gray-500">No complaints found.</td></tr>';
}
$conn->close();
?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
            <div class="premium-stats p-6 flex flex-col items-center text-center">
                <span class="premium-icon mb-2"><i class="fas fa-clipboard-list"></i></span>
                <h4 class="text-sm font-semibold text-blue-800">Total Complaints</h4>
                <p class="text-2xl font-bold"><?= $totalComplaints ?></p>
            </div>
            <div class="premium-stats p-6 flex flex-col items-center text-center">
                <span class="premium-icon mb-2"><i class="fas fa-hourglass-half"></i></span>
                <h4 class="text-sm font-semibold text-orange-800">Pending</h4>
                <p class="text-2xl font-bold"><?= $pendingCount ?></p>
            </div>
            <div class="premium-stats p-6 flex flex-col items-center text-center">
                <span class="premium-icon mb-2"><i class="fas fa-check-circle"></i></span>
                <h4 class="text-sm font-semibold text-green-800">Resolved</h4>
                <p class="text-2xl font-bold"><?= $resolvedCount ?></p>
            </div>
            
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Make table rows clickable
            const tableRows = document.querySelectorAll('.premium-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking the View button itself
                    if (e.target.tagName === 'A' || e.target.tagName === 'I' || e.target.closest('a')) {
                        return;
                    }
                    // Find the View link in this row and navigate to it
                    const viewLink = this.querySelector('a[href*="view_complaint_details.php"]');
                    if (viewLink) {
                        window.location.href = viewLink.href;
                    }
                });
            });
            
            // Status Chart using normalized labels (In Case, Pending, Record Purposes, Dismissed)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($statusLabels) ?>,
                    datasets: [{
                        label: 'Complaints by Status',
                        data: [<?= implode(',', $statusCounts) ?>],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',  // In Case
                            'rgba(255, 159, 64, 0.7)',  // Pending
                            'rgba(255, 99, 132, 0.7)',  // Record Purposes
                            'rgba(156, 163, 175, 0.7)'  // Dismissed (gray)
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            // Line Chart: Quarterly Complaint Trends
            const quarterlyLineCtx = document.getElementById('quarterlyLineChart').getContext('2d');
            const quarterlyLineChart = new Chart(quarterlyLineCtx, {
                type: 'line',
                data: {
                    labels: [
                        "Q1 (Jan-Mar)",
                        "Q2 (Apr-Jun)",
                        "Q3 (Jul-Sep)",
                        "Q4 (Oct-Dec)"
                    ],
                    datasets: [{
                        label: 'Complaints per Quarter',
                        data: [<?= implode(',', $quarterCounts) ?>],
                        borderColor: '#0c9ced',
                        backgroundColor: 'rgba(12, 156, 237, 0.1)',
                        tension: 0.4,
                        borderWidth: 2,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#0c9ced'
                    }]
                },
                options: {
                    responsive: true,
                  
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { precision: 0 } }
                    }
                }
            });
            // Print button functionality
            document.getElementById('printReport').addEventListener('click', function() {
                window.print();
            });
            // For a real application, you would implement PDF and Excel export functionality
            // This could use libraries like jsPDF and SheetJS, or make requests to server-side code
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const dateRange = document.getElementById('dateRange');
    const status = document.getElementById('status');
    const tableRows = document.querySelectorAll('tbody tr');

    function filterTable() {
        const dateValue = dateRange.value;
        const statusValue = status.value.toLowerCase();
        const now = new Date();

        tableRows.forEach(row => {
            const dateFiled = new Date(row.cells[3].textContent.trim());
            const complaintStatus = row.cells[4].textContent.trim().toLowerCase();
            let show = true;

            // Filter by status
            if (statusValue !== 'all' && complaintStatus !== statusValue) {
                show = false;
            }

            // Filter by date range
            if (dateValue !== 'all') {
                if (dateValue === 'this_month') {
                    if (!(dateFiled.getMonth() === now.getMonth() && dateFiled.getFullYear() === now.getFullYear())) {
                        show = false;
                    }
                } else if (dateValue === 'last_month') {
                    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    if (!(dateFiled.getMonth() === lastMonth.getMonth() && dateFiled.getFullYear() === lastMonth.getFullYear())) {
                        show = false;
                    }
                } else if (dateValue === 'this_year') {
                    if (dateFiled.getFullYear() !== now.getFullYear()) {
                        show = false;
                    }
                }
                // (Custom range would need datepickers)
            }

            row.style.display = show ? '' : 'none';
        });
    }

    // Auto-trigger filtering when user changes dropdowns
    dateRange.addEventListener('change', filterTable);
    status.addEventListener('change', filterTable);

    // Run filter once on load (to respect "This Month" default)
    filterTable();
});
</script>

    <?php include '../chatbot/bpamis_case_assistant.php'?>
    <?php include 'sidebar_.php';?>
</body>
</html>