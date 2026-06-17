
<?php
include '../controllers/session_control.php';
include '../server/server.php';

// CASES BY STATUS
$statusQuery = "SELECT Case_Status, COUNT(*) as count FROM case_info GROUP BY Case_Status";
$statusResult = $conn->query($statusQuery);
$statusData = [];
$statusLabels = [];

while ($row = $statusResult->fetch_assoc()) {
    $statusLabels[] = $row['Case_Status'];
    $statusData[] = (int) $row['count'];
}

// MONTHLY TRENDS (number of cases filed per month)
$monthQuery = "
    SELECT 
        MONTH(ci.Date_Filed) as month,
        COUNT(*) as count 
    FROM case_info c
    JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID
    GROUP BY MONTH(ci.Date_Filed)
    ORDER BY MONTH(ci.Date_Filed)
";
$monthResult = $conn->query($monthQuery);
$monthLabels = [];
$monthCounts = [];

// Quarterly aggregation
$quarterLabels = ['Q1 (Jan-Mar)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Oct-Dec)'];
$quarterCounts = [0, 0, 0, 0];

$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

while ($row = $monthResult->fetch_assoc()) {
    $monthIndex = (int) $row['month'] - 1;
    $monthLabels[] = $monthNames[$monthIndex];
    $monthCounts[] = (int) $row['count'];
    // Accumulate into quarters
    $mn = (int)$row['month'];
    if ($mn >= 1 && $mn <= 3) {
        $quarterCounts[0] += (int)$row['count'];
    } elseif ($mn >= 4 && $mn <= 6) {
        $quarterCounts[1] += (int)$row['count'];
    } elseif ($mn >= 7 && $mn <= 9) {
        $quarterCounts[2] += (int)$row['count'];
    } elseif ($mn >= 10 && $mn <= 12) {
        $quarterCounts[3] += (int)$row['count'];
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = "View Case Reports";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Case Reports</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae2fd',
                            300: '#7cccfd',
                            400: '#36b3f9',
                            500: '#0c9ced',
                            600: '#0281d4',
                            700: '#026aad',
                            800: '#065a8f',
                            900: '#0a4b76'
                        }
                    },
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e0effe 100%);
        }

        .premium-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.18);
            transition: box-shadow 0.3s, transform 0.3s;
        }

        .premium-card:hover {
            box-shadow: 0 16px 40px 0 rgba(31, 38, 135, 0.18);
            transform: translateY(-4px) scale(1.01);
        }

        .premium-gradient {
            background: linear-gradient(135deg, #bae2fd 0%, #7cccfd 100%);
        }

        .premium-header {
            background: linear-gradient(90deg, #e0effe 0%, #bae2fd 100%);
            border-radius: 1.5rem;
        }

        .premium-table th {
            background: #e0effe;
            color: #065a8f;
            font-weight: 600;
        }

        .premium-table td,
        .premium-table th {
            padding: 0.75rem 1rem;
        }

        .premium-table tbody tr {
            transition: background 0.2s;
            cursor: pointer;
        }

        .premium-table tbody tr:hover {
            background: #f0f7ff;
        }

        .premium-stats {
            background: linear-gradient(135deg, #f0f7ff 0%, #e0effe 100%);
            border-radius: 1.5rem;
        }

        .premium-btn {
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(12, 156, 237, 0.08);
        }

        .premium-btn:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 18px rgba(12, 156, 237, 0.13);
        }

        .premium-icon {
            background: #dbeafe;
            color: #2563eb;
            border-radius: 9999px;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 0.75rem;
        }
        
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
                flex-direction: row !important;
                align-items: flex-start !important;
            }
            
            .premium-header > div:first-child {
                flex: 1 !important;
            }
            
            .premium-header .flex.gap-2 {
                flex-shrink: 0 !important;
            }
            
            .premium-header h1 {
                font-size: 1.125rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .premium-header p {
                font-size: 0.7rem !important;
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
            
            .premium-card h3, .premium-card h2 {
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
                gap: 0.75rem !important;
            }
            
            /* Filter form - compact */
            form .grid {
                gap: 0.75rem !important;
            }
            
            form label, .premium-card label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            form select, .premium-card select {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }
            
            /* Table - compact with horizontal scroll */
            .overflow-x-auto {
                margin: 0 -0.75rem !important;
                padding: 0 0.75rem !important;
            }
            
            /* Case Details container - add horizontal margins */
            .premium-card.overflow-x-auto {
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
            }
            
            .premium-table {
                font-size: 0.7rem !important;
            }
            
            /* Case Description column - minimal width */
            .premium-table th:nth-child(2),
            .premium-table td:nth-child(2) {
                max-width: 80px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            
            .premium-table th,
            .premium-table td {
                padding: 0.5rem 0.625rem !important;
                white-space: nowrap !important;
            }
            
            .premium-table th {
                font-size: 9px !important;
                font-weight: 600 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.025em !important;
            }
            
            /* Table action buttons */
            .premium-table .premium-btn,
            .premium-table .card-hover,
            .premium-table a {
                padding: 0.375rem 0.5rem !important;
                font-size: 9px !important;
            }
            
            .premium-table .premium-btn i,
            .premium-table .card-hover i,
            .premium-table a i {
                font-size: 9px !important;
            }
            
            /* Status badges in table */
            .premium-table span.px-2 {
                padding: 0.25rem 0.5rem !important;
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
                margin-right: 0.5rem !important;
            }
            
            /* Statistics grid - single row on mobile for 4 columns */
            .grid.grid-cols-1.md\:grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
            }
            
            /* Chart containers */
            canvas {
                max-height: 180px !important;
            }
            
            .chart-box {
                height: 180px !important;
                margin-top: 0.5rem !important;
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
                gap: 0.75rem !important;
            }
            
            .gap-6 {
                gap: 0.75rem !important;
            }
            
            .gap-4 {
                gap: 0.5rem !important;
            }
            
            .mb-6 {
                margin-bottom: 0.75rem !important;
            }
            
            .mb-4 {
                margin-bottom: 0.5rem !important;
            }
            
            .p-6 {
                padding: 0.75rem !important;
            }
            
            .p-3 {
                padding: 0.5rem !important;
            }
            
            /* Hide hover effects on mobile */
            .premium-card:hover {
                transform: none !important;
            }
            
            .premium-btn:hover {
                transform: none !important;
            }
            
            .card-hover:hover {
                transform: none !important;
            }
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Chatbot Button Styles */
        .chatbot-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            box-shadow: 0 4px 15px rgba(2, 129, 212, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            outline: none;
        }

        .chatbot-button:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 6px 20px rgba(2, 129, 212, 0.35);
        }

        .chatbot-button i {
            font-size: 24px;
            color: white;
            transition: transform 0.3s ease;
        }

        .chatbot-button:hover i {
            transform: rotate(10deg);
        }

        .pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: rgba(2, 129, 212, 0.7);
            opacity: 0;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                opacity: 0.7;
            }

            70% {
                transform: scale(1.1);
                opacity: 0;
            }

            100% {
                transform: scale(0.95);
                opacity: 0;
            }
        }

        .chatbot-container {
            position: fixed;
            bottom: 5.5rem;
            right: 2rem;
            width: 350px;
            max-height: 500px;
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            z-index: 999;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .chatbot-container.active {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: all;
        }

        .chatbot-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #0281d4, #0c9ced);
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chatbot-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1rem;
        }

        .chatbot-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .chatbot-close:hover {
            transform: rotate(90deg);
        }

        .chatbot-body {
            height: 340px;
            overflow-y: auto;
            padding: 20px;
        }

        .chatbot-footer {
            padding: 12px 15px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }

        .chatbot-input {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .chatbot-input:focus {
            border-color: #0c9ced;
            box-shadow: 0 0 0 2px rgba(12, 156, 237, 0.1);
        }

        .send-button {
            background: #0c9ced;
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .send-button:hover {
            background: #0281d4;
        }

        .chat-message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .user-message {
            justify-content: flex-end;
        }

        .bot-message {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            position: relative;
        }

        .user-message .message-content {
            background-color: #0c9ced;
            color: white;
            border-bottom-right-radius: 4px;
            margin-right: 10px;
        }

        .bot-message .message-content {
            background-color: #f0f7ff;
            color: #333;
            border-bottom-left-radius: 4px;
            margin-left: 10px;
        }

        .bot-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0effe;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bot-avatar i {
            color: #0281d4;
            font-size: 16px;
        }

        .message-time {
            font-size: 10px;
            color: #888;
            margin-top: 4px;
            text-align: right;
        }

        /* Mobile responsiveness for chatbot */
        @media (max-width: 640px) {
            .chatbot-container {
                width: calc(100% - 32px);
                right: 16px;
                left: 16px;
                bottom: 5rem;
            }

            .chatbot-button {
                bottom: 1.5rem;
                right: 1.5rem;
            }
        }

        /* Internal scroll wrapper: confines horizontal scrolling to this element only */
        .scrollable-section {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* WebKit scrollbar styling (Chromium/Safari) */
        .scrollable-section::-webkit-scrollbar {
            height: 8px;
        }

        .scrollable-section::-webkit-scrollbar-thumb {
            background: rgba(12, 156, 237, 0.22);
            border-radius: 8px;
        }

        /* Status badge helper for consistent spacing */
        .status-badge {
            padding: 0.25rem 0.6rem;
            margin-right: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Ensure Status column has room on narrow screens to avoid overlap */
        .scrollable-section table th:nth-child(5),
        .scrollable-section table td.status-cell {
            min-width: 180px;
            max-width: 260px;
            white-space: nowrap;
        }

        /* Extra right padding for status cell to keep badge away from next column */
        .scrollable-section table td.status-cell {
            padding-right: 1.25rem;
        }

        /* On small screens, ensure the table is slightly wider than the viewport so the inner scrollbar appears */
        @media (max-width: 640px) {
            .scrollable-section table {
                min-width: 760px;
            }
        }
    </style>
</head>

<body class="font-sans">
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>

    <!-- Page Header (premium) -->
    <div class="container mx-auto py-8 px-2 sm:px-6 lg:px-8">
        <div class="premium-header p-8 mb-8 shadow-md flex flex-row items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-primary-800 mb-2">Case Reports</h1>
                <p class="text-gray-600">Generate and view analytics on case resolution and statistics</p>
            </div>
            <div class="flex gap-2">
                <button id="printReport"
                    class="premium-btn bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center"><i
                        class="fas fa-print mr-2"></i>Print</button>
            </div>
        </div>

        


            <script>
                document.getElementById("printReport").addEventListener("click", function () {
                    window.print();
                });

                document.getElementById("exportExcel").addEventListener("click", function () {
                    const table = document.querySelector("table");
                    let csv = [];

                    for (let row of table.rows) {
                        let cols = [];
                        for (let cell of row.cells) {
                            cols.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                        }
                        csv.push(cols.join(","));
                    }

                    let csvContent = csv.join("\n");
                    let blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
                    let link = document.createElement("a");
                    link.href = URL.createObjectURL(blob);
                    link.download = "case-report.csv";
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            </script>

        
        <!-- Charts Section -->
        <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i
                            class="fas fa-chart-pie"></i></span>Cases by Status</h3>
                <div class="chart-box" style="height: 600px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="premium-card p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center"><span class="premium-icon"><i
                            class="fas fa-chart-line"></i></span>Quarterly Case Trends</h3>
                <div class="chart-box" style="height: 600px; margin-top: 2rem;">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Table -->

        <div class="premium-card p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                <i class="fas fa-table text-primary-500 mr-2"></i>
                Case Details
            </h2>

            <!-- Filter Options -->
            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="dateRange" class="block text-sm font-medium text-gray-600 mb-1.5">Date Range</label>
                    <select id="dateRange"
                        class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-300 transition">
                        <option value="all">All Time</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                        <option value="q1">Q1 (Jan-April)</option>
                        <option value="q2">Q2 (May-August)</option>
                        <option value="q3">Q3 (Sep-Dec)</option>
                        <option value="yearly">Yearly</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div>
                    <label for="caseStatus" class="block text-sm font-medium text-gray-600 mb-1.5">Case Status</label>
                    <select id="caseStatus"
                        class="w-full p-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-300 transition">
                        <option value="all">All Statuses</option>
                        <option value="mediation">Mediation</option>
                        <option value="conciliation">Conciliation</option>
                        <option value="arbitration">Arbitration</option>
                        <option value="mediation resolved">Mediation Resolved</option>
                        <option value="conciliation resolved">Conciliation Resolved</option>
                        <option value="arbitration resolved">Arbitration Resolved</option>
                        <option value="certificate to file action">Certificate to File Action</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>
                <div class="flex flex-col justify-end">
                    <div class="flex gap-2">

                        <form action="export_pdf.php" method="post" target="_blank" id="pdfForm" class="flex items-center gap-2">
                            <input type="hidden" name="range" id="exportRange">
                            <input type="hidden" name="status" id="exportStatus">
                            <input type="hidden" name="download" id="exportDownload">
                            <button type="button" id="viewReportBtn" class="premium-btn bg-primary-600 text-white px-4 py-2 rounded-lg inline-flex items-center"><i class="fas fa-eye mr-2"></i>View Case Report</button>
                            <button type="button" id="downloadReportBtn" class="premium-btn bg-primary-500 text-white px-4 py-2 rounded-lg inline-flex items-center"><i class="fas fa-download mr-2"></i>Download PDF</button>

                        </form>

                    </div>
                </div>
            </div>

            <div class="scrollable-section">
            <table class="premium-table table-fixed w-full mt-4 border-collapse rounded-lg overflow-hidden">
                <colgroup>
                    <col style="width: 15%">
                    <col style="width: 18%">
                    <col style="width: 22%">
                    <col style="width: 15%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-left">Case ID</th>
                        <th class="text-left">Case Type</th>
                        <th class="text-left">Complainant</th>
                        <th class="text-left">Date Filed</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="caseTableBody">

                    <?php
                    include '../server/server.php';

                    $query = "SELECT 
    c.Case_ID,
    c.case_original_id,
    ci.case_type,
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
ORDER BY c.Date_Opened DESC";


                    $result = $conn->query($query);

                    if ($result->num_rows > 0) {
                        while ($case = $result->fetch_assoc()) {
                            echo '<tr class="border-b border-gray-100 hover:bg-gray-50 transition">';
                            $displayId = trim($case['case_original_id'] ?? '') !== '' ? $case['case_original_id'] : $case['Case_ID'];
                            echo '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($displayId) . '</td>';
                            $rawType = trim($case['case_type'] ?? '');
                            $typeDisplay = $rawType !== '' ? ucwords($rawType) : 'Unspecified Case Type';
                            echo '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($typeDisplay) . '</td>';
                            $complainantName = !empty($case['resident_fname'])
                                ? $case['resident_fname'] . ' ' . $case['resident_lname']
                                : $case['external_fname'] . ' ' . $case['external_lname'];

                            echo '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($complainantName) . '</td>';

                            echo '<td class="p-3 text-sm text-gray-700">' . htmlspecialchars($case['Date_Filed']) . '</td>';

                            $statusClass = '';
                            $statusIcon = '';
                            switch ($case['Case_Status']) {
                                case 'Arbitration':
                                    $statusClass = 'text-blue-700 bg-blue-50 border-blue-200';
                                    $statusIcon = '<i class="fas fa-folder-open mr-1"></i>';
                                    break;
                                case 'Mediation':
                                    $statusClass = 'text-purple-700 bg-purple-50 border-purple-200';
                                    $statusIcon = '<i class="fas fa-handshake mr-1"></i>';
                                    break;
                                case 'Mediation Resolved':
                                    $statusClass = 'text-green-700 bg-green-50 border-green-200';
                                    $statusIcon = '<i class="fas fa-check-circle mr-1"></i>';
                                    break;
                                case 'Conciliation':
                                    $statusClass = 'text-yellow-700 bg-yellow-50 border-yellow-200';
                                    $statusIcon = '<i class="fas fa-folder-open mr-1"></i>';
                                    break;
                                case 'Conciliation Resolved':
                                    $statusClass = 'text-green-700 bg-green-50 border-green-200';
                                    $statusIcon = '<i class="fas fa-handshake mr-1"></i>';
                                    break;
                                case 'Arbitration Resolved':
                                    $statusClass = 'text-green-700 bg-green-50 border-green-200';
                                    $statusIcon = '<i class="fas fa-check-circle mr-1"></i>';
                                    break;
                                case 'Dismissed':
                                    $statusClass = 'text-red-700 bg-red-50 border-red-200';
                                    $statusIcon = '<i class="fas fa-folder mr-1"></i>';
                                    break;
                                default:
                                    $statusClass = 'text-gray-700 bg-gray-50 border-gray-200';
                                    $statusIcon = '<i class="fas fa-info-circle mr-1"></i>';
                            }

                            echo '<td class="p-3 text-sm status-cell">
                <span class="status-badge rounded-full text-xs border ' . $statusClass . '">
                    ' . $statusIcon . htmlspecialchars($case['Case_Status']) . '
                                </span>
                                </td>';

                                                                                                                                                                                                                                echo '<td class="p-3 pl-6 text-center">
                                <a href="view_case_details.php?id=' . urlencode($case['Case_ID']) . '" class="card-hover bg-primary-500 text-white px-3 py-1.5 rounded-lg hover:bg-primary-600 transition inline-flex items-center text-sm">
                                        <i class="fas fa-file-alt mr-1.5"></i> View Report
                                </a>
                            </td>';
                                                        echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" class="p-4 text-center text-gray-500">No cases found.</td></tr>';
                    }


                    ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
        include '../server/server.php';
        // Initialize all stats
        $complaintsCount = $resolvedCount = $pendingCount = $rejectedCount = 0;
        $casesCount = $mediatedCount = $resolutionCount = $settlementCount = $closedCount = $resolvedCaseCount = 0;
        $scheduledHearings = 0;

        // Summary Stats from case_info joined with complaint_info for date_filed
        $totalCases = 0;
        $activeCases = 0;
        $resolvedClosedCases = 0;
        $avgResolutionDays = 0;

        $summaryQuery = $conn->query("SELECT c.case_status, ci.date_filed FROM case_info c JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID");
        $totalDays = 0;
        $resolvedCountForAvg = 0;

        if ($summaryQuery) {
            while ($row = $summaryQuery->fetch_assoc()) {
                $status = strtolower(trim($row['case_status']));
                $totalCases++;

                // Align summary buckets with the Case Status select options
                // Active (ongoing stages): Mediation, Conciliation, Arbitration
                // Resolved/Closed (terminal outcomes): Mediation Resolved, Conciliation Resolved, Arbitration Resolved, Certificate to File Action, Dismissed, Closed/Resolved
                $activeStatuses = ['mediation', 'conciliation', 'arbitration'];
                $resolvedStatuses = [
                    'mediation resolved',
                    'conciliation resolved',
                    'arbitration resolved',
                    'certificate to file action',
                    'dismissed',
                    'closed',
                    'resolved'
                ];

                if (in_array($status, $activeStatuses, true)) {
                    $activeCases++;
                } elseif (in_array($status, $resolvedStatuses, true)) {
                    $resolvedClosedCases++;
                }

                if (!empty($row['date_filed'])) {
                    $start = new DateTime($row['date_filed']);
                    $end = new DateTime();
                    $interval = $start->diff($end);
                    $totalDays += $interval->days;
                    $resolvedCountForAvg++;
                }
            }

            if ($resolvedCountForAvg > 0) {
                $avgResolutionDays = round($totalDays / $resolvedCountForAvg, 1);
            }
        }

        // Hearings Count
        $hearingQuery = "SELECT COUNT(*) as count FROM schedule_list";
        $result = $conn->query($hearingQuery);
        if ($result && $row = $result->fetch_assoc()) {
            $scheduledHearings = (int) $row['count'];
        }

        ?>

        <!-- Summary Statistics -->
        <div class="mt-8">
           
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="premium-stats p-6 flex items-center">
                    <div class="premium-icon mr-3"><i class="fas fa-clipboard-list"></i></div>
                    <div>
                        <h4 class="text-xs text-blue-800 uppercase tracking-wider">Total Cases</h4>
                        <p class="text-2xl font-semibold text-gray-800" id="totalCases"><?php echo $totalCases; ?></p>
                    </div>
                </div>
                <div class="premium-stats p-6 flex items-center">
                    <div class="premium-icon mr-3"><i class="fas fa-folder-open"></i></div>
                    <div>
                        <h4 class="text-xs text-amber-800 uppercase tracking-wider">Active Cases</h4>
                        <p class="text-2xl font-semibold text-gray-800" id="activeCases"><?php echo $activeCases; ?></p>
                    </div>
                </div>
                <div class="premium-stats p-6 flex items-center">
                    <div class="premium-icon mr-3"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h4 class="text-xs text-green-800 uppercase tracking-wider">Resolved/Closed</h4>
                        <p class="text-2xl font-semibold text-gray-800" id="closedCases">
                            <?php echo $resolvedClosedCases; ?></p>
                    </div>
                </div>
                <div class="premium-stats p-6 flex items-center">
                    <div class="premium-icon mr-3"><i class="fas fa-clock"></i></div>
                    <div>
                        <h4 class="text-xs text-purple-800 uppercase tracking-wider">Avg Resolution Time</h4>
                        <p class="text-2xl font-semibold text-gray-800" id="avgResolution">
                            <?php echo $avgResolutionDays; ?> days</p>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('pdfForm').addEventListener('submit', function (e) {
                // Get the current values from your dropdowns
                const range = document.getElementById('dateRange').value;
                const status = document.getElementById('caseStatus').value;

                // Set them into the hidden inputs
                document.getElementById('exportRange').value = range;
                document.getElementById('exportStatus').value = status;
                // Do not force download on generic submit
                const dl = document.getElementById('exportDownload');
                if (dl) dl.value = '';
            });

            // Bind report buttons to submit with current filters
            (function(){
                const viewBtn = document.getElementById('viewReportBtn');
                const dlBtn = document.getElementById('downloadReportBtn');
                const form = document.getElementById('pdfForm');
                function setFilters(download){
                    const range = document.getElementById('dateRange').value;
                    const status = document.getElementById('caseStatus').value;
                    document.getElementById('exportRange').value = range;
                    document.getElementById('exportStatus').value = status;
                    const dl = document.getElementById('exportDownload');
                    if (dl) dl.value = download ? '1' : '';
                }
                if (viewBtn && form){ viewBtn.addEventListener('click', function(){ setFilters(false); form.submit(); }); }
                if (dlBtn && form){ dlBtn.addEventListener('click', function(){ setFilters(true); form.submit(); }); }
            })();
            let statusChart; // outer-scoped for updates
            let trendsChart;

            function applyStatusFilter(){
                const select = document.getElementById('caseStatus');
                if(!select) return;
                const selectedText = (select.options[select.selectedIndex]?.textContent || '').trim().toLowerCase();
                const rows = document.querySelectorAll('#caseTableBody tr');
                rows.forEach(tr => {
                    const statusCell = tr.querySelector('td:nth-child(5)'); // Status column
                    if(!statusCell){ tr.style.display=''; return; }
                    const statusText = statusCell.textContent.trim().toLowerCase();
                    let show = true;
                    if(selectedText && selectedText !== 'all statuses'){
                        // Synonym mapping: Conciliation ≈ Resolution
                        if(selectedText === 'conciliation'){
                            show = /conciliation|resolution/.test(statusText);
                        } else {
                            show = statusText.includes(selectedText);
                        }
                    }
                    // Store interim visibility in dataset; date range filter will finalize
                    tr.dataset.statusShow = show ? '1' : '0';
                });
            }

            function applyDateRangeFilter(){
                const range = document.getElementById('dateRange')?.value || 'all';
                const now = new Date();
                const thisMonth = now.getMonth() + 1; // 1-12
                const thisYear = now.getFullYear();
                const lastMonthDate = new Date(thisYear, thisMonth - 2, 1); // previous month
                const rows = document.querySelectorAll('#caseTableBody tr');

                function inQuarter(m, q){
                    if(q==='q1') return m>=1 && m<=4;
                    if(q==='q2') return m>=5 && m<=8;
                    if(q==='q3') return m>=9 && m<=12;
                    return true;
                }

                rows.forEach(tr => {
                    // parse Date Filed from 4th column
                    const dateCell = tr.querySelector('td:nth-child(4)');
                    let showByDate = true;
                    if(dateCell){
                        const txt = dateCell.textContent.trim();
                        const d = new Date(txt);
                        if(!isNaN(d)){ 
                            const m = d.getMonth() + 1;
                            const y = d.getFullYear();
                            switch(range){
                                case 'this_month':
                                    showByDate = (m === thisMonth && y === thisYear);
                                    break;
                                case 'last_month':
                                    showByDate = (m === (lastMonthDate.getMonth()+1) && y === lastMonthDate.getFullYear());
                                    break;
                                case 'this_year':
                                    showByDate = (y === thisYear);
                                    break;
                                case 'yearly':
                                    showByDate = (y === thisYear);
                                    break;
                                case 'q1':
                                case 'q2':
                                case 'q3':
                                    showByDate = (y === thisYear && inQuarter(m, range));
                                    break;
                                case 'all':
                                default:
                                    showByDate = true;
                            }
                        }
                    }
                    const statusShow = tr.dataset.statusShow !== '0';
                    tr.style.display = (statusShow && showByDate) ? '' : 'none';
                });
            }

            function applyAllFilters(){
                applyStatusFilter();
                applyDateRangeFilter();
            }

function fetchFilteredData() {
    // Prefer client-side filtering to avoid resetting the dropdowns
    applyAllFilters();

    // Optionally refresh server-backed charts/table if a handler exists
    try {
        if (typeof updateTableAndCharts === 'function') {
            const status = document.getElementById('caseStatus').value;
            const range = document.getElementById('dateRange').value;
            fetch('fetch_filtered_cases.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `status=${encodeURIComponent(status)}&range=${encodeURIComponent(range)}`
            })
            .then(res => res.json())
            .then(data => {
                try { updateTableAndCharts(data); } catch(e) { /* no-op if not defined */ }
            })
            .catch(() => { /* silent fallback to client-side only */ });
        }
    } catch(e) { /* ignore */ }
}


            document.getElementById('caseStatus').addEventListener('change', fetchFilteredData);
            // Also locally filter existing rows without waiting for server
            document.getElementById('caseStatus').addEventListener('change', applyAllFilters);
            document.getElementById('dateRange').addEventListener('change', function(){
                // local filtering for ranges including Sep-Dec (q3)
                applyAllFilters();
                fetchFilteredData();
            });

            // Make table rows clickable
            function attachRowClickHandlers() {
                const tableRows = document.querySelectorAll('.premium-table tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('click', function(e) {
                        // Don't trigger if clicking the View button itself
                        if (e.target.tagName === 'A' || e.target.tagName === 'I' || e.target.closest('a')) {
                            return;
                        }
                        // Find the View link in this row and navigate to it
                        const viewLink = this.querySelector('a[href*="view_case_details.php"]');
                        if (viewLink) {
                            window.location.href = viewLink.href;
                        }
                    });
                });
            }

            // Attach handlers on initial load
            attachRowClickHandlers();
            // Initial filter on load (status + date range)
            applyAllFilters();

            document.addEventListener('DOMContentLoaded', function () {
                // CASES BY STATUS
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                // Map each status label to the same color coding used for table badges
                const statusLabelsArr = <?= json_encode($statusLabels); ?>;
                const statusDataArr = <?= json_encode($statusData); ?>;
                const normalizedStatus = (str) =>
                    (str || '').toString().trim().toLowerCase().replace(/\s+/g, ' ');

                const colorMap = (label) => {
                    const s = normalizedStatus(label);
                    if (s.includes('mediation resolved')) return 'rgba(16, 185, 129, 0.7)';
                    if (s.includes('conciliation resolved')) return 'rgba(16, 185, 129, 0.7)';
                    if (s.includes('arbitration resolved')) return 'rgba(16, 185, 129, 0.7)';
                    if (s.includes('conciliation')) return 'rgba(234, 179, 8, 0.7)';
                    if (s.includes('mediation')) return 'rgba(168, 85, 247, 0.7)';
                    if (s.includes('arbitration')) return 'rgba(59, 130, 246, 0.7)';
                    if (s.includes('dismissed')) return 'rgba(239, 68, 68, 0.7)';
                    return 'rgba(107, 114, 128, 0.7)'; // default gray
                };


                const statusColors = statusLabelsArr.map(colorMap);

                statusChart = new Chart(statusCtx, {
                    type: 'pie',
                    data: {
                        labels: statusLabelsArr,
                        datasets: [{
                            label: 'Cases by Status',
                            data: statusDataArr,
                            backgroundColor: statusColors,
                            borderWidth: 0,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: { size: 11 }
                                }
                            }
                        }
                    }
                });

                // MONTHLY TRENDS
                const trendsCtx = document.getElementById('trendsChart').getContext('2d');
                trendsChart = new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($quarterLabels); ?>,
                        datasets: [{
                            label: 'Complaints per Quarter',
                            data: <?= json_encode($quarterCounts); ?>,
                            borderColor: '#0c9ced',
                            backgroundColor: 'rgba(12, 156, 237, 0.1)',
                            tension: 0.4,
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    font: { size: 11 }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: {
                                    drawBorder: false,
                                    color: '#f3f4f6'
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });

                // Mobile navigation toggle
                if (typeof menuButton !== 'undefined' && typeof mobileMenu !== 'undefined') {
                    menuButton.addEventListener('click', function () {
                        this.classList.toggle('active');
                        if (mobileMenu.style.transform === 'translateY(0%)') {
                            mobileMenu.style.transform = 'translateY(-100%)';
                        } else {
                            mobileMenu.style.transform = 'translateY(0%)';
                        }
                    });
                }
            });
        </script>
    </div>

    <?php include '../chatbot/bpamis_case_assistant.php' ?>
</body>

</html>