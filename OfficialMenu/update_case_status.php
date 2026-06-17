<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';
date_default_timezone_set('Asia/Manila');
// Redirect if no case ID is provided
if (!isset($_GET['id'])) {
    header("Location: view_cases.php");
    exit();
}

$caseId = intval($_GET['id']);

// Resolve real table names for case-sensitive Linux hosts
$T_CASE_INFO = bpamis_table($conn, 'CASE_INFO');
$T_COMPLAINT_INFO = bpamis_table($conn, 'COMPLAINT_INFO');
$T_RESIDENT_INFO = bpamis_table($conn, 'RESIDENT_INFO');
$T_NOTIFICATIONS = bpamis_table($conn, 'notifications');

$TB_CASE_INFO = bpamis_quote_table($T_CASE_INFO);
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_NOTIFICATIONS = bpamis_quote_table($T_NOTIFICATIONS);

$respondentIdCol = bpamis_first_existing_column($conn, $T_COMPLAINT_INFO, ['Respondent_ID','respondent_id']);

// Fetch case details with extra info
$sql = "SELECT 
            cs.Case_ID, cs.Case_Status,
            ci.Complaint_Title, ci.Date_Filed,
            comp.First_Name AS Complainant_First, comp.Last_Name AS Complainant_Last,
            " . ($respondentIdCol ? "resp.First_Name AS Respondent_First, resp.Last_Name AS Respondent_Last" : "NULL AS Respondent_First, NULL AS Respondent_Last") . "
        FROM {$TB_CASE_INFO} cs
        LEFT JOIN {$TB_COMPLAINT_INFO} ci ON cs.Complaint_ID = ci.Complaint_ID
        LEFT JOIN {$TB_RESIDENT_INFO} comp ON ci.Resident_ID = comp.Resident_ID
        " . ($respondentIdCol ? ("LEFT JOIN {$TB_RESIDENT_INFO} resp ON ci." . bpamis_quote_ident($respondentIdCol) . " = resp.Resident_ID") : "") . "
        WHERE cs.Case_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $caseId);
$stmt->execute();

$rows = bpamis_stmt_fetch_all_assoc($stmt);
$stmt->close();

if (empty($rows)) {
    echo "<p class='text-center text-red-600'>Case not found.</p>";
    exit();
}

$case = $rows[0];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $update = $conn->prepare("UPDATE {$TB_CASE_INFO} SET Case_Status = ? WHERE Case_ID = ?");
    $update->bind_param("si", $newStatus, $caseId);

    if ($update->execute()) {
    // Fetch resident_id and external_id
    $notifSql = "SELECT co.Resident_ID, co.External_Complainant_ID FROM {$TB_CASE_INFO} cs JOIN {$TB_COMPLAINT_INFO} co ON cs.Complaint_ID = co.Complaint_ID WHERE cs.Case_ID = ?";
    $notifRows = [];
    if ($ns = $conn->prepare($notifSql)) {
        $ns->bind_param('i', $caseId);
        $ns->execute();
        $notifRows = bpamis_stmt_fetch_all_assoc($ns);
        $ns->close();
    } else {
        $nr = $conn->query("SELECT co.Resident_ID, co.External_Complainant_ID FROM {$TB_CASE_INFO} cs JOIN {$TB_COMPLAINT_INFO} co ON cs.Complaint_ID = co.Complaint_ID WHERE cs.Case_ID = $caseId");
        if ($nr) {
            while ($r = $nr->fetch_assoc()) $notifRows[] = $r;
            $nr->free();
        }
    }

    if (!empty($notifRows)) {
        $resident_id = $notifRows[0]['Resident_ID'] ?? null;
        $external_id = $notifRows[0]['External_Complainant_ID'] ?? null;

        $title = "Case Status Updated";
        $message = "The status of your case (ID: $caseId) has been updated to \"$newStatus\".";
        $created_at = date('Y-m-d H:i:s');

        if (!empty($resident_id)) {
            if ($ins = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
                $rid = intval($resident_id);
                $ins->bind_param('isss', $rid, $title, $message, $created_at);
                $ins->execute();
                $ins->close();
            } else {
                $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (resident_id, title, message, is_read, created_at) VALUES (" . intval($resident_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created_at) . "')");
            }
        }

        if (!empty($external_id)) {
            if ($ins2 = $conn->prepare("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)")) {
                $eid = intval($external_id);
                $ins2->bind_param('isss', $eid, $title, $message, $created_at);
                $ins2->execute();
                $ins2->close();
            } else {
                $conn->query("INSERT INTO {$TB_NOTIFICATIONS} (external_complaint_id, title, message, is_read, created_at) VALUES (" . intval($external_id) . ", '" . $conn->real_escape_string($title) . "', '" . $conn->real_escape_string($message) . "', 0, '" . $conn->real_escape_string($created_at) . "')");
            }
        }
    }

    header("Location: view_cases.php?status_updated=1");
    exit();
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Case Status</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-xl shadow">
        <h2 class="text-xl font-bold mb-4 text-gray-700">Update Case Status</h2>

        <div class="mb-6 space-y-2">
            <p><strong>Case ID:</strong> <?= htmlspecialchars($case['Case_ID']) ?></p>
            <p><strong>Title:</strong> <?= htmlspecialchars($case['Complaint_Title']) ?></p>
            <p><strong>Complainant:</strong> <?= htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) ?></p>
            <p><strong>Respondent:</strong> <?= htmlspecialchars($case['Respondent_First'] . ' ' . $case['Respondent_Last']) ?></p>
            <p><strong>Date Filed:</strong> <?= date("F j, Y", strtotime($case['Date_Filed'])) ?></p>
            <p><strong>Current Status:</strong> <?= htmlspecialchars($case['Case_Status']) ?></p>
        </div>

        <form method="POST">
            <div class="mb-4">
                <label class="block font-semibold text-gray-700 mb-1">New Status:</label>
                <?php
                    $current = $case['Case_Status'];
                    $transitions = [
                        "Open" => ["Mediation", "Resolved", "Closed"],
                        "Mediation" => ["Resolution", "Resolved", "Closed"],
                        "Resolution" => ["Settlement", "Resolved", "Closed"],
                        "Settlement" => ["Resolved", "Closed"],
                        "Resolved" => ["Closed"],
                        "Closed" => []
                    ];
                    $available = $transitions[$current];
                ?>
                <?php if (count($available) > 0): ?>
                <select name="status" class="w-full p-2 border rounded">
                    <?php foreach ($available as $status): ?>
                        <option value="<?= $status ?>"><?= $status ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                    <p class="bg-gray-100 p-2 rounded text-red-600">This case is already closed. No further updates allowed.</p>
                <?php endif; ?>
            </div>

            <div class="text-right">
                <a href="view_cases.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    <?= count($available) === 0 ? 'disabled class="opacity-50 cursor-not-allowed"' : '' ?>>
                    Update
                </button>
            </div>
        </form>
    </div>
</body>
</html>
