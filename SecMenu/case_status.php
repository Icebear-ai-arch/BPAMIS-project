<?php
include '../server/server.php'; 
include '../controllers/session_control.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Status</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

</head>
<body class="bg-blue-50">
    <?php include '../includes/barangay_official_sec_nav.php'; ?>
    <div class="container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <h2 class="text-2xl font-semibold text-blue-800 mb-4">Case Status Information</h2>

        <!-- Case Search -->
        <div class="mb-4">
            <div class="flex">
                <input type="text" id="searchCase" placeholder="Search by case number or title..." class="w-full p-2 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <button class="bg-blue-500 text-white p-2 rounded-lg ml-2"><i class="fas fa-search"></i> Search</button>
            </div>
        </div>
        
        <!-- Case Status Filter -->
        <div class="mb-4 flex space-x-2">       
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg active-status">All Cases</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Open</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Pending Hearing</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Mediation</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Resolution</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Settlement</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Resolved</button>
            <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg">Closed</button>
        </div>
        
        <table class="w-full mt-4 border-collapse">
            <thead>
                <tr class="bg-blue-200">
                    <th class="p-2 text-left">Case ID</th>
                    <th class="p-2 text-left">Case Title</th>
                    <th class="p-2 text-left">Date Filed</th>
                    <th class="p-2 text-left">Last Updated</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
               $sql = "SELECT ci.*, comp.Complaint_Title 
        FROM case_info ci 
        LEFT JOIN complaint_info comp ON ci.Complaint_ID = comp.Complaint_ID 
        ORDER BY ci.Date_Opened DESC";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
    while ($case = $result->fetch_assoc()) {
    echo '<tr class="border-b hover:bg-gray-100">';
    echo '<td class="p-2">' . $case['Case_ID'] . '</td>';
    echo '<td class="p-2">' . ($case['Complaint_Title'] ?? 'N/A') . '</td>';
    echo '<td class="p-2">' . $case['Date_Opened'] . '</td>';
    echo '<td class="p-2">' . ($case['Date_Closed'] ?? 'Not yet closed') . '</td>';

    $status = $case['Case_Status']; // This is your correct field
    $statusClass = 'text-gray-600';
    $statusIcon = '';

    switch ($status) {
        case 'Open':
            $statusClass = 'text-blue-600';
            $statusIcon = '<i class="fas fa-folder-open"></i> ';
            break;
        case 'Pending Hearing':
            $statusClass = 'text-orange-600';
            $statusIcon = '<i class="fas fa-calendar-day"></i> ';
            break;
        case 'Mediation':
            $statusClass = 'text-purple-600';
            $statusIcon = '<i class="fas fa-handshake"></i> ';
            break;
        case 'Resolved':
            $statusClass = 'text-green-600';
            $statusIcon = '<i class="fas fa-check-circle"></i> ';
            break;
        case 'Closed':
            $statusClass = 'text-gray-600';
            $statusIcon = '<i class="fas fa-folder"></i> ';
            break;
    }

    echo '<td class="p-2 ' . $statusClass . '">' . $statusIcon . $status . '</td>';
    echo '<td class="p-2 text-center">
            <a href="view_case_details.php?id=' . $case['Case_ID'] . '" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="update_case_status.php?id=' . $case['Case_ID'] . '" class="bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600 ml-1">
                <i class="fas fa-edit"></i> Update
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
        
        <div class="mt-4 flex justify-between items-center">
            <div>
                Showing 1-5 of 5 entries
            </div>
            <div class="flex">
                <a href="#" class="mx-1 px-3 py-1 bg-gray-200 rounded-md">Previous</a>
                <a href="#" class="mx-1 px-3 py-1 bg-blue-500 text-white rounded-md">1</a>
                <a href="#" class="mx-1 px-3 py-1 bg-gray-200 rounded-md">Next</a>
            </div>
        </div>
    </div>
      <script>
        // Client-side search functionality
        document.getElementById('searchCase').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Filter buttons could be implemented with JavaScript here
    </script>
    <script>
    const searchInput = document.getElementById('searchCase');
    const filterButtons = document.querySelectorAll('.mb-4.flex.space-x-2 button');
    const tableRows = document.querySelectorAll('tbody tr');

    let activeStatus = 'All Cases';

    // Apply filters (status + search)
    function applyFilters() {
        const searchValue = searchInput.value.toLowerCase();

        tableRows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const statusCell = row.querySelector('td:nth-child(5)');
            const status = statusCell ? statusCell.textContent.trim().toLowerCase() : '';

            const matchesSearch = rowText.includes(searchValue);
            const matchesStatus = activeStatus === 'All Cases' || status.includes(activeStatus.toLowerCase());

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    // Search input event
    searchInput.addEventListener('keyup', applyFilters);

    // Status filter button events
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            // Reset all button styles
            filterButtons.forEach(b => b.classList.remove('bg-blue-600', 'text-white'));
            filterButtons.forEach(b => b.classList.add('bg-gray-200', 'text-gray-800'));

            // Highlight clicked button
            this.classList.remove('bg-gray-200', 'text-gray-800');
            this.classList.add('bg-blue-600', 'text-white');

            activeStatus = this.textContent.trim();
            applyFilters();
        });
    });
</script>

   <?php include '../chatbot/bpamis_case_assistant.php'?>
    <?php include 'sidebar_.php';?>
</body>
</html>
