<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Redirect if not logged in
if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Get filter status from GET request (default to Resolution)
$status_filter = $_GET['status'] ?? 'Resolution';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $case_id = $_POST['case_id'] ?? null;
    $lupon_names = $_POST['lupon_name'] ?? [];
    $status_type = $_POST['status_type'] ?? '';

    if (!empty($case_id) && !empty($lupon_names) && !empty($status_type)) {
    $table_map = [
        'Mediation' => 'mediation_info',
        'Resolution' => 'resolution',
        'Settlement' => 'settlement'
    ];

    if (!isset($table_map[$status_type])) {
        $error = "Invalid status type.";
    } else {
        $table_name = $table_map[$status_type];
        $names_string = implode(', ', array_map('trim', $lupon_names));

        // Prepare update statement
        $stmt = $conn->prepare("UPDATE $table_name SET mediator_name = ? WHERE case_id = ?");
        $stmt->bind_param("si", $names_string, $case_id);

        // Execute the update statement here!
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = "Case assigned successfully with multiple Lupon Tagapamayapa in $status_type stage.";

                // Prepare notification insert statement ONCE before the loop
                $notifStmt = $conn->prepare("INSERT INTO notifications (title, message, type, created_at, lupon_id, is_read) VALUES (?, ?, ?, NOW(), ?, 0)");
                if (!$notifStmt) {
                    die("Notification statement prepare failed: " . $conn->error);
                }

                // Loop to find each Lupon and insert notification
                foreach ($lupon_names as $fullName) {
                    $fullName = trim($fullName);
                    if (empty($fullName)) continue;

                    $searchSql = "SELECT Official_ID FROM barangay_officials WHERE Position = 'Lupon Tagapamayapa' AND Name = ?";
                    $searchStmt = $conn->prepare($searchSql);
                    $searchStmt->bind_param("s", $fullName);
                    $searchStmt->execute();
                    $searchResult = bpamis_stmt_get_result($searchStmt);

                    if ($searchResult && $searchResult->num_rows > 0) {
                        $luponRow = $searchResult->fetch_assoc();
                        $luponId = $luponRow['Official_ID'];

                        $title = "New Case Assigned";
                        $message = "A new case #$case_id has been assigned to you in the $status_type stage.";
                        $type = "Case";

                        $notifStmt->bind_param("sssi", $title, $message, $type, $luponId);
                        $notifStmt->execute();
                    }
                    $searchStmt->close();
                }
                $notifStmt->close();

            } else {
                $error = "No matching case found in $status_type table.";
            }
        } else {
            $error = "Failed to assign case. Please try again.";
        }

        $stmt->close();
    }
} else {
    $error = "Please fill out all required fields.";
}

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign a Case</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js" defer></script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <?php include 'sidebar_.php'; ?>
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-2xl mx-auto bg-white p-6 rounded-xl shadow-md border border-gray-100">
            <div class="mb-6 flex items-center gap-2">
                <i class="fas fa-user-plus text-blue-600 text-2xl"></i>
                <h2 class="text-2xl font-semibold text-gray-800">Assign a Case</h2>
            </div>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Status Filter -->
            <form method="GET" class="mb-4">
                <label for="status" class="block text-gray-700 font-medium mb-1">Filter by Status</label>
                <select id="status" name="status" class="w-full p-2 border border-gray-300 rounded-lg" onchange="this.form.submit()">
                    <option value="Mediation" <?= $status_filter === 'Mediation' ? 'selected' : '' ?>>Mediation</option>
                    <option value="Resolution" <?= $status_filter === 'Resolution' ? 'selected' : '' ?>>Resolution</option>
                    <option value="Settlement" <?= $status_filter === 'Settlement' ? 'selected' : '' ?>>Settlement</option>
                </select>
            </form>

            <!-- Assign Form -->
            <form method="POST">
                <input type="hidden" name="status_type" value="<?= htmlspecialchars($status_filter) ?>">

                <div class="mb-4">
                    <label for="case_id" class="block text-gray-700 font-medium mb-1">Select Case</label>
                    <select id="case_id" name="case_id" required class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="">-- Select a Case --</option>
                        <?php
                        $sql = "SELECT ci.Case_ID, co.Complaint_Title
                                FROM case_info ci
                                JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
                                WHERE ci.Case_Status = ?
                                ORDER BY ci.Case_ID ASC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $status_filter);
                        $stmt->execute();
                        $result = bpamis_stmt_get_result($stmt);
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['Case_ID']) . '">' .
                                    htmlspecialchars($row['Case_ID'] . ' - ' . $row['Complaint_Title']) .
                                    '</option>';
                            }
                        } else {
                            echo '<option disabled>No cases found for this status</option>';
                        }
                        $stmt->close();
                        ?>
                    </select>
                </div>

                <div class="mb-4" id="lupon-container">
                    <label class="block text-gray-700 font-medium mb-1">Lupon Tagapamayapa Name(s)</label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" name="lupon_name[]" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                            placeholder="Enter the Lupon Tagapamayapa name">
                        <button type="button" onclick="addLuponField()" class="bg-green-500 text-white px-3 py-2 rounded-lg">+</button>
                    </div>
                </div>

                <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition-all shadow-md">
                    <i class="fas fa-paper-plane mr-2"></i>Assign
                </button>
            </form>

            <script>
