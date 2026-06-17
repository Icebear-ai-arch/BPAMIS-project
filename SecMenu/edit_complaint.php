<?php

include('../server/server.php');

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle update on form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['complaint_title']);
    $respondent_name = $conn->real_escape_string($_POST['respondent_name']);
    $description = $conn->real_escape_string($_POST['complaint_description']);
    $date_filed = $conn->real_escape_string($_POST['date_filed']);
    $status = $conn->real_escape_string($_POST['status']);

    $update = "UPDATE COMPLAINT_INFO 
           SET Complaint_Title = '$title', Respondent_Name = '$respondent_name', Complaint_Details = '$description', 
               Date_Filed = '$date_filed', Status = '$status' 
           WHERE Complaint_ID = $complaint_id";


    if ($conn->query($update) === TRUE) {
       header("Location: view_complaints.php");// Redirect to view page after update
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}

// Fetch existing complaint data
$sql = "SELECT * FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id";
$result = $conn->query($sql);
$complaint = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Complaint</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-blue-50 p-10">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold text-blue-800 mb-4">Edit Complaint</h2>

        <?php if (isset($error)): ?>
            <p class="text-red-600"><?= $error ?></p>
        <?php endif; ?>

        <?php if ($complaint): ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-blue-900">Complaint Title</label>
                <input type="text" name="complaint_title" value="<?= htmlspecialchars($complaint['Complaint_Title']) ?>" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-blue-900">Respondent   </label>
                <input type="text" name="respondent_name" value="<?= htmlspecialchars($complaint['Respondent_Name'] ?? '') ?>" class="w-full p-2 border rounded">
            </div>
            <div class="mb-4">
                <label class="block text-blue-900">Complaint Description</label>
                <textarea name="complaint_description" rows="4" class="w-full p-2 border rounded" required><?= htmlspecialchars($complaint['Complaint_Details']) ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-blue-900">Date Filed</label>
                <input type="date" name="date_filed" value="<?= $complaint['Date_Filed'] ?>" class="w-full p-2 border rounded" required>
            </div>

            <div class="mb-4">
                <label class="block text-blue-900">Status</label>
                <select name="status" class="w-full p-2 border rounded" required>
                    <?php
                    $statuses = ['Pending', 'Under Investigation', 'Scheduled for Hearing', 'Resolved', 'Dismissed'];
                    foreach ($statuses as $status) {
                        $selected = $status === $complaint['Status'] ? 'selected' : '';
                        echo "<option value=\"$status\" $selected>$status</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="flex justify-between mt-6">
                <a href="view_complaint.php?id=<?= $complaint_id ?>" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
        <?php else: ?>
            <p class="text-red-600">Complaint not found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
