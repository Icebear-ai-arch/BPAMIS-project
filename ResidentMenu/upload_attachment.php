<?php
session_start();
include '../server/server.php';

if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit; }

$residentId = (int)$_SESSION['user_id'];
$complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;

if($complaintId <= 0) { header("Location: view_complaints.php?error=invalid"); exit; }

// Fetch complaint and verify ownership
$stmt = $conn->prepare("SELECT Attachment_Path FROM complaint_info WHERE Complaint_ID = ? AND Resident_ID = ? LIMIT 1");
$stmt->bind_param("ii", $complaintId, $residentId);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
if($res->num_rows === 0){ $stmt->close(); header("Location: view_complaints.php?error=notfound"); exit; }
$complaint = $res->fetch_assoc();
$stmt->close();

$uploadDir = "../uploads/complaints/";
if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$allowedExt = ['jpg','jpeg','png','gif','webp','bmp','pdf'];
$newPaths = [];

foreach($_FILES['attachments']['tmp_name'] as $i=>$tmpName){
    if(!is_uploaded_file($tmpName)) continue;
    $name = basename($_FILES['attachments']['name'][$i]);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if(!in_array($ext,$allowedExt)) continue;

    $newName = uniqid("att_").".".$ext;
    $dest = $uploadDir.$newName;

    if(move_uploaded_file($tmpName, $dest)){
        $newPaths[] = "uploads/complaints/".$newName;
    }
}

// Merge with old attachments
$existing = array_filter(array_map('trim', explode(';', $complaint['Attachment_Path'] ?? '')));
$finalPaths = array_merge($existing, $newPaths);

// Update DB
$stmt = $conn->prepare("UPDATE complaint_info SET Attachment_Path = ? WHERE Complaint_ID = ? AND Resident_ID = ?");
$newPathStr = implode(';', $finalPaths);
$stmt->bind_param("sii", $newPathStr, $complaintId, $residentId);
$stmt->execute();
$stmt->close();

header("Location: view_complaint_details.php?id=".$complaintId."&success=uploaded");

exit;
?>
