<?php
require_once(__DIR__ . '/../../server/server.php');
include __DIR__ . '/../../controllers/session_control.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$resp = ['success' => false, 'remarks' => null, 'lupon' => null, 'case_original_id' => null, 'case_status' => null, 'case_title' => null, 'feedback' => null];

// Fetch schedule row (remarks and Case_ID)
$sched_stmt = $conn->prepare("SELECT id, Case_ID, Remarks FROM schedule_list WHERE id = ? LIMIT 1");
if ($sched_stmt) {
    $sched_stmt->bind_param('i', $id);
    if ($sched_stmt->execute()) {
        $r = bpamis_stmt_get_result($sched_stmt)->fetch_assoc();
        if ($r) {
            $resp['remarks'] = (string)($r['Remarks'] ?? '');
            $case_id = (int)($r['Case_ID'] ?? 0);

            // If we have a case_id, try to resolve a lupon/mediator name using the same coalescing logic
            if ($case_id > 0) {
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
                    LEFT JOIN mediation_info mi ON cs.Case_ID = mi.Case_ID
                    LEFT JOIN conciliation ci ON cs.Case_ID = ci.Case_ID
                    LEFT JOIN resolution ri ON cs.Case_ID = ri.Case_ID
                    LEFT JOIN settlement si ON cs.Case_ID = si.Case_ID
                    LEFT JOIN arbitration ai ON cs.Case_ID = ai.Case_ID
                    WHERE cs.Case_ID = ?
                ";
                $ls = $conn->prepare($lupon_sql);
                if ($ls) {
                    $ls->bind_param('i', $case_id);
                    if ($ls->execute()) {
                        $lr = bpamis_stmt_get_result($ls)->fetch_assoc();
                        if ($lr && !empty($lr['lupon_tagapamayapa'])) {
                            $resp['lupon'] = $lr['lupon_tagapamayapa'];
                        }
                    }
                    $ls->close();
                }
                
                // Also fetch case_original_id, case status and complaint title for display
                $case_info_sql = "SELECT Case_ID, case_original_id, Case_Status, Complaint_ID FROM CASE_INFO WHERE Case_ID = ? LIMIT 1";
                if ($ci = $conn->prepare($case_info_sql)) {
                    $ci->bind_param('i', $case_id);
                    if ($ci->execute()) {
                        $cinfo = bpamis_stmt_get_result($ci)->fetch_assoc();
                        if ($cinfo) {
                            $resp['case_original_id'] = isset($cinfo['case_original_id']) && trim($cinfo['case_original_id']) !== '' ? $cinfo['case_original_id'] : null;
                            $resp['case_status'] = isset($cinfo['Case_Status']) ? $cinfo['Case_Status'] : null;
                            $complaint_id = isset($cinfo['Complaint_ID']) ? intval($cinfo['Complaint_ID']) : 0;
                            if ($complaint_id > 0) {
                                $qt = $conn->prepare("SELECT Complaint_Title FROM complaint_info WHERE complaint_id = ? LIMIT 1");
                                if ($qt) {
                                    $qt->bind_param('i', $complaint_id);
                                    if ($qt->execute()) {
                                        $qtr = bpamis_stmt_get_result($qt)->fetch_assoc();
                                        if ($qtr && !empty($qtr['Complaint_Title'])) $resp['case_title'] = $qtr['Complaint_Title'];
                                    }
                                    $qt->close();
                                }
                            }
                        }
                    }
                    $ci->close();
                }

                // Fetch latest feedback message (if any) for this case
                $fb = $conn->prepare("SELECT message FROM feedback WHERE case_id = ? ORDER BY created_at DESC LIMIT 1");
                if ($fb) {
                    $fb->bind_param('i', $case_id);
                    if ($fb->execute()) {
                        $fbr = bpamis_stmt_get_result($fb)->fetch_assoc();
                        if ($fbr && !empty($fbr['message'])) $resp['feedback'] = $fbr['message'];
                    }
                    $fb->close();
                }
            }

            // Fallback: if still empty, try schedule_list.lupon or case_info.lupon_assign
            if (empty($resp['lupon'])) {
                // check schedule_list.lupon column
                $chk = $conn->query("SHOW COLUMNS FROM schedule_list LIKE 'lupon'");
                if ($chk && $chk->num_rows > 0) {
                    $q = $conn->prepare("SELECT lupon FROM schedule_list WHERE id = ? LIMIT 1");
                    if ($q) {
                        $q->bind_param('i', $id);
                        if ($q->execute()) {
                            $r2 = bpamis_stmt_get_result($q)->fetch_assoc();
                            if ($r2 && !empty($r2['lupon'])) $resp['lupon'] = $r2['lupon'];
                        }
                        $q->close();
                    }
                }
            }

            // Final fallback: case_info.lupon_assign
            if (empty($resp['lupon']) && !empty($case_id)) {
                $chk2 = $conn->query("SHOW COLUMNS FROM CASE_INFO LIKE 'lupon_assign'");
                if ($chk2 && $chk2->num_rows > 0) {
                    $q2 = $conn->prepare("SELECT lupon_assign FROM CASE_INFO WHERE Case_ID = ? LIMIT 1");
                    if ($q2) {
                        $q2->bind_param('i', $case_id);
                        if ($q2->execute()) {
                            $r3 = bpamis_stmt_get_result($q2)->fetch_assoc();
                            if ($r3 && !empty($r3['lupon_assign'])) $resp['lupon'] = $r3['lupon_assign'];
                        }
                        $q2->close();
                    }
                }
            }

            $resp['success'] = true;
        }
    }
    $sched_stmt->close();
}

echo json_encode($resp);

?>
