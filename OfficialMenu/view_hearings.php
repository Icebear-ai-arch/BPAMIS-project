<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Make sure Lupon is logged in
if (!isset($_SESSION['lupon_name'])) {
    header("Location: ../login.php");
    exit();
}

$lupon_name = $_SESSION['lupon_name'];

// Prepare the query to get hearings for this lupon
$sql = "
    SELECT sl.*
    FROM schedule_list sl
    INNER JOIN case_info ci ON ci.case_id = sl.case_id
    LEFT JOIN mediation_info mi 
        ON mi.case_id = ci.case_id AND mi.mediator_name = ?
    LEFT JOIN resolution r 
        ON r.case_id = ci.case_id AND r.mediator_name = ?
    LEFT JOIN settlement s 
        ON s.case_id = ci.case_id AND s.mediator_name = ?
    WHERE mi.case_id IS NOT NULL 
       OR r.case_id IS NOT NULL 
       OR s.case_id IS NOT NULL
    ORDER BY sl.hearingDateTime DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $lupon_name, $lupon_name, $lupon_name);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);

$hearings = [];
while ($row = $result->fetch_assoc()) {
    $hearings[] = $row;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Assigned Hearings</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <?php include '../includes/barangay_official_lupon_nav.php'?>
    <?php include 'sidebar_lupon.php'?>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="max-w-6xl mx-auto bg-white rounded shadow p-6">
        <h1 class="text-2xl font-bold mb-4">My Assigned Hearings</h1>

        <?php if (empty($hearings)): ?>
            <p class="text-gray-600">No hearings assigned to you.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table-auto border-collapse border border-gray-300 w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-300 px-4 py-2">Case ID</th>
                            <th class="border border-gray-300 px-4 py-2">Title</th>
                            <th class="border border-gray-300 px-4 py-2">Date & Time</th>
                            <th class="border border-gray-300 px-4 py-2">Remarks</th>
                            <th class="border border-gray-300 px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hearings as $hearing): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($hearing['Case_ID']) ?></td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($hearing['hearingTitle']) ?></td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <?= date("F d, Y h:i A", strtotime($hearing['hearingDateTime'])) ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($hearing['remarks']) ?></td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <a href="view_case_details.php?id=<?= urlencode($hearing['Case_ID']) ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                                       View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
