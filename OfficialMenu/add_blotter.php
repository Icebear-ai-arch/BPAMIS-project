<?php
session_start();
include_once __DIR__ . '/../server/server.php';

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['blotter_description'];
    $reported_by = $_POST['reported_by'];
    $date_reported = $_POST['date_reported'];

    $stmt = $conn->prepare("INSERT INTO BLOTTER_INFO (Blotter_Description, Reported_By, Date_Reported) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $description, $reported_by, $date_reported);

    if ($stmt->execute()) {
        $success = true;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Blotter Report</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow">
        <h2 class="text-2xl font-semibold text-blue-800 mb-6">Add Blotter Report</h2>

        <?php if ($success): ?>
            <div class="mb-6 p-4 rounded bg-green-100 border border-green-300 text-green-800">
                Blotter report has been successfully recorded.
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="blotter_description">Blotter Description</label>
                <textarea name="blotter_description" id="blotter_description" rows="4" required class="w-full border border-gray-300 rounded p-2"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="reported_by">Reported By</label>
                <input type="text" name="reported_by" id="reported_by" required class="w-full border border-gray-300 rounded p-2">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="date_reported">Date Reported</label>
                <input type="date" name="date_reported" id="date_reported" required class="w-full border border-gray-300 rounded p-2">
            </div>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Submit Blotter Report
            </button>
        </form>
        
    </div>
    <?php include '../chatbot/bpamis_case_assistant.php'?>    
</body>
</html>
