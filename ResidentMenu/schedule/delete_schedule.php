<?php 
require_once('db-connect.php');
if(!isset($_GET['id'])){
    echo "<script> alert('Undefined Schedule ID.'); window.location.href = 'CalendarSec.php'; </script>";
    $conn->close();
    exit;
}

$stmt = $conn->prepare("DELETE FROM `schedule_list` WHERE hearingID = ?");
$stmt->bind_param("i", $_GET['id']);
if($stmt->execute()){
    echo "<script> alert('Event has been deleted successfully.'); window.location.href = 'CalendarSec.php'; </script>";

} else {
    echo "<pre>";
    echo "An Error occurred.<br>";
    echo "Error: " . $conn->error . "<br>";
    echo "</pre>";
}
$stmt->close();
$conn->close();


?>