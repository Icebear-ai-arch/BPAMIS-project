<?php
// Simple migration helper: adds Attendance and Reason_Incompliance columns to MEETING_LOGS if they don't exist.
// Run this once from the CLI: php controllers/add_meeting_logs_columns.php
// Or place it temporarily on the server (then remove it) and open in browser if necessary.

include_once __DIR__ . '/../server/server.php';

$cols = function($name) use ($conn) {
    $res = $conn->query("SHOW COLUMNS FROM MEETING_LOGS LIKE '" . $conn->real_escape_string($name) . "'");
    if (!$res) return false;
    $has = $res->num_rows > 0;
    $res->close();
    return $has;
};

$haveAttendance = $cols('Attendance');
$haveReason = $cols('Reason_Incompliance');

if ($haveAttendance && $haveReason) {
    echo "MEETING_LOGS already has Attendance and Reason_Incompliance columns.\n";
    exit(0);
}

$parts = [];
if (!$haveAttendance) $parts[] = "ADD COLUMN Attendance TEXT NULL";
if (!$haveReason) $parts[] = "ADD COLUMN Reason_Incompliance TEXT NULL";

$sql = "ALTER TABLE MEETING_LOGS " . implode(', ', $parts);

if ($conn->query($sql) === TRUE) {
    echo "Successfully updated MEETING_LOGS: added columns.\n";
} else {
    echo "Failed to alter MEETING_LOGS: " . $conn->error . "\n";
}

$conn->close();

?>
