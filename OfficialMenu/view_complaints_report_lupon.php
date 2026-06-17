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

// Prepare status chart for complaints: 'IN CASE', 'Pending', 'Rejected'
$statusLabels = ['IN CASE', 'Pending', 'Rejected'];
$statusCounts = [0, 0, 0];
$statusQuery = "SELECT Status, COUNT(*) as count FROM complaint_info WHERE Status IN ('IN CASE', 'Pending', 'Rejected') GROUP BY Status";
$statusResult = $conn->query($statusQuery);
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        if ($row['Status'] === 'IN CASE') $statusCounts[0] = (int)$row['count'];
        if ($row['Status'] === 'Pending') $statusCounts[1] = (int)$row['count'];
        if ($row['Status'] === 'Rejected') $statusCounts[2] = (int)$row['count'];
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
        .premium-table th { background: #e0effe; color: #065a8f; font-weight: 600; }
        .premium-table td, .premium-table th { padding: 0.75rem 1rem; }
        .premium-table tr { transition: background 0.2s; }
        .premium-table tr:hover { background: #f0f7ff; }
        .premium-stats { background: linear-gradient(135deg, #f0f7ff 0%, #e0effe 100%); border-radius: 1.5rem; }
        .premium-btn { transition: all 0.2s; box-shadow: 0 2px 8px rgba(12,156,237,0.08); }
        .premium-btn:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 6px 18px rgba(12,156,237,0.13); }
        .premium-icon { background: #dbeafe; color: #2563eb; border-radius: 9999px; width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-right: 0.75rem; }
        @media (max-width: 640px) { .premium-card, .premium-stats { border-radius: 1rem; } }
    </style>
</head>
<body>
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>
    <div class="container mx-auto py-8 px-2 sm:px-6 lg:px-8">
        <div class="premium-header p-8 mb-8 shadow-md flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-primary-800 mb-2">Complaints Report</h1>
                <p class="text-gray-600">Analytics and statistics for all complaints</p>
            </div>
            <div class="flex gap-2 mt-4 md:mt-0">
                <button id="printReport" class="premium-btn bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center"><i class="fas fa-print mr-2"></i>Print</button>
                <button id="exportPDF" class="premium-btn bg-red-600 text-white px-4 py-2 rounded-lg flex items-center"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                <button id="exportExcel" class="premium-btn bg-green-600 text-white px-4 py-2 rounded-lg flex items-center"><i class="fas fa-file-excel mr-2"></i>Excel</button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i class="fas fa-chart-pie"></i></span>Cases by Status</h3>
                <canvas id="statusChart" height="200"></canvas>
            </div>
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i class="fas fa-chart-line"></i></span>Quarterly Case Trends</h3>
                <canvas id="quarterlyLineChart" height="300"></canvas>
            </div>
        </div>
        <div class="premium-card p-6 mb-8">
            <div class="flex flex-wrap gap-4 mb-6">
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
            <option value="all" <?= ($_GET['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="Pending" <?= ($_GET['status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Under Investigation" <?= ($_GET['status'] ?? '') === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
            <option value="Scheduled for Hearing" <?= ($_GET['status'] ?? '') === 'Scheduled for Hearing' ? 'selected' : '' ?>>Scheduled for Hearing</option>
            <option value="Resolved" <?= ($_GET['status'] ?? '') === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="Dismissed" <?= ($_GET['status'] ?? '') === 'Dismissed' ? 'selected' : '' ?>>Dismissed</option>
        </select>
    </div>

</form>
            </div>
            <div class="overflow-x-auto">
                <table class="premium-table w-full mt-4 border-collapse rounded-lg overflow-hidden">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Title</th>
                            <th>Complainant</th>
                            <th>Date Filed</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

$where = [];
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $status = $conn->real_escape_string($_GET['status']);
    $where[] = "Status = '$status'";
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

$sql = "SELECT Complaint_ID, Resident_ID, Complaint_Title, Date_Filed, Status 
        FROM COMPLAINT_INFO";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY Date_Filed DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $complaintID = 'C' . date('Y') . '-' . str_pad($row['Complaint_ID'], 3, '0', STR_PAD_LEFT);
        $title = substr($row['Complaint_Title'], 0, 30);
        $complainant = 'Resident #' . $row['Resident_ID'];
        echo '<tr>';
        echo '<td>' . $complaintID . '</td>';
        echo '<td>' . htmlspecialchars($title) . '</td>';
        echo '<td>' . $complainant . '</td>';
        echo '<td>' . $row['Date_Filed'] . '</td>';
        // Normalize and map statuses; short-display for BLOTTER_* entries
        $rawStatus = $row['Status'];
        $displayStatus = $rawStatus;
        if (stripos($rawStatus, 'BLOTTER') !== false) {
            $displayStatus = 'Blotter';
        }

        $statusClass = '';
        switch (strtoupper($displayStatus)) {
            case 'PENDING': $statusClass = 'text-orange-600'; break;
            case 'IN CASE': $statusClass = 'text-blue-600'; break;
            case 'UNDER INVESTIGATION': $statusClass = 'text-blue-600'; break;
            case 'SCHEDULED FOR HEARING': $statusClass = 'text-purple-600'; break;
            case 'RESOLVED': $statusClass = 'text-green-600'; break;
            case 'DISMISSED': $statusClass = 'text-red-600'; break;
            default: $statusClass = ''; break;
        }

        $extraClass = '';
        if (stripos($rawStatus, 'BLOTTER') !== false) {
            $extraClass = ' blotter-status';
        }

        echo '<td class="' . $statusClass . $extraClass . '">' . htmlspecialchars($displayStatus) . '</td>';
        echo '<td><a href="view_complaint_details.php?id=' . $row['Complaint_ID'] . '" class="premium-btn bg-primary-500 text-white px-3 py-1 rounded hover:bg-primary-600"><i class="fas fa-eye"></i> View</a></td>';
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
            // Status Chart: IN CASE, Pending, Rejected
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: ["IN CASE", "Pending", "Rejected"],
                    datasets: [{
                        label: 'Complaints by Status',
                        data: [<?= implode(',', $statusCounts) ?>],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',  // IN CASE
                            'rgba(255, 159, 64, 0.7)',  // Pending
                            'rgba(255, 99, 132, 0.7)'   // Rejected
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