document.getElementById('case_id').addEventListener('change', function() {
    const caseId = this.value;
    const status = '<?= htmlspecialchars($status_filter) ?>';
    const luponContainer = document.getElementById('lupon-container');

    // Clear current inputs except the first input and button wrapper
    luponContainer.innerHTML = '<label class="block text-gray-700 font-medium mb-1">Lupon Tagapamayapa Name(s)</label>';

    if (!caseId) {
        // If no case selected, reset to one empty field with add button
        luponContainer.innerHTML += `
            <div class="flex gap-2 mb-2">
                <input type="text" name="lupon_name[]" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                    placeholder="Enter the Lupon Tagapamayapa name">
                <button type="button" onclick="addLuponField()" class="bg-green-500 text-white px-3 py-2 rounded-lg">+</button>
            </div>
        `;
        return;
    }

    fetch(`get_mediators.php?case_id=${caseId}&status=${status}`)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        const mediators = data.mediators;

        if (mediators.length === 0) {
            // No mediators found, create one empty field
            luponContainer.innerHTML += `
                <div class="flex gap-2 mb-2">
                    <input type="text" name="lupon_name[]" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                        placeholder="Enter the Lupon Tagapamayapa name">
                    <button type="button" onclick="addLuponField()" class="bg-green-500 text-white px-3 py-2 rounded-lg">+</button>
                </div>
            `;
        } else {
            mediators.forEach((name, index) => {
                luponContainer.innerHTML += `
                    <div class="flex gap-2 mb-2">
                        <input type="text" name="lupon_name[]" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
                            value="${name}" placeholder="Enter the Lupon Tagapamayapa name">
                        ${index === mediators.length - 1
                            ? '<button type="button" onclick="addLuponField()" class="bg-green-500 text-white px-3 py-2 rounded-lg">+</button>'
                            : '<button type="button" onclick="this.parentElement.remove()" class="bg-red-500 text-white px-3 py-2 rounded-lg">-</button>'
                        }
                    </div>
                `;
            });
        }
    })
    .catch(err => {
        alert('Failed to fetch mediators.');
        console.error(err);
    });
});

function addLuponField() {
    const container = document.getElementById('lupon-container');
    const div = document.createElement('div');
    div.classList.add('flex', 'gap-2', 'mb-2');
    div.innerHTML = `
        <input type="text" name="lupon_name[]" required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300"
            placeholder="Enter another Lupon Tagapamayapa name">
        <button type="button" onclick="this.parentElement.remove()" class="bg-red-500 text-white px-3 py-2 rounded-lg">-</button>
    `;
    container.appendChild(div);
}

// Trigger change event on page load to load mediators for the selected case if any
window.addEventListener('DOMContentLoaded', () => {
    const caseSelect = document.getElementById('case_id');
    if (caseSelect.value) {
        caseSelect.dispatchEvent(new Event('change'));
    }
});
</script>

        </div>
    </div>
</body>
</html>
