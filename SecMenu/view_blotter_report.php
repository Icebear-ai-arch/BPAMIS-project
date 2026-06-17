<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

// Summary statistics
$totalBlotters = 0;
$statusCounts = [];
$monthlyTrends = [];

// Fetch all blotter info
$sql = "SELECT Date_Reported FROM BLOTTER_INFO";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $totalBlotters = $result->num_rows;
    while ($row = $result->fetch_assoc()) {
        
        $month = date('M', strtotime($row['Date_Reported']));
        
        // Count monthly
        if (!isset($monthlyTrends[$month])) $monthlyTrends[$month] = 0;
        $monthlyTrends[$month]++;
    }
}

// Prepare status bar chart (show Open, Ongoing, Resolved, Closed)
$barStatuses = ['Open', 'Ongoing', 'Resolved', 'Closed'];
$barCounts = [];
foreach ($barStatuses as $s) {
    $barCounts[] = $statusCounts[$s] ?? 0;
}

// Prepare monthly line chart
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthCounts = [];
foreach ($monthNames as $m) {
    $monthCounts[] = $monthlyTrends[$m] ?? 0;
}

// Prepare quarterly line chart
$quarters = ['Q1','Q2','Q3','Q4'];
$quarterCounts = [0,0,0,0];
$sql = "SELECT Date_Reported FROM BLOTTER_INFO";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $month = (int)date('n', strtotime($row['Date_Reported']));
        if ($month >= 1 && $month <= 3) $quarterCounts[0]++;
        elseif ($month >= 4 && $month <= 6) $quarterCounts[1]++;
        elseif ($month >= 7 && $month <= 9) $quarterCounts[2]++;
        elseif ($month >= 10 && $month <= 12) $quarterCounts[3]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Blotter Reports</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <?php include 'sidebar_.php';?>
    <style>
    /* Ensure navbar is not transparent */
    nav, .navbar, .bpamis-navbar, .bpamis-nav {
        background: #fff !important;
        background-color: #fff !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        opacity: 1 !important;
    }
    </style>
    <div class="container font-sans mx-auto py-8 px-2 sm:px-6 lg:px-8">
        <div class="premium-header p-8 mb-8 shadow-md flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-primary-800 mb-2">Blotter Reports</h1>
                <p class="text-gray-600">Analytics and statistics for all blotter cases</p>
            </div>
            <div class="flex gap-2 mt-4 md:mt-0">
                <button id="printReport" class="premium-btn bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center"><i class="fas fa-print mr-2"></i>Print</button>
                
            </div>
        </div>
        <!-- Chart.js 2-column section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            <div class="premium-card p-6 md:col-span-2">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i class="fas fa-chart-line"></i></span>Quarterly Blotter Trends</h3>
                <canvas id="quarterlyLineChart" height="120"></canvas>
            </div>
        </div>
        <div class="premium-card p-6 mb-8">
            <div class="overflow-x-auto">
                <table class="premium-table w-full mt-4 border-collapse rounded-lg overflow-hidden">
                    <thead>
                        <tr>
                            <th>Blotter Case #</th>
                            <th>Description</th>
                            <th>Reported By</th>
                            <th>Date Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM BLOTTER_INFO ORDER BY Date_Reported DESC";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            $i = 1;
                            while ($row = $result->fetch_assoc()) {
                                // Determine base ID: prefer actual Blotter_ID if present, otherwise use sequential index
                                $baseId = isset($row['Blotter_ID']) && $row['Blotter_ID'] !== '' ? $row['Blotter_ID'] : $i;

                                // Try to extract month and two-digit year from Date_Reported
                                $formattedRef = htmlspecialchars($baseId);
                                if (!empty($row['Date_Reported'])) {
                                    $ts = strtotime($row['Date_Reported']);
                                    if ($ts !== false) {
                                        $month = date('n', $ts); // month without leading zeros
                                        $yy = date('y', $ts);    // two-digit year
                                        $formattedRef = htmlspecialchars($baseId . '-' . $month . '-' . $yy);
                                    }
                                }

                                echo '<tr>';
                                echo '<td>' . $formattedRef . '</td>';
                                echo '<td>' . htmlspecialchars($row['Blotter_Description']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['Reported_By']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['Date_Reported']) . '</td>';
                                echo '</tr>';
                                $i++;
                            }
                        } else {
                            echo '<tr><td colspan="4" class="p-4 text-center text-gray-500">No blotter reports found.</td></tr>';
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Line Chart: Quarterly Blotter Trends
        const quarterlyLineCtx = document.getElementById('quarterlyLineChart').getContext('2d');
        const quarterlyLineChart = new Chart(quarterlyLineCtx, {
            type: 'line',
            data: {
                labels: ["Q1", "Q2", "Q3", "Q4"],
                datasets: [{
                    label: 'Blotters per Quarter',
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

        document.getElementById('printReport').addEventListener('click', function() { window.print(); });
        document.getElementById('exportPDF').addEventListener('click', function() { alert('Exporting to PDF...'); });
        document.getElementById('exportExcel').addEventListener('click', function() { alert('Exporting to Excel...'); });
    });
    </script>
</body>
</html>             