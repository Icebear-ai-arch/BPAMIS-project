<?php 
require_once('db-connect.php');

if(!isset($_GET['id'])){
    echo "<script> alert('Undefined Schedule ID.'); window.location.href = 'CalendarSec.php'; </script>";
    $conn->close();
    exit;
}

// Determine return target after deletion.
// Accept either a simple filename (e.g. CalendarSec.php) or a site-root relative path like
// "bpamis/SecMenu/home-secretary.php". Normalize to an absolute path and block any '..' segments.
$returnTarget = 'CalendarSec.php';
if (isset($_GET['return'])) {
    $candidate = $_GET['return'];
    // allow alphanum, underscore, dot, hyphen and slashes
    if (preg_match('/^[a-zA-Z0-9_\/\.-]+$/', $candidate) && strpos($candidate, '..') === false) {
        // If candidate contains a slash, treat it as a site-root relative path and ensure it starts with '/'
        if (strpos($candidate, '/') !== false) {
            $returnTarget = '/' . ltrim($candidate, '/');
        } else {
            // simple filename in same folder
            $returnTarget = $candidate;
        }
    }
}

// Prefer soft-hide so history and joins remain intact; mark visible = 0 for the matching schedule row(s)
$hid = (int)$_GET['id'];

// Fetch schedule row to get case_id and hearing datetime for notifying related parties
$sched = null;
$sstmt = $conn->prepare("SELECT hearingID, Case_ID, HearingDateTime, hearingTitle FROM schedule_list WHERE hearingID = ? LIMIT 1");
if ($sstmt) {
    $sstmt->bind_param('i', $hid);
    $sstmt->execute();
    $sr = bpamis_stmt_get_result($sstmt);
    if ($sr && $sr->num_rows > 0) {
        $sched = $sr->fetch_assoc();
    }
    $sstmt->close();
}

