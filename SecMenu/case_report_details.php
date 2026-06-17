<?php
include '../server/server.php';
include '../controllers/session_control.php';
$caseId = $_GET['id'] ?? null;

if (!$caseId) {
    echo "Invalid request.";
    exit;
}

// Fetch case & complaint info
$query = "SELECT 
    c.Case_ID,
    ci.Complaint_Title,
    ci.Complaint_Details,
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
WHERE c.Case_ID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $caseId);
$stmt->execute();
$caseResult = bpamis_stmt_get_result($stmt);

if ($caseResult->num_rows === 0) {
    echo "Case not found.";
    exit;
}

$case = $caseResult->fetch_assoc();

// Fetch hearing schedule
$hearingsQuery = "SELECT hearingTitle, hearingDateTime, place, participant, remarks 
                  FROM schedule_list 
                  WHERE Case_ID = ? 
                  ORDER BY hearingDateTime ASC";

$stmt2 = $conn->prepare($hearingsQuery);
$stmt2->bind_param("i", $caseId);
$stmt2->execute();
$hearingsResult = bpamis_stmt_get_result($stmt2);

$hearings = [];
while ($row = $hearingsResult->fetch_assoc()) {
    $hearings[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Report Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</head>
<body class="bg-gray-50 font-sans">
<?php include '../includes/barangay_official_sec_nav.php'; ?>

<!-- Page Header (copied from view_case_details.php) -->
<section class="container mx-auto mt-8 px-4">
    <div class="gradient-bg rounded-xl shadow-sm p-6 md:p-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-20 -mt-20 opacity-70"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-10 -mb-10 opacity-60"></div>
        <div class="relative z-10">
            <h1 class="text-2xl font-medium text-primary-800">Case Report Details</h1>
            <p class="mt-1 text-gray-600">View recorded information of case report details.</p>
        </div>
    </div>
</section>

<div class="container mx-auto mt-12 px-4">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-2xl font-bold text-blue-800 mb-4">Case Report Details</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p><strong>Case ID:</strong> <?= htmlspecialchars($case['Case_ID']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($case['Case_Status']) ?></p>
                <p><strong>Date Opened:</strong> <?= htmlspecialchars($case['Date_Opened']) ?></p>
                <p><strong>Date Closed:</strong> <?= htmlspecialchars($case['Date_Closed'] ?? 'N/A') ?></p>
                <p><strong>Next Hearing:</strong> <?= htmlspecialchars($case['Next_Hearing_Date'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p><strong>Complainant:</strong> <?= !empty($case['resident_fname']) 
                    ? htmlspecialchars($case['resident_fname'] . ' ' . $case['resident_lname']) 
                    : htmlspecialchars($case['external_fname'] . ' ' . $case['external_lname']) ?></p>
            </div>
        </div>

        <div class="mt-4">
            <p><strong>Complaint Title:</strong> <?= htmlspecialchars($case['Complaint_Title']) ?></p>
            <p><strong>Date Filed:</strong> <?= htmlspecialchars($case['Date_Filed']) ?></p>
        </div>

        <div class="mt-4">
            <p class="font-semibold">Complaint Details:</p>
            <div class="border p-3 rounded bg-gray-50 text-sm text-gray-700">
                <?= nl2br(htmlspecialchars($case['Complaint_Details'])) ?>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-xl font-semibold mb-2">Hearing Schedules</h2>
            <?php if (count($hearings) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 shadow-sm rounded">
                        <thead class="bg-gray-50 text-gray-700 text-sm font-semibold">
                            <tr>
                                <th class="py-2 px-4 border-b">Title</th>
                                <th class="py-2 px-4 border-b">Date & Time</th>
                                <th class="py-2 px-4 border-b">Place</th>
                                <th class="py-2 px-4 border-b">Participants</th>
                                <th class="py-2 px-4 border-b">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-600">
                            <?php foreach ($hearings as $hearing): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($hearing['hearingTitle']) ?></td>
                                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($hearing['hearingDateTime']) ?></td>
                                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($hearing['place']) ?></td>
                                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($hearing['participant']) ?></td>
                                    <td class="py-2 px-4 border-b"><?= htmlspecialchars($hearing['remarks']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No hearings recorded for this case.</p>
            <?php endif; ?>
        </div>

        <div class="mt-6">
            <a href="javascript:history.back()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">&larr; Back to Case List</a>
        </div>
    </div>
</div>
<?php include 'sidebar_.php';?>
</body>
</html>
