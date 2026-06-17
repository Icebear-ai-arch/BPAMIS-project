<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
// Accept case id from either ?id= or ?case_id=
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : (int)($_GET['id'] ?? 0);
// Accept hearing id (hearingID) if provided so we can fetch the exact scheduled datetime
$hearing_id = isset($_GET['hearing_id']) ? (int)$_GET['hearing_id'] : (isset($_GET['hearingID']) ? (int)$_GET['hearingID'] : 0);

// Save new hearing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $hearing_date = $_POST['hearing_date'];
  $hearing_time = $_POST['hearing_time'] . ':00';
  // Always compute a datetime string for notifications and schedule matching
  $hearing_datetime = $hearing_date . ' ' . $hearing_time;
  // Preserve hearing_id from the form if present
  $post_hearing_id = isset($_POST['hearing_id']) ? (int)$_POST['hearing_id'] : 0;

  // Check if hearing_end_time is provided; if empty, use NULL
  $hearing_end_time = !empty($_POST['hearing_end_time']) ? $_POST['hearing_end_time'] . ':00' : null;

  $details = $_POST['details'];

  $complainant_status = $_POST['complainant_status'] ?? null;
  $complainant_reason = trim($_POST['complainant_reason'] ?? '');

  // If respondent_status is an array, join into a comma-separated string
  if (is_array($_POST['respondent_status'])) {
    $respondent_status = implode(', ', $_POST['respondent_status']);
  } else {
    $respondent_status = $_POST['respondent_status'] ?? null;
  }

  // Collect respondent reasons (array keyed by respondent id)
  $respondent_status_array = is_array($_POST['respondent_status']) ? $_POST['respondent_status'] : [];
  $respondent_reason_array = is_array($_POST['respondent_reason'] ?? null) ? $_POST['respondent_reason'] : [];

  // Build attendance lines listing complainant and respondents with their status
  $attendance_lines = [];
  $reason_items = [];

  // Determine Complaint_ID for this case so we can lookup names
  $complaintId = null;
  if ($cstmt = $conn->prepare("SELECT Complaint_ID FROM CASE_INFO WHERE Case_ID = ?")) {
    $cstmt->bind_param("i", $case_id);
    $cstmt->execute();
    $cres = bpamis_stmt_get_result($cstmt);
    if ($crow = $cres->fetch_assoc()) { $complaintId = $crow['Complaint_ID']; }
    $cstmt->close();
  }

  // Complainant name
  $complainant_name = 'Complainant';
  if (!empty($complaintId)) {
    $cn_stmt = $conn->prepare("SELECT COALESCE(CONCAT(res.First_Name,' ',res.Last_Name), CONCAT(ext.First_Name,' ',ext.Last_Name)) AS name
                               FROM COMPLAINT_INFO ci
                               LEFT JOIN RESIDENT_INFO res ON ci.Resident_ID = res.Resident_ID
                               LEFT JOIN external_complainant ext ON ci.External_Complainant_ID = ext.External_Complaint_ID
                               WHERE ci.Complaint_ID = ?");
    if ($cn_stmt) {
      $cn_stmt->bind_param("i", $complaintId);
      $cn_stmt->execute();
      $cnr = bpamis_stmt_get_result($cn_stmt);
      if ($cnrow = $cnr->fetch_assoc()) { $complainant_name = trim($cnrow['name']) ?: 'Complainant'; }
      $cn_stmt->close();
    }
  }

  // Add complainant line (store user-facing label 'Failure to Appear')
  $cstatus = $complainant_status ?? 'Present';
  $cstatus_label = (strtolower($cstatus) === 'unattended') ? 'Failure to Appear' : $cstatus;
  $attendance_lines[] = $complainant_name . ': ' . $cstatus_label;
  if (strtolower($cstatus) === 'unattended') {
    $reason_items[] = 'Complainant: ' . $complainant_name . ' - ' . ($complainant_reason !== '' ? $complainant_reason : 'No reason provided');
  }

  // Build respondents set from submitted statuses plus ensure main respondent is included, then lookup names
  $allRespIds = [];
  foreach ($respondent_status_array as $rid => $st) {
    $rid = (int)$rid; if ($rid > 0 && !in_array($rid, $allRespIds, true)) $allRespIds[] = $rid;
  }
  // Include main respondent id if present in COMPLAINT_INFO
  $mainRespId = null;
  if (!empty($complaintId)) {
    if ($mr = $conn->prepare("SELECT COALESCE(respondent_id, Respondent_ID) AS main_id FROM COMPLAINT_INFO WHERE Complaint_ID = ?")) {
      $mr->bind_param("i", $complaintId);
      $mr->execute();
      $mrs = bpamis_stmt_get_result($mr);
      if ($mrow = $mrs->fetch_assoc()) { $mainRespId = (int)($mrow['main_id'] ?? 0); }
      $mr->close();
    }
  }
  if (!empty($mainRespId) && !in_array($mainRespId, $allRespIds, true)) {
    array_unshift($allRespIds, $mainRespId);
  }

  // Lookup each respondent's name and add to attendance with proper label and reasons
  if (count($allRespIds)) {
    if ($nameStmt = $conn->prepare("SELECT CONCAT(COALESCE(First_Name,''),' ',COALESCE(Last_Name,'')) AS name FROM RESIDENT_INFO WHERE Resident_ID = ?")) {
      foreach ($allRespIds as $rid) {
        $nm = null;
        $ridInt = (int)$rid;
        $nameStmt->bind_param("i", $ridInt);
        $nameStmt->execute();
        $resn = bpamis_stmt_get_result($nameStmt);
        if ($nrow = $resn->fetch_assoc()) { $nm = trim($nrow['name']); }
        $rname = $nm !== '' ? $nm : ('Respondent ' . $ridInt);
        $rstat = $respondent_status_array[$ridInt] ?? 'Present';
        $rstat_label = (strtolower($rstat) === 'unattended') ? 'Failure to Appear' : $rstat;
        $attendance_lines[] = $rname . ': ' . $rstat_label;
        if (strtolower($rstat) === 'unattended') {
          $rreason = trim($respondent_reason_array[$ridInt] ?? '');
          $reason_items[] = 'Respondent: ' . $rname . ' - ' . ($rreason !== '' ? $rreason : 'No reason provided');
        }
      }
      $nameStmt->close();
    } else {
      // Fallback: no name statement, use ids
      foreach ($allRespIds as $rid) {
        $ridInt = (int)$rid;
        $rstat = $respondent_status_array[$ridInt] ?? 'Present';
        $rstat_label = (strtolower($rstat) === 'unattended') ? 'Failure to Appear' : $rstat;
        $attendance_lines[] = 'Respondent ' . $ridInt . ': ' . $rstat_label;
        if (strtolower($rstat) === 'unattended') {
          $rreason = trim($respondent_reason_array[$ridInt] ?? '');
          $reason_items[] = 'Respondent: Respondent ' . $ridInt . ' - ' . ($rreason !== '' ? $rreason : 'No reason provided');
        }
      }
    }
  }

  $attendance = implode(' | ', $attendance_lines);
  $reason_incompliance = count($reason_items) ? implode(' | ', $reason_items) : 'N/A';

  // Adjust SQL to include Hearing_End_Time
  // Check if Attendance and Reason_Incompliance columns exist
  $hasAttendanceCol = false; $hasReasonCol = false;
  if ($col = $conn->query("SHOW COLUMNS FROM MEETING_LOGS LIKE 'Attendance'")) { $hasAttendanceCol = $col->num_rows > 0; $col->close(); }
  if ($col = $conn->query("SHOW COLUMNS FROM MEETING_LOGS LIKE 'Reason_Incompliance'")) { $hasReasonCol = $col->num_rows > 0; $col->close(); }

  if ($hasAttendanceCol && $hasReasonCol) {
    $stmt = $conn->prepare("INSERT INTO MEETING_LOGS 
        (Case_ID, Hearing_Date, Hearing_Time, Hearing_End_Time, Hearing_Details, Complainant_Status, Respondent_Status, Attendance, Reason_Incompliance) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // i + 8 strings
    $stmt->bind_param("issssssss", $case_id, $hearing_date, $hearing_time, $hearing_end_time, $details, $complainant_status, $respondent_status, $attendance, $reason_incompliance);
  } else {
    $stmt = $conn->prepare("INSERT INTO MEETING_LOGS 
        (Case_ID, Hearing_Date, Hearing_Time, Hearing_End_Time, Hearing_Details, Complainant_Status, Respondent_Status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    // i + 6 strings
    $stmt->bind_param("issssss", $case_id, $hearing_date, $hearing_time, $hearing_end_time, $details, $complainant_status, $respondent_status);
  }

  $stmt->execute();
  $stmt->close();

  // ===== Mark the hearing schedule as done (hide from calendars) =====
  // Prefer updating by hearingID when available (more precise). Otherwise fall back to matching by datetime.
  $matching_hearing_id = 0;
  if (!empty($post_hearing_id)) {
    $updateSchedule = $conn->prepare("UPDATE schedule_list SET visible = 0 WHERE hearingID = ?");
    if ($updateSchedule) {
      $updateSchedule->bind_param("i", $post_hearing_id);
      $updateSchedule->execute();
      $matching_hearing_id = $post_hearing_id;
      $updateSchedule->close();
    }
  } else {
    $findStmt = $conn->prepare("SELECT hearingID FROM schedule_list WHERE Case_ID = ? AND HearingDateTime = ? LIMIT 1");
    if ($findStmt) {
      $findStmt->bind_param("is", $case_id, $hearing_datetime);
      $findStmt->execute();
      $fr = bpamis_stmt_get_result($findStmt);
      if ($r = $fr->fetch_assoc()) { $matching_hearing_id = (int)$r['hearingID']; }
      $findStmt->close();
    }
    $updateSchedule = $conn->prepare("UPDATE schedule_list SET visible = 0 WHERE Case_ID = ? AND HearingDateTime = ?");
    if ($updateSchedule) {
      $updateSchedule->bind_param("is", $case_id, $hearing_datetime);
      $updateSchedule->execute();
      $updateSchedule->close();
    }
  }

  // ===== Send notifications to all related parties =====
  $created_at = date('Y-m-d H:i:s');
  $notif_title = 'Hearing Completed';
  $notif_message = 'The hearing scheduled on ' . date('F d, Y g:i A', strtotime($hearing_datetime)) . ' has been completed and minutes have been recorded for the case (ID: ' . (int)$case_id . ').';
  $notif_type = 'Hearing';

  // Get case and complaint details for notifications
  $caseInfo = null;
  if ($caseStmt = $conn->prepare("SELECT ci.Case_ID, ci.case_original_id, co.Complaint_ID, co.Resident_ID, co.External_Complainant_ID, COALESCE(co.respondent_id, co.Respondent_ID) AS main_respondent FROM CASE_INFO ci JOIN COMPLAINT_INFO co ON ci.Complaint_ID = co.Complaint_ID WHERE ci.Case_ID = ?")) {
    $caseStmt->bind_param("i", $case_id);
    $caseStmt->execute();
    $caseResult = bpamis_stmt_get_result($caseStmt);
    if ($caseRow = $caseResult->fetch_assoc()) {
      $caseInfo = $caseRow;
    }
    $caseStmt->close();
  }

  if ($caseInfo) {
    // Notify complainant (resident or external)
    if (!empty($caseInfo['Resident_ID'])) {
      $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
      if ($notifStmt) {
        $notifStmt->bind_param("issss", $caseInfo['Resident_ID'], $notif_title, $notif_message, $notif_type, $created_at);
        $notifStmt->execute();
        $notifStmt->close();
      }
    } elseif (!empty($caseInfo['External_Complainant_ID'])) {
      $notifStmt = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
      if ($notifStmt) {
        $notifStmt->bind_param("issss", $caseInfo['External_Complainant_ID'], $notif_title, $notif_message, $notif_type, $created_at);
        $notifStmt->execute();
        $notifStmt->close();
      }
    }

    // Notify main respondent
    if (!empty($caseInfo['main_respondent'])) {
      $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
      if ($notifStmt) {
        $notifStmt->bind_param("issss", $caseInfo['main_respondent'], $notif_title, $notif_message, $notif_type, $created_at);
        $notifStmt->execute();
        $notifStmt->close();
      }
    }

    // Notify all other respondents
    if (!empty($caseInfo['Complaint_ID'])) {
      $respStmt = $conn->prepare("SELECT Respondent_ID FROM COMPLAINT_RESPONDENTS WHERE Complaint_ID = ?");
      if ($respStmt) {
        $respStmt->bind_param("i", $caseInfo['Complaint_ID']);
        $respStmt->execute();
        $respResult = bpamis_stmt_get_result($respStmt);
        while ($respRow = $respResult->fetch_assoc()) {
          $respId = $respRow['Respondent_ID'];
          // Avoid duplicate if this is the main respondent
          if ($respId != $caseInfo['main_respondent']) {
            $notifStmt = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
            if ($notifStmt) {
              $notifStmt->bind_param("issss", $respId, $notif_title, $notif_message, $notif_type, $created_at);
              $notifStmt->execute();
              $notifStmt->close();
            }
          }
        }
        $respStmt->close();
      }
    }

    // Notify Secretary, Captain, and Lupon Head
    $officials = ['Secretary', 'Captain', 'Lupon Head'];
    foreach ($officials as $position) {
      $officialStmt = $conn->prepare("SELECT official_id FROM barangay_officials WHERE position = ? LIMIT 1");
      if ($officialStmt) {
        $officialStmt->bind_param("s", $position);
        $officialStmt->execute();
        $officialResult = bpamis_stmt_get_result($officialStmt);
        if ($officialRow = $officialResult->fetch_assoc()) {
          $notifStmt = $conn->prepare("INSERT INTO notifications (official_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
          if ($notifStmt) {
            $notifStmt->bind_param("issss", $officialRow['official_id'], $notif_title, $notif_message, $notif_type, $created_at);
            $notifStmt->execute();
            $notifStmt->close();
          }
        }
        $officialStmt->close();
      }
    }

    // Notify assigned Lupon members (mediator/arbitrator)
    // Check across all case status-related tables
    $luponNames = [];
    
    // Get from mediation_info
    $luponStmt = $conn->prepare("SELECT Mediator_Name FROM mediation_info WHERE Case_ID = ?");
    if ($luponStmt) {
      $luponStmt->bind_param("i", $case_id);
      $luponStmt->execute();
      $luponResult = bpamis_stmt_get_result($luponStmt);
      $luponRow = $luponResult->fetch_assoc();
      if ($luponRow && !empty($luponRow['Mediator_Name'])) {
        $luponNames[] = $luponRow['Mediator_Name'];
      }
      $luponStmt->close();
    }

    // Get from conciliation
    $luponStmt = $conn->prepare("SELECT Mediator_Name FROM conciliation WHERE Case_ID = ?");
    if ($luponStmt) {
      $luponStmt->bind_param("i", $case_id);
      $luponStmt->execute();
      $luponResult = bpamis_stmt_get_result($luponStmt);
      $luponRow = $luponResult->fetch_assoc();
      if ($luponRow && !empty($luponRow['Mediator_Name'])) {
        $luponNames[] = $luponRow['Mediator_Name'];
      }
      $luponStmt->close();
    }

    // Get from resolution
    $luponStmt = $conn->prepare("SELECT Mediator_Name FROM resolution WHERE Case_ID = ?");
    if ($luponStmt) {
      $luponStmt->bind_param("i", $case_id);
      $luponStmt->execute();
      $luponResult = bpamis_stmt_get_result($luponStmt);
      $luponRow = $luponResult->fetch_assoc();
      if ($luponRow && !empty($luponRow['Mediator_Name'])) {
        $luponNames[] = $luponRow['Mediator_Name'];
      }
      $luponStmt->close();
    }

    // Get from settlement
    $luponStmt = $conn->prepare("SELECT Mediator_Name FROM settlement WHERE Case_ID = ?");
    if ($luponStmt) {
      $luponStmt->bind_param("i", $case_id);
      $luponStmt->execute();
      $luponResult = bpamis_stmt_get_result($luponStmt);
      $luponRow = $luponResult->fetch_assoc();
      if ($luponRow && !empty($luponRow['Mediator_Name'])) {
        $luponNames[] = $luponRow['Mediator_Name'];
      }
      $luponStmt->close();
    }

    // Get from arbitration
    $luponStmt = $conn->prepare("SELECT Mediator_Name FROM arbitration WHERE Case_ID = ?");
    if ($luponStmt) {
      $luponStmt->bind_param("i", $case_id);
      $luponStmt->execute();
      $luponResult = bpamis_stmt_get_result($luponStmt);
      $luponRow = $luponResult->fetch_assoc();
      if ($luponRow && !empty($luponRow['Mediator_Name'])) {
        $luponNames[] = $luponRow['Mediator_Name'];
      }
      $luponStmt->close();
    }

    // Remove duplicates and notify each unique lupon member
    $luponNames = array_unique($luponNames);
    foreach ($luponNames as $luponName) {
      // Find the lupon official_id by name
      $luponOfficialStmt = $conn->prepare("SELECT official_id FROM barangay_officials WHERE name LIKE ? LIMIT 1");
      if ($luponOfficialStmt) {
        $searchName = '%' . $luponName . '%';
        $luponOfficialStmt->bind_param("s", $searchName);
        $luponOfficialStmt->execute();
        $luponOfficialResult = bpamis_stmt_get_result($luponOfficialStmt);
        if ($luponOfficialRow = $luponOfficialResult->fetch_assoc()) {
          $notifStmt = $conn->prepare("INSERT INTO notifications (lupon_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
          if ($notifStmt) {
            $notifStmt->bind_param("issss", $luponOfficialRow['official_id'], $notif_title, $notif_message, $notif_type, $created_at);
            $notifStmt->execute();
            $notifStmt->close();
          }
        }
        $luponOfficialStmt->close();
      }
    }
  }

  // Redirect back to the meeting log page for the same case and hearing (if known)
  $redir = "meeting_cases_log.php?id=" . $case_id;
  if (!empty($matching_hearing_id)) { $redir .= "&hearing_id=" . $matching_hearing_id; }
  header("Location: " . $redir);
  exit;
}


$latest_date = '';
$latest_time = '';
$log_exists = false;

// If a specific hearing_id is provided, load its hearing datetime exactly. Otherwise, fall back to latest schedule for the case.
if (!empty($hearing_id)) {
  $hstmt = $conn->prepare("SELECT HearingDateTime, Case_ID FROM schedule_list WHERE hearingID = ? LIMIT 1");
  if ($hstmt) {
    $hstmt->bind_param("i", $hearing_id);
    $hstmt->execute();
    $hres = bpamis_stmt_get_result($hstmt);
    if ($hrow = $hres->fetch_assoc()) {
      $dt = new DateTime($hrow['HearingDateTime']);
      $latest_date = $dt->format('Y-m-d');
      $latest_time = $dt->format('H:i');
      // Ensure case_id is set from the schedule if it wasn't provided
      if (empty($case_id)) { $case_id = (int)$hrow['Case_ID']; }
      // Check if a meeting log already exists for this specific hearing date and time
      $check_log = $conn->prepare("SELECT Log_ID FROM MEETING_LOGS WHERE Case_ID = ? AND Hearing_Date = ? AND Hearing_Time = ?");
      if ($check_log) {
        $check_log->bind_param("iss", $case_id, $latest_date, $latest_time);
        $check_log->execute();
        $log_result = bpamis_stmt_get_result($check_log);
        if ($log_result->num_rows > 0) { $log_exists = true; }
        $check_log->close();
      }
    }
    $hstmt->close();
  }
} else {
  $date_stmt = $conn->prepare("SELECT HearingDateTime 
                             FROM schedule_list 
                             WHERE Case_ID = ? 
                             ORDER BY HearingDateTime DESC 
                             LIMIT 1");
  $date_stmt->bind_param("i", $case_id);
  $date_stmt->execute();
  $date_result = bpamis_stmt_get_result($date_stmt);
  if ($row = $date_result->fetch_assoc()) {
    $dt = new DateTime($row['HearingDateTime']);
    $latest_date = $dt->format('Y-m-d'); // For <input type="date">
    $latest_time = $dt->format('H:i');   // For <input type="time">
    
    // Check if a meeting log already exists for this hearing date and time
    $check_log = $conn->prepare("SELECT Log_ID FROM MEETING_LOGS WHERE Case_ID = ? AND Hearing_Date = ? AND Hearing_Time = ?");
    if ($check_log) {
      $check_log->bind_param("iss", $case_id, $latest_date, $latest_time);
      $check_log->execute();
      $log_result = bpamis_stmt_get_result($check_log);
      if ($log_result->num_rows > 0) {
        $log_exists = true;
      }
      $check_log->close();
    }
  }
  $date_stmt->close();
}

$caseDetails = null;
$sql = "
SELECT 
    cs.Case_ID,
    cs.case_original_id,
    cs.Case_Status,
    ci.Complaint_ID,
    ci.Complaint_Title,
    ci.Date_Filed,
  COALESCE(ci.respondent_id, ci.Respondent_ID) AS main_respondent,
    CONCAT(
        COALESCE(res_com.First_Name, ext_com.First_Name, ''),
        ' ',
        COALESCE(res_com.Last_Name, ext_com.Last_Name, '')
    ) AS complainant_name,
    GROUP_CONCAT(
        CONCAT(
            COALESCE(res_res.First_Name, ''),
            ' ',
            COALESCE(res_res.Last_Name, '')
        ) SEPARATOR ', '
    ) AS respondent_names
FROM CASE_INFO cs
LEFT JOIN COMPLAINT_INFO ci 
    ON cs.Complaint_ID = ci.Complaint_ID
LEFT JOIN RESIDENT_INFO res_com 
    ON ci.Resident_ID = res_com.Resident_ID
LEFT JOIN external_complainant ext_com 
    ON ci.External_Complainant_ID = ext_com.External_Complaint_ID
LEFT JOIN COMPLAINT_RESPONDENTS cr
    ON ci.Complaint_ID = cr.Complaint_ID
LEFT JOIN RESIDENT_INFO res_res
    ON cr.Respondent_ID = res_res.Resident_ID
WHERE cs.Case_ID = ?
GROUP BY cs.Case_ID
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $case_id);

$stmt->execute();
$result = bpamis_stmt_get_result($stmt);
if ($row = $result->fetch_assoc()) {
  $caseDetails = $row;
}

// Get Lupon Tagapamayapa / Mediator based on case status
$lupon_sql = "
    SELECT 
        CASE 
            WHEN cs.Case_Status = 'Mediation' THEN mi.Mediator_Name
            WHEN cs.Case_Status = 'Conciliation' THEN ci.Mediator_Name
            WHEN cs.Case_Status = 'Resolution' THEN ri.Mediator_Name
            WHEN cs.Case_Status = 'Settlement' THEN si.Mediator_Name
            WHEN cs.Case_Status = 'Arbitration' THEN ai.Mediator_Name
            ELSE NULL
        END AS lupon_tagapamayapa
    FROM CASE_INFO cs
    LEFT JOIN mediation_info mi 
        ON cs.Case_ID = mi.Case_ID
    LEFT JOIN conciliation ci
        ON cs.Case_ID = ci.Case_ID
    LEFT JOIN resolution ri 
        ON cs.Case_ID = ri.Case_ID
    LEFT JOIN settlement si 
        ON cs.Case_ID = si.Case_ID
    LEFT JOIN arbitration ai
        ON cs.Case_ID = ai.Case_ID
    WHERE cs.Case_ID = ?
";

$lupon_stmt = $conn->prepare($lupon_sql);
$lupon_stmt->bind_param("i", $case_id);
$lupon_stmt->execute();
$lupon_result = bpamis_stmt_get_result($lupon_stmt);
$lupon_name = null;
if ($row = $lupon_result->fetch_assoc()) {
  $lupon_name = $row['lupon_tagapamayapa'] ?? null;
}
$lupon_stmt->close();

// If no lupon found from above tables, try to get from assigned case info
if (empty($lupon_name)) {
  $alt_sql = "
    SELECT 
      COALESCE(mi.Mediator_Name, ci.Mediator_Name, ri.Mediator_Name, si.Mediator_Name, ai.Mediator_Name) AS lupon_name
    FROM CASE_INFO cs
    LEFT JOIN mediation_info mi ON cs.Case_ID = mi.Case_ID
    LEFT JOIN conciliation ci ON cs.Case_ID = ci.Case_ID
    LEFT JOIN resolution ri ON cs.Case_ID = ri.Case_ID
    LEFT JOIN settlement si ON cs.Case_ID = si.Case_ID
    LEFT JOIN arbitration ai ON cs.Case_ID = ai.Case_ID
    WHERE cs.Case_ID = ?
    LIMIT 1
  ";
  $alt_stmt = $conn->prepare($alt_sql);
  $alt_stmt->bind_param("i", $case_id);
  $alt_stmt->execute();
  $alt_result = bpamis_stmt_get_result($alt_stmt);
  if ($alt_row = $alt_result->fetch_assoc()) {
    $lupon_name = $alt_row['lupon_name'] ?? null;
  }
  $alt_stmt->close();
}

// Final fallback
if (empty($lupon_name)) {
  $lupon_name = 'Not Yet Assigned';
}

$sql_respondents = "
    SELECT 
        r.Respondent_ID,
        CONCAT(COALESCE(res.First_Name, ''), ' ', COALESCE(res.Last_Name, '')) AS name
    FROM COMPLAINT_RESPONDENTS r
    LEFT JOIN RESIDENT_INFO res ON r.Respondent_ID = res.Resident_ID
    WHERE r.Complaint_ID = ?
";
$stmt_res = $conn->prepare($sql_respondents);
$stmt_res->bind_param("i", $caseDetails['Complaint_ID']);
$stmt_res->execute();
$res_result = bpamis_stmt_get_result($stmt_res);

$respondents = [];
while ($row = $res_result->fetch_assoc()) {
  $respondents[] = $row;
}
$stmt_res->close();

// Ensure the main respondent from COMPLAINT_INFO is present and placed first
if (!empty($caseDetails['main_respondent'])) {
  $mainId = (int)$caseDetails['main_respondent'];
  $found = false;
  foreach ($respondents as $r) {
    if ((int)$r['Respondent_ID'] === $mainId) { $found = true; break; }
  }
  if ($found) {
    // move the main respondent to the front
    usort($respondents, function($a, $b) use ($mainId) {
      if ((int)$a['Respondent_ID'] === $mainId) return -1;
      if ((int)$b['Respondent_ID'] === $mainId) return 1;
      return 0;
    });
  } else {
    // fetch main respondent's name and prepend
      // try resident lookup first
      $mainName = null;
      if ($m_stmt = $conn->prepare("SELECT CONCAT(COALESCE(First_Name,''),' ',COALESCE(Last_Name,'')) AS name FROM RESIDENT_INFO WHERE Resident_ID = ?")) {
        $m_stmt->bind_param("i", $mainId);
        $m_stmt->execute();
        $mres = bpamis_stmt_get_result($m_stmt);
        if ($mrow = $mres->fetch_assoc()) { $mainName = trim($mrow['name']); }
        $m_stmt->close();
      }
      // Fallback: if we couldn't find a resident name, still show the respondent using a fallback label
      if (empty($mainName)) { $mainName = 'Respondent ' . $mainId; }
      array_unshift($respondents, ['Respondent_ID' => $mainId, 'name' => $mainName]);
  }
}

$stmt->close();

// Fetch complaint details text and attachments for this case's complaint
$complaint_details_text = null;
$complaint_attachments_raw = null;
if (!empty($caseDetails['Complaint_ID'])) {
  // Complaint details
  if ($ci_stmt = $conn->prepare("SELECT Complaint_Details FROM COMPLAINT_INFO WHERE Complaint_ID = ?")) {
    $ci_stmt->bind_param("i", $caseDetails['Complaint_ID']);
    $ci_stmt->execute();
    $ci_res = bpamis_stmt_get_result($ci_stmt);
    if ($row = $ci_res->fetch_assoc()) {
      $complaint_details_text = $row['Complaint_Details'] ?? null;
    }
    $ci_stmt->close();
  }
  // Attachments (optional column)
  if ($res = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Attachment_Path'")) {
    if ($res->num_rows > 0) {
      if ($att_stmt = $conn->prepare("SELECT Attachment_Path FROM COMPLAINT_INFO WHERE Complaint_ID = ?")) {
        $att_stmt->bind_param("i", $caseDetails['Complaint_ID']);
        $att_stmt->execute();
        $att_res = bpamis_stmt_get_result($att_stmt);
        if ($ar = $att_res->fetch_assoc()) {
          $complaint_attachments_raw = $ar['Attachment_Path'] ?? null; // semicolon-separated
        }
        $att_stmt->close();
      }
    }
    $res->close();
  }
}

// Fetch complaint details and attachments for the case's complaint
$complaint_details_text = null;
$complaint_attachments_raw = null;
if ($caseDetails && !empty($caseDetails['Complaint_ID'])) {
  // Complaint details
  if ($ci_stmt = $conn->prepare("SELECT Complaint_Details FROM COMPLAINT_INFO WHERE Complaint_ID = ?")) {
    $ci_stmt->bind_param("i", $caseDetails['Complaint_ID']);
    $ci_stmt->execute();
    $ci_res = bpamis_stmt_get_result($ci_stmt);
    if ($row = $ci_res->fetch_assoc()) {
      $complaint_details_text = $row['Complaint_Details'] ?? null;
    }
    $ci_stmt->close();
  }
  // Attachments (optional column)
  if ($res = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'Attachment_Path'")) {
    if ($res->num_rows > 0) {
      if ($att_stmt = $conn->prepare("SELECT Attachment_Path FROM COMPLAINT_INFO WHERE Complaint_ID = ?")) {
        $att_stmt->bind_param("i", $caseDetails['Complaint_ID']);
        $att_stmt->execute();
        $att_res = bpamis_stmt_get_result($att_stmt);
        if ($ar = $att_res->fetch_assoc()) {
          $complaint_attachments_raw = $ar['Attachment_Path'] ?? null; // semicolon-separated relative paths like 'uploads/..'
        }
        $att_stmt->close();
      }
    }
    $res->close();
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Case Hearing Logs</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { primary: { 50: '#f0f7ff', 100: '#e0effe', 200: '#bae2fd', 300: '#7cccfd', 400: '#36b3f9', 500: '#0c9ced', 600: '#0281d4', 700: '#026aad', 800: '#065a8f', 900: '#0a4b76' } }, boxShadow: { glow: '0 0 0 1px rgba(12,156,237,0.10), 0 4px 18px -2px rgba(6,90,143,0.20)' }, keyframes: { fadeIn: { '0%': { opacity: 0, transform: 'translateY(4px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } }, pulseSoft: { '0%,100%': { opacity: 1 }, '50%': { opacity: .55 } } }, animation: { 'fade-in': 'fadeIn .5s ease-out', 'pulse-soft': 'pulseSoft 3s ease-in-out infinite' } } } };
  </script>
  <!-- Tailwind safelist for dynamic classes used in JS: bg-green-50 text-green-700 border-green-200 bg-amber-50 text-amber-700 border-amber-200 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .bg-orbs:before,
    .bg-orbs:after {
      content: "";
      position: absolute;
      border-radius: 9999px;
      filter: blur(70px);
      opacity: .35;
    }

    .bg-orbs:before {
      width: 480px;
      height: 480px;
      background: linear-gradient(135deg, #7cccfd, #0c9ced);
      top: -160px;
      left: -140px;
    }

    .bg-orbs:after {
      width: 420px;
      height: 420px;
      background: linear-gradient(135deg, #bae2fd, #7cccfd);
      bottom: -140px;
      right: -120px;
    }
    .glass {
      background: linear-gradient(145deg, rgba(255, 255, 255, .88), rgba(255, 255, 255, .65));
      backdrop-filter: blur(14px) saturate(140%);
      -webkit-backdrop-filter: blur(14px) saturate(140%);
    }

    .input-base {
      width: 100%;
      border-radius: 0.5rem;
      border: 1px solid rgba(209, 213, 219, .7);

      background: rgba(255, 255, 255, .7);
      padding: .625rem .75rem;
      font-size: .875rem;
      transition: .2s;
    }

    .input-base:not(textarea) {
      height: 44px;
      line-height: 1.2;
    }

    .input-base:focus {
      outline: none;
      background: #fff;
      border-color: #36b3f9;
      box-shadow: 0 0 0 4px rgba(12, 156, 237, .25);
    }

    .field-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      margin-bottom: 4px;
      display: flex;
      gap: 4px;
      align-items: center;
      color: #4b5563;
    }
    /* Compact mobile tweaks for meeting logs */
    @media (max-width: 640px) {
      .hearing-item { padding: .6rem !important; }
      .hearing-item p { font-size: 13px !important; }
      .glass.p-8 { padding: .75rem !important; }
      .input-base { padding: .5rem .6rem !important; font-size: .85rem !important; }
      #prevHearingsList { max-height: 360px !important; }
      #hearingDetailsPanel .p-5 { padding: .75rem !important; }
    }
  </style>
</head>

<body
  class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
  <?php include '../includes/barangay_official_sec_nav.php'; ?>

  <!-- Page Header (premium glass style) -->
  <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 mt-8 animate-fade-in">
    <div
      class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
      <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
      <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
          <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i
                class="fa fa-gavel text-lg"></i></span>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Edit Minutes of
              the Meeting</span>
          </h1>
          <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Record and view minutes of barangay meetings.
          </p>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-500">
          <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i
              class="fa fa-shield-halved text-primary-500"></i> Secure</div>
          <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i
              class="fa fa-clock text-primary-500"></i> Quick Logging</div>
        </div>
      </div>
    </div>
  </header>

  <div class="container mx-auto px-4 mt-8 pb-16">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-[1600px] mx-auto">

      <!-- LEFT: Log Hearing Notes Form -->
      <div class="glass rounded-2xl border border-white/60 ring-1 ring-primary-100/50 shadow-glow p-8 flex flex-col">

        <!-- Header with title + button side by side -->
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-3">
          
          <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i
                class="fa fa-pen text-lg"></i></span>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Log Hearing Notes</span>
        </h3>
        <?php if ($caseDetails): ?>
          <a href="view_case_details.php?id=<?= urlencode($caseDetails['Case_ID']) ?>"
            class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm">
            View Case Details
          </a>
        <?php endif; ?>
      </div>

      <?php if ($caseDetails): ?>
        <?php if ($log_exists): ?>
          <!-- Alert: Meeting log already submitted -->
          <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-xl p-4 mb-6 shadow-sm">
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0">
                <i class="fa-solid fa-circle-check text-green-600 text-2xl"></i>
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-green-800 mb-1">Meeting Log Already Submitted</h4>
                <p class="text-sm text-green-700">The meeting log for this hearing has been successfully recorded. The form has been disabled to prevent duplicate entries.</p>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
          <label class="field-label mb-1"><i class="fa fa-user-tie text-primary-500"></i> Lupon Tagapamayapa / Mediator</label>
          <p class="text-gray-800"><?= htmlspecialchars($lupon_name) ?></p>
        </div>

        <form method="POST" class="space-y-4">
          <!-- preserve hearing id so POST can update the exact schedule row -->
          <input type="hidden" name="hearing_id" value="<?= htmlspecialchars(isset($hearing_id) ? $hearing_id : '') ?>">
          <fieldset <?= $log_exists ? 'disabled' : '' ?> class="<?= $log_exists ? 'opacity-60 pointer-events-none' : '' ?>">
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
            <label class="field-label mb-2"><i class="fa fa-user text-primary-500"></i> Complainant</label>
            <div class="flex items-center justify-between">
              <p class="mr-4"><?= htmlspecialchars($caseDetails['complainant_name'] ?? 'N/A') ?></p>
              <select name="complainant_status" class="input-base max-w-[200px]" id="complainant_status_select">
                <option value="Present">Present</option>
                <option value="Unattended">Failure to Appear</option>
              </select>
            </div>
            <div id="complainant_reason_group" class="mt-3 hidden">
              <label class="field-label"><i class="fa-solid fa-circle-exclamation text-primary-500"></i> Reason of non-compliance</label>
              <input type="text" name="complainant_reason" id="complainant_reason_input" class="input-base" placeholder="Enter reason for non-compliance" />
            </div>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
            <label class="field-label mb-2"><i class="fa fa-users text-primary-500"></i> Respondent/s</label>

            <?php foreach ($respondents as $res): ?>
              <div class="mb-3">
                <div class="flex items-center justify-between">
                  <p class="mr-4"><?= htmlspecialchars($res['name']) ?></p>
                  <select name="respondent_status[<?= $res['Respondent_ID'] ?>]" class="input-base max-w-[200px] respondent-status-select" data-respondent-id="<?= $res['Respondent_ID'] ?>">
                    <option value="Present">Present</option>
                    <option value="Unattended">Failure to Appear</option>
                  </select>
                </div>
                <div id="respondent_reason_group_<?= $res['Respondent_ID'] ?>" class="mt-2 hidden">
                  <label class="field-label"><i class="fa-solid fa-circle-exclamation text-primary-500"></i> Reason of non-compliance</label>
                  <input type="text" name="respondent_reason[<?= $res['Respondent_ID'] ?>]" id="respondent_reason_input_<?= $res['Respondent_ID'] ?>" class="input-base" placeholder="Enter reason for non-compliance" />
                </div>
              </div>
            <?php endforeach; ?>
          </div>



          <div>
            <label class="field-label"><i class="fa fa-calendar-day"></i> Hearing Date</label>
            <input type="date" name="hearing_date" required readonly value="<?= htmlspecialchars($latest_date); ?>"
              class="input-base bg-gray-100 cursor-not-allowed">
          </div>

          <div class="flex space-x-4">
            <div class="flex-1">
              <label class="field-label" for="hearing_time"><i class="fa fa-clock"></i> Hearing Start Time</label>
              <input type="time" id="hearing_time" name="hearing_time" required
                value="<?= htmlspecialchars($latest_time); ?>" class="input-base">
            </div>
            <div class="flex-1">
              <label class="field-label" for="hearing_end_time"><i class="fa fa-clock"></i> Hearing End Time</label>
              <input type="time" id="hearing_end_time" name="hearing_end_time" value="" class="input-base">
            </div>
          </div>



          <div>
            <label class="field-label"><i class="fa fa-align-left"></i> Details</label>
            <textarea name="details" rows="5" required class="input-base resize-y"></textarea>
          </div>

          <div class="flex justify-end">
            <button type="submit"
              class="bg-primary-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-primary-700 transition <?= $log_exists ? 'cursor-not-allowed' : '' ?>"
              <?= $log_exists ? 'disabled' : '' ?>>
              <i class="fa fa-save"></i> <?= $log_exists ? 'Log Already Submitted' : 'Save Hearing' ?>
            </button>
          </div>
          </fieldset>
        </form>

        <script>
          // Toggle reason fields based on status selections
          document.addEventListener('DOMContentLoaded', function(){
            const compSel = document.getElementById('complainant_status_select');
            const compGroup = document.getElementById('complainant_reason_group');
            const compInp = document.getElementById('complainant_reason_input');
            function syncComp(){
              if (!compSel) return;
              if (compSel.value === 'Unattended') {
                compGroup.classList.remove('hidden');
                if (compInp) compInp.required = true;
              } else {
                compGroup.classList.add('hidden');
                if (compInp) { compInp.required = false; compInp.value = ''; }
              }
            }
            if (compSel) { compSel.addEventListener('change', syncComp); syncComp(); }

            document.querySelectorAll('select.respondent-status-select').forEach(function(sel){
              const rid = sel.getAttribute('data-respondent-id');
              const group = document.getElementById('respondent_reason_group_' + rid);
              const inp = document.getElementById('respondent_reason_input_' + rid);
              function sync(){
                if (sel.value === 'Unattended') {
                  group && group.classList.remove('hidden');
                  if (inp) inp.required = true;
                } else {
                  group && group.classList.add('hidden');
                  if (inp) { inp.required = false; inp.value=''; }
                }
              }
              sel.addEventListener('change', sync);
              sync();
            });
          });
        </script>


      <?php endif; ?>



    </div>
    
    <!-- RIGHT: Previous Hearings & Details -->
    <div class="flex flex-col gap-8">
      <!-- Case Information Card -->
      <?php if ($caseDetails): ?>
        <div class="glass rounded-2xl border border-white/60 ring-1 ring-primary-100/50 shadow-glow p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
              <label class="field-label mb-1"><i class="fa fa-hashtag text-primary-500"></i> Case Original ID</label>
              <p class="text-gray-800 font-semibold"><?= htmlspecialchars($caseDetails['case_original_id'] ?? 'N/A') ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
              <label class="field-label mb-1"><i class="fa fa-info-circle text-primary-500"></i> Case Status</label>
              <p class="text-gray-800 font-semibold"><?= htmlspecialchars($caseDetails['Case_Status'] ?? 'N/A') ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- Previous Hearings List -->
      <div
        class="glass rounded-2xl border border-white/60 ring-1 ring-primary-100/50 shadow-glow p-8 flex flex-col">
        <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center gap-3">
          <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i
                  class="fa fa-history text-lg"></i></span>
              <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Previous Hearing</span>
        </h3>
        <!-- Filter / Search for previous hearings (compact, similar to meeting_log filters) -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
          <input id="hearingSearch" type="search" placeholder="Search hearings..." class="input-base pl-10 pr-3 py-2 rounded-xl border border-gray-200/80 bg-white/70 placeholder:text-gray-400 text-sm w-full md:w-1/2" />
          <select id="filterMonth" class="input-base rounded-xl border border-gray-200 bg-white/70 text-sm max-w-[140px]">
            <option value="All">All Months</option>
            <?php for ($m = 1; $m <= 12; $m++): $monthName = date("F", mktime(0, 0, 0, $m, 1)); ?>
              <option value="<?= $m ?>"><?= $monthName ?></option>
            <?php endfor; ?>
          </select>
          <select id="filterYear" class="input-base rounded-xl border border-gray-200 bg-white/70 text-sm max-w-[120px]"></select>
          <button id="clearFilters" class="px-3 py-2 rounded-xl bg-white border border-gray-100 text-sm text-gray-600">Clear</button>
        </div>

        <!-- Scrollable previous hearings list -->
        <div id="prevHearingsList" class="max-h-[500px] overflow-y-auto pr-2">
        <?php
        // Fetch and display hearing details
    $stmt = $conn->prepare("SELECT
      m.Hearing_Date,
      m.Hearing_Time,
      m.Hearing_End_Time,
      m.Log_ID AS log_id,
      m.Attendance,
      m.Reason_Incompliance,
      m.Complainant_Status,
      m.Respondent_Status,
      m.Hearing_Details,
      c.Case_ID,
      sl.hearingTitle AS Hearing_Title,
      sl.hearingID AS hearingID,
      CONCAT(
        COALESCE(res_com.First_Name, ext_com.First_Name, ''),
        ' ',
        COALESCE(res_com.Last_Name, ext_com.Last_Name, '')
      ) AS complainant_name,
  COALESCE(ci.respondent_id, ci.Respondent_ID) AS main_respondent,
      CONCAT(COALESCE(main_res.First_Name,''),' ',COALESCE(main_res.Last_Name,'')) AS main_respondent_name,
      GROUP_CONCAT(
        CONCAT(
          COALESCE(res_res.First_Name, ''),
          ' ',
          COALESCE(res_res.Last_Name, '')
        ) ORDER BY cr.Respondent_ID SEPARATOR '||' 
      ) AS respondent_names_concat,
      GROUP_CONCAT(
        cr.Respondent_ID ORDER BY cr.Respondent_ID SEPARATOR ','
      ) AS respondent_ids_concat
    FROM MEETING_LOGS m
    JOIN CASE_INFO c ON m.Case_ID = c.Case_ID
    LEFT JOIN schedule_list sl ON sl.Case_ID = m.Case_ID AND DATE(sl.HearingDateTime) = m.Hearing_Date AND TIME(sl.HearingDateTime) = m.Hearing_Time
    LEFT JOIN COMPLAINT_INFO ci ON c.Complaint_ID = ci.Complaint_ID
    LEFT JOIN RESIDENT_INFO res_com ON ci.Resident_ID = res_com.Resident_ID
    LEFT JOIN external_complainant ext_com ON ci.External_Complainant_ID = ext_com.External_Complaint_ID
    LEFT JOIN COMPLAINT_RESPONDENTS cr ON ci.Complaint_ID = cr.Complaint_ID
    LEFT JOIN RESIDENT_INFO res_res ON cr.Respondent_ID = res_res.Resident_ID
    LEFT JOIN RESIDENT_INFO main_res ON COALESCE(ci.respondent_id, ci.Respondent_ID) = main_res.Resident_ID
    WHERE m.Case_ID = ?
  GROUP BY m.Log_ID
  -- Ensure most recently submitted logs appear first: order by hearing date/time and insertion (Log_ID) desc
  ORDER BY m.Hearing_Date DESC, m.Hearing_Time DESC, m.Log_ID DESC

    ");
        $stmt->bind_param("i", $case_id);
        $stmt->execute();
        $result = bpamis_stmt_get_result($stmt);

      if ($result->num_rows > 0) {
          echo '<ol class="relative border-l-2 border-primary-200 ml-2">';
          while ($log = $result->fetch_assoc()) {
            $startTime = date("g:i A", strtotime($log['Hearing_Time']));
            if (!empty($log['Hearing_End_Time'])) {
              $endTime = date("g:i A", strtotime($log['Hearing_End_Time']));
              $formattedTime = $startTime . " - " . $endTime;
            } else {
              $formattedTime = $startTime;
            }

                      echo '<li class="mb-8 ml-6">';
                      echo '<span class="absolute -left-3 flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 text-white ring-4 ring-white shadow-md"><i class="fa fa-gavel text-[10px]"></i></span>';

                      // Build structured respondent name/status lists for the details panel
                      $data_attendance_attr = isset($log['Attendance']) ? $log['Attendance'] : '';
                      $data_reason_attr = (isset($log['Reason_Incompliance']) && trim($log['Reason_Incompliance']) !== '') ? $log['Reason_Incompliance'] : 'N/A';

                      // parse respondent names (separator '||') and ids (comma)
                      $respNamesRaw = $log['respondent_names_concat'] ?? '';
                      $respIdsRaw = $log['respondent_ids_concat'] ?? '';
                      $respNames = [];
                      if ($respNamesRaw !== '') {
                        $parts = explode('||', $respNamesRaw);
                        foreach ($parts as $p) { $p = trim($p); if ($p !== '') $respNames[] = $p; }
                      }
                      $respIds = [];
                      if ($respIdsRaw !== '') { $respIds = array_map('trim', explode(',', $respIdsRaw)); }

                      // statuses saved in MEETING_LOGS.Respondent_Status (comma-separated in save order)
                      $respStatuses = [];
                      if (!empty($log['Respondent_Status'])) {
                        $respStatuses = preg_split('/\s*,\s*/', $log['Respondent_Status']);
                      }

                      $namesOrdered = [];
                      $statusesOrdered = [];

                      $mainIdLog = isset($log['main_respondent']) ? (int)$log['main_respondent'] : 0;
                      $mainNameLog = trim($log['main_respondent_name'] ?? '');
                      if ($mainIdLog) {
                        $foundMain = false;
                        foreach ($respIds as $iid) { if ((int)$iid === $mainIdLog) { $foundMain = true; break; } }
                        if (!$foundMain) {
                          $namesOrdered[] = ($mainNameLog !== '') ? $mainNameLog : ('Respondent ' . $mainIdLog);
                          $statusesOrdered[] = 'Present';
                        }
                      }

                      for ($i = 0; $i < count($respNames); $i++) {
                        $nm = $respNames[$i];
                        $st = isset($respStatuses[$i]) ? $respStatuses[$i] : 'Present';
                        $st_label = (strtolower($st) === 'unattended') ? 'Failure to Appear' : $st;
                        $namesOrdered[] = $nm;
                        $statusesOrdered[] = $st_label;
                      }

                      $data_respondents_attr = count($namesOrdered) ? implode(', ', $namesOrdered) : 'N/A';
                      $data_respondentstatus_attr = count($statusesOrdered) ? implode(', ', $statusesOrdered) : 'N/A';

                      // Determine main respondent status (match by id against status list if available)
                      $mainStatusRaw = null;
                      if ($mainIdLog && count($respIds)) {
                        for ($mi = 0; $mi < count($respIds); $mi++) {
                          if ((int)$respIds[$mi] === $mainIdLog) { $mainStatusRaw = $respStatuses[$mi] ?? 'Present'; break; }
                        }
                      }
                      $mainStatusLabel = $mainStatusRaw ? ((strtolower($mainStatusRaw) === 'unattended') ? 'Failure to Appear' : $mainStatusRaw) : 'Present';
                      $mainNameEffective = ($mainNameLog !== '') ? $mainNameLog : ($mainIdLog ? ('Respondent ' . $mainIdLog) : '');

        echo '<div class="group bg-white/90 hover:bg-white border border-primary-100 rounded-xl p-4 shadow-sm cursor-pointer hearing-item transition-all duration-200 hover:shadow-glow" '
                         . 'data-lupon="' . htmlspecialchars($lupon_name ?? 'N/A') . '" '
                         . 'data-complaintdetails="' . htmlspecialchars($complaint_details_text ?? '') . '" '
                         . 'data-attachments="' . htmlspecialchars($complaint_attachments_raw ?? '') . '" '
                         . 'data-attendance="' . htmlspecialchars($data_attendance_attr) . '" '
                         . 'data-reason="' . htmlspecialchars($data_reason_attr) . '" '
                         . 'data-date="' . htmlspecialchars($log['Hearing_Date'] ?? '') . '" '
                         . 'data-time="' . $formattedTime . '" '
                         . 'data-details="' . htmlspecialchars($log['Hearing_Details']) . '" '
                    . 'data-complainant="' . htmlspecialchars($log['complainant_name'] ?? 'N/A') . '" '
                    . 'data-complainantstatus="' . htmlspecialchars((isset($log['Complainant_Status']) && strtolower($log['Complainant_Status']) === 'unattended') ? 'Failure to Appear' : ($log['Complainant_Status'] ?? 'N/A')) . '" '
         . 'data-mainrespondent="' . htmlspecialchars($mainNameEffective) . '" '
         . 'data-mainrespondentstatus="' . htmlspecialchars($mainStatusLabel) . '" '
                         . 'data-respondents="' . htmlspecialchars($data_respondents_attr) . '" '
                         . 'data-respondentstatus="' . htmlspecialchars($data_respondentstatus_attr) . '" '
                         . 'data-caseid="' . (int)$log['Case_ID'] . '" '
                         . 'data-hearingid="' . htmlspecialchars($log['hearingID'] ?? '') . '" '
                         . 'data-logid="' . htmlspecialchars($log['log_id'] ?? '') . '">';
            echo '<p class="text-sm text-primary-700 font-semibold mb-1">'
               . '<i class="fa fa-calendar-day mr-1"></i> ' . htmlspecialchars($log['Hearing_Date'])
               . ' <span class="mx-2">|</span> '
               . '<i class="fa fa-clock mr-1"></i>' . $formattedTime
               . '</p>';
        // Replace notes with hearing title and case number
  $hearingTitle = !empty($log['Hearing_Title']) ? $log['Hearing_Title'] : 'Hearing';
        echo '<p class="text-gray-800 mt-1">'
          . '<span class="font-semibold">' . htmlspecialchars($hearingTitle) . '</span>'
          . ' <span class="text-gray-500">&middot; Case #' . htmlspecialchars($log['Case_ID']) . '</span>'
          . '</p>';
            echo '</div>';
            echo '</li>';
          }
          echo '</ol>';
        } else {
          echo '<p class="text-gray-500">No hearings logged yet.</p>';
        }
        $stmt->close();
        ?>
      </div>
    </div>
    
    <!-- Hearing Details Panel -->
    <div class="glass rounded-2xl border border-white/60 ring-1 ring-primary-100/50 shadow-glow p-8 flex flex-col">
      <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center gap-3">
        <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i
                class="fa fa-info text-lg"></i></span>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Hearing Details</span>
      </h3>
      <div id="hearingDetailsPanel" class="relative bg-white rounded-2xl border border-primary-100 ring-1 ring-primary-100 shadow-md overflow-hidden">
          <div class="sticky top-0 z-10 -mx-px -mt-px rounded-t-2xl bg-gradient-to-r from-primary-50 to-primary-100 border-b border-primary-100 px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2 text-primary-700">
              <i class="fa-solid fa-file-circle-info text-primary-600"></i>
              <span class="font-semibold">Selected Hearing</span>
            </div>
            <div class="flex items-center gap-2 text-[11px]" id="panelMetaChips">
              <span class="hidden px-2 py-0.5 rounded-full bg-white border border-primary-200 text-primary-700" id="chipDate"><i class="fa-solid fa-calendar-day mr-1"></i><span></span></span>
              <span class="hidden px-2 py-0.5 rounded-full bg-white border border-primary-200 text-primary-700" id="chipTime"><i class="fa-solid fa-clock mr-1"></i><span></span></span>
            </div>
          </div>
          <div class="p-5">
            <div id="hearingDetailsEmpty" class="text-primary-700/80 text-sm bg-primary-50/70 border border-primary-100 rounded-xl p-4 flex items-center gap-3">
              <i class="fa-regular fa-hand-pointer text-primary-500 text-lg"></i>
              <span>Click any of the previous hearings to view details.</span>
            </div>
            <div id="hearingDetailsContent" class="space-y-4 hidden">
              
              
              <div>
                <p class="text-sm text-gray-700"><span class="font-semibold">Lupon Assigned:</span> <span id="panelLupon"></span></p>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <p class="text-sm text-gray-700"><span class="font-semibold">Attendance:</span> <span id="panelAttendance"></span></p>
                <p class="text-sm text-gray-700"><span class="font-semibold">Reason:</span> <span id="panelReason"></span></p>
              </div>
              <div>
                <p class="text-sm font-semibold text-gray-700 mb-1">Hearing Notes:</p>
                <pre class="whitespace-pre-wrap bg-primary-50/60 border border-primary-100 rounded-lg p-3 text-gray-800" id="panelDetails"></pre>
              </div>
              
              <div>
                <p class="text-sm font-semibold text-gray-700 mb-2"><i class="fa-solid fa-paperclip text-primary-500 mr-2"></i>Attachments:</p>
                <div id="panelAttachments" class="flex flex-wrap gap-2 text-sm"></div>
              </div>
              <div class="pt-2">
                <a id="panelCaseLink" href="#" class="inline-flex items-center gap-2 px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"><i class="fa fa-arrow-up-right-from-square"></i> View Case Details</a>
                <a id="generateLogBtn" href="#" target="_blank" class="ml-2 inline-flex items-center gap-2 px-3 py-2 bg-primary-500 text-white rounded hover:bg-primary-600 text-sm hidden"><i class="fa fa-file-lines"></i> Generate Minutes of the Meeting</a>
                <a id="downloadLogBtn" href="#" class="ml-2 inline-flex items-center gap-2 px-3 py-2 bg-primary-400 text-white rounded hover:bg-primary-500 text-sm hidden" title="Download PDF"><i class="fa fa-download"></i> Download PDF</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <script>
      // Inline details panel population
      (function(){
        const items = Array.from(document.querySelectorAll('.hearing-item'));
        const empty = document.getElementById('hearingDetailsEmpty');
        const content = document.getElementById('hearingDetailsContent');
        const chipDate = document.getElementById('chipDate');
        const chipTime = document.getElementById('chipTime');
        // helper: style attendance text as a colored chip
        function styleChip(el, value){
          if(!el) return;
          el.className = 'attendance-chip text-[11px] px-2 py-0.5 rounded-full border';
          if(!value){ el.classList.add('hidden'); return; }
          el.classList.remove('hidden');
          const v = String(value).toLowerCase();
          // If any unattended present, show Unattended state; otherwise show Present when present
          if(v.includes('unattended') || v.includes('failure')){
            el.classList.add('bg-amber-50','text-amber-700','border-amber-200');
            el.textContent = 'Failure to Appear';
          } else if (v.includes('present')){
            el.classList.add('bg-green-50','text-green-700','border-green-200');
            el.textContent = 'Present';
          } else {
            el.classList.add('bg-gray-50','text-gray-700','border-gray-200');
            el.textContent = v;
          }
        }
        const fields = {
          date: document.getElementById('panelDate'),
          time: document.getElementById('panelTime'),
          details: document.getElementById('panelDetails'),
          complainant: document.getElementById('panelComplainant'),
          complainantstatus: document.getElementById('panelComplainantStatus'),
          respondents: document.getElementById('panelRespondents'),
          respondentstatus: document.getElementById('panelRespondentStatus'),
          lupon: document.getElementById('panelLupon'),
          attendance: document.getElementById('panelAttendance'),
          reason: document.getElementById('panelReason'),
          complaintdetails: document.getElementById('panelComplaintDetails'),
          attachments: document.getElementById('panelAttachments'),
          caseLink: document.getElementById('panelCaseLink'),
        };

        function selectItem(el){
          items.forEach(i=> i.classList.remove('ring-2','ring-primary-300'));
          el.classList.add('ring-2','ring-primary-300');
        }

        // initialize attendance chips on list items
        items.forEach(item=>{
          const chip = item.querySelector('.attendance-chip');
          styleChip(chip, item.dataset.attendance || '');
        });

        items.forEach(item=>{
          item.addEventListener('click', function(){
            selectItem(this);
            if (empty) empty.classList.add('hidden');
            if (content) content.classList.remove('hidden');
            const d = this.dataset.date || '';
            const t = this.dataset.time || '';
            if (chipDate){ chipDate.classList.remove('hidden'); chipDate.querySelector('span').textContent = d; }
            if (chipTime){ chipTime.classList.remove('hidden'); chipTime.querySelector('span').textContent = t; }
            if (fields.details) fields.details.textContent = this.dataset.details || '';
            if (fields.complainant) fields.complainant.textContent = this.dataset.complainant || '';
            if (fields.complainantstatus) fields.complainantstatus.textContent = this.dataset.complainantstatus || '';
            if (fields.respondents) fields.respondents.textContent = this.dataset.respondents || '';
            if (fields.respondentstatus) fields.respondentstatus.textContent = this.dataset.respondentstatus || '';
            if (fields.lupon) fields.lupon.textContent = this.dataset.lupon || '';
            // attendance list in details: render per-person badges
            if (fields.attendance){
              const cont = fields.attendance;
              cont.innerHTML = '';

              // Try to use stored precomputed attendance if available
              const stored = (this.dataset.attendance || '').trim();
              if (stored) {
                const entries = stored.split(' | ').filter(Boolean);
                entries.forEach(function(ent){
                  const span = document.createElement('span');
                  span.className = 'inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs border mr-2 mb-2';
                  const low = String(ent).toLowerCase();
                  if (low.includes('present')) span.classList.add('bg-green-50','text-green-700','border-green-200');
                  else if (low.includes('unattended') || low.includes('failure')) span.classList.add('bg-amber-50','text-amber-700','border-amber-200');
                  else span.classList.add('bg-gray-50','text-gray-700','border-gray-200');
                  span.textContent = ent;
                  cont.appendChild(span);
                });
                // Augment with main respondent if missing from stored attendance (for older logs)
                const mrName = (this.dataset.mainrespondent || '').trim();
                const mrStatus = (this.dataset.mainrespondentstatus || '').trim() || 'Present';
                if (mrName) {
                  const currentText = cont.textContent.toLowerCase();
                  if (!currentText.includes(mrName.toLowerCase())) {
                    const ent = mrName + ': ' + (mrStatus.toLowerCase() === 'unattended' ? 'Failure to Appear' : mrStatus);
                    const span = document.createElement('span');
                    span.className = 'inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs border mr-2 mb-2';
                    const low = ent.toLowerCase();
                    if (low.includes('present')) span.classList.add('bg-green-50','text-green-700','border-green-200');
                    else if (low.includes('unattended') || low.includes('failure')) span.classList.add('bg-amber-50','text-amber-700','border-amber-200');
                    else span.classList.add('bg-gray-50','text-gray-700','border-gray-200');
                    span.textContent = ent;
                    cont.appendChild(span);
                  }
                }
              } else {
                // Build from available fields: complainant + respondents with their statuses
                const compName = (this.dataset.complainant || '').trim();
                const compStatus = (this.dataset.complainantstatus || '').trim() || 'Present';
                if (compName) {
                  const compLabel = compStatus.toLowerCase() === 'unattended' ? 'Failure to Appear' : compStatus;
                  const ent = compName + ': ' + compLabel;
                  const span = document.createElement('span');
                  span.className = 'inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs border mr-2 mb-2';
                  const low = String(ent).toLowerCase();
                  if (low.includes('present')) span.classList.add('bg-green-50','text-green-700','border-green-200');
                  else if (low.includes('unattended') || low.includes('failure')) span.classList.add('bg-amber-50','text-amber-700','border-amber-200');
                  else span.classList.add('bg-gray-50','text-gray-700','border-gray-200');
                  span.textContent = ent;
                  cont.appendChild(span);
                }

                const respNames = (this.dataset.respondents || '').split(',').map(s=>s.trim()).filter(Boolean);
                const respStats = (this.dataset.respondentstatus || '').split(',').map(s=>s.trim()).filter(Boolean);
                for (let i=0;i<respNames.length;i++){
                  const name = respNames[i] || ('Respondent ' + (i+1));
                  const stat = respStats[i] || 'Present';
                  const label = (String(stat).toLowerCase() === 'unattended') ? 'Failure to Appear' : stat;
                  const ent = name + ': ' + label;
                  const span = document.createElement('span');
                  span.className = 'inline-flex items-center gap-2 px-2 py-1 rounded-full text-xs border mr-2 mb-2';
                  const low = String(ent).toLowerCase();
                  if (low.includes('present')) span.classList.add('bg-green-50','text-green-700','border-green-200');
                  else if (low.includes('unattended') || low.includes('failure')) span.classList.add('bg-amber-50','text-amber-700','border-amber-200');
                  else span.classList.add('bg-gray-50','text-gray-700','border-gray-200');
                  span.textContent = ent;
                  cont.appendChild(span);
                }
              }
            }
            if (fields.reason) {
              const reasonText = (this.dataset.reason && this.dataset.reason.trim()) ? this.dataset.reason : 'N/A';
              fields.reason.textContent = reasonText;
            }
            if (fields.complaintdetails) fields.complaintdetails.textContent = this.dataset.complaintdetails || '';
            if (fields.caseLink) fields.caseLink.href = 'view_case_details.php?id=' + (this.dataset.caseid || '');
            // Update Generate Meeting Log button with log id
            const genBtn = document.getElementById('generateLogBtn');
            if (genBtn) {
              const logId = this.dataset.logid || '';
              if (logId) {
                genBtn.href = 'generate_meeting_log.php?log_id=' + encodeURIComponent(logId);
                genBtn.classList.remove('hidden');
              } else {
                genBtn.classList.add('hidden');
              }
            }
            // Update Download PDF button
            const dlBtn = document.getElementById('downloadLogBtn');
            if (dlBtn) {
              const logId = this.dataset.logid || '';
              if (logId) {
                dlBtn.href = 'generate_meeting_log.php?log_id=' + encodeURIComponent(logId) + '&download=1';
                dlBtn.classList.remove('hidden');
              } else {
                dlBtn.classList.add('hidden');
              }
            }
            // Render attachments chips
            if (fields.attachments) {
              const att = (this.dataset.attachments || '').trim();
              const cont = fields.attachments;
              cont.innerHTML = '';
              if (att) {
                att.split(';').filter(Boolean).forEach(function(path){
                  const name = path.split('/').pop();
                  const a = document.createElement('a');
                  a.href = path;
                  a.target = '_blank';
                  a.className = 'inline-flex items-center gap-1 px-2 py-1 rounded border border-primary-200 bg-white hover:bg-primary-50 text-primary-700';
                  a.innerHTML = '<i class="fa fa-paperclip"></i><span>'+ name +'</span>';
                  cont.appendChild(a);
                });
              } else {
                const span = document.createElement('span');
                span.className = 'text-gray-500';
                span.textContent = 'No attachments';
                cont.appendChild(span);
              }
            }

            // subtle attention pulse
            const panel = document.getElementById('hearingDetailsPanel');
            if(panel){
              panel.classList.remove('ring-2','ring-primary-300');
              void panel.offsetWidth; // reflow
              panel.classList.add('ring-2','ring-primary-300');
              setTimeout(()=> panel.classList.remove('ring-2','ring-primary-300'), 450);
            }
            // --- Populate left form (edit) when a hearing row is clicked ---
            try {
              const hearingIdInput = document.querySelector('input[name="hearing_id"]');
              if (hearingIdInput) {
                // set hearing_id (schedule id) if present
                hearingIdInput.value = this.dataset.hearingid || this.dataset.logid || '';
                // set date
                const dateInput = document.querySelector('input[name="hearing_date"]');
                if (dateInput) dateInput.value = this.dataset.date || dateInput.value;
                // parse times (dataset.time may be "1:00 PM - 2:00 PM" or "1:00 PM")
                function to24(t12){
                  if(!t12) return '';
                  // parse like '1:00 PM'
                  const m = t12.match(/(\d{1,2}:\d{2})\s*(AM|PM)?/i);
                  if(!m) return '';
                  let time = m[1];
                  const ampm = (m[2]||'').toUpperCase();
                  let [hh,mm] = time.split(':').map(Number);
                  if(ampm==='PM' && hh<12) hh += 12;
                  if(ampm==='AM' && hh===12) hh = 0;
                  return (hh<10? '0'+hh : ''+hh) + ':' + (mm<10? '0'+mm : ''+mm);
                }
                const t = (this.dataset.time||'').trim();
                let start='', end='';
                if(t.includes(' - ')){
                  [start,end] = t.split(' - ').map(s=>s.trim());
                } else { start = t; }
                const start24 = to24(start);
                const end24 = to24(end);
                const startInput = document.getElementById('hearing_time');
                const endInput = document.getElementById('hearing_end_time');
                if(startInput && start24) startInput.value = start24;
                if(endInput){ if(end24) endInput.value = end24; else endInput.value = ''; }
                // details
                const details = document.querySelector('textarea[name="details"]');
                if(details) details.value = this.dataset.details || details.value || '';
                // complainant status
                const compSel = document.getElementById('complainant_status_select');
                if(compSel){ compSel.value = (this.dataset.complainantstatus && String(this.dataset.complainantstatus).toLowerCase().includes('failure')) ? 'Unattended' : 'Present'; compSel.dispatchEvent(new Event('change')); }
                // respondents: match by name text in the form and set their selects
                const respNames = (this.dataset.respondents||'').split(',').map(s=>s.trim()).filter(Boolean).map(s=>s.toLowerCase());
                const respStats = (this.dataset.respondentstatus||'').split(',').map(s=>s.trim()).filter(Boolean);
                document.querySelectorAll('select.respondent-status-select').forEach(sel=>{
                  const p = sel.parentElement.querySelector('p');
                  const nm = p ? p.textContent.trim().toLowerCase() : '';
                  const idx = respNames.findIndex(rn => rn === nm);
                  if(idx >= 0){ sel.value = respStats[idx] || sel.value; sel.dispatchEvent(new Event('change')); }
                });
                // scroll to form top to edit
                const formTop = document.querySelector('.glass.rounded-2xl.p-8');
                if(formTop) formTop.scrollIntoView({behavior:'smooth', block:'start'});
              }
            } catch(e){ console.error(e); }
          });
        });
        
        // --- Filter / search logic for prevHearingsList ---
        (function(){
          const items = Array.from(document.querySelectorAll('.hearing-item'));
          const search = document.getElementById('hearingSearch');
          const month = document.getElementById('filterMonth');
          const year = document.getElementById('filterYear');
          const clear = document.getElementById('clearFilters');

          // populate year select from items
          const years = new Set();
          items.forEach(it=>{ const d = it.dataset.date; if(d){ const y = (new Date(d)).getFullYear(); if(!isNaN(y)) years.add(y); }});
          const yearsArr = Array.from(years).sort((a,b)=>b-a);
          if(year && yearsArr.length){ year.innerHTML = '<option value="All">All Years</option>' + yearsArr.map(y => '<option value="'+y+'">'+y+'</option>').join(''); }

          function applyFilters(){
            const q = (search && search.value || '').toLowerCase().trim();
            const m = (month && month.value) || 'All';
            const y = (year && year.value) || 'All';
            items.forEach(it=>{
              let show = true;
              if(q){ const hay = (it.textContent||'').toLowerCase(); if(!hay.includes(q)) show = false; }
              if(show && m && m !== 'All'){ const dt = it.dataset.date ? new Date(it.dataset.date) : null; if(!dt || (dt.getMonth()+1) != parseInt(m,10)) show = false; }
              if(show && y && y !== 'All'){ const dt = it.dataset.date ? new Date(it.dataset.date) : null; if(!dt || dt.getFullYear() != parseInt(y,10)) show = false; }
              const li = it.closest('li'); if(li) li.style.display = show ? '' : 'none';
            });
          }

          search && search.addEventListener('input', applyFilters);
          month && month.addEventListener('change', applyFilters);
          year && year.addEventListener('change', applyFilters);
          clear && clear.addEventListener('click', function(){ if(search) search.value=''; if(month) month.value='All'; if(year) year.value='All'; applyFilters(); });
        })();
      })();
      </script>

    </div>
    </div>
  </div>
  
  <?php include 'sidebar_.php'; ?>
</body>

</html>