if (!$sched) {
    // If the schedule row wasn't found, fall back to deleting by id to keep previous behavior
    $stmt = $conn->prepare("DELETE FROM `schedule_list` WHERE hearingID = ?");
    $stmt->bind_param("i", $hid);
    if($stmt->execute()){
        $msg = 'Event has been deleted successfully.';
        echo "<script> alert('" . addslashes($msg) . "'); window.location.href = '" . addslashes($returnTarget) . "'; </script>";
    } else {
        echo "<pre>";
        echo "An Error occurred.<br>";
        echo "Error: " . $conn->error . "<br>";
        echo "</pre>";
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Update all matching schedule rows (precise hearing row and any duplicates for same case+datetime)
$caseId = (int)$sched['Case_ID'];
$hdt = $sched['HearingDateTime'];
$uStmt = $conn->prepare("UPDATE schedule_list SET visible = 0 WHERE hearingID = ? OR (Case_ID = ? AND HearingDateTime = ?)");
if ($uStmt) {
    $uStmt->bind_param('iis', $hid, $caseId, $hdt);
    $uStmt->execute();
    $uStmt->close();
}

// Insert notifications to related parties (mirror pattern used when creating/rescheduling hearings)
$created_at = date('Y-m-d H:i:s');
$notif_title = 'Hearing Cancelled';
$niceDate = date('F d, Y g:i A', strtotime($hdt));
$notif_message = "The hearing for Case ID: $caseId scheduled on $niceDate has been cancelled.";
$notif_type = 'Hearing';

// Fetch case + complaint metadata for recipients
$caseInfo = null;
$caseSql = $conn->prepare("SELECT ci.Case_ID, ci.case_original_id, co.Complaint_ID, co.Resident_ID, co.External_Complainant_ID, COALESCE(co.respondent_id, co.Respondent_ID) AS main_respondent FROM case_info ci JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID WHERE ci.Case_ID = ? LIMIT 1");
if ($caseSql) {
    $caseSql->bind_param('i', $caseId);
    $caseSql->execute();
    $cres = bpamis_stmt_get_result($caseSql);
    if ($cres && $cres->num_rows > 0) $caseInfo = $cres->fetch_assoc();
    $caseSql->close();
}

if ($caseInfo) {
    // Notify complainant (resident or external)
    if (!empty($caseInfo['Resident_ID'])) {
        $r = $conn->real_escape_string($notif_message);
        $t = $conn->real_escape_string($notif_title);
        $conn->query("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES ({$caseInfo['Resident_ID']}, '$t', '$r', '$notif_type', 0, '$created_at')");
    } elseif (!empty($caseInfo['External_Complainant_ID'])) {
        $r = $conn->real_escape_string($notif_message);
        $t = $conn->real_escape_string($notif_title);
        $conn->query("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES ({$caseInfo['External_Complainant_ID']}, '$t', '$r', '$notif_type', 0, '$created_at')");
    }

    // Notify main respondent
    if (!empty($caseInfo['main_respondent'])) {
        $r = $conn->real_escape_string($notif_message);
        $t = $conn->real_escape_string($notif_title);
        $conn->query("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES ({$caseInfo['main_respondent']}, '$t', '$r', '$notif_type', 0, '$created_at')");
    }

    // Notify all other respondents (complaint_respondents)
    if (!empty($caseInfo['Complaint_ID'])) {
        $respStmt = $conn->prepare("SELECT Respondent_ID FROM complaint_respondents WHERE Complaint_ID = ?");
        if ($respStmt) {
            $respStmt->bind_param('i', $caseInfo['Complaint_ID']);
            $respStmt->execute();
            $rr = bpamis_stmt_get_result($respStmt);
            while ($row = $rr->fetch_assoc()) {
                $rid = (int)$row['Respondent_ID'];
                if ($rid && $rid != $caseInfo['main_respondent']) {
                    $r = $conn->real_escape_string($notif_message);
                    $t = $conn->real_escape_string($notif_title);
                    $conn->query("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES ($rid, '$t', '$r', '$notif_type', 0, '$created_at')");
                }
            }
            $respStmt->close();
        }
    }

    // Notify Secretary, Captain, and Lupon Head (officials)
    $officials = ['Secretary', 'Captain', 'Lupon Head'];
    foreach ($officials as $position) {
        $officialStmt = $conn->prepare("SELECT official_id FROM barangay_officials WHERE position = ? LIMIT 1");
        if ($officialStmt) {
            $officialStmt->bind_param('s', $position);
            $officialStmt->execute();
            $or = bpamis_stmt_get_result($officialStmt);
            if ($or && $or->num_rows > 0) {
                $orow = $or->fetch_assoc();
                $oid = (int)$orow['official_id'];
                $r = $conn->real_escape_string($notif_message);
                $t = $conn->real_escape_string($notif_title);
                $conn->query("INSERT INTO notifications (official_id, title, message, type, is_read, created_at) VALUES ($oid, '$t', '$r', '$notif_type', 0, '$created_at')");
            }
            $officialStmt->close();
        }
    }

    // Notify assigned Lupon members (lookup mediator names across related tables)
    $luponNames = [];
    $tables = ['mediation_info' => 'Mediator_Name', 'conciliation' => 'Mediator_Name', 'resolution' => 'Mediator_Name', 'settlement' => 'Mediator_Name', 'arbitration' => 'Mediator_Name'];
    foreach ($tables as $tbl => $col) {
        $ls = $conn->prepare("SELECT {$col} FROM {$tbl} WHERE Case_ID = ? LIMIT 1");
        if ($ls) {
            $ls->bind_param('i', $caseId);
            $ls->execute();
            $lr = bpamis_stmt_get_result($ls);
            if ($lr && $lr->num_rows > 0) {
                $lrow = $lr->fetch_assoc();
                if (!empty($lrow[$col])) $luponNames[] = $lrow[$col];
            }
            $ls->close();
        }
    }
    $luponNames = array_unique(array_filter($luponNames));
    foreach ($luponNames as $luponName) {
        $luponOfficialStmt = $conn->prepare("SELECT official_id FROM barangay_officials WHERE name LIKE ? LIMIT 1");
        if ($luponOfficialStmt) {
            $like = '%' . $luponName . '%';
            $luponOfficialStmt->bind_param('s', $like);
            $luponOfficialStmt->execute();
            $lr = bpamis_stmt_get_result($luponOfficialStmt);
            if ($lr && $lr->num_rows > 0) {
                $lrow = $lr->fetch_assoc();
                $oid = (int)$lrow['official_id'];
                $r = $conn->real_escape_string($notif_message);
                $t = $conn->real_escape_string($notif_title);
                $conn->query("INSERT INTO notifications (lupon_id, title, message, type, is_read, created_at) VALUES ($oid, '$t', '$r', '$notif_type', 0, '$created_at')");
            }
            $luponOfficialStmt->close();
        }
    }
}

// Provide user feedback and redirect back to the requested page
$msg = 'Event has been cancelled and hidden from participant calendars.';
echo "<script> alert('" . addslashes($msg) . "'); window.location.href = '" . addslashes($returnTarget) . "'; </script>";

$conn->close();


?>