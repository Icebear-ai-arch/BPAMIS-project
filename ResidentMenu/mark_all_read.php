<?php
session_start();
include '../server/server.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: notifications.php");
    exit();
}

$resident_id = $_SESSION['user_id'];

$sql = "UPDATE notifications SET is_read = 1 WHERE resident_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $resident_id);
$stmt->execute();

header("Location: notifications.php");
exit();
