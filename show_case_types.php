<?php
// show_case_types.php — debug helper for case_types column
// Place at webroot and open in browser: http://localhost/BPAMIS/show_case_types.php
// Visiting with ?fix=1 will create a Case_Type column and copy values from case_type if needed.

require_once __DIR__ . '/server/server.php'; // provides $conn and session handling

function column_exists($conn, $col) {
    $tbl = 'case_types';
    $sql = "SHOW COLUMNS FROM `{$tbl}` LIKE '" . $conn->real_escape_string($col) . "'";
    $r = $conn->query($sql);
    return $r && $r->num_rows > 0;
}

$hasCaseType = column_exists($conn, 'Case_Type');
$hascase_type = column_exists($conn, 'case_type');

echo "<h2>case_types table diagnostics</h2>\n";
echo "<ul>\n";
echo "<li>Case_Type column exists: " . ($hasCaseType ? 'Yes' : 'No') . "</li>\n";
echo "<li>case_type column exists: " . ($hascase_type ? 'Yes' : 'No') . "</li>\n";
echo "</ul>\n";

// If the user requested fix=1, we will create Case_Type if missing and copy values
$doFix = isset($_GET['fix']) && $_GET['fix'] == '1';
if ($doFix) {
    if (!$hasCaseType && !$hascase_type) {
        echo "<p style='color:red'>No case_type-like column exists; cannot copy. Consider manual INSERT column.</p>\n";
    } else {
        if (!$hasCaseType) {
            // create Case_Type column
            $sql = "ALTER TABLE `case_types` ADD COLUMN `Case_Type` VARCHAR(255) NULL";
            if (!$conn->query($sql)) {
                echo "<p style='color:red'>Failed to create Case_Type: " . htmlspecialchars($conn->error) . "</p>\n";
            } else {
                echo "<p>Created column `Case_Type`.</p>\n";
                $hasCaseType = true;
            }
        }

        // If case_type exists and Case_Type exists, copy non-null values where Case_Type is null
        if ($hascase_type && $hasCaseType) {
            $sql = "UPDATE `case_types` SET `Case_Type` = `case_type` WHERE (`Case_Type` IS NULL OR `Case_Type` = '') AND (`case_type` IS NOT NULL AND `case_type` <> '')";
            if ($conn->query($sql)) {
                echo "<p>Copied values from `case_type` to `Case_Type`. Affected rows: " . $conn->affected_rows . "</p>\n";
            } else {
                echo "<p style='color:red'>Failed to copy values: " . htmlspecialchars($conn->error) . "</p>\n";
            }
        }
    }
}

// Show rows using whichever column we have (prefer Case_Type)
$col = $hasCaseType ? 'Case_Type' : ($hascase_type ? 'case_type' : null);
if (!$col) {
    echo "<p style='color:red'>No suitable column to display.</p>\n";
    exit;
}

$sql = "SELECT Type_ID, `{$col}` AS case_type FROM `case_types` ORDER BY Type_ID";
$res = $conn->query($sql);
if (!$res) {
    echo "<p style='color:red'>Failed to query case_types: " . htmlspecialchars($conn->error) . "</p>\n";
    exit;
}

echo "<h3>Using column: {$col}</h3>\n";
echo "<table border=1 cellpadding=6><tr><th>Type_ID</th><th>case_type</th></tr>\n";
while ($r = $res->fetch_assoc()) {
    echo '<tr><td>' . htmlspecialchars($r['Type_ID']) . '</td><td>' . htmlspecialchars($r['case_type']) . '</td></tr>' . PHP_EOL;
}
echo "</table>\n";

echo "<p>To fix column mismatches automatically, visit this page with <code>?fix=1</code> (will create Case_Type if missing and copy values from case_type).</p>\n";

?>