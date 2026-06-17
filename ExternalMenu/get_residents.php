<?php
include '../server/server.php';
header('Content-Type: application/json; charset=utf-8');

$residents = [];
$query = "SELECT TRIM(CONCAT_WS(' ', First_Name, Middle_Name, Last_Name)) AS full_name FROM resident_info ORDER BY First_Name ASC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $name = trim(preg_replace('/\s+/', ' ', (string)$row['full_name']));
        if ($name !== '') {
            $residents[] = ['value' => $name];
        }
    }
}

echo json_encode($residents);
