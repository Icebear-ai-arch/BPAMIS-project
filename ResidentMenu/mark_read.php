<?php
session_start();
include '../server/server.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['notif_id'])) {
    header("Location: notifications.php");
    exit();
}

$notif_id = $_POST['notif_id'];
$resident_id = $_SESSION['user_id'];

$sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND resident_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notif_id, $resident_id);
$stmt->execute();

header("Location: notifications.php");
exit();
?>