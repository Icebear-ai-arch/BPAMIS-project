<?php
/**
 * Sync Hearing Visibility
 * 
 * This script automatically sets visible = 0 for hearings in schedule_list
 * that have corresponding meeting logs in MEETING_LOGS table.
 * 
 * This ensures hearings with completed meeting logs are hidden from all calendars.
 */

require_once('db-connect.php');

// Update visible column to 0 for hearings that have meeting logs
$update_sql = "
    UPDATE schedule_list sl
    INNER JOIN MEETING_LOGS ml 
        ON ml.Case_ID = sl.case_id 
        AND DATE(ml.Hearing_Date) = DATE(sl.hearingDateTime)
        AND TIME(ml.Hearing_Time) = TIME(sl.hearingDateTime)
    SET sl.visible = 0
    WHERE sl.visible = 1
";

$result = $conn->query($update_sql);

if ($result) {
    $affected_rows = $conn->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated {$affected_rows} hearing(s) visibility.",
        'affected_rows' => $affected_rows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Error updating hearing visibility: " . $conn->error
    ]);
}

$conn->close();
?>